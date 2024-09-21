<?php
/**
 * Class Login_Controller
 *
 * @package AIO Login
 */

namespace AIO_Login\Login_Controller;

use AIO_Login\Helper\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Login_Controller\\Login_Controller' ) ) {
	/**
	 * Class Login_Controller
	 */
	class Login_Controller {

		/**
		 * Limit attempts enabled.
		 *
		 * @var bool $limit_attempts_enabled Limit attempts enabled.
		 */
		private $limit_attempts_enabled;

		/**
		 * Limit attempts maximum attempts.
		 *
		 * @var int $limit_attempts_maximum_attempts Maximum attempts.
		 */
		private $limit_attempts_maximum_attempts = 5;

		/**
		 * Limit attempts timeout.
		 *
		 * @var int $limit_attempts_timeout Timeout.
		 */
		private $limit_attempts_timeout = 5;

		/**
		 * Lockout message.
		 *
		 * @var string $lockout_message Lockout message.
		 */
		private $lockout_message = '';

		/**
		 * Login_Controller constructor.
		 */
		private function __construct() {
			$this->limit_attempts_enabled          = get_option( 'aio_login_limit_attempts_enable', 'off' );
			$this->limit_attempts_maximum_attempts = get_option( 'aio_login_limit_attempts_maximum_attempts', 5 );
			$this->limit_attempts_timeout          = get_option( 'aio_login_limit_attempts_timeout', 5 );
			$this->lockout_message                 = get_option(
				'aio_login_limit_attempts_lockout_message',
				// translators: %d: Remaining minutes.
				__( 'You have been blocked due to too many unsuccessful login attempts. Please try again in %d minutes.', 'aio-login' )
			);

			/**
			 * Setting the default values for each field.
			 */
			$this->limit_attempts_enabled          = 'on' === $this->limit_attempts_enabled;
			$this->limit_attempts_maximum_attempts = empty( $this->limit_attempts_maximum_attempts ) ? 5 : $this->limit_attempts_maximum_attempts;
			$this->limit_attempts_timeout          = empty( $this->limit_attempts_timeout ) ? 5 : $this->limit_attempts_timeout;

			if ( empty( $this->lockout_message ) ) {
				$this->lockout_message = // translators: %d: Remaining minutes.
					__( 'You have been blocked due to too many unsuccessful login attempts. Please try again in %d minutes.', 'aio-login' );
			}

			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'aio_login__tab_login-protection_limit-login-attempts', array( $this, 'settings_sections' ) );
			add_action( 'wp_ajax_aio_login_save_limit_login_attempts', array( $this, 'save_limit_login_attempts' ) );

			add_action( 'aio_login__tab_activity-log_failed-logins', array( $this, 'display_failed_login_attempts_content' ) );
			add_action( 'aio_login__tab_activity-log_lockouts', array( $this, 'display_lockout_attempts_content' ) );

			add_action( 'login_enqueue_scripts', array( $this, 'login_enqueue_scripts' ) );
			add_filter( 'wp_authenticate_user', array( $this, 'wp_authenticate_user' ), 999, 2 );
			add_action( 'wp_login_failed', array( $this, 'wp_login_failed' ), 10, 2 );
			add_filter( 'login_errors', array( $this, 'wp_login_failed_message' ) );
			add_action( 'login_form', array( $this, 'add_hidden_fields' ) );
		}

		/**
		 * Register settings
		 */
		public function register_settings() {
			register_setting( 'aio_login__limit_login_attempts', 'aio_login_limit_attempts_enable' );
			register_setting( 'aio_login__limit_login_attempts', 'aio_login_limit_attempts_maximum_attempts' );
			register_setting( 'aio_login__limit_login_attempts', 'aio_login_limit_attempts_timeout' );
			register_setting( 'aio_login__limit_login_attempts', 'aio_login_limit_attempts_lockout_message' );

			add_settings_section(
				'aio_login__limit_login_attempts',
				__( 'Limit Login Attempts', 'aio-login' ),
				'__return_null',
				'page=aio-login&tab=security&sub-tab=limit-attempts'
			);

			add_settings_field(
				'aio_login_limit_attempts_enable',
				__( 'Enable', 'aio-login' ),
				array( $this, 'limit_attempts_enable' ),
				'page=aio-login&tab=security&sub-tab=limit-attempts',
				'aio_login__limit_login_attempts',
				array(
					'label_for' => 'aio_login_limit_attempts_enable',
				)
			);

			add_settings_field(
				'aio_login_limit_attempts_maximum_attempts',
				__( 'Maximum Attempts', 'aio-login' ),
				array( $this, 'limit_attempts_maximum_attempts' ),
				'page=aio-login&tab=security&sub-tab=limit-attempts',
				'aio_login__limit_login_attempts',
				array(
					'label_for' => 'aio_login_limit_attempts_maximum_attempts',
				)
			);

			add_settings_field(
				'aio_login_limit_attempts_timeout',
				__( 'Timeout', 'aio-login' ),
				array( $this, 'limit_attempts_timeout' ),
				'page=aio-login&tab=security&sub-tab=limit-attempts',
				'aio_login__limit_login_attempts',
				array(
					'label_for' => 'aio_login_limit_attempts_timeout',
				)
			);

			add_settings_field(
				'aio_login_limit_attempts_lockout_message',
				__( 'Lockout Message', 'aio-login' ),
				array( $this, 'lockout_message' ),
				'page=aio-login&tab=security&sub-tab=limit-attempts',
				'aio_login__limit_login_attempts',
				array(
					'label_for' => 'aio_login_limit_attempts_lockout_message',
				)
			);
		}

		/**
		 * Limit attempts enable
		 *
		 * @param array $args Arguments.
		 */
		public function limit_attempts_enable( $args ) {
			$enabled = get_option( $args['label_for'], 'off' );
			echo '<div class="aio-login__toggle-switch-wrapper">
				<input type="checkbox" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" ' . checked( $enabled, 'on', false ) . ' name="' . esc_attr( $args['label_for'] ) . '" class="aio-login__toggle-field">
				<label for="' . esc_attr( $args['label_for'] ) . '" class="aio-login__toggle-switch">
					<span class="aio-login__toggle-indicator"></span>
				</label>
			</div>';
		}

		/**
		 * Limit attempts maximum attempts
		 *
		 * @param array $args Arguments.
		 */
		public function limit_attempts_maximum_attempts( $args ) {
			echo '<input type="number" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" value="' . esc_attr( $this->limit_attempts_maximum_attempts ) . '" class="regular-text" min="1" placeholder="5">
			<p class="description">
				' . esc_html__( 'Maximum number of login attempts allowed before users IP are locked out.', 'aio-login' ) . '
				<br>
				<strong>
					' . esc_html__( 'By default, this is set to 5 failed attempts if left blank.', 'aio-login' ) . '
				</strong>
			</p>';
		}

		/**
		 * Limit attempts timeout
		 *
		 * @param array $args Arguments.
		 */
		public function limit_attempts_timeout( $args ) {
			echo '<input type="number" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" value="' . esc_attr( $this->limit_attempts_timeout ) . '" class="regular-text" min="1" placeholder="5">
			<p class="description">
				' . esc_html__( 'Amount of time a particular IP will be locked out once a lockout has been triggered.', 'aio-login' ) . '
				<br>
				<strong>
					' . esc_html__( 'By default, this is set to 5 mins if left blank.', 'aio-login' ) . '
				</strong>
			</p>';
		}

		/**
		 * Lockout message
		 *
		 * @param array $args Arguments.
		 */
		public function lockout_message( $args ) {
			echo '<textarea name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" class="regular-text" required>' . esc_html( $this->lockout_message ) . '</textarea>';
			echo '<p class="description">
				<strong>' . esc_attr__( 'Message displayed to locked out visitors due to too many failed login attempts.', 'aio-login' ) . '</strong>
				<br>
				<strong>' . esc_html__( 'Available placeholders', 'aio-login' ) . '</strong>
				<br>
				<strong>%d</strong> - ' . esc_html__( 'Remaining minutes', 'aio-login' ) . '
			</p>';
		}

		/**
		 * Login enqueue scripts
		 */
		public function login_enqueue_scripts() {
			wp_register_script( 'aio-login--detect-js', AIO_LOGIN__DIR_URL . 'assets/js/detect.js', array(), AIO_LOGIN__VERSION, true );
			wp_enqueue_script( 'aio-login--login-js', AIO_LOGIN__DIR_URL . 'assets/js/login.js', array( 'jquery', 'aio-login--detect-js' ), AIO_LOGIN__VERSION, true );
		}

		/**
		 * Settings template
		 */
		public function settings_sections() {
			echo '<aio-login-settings-form action="aio_login_save_limit_login_attempts">
                <template v-slot:settings-fields>';
					settings_fields( 'aio_login__limit_login_attempts' );
					do_settings_sections( 'page=aio-login&tab=security&sub-tab=limit-attempts' );
				echo '</template>
            </aio-login-settings-form>';
		}

		/**
		 * Save limit login attempts
		 */
		public function save_limit_login_attempts() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'aio-login' ) );
			}
			if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'aio_login__limit_login_attempts-options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				if ( isset( $_POST['aio_login_limit_attempts_enable'] ) ) {
					update_option( 'aio_login_limit_attempts_enable', 'on' );
				} else {
					update_option( 'aio_login_limit_attempts_enable', 'off' );
				}

				if ( isset( $_POST['aio_login_limit_attempts_maximum_attempts'] ) ) {
					update_option( 'aio_login_limit_attempts_maximum_attempts', absint( sanitize_text_field( wp_unslash( $_POST['aio_login_limit_attempts_maximum_attempts'] ) ) ) );
				}

				if ( isset( $_POST['aio_login_limit_attempts_timeout'] ) ) {
					update_option( 'aio_login_limit_attempts_timeout', absint( sanitize_text_field( wp_unslash( $_POST['aio_login_limit_attempts_timeout'] ) ) ) );
				}

				if ( isset( $_POST['aio_login_limit_attempts_lockout_message'] ) ) {
					update_option( 'aio_login_limit_attempts_lockout_message', sanitize_text_field( wp_unslash( $_POST['aio_login_limit_attempts_lockout_message'] ) ) );
				}

				wp_send_json_success(
					array(
						'message' => __( 'Limit Login Attempts settings saved successfully.', 'aio-login' ),
					),
					200
				);
			}
			exit( 0 );
		}

		/**
		 * WP Authenticate User
		 *
		 * @param \WP_User $wp_user WP_User.
		 * @param string   $password Password.
		 *
		 * @return \WP_User|\WP_Error
		 */
		public function wp_authenticate_user( $wp_user, $password ) {
			if ( $this->is_enabled() ) {
				if ( Helper::is_ip_blocked() ) {
					$message = $this->get_message_after_calculation();

					return new \WP_Error(
						'aio_login__blocked',
						$message
					);
				}
			}

			if ( wp_check_password( $password, $wp_user->user_pass, $wp_user->ID ) ) {
				self::insert_log( $wp_user->user_login, 'success' );
				Helper::update_user_attempt_count( '', true );
			}

			$wp_user = apply_filters( 'aio_login__wp_authenticate_user', $wp_user );
			return $wp_user;
		}

		/**
		 * Add error message
		 *
		 * @param string $username Username.
		 */
		public function wp_login_failed( $username ) {
			$this->insert_log( $username, 'failed' );

			if ( $this->is_enabled() ) {
				if ( ! Helper::is_ip_blocked() ) {
					Helper::update_user_attempt_count();

					$attempts = Helper::get_user_attempt_count();
					if ( $attempts >= $this->limit_attempts_maximum_attempts ) {
						Helper::block_ip();
						Helper::update_user_attempt_count( '', true );
					}
				}
			}
		}

		/**
		 * Get message after calculation
		 *
		 * @return string
		 */
		private function get_message_after_calculation() {
			$blocked_data = Helper::is_ip_blocked();
			$timeout      = $this->limit_attempts_timeout * 60;
			$remaining    = $timeout - ( current_time( 'timestamp' ) - $blocked_data['time'] ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$remaining    = round( $remaining / 60 );
			$remaining    = max( $remaining, 1 );

			return sprintf(
				$this->lockout_message,
				$remaining
			);
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
			$location   = Helper::get_location( $ip_address );
			$country    = $location['country'] ?? '';
			$city       = $location['city'] ?? '';
			$user_agent = '';

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
		 * Add error message
		 *
		 * @param string $error Error message.
		 *
		 * @return string
		 */
		public function wp_login_failed_message( $error ) {
			if ( $this->is_enabled() ) {
				if ( ! isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( Helper::is_ip_blocked() ) {
						$error = $this->get_message_after_calculation();

					} else {
						$attempts = Helper::get_user_attempt_count();
						$error   .= '<br>';
						$error   .= '<b>' . sprintf(
							// translators: %d: Remaining attempts.
							__( 'You have %d attempts remaining.', 'aio-login' ),
							absint( $this->limit_attempts_maximum_attempts ) - absint( $attempts )
						) . '</b>';
					}
				}
			}
			return $error;
		}

		/**
		 * Add hidden fields
		 */
		public function add_hidden_fields() {
			echo '<input type="hidden" name="aio_login__user_agent" id="aio_login__user_agent" />';
		}

		/**
		 * Display failed login attempts tables.
		 */
		public function display_failed_login_attempts_content() {
			?>
			<aio-login-data-table
				type="failed_login_activity_logs"
				id="failed_login_attempts"
				delete-logs-title="Empty failed login attempts"
				:headers="[
					{ key: 'id', value: '<?php esc_attr_e( 'ID', 'aio-login' ); ?>' },
					{ key: 'user_login', value: '<?php esc_attr_e( 'User Login', 'aio-login' ); ?>' },
					{ key: 'time', value: '<?php esc_attr_e( 'Date & Time', 'aio-login' ); ?>' },
					{ key: 'country', value: '<?php esc_attr_e( 'Country', 'aio-login' ); ?>' },
					{ key: 'city', value: '<?php esc_attr_e( 'City', 'aio-login' ); ?>' },
					{ key: 'user_agent', value: '<?php esc_attr_e( 'User Agent', 'aio-login' ); ?>' },
					{ key: 'ip_address', value: '<?php esc_attr_e( 'IP Address', 'aio-login' ); ?>' },
				]"
			></aio-login-data-table>
			<?php
		}

		/**
		 * Display lockout attempts content.
		 */
		public function display_lockout_attempts_content() {
			if ( ! self::is_enabled() ) {
				echo '<h2>Lockout settings are disabled by Administrator</h2>';
				return;
			}

			?>
			<aio-login-data-table
				type="lockout_activity_logs"
				id="lockout_activity_logs"
                delete-logs-title="Empty lockout attempts logs"
				:headers="[
					{ key: 'time', value: '<?php esc_attr_e( 'Date & Time', 'aio-login' ); ?>' },
					{ key: 'country', value: '<?php esc_attr_e( 'Country', 'aio-login' ); ?>' },
					{ key: 'city', value: '<?php esc_attr_e( 'City', 'aio-login' ); ?>' },
					{ key: 'user_agent', value: '<?php esc_attr_e( 'User Agent', 'aio-login' ); ?>' },
					{ key: 'ip_address', value: '<?php esc_attr_e( 'IP Address', 'aio-login' ); ?>' },
				]"
			></aio-login-data-table>
			<?php
		}

		/**
		 * Is enabled
		 *
		 * @return bool
		 */
		public function is_enabled() {
			$this->limit_attempts_enabled = apply_filters(
				'aio_login__limit_attempts_enabled',
				$this->limit_attempts_enabled
			);

			return $this->limit_attempts_enabled;
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
