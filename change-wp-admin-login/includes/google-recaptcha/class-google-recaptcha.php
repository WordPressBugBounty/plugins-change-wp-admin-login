<?php
/**
 * Class Google_Recaptcha
 *
 * @package AIO Login
 */

namespace AIO_Login\Google_Recaptcha;

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

			add_action( 'admin_init', array( $this, 'register_settings' ), -3, 0 );
			add_action( 'aio_login__tab_security_grecaptcha', array( $this, 'settings_sections' ) );
			add_action( 'wp_ajax_aio_login_save_recaptcha', array( $this, 'save_recaptcha' ) );

			if ( $this->is_enabled() ) {
				add_filter( 'aio_login__wp_authenticate_user', array( $this, 'wp_authenticate_user' ) );
				add_action( 'login_enqueue_scripts', array( $this, 'login_enqueue_scripts' ) );
				add_action( 'login_form', array( $this, 'login_form' ) );
			}
		}

		/**
		 * Registering settings
		 */
		public function register_settings() {
			register_setting( 'aio_login__google_recaptcha', 'aio_login_google_recaptcha_enable' );
			register_setting( 'aio_login__google_recaptcha', 'aio_login_google_recaptcha_version' );
			register_setting( 'aio_login__google_recaptcha', 'aio_login_google_recaptcha_v2_site_key' );
			register_setting( 'aio_login__google_recaptcha', 'aio_login_google_recaptcha_v2_secret_key' );
			register_setting( 'aio_login__google_recaptcha', 'aio_login_google_recaptcha_v2_theme' );
			register_setting( 'aio_login__google_recaptcha', 'aio_login_google_recaptcha_v3_site_key' );
			register_setting( 'aio_login__google_recaptcha', 'aio_login_google_recaptcha_v3_secret_key' );

			add_settings_section(
				'aio_login__google_recaptcha',
				__( 'Google reCAPTCHA', 'aio-login' ),
				'__return_null',
				'page=aio-login&tab=security&sub-tab=google-recaptcha'
			);

			/**
			 * Toggle switch
			 */
			add_settings_field(
				'aio_login_google_recaptcha_enable',
				__( 'Enable', 'aio-login' ),
				array( $this, 'google_recaptcha_enable' ),
				'page=aio-login&tab=security&sub-tab=google-recaptcha',
				'aio_login__google_recaptcha',
				array(
					'label_for' => 'aio_login_google_recaptcha_enable',
				)
			);

			/**
			 * Version selector
			 */
			add_settings_field(
				'aio_login_google_recaptcha_version',
				__( 'Version', 'aio-login' ),
				array( $this, 'google_recaptcha_version' ),
				'page=aio-login&tab=security&sub-tab=google-recaptcha',
				'aio_login__google_recaptcha',
				array(
					'label_for' => 'aio_login_google_recaptcha_version',
				)
			);

			/**
			 * V2 settings
			 */
			add_settings_field(
				'aio_login_google_recaptcha_v2_site_key',
				__( 'Site Key', 'aio-login' ),
				array( $this, 'google_recaptcha_v2_site_key' ),
				'page=aio-login&tab=security&sub-tab=google-recaptcha',
				'aio_login__google_recaptcha',
				array(
					'label_for' => 'aio_login_google_recaptcha_v2_site_key',
				)
			);
			add_settings_field(
				'aio_login_google_recaptcha_v2_secret_key',
				__( 'Secret Key', 'aio-login' ),
				array( $this, 'google_recaptcha_v2_secret_key' ),
				'page=aio-login&tab=security&sub-tab=google-recaptcha',
				'aio_login__google_recaptcha',
				array(
					'label_for' => 'aio_login_google_recaptcha_v2_secret_key',
				)
			);
			add_settings_field(
				'aio_login_google_recaptcha_v2_theme',
				__( 'Theme', 'aio-login' ),
				array( $this, 'google_recaptcha_v2_theme' ),
				'page=aio-login&tab=security&sub-tab=google-recaptcha',
				'aio_login__google_recaptcha',
				array(
					'label_for' => 'aio_login_google_recaptcha_v2_theme',
				)
			);

			/**
			 * V3 settings
			 */
			add_settings_field(
				'aio_login_google_recaptcha_v3_site_key',
				__( 'Site Key', 'aio-login' ),
				array( $this, 'google_recaptcha_v3_site_key' ),
				'page=aio-login&tab=security&sub-tab=google-recaptcha',
				'aio_login__google_recaptcha',
				array(
					'label_for' => 'aio_login_google_recaptcha_v3_site_key',
				)
			);
			add_settings_field(
				'aio_login_google_recaptcha_v3_secret_key',
				__( 'Secret Key', 'aio-login' ),
				array( $this, 'google_recaptcha_v3_secret_key' ),
				'page=aio-login&tab=security&sub-tab=google-recaptcha',
				'aio_login__google_recaptcha',
				array(
					'label_for' => 'aio_login_google_recaptcha_v3_secret_key',
				)
			);
			add_settings_field(
				'aio_login_google_recaptcha_v3_threshold',
				__( 'Threshold', 'aio-login' ),
				array( $this, 'google_recaptcha_v3_threshold' ),
				'page=aio-login&tab=security&sub-tab=google-recaptcha',
				'aio_login__google_recaptcha',
				array(
					'label_for' => 'aio_login_google_recaptcha_v3_threshold',
				)
			);
		}

		/**
		 * Google reCAPTCHA enable.
		 *
		 * @param array $args Arguments.
		 */
		public function google_recaptcha_enable( $args ) {
			$checked = get_option( $args['label_for'], 'off' );
			echo '<div class="aio-login__toggle-switch-wrapper">
				<input class="aio-login__toggle-field" type="checkbox" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" ' . checked( $checked, 'on', false ) . ' value="on" />
				<label for="' . esc_attr( $args['label_for'] ) . '" class="aio-login__toggle-switch">
					<span class="aio-login__toggle-indicator"></span>
				</label>
			</div>
			
			<p class="description">
				<strong>' . esc_html__( 'Captcha or "are you human" verification ensures bots can\'t attack your login page and provides additional protection with minimal impact to users.', 'aio-login' ) . '</strong>
			</p>';
		}

		/**
		 * Google reCAPTCHA version.
		 *
		 * @param array $args Arguments.
		 */
		public function google_recaptcha_version( $args ) {
			$selected = get_option( $args['label_for'], 'v2' );
			echo '<select @change="e => toggleFields( e.target.value )" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" class="regular-text">
				<option value="v2" ' . selected( $selected, 'v2', false ) . '>' . esc_html__( 'v2', 'aio-login' ) . '</option>
				<option value="v3" ' . selected( $selected, 'v3', false ) . '>' . esc_html__( 'v3', 'aio-login' ) . '</option>
			</select>';
		}

		/**
		 * Google reCAPTCHA v2 site key.
		 *
		 * @param array $args Arguments.
		 */
		public function google_recaptcha_v2_site_key( $args ) {
			echo '<input type="text" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" class="regular-text" value="' . esc_attr( get_option( $args['label_for'] ) ) . '" />
			<p class="description">
				<strong>' . esc_attr__( 'Enter your site\'s unique reCAPTCHA site key here.', 'aio-login' ) . '</strong>
			</p>';
		}

		/**
		 * Google reCAPTCHA v2 secret key.
		 *
		 * @param array $args Arguments.
		 */
		public function google_recaptcha_v2_secret_key( $args ) {
			echo '<input type="text" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" class="regular-text" value="' . esc_attr( get_option( $args['label_for'] ) ) . '" />
			<p class="description">
				<strong>' . esc_attr__( 'Enter your site\'s secret reCAPTCHA key here.', 'aio-login' ) . '</strong>
			</p>';
		}

		/**
		 * Google reCAPTCHA v2 theme.
		 *
		 * @param array $args Arguments.
		 */
		public function google_recaptcha_v2_theme( $args ) {
			$selected = get_option( $args['label_for'], 'light' );
			echo '<select name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" class="regular-text">
				<option value="light" ' . selected( $selected, 'light', false ) . '>' . esc_html__( 'Light', 'aio-login' ) . '</option>
				<option value="dark" ' . selected( $selected, 'dark', false ) . '>' . esc_html__( 'Dark', 'aio-login' ) . '</option>
			</select>';
		}

		/**
		 * Google reCAPTCHA v3 site key.
		 *
		 * @param array $args Arguments.
		 */
		public function google_recaptcha_v3_site_key( $args ) {
			echo '<input type="text" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" class="regular-text" value="' . esc_attr( get_option( $args['label_for'] ) ) . '" />
			<p class="description">
				<strong>' . esc_attr__( 'Enter your site\'s unique reCAPTCHA site key here.', 'aio-login' ) . '</strong>
			</p>';
		}

		/**
		 * Google reCAPTCHA v3 secret key.
		 *
		 * @param array $args Arguments.
		 */
		public function google_recaptcha_v3_secret_key( $args ) {
			echo '<input type="text" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" class="regular-text" value="' . esc_attr( get_option( $args['label_for'] ) ) . '" />
			<p class="description">
				<strong>' . esc_attr__( 'Enter your site\'s secret reCAPTCHA key here.', 'aio-login' ) . '</strong>
			</p>';
		}

		/**
		 * Google reCAPTCHA v3 threshold.
		 *
		 * @param array $args Arguments.
		 */
		public function google_recaptcha_v3_threshold( $args ) {
			$selected = get_option( $args['label_for'], '0.5' );
			echo '<select name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" class="regular-text">
				<option value="0.1" ' . selected( $selected, '0.1', false ) . '>' . esc_html__( '0.1', 'aio-login' ) . '</option>
				<option value="0.2" ' . selected( $selected, '0.2', false ) . '>' . esc_html__( '0.2', 'aio-login' ) . '</option>
				<option value="0.3" ' . selected( $selected, '0.3', false ) . '>' . esc_html__( '0.3', 'aio-login' ) . '</option>
				<option value="0.4" ' . selected( $selected, '0.4', false ) . '>' . esc_html__( '0.4', 'aio-login' ) . '</option>
				<option value="0.5" ' . selected( $selected, '0.5', false ) . '>' . esc_html__( '0.5', 'aio-login' ) . '</option>
			</select>';
		}

		/**
		 * Settings fields.
		 */
		public function settings_sections() {
			echo '<aio-login-settings-form action="aio_login_save_recaptcha">
				<template v-slot:settings-fields>';
					settings_fields( 'aio_login__google_recaptcha' );
					do_settings_sections( 'page=aio-login&tab=security&sub-tab=google-recaptcha' );
				echo '</template>
			</aio-login-settings-form>';
		}

		/**
		 * Save recaptcha.
		 */
		public function save_recaptcha() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'aio-login' ) );
			}
			if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'aio_login__google_recaptcha-options' ) ) {
				if ( isset( $_POST['aio_login_google_recaptcha_enable'] ) ) {
					update_option( 'aio_login_google_recaptcha_enable', 'on' );
				} else {
					update_option( 'aio_login_google_recaptcha_enable', 'off' );
				}

				if ( isset( $_POST['aio_login_google_recaptcha_version'] ) ) {
					update_option( 'aio_login_google_recaptcha_version', sanitize_text_field( wp_unslash( $_POST['aio_login_google_recaptcha_version'] ) ) );
				}

				if ( isset( $_POST['aio_login_google_recaptcha_v2_site_key'] ) ) {
					update_option( 'aio_login_google_recaptcha_v2_site_key', sanitize_text_field( wp_unslash( $_POST['aio_login_google_recaptcha_v2_site_key'] ) ) );
				}

				if ( isset( $_POST['aio_login_google_recaptcha_v2_secret_key'] ) ) {
					update_option( 'aio_login_google_recaptcha_v2_secret_key', sanitize_text_field( wp_unslash( $_POST['aio_login_google_recaptcha_v2_secret_key'] ) ) );
				}

				if ( isset( $_POST['aio_login_google_recaptcha_v2_theme'] ) ) {
					update_option( 'aio_login_google_recaptcha_v2_theme', sanitize_text_field( wp_unslash( $_POST['aio_login_google_recaptcha_v2_theme'] ) ) );
				}

				if ( isset( $_POST['aio_login_google_recaptcha_v3_site_key'] ) ) {
					update_option( 'aio_login_google_recaptcha_v3_site_key', sanitize_text_field( wp_unslash( $_POST['aio_login_google_recaptcha_v3_site_key'] ) ) );
				}

				if ( isset( $_POST['aio_login_google_recaptcha_v3_secret_key'] ) ) {
					update_option( 'aio_login_google_recaptcha_v3_secret_key', sanitize_text_field( wp_unslash( $_POST['aio_login_google_recaptcha_v3_secret_key'] ) ) );
				}

				if ( isset( $_POST['aio_login_google_recaptcha_v3_threshold'] ) ) {
					update_option( 'aio_login_google_recaptcha_v3_threshold', sanitize_text_field( wp_unslash( $_POST['aio_login_google_recaptcha_v3_threshold'] ) ) );
				}

				wp_send_json_success(
					array(
						'message' => esc_html__( 'Google reCaptcha settings saved successfully.', 'aio-login' ),
					),
					200
				);
			}

			exit( 0 );
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
				return new \WP_Error( 'empty_g_recaptcha_response', __( 'Please verify that you are not a robot.', 'aio-login' ) );
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

				return new \WP_Error( 'invalid_g_recaptcha_response', __( 'Please verify that you are not a robot.', 'aio-login' ) );
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
				echo '<style type="text/css">
					#login {
						width: 352px !important;
					}
					.g-recaptcha {
						margin-bottom: 20px !important;
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
