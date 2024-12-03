<?php
/**
 * Class Google_Recaptcha
 *
 * @package AIO Login
 */

namespace AIO_Login\Google_Recaptcha;

use AIO_Login\Helper\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Google_Recaptcha\\Google_Recaptcha' ) ) {
	/**
	 * Class Google_Recaptcha
	 */
	class Google_Recaptcha {
		/**
		 * Register settings.
		 */

		/**
		 * Is google recaptcha enabled.
		 *
		 * @var bool $is_enabled Is google recaptcha enabled.
		 */
		private $is_enabled;

		/**
		 * Google recaptcha version.
		 *
		 * @var string $version Google recaptcha version.
		 */
		private $version;

		/**
		 * Google recaptcha site key.
		 *
		 * @var string $site_key Google recaptcha site key.
		 */
		private $site_key;

		/**
		 * Google recaptcha secret key.
		 *
		 * @var string $secret_key Google recaptcha secret key.
		 */
		private $secret_key;

		/**
		 * Google recaptcha theme.
		 *
		 * @var string $theme Google recaptcha theme.
		 */
		private $theme;

		/**
		 * Google recaptcha threshold.
		 *
		 * @var string $threshold Google recaptcha threshold.
		 */
		private $threshold;

		/**
		 * Google recaptcha location.
		 *
		 * @var string $location Google recaptcha location.
		 */
		private $location;

		/**
		 * Google_Recaptcha constructor.
		 */
		public function __construct() {
			$this->is_enabled = get_option( 'aio_login_google_recaptcha_enable', 'off' );
			$this->version    = get_option( 'aio_login_google_recaptcha_version', 'v2' );
			$this->site_key   = get_option( 'aio_login_google_recaptcha_' . $this->version . '_site_key' );
			$this->secret_key = get_option( 'aio_login_google_recaptcha_' . $this->version . '_secret_key' );
			$this->theme      = get_option( 'aio_login_google_recaptcha_v2_theme', 'light' );
			$this->threshold  = get_option( 'aio_login_google_recaptcha_v3_threshold', '0.5' );

			$this->is_enabled = 'on' === $this->is_enabled;


			if ( $this->is_enabled() ) {
				add_filter( 'aio_login__wp_authenticate_user', array( $this, 'wp_authenticate_user' ) );
				add_action( 'login_enqueue_scripts', array( $this, 'login_enqueue_scripts' ) );
				add_action( 'login_form', array( $this, 'login_form' ) );
			}

			add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		}

		/**
		 * WP Authenticate user.
		 *
		 * @param \WP_User $user WP_User object.
		 *
		 * @return \WP_User|\WP_Error
		 */
		public function wp_authenticate_user( $user ) {

			if ( is_wp_error( $user ) ) {
				return $user;
			}

			$g_recaptcha_response = sanitize_text_field( wp_unslash( filter_input( INPUT_POST, 'g-recaptcha-response' ) ) );
			if ( empty( $g_recaptcha_response ) ) {
				return new \WP_Error( 'empty_g_recaptcha_response', __( 'Please verify that you are not a robot.', 'change-wp-admin-login' ) );
			}

			$remote_request = wp_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				array(
					'body' => array(
						'secret'   => $this->secret_key,
						'response' => $g_recaptcha_response,
					),
				)
			);
			$api_response   = wp_remote_retrieve_body( $remote_request );
			$response       = json_decode( $api_response, true );

			if ( isset( $response['success'] ) && true === $response['success'] ) {
				if ( 'v2' === $this->version ) {
					return $user;
				}

				if ( 'v3' === $this->version ) {
					if ( isset( $response['score'] ) && $response['score'] >= $this->threshold && isset( $response['action'] ) && 'login' === $response['action'] ) {
						return $user;
					}
				}

				return new \WP_Error( 'invalid_g_recaptcha_response', __( 'Please verify that you are not a robot.', 'change-wp-admin-login' ) );
			} elseif ( isset( $response['error-codes'][0] ) ) {
				switch ( $response['error-codes'][0] ) {
					case 'missing-input-secret':
						return new \WP_Error( 'cwpal_recaptcha_error', __( 'The secret parameter is missing.', 'change-wp-admin-login' ) );
					case 'invalid-input-secret':
						return new \WP_Error( 'cwpal_recaptcha_error', __( 'The secret parameter is invalid or malformed.', 'change-wp-admin-login' ) );
					case 'missing-input-response':
						return new \WP_Error( 'cwpal_recaptcha_error', __( 'The response parameter is missing.', 'change-wp-admin-login' ) );
					case 'invalid-input-response':
						return new \WP_Error( 'cwpal_recaptcha_error', __( 'The response parameter is invalid or malformed.', 'change-wp-admin-login' ) );
					case 'bad-request':
						return new \WP_Error( 'cwpal_recaptcha_error', __( 'The request is invalid or malformed.', 'change-wp-admin-login' ) );
					case 'timeout-or-duplicate':
						return new \WP_Error( 'cwpal_recaptcha_error', __( 'The response is no longer valid: either is too old or has been used previously.', 'change-wp-admin-login' ) );
				}
			}
			return $user;
		}

		/**
		 * Login enqueue scripts.
		 */
		public function login_enqueue_scripts() {
			if ( 'v2' === $this->version ) {
				echo '<style type="text/css">';
				
				if ( in_array( get_option( 'aio_login__customization_templates', 'default' ), array( 'template-2', 'default' ), true ) ) {
					echo '#login {
						width: 352px !important;
					}';
				}
				
				echo '.g-recaptcha {
						margin-bottom: 20px !important;
						display: flex;
						justify-content: center;
					}
				</style>';

				// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				wp_register_script( 'aio-login-g-recaptcha', 'https://google.com/recaptcha/api.js', array(), null, true );
			}

			if ( 'v3' === $this->version ) {
				// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				wp_register_script( 'aio-login-g-recaptcha', 'https://google.com/recaptcha/api.js?render=' . $this->site_key, array(), null, true );
				wp_add_inline_script(
					'aio-login-g-recaptcha',
					'grecaptcha.ready( function() {
						grecaptcha.execute( "' . $this->site_key . '", { action: "login" } )
							.then( function( token ) {
								document.getElementById( "g-recaptcha-response" ).value = token;
							} );
					} );'
				);
			}

			wp_enqueue_script( 'aio-login-g-recaptcha' );
		}

		/**
		 * Login form.
		 */
		public function login_form() {
			if ( 'v2' === $this->version ) {
				echo '<div class="g-recaptcha" data-sitekey="' . esc_attr( $this->site_key ) . '" data-theme="' . esc_attr( $this->theme ) . '"></div>';
			}

			if ( 'v3' === $this->version ) {
				echo '<input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" />';
			}
		}

		/**
		 * Is google recaptcha enabled.
		 *
		 * @return bool
		 */
		public function is_enabled() {
			return $this->is_enabled;
		}

		public function rest_api_init() {
			register_rest_route(
				'aio-login/grecaptcha',
				'/get-settings',
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_settings' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/grecaptcha',
				'/save-settings',
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'save_settings' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);
		}

		public function get_settings() {
			$response = array(
				'enabled'       => $this->is_enabled,
				'version'       => $this->version,
				'v2_site_key'   => get_option( 'aio_login_google_recaptcha_v2_site_key', '' ),
				'v2_secret_key' => get_option( 'aio_login_google_recaptcha_v2_secret_key', '' ),
				'theme'         => $this->theme,
				'v3_site_key'   => get_option( 'aio_login_google_recaptcha_v3_site_key', '' ),
				'v3_secret_key' => get_option( 'aio_login_google_recaptcha_v3_secret_key', '' ),
				'threshold'     => $this->threshold,
				'nonce'         => wp_create_nonce( 'google-recaptcha' ),
			);

			return rest_ensure_response( $response );
		}

		public function save_settings( \WP_REST_Request $request ) {
			$params = $request->get_params();

			if ( isset( $params['_wpnonce'] ) && wp_verify_nonce( $params['_wpnonce'], 'google-recaptcha' ) ) {
				$enabled        = 'off';
				$version        = sanitize_text_field( wp_unslash( $params['version'] ) );
				$v2_site_key    = sanitize_text_field( wp_unslash( $params['v2_site_key'] ) );
				$v2_secret_key  = sanitize_text_field( wp_unslash( $params['v2_secret_key'] ) );
				$theme          = sanitize_text_field( wp_unslash( $params['theme'] ) );
				$v3_site_key    = sanitize_text_field( wp_unslash( $params['v3_site_key'] ) );
				$v3_secret_key  = sanitize_text_field( wp_unslash( $params['v3_secret_key'] ) );
				$threshold      = sanitize_text_field( wp_unslash( $params['threshold'] ) );

				if ( isset( $params['enabled'] ) && true === $params['enabled'] ) {
					$enabled = 'on';
				}

				update_option( 'aio_login_google_recaptcha_enable', $enabled );
				update_option( 'aio_login_google_recaptcha_version', $version );

				if ( 'v2' === $version ) {
					update_option( 'aio_login_google_recaptcha_v2_site_key', $v2_site_key );
					update_option( 'aio_login_google_recaptcha_v2_secret_key', $v2_secret_key );
					update_option( 'aio_login_google_recaptcha_v2_theme', $theme );
				}

				if ( 'v3' === $version ) {
					update_option( 'aio_login_google_recaptcha_v3_site_key', $v3_site_key );
					update_option( 'aio_login_google_recaptcha_v3_secret_key', $v3_secret_key );
					update_option( 'aio_login_google_recaptcha_v3_threshold', $threshold );
				}

				return rest_ensure_response(
					array(
						'success' => true,
						'message' => __( 'Settings saved successfully', 'change-wp-admin-login' ),
					)
				);
			}

			return new \WP_Error( 'invalid-nonce', __( 'Nonce verification failed', 'change-wp-admin-login' ), array( 'status' => 401 ) );
		}

		/**
		 * Getting instance of Google_Recaptcha.
		 *
		 * @return Google_Recaptcha
		 */
		public static function get_instance() {
			static $instance = null;

			if ( is_null( $instance ) ) {
				$instance = new Google_Recaptcha();
			}

			return $instance;
		}
	}
}
