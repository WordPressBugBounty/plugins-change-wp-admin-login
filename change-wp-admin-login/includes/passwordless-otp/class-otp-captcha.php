<?php
/**
 * Reuse active captcha providers for passwordless OTP requests.
 *
 * Free handles Google reCAPTCHA. Pro providers register via
 * {@see 'aio_login_captcha_providers'} and OTP filters.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Captcha' ) ) {
	/**
	 * OTP_Captcha
	 */
	final class OTP_Captcha {

		/**
		 * Whether any captcha is enabled site-wide.
		 *
		 * @return bool
		 */
		public static function is_required() {
			return self::get_active_provider() !== '';
		}

		/**
		 * @return string Provider slug or empty string.
		 */
		public static function get_active_provider() {
			/**
			 * Priority order of captcha providers for passwordless OTP.
			 * Free defaults to reCAPTCHA only; Pro appends hCaptcha / Turnstile.
			 *
			 * @param string[] $order Provider slugs.
			 */
			$order = apply_filters( 'aio_login_otp_captcha_provider_order', array( 'recaptcha' ) );
			if ( ! is_array( $order ) ) {
				$order = array( 'recaptcha' );
			}

			foreach ( $order as $slug ) {
				$slug = (string) $slug;
				if (
					'' !== $slug
					&& class_exists( '\AIO_Login\Captcha\Captcha_Validation' )
					&& \AIO_Login\Captcha\Captcha_Validation::is_active_for_frontend( $slug )
				) {
					return $slug;
				}
			}

			return '';
		}

		/**
		 * Config for login script (site keys only).
		 *
		 * @return array<string, mixed>
		 */
		public static function get_frontend_config() {
			$provider = self::get_active_provider();
			$config   = array(
				'provider' => $provider,
			);

			if ( 'recaptcha' === $provider ) {
				$version            = get_option( 'aio_login_google_recaptcha_version', 'v2' );
				$config['version']  = $version;
				$config['site_key'] = (string) get_option( 'aio_login_google_recaptcha_' . $version . '_site_key', '' );
				$config['theme']    = (string) get_option( 'aio_login_google_recaptcha_v2_theme', 'light' );
			}

			/**
			 * Augment OTP captcha frontend config (Pro fills hCaptcha / Turnstile).
			 *
			 * @param array  $config   Config for the active provider.
			 * @param string $provider Active provider slug.
			 */
			$config = apply_filters( 'aio_login_otp_captcha_frontend_config', $config, $provider );

			return is_array( $config ) ? $config : array( 'provider' => $provider );
		}

		/**
		 * Verify captcha token from POST.
		 *
		 * @return true|\WP_Error
		 */
		public static function verify_request() {
			$provider = self::get_active_provider();
			if ( '' === $provider ) {
				return true;
			}

			if ( 'recaptcha' === $provider ) {
				return self::verify_recaptcha();
			}

			/**
			 * Verify a Pro (or other) captcha provider for OTP requests.
			 *
			 * @param true|\WP_Error|null $result   Null means unhandled.
			 * @param string              $provider Provider slug.
			 */
			$result = apply_filters( 'aio_login_otp_captcha_verify_request', null, $provider );
			if ( null !== $result ) {
				return $result;
			}

			return true;
		}

		/**
		 * @return true|\WP_Error
		 */
		private static function verify_recaptcha() {
			$response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' === $response ) {
				return new \WP_Error( 'captcha_required', __( 'Please verify that you are not a robot.', 'change-wp-admin-login' ) );
			}

			$version    = get_option( 'aio_login_google_recaptcha_version', 'v2' );
			$secret_key = (string) get_option( 'aio_login_google_recaptcha_' . $version . '_secret_key', '' );
			if ( '' === $secret_key ) {
				return new \WP_Error( 'captcha_misconfigured', __( 'Captcha is not configured correctly.', 'change-wp-admin-login' ) );
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

			if ( is_wp_error( $remote ) ) {
				return new \WP_Error( 'captcha_network', __( 'Unable to verify captcha. Please try again.', 'change-wp-admin-login' ) );
			}

			$body = json_decode( wp_remote_retrieve_body( $remote ), true );
			if ( empty( $body['success'] ) ) {
				return new \WP_Error( 'captcha_invalid', __( 'Please verify that you are not a robot.', 'change-wp-admin-login' ) );
			}

			if ( 'v3' === $version ) {
				$threshold = (float) get_option( 'aio_login_google_recaptcha_v3_threshold', 0.5 );
				$score     = isset( $body['score'] ) ? (float) $body['score'] : 0;
				if ( $score < $threshold ) {
					return new \WP_Error( 'captcha_invalid', __( 'Please verify that you are not a robot.', 'change-wp-admin-login' ) );
				}
			}

			return true;
		}
	}
}
