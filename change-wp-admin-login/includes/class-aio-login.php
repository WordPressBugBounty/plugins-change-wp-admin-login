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
use AIO_Login\Rest_API\Controller;

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

			add_action( 'init', array( $this, 'load_textdomain' ) );
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
			load_plugin_textdomain( 'change-wp-admin-login', false, AIO_LOGIN__DIR_PATH . '/languages' );
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
