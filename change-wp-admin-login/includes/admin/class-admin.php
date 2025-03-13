<?php
/**
 * Class AIO_Login
 *
 * @package All In One Login
 */

namespace AIO_Login\Admin;

use AIO_Login\Helper\Helper;
use AIO_Login\Login_Controller\Failed_Logins;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\Admin\Admin' ) ) {
	/**
	 * Class Admin
	 */
	class Admin {

		/**
		 * Settings tabs.
		 *
		 * @var array $settings_tabs Settings tabs.
		 */
		private $settings_tabs = array();

		/**
		 * Admin constructor.
		 */
		private function __construct() {
			add_action( 'init', array( $this, 'register_settings_tabs' ), -3, 0 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_mount_script' ), 20 );
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );

			add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		}

		/**
		 * Register_settings tabs.
		 */
		public function register_settings_tabs() {
			if ( is_admin() ) {
				$this->settings_tabs = array(
					'dashboard'        => array(
						'title' => __( 'Dashboard', 'change-wp-admin-login' ),
						'slug'  => 'dashboard',
						'icon'  => 'dashboard',
					),
					'login-protection' => array(
						'title'    => __( 'Login Protection', 'change-wp-admin-login' ),
						'slug'     => 'login-protection',
						'icon'     => 'login-protection',
						'sub-tabs' => array(
							'change-login-url'         => array(
								'title' => __( 'Change Login URL', 'change-wp-admin-login' ),
								'slug'  => 'change-login-url',
							),
							'limit-login-attempts'     => array(
								'title' => __( 'Limit Login Attempts', 'change-wp-admin-login' ),
								'slug'  => 'limit-login-attempts',
							),
							'disable-common-usernames' => array(
								'title'  => __( 'Disable Common Usernames', 'change-wp-admin-login' ),
								'slug'   => 'disable-common-usernames',
								'is-pro' => true,
								'plan'   => 'professional',
							),
						),
					),
					'activity-log'     => array(
						'title'    => __( 'Activity Log', 'change-wp-admin-login' ),
						'slug'     => 'activity-log',
						'icon'     => 'activity-log',
						'sub-tabs' => array(
							'lockouts'      => array(
								'title' => __( 'Lockouts', 'change-wp-admin-login' ),
								'slug'  => 'lockouts',
							),
							'failed-logins' => array(
								'title' => __( 'Failed Logins', 'change-wp-admin-login' ),
								'slug'  => 'failed-logins',
							),
						),
					),
					'security'         => array(
						'title'    => __( 'Security', 'change-wp-admin-login' ),
						'slug'     => 'security',
						'icon'     => 'security',
						'sub-tabs' => array(
							'grecaptcha'         => array(
								'title' => __( 'Google reCAPTCHA', 'change-wp-admin-login' ),
								'slug'  => 'grecaptcha',
							),
							'2fa'                => array(
								'title'  => __( '2FA', 'change-wp-admin-login' ),
								'slug'   => '2fa',
								'is-pro' => true,
								'plan'   => 'professional',
							),
							'block-ip-addresses' => array(
								'title'  => __( 'Ban / Whitelist IP Addresses', 'change-wp-admin-login' ),
								'slug'   => 'block-ip-addresses',
								'is-pro' => true,
								'plan'   => 'basic',
							),
						),
					),
					'temp-access'      => array(
						'title'  => __( 'Temporary Access', 'change-wp-admin-login' ),
						'slug'   => 'temp-access',
						'icon'   => 'temp-access',
						'is-pro' => true,
						'plan'   => 'business',
					),
					'customization'    => array(
						'title'    => __( 'Customize', 'change-wp-admin-login' ),
						'slug'     => 'customization',
						'icon'     => 'customize',
						'sub-tabs' => array(
							'logo'       => array(
								'title' => __( 'Logo', 'change-wp-admin-login' ),
								'slug'  => 'logo',
							),
							'background' => array(
								'title' => __( 'Background', 'change-wp-admin-login' ),
								'slug'  => 'background',
							),
							'custom-css' => array(
								'title' => __( 'Custom CSS', 'change-wp-admin-login' ),
								'slug'  => 'custom-css',
							),
							'templates'  => array(
								'title'  => __( 'Templates', 'change-wp-admin-login' ),
								'slug'   => 'templates',
								'is-pro' => true,
								'plan'   => 'business',
							),
							'social-login'  => array(
								'title'  => __( 'Social Login', 'change-wp-admin-login' ),
								'slug'   => 'social-login',
								'is-pro' => true,
								'plan'   => 'professional',
							),
						),
					),
				);

				$this->settings_tabs = apply_filters( 'aio_login__register_settings_tabs', $this->settings_tabs );

				if ( ! \AIO_Login\Aio_Login::has_pro() ) {
					$this->settings_tabs['getpro'] = array(
						'title' => __( 'Get Pro', 'change-wp-admin-login' ),
						'slug'  => 'getpro',
						'icon'  => 'getpro-icon',
					);
				}
			}
		}

		/**
		 * Admin_enqueue_scripts.
		 *
		 * @param string $hook Hook name.
		 */
		public function admin_enqueue_scripts( $hook ) {
			$l10n = array(
				'tabs'       => $this->settings_tabs,
				'version'    => AIO_LOGIN__VERSION,
				'assets_url' => AIO_LOGIN__DIR_URL . 'assets/',
				'admin_url'  => add_query_arg(
					array(
						'page' => 'aio-login',
					),
					admin_url( 'admin.php' )
				),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'rest_url'   => rest_url(),
				'has_pro'    => ( \AIO_Login\Aio_Login::has_pro() ) ? 'true' : 'false',
				'site_url'   => site_url(),
			);
			
			if ( get_option( 'permalink_structure' ) ) {
				$l10n['site_link_login_url'] = trailingslashit( home_url() );
			} else {
				$l10n['site_link_login_url'] = trailingslashit( home_url() ) . '?';
			}
			$l10n['site_link_redirect_url'] = trailingslashit( home_url() );
			$l10n['use_trailing_slashes'] = str_ends_with( get_option( 'permalink_structure' ), '/' ) ? 'true' : 'false';
			

			$dependencies = array( 'wp-color-picker', 'wp-i18n', 'jquery' );

			wp_register_style ( 'aio-login__app', AIO_LOGIN__DIR_URL . 'assets/css/app.css', array( 'wp-color-picker' ), AIO_LOGIN__VERSION, 'all' );
			wp_register_script( 'aio-login__app', AIO_LOGIN__DIR_URL . 'assets/js/app.js',   $dependencies,              AIO_LOGIN__VERSION, true   );

			if ( 'toplevel_page_aio-login' === $hook ) {
				wp_enqueue_media();

				wp_enqueue_style( 'aio-login__app' );
				wp_enqueue_script( 'aio-login__app' );
				wp_set_script_translations( 'aio-login__app', 'change-wp-admin-login', AIO_LOGIN__DIR_PATH . 'languages' );
				wp_localize_script( 'aio-login__app', 'aio_login__app_object', $l10n );
				
			}
			if ( ! \AIO_Login\Aio_Login::has_pro() ) {
				echo '<style type="text/css" id="aio-login__submenu-handler-styles">
					#toplevel_page_aio-login > ul li:last-child { background-image: linear-gradient(180deg, #6D16DF 0%, #490F95 100%); } #toplevel_page_aio-login > ul li:last-child a { color: #fff !important;}
				</style>';
			}
		}

		/**
		 * Admin enqueue scripts
		 *
		 * @param string $hook Page hook.
		 */
		public function admin_mount_script( $hook ) {
			if ( 'toplevel_page_aio-login' === $hook ) {
				wp_add_inline_script(
					'aio-login-dist',
					'window.$aioLogin.aioLoginApp.mount( "#aio-login__app" )',
					'after'
				);
			}
		}

		/**
		 * Admin_menu.
		 */
		public function admin_menu() {
			add_menu_page(
				__( 'All in One Login', 'change-wp-admin-login' ),
				__( 'AIO Login', 'change-wp-admin-login' ),
				'manage_options',
				'aio-login',
				array( $this, 'admin_page' )
			);

			add_submenu_page(
				'aio-login',
				__( 'Dashboard', 'change-wp-admin-login' ),
				__( 'Dashboard', 'change-wp-admin-login' ),
				'manage_options',
				'aio-login',
				array( $this, 'admin_page' )
			);

			$tabs = $this->settings_tabs;

			foreach ( $tabs as $tab ) {
				if ( 'dashboard' === $tab['slug'] ) {
					continue;
				}

				add_submenu_page(
					'aio-login',
					$tab['title'],
					$tab['title'],
					'manage_options',
					'admin.php?page=aio-login&tab=' . $tab['slug']
				);
			}
		}

		/**
		 * Admin_page.
		 */
		public function admin_page() {
			$settings_tab         = $this->settings_tabs;
			$setting_tab_slug     = 'dashboard';
			$settings_sub_tab     = array();
			$setting_sub_tab_slug = '';

			if ( isset( $_GET['tab' ] ) && ! empty( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Arrays.ArrayKeySpacingRestrictions.SpacesAroundArrayKeys
				$setting_tab_slug = sanitize_text_field( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			if ( isset( $this->settings_tabs[ $setting_tab_slug ]['sub-tabs'] ) && ! empty( $this->settings_tabs[ $setting_tab_slug ]['sub-tabs'] ) ) {
				$setting_sub_tab_slug = array_key_first( $this->settings_tabs[ $setting_tab_slug ]['sub-tabs'] );
				$settings_sub_tab     = $this->settings_tabs[ $setting_tab_slug ]['sub-tabs'];
			}

			if ( isset( $_GET['sub-tab'] ) && ! empty( $_GET['sub-tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$setting_sub_tab_slug = sanitize_text_field( wp_unslash( $_GET['sub-tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			require_once AIO_LOGIN__DIR_PATH . 'includes/admin/settings/admin.php';
		}

		public function rest_api_init() {
			register_rest_route(
				'aio-login/dashboard',
				'/get-settings',
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_settings' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/dashboard',
				'/get-counts',
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_counts' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/limit-login-attempts',
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'update_limit_login_attempts' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/two-factor-authentication',
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'update_two_factor_authentication' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/block-ip-address',
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'update_block_ip_address' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/dashboard/logs',
				'/lockouts',
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_lockouts' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/dashboard/logs',
				'/failed-logins',
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_failed_logins' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);
		}

		public function get_settings() {
			$settings = array(
				'limit_login_attempts' => get_option( 'aio_login_limit_attempts_enable', 'off' ),
				'two_factor_auth' => get_option( 'aio_login_pro__two_factor_auth_enable', 'off' ),
				'block_ip_address' => get_option( 'aio_login_block_ip_address_enable', 'off' ),
			);

			return rest_ensure_response( $settings );
		}

		public function get_lockouts() {
			$logs = Helper::get_logs( 'lockout' );

			$logs = array_slice( $logs, 0, 5 );

			$logs = array_map(
				function( $log ) {
					$log['time'] = date( 'F j, Y, g:i a', $log['time'] );
					return $log;
				},
				$logs
			);

			return rest_ensure_response( $logs );
		}

		public function get_failed_logins() {
			$logs = Helper::get_logs( 'failed' );

			$logs = array_slice( $logs, 0, 5 );

			$logs = array_map(
				function( $log ) {
					$log['time'] = date( 'F j, Y, g:i a', $log['time'] );
					return $log;
				},
				$logs
			);

			return rest_ensure_response( $logs );
		}

		public function get_counts( $request ) {
			$type     = 'success';
			$duration = 'today';

			if ( isset( $request['type'] ) && ! empty( $request['type'] ) ) {
				$type = sanitize_text_field( wp_unslash( $request['type'] ) );
			}

			if ( isset( $request['duration'] ) && ! empty( $request['duration'] ) ) {
				$duration = sanitize_text_field( wp_unslash( $request['duration'] ) );
			}

			if ( 'lockouts' === $type ) {
				$count = Failed_Logins::get_lockout_attempts_count( $duration );
			} else {
				$count = Failed_Logins::get_attempts_count( $type, $duration );
			}

			return rest_ensure_response( array( 'count' => absint( $count ) ) );
		}

		public function update_limit_login_attempts( $request ) {
			$params = $request->get_params();

			if ( isset( $params['value'] ) ) {
				$value = sanitize_text_field( wp_unslash( $params['value'] ) );
				$value = 'on' === $value ? 'on' : 'off';

				update_option( 'aio_login_limit_attempts_enable', $value );

				return rest_ensure_response(
					array(
						'status' => 'success',
						'message' => __( 'Limit login attempts updated successfully', 'change-wp-admin-login' ),
					)
				);
			}

			return new \WP_Error(
				'invalid_value',
				__( 'Invalid value', 'change-wp-admin-login' ),
				array( 'status' => 400 )
			);
		}

		public function update_two_factor_authentication( $request ) {
			$params = $request->get_params();

			if ( isset( $params['value'] ) ) {
				$value = sanitize_text_field( wp_unslash( $params['value'] ) );
				$value = 'on' === $value ? 'on' : 'off';

				update_option( 'aio_login_pro__two_factor_auth_enable', $value );

				return rest_ensure_response(
					array(
						'status' => 'success',
						'message' => __( 'Two factor authentication updated successfully', 'change-wp-admin-login' ),
					)
				);
			}

			return new \WP_Error(
				'invalid_value',
				__( 'Invalid value', 'change-wp-admin-login' ),
				array( 'status' => 400 )
			);
		}

		public function update_block_ip_address( $request ) {
			$params = $request->get_params();

			if ( isset( $params['value'] ) ) {
				$value = sanitize_text_field( wp_unslash( $params['value'] ) );
				$value = 'on' === $value ? 'on' : 'off';

				update_option( 'aio_login_block_ip_address_enable', $value );

				return rest_ensure_response(
					array(
						'status' => 'success',
						'message' => __( 'Block ip address updated successfully', 'change-wp-admin-login' ),
					)
				);
			}

			return new \WP_Error(
				'invalid_value',
				__( 'Invalid value', 'change-wp-admin-login' ),
				array( 'status' => 400 )
			);
		}

		/**
		 * Getting instance of class.
		 *
		 * @return Admin
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
