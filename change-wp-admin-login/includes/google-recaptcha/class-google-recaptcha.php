<?php
/**
 * Class Google_Recaptcha
 *
 * @package AIO Login
 */

namespace AIO_Login\Google_Recaptcha;

use AIO_Login\Captcha\Captcha_Validation;
use AIO_Login\Helper\Helper;

defined('ABSPATH') || exit;

if (!class_exists('AIO_Login\\Google_Recaptcha\\Google_Recaptcha')) {
	/**
	 * Class Google_Recaptcha
	 */
	class Google_Recaptcha
	{
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
		/**
		 * Normalize stored reCAPTCHA version (empty/invalid => v2).
		 *
		 * @param mixed $version Raw option value.
		 * @return string v2|v3
		 */
		private function normalize_recaptcha_version( $version ) {
			$version = strtolower( trim( (string) $version ) );
			if ( in_array( $version, array( 'v2', 'v3' ), true ) ) {
				return $version;
			}
			return 'v2';
		}

		public function __construct()
		{
			$this->is_enabled = get_option('aio_login_google_recaptcha_enable', 'off');
			$this->version = $this->normalize_recaptcha_version( get_option( 'aio_login_google_recaptcha_version', 'v2' ) );
			$this->site_key = get_option('aio_login_google_recaptcha_' . $this->version . '_site_key');
			$this->secret_key = get_option('aio_login_google_recaptcha_' . $this->version . '_secret_key');
			$this->theme = get_option('aio_login_google_recaptcha_v2_theme', 'light');
			$this->threshold = get_option('aio_login_google_recaptcha_v3_threshold', '0.5');

			$this->is_enabled = 'on' === get_option('aio_login_google_recaptcha_enable', 'off');


			if (Captcha_Validation::is_active_for_frontend('recaptcha')) {
				add_filter('aio_login__wp_authenticate_user', array($this, 'wp_authenticate_user'));
				add_action('login_enqueue_scripts', array($this, 'login_enqueue_scripts'));
				add_action('login_form', array($this, 'login_form'));
			}

			add_action('rest_api_init', array($this, 'rest_api_init'));
		}

		/**
		 * WP Authenticate user.
		 *
		 * @param \WP_User $user WP_User object.
		 *
		 * @return \WP_User|\WP_Error
		 */
		public function wp_authenticate_user($user)
		{

			if (is_wp_error($user)) {
				return $user;
			}

			$g_recaptcha_response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
			if (empty($g_recaptcha_response)) {
				return new \WP_Error('empty_g_recaptcha_response', __('Please verify that you are not a robot.', 'change-wp-admin-login'));
			}

			$remote_request = wp_remote_post(
				'https://www.google.com/recaptcha/api/siteverify',
				array(
					'body' => array(
						'secret' => $this->secret_key,
						'response' => $g_recaptcha_response,
					),
				)
			);

			if (is_wp_error($remote_request)) {
				return new \WP_Error('aio_login_recaptcha_network_error', __('Unable to connect to Google reCAPTCHA. Please try again later.', 'change-wp-admin-login'));
			}

			$api_response = wp_remote_retrieve_body($remote_request);
			$response = json_decode($api_response, true);

			if (isset($response['success']) && true === $response['success']) {
				if ('v2' === $this->version) {
					return $user;
				}

				if ('v3' === $this->version) {
					if (isset($response['score']) && $response['score'] >= $this->threshold && isset($response['action']) && 'login' === $response['action']) {
						return $user;
					}
				}

				return new \WP_Error('invalid_g_recaptcha_response', __('Please verify that you are not a robot.', 'change-wp-admin-login'));
			} elseif (isset($response['error-codes']) && is_array($response['error-codes'])) {
				$error_codes = array_map('sanitize_text_field', $response['error-codes']);

				// Prioritize secret key issues for clearer admin diagnostics.
				if (in_array('missing-input-secret', $error_codes, true)) {
					return new \WP_Error('cwpal_recaptcha_error', __('Google reCAPTCHA secret key is missing. Please check plugin settings.', 'change-wp-admin-login'));
				}
				if (in_array('invalid-input-secret', $error_codes, true)) {
					return new \WP_Error('cwpal_recaptcha_error', __('Google reCAPTCHA secret key is invalid. Please update it in plugin settings.', 'change-wp-admin-login'));
				}
				foreach ($error_codes as $error_code) {
					if (false !== strpos($error_code, 'secret') || false !== strpos($error_code, 'key')) {
						return new \WP_Error('cwpal_recaptcha_error', __('Google reCAPTCHA secret key is invalid. Please update it in plugin settings.', 'change-wp-admin-login'));
					}
				}

				if (in_array('missing-input-response', $error_codes, true)) {
					return new \WP_Error('cwpal_recaptcha_error', __('The response parameter is missing.', 'change-wp-admin-login'));
				}
				if (in_array('invalid-input-response', $error_codes, true)) {
					// In real-world misconfigurations this often indicates site/secret mismatch.
					// Keep UX consistent with other captcha providers by surfacing secret-key guidance.
					return new \WP_Error('cwpal_recaptcha_error', __('Google reCAPTCHA secret key is invalid. Please update it in plugin settings.', 'change-wp-admin-login'));
				}
				if (in_array('bad-request', $error_codes, true)) {
					return new \WP_Error('cwpal_recaptcha_error', __('The request is invalid or malformed.', 'change-wp-admin-login'));
				}
				if (in_array('timeout-or-duplicate', $error_codes, true)) {
					return new \WP_Error('cwpal_recaptcha_error', __('The response is no longer valid: either is too old or has been used previously.', 'change-wp-admin-login'));
				}
			}
			return $user;
		}

		/**
		 * Login enqueue scripts.
		 */
		public function login_enqueue_scripts()
		{
			if ('v2' === $this->version) {
				// Checkbox widget is ~304px; core #login is 320px with padding, so it clips
				// and overlaps floated .forgetmenot without clear + room.
				wp_register_style( 'aio-login-recaptcha-v2-layout', false, array(), AIO_LOGIN__VERSION );
				wp_enqueue_style( 'aio-login-recaptcha-v2-layout' );
				wp_add_inline_style(
					'aio-login-recaptcha-v2-layout',
					'body.login #login{width:360px!important;max-width:calc(100vw - 24px)!important;overflow:visible!important;}body.login #loginform{overflow:visible!important;}body.login #loginform .aio-login-wp-captcha{clear:both!important;display:block!important;width:100%!important;max-width:100%!important;margin:10px 0 16px!important;padding:0!important;overflow:visible!important;box-sizing:border-box!important;text-align:center;min-height:78px;}body.login #loginform .aio-login-wp-captcha .g-recaptcha,body.login #loginform > .g-recaptcha{display:inline-block!important;margin:0 auto 16px!important;max-width:100%!important;transform:scale(0.95);transform-origin:center top;-webkit-transform:scale(0.95);-webkit-transform-origin:center top;}body.login #loginform p.forgetmenot{clear:both!important;}'
				);

				wp_enqueue_script(
					'aio-login-form-captcha-position',
					AIO_LOGIN__DIR_URL . 'assets/js/login-form-captcha-position.js',
					array(),
					AIO_LOGIN__VERSION,
					true
				);

				// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				wp_register_script('aio-login-g-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
			}

			if ('v3' === $this->version) {
				// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				wp_register_script('aio-login-g-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode($this->site_key), array(), null, true);
				$site_key_json = wp_json_encode($this->site_key);
				$inline_script = '
				(function() {
					var siteKey = ' . $site_key_json . ';
					function setToken(token) {
						if (!token) return;
						var inputs = document.querySelectorAll("input[name=\'g-recaptcha-response\']");
						if (inputs.length === 0) {
							var forms = document.querySelectorAll("#loginform, #lostpasswordform, #registerform");
							forms.forEach(function(form) {
								var inp = document.createElement("input");
								inp.type = "hidden";
								inp.name = "g-recaptcha-response";
								inp.id = "g-recaptcha-response";
								inp.value = token;
								form.appendChild(inp);
							});
						} else {
							inputs.forEach(function(inp) {
								inp.value = token;
							});
						}
					}

					function executeV3(callback) {
						if (typeof grecaptcha !== "undefined" && grecaptcha.ready) {
							grecaptcha.ready(function() {
								grecaptcha.execute(siteKey, { action: "login" }).then(function(token) {
									setToken(token);
									if (callback) callback(token);
								}).catch(function() {
									if (callback) callback("");
								});
							});
						} else if (callback) {
							callback("");
						}
					}

					executeV3();

					document.addEventListener("DOMContentLoaded", function() {
						var forms = document.querySelectorAll("#loginform, #lostpasswordform, #registerform");
						forms.forEach(function(form) {
							var isSubmitting = false;
							form.addEventListener("submit", function(e) {
								if (isSubmitting) return;
								var inp = form.querySelector("input[name=\'g-recaptcha-response\']");
								if (!inp) {
									inp = document.createElement("input");
									inp.type = "hidden";
									inp.name = "g-recaptcha-response";
									inp.id = "g-recaptcha-response";
									form.appendChild(inp);
								}
								if (typeof grecaptcha !== "undefined" && grecaptcha.execute) {
									e.preventDefault();
									var handled = false;
									var proceed = function(token) {
										if (handled) return;
										handled = true;
										if (token) inp.value = token;
										isSubmitting = true;
										form.submit();
									};
									setTimeout(function() { proceed(""); }, 3000);
									grecaptcha.ready(function() {
										grecaptcha.execute(siteKey, { action: "login" }).then(function(token) {
											proceed(token);
										}).catch(function() {
											proceed("");
										});
									});
								}
							});
						});
					});
				})();';
				wp_add_inline_script('aio-login-g-recaptcha', $inline_script);
			}

			wp_enqueue_script('aio-login-g-recaptcha');
		}

		/**
		 * Login form.
		 */
		public function login_form()
		{
			if ('v2' === $this->version) {
				echo '<div class="aio-login-wp-captcha">';
				echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($this->site_key) . '" data-theme="' . esc_attr($this->theme) . '"></div>';
				echo '</div>';
			}

			if ('v3' === $this->version) {
				echo '<input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" />';
			}
		}

		/**
		 * Is google recaptcha enabled.
		 *
		 * @return bool
		 */
		public function is_enabled()
		{
			return $this->is_enabled;
		}

		public function rest_api_init()
		{
			register_rest_route(
				'aio-login/grecaptcha',
				'/get-settings',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/grecaptcha',
				'/save-settings',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'save_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/grecaptcha',
				'/test-connection',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'test_connection'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);
		}

		public function get_settings()
		{
			$response = array(
				'enabled' => $this->is_enabled,
				'validated' => Captcha_Validation::is_validated('recaptcha'),
				'version' => $this->normalize_recaptcha_version( $this->version ),
				'v2_site_key' => get_option('aio_login_google_recaptcha_v2_site_key', ''),
				'v2_secret_key' => get_option('aio_login_google_recaptcha_v2_secret_key', ''),
				'theme' => $this->theme,
				'v3_site_key' => get_option('aio_login_google_recaptcha_v3_site_key', ''),
				'v3_secret_key' => get_option('aio_login_google_recaptcha_v3_secret_key', ''),
				'threshold' => $this->threshold,
				'nonce' => wp_create_nonce('google-recaptcha'),
			);

			if ( class_exists( '\AIO_Login\Multisite\Network_Settings' ) ) {
				$response['network'] = \AIO_Login\Multisite\Network_Settings::get_feature_context( 'google_recaptcha' );
			}

			return rest_ensure_response($response);
		}

		public function save_settings(\WP_REST_Request $request)
		{
			$params = $request->get_params();

			if (isset($params['_wpnonce']) && wp_verify_nonce($params['_wpnonce'], 'google-recaptcha')) {
				if ( class_exists( '\AIO_Login\Multisite\Network_Settings' ) ) {
					$context = \AIO_Login\Multisite\Network_Settings::get_feature_context( 'google_recaptcha' );
					if ( ! $context['can_edit'] ) {
						return new \WP_Error(
							'network_managed',
							__( 'This setting is managed by the Network Administrator.', 'change-wp-admin-login' ),
							array( 'status' => 403 )
						);
					}
				}

				$enabled = 'off';
				$version = sanitize_text_field(wp_unslash($params['version']));
				$v2_site_key = sanitize_text_field(wp_unslash($params['v2_site_key']));
				$v2_secret_key = sanitize_text_field(wp_unslash($params['v2_secret_key']));
				$theme = sanitize_text_field(wp_unslash($params['theme']));
				$v3_site_key = sanitize_text_field(wp_unslash($params['v3_site_key']));
				$v3_secret_key = sanitize_text_field(wp_unslash($params['v3_secret_key']));
				$threshold = sanitize_text_field(wp_unslash($params['threshold']));

				if (isset($params['enabled']) && true === $params['enabled']) {
					$enabled = 'on';
				}

				update_option('aio_login_google_recaptcha_enable', $enabled);
				update_option('aio_login_google_recaptcha_version', $version);

				$mark_validated = isset( $params['validated'] ) && filter_var( $params['validated'], FILTER_VALIDATE_BOOLEAN );

				if ( 'v2' === $version ) {
					$prev_site   = (string) get_option( 'aio_login_google_recaptcha_v2_site_key', '' );
					$prev_secret = (string) get_option( 'aio_login_google_recaptcha_v2_secret_key', '' );
					update_option( 'aio_login_google_recaptcha_v2_site_key', $v2_site_key );
					update_option( 'aio_login_google_recaptcha_v2_secret_key', $v2_secret_key );
					update_option( 'aio_login_google_recaptcha_v2_theme', $theme );
					if ( ! $mark_validated && ( $prev_site !== $v2_site_key || $prev_secret !== $v2_secret_key ) ) {
						Captcha_Validation::set_validated( 'recaptcha', false );
					}
				}

				if ( 'v3' === $version ) {
					$prev_site   = (string) get_option( 'aio_login_google_recaptcha_v3_site_key', '' );
					$prev_secret = (string) get_option( 'aio_login_google_recaptcha_v3_secret_key', '' );
					update_option( 'aio_login_google_recaptcha_v3_site_key', $v3_site_key );
					update_option( 'aio_login_google_recaptcha_v3_secret_key', $v3_secret_key );
					update_option( 'aio_login_google_recaptcha_v3_threshold', $threshold );
					if ( ! $mark_validated && ( $prev_site !== $v3_site_key || $prev_secret !== $v3_secret_key ) ) {
						Captcha_Validation::set_validated( 'recaptcha', false );
					}
				}

				if ( $mark_validated ) {
					Captcha_Validation::set_validated( 'recaptcha', true );
				}

				// If enabling reCAPTCHA, disable other registered captcha providers (Pro hooks).
				if ( 'on' === $enabled ) {
					Captcha_Validation::disable_other_providers( 'recaptcha' );
				}

				// Update snapshot for WooCommerce integration
				if ( class_exists( '\AIO_Login\Helper\Helper' ) ) {
					\AIO_Login\Helper\Helper::update_configured_providers_snapshot();
				}

				return rest_ensure_response(
					array(
						'success'   => true,
						'validated' => Captcha_Validation::is_validated( 'recaptcha' ),
						'message'   => __( 'Settings saved successfully', 'change-wp-admin-login' ),
					)
				);
			}

			return new \WP_Error('invalid-nonce', __('Nonce verification failed', 'change-wp-admin-login'), array('status' => 401));
		}

		/**
		 * Test reCAPTCHA credentials without enabling frontend hooks.
		 *
		 * @param \WP_REST_Request $request Request object.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function test_connection(\WP_REST_Request $request)
		{
			$params = $request->get_params();

			if (!isset($params['_wpnonce']) || !wp_verify_nonce($params['_wpnonce'], 'google-recaptcha')) {
				return new \WP_Error('invalid-nonce', __('Nonce verification failed', 'change-wp-admin-login'), array('status' => 401));
			}

			$version    = sanitize_text_field(wp_unslash($params['version'] ?? 'v2'));
			$site_key   = sanitize_text_field(wp_unslash('v2' === $version ? ($params['v2_site_key'] ?? '') : ($params['v3_site_key'] ?? '')));
			$secret_key = sanitize_text_field(wp_unslash('v2' === $version ? ($params['v2_secret_key'] ?? '') : ($params['v3_secret_key'] ?? '')));

			$verify = Captcha_Validation::verify_provider_keys(
				'recaptcha',
				array(
					'site_key'   => $site_key,
					'secret_key' => $secret_key,
					'version'    => $version,
					'response'   => Captcha_Validation::sanitize_captcha_response_token( $params['response'] ?? $params['g-recaptcha-response'] ?? '' ),
				)
			);

			if (is_wp_error($verify)) {
				return new \WP_Error(
					'captcha_validation_failed',
					$verify->get_error_message(),
					array('status' => 400)
				);
			}

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __('Connection successful. Your reCAPTCHA keys are valid.', 'change-wp-admin-login'),
				)
			);
		}

		/**
		 * Getting instance of Google_Recaptcha.
		 *
		 * @return Google_Recaptcha
		 */
		public static function get_instance()
		{
			static $instance = null;

			if (is_null($instance)) {
				$instance = new Google_Recaptcha();
			}

			return $instance;
		}
	}
}
