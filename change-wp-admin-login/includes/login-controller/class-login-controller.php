<?php
/**
 * Class Login_Controller
 *
 * @package AIO Login
 */

namespace AIO_Login\Login_Controller;

use AIO_Login\Helper\Helper;
use AIO_Login\Passwordless_Otp\OTP_Captcha;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Login_Controller\\Login_Controller' ) ) {
	/**
	 * Class Login_Controller
	 *
	 * Free owns activity logging + login scripts. Limit Login Attempts enforcement
	 * lives in AIO Login Pro (Guideline 5).
	 */
	class Login_Controller {

		/**
		 * Login_Controller constructor.
		 */
		private function __construct() {
			add_action( 'login_enqueue_scripts', array( $this, 'login_enqueue_scripts' ) );
			add_filter( 'wp_authenticate_user', array( $this, 'wp_authenticate_user' ), 999, 2 );
			add_action( 'wp_login_failed', array( $this, 'wp_login_failed' ), 10, 2 );
			add_action( 'login_form', array( $this, 'add_hidden_fields' ) );

			add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		}

		/**
		 * Login enqueue scripts
		 */
		public function login_enqueue_scripts() {
			wp_register_script( 'aio-login--detect-js', AIO_LOGIN__DIR_URL . 'assets/js/detect.js', array(), AIO_LOGIN__VERSION, true );
			wp_enqueue_script( 'aio-login--login-js', AIO_LOGIN__DIR_URL . 'assets/js/login.js', array( 'jquery', 'aio-login--detect-js' ), AIO_LOGIN__VERSION, true );

			if ( class_exists( OTP_Captcha::class ) && OTP_Captcha::is_required() ) {
				wp_enqueue_script(
					'aio-login-form-captcha-position',
					AIO_LOGIN__DIR_URL . 'assets/js/login-form-captcha-position.js',
					array(),
					AIO_LOGIN__VERSION,
					true
				);
			}
		}

		/**
		 * WP Authenticate User — success activity log only (no LLA / IP block).
		 *
		 * @param \WP_User $wp_user WP_User.
		 * @param string   $password Password.
		 *
		 * @return \WP_User|\WP_Error
		 */
		public function wp_authenticate_user( $wp_user, $password ) {
			// fixing the support ticket by adding the check user instance.
			if ( $wp_user instanceof \WP_User ) {
				if ( wp_check_password( $password, $wp_user->user_pass, $wp_user->ID ) ) {
					self::insert_log( $wp_user->user_login, 'success' );
				}
			}

			$wp_user = apply_filters( 'aio_login__wp_authenticate_user', $wp_user );
			return $wp_user;
		}

		/**
		 * Log failed login only (no attempt counting / block_ip).
		 *
		 * @param string $username Username.
		 */
		public function wp_login_failed( $username ) {
			$this->insert_log( $username, 'failed' );
		}

		/**
		 * Insert failed login log
		 *
		 * @param string $username Username.
		 * @param string $status Status.
		 *
		 * @return int
		 */
		private function insert_log( $username, $status = '' ) {
			$ip_address = Helper::get_ip();
			$country    = '';
			$city       = '';
			if ( class_exists( '\AIO_Login\AIO_Login' ) && \AIO_Login\AIO_Login::has_pro() ) {
				$location = Helper::get_location( $ip_address );
				$country  = $location['country'] ?? '';
				$city     = $location['city'] ?? '';
			}
			$user_agent = 'UNKNOWN';

			if ( isset( $_POST['aio_login__user_agent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$user_agent = sanitize_text_field( wp_unslash( $_POST['aio_login__user_agent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}

			$login_details = array(
				'user_login' => $username,
				'ip_address' => $ip_address,
				'country'    => $country,
				'city'       => $city,
				'user_agent' => $user_agent,
				'status'     => $status,
				'time'       => current_time( 'timestamp' ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			);

			return Failed_Logins::insert_logs( $login_details );
		}

		/**
		 * Add hidden fields
		 */
		public function add_hidden_fields() {
			echo '<input type="hidden" name="aio_login__user_agent" id="aio_login__user_agent" />';
		}

		/**
		 * REST for activity log endpoints only.
		 */
		public function rest_api_init() {
			register_rest_route(
				'aio-login/logs',
				'/failed-login',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_failed_logs' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/logs',
				'/lockouts',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'lockouts' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);
		}

		/**
		 * Get failed login logs.
		 *
		 * @return \WP_REST_Response
		 */
		public function get_failed_logs() {
			$logs = Helper::get_logs( 'failed' );

			$logs = array_map(
				function ( $log ) {
					$log['time'] = date( 'F j, Y, g:i a', $log['time'] );
					return $log;
				},
				$logs
			);

			return rest_ensure_response( $logs );
		}

		/**
		 * Get lockout logs.
		 *
		 * @return \WP_REST_Response
		 */
		public function lockouts() {
			$logs = Helper::get_logs( 'lockout' );

			$logs = array_map(
				function ( $log ) {
					$log['time'] = date( 'F j, Y, g:i a', $log['time'] );
					return $log;
				},
				$logs
			);

			return rest_ensure_response( $logs );
		}

		/**
		 * Get instance.
		 *
		 * @return Login_Controller
		 */
		public static function get_instance() {
			static $instance = null;

			if ( is_null( $instance ) ) {
				$instance = new Login_Controller();
			}

			return $instance;
		}
	}
}
