<?php
/**
 * Captcha credential validation and frontend activation gate.
 *
 * Free ships Google reCAPTCHA only. Pro registers hCaptcha / Turnstile via
 * {@see 'aio_login_captcha_providers'}.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Captcha;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Captcha\\Captcha_Validation' ) ) {
	/**
	 * Captcha_Validation
	 */
	final class Captcha_Validation {

		/**
		 * Built-in + filtered captcha providers.
		 *
		 * Each provider array may include:
		 * - validated_option (string)
		 * - enable_option (string) optional, for mutual exclusion
		 * - is_toggle_enabled (callable): () => bool
		 * - has_stored_keys (callable): () => bool
		 * - verify (callable): ( array $args ) => true|\WP_Error
		 *
		 * @return array<string, array<string, mixed>>
		 */
		public static function get_providers() {
			$providers = array(
				'recaptcha' => array(
					'validated_option'  => 'aio_login_google_recaptcha_validated',
					'enable_option'     => 'aio_login_google_recaptcha_enable',
					'is_toggle_enabled' => static function () {
						return 'on' === get_option( 'aio_login_google_recaptcha_enable', 'off' );
					},
					'has_stored_keys'   => static function () {
						$version = get_option( 'aio_login_google_recaptcha_version', 'v2' );
						return '' !== (string) get_option( 'aio_login_google_recaptcha_' . $version . '_site_key', '' )
							&& '' !== (string) get_option( 'aio_login_google_recaptcha_' . $version . '_secret_key', '' );
					},
					'verify'            => static function ( $args ) {
						$secret_key = isset( $args['secret_key'] ) ? sanitize_text_field( (string) $args['secret_key'] ) : '';
						$response   = Captcha_Validation::sanitize_captcha_response_token( $args['response'] ?? '' );
						return Captcha_Validation::verify_recaptcha_keys( $secret_key, $response );
					},
				),
			);

			/**
			 * Register additional captcha providers (Pro: hCaptcha, Turnstile).
			 *
			 * @param array<string, array<string, mixed>> $providers Provider definitions keyed by slug.
			 */
			$providers = apply_filters( 'aio_login_captcha_providers', $providers );

			return is_array( $providers ) ? $providers : array();
		}

		/**
		 * @param string $provider Provider slug.
		 * @return array<string, mixed>|null
		 */
		private static function get_provider( $provider ) {
			$providers = self::get_providers();
			return isset( $providers[ $provider ] ) && is_array( $providers[ $provider ] )
				? $providers[ $provider ]
				: null;
		}

		/**
		 * @param string $provider Provider slug.
		 * @return bool
		 */
		public static function is_validated( $provider ) {
			$def = self::get_provider( $provider );
			if ( null === $def || empty( $def['validated_option'] ) ) {
				return false;
			}

			return 'on' === get_option( (string) $def['validated_option'], 'off' );
		}

		/**
		 * @param string $provider  Provider slug.
		 * @param bool   $validated Validated state.
		 * @return void
		 */
		public static function set_validated( $provider, $validated ) {
			$def = self::get_provider( $provider );
			if ( null === $def || empty( $def['validated_option'] ) ) {
				return;
			}

			update_option( (string) $def['validated_option'], $validated ? 'on' : 'off' );
		}

		/**
		 * Whether captcha should run on the login frontend.
		 *
		 * @param string $provider Provider slug.
		 * @return bool
		 */
		public static function is_active_for_frontend( $provider ) {
			if ( ! self::is_toggle_enabled( $provider ) ) {
				return false;
			}

			if ( ! self::has_stored_keys( $provider ) ) {
				return false;
			}

			return self::is_validated( $provider );
		}

		/**
		 * @param string $provider Provider slug.
		 * @return bool
		 */
		public static function is_toggle_enabled( $provider ) {
			$def = self::get_provider( $provider );
			if ( null === $def || ! isset( $def['is_toggle_enabled'] ) || ! is_callable( $def['is_toggle_enabled'] ) ) {
				return false;
			}

			return (bool) call_user_func( $def['is_toggle_enabled'] );
		}

		/**
		 * @param string $provider Provider slug.
		 * @return bool
		 */
		public static function has_stored_keys( $provider ) {
			$def = self::get_provider( $provider );
			if ( null === $def || ! isset( $def['has_stored_keys'] ) || ! is_callable( $def['has_stored_keys'] ) ) {
				return false;
			}

			return (bool) call_user_func( $def['has_stored_keys'] );
		}

		/**
		 * Disable every captcha provider except the one being enabled.
		 *
		 * @param string $keep_provider Provider slug to keep on.
		 * @return void
		 */
		public static function disable_other_providers( $keep_provider ) {
			foreach ( self::get_providers() as $slug => $def ) {
				if ( $slug === $keep_provider || empty( $def['enable_option'] ) ) {
					continue;
				}
				update_option( (string) $def['enable_option'], 'off' );
			}
		}

		/**
		 * Verify provider credentials against the remote API.
		 *
		 * @param string               $provider Provider slug.
		 * @param array<string, mixed> $args     site_key, secret_key, response (optional), version (recaptcha).
		 * @return true|\WP_Error
		 */
		public static function verify_provider_keys( $provider, $args ) {
			$site_key   = isset( $args['site_key'] ) ? sanitize_text_field( (string) $args['site_key'] ) : '';
			$secret_key = isset( $args['secret_key'] ) ? sanitize_text_field( (string) $args['secret_key'] ) : '';
			$response   = self::sanitize_captcha_response( $args['response'] ?? '' );

			if ( '' === $site_key || '' === $secret_key ) {
				return new \WP_Error(
					'captcha_missing_keys',
					__( 'Site key and secret key are required.', 'change-wp-admin-login' )
				);
			}

			$def = self::get_provider( $provider );
			if ( null === $def || ! isset( $def['verify'] ) || ! is_callable( $def['verify'] ) ) {
				/**
				 * Fallback verify hook when a provider is not registered on the registry.
				 *
				 * @param true|\WP_Error|null $result   Null to signal unknown provider.
				 * @param string              $provider Provider slug.
				 * @param array               $args     Verification args.
				 */
				$filtered = apply_filters( 'aio_login_verify_captcha_provider', null, $provider, $args );
				if ( null !== $filtered ) {
					return $filtered;
				}

				return new \WP_Error(
					'captcha_invalid_provider',
					__( 'Unknown captcha provider.', 'change-wp-admin-login' )
				);
			}

			$args['site_key']   = $site_key;
			$args['secret_key'] = $secret_key;
			$args['response']   = $response;

			return call_user_func( $def['verify'], $args );
		}

		/**
		 * Preserve captcha token characters; do not run sanitize_text_field on JWT-like values.
		 *
		 * @param mixed $response Raw response token.
		 * @return string
		 */
		public static function sanitize_captcha_response_token( $response ) {
			return self::sanitize_captcha_response( $response );
		}

		/**
		 * @param mixed $response Raw response token.
		 * @return string
		 */
		private static function sanitize_captcha_response( $response ) {
			if ( ! is_scalar( $response ) ) {
				return '';
			}

			return trim( (string) wp_unslash( $response ) );
		}

		/**
		 * Verify reCAPTCHA credentials.
		 *
		 * @param string $secret_key Secret key.
		 * @param string $response   Optional client token.
		 * @return true|\WP_Error
		 */
		public static function verify_recaptcha_keys( $secret_key, $response = '' ) {
			if ( '' === $response ) {
				return self::verify_recaptcha_secret_only( $secret_key );
			}

			$remote = wp_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				array(
					'timeout' => 15,
					'body'    => array(
						'secret'   => $secret_key,
						'response' => $response,
					),
				)
			);

			$result = self::interpret_siteverify_response( $remote );
			if ( true === $result ) {
				return true;
			}

			return self::verify_recaptcha_secret_only( $secret_key );
		}

		/**
		 * Google accepts a valid secret key with no response token and returns success=true.
		 *
		 * @param string $secret_key Secret key.
		 * @return true|\WP_Error
		 */
		private static function verify_recaptcha_secret_only( $secret_key ) {
			$remote = wp_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				array(
					'timeout' => 15,
					'body'    => array(
						'secret' => $secret_key,
					),
				)
			);

			return self::interpret_siteverify_response( $remote );
		}

		/**
		 * @param array|\WP_Error $remote Remote response.
		 * @return true|\WP_Error
		 */
		private static function interpret_siteverify_response( $remote ) {
			if ( is_wp_error( $remote ) ) {
				return new \WP_Error(
					'captcha_network',
					__( 'Unable to connect to the captcha provider. Please try again.', 'change-wp-admin-login' )
				);
			}

			$body = json_decode( wp_remote_retrieve_body( $remote ), true );
			if ( ! is_array( $body ) ) {
				return new \WP_Error(
					'captcha_invalid_response',
					__( 'Unexpected response from the captcha provider.', 'change-wp-admin-login' )
				);
			}

			if ( ! empty( $body['success'] ) ) {
				return true;
			}

			$error_codes = array();
			if ( ! empty( $body['error-codes'] ) && is_array( $body['error-codes'] ) ) {
				$error_codes = $body['error-codes'];
			}

			if ( in_array( 'invalid-input-secret', $error_codes, true ) || in_array( 'missing-input-secret', $error_codes, true ) ) {
				return new \WP_Error(
					'captcha_invalid_secret',
					__( 'Secret key is invalid. Please check your credentials.', 'change-wp-admin-login' )
				);
			}

			// Keys are valid but the current domain is not listed in the provider console (common on local/staging).
			if ( in_array( 'hostname-mismatch', $error_codes, true ) ) {
				return true;
			}

			if ( in_array( 'invalid-input-response', $error_codes, true ) ) {
				return new \WP_Error(
					'captcha_invalid_credentials',
					__( 'Captcha credentials are invalid. Please verify your site key and secret key.', 'change-wp-admin-login' )
				);
			}

			if ( in_array( 'missing-input-response', $error_codes, true ) ) {
				return new \WP_Error(
					'captcha_response_required',
					__( 'Complete the captcha challenge to test the connection.', 'change-wp-admin-login' )
				);
			}

			if ( in_array( 'timeout-or-duplicate', $error_codes, true ) ) {
				return new \WP_Error(
					'captcha_token_expired',
					__( 'Captcha token expired. Please test the connection again.', 'change-wp-admin-login' )
				);
			}

			return new \WP_Error(
				'captcha_verification_failed',
				__( 'Could not verify captcha credentials. Please check your keys and try again.', 'change-wp-admin-login' )
			);
		}
	}
}
