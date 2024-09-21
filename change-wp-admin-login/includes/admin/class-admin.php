<?php
/**
 * Class AIO_Login
 *
 * @package All In One Login
 */

namespace AIO_Login\Admin;

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
			add_action( 'aio_login__tab_dashboard', array( $this, 'admin_dashboard' ) );
			add_action( 'aio_login__tab_getpro', array( $this, 'pro_features' ) );
		}

		/**
		 * Register_settings tabs.
		 */
		public function register_settings_tabs() {
			if ( is_admin() ) {
				$this->settings_tabs = array(
					'dashboard'        => array(
						'title' => __( 'Dashboard', 'aio-login' ),
						'slug'  => 'dashboard',
						'icon'  => 'dashboard',
					),
					'login-protection' => array(
						'title'    => __( 'Login Protection', 'aio-login' ),
						'slug'     => 'login-protection',
						'icon'     => 'login-protection',
						'sub-tabs' => array(
							'change-login-url'         => array(
								'title' => __( 'Change Login URL', 'aio-login' ),
								'slug'  => 'change-login-url',
							),
							'limit-login-attempts'     => array(
								'title' => __( 'Limit Login Attempts', 'aio-login' ),
								'slug'  => 'limit-login-attempts',
							),
							'block-ip-addresses'       => array(
								'title'  => __( 'Block IP Addresses', 'aio-login' ),
								'slug'   => 'block-ip-addresses',
								'is-pro' => true,
							),
							'disable-common-usernames' => array(
								'title'  => __( 'Disable Common Usernames', 'aio-login' ),
								'slug'   => 'disable-common-usernames',
								'is-pro' => true,
							),
						),
					),
					'activity-log'     => array(
						'title'    => __( 'Activity Log', 'aio-login' ),
						'slug'     => 'activity-log',
						'icon'     => 'activity-log',
						'sub-tabs' => array(
							'lockouts'      => array(
								'title' => __( 'Lockouts', 'aio-login' ),
								'slug'  => 'lockouts',
							),
							'failed-logins' => array(
								'title' => __( 'Failed Logins', 'aio-login' ),
								'slug'  => 'failed-logins',
							),
						),
					),
					'security'         => array(
						'title'    => __( 'Security', 'aio-login' ),
						'slug'     => 'security',
						'icon'     => 'security',
						'sub-tabs' => array(
							'grecaptcha' => array(
								'title' => __( 'Google reCAPTCHA', 'aio-login' ),
								'slug'  => 'grecaptcha',
							),
							'2fa'        => array(
								'title'  => __( '2FA', 'aio-login' ),
								'slug'   => '2fa',
								'is-pro' => true,
							),
						),
					),
					'temp-access'      => array(
						'title'  => __( 'Temporary Access', 'aio-login' ),
						'slug'   => 'temp-access',
						'icon'   => 'temp-access',
						'is-pro' => true,
					),
					'customization'    => array(
						'title'    => __( 'Customize', 'aio-login' ),
						'slug'     => 'customization',
						'icon'     => 'customize',
						'sub-tabs' => array(
							'logo'       => array(
								'title' => __( 'Logo', 'aio-login' ),
								'slug'  => 'logo',
							),
							'background' => array(
								'title' => __( 'Background', 'aio-login' ),
								'slug'  => 'background',
							),
							'custom-css' => array(
								'title' => __( 'Custom CSS', 'aio-login' ),
								'slug'  => 'custom-css',
							),
							'templates'  => array(
								'title'  => __( 'Templates', 'aio-login' ),
								'slug'   => 'templates',
								'is-pro' => true,
							),
						),
					),
				);

				$this->settings_tabs = apply_filters( 'aio_login__register_settings_tabs', $this->settings_tabs );

				if ( ! \AIO_Login\Aio_Login::has_pro() ) {
					$this->settings_tabs['getpro'] = array(
						'title' => __( 'Get Pro', 'aio-login' ),
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
			wp_register_style( 'aio-login--figtree-font', AIO_LOGIN__DIR_URL . 'assets/css/figtree.css', array(), AIO_LOGIN__VERSION, 'all' );

			wp_register_script( 'aio-login-dist', AIO_LOGIN__DIR_URL . 'assets/js/app.js', array( 'jquery', 'wp-i18n', 'wp-color-picker' ), AIO_LOGIN__VERSION, true );

			wp_enqueue_style( 'aio-login--admin', AIO_LOGIN__DIR_URL . 'assets/css/admin.css', array( 'wp-color-picker' ), AIO_LOGIN__VERSION, 'all' );
			wp_enqueue_style( 'aio-login--dashboard', AIO_LOGIN__DIR_URL . 'assets/css/dashboard.css', array( 'aio-login--figtree-font' ), AIO_LOGIN__VERSION, 'all' );

			if ( 'toplevel_page_aio-login' === $hook ) {
				wp_enqueue_media();

				wp_enqueue_script( 'aio-login-dist' );

				wp_set_script_translations( 'aio-login-dist', 'aio-login', AIO_LOGIN__DIR_PATH . 'languages' );
				wp_localize_script(
					'aio-login-dist',
					'aio_login_dist',
					array(
						'nonce'                       => wp_create_nonce( 'aio-login-dashboard' ),
						'options_url'                 => admin_url( 'options.php' ),
						'success_count'               => esc_attr( \AIO_Login\Login_Controller\Failed_Logins::get_attempts_count( 'success', 'today' ) ),
						'failed_count'                => esc_attr( \AIO_Login\Login_Controller\Failed_Logins::get_attempts_count( 'failed', 'today' ) ),
						'lockout_count'               => esc_attr( \AIO_Login\Login_Controller\Failed_Logins::get_lockout_attempts_count( 'today' ) ),
						'tabs'                        => $this->settings_tabs,
						'icon_url'                    => AIO_LOGIN__DIR_URL . 'assets/images/icons/',
						'admin_url'                   => add_query_arg(
							array(
								'page' => 'aio-login',
							),
							admin_url( 'admin.php' )
						),
						'lla_settings_url'            => add_query_arg(
							array(
								'page'    => 'aio-login',
								'tab'     => 'login-protection',
								'sub-tab' => 'limit-login-attempts',
							),
							admin_url( 'admin.php' )
						),
						'enable_block_ip_address_url' => add_query_arg(
							array(
								'page'    => 'aio-login',
								'tab'     => 'login-protection',
								'sub-tab' => 'block-ip-addresses',
							),
							admin_url( 'admin.php' )
						),
						'enable_2fa_url'              => add_query_arg(
							array(
								'page'    => 'aio-login',
								'tab'     => 'security',
								'sub-tab' => '2fa',
							),
							admin_url( 'admin.php' )
						),
						'has_pro'                     => \AIO_Login\Aio_Login::has_pro() ? 'true' : 'false',
					)
				);

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
				__( 'All in One Login', 'aio-login' ),
				__( 'AIO Login', 'aio-login' ),
				'manage_options',
				'aio-login',
				array( $this, 'admin_page' )
			);

			add_submenu_page(
				'aio-login',
				__( 'Dashboard', 'aio-login' ),
				__( 'Dashboard', 'aio-login' ),
				'manage_options',
				'aio-login',
				array( $this, 'admin_page' )
			);

			$tabs = $this->settings_tabs;

			foreach ( $tabs as $tab ) {
				if ( 'dashboard' === $tab['slug'] ) {
					continue;
				}

				if ( isset( $tab['is-pro'] ) && $tab['is-pro'] && ! \AIO_Login\Aio_Login::has_pro() ) {
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
		 * AIO login dashboard.
		 */
		public function admin_dashboard() {
			require_once AIO_LOGIN__DIR_PATH . 'includes/admin/settings/dashboard.php';
		}

		/**
		 * PRO Features.
		 */
		public function pro_features() {
			require_once AIO_LOGIN__DIR_PATH . 'includes/admin/settings/pro-features.php';
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
