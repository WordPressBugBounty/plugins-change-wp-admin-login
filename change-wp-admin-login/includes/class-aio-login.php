<?php
/**
 * Class AIO_Login
 *
 * @package All In One Login
 */

namespace AIO_Login;

use AIO_Login\Admin\Admin;
use AIO_Login\Change_WP_Admin_Login\Change_WP_Admin_Login;
use AIO_Login\Google_Recaptcha\Google_Recaptcha;
use AIO_Login\Helper\Helper;
use AIO_Login\Login_Controller\Failed_Logins;
use AIO_Login\Login_Controller\Login_Controller;
use AIO_Login\Login_Customization\Login_Customization;
use AIO_Login\Login_Customization\Login_Customization_Output;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\AIO_Login' ) ) {
	/**
	 * Class AIO_Login
	 */
	class AIO_Login {
		/**
		 * Plugin dependencies
		 *
		 * @var array $dependencies Plugin dependencies.
		 */
		public static $dependencies = array();

		/**
		 * AIO_Login constructor.
		 */
		public function __construct() {
			$this->include_files();

			self::class_loader( Admin::class );
			self::class_loader( Change_WP_Admin_Login::class );
			self::class_loader( Google_Recaptcha::class );
			self::class_loader( Login_Controller::class );
			self::class_loader( Login_Customization::class );
			self::class_loader( Login_Customization_Output::class );

			$this->init();

			do_action( 'aio_login__plugin_init' );
		}

		/**
		 * Include files.
		 */
		private function include_files() {
			require_once AIO_LOGIN__DIR_PATH . 'includes/class-helper.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/admin/class-admin.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/change-wp-admin-login/class-change-wp-admin-login.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/google-recaptcha/class-google-recaptcha.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-controller/class-login-controller.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-controller/class-failed-logins.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-customization/class-login-customization.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-customization/class-login-customization-output.php';

			if ( isset( $_GET['page'] ) && 'aio-login' === $_GET['page'] && isset( $_GET['tab'] ) && 'activity-log' === $_GET['tab'] && is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
			}

			require_once AIO_LOGIN__DIR_PATH . 'includes/login-controller/class-failed-login-activity-logs.php';
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-controller/class-lockouts-activity-logs.php';
		}

		/**
		 * Class loader.
		 *
		 * @param string $class_name Class name.
		 */
		public static function class_loader( $class_name ) {
			$return = false;
			if ( class_exists( $class_name ) ) {
				if ( method_exists( $class_name, 'get_instance' ) ) {
					$return = $class_name::get_instance();

					self::$dependencies[ $class_name ] = $return;
				}
			}

			return $return;
		}

		/**
		 * Init.
		 */
		private function init() {
			register_activation_hook( AIO_LOGIN__FILE, array( $this, 'activate_plugin' ) );
			register_uninstall_hook( AIO_LOGIN__FILE, array( self::class, 'uninstall_plugin' ) );

			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			add_action( 'wp_ajax_aio_login', array( $this, 'ajax_handler' ) );
			add_action( 'init', array( $this, 'if_activation_hook_not_triggered' ) );

			if ( is_multisite() ) {
				add_action( 'wp_initialize_site', array( $this, 'new_site_registered' ) );
			}
		}

		/**
		 * Check if pro version is installed.
		 *
		 * @return bool
		 */
		public static function has_pro() {
			if ( function_exists( 'is_plugin_active' ) ) {
				if ( is_plugin_active( 'aio-login-pro/aio-login-pro.php' ) ) {
					return true;
				}
			}

			if ( class_exists( 'AIO_Login_Pro\\AIO_Login_Pro' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Activate AIO Login plugin.
		 */
		public function activate_plugin() {
			Helper::create_table(
				'login_attempts',
				array(
					'user_login' => 'varchar(255) NOT NULL',
					'ip_address' => 'varchar(255) NOT NULL',
					'country'    => 'varchar(255) NOT NULL',
					'city'       => 'varchar(255) NOT NULL',
					'time'       => 'VARCHAR(255) NOT NULL',
					'user_agent' => 'varchar(255) NOT NULL',
					'status'     => 'varchar(255) NOT NULL DEFAULT ""',
				)
			);
			Helper::create_table(
				'login_lockouts',
				array(
					'ip_address' => 'varchar(255) NOT NULL',
					'country'    => 'varchar(255) NOT NULL',
					'city'       => 'varchar(255) NOT NULL',
					'time'       => 'bigint(20) NOT NULL',
					'user_agent' => 'varchar(255) NOT NULL',
				)
			);

			if ( ! get_option( 'aio_login__version' ) ) {
				update_option( 'aio_login__version', AIO_LOGIN__VERSION );
			}
		}

		/**
		 * Uninstall AIO Login plugin.
		 */
		public static function uninstall_plugin() {
			\AIO_Login\Helper\Helper::drop_table( 'login_attempts' );
			\AIO_Login\Helper\Helper::drop_table( 'login_lockouts' );

			global $wpdb;
			$sql = "DELETE FROM %i WHERE option_name LIKE 'aio_login%'";
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$sql,
					$wpdb->options
				)
			);
		}



		/**
		 * Load textdomain.
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'aio-login', false, AIO_LOGIN__DIR_PATH . '/languages' );
		}

		/**
		 * Ajax handler.
		 */
		public function ajax_handler() {
			if ( isset( $_REQUEST['aio_login_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['aio_login_nonce'] ) ), 'aio-login-dashboard' ) ) {
				$method = '';
				if ( isset( $_REQUEST['method'] ) ) {
					$method = sanitize_text_field( wp_unslash( $_REQUEST['method'] ) );
				}

				if ( empty( $method ) ) {
					wp_send_json_error(
						array(
							'message' => __( 'Method not found', 'aio-login' ),
						),
						400
					);
				}

				if ( in_array( $method, array( 'success', 'failed', 'lockout' ), true ) ) {
					$value = '';
					$count = 0;

					if ( isset( $_POST['value'] ) ) {
						$value = sanitize_text_field( wp_unslash( $_POST['value'] ) );
					}

					if ( 'success' === $method ) {
						$count = Failed_Logins::get_attempts_count( 'success', $value );
					}

					if ( 'failed' === $method ) {
						$count = Failed_Logins::get_attempts_count( 'failed', $value );
					}

					if ( 'lockout' === $method ) {
						$count = Failed_Logins::get_lockout_attempts_count( $value );
					}

					wp_send_json_success(
						array(
							'count' => $count,
						)
					);
				}

				if ( 'lla_toggle' === $method ) {
					if ( isset( $_POST['value'] ) ) {
						$value = sanitize_text_field( wp_unslash( $_POST['value'] ) );
						if ( empty( $value ) ) {
							$value = get_option( 'aio_login_limit_attempts_enable', 'off' );
						}

						update_option( 'aio_login_limit_attempts_enable', $value );

						wp_send_json_success(
							array(
								'value'   => $value,
								'message' => __( 'Limit login attempts has been updated', 'aio-login' ),
							)
						);
					}
				}

				if ( in_array( $method, array( 'failed_login_activity_logs', 'lockout_activity_logs' ), true ) ) {
					$limit  = 5;
					$offset = 0;
					if ( isset( $_GET['limit'] ) ) {
						$limit = sanitize_text_field( wp_unslash( $_GET['limit'] ) );
					}

					if ( isset( $_GET['offset'] ) ) {
						$offset = sanitize_text_field( wp_unslash( $_GET['offset'] ) );
					}

					$logs = array();
					if ( 'failed_login_activity_logs' === $method ) {
						$logs = Helper::get_logs( 'failed' );
					}

					if ( 'lockout_activity_logs' === $method ) {
						$logs = Helper::get_logs( 'lockout' );
					}

					$count = count( $logs );
					if ( $count > $limit ) {
						$logs = array_slice( $logs, $offset, $limit );
					}

					$logs = array_map(
						function ( $log ) {
							$log['time'] = gmdate( 'F j, Y, g:i a', $log['time'] );
							return $log;
						},
						$logs
					);

					wp_send_json_success(
						array(
							'logs'      => $logs,
							'count'     => $count,
							'log_count' => count( $logs ),
						),
						200
					);
				}

				if ( in_array( $method, array( 'delete_logs_failed_login_activity_logs', 'delete_logs_lockout_activity_logs' ), true ) ) {
					$logs = array();
					if ( isset( $_POST['logs'] ) ) {
						if ( is_array( $_POST['logs'] ) ) {
							$logs = array_map( 'sanitize_text_field', wp_unslash( $_POST['logs'] ) );
						} else {
							$logs = sanitize_text_field( wp_unslash( $_POST['logs'] ) );
						}
					}

					if ( 'delete_logs_failed_login_activity_logs' === $method ) {
						$result = Failed_Logins::delete_logs( $logs );
					}

					if ( 'delete_logs_lockout_activity_logs' === $method ) {
						$result = Failed_Logins::delete_lockouts( $logs );
					}

					if ( $result ) {
						wp_send_json_success(
							array(
								'message' => __( 'Logs has been deleted', 'aio-login' ),
							),
							200
						);
					}
				}

				if ( 'enable_block_ip_address' === $method ) {
					$value = 'off';
					if ( isset( $_POST['value'] ) ) {
						$value = sanitize_text_field( wp_unslash( $_POST['value'] ) );
					}

					update_option( 'aio_login_block_ip_address_enable', $value );

					wp_send_json_success(
						array(
							'value'   => $value,
							'message' => __( 'Block IP address has been updated', 'aio-login' ),
						)
					);
				}

				if ( 'enable_2fa' === $method ) {
					$value = 'off';
					if ( isset( $_POST['value'] ) ) {
						$value = sanitize_text_field( wp_unslash( $_POST['value'] ) );
					}

					update_option( 'aio_login_pro__two_factor_auth_enable', $value );

					wp_send_json_success(
						array(
							'value'   => $value,
							'message' => __( '2FA has been updated', 'aio-login' ),
						)
					);
				}
			}
		}

		/**
		 * If activation hook not triggered.
		 */
		public function if_activation_hook_not_triggered() {
			if ( is_admin() && 'true' !== get_option( 'aio_login__update' ) ) {
				Helper::create_table(
					'login_attempts',
					array(
						'user_login' => 'varchar(255) NOT NULL',
						'ip_address' => 'varchar(255) NOT NULL',
						'country'    => 'varchar(255) NOT NULL',
						'city'       => 'varchar(255) NOT NULL',
						'time'       => 'VARCHAR(255) NOT NULL',
						'user_agent' => 'varchar(255) NOT NULL',
						'status'     => 'varchar(255) NOT NULL DEFAULT ""',
					)
				);
				Helper::create_table(
					'login_lockouts',
					array(
						'ip_address' => 'varchar(255) NOT NULL',
						'country'    => 'varchar(255) NOT NULL',
						'city'       => 'varchar(255) NOT NULL',
						'time'       => 'bigint(20) NOT NULL',
						'user_agent' => 'varchar(255) NOT NULL',
					)
				);

				update_option( 'aio_login__update', 'true' );
			}

			if ( ! get_option( 'aio_login__version' ) ) {
				update_option( 'aio_login__cwpal_enable', 'on' );
			}

		}

		/**
		 * New site registered.
		 *
		 * @param \WP_Site $new_site New site.
		 */
		 public function new_site_registered( $new_site ) {
			$site_id = $new_site->blog_id;
			switch_to_blog( $site_id );
			$this->activate_plugin();
			restore_current_blog();
		}

		/**
		 * Get instance.
		 *
		 * @return AIO_Login
		 */
		public static function get_instance() {
			static $instance = null;

			if ( is_null( $instance ) ) {
				$instance = new self();
			}

			return $instance;
		}
	}
}
