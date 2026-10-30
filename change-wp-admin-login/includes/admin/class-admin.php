<?php
/**
 * Class AIO_Login
 *
 * @package All In One Login
 */

namespace AIO_Login\Admin;

use AIO_Login\Helper\Helper;
use AIO_Login\Login_Controller\Failed_Logins;
use AIO_Login\Multisite\Network_Settings;

defined('ABSPATH') || exit;

if (!class_exists('AIO_Login\Admin\Admin')) {
	/**
	 * Class Admin
	 */
	class Admin
	{

		/**
		 * Settings tabs.
		 *
		 * @var array $settings_tabs Settings tabs.
		 */
		private $settings_tabs = array();

		/**
		 * Admin constructor.
		 */
		private function __construct()
		{
			add_action('init', array($this, 'register_settings_tabs'), -3, 0);
			// Register shared Vue early so Pro can declare it as a script dependency.
			add_action('init', array($this, 'register_vue_vendor_script'), 5);
			add_action('admin_init', array($this, 'redirect_login_customizer_on_admin_init'), 0);
			add_action('admin_menu', array($this, 'fix_customize_submenu_link' ), 999 );
			add_action('network_admin_menu', array($this, 'fix_customize_submenu_link' ), 999 );
			// AIOL-774 #3: only the WP Admin submenu "Get Pro" opens tab=getpro. Other Get Pro CTAs use external pricing.
			add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
			add_action('admin_menu', array($this, 'admin_menu'));
			add_action('network_admin_menu', array($this, 'network_admin_menu'));
			add_filter('login_redirect', array($this, 'fix_subscriber_login_redirect'), 99, 3);
			add_filter('woocommerce_prevent_admin_access', array($this, 'allow_subscriber_admin_access'), 99);

			add_action('rest_api_init', array($this, 'rest_api_init'));
			add_action('wp_ajax_aio_login_refresh_rest_nonce', array($this, 'ajax_refresh_rest_nonce'));

			$plugin_basename = plugin_basename(AIO_LOGIN__FILE);
			add_filter('plugin_action_links_' . $plugin_basename, array($this, 'plugin_action_links'), 99);
			add_filter('network_admin_plugin_action_links_' . $plugin_basename, array($this, 'plugin_action_links'), 99);
		}

		/**
		 * Plugins list "Get Pro" link (AIOL-695) — external pricing, not the in-plugin tab.
		 *
		 * @param array $links Existing action links.
		 * @return array
		 */
		public function plugin_action_links($links)
		{
			if (!is_array($links)) {
				$links = array();
			}

			$get_pro_html = '<a href="' . esc_url( $this->get_pro_pricing_url() ) . '" target="_blank" rel="noopener noreferrer" style="font-weight:700;">' . esc_html__( 'Get Pro', 'change-wp-admin-login' ) . '</a>';

			unset($links['upgrade'], $links['get-pro']);

			foreach ($links as $key => $link) {
				if (!is_string($link)) {
					continue;
				}

				$text = trim(wp_strip_all_tags($link));
				if (
					false !== stripos($text, 'Get Pro Bundle')
					|| 0 === strcasecmp($text, 'Upgrade')
					|| 0 === strcasecmp($text, 'Get Pro')
				) {
					unset($links[$key]);
				}
			}

			if ($this->should_show_get_pro_tab()) {
				$links['get-pro'] = $get_pro_html;
			}

			return $links;
		}

		/**
		 * In-plugin Get Pro tab URL (admin menu item only — AIOL-774 #3).
		 *
		 * @return string
		 */
		private function get_pro_admin_page_url() {
			return $this->get_plugin_admin_url( array( 'tab' => 'getpro' ) );
		}

		/**
		 * External pricing URL for plugins-list / dashboard / Get Pro page CTAs.
		 *
		 * @return string
		 */
		private function get_pro_pricing_url()
		{
			if (defined('AIO_LOGIN_GET_PRO_URL') && '' !== AIO_LOGIN_GET_PRO_URL) {
				return AIO_LOGIN_GET_PRO_URL;
			}

			return 'https://aiologin.com/?utm_source=plugin&utm_medium=wp_plugin_get_pro&utm_campaign=upgrade_pro&utm_id=upgrade_pro';
		}

		/**
		 * Register_settings tabs.
		 */
		public function register_settings_tabs()
		{
			if (is_admin()) {
				// Free-only tabs. Pro adds its tabs via aio_login__register_settings_tabs when active.
				$this->settings_tabs = array(
					'dashboard' => array(
						'title' => __('Dashboard', 'change-wp-admin-login'),
						'slug' => 'dashboard',
						'icon' => 'dashboard',
					),
					'login-protection' => array(
						'title' => __('Login Protection', 'change-wp-admin-login'),
						'slug' => 'login-protection',
						'icon' => 'login-protection',
						'sub-tabs' => array(
							'change-login-url' => array(
								'title' => __('Change Login URL', 'change-wp-admin-login'),
								'slug' => 'change-login-url',
							),
							'login-redirection' => array(
								'title' => __('Login Redirection', 'change-wp-admin-login'),
								'slug' => 'login-redirection',
							),
							'disable-common-usernames' => array(
								'title' => __('Disable Common Usernames', 'change-wp-admin-login'),
								'slug' => 'disable-common-usernames',
							),
						),
					),
					'2fa' => array(
						'title' => __('2FA', 'change-wp-admin-login'),
						'slug' => '2fa',
						'icon' => '2fa-icon',
						'sub-tabs' => array(
							'authentication-methods' => array(
								'title' => __('Authentication Methods', 'change-wp-admin-login'),
								'slug' => 'authentication-methods',
							),
						),
					),
					'passwordless-authentication' => array(
						'title' => __('Passwordless Authentication', 'change-wp-admin-login'),
						'slug' => 'passwordless-authentication',
						'icon' => 'passwordless-authetication-icon',
						'sub-tabs' => array(
							'otp-login' => array(
								'title' => __('OTP Login', 'change-wp-admin-login'),
								'slug' => 'otp-login',
							),
						),
					),
					'activity-log' => array(
						'title' => __('Activity Log', 'change-wp-admin-login'),
						'slug' => 'activity-log',
						'icon' => 'activity-log',
						'sub-tabs' => array(
							'lockouts' => array(
								'title' => __('Lockouts', 'change-wp-admin-login'),
								'slug' => 'lockouts',
							),
							'failed-logins' => array(
								'title' => __('Failed Logins', 'change-wp-admin-login'),
								'slug' => 'failed-logins',
							),
						),
					),
					'security' => array(
						'title' => __('Security', 'change-wp-admin-login'),
						'slug' => 'security',
						'icon' => 'security',
						'sub-tabs' => array(
							'captcha' => array(
								'title' => __('CAPTCHA', 'change-wp-admin-login'),
								'slug' => 'captcha',
							),
							'block-ip-addresses' => array(
								'title' => __('Ban / Whitelist IP Addresses', 'change-wp-admin-login'),
								'slug' => 'block-ip-addresses',
							),
							'user-enumeration-protection' => array(
								'title' => __('User Enumeration Protection', 'change-wp-admin-login'),
								'slug' => 'user-enumeration-protection',
							),
						),
					),
				);

				$this->settings_tabs = apply_filters('aio_login__register_settings_tabs', $this->settings_tabs);

				if ($this->should_show_get_pro_tab()) {
					$this->settings_tabs['getpro'] = array(
						'title' => __('Get Pro', 'change-wp-admin-login'),
						'slug' => 'getpro',
						'icon' => 'getpro-icon',
					);
				}
			}
		}

		/**
		 * Register shared Vue / vue-router vendor (used by Free + Pro admin bundles).
		 */
		public function register_vue_vendor_script()
		{
			$vendor_path = AIO_LOGIN__DIR_PATH . 'assets/js/vue-vendor.js';
			$vendor_ver  = file_exists( $vendor_path ) ? (string) filemtime( $vendor_path ) : AIO_LOGIN__VERSION;
			wp_register_script(
				'aio-login-vue',
				AIO_LOGIN__DIR_URL . 'assets/js/vue-vendor.js',
				array(),
				$vendor_ver,
				true
			);
		}

		/**
		 * Admin_enqueue_scripts.
		 *
		 * @param string $hook Hook name.
		 */
		public function admin_enqueue_scripts($hook)
		{
			$is_limited_user = ! current_user_can('manage_options');

			$login_customizer_url = $this->get_plugin_admin_url();
			if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_network_options' ) ) {
				$login_customizer_url = $this->get_login_customizer_target_url();
			}

			// Thin Free localize: has_pro bridge + optional Get Pro upsell. Pro injects plan unlock flags / slack_connect_url via aio_login__app_object_l10n.
			$l10n = array(
				'tabs' => $this->settings_tabs,
				'version' => AIO_LOGIN__VERSION,
				'pro_version' => defined( 'AIO_LOGIN_PRO__VERSION' ) ? AIO_LOGIN_PRO__VERSION : '',
				'assets_url' => AIO_LOGIN__DIR_URL . 'assets/',
				'admin_url' => $this->get_plugin_admin_url(),
				'get_pro_page_url' => $this->get_pro_admin_page_url(),
				'get_pro_pricing_url' => $this->get_pro_pricing_url(),
				'login_customizer_url' => $login_customizer_url,
				'nonce' => wp_create_nonce('wp_rest'),
				'admin_nonce' => wp_create_nonce('aio_login_admin'),
				'rest_url' => esc_url_raw( get_rest_url( null, '/' ) ),
				'ajax_url' => admin_url('admin-ajax.php'),
				'has_pro' => (\AIO_Login\Aio_Login::has_pro()) ? 'true' : 'false',
				'site_url' => site_url(),
				'blog_name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				// Site admin email only for users who can manage options (2FA sender defaults).
				'admin_email' => current_user_can( 'manage_options' )
					? (string) get_option( 'admin_email', '' )
					: '',
				'is_limited_user' => $is_limited_user ? 'true' : 'false',
				'upgrade_popup_variant' => ( function_exists( 'aiologin_pro_is_custom_license_runtime' ) && aiologin_pro_is_custom_license_runtime() )
					? 'appsumo'
					: 'freemius',
				'has_active_license' => ( function_exists( 'aiologin_has_active_license' ) && aiologin_has_active_license() )
					? 'true'
					: 'false',
				'locale' => function_exists( 'determine_locale' ) ? determine_locale() : get_locale(),
			);

			if ($is_limited_user) {
				// Limited UI: only 2FA tab; do not set is-pro here (JS uses has_pro; is-pro forced the pro blur for non-admins).
				$l10n['tabs'] = array(
					'2fa' => array(
						'title' => __('2FA', 'change-wp-admin-login'),
						'slug'  => '2fa',
						'icon'  => '2fa-icon',
						'sub-tabs' => array(
							'authentication-methods' => array(
								'title' => __('Authentication Methods', 'change-wp-admin-login'),
								'slug'  => 'authentication-methods',
							),
						),
					),
				);
			}

			$l10n['i18n'] = Admin_JS_I18n::get_map();

			$l10n = apply_filters('aio_login__app_object_l10n', $l10n);

			if (get_option('permalink_structure')) {
				$l10n['site_link_login_url'] = trailingslashit(home_url());
			} else {
				$l10n['site_link_login_url'] = trailingslashit(home_url()) . '?';
			}
			$l10n['site_link_redirect_url'] = trailingslashit(home_url());
			$l10n['use_trailing_slashes'] = str_ends_with(get_option('permalink_structure'), '/') ? 'true' : 'false';


			$dependencies = array( 'wp-color-picker', 'wp-i18n', 'jquery', 'aio-login-vue' );
			// Load Pro bundle first so window.aio_login_pro exists before the free app mounts (Pro components override demos).
			if ( wp_script_is( 'aio-login-pro__app', 'registered' ) ) {
				$dependencies[] = 'aio-login-pro__app';
			}

			wp_register_style('aio-login__app', AIO_LOGIN__DIR_URL . 'assets/css/app.css', array('wp-color-picker', 'dashicons'), ( file_exists( AIO_LOGIN__DIR_PATH . 'assets/css/app.css' ) ? (string) filemtime( AIO_LOGIN__DIR_PATH . 'assets/css/app.css' ) : AIO_LOGIN__VERSION ), 'all');
			$app_js_path = AIO_LOGIN__DIR_PATH . 'assets/js/app.js';
			$app_js_ver  = file_exists( $app_js_path ) ? (string) filemtime( $app_js_path ) : AIO_LOGIN__VERSION;
			wp_register_script('aio-login__app', AIO_LOGIN__DIR_URL . 'assets/js/app.js', $dependencies, $app_js_ver, true);

			if ('toplevel_page_aio-login' === $hook) {
				wp_enqueue_media();

				wp_enqueue_style('aio-login__app');
				wp_enqueue_script('aio-login__app');
				wp_set_script_translations('aio-login__app', 'change-wp-admin-login', AIO_LOGIN__DIR_PATH . 'languages');
				wp_localize_script('aio-login__app', 'aio_login__app_object', $l10n);

			}
			// Menu chrome only — needed on every admin screen where the AIO Login sidebar item shows.
			wp_register_style( 'aio-login-admin-menu', false, array(), AIO_LOGIN__VERSION );
			wp_enqueue_style( 'aio-login-admin-menu' );

			$menu_css = '
				#adminmenu #toplevel_page_aio-login .wp-menu-image {
					display: flex;
					align-items: center;
					justify-content: center;
				}
				#adminmenu #toplevel_page_aio-login .wp-menu-image img {
					display: block !important;
					margin-top: -8px !important;
					width: 24px !important;
					height: 24px !important;
					object-fit: contain !important;
				}
				#adminmenu #toplevel_page_aio-login .wp-submenu {
					display: flex;
					flex-direction: column;
				}
				/* Visual order only — Dashboard stays first in $submenu so parent click lands there. */
				#adminmenu #toplevel_page_aio-login .wp-submenu li:has(> a[href*="customize.php"]) {
					order: -1;
				}
				#adminmenu #toplevel_page_aio-login .wp-submenu li a[href*="customize.php"] {
					display: flex;
					align-items: center;
					gap: 8px;
					color: #b5bbc1 !important;
					font-weight: 400;
				}
				#adminmenu #toplevel_page_aio-login .wp-submenu li a[href*="customize.php"]::before {
					content: "\f540";
					font: normal 18px/1 dashicons;
					display: inline-block;
					width: 18px;
					height: 18px;
					text-align: center;
					color: #b5bbc1;
					-webkit-font-smoothing: antialiased;
					-moz-osx-font-smoothing: grayscale;
				}
				#adminmenu #toplevel_page_aio-login .wp-submenu li a[href*="customize.php"]:hover,
				#adminmenu #toplevel_page_aio-login .wp-submenu li a[href*="customize.php"]:focus {
					color: #fff !important;
				}
				#adminmenu #toplevel_page_aio-login .wp-submenu li a[href*="customize.php"]:hover::before,
				#adminmenu #toplevel_page_aio-login .wp-submenu li a[href*="customize.php"]:focus::before {
					color: #fff;
				}
				#adminmenu #toplevel_page_aio-login.wp-has-current-submenu .wp-submenu li.current a[href*="customize.php"],
				#adminmenu #toplevel_page_aio-login .wp-submenu li.current a[href*="customize.php"] {
					color: #fff !important;
					font-weight: 500;
				}
				#adminmenu #toplevel_page_aio-login.wp-has-current-submenu .wp-submenu li.current a[href*="customize.php"]::before,
				#adminmenu #toplevel_page_aio-login .wp-submenu li.current a[href*="customize.php"]::before {
					color: #fff;
				}
			';
			if ( $this->should_show_get_pro_tab() ) {
				$crown = esc_url( AIO_LOGIN__DIR_URL . 'assets/images/pro-crown.svg' );
				$menu_css .= '
					#toplevel_page_aio-login > ul li a[href*="tab=getpro"] {
						display: flex;
						align-items: center;
						gap: 8px;
						/* Figma Brand Purple #6E16DF (AIOL-774 #5). */
						background: #6E16DF;
						color: #fff !important;
						font-weight: 600;
					}
					#toplevel_page_aio-login > ul li a[href*="tab=getpro"]::before {
						content: "";
						display: inline-block;
						width: 17px;
						height: 12px;
						background: url("' . $crown . '") center/contain no-repeat;
					}
				';
			}
			wp_add_inline_style( 'aio-login-admin-menu', $menu_css );

			wp_register_script( 'aio-login-admin-menu', false, array(), AIO_LOGIN__VERSION, true );
			wp_enqueue_script( 'aio-login-admin-menu' );
			$active_icon = esc_js( AIO_LOGIN__DIR_URL . 'assets/images/icons/aio-login-menu.svg' );
			$grey_icon   = esc_js( AIO_LOGIN__DIR_URL . 'assets/images/icons/aio-login-menu-grey.svg' );
			wp_add_inline_script(
				'aio-login-admin-menu',
				'document.addEventListener("DOMContentLoaded",function(){var li=document.getElementById("toplevel_page_aio-login");if(!li)return;var isCurrent=li.classList.contains("current")||li.classList.contains("wp-has-current-submenu");var img=li.querySelector(".wp-menu-image img");if(img){img.src=isCurrent?"' . $active_icon . '":"' . $grey_icon . '";}var parentLink=li.querySelector(":scope > a.menu-top, :scope > a");var submenu=li.querySelector(".wp-submenu");if(!submenu){return;}var dashHref="",custLi=null;submenu.querySelectorAll(":scope > li").forEach(function(item){var a=item.querySelector("a");if(!a){return;}var href=a.getAttribute("href")||"";if(href.indexOf("customize.php")!==-1){custLi=item;return;}try{var u=new URL(href,window.location.origin);if(u.searchParams.get("page")==="aio-login"&&!u.searchParams.get("tab")){dashHref=href;}}catch(e){if(/[?&]page=aio-login(?:&|$)/.test(href)&&href.indexOf("tab=")===-1){dashHref=href;}}});if(parentLink&&dashHref){parentLink.setAttribute("href",dashHref);}if(custLi&&submenu.firstElementChild!==custLi){submenu.insertBefore(custLi,submenu.firstElementChild);}});'
			);
		}

		/**
		 * Whether to hide the AIO Login admin menu for users without manage_options.
		 * Show the 2FA-only screen when the 2FA module is on (Free or Pro). Pro still requires an active license.
		 *
		 * @return bool
		 */
		private function should_hide_aio_menu_for_limited_user()
		{
			if ( ! class_exists( '\AIO_Login\Two_Factor\Two_Factor' ) ) {
				return true;
			}
			if ( ! \AIO_Login\Two_Factor\Two_Factor::is_2fa_module_master_enabled() ) {
				return true;
			}
			if ( class_exists( '\AIO_Login\Aio_Login' ) && \AIO_Login\Aio_Login::has_pro() ) {
				if ( function_exists( 'aiologin_has_active_license' ) && ! aiologin_has_active_license() ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Base admin URL for the AIO Login app (site or network).
		 *
		 * @param array $args Extra query args.
		 * @return string
		 */
		public function get_plugin_admin_url( $args = array() ) {
			$args = array_merge( array( 'page' => 'aio-login' ), $args );
			$base = is_network_admin()
				? network_admin_url( 'admin.php' )
				: admin_url( 'admin.php' );
			return add_query_arg( $args, $base );
		}

		/**
		 * Admin_menu (site admin).
		 */
		public function admin_menu()
		{
			$is_limited_user = ! current_user_can('manage_options');

			if ($is_limited_user && $this->should_hide_aio_menu_for_limited_user()) {
				return;
			}

			$this->register_aio_login_menus( false, $is_limited_user );
		}

		/**
		 * Full AIO Login plugin menu in Network Admin.
		 */
		public function network_admin_menu()
		{
			if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) {
				return;
			}

			$this->register_aio_login_menus( true, false );
		}

		/**
		 * Register top-level + submenu pages for site or network admin.
		 *
		 * @param bool $for_network     Network Admin context.
		 * @param bool $is_limited_user Limited site user (2FA only).
		 */
		private function register_aio_login_menus( $for_network, $is_limited_user ) {
			$cap = $for_network ? 'manage_network_options' : 'read';

			add_menu_page(
				__('All in One Login', 'change-wp-admin-login'),
				__('AIO Login', 'change-wp-admin-login'),
				$cap,
				'aio-login',
				array($this, 'admin_page'),
				AIO_LOGIN__DIR_URL . 'assets/images/icons/aio-login-menu-grey.svg'
			);

			if ($is_limited_user) {
				add_submenu_page(
					'aio-login',
					__('2FA', 'change-wp-admin-login'),
					__('2FA', 'change-wp-admin-login'),
					$cap,
					'aio-login',
					array($this, 'admin_page')
				);
				return;
			}

			// Dashboard keeps parent slug so top-level AIO Login still lands here.
			add_submenu_page(
				'aio-login',
				__('Dashboard', 'change-wp-admin-login'),
				__('Dashboard', 'change-wp-admin-login'),
				$cap,
				'aio-login',
				array($this, 'admin_page')
			);

			// Register early; fix_customize_submenu_link() moves it above Dashboard visually (Figma).
			$this->register_login_customize_submenu( $cap );

			$tabs = $this->settings_tabs;

			foreach ($tabs as $tab) {
				if ('dashboard' === $tab['slug']) {
					continue;
				}

				$submenu_slug = $for_network
					? network_admin_url( 'admin.php?page=aio-login&tab=' . $tab['slug'] )
					: 'admin.php?page=aio-login&tab=' . $tab['slug'];

				add_submenu_page(
					'aio-login',
					$tab['title'],
					$tab['title'],
					$cap,
					$submenu_slug
				);
			}
		}

		/**
		 * WP admin submenu: open login page in Customizer (handler enforces Pro / caps).
		 *
		 * @param string $cap Capability.
		 */
		private function register_login_customize_submenu( $cap = 'read' ) {
			add_submenu_page(
				'aio-login',
				__( 'Customize', 'change-wp-admin-login' ),
				__( 'Customize', 'change-wp-admin-login' ),
				$cap,
				'aio-login-customize',
				array( $this, 'open_login_customizer' )
			);
		}

		/**
		 * Point AIO Login → Customize submenu at customize.php directly (opens AIO Login Customize panel).
		 * Avoids stopping on admin.php?page=aio-login-customize; WP treats full http(s) URLs as link hrefs.
		 *
		 * Do NOT move Customize to index 0 in $submenu — WordPress uses the first submenu URL as the
		 * top-level menu href. Dashboard must stay first so "AIO Login" opens the Dashboard.
		 * Visual order (Customize above Dashboard) is handled in CSS/JS (Figma / AIOL-774 Issue 1).
		 */
		public function fix_customize_submenu_link() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			global $submenu;
			if ( empty( $submenu['aio-login'] ) || ! is_array( $submenu['aio-login'] ) ) {
				return;
			}
			foreach ( $submenu['aio-login'] as $i => $item ) {
				if ( isset( $item[2] ) && 'aio-login-customize' === $item[2] ) {
					$submenu['aio-login'][ $i ][2] = $this->get_login_customizer_target_url();
					break;
				}
			}
		}

		/**
		 * Full customize.php URL for wp-login preview + AIO Login panel (same for redirect and submenu link).
		 *
		 * @return string
		 */
		private function get_login_customizer_target_url() {
			$preview_url = add_query_arg(
				array(
					'aio_login_customizer_preview' => '1',
				),
				site_url( 'wp-login.php' )
			);

			$return_after_close = admin_url( 'admin.php?page=aio-login' );

			// Nested `autofocus` so customize.php receives $_REQUEST['autofocus'] as an array. Open the
			// AIO Login Customize panel only (not a subsection like Themes).
			return add_query_arg(
				array(
					'aio_login_customizer' => '1',
					'url'                  => $preview_url,
					'return'               => $return_after_close,
					'autofocus'            => array(
						'panel' => 'aio_login_customizer',
					),
				),
				admin_url( 'customize.php' )
			);
		}

		/**
		 * Redirect before any admin HTML (fonts, notices) so wp_safe_redirect() succeeds.
		 * The submenu callback runs too late and triggers "headers already sent" on some setups.
		 */
		public function redirect_login_customizer_on_admin_init() {
			if ( ! isset( $_GET['page'] ) || 'aio-login-customize' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			$this->do_login_customizer_redirect();
		}

		/**
		 * Open WP Customizer for wp-login.php preview (Pro feature).
		 * Fallback only if admin_init did not run first; normally redirects from admin_init.
		 */
		public function open_login_customizer() {
			$this->do_login_customizer_redirect();
		}

		/**
		 * Perform redirect for the Customize submenu (login Customizer ships with the free plugin).
		 */
		private function do_login_customizer_redirect() {
			if ( ! current_user_can( 'read' ) ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'change-wp-admin-login' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				$this->safe_redirect_exit( admin_url( 'admin.php?page=aio-login' ) );
			}

			$this->safe_redirect_exit( $this->get_login_customizer_target_url() );
		}

		/**
		 * Redirect or JS fallback when headers are already sent.
		 *
		 * @param string $url Destination URL.
		 */
		private function safe_redirect_exit( $url ) {
			if ( headers_sent() ) {
				$this->print_js_redirect( $url );
				exit;
			}

			wp_safe_redirect( $url );
			exit;
		}

		/**
		 * JS redirect fallback when HTTP headers already sent.
		 *
		 * @param string $url Destination URL.
		 */
		private function print_js_redirect( $url ) {
			$script = 'window.location.href=' . wp_json_encode( $url ) . ';';
			if ( function_exists( 'wp_print_inline_script_tag' ) ) {
				wp_print_inline_script_tag( $script );
			} else {
				echo '<script>' . $script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		/**
		 * Show "Get Pro" for free installs, or Pro without an active license.
		 * Hide when Pro is active and any valid license is active (Freemius or custom).
		 *
		 * @return bool
		 */
		private function should_show_get_pro_tab()
		{
			if ( ! \AIO_Login\Aio_Login::has_pro() ) {
				return true;
			}

			if ( function_exists( 'aiologin_has_active_license' ) && aiologin_has_active_license() ) {
				return false;
			}

			return true;
		}

		/**
		 * Admin_page.
		 */
		public function admin_page()
		{
			// Old in-app Customize tab removed — send bookmarks to Dashboard.
			if ( isset( $_GET['tab'] ) && 'customization' === $_GET['tab'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->safe_redirect_exit( $this->get_plugin_admin_url() );
			}

			$is_limited_user = ! current_user_can('manage_options') && ! current_user_can( 'manage_network_options' );

			if ($is_limited_user && $this->should_hide_aio_menu_for_limited_user()) {
				$this->safe_redirect_exit( admin_url( 'index.php' ) );
			}

			// For limited users, always force URL to 2FA so Vue doesn't default to Dashboard.
			if ($is_limited_user) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing; capability checked above.
				$requested_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';

				if ('2fa' !== $requested_tab) {
					$this->safe_redirect_exit( $this->get_plugin_admin_url( array( 'tab' => '2fa' ) ) );
				}
			}

			$settings_tab = $this->settings_tabs;
			$setting_tab_slug = $is_limited_user ? '2fa' : 'dashboard';
			$settings_sub_tab = array();
			$setting_sub_tab_slug = '';

			if (isset($_GET['tab']) && !empty($_GET['tab'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Arrays.ArrayKeySpacingRestrictions.SpacesAroundArrayKeys
				$setting_tab_slug = sanitize_text_field(wp_unslash($_GET['tab'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			if ($is_limited_user && '2fa' !== $setting_tab_slug) {
				$setting_tab_slug = '2fa';
			}

			if ( ! $is_limited_user && 'getpro' === $setting_tab_slug && ! $this->should_show_get_pro_tab() ) {
				$this->safe_redirect_exit( $this->get_plugin_admin_url() );
			}

			$tabs_source = $this->settings_tabs;
			if ($is_limited_user) {
				$tabs_source = array(
					'2fa' => array(
						'title' => __('2FA', 'change-wp-admin-login'),
						'slug'  => '2fa',
						'icon'  => '2fa-icon',
						'sub-tabs' => array(
							'authentication-methods' => array(
								'title' => __('Authentication Methods', 'change-wp-admin-login'),
								'slug'  => 'authentication-methods',
							),
						),
					),
				);
			}
			if (isset($tabs_source[$setting_tab_slug]['sub-tabs']) && !empty($tabs_source[$setting_tab_slug]['sub-tabs'])) {
				$setting_sub_tab_slug = array_key_first($tabs_source[$setting_tab_slug]['sub-tabs']);
				$settings_sub_tab = $tabs_source[$setting_tab_slug]['sub-tabs'];
			}

			if (isset($_GET['sub-tab']) && !empty($_GET['sub-tab'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$setting_sub_tab_slug = sanitize_text_field(wp_unslash($_GET['sub-tab'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			if ($is_limited_user && 'authentication-methods' !== $setting_sub_tab_slug) {
				$setting_sub_tab_slug = 'authentication-methods';
			}

			require_once AIO_LOGIN__DIR_PATH . 'includes/admin/settings/admin.php';
		}

		/**
		 * Return a fresh wp_rest nonce for admin SPA axios (Multisite cookie auth).
		 */
		public function ajax_refresh_rest_nonce() {
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}

			check_ajax_referer( 'aio_login_admin', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
				wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
			}

			wp_send_json_success(
				array(
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'rest_url' => esc_url_raw( get_rest_url( null, '/' ) ),
				)
			);
		}

		public function rest_api_init()
		{
			register_rest_route(
				'aio-login/dashboard',
				'/get-settings',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard',
				'/get-counts',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_counts'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/limit-login-attempts',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'update_limit_login_attempts'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/two-factor-authentication',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'update_two_factor_authentication'),
					// Site-wide dashboard toggle — manage_options required (AIOL-770).
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			// Limited 2FA status endpoint for logged-in users.
			register_rest_route(
				'aio-login/security',
				'/2fa-status',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_2fa_status'),
					'permission_callback' => array($this, 'check_2fa_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/block-ip-address',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'update_block_ip_address'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/logs',
				'/lockouts',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_lockouts'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/logs',
				'/failed-logins',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_failed_logins'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			// User Enumeration Protection endpoints
			register_rest_route(
				'aio-login/dashboard',
				'/user-enumeration-settings',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_user_enumeration_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/user-enumeration-settings',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'update_user_enumeration_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			// Activity Log Settings endpoints
			register_rest_route(
				'aio-login/dashboard',
				'/activity-log-settings',
				array(
					'methods' => 'GET',
					'callback' => array($this, 'get_activity_log_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);

			register_rest_route(
				'aio-login/dashboard/update',
				'/activity-log-settings',
				array(
					'methods' => 'POST',
					'callback' => array($this, 'update_activity_log_settings'),
					'permission_callback' => array(Helper::class, 'get_api_permission'),
				)
			);
		}

		/**
		 * Whether any site-wide 2FA method is enabled (Email OTP and/or Authenticator).
		 * Dashboard toggle is global — not TOTP-only (AIOL-769).
		 *
		 * @return bool
		 */
		private function is_any_site_2fa_method_enabled() {
			if ( class_exists( '\AIO_Login\Two_Factor\Two_Factor' )
				&& is_callable( array( '\AIO_Login\Two_Factor\Two_Factor', 'is_2fa_module_master_enabled' ) ) ) {
				return (bool) \AIO_Login\Two_Factor\Two_Factor::is_2fa_module_master_enabled();
			}

			$email = Network_Settings::get_option( 'aio_login_pro__2fa_email_otp_enable', 'off' );
			$totp  = Network_Settings::get_option( 'aio_login_pro__two_factor_auth_enable', 'off' );

			return ( 'on' === $email || 'on' === $totp );
		}

		public function get_settings()
		{
			$is_admin = current_user_can('manage_options');
			$current_user_id = get_current_user_id();

			$limit_login = Network_Settings::get_option('aio_login_limit_attempts_enable', 'off');
			$block_ip = get_option('aio_login_block_ip_address_enable', 'off');

			$settings = array(
				'limit_login_attempts' => $limit_login,
				// Admin: any site-wide 2FA method (Email OTP or Authenticator). Users: own TOTP meta.
				'two_factor_auth' => $is_admin
					? ( $this->is_any_site_2fa_method_enabled() ? 'on' : 'off' )
					: get_user_meta( $current_user_id, 'aio_login_pro__two_factor_auth_enable', true ),
				'block_ip_address' => $block_ip,
			);

			return rest_ensure_response($settings);
		}

		public function get_lockouts()
		{
			$logs = Helper::get_logs('lockout');

			$logs = array_slice($logs, 0, 5);

			$logs = array_map(
				function ($log) {
					$log['time'] = wp_date( 'F j, Y, g:i a', (int) $log['time'] );
					return $log;
				},
				$logs
			);

			return rest_ensure_response($logs);
		}

		public function get_failed_logins()
		{
			$logs = Helper::get_logs('failed');

			$logs = array_slice($logs, 0, 5);

			$logs = array_map(
				function ($log) {
					$log['time'] = wp_date( 'F j, Y, g:i a', (int) $log['time'] );
					return $log;
				},
				$logs
			);

			return rest_ensure_response($logs);
		}

		public function get_counts($request)
		{
			$type = 'success';
			$duration = 'today';

			if (isset($request['type']) && !empty($request['type'])) {
				$type = sanitize_text_field(wp_unslash($request['type']));
			}

			if (isset($request['duration']) && !empty($request['duration'])) {
				$duration = sanitize_text_field(wp_unslash($request['duration']));
			}

			if (isset($request['blog_id']) && Network_Settings::is_multisite_install()) {
				$blog_id = absint($request['blog_id']);
				$count = Network_Settings::get_network_counts($type, $duration, $blog_id);
				return rest_ensure_response(array('count' => absint($count)));
			}

			if ('lockouts' === $type) {
				$count = Failed_Logins::get_lockout_attempts_count($duration);
			} else {
				$count = Failed_Logins::get_attempts_count($type, $duration);
			}

			return rest_ensure_response(array('count' => absint($count)));
		}

		public function update_limit_login_attempts($request)
		{
			$nonce_check = $this->verify_dashboard_rest_nonce( $request );
			if ( is_wp_error( $nonce_check ) ) {
				return $nonce_check;
			}

			if (!\AIO_Login\AIO_Login::has_pro()) {
				return new \WP_Error(
					'pro_required',
					__('Limit Login Attempts requires AIO Login Pro.', 'change-wp-admin-login'),
					array('status' => 403)
				);
			}

			if (class_exists('\AIO_Login_Pro\Plan\Plan_Features') && !\AIO_Login_Pro\Plan\Plan_Features::can('limit_login_attempts')) {
				return new \WP_Error(
					'plan_restricted',
					__('Your plan does not include Limit Login Attempts.', 'change-wp-admin-login'),
					array('status' => 403)
				);
			}

			$params = $request->get_params();

			if (isset($params['value'])) {
				$value = sanitize_text_field(wp_unslash($params['value']));
				$value = 'on' === $value ? 'on' : 'off';

				$result = Network_Settings::update_option('aio_login_limit_attempts_enable', $value);
				if (is_wp_error($result)) {
					return $result;
				}

				return rest_ensure_response(
					array(
						'status' => 'success',
						'message' => __('Limit login attempts updated successfully', 'change-wp-admin-login'),
					)
				);
			}

			return new \WP_Error(
				'invalid_value',
				__('Invalid value', 'change-wp-admin-login'),
				array('status' => 400)
			);
		}

		public function update_two_factor_authentication($request)
		{
			$nonce_check = $this->verify_dashboard_rest_nonce( $request );
			if ( is_wp_error( $nonce_check ) ) {
				return $nonce_check;
			}

			$params = $request->get_params();

			if (isset($params['value'])) {
				$value = sanitize_text_field(wp_unslash($params['value']));
				$value = 'on' === $value ? 'on' : 'off';

				if ( 'off' === $value ) {
					// Disable every site-wide method so the dashboard toggle stays in sync.
					$result = Network_Settings::update_option( 'aio_login_pro__two_factor_auth_enable', 'off' );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					$result = Network_Settings::update_option( 'aio_login_pro__2fa_email_otp_enable', 'off' );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				} else {
					$email_on = ( 'on' === Network_Settings::get_option( 'aio_login_pro__2fa_email_otp_enable', 'off' ) );
					$totp_on  = ( 'on' === Network_Settings::get_option( 'aio_login_pro__two_factor_auth_enable', 'off' ) );
					// If nothing is active yet: prefer Authenticator (TOTP); Free-only falls back to Email OTP.
					if ( ! $email_on && ! $totp_on ) {
						$has_pro = class_exists( '\AIO_Login\AIO_Login' ) && \AIO_Login\AIO_Login::has_pro();
						$option  = $has_pro
							? 'aio_login_pro__two_factor_auth_enable'
							: 'aio_login_pro__2fa_email_otp_enable';
						$result  = Network_Settings::update_option( $option, 'on' );
						if ( is_wp_error( $result ) ) {
							return $result;
						}
					}
				}

				return rest_ensure_response(
					array(
						'status' => 'success',
						'message' => __('Two factor authentication updated successfully', 'change-wp-admin-login'),
					)
				);
			}

			return new \WP_Error(
				'invalid_value',
				__('Invalid value', 'change-wp-admin-login'),
				array('status' => 400)
			);
		}

		/**
		 * Verify REST request nonce (X-WP-Nonce and/or body _wpnonce).
		 *
		 * @param \WP_REST_Request $request Request.
		 * @return true|\WP_Error
		 */
		private function verify_dashboard_rest_nonce( $request ) {
			$header = $request->get_header( 'X-WP-Nonce' );
			if ( $header && wp_verify_nonce( $header, 'wp_rest' ) ) {
				return true;
			}

			$params = $request->get_params();
			$body   = isset( $params['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ) : '';
			if ( $body && ( wp_verify_nonce( $body, 'wp_rest' ) || wp_verify_nonce( $body, 'aio_login_dashboard' ) || wp_verify_nonce( $body, 'aio_login_activity_log_settings' ) ) ) {
				return true;
			}

			// Cookie-authenticated REST already validated X-WP-Nonce via rest_cookie_check_errors
			// when the header is present; reject otherwise.
			return new \WP_Error(
				'rest_cookie_invalid_nonce',
				__( 'Cookie check failed', 'change-wp-admin-login' ),
				array( 'status' => 403 )
			);
		}

		public function check_2fa_permission() {
			return is_user_logged_in();
		}

		public function get_2fa_status() {
			$user_id = get_current_user_id();

			return rest_ensure_response(array(
				'global_enabled' => $this->is_any_site_2fa_method_enabled() ? 'on' : 'off',
				'user_enabled'   => get_user_meta($user_id, 'aio_login_pro__two_factor_auth_enable', true),
			));
		}

		public function update_block_ip_address($request)
		{
			$nonce_check = $this->verify_dashboard_rest_nonce( $request );
			if ( is_wp_error( $nonce_check ) ) {
				return $nonce_check;
			}

			$params = $request->get_params();

					if (isset($params['value'])) {
				$value = sanitize_text_field(wp_unslash($params['value']));
				$value = 'on' === $value ? 'on' : 'off';

				update_option('aio_login_block_ip_address_enable', $value);

				return rest_ensure_response(
					array(
						'status' => 'success',
						'message' => __('Block IP address updated successfully', 'change-wp-admin-login'),
					)
				);
			}

			return new \WP_Error(
				'invalid_value',
				__('Invalid value', 'change-wp-admin-login'),
				array('status' => 400)
			);
		}

		/**
		 * Get user enumeration protection settings
		 */
		public function get_user_enumeration_settings()
		{
			// Direct check for pro plugin
			$has_pro = false;

			// Check if pro plugin is active
			if (function_exists('is_plugin_active')) {
				$has_pro = is_plugin_active('aio-login-pro/aio-login-pro.php');
			}

			// If not found via is_plugin_active, check if class exists
			if (!$has_pro && class_exists('AIO_Login_Pro\\AIO_Login_Pro')) {
				$has_pro = true;
			}

			// Also check the filter
			if (!$has_pro) {
				$has_pro = apply_filters('aio_login_has_pro', false);
			}

			$settings = array(
				'enable_protection' => get_option('aio_login_user_enumeration_enable', 'off'),
				'stop_oembed_calls' => get_option('aio_login_user_enumeration_oembed', 'off'),
				'disable_author_sitemaps' => get_option('aio_login_user_enumeration_sitemaps', 'off'),
				'remove_comment_numbers' => get_option('aio_login_user_enumeration_comments', 'off'),
				'protect_rest_api' => get_option('aio_login_user_enumeration_rest_api', 'off'),
				'login_registration_errors' => get_option('aio_login_user_enumeration_login_registration', 'off'),
				'has_pro' => $has_pro ? 'true' : 'false',
			);

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => $settings,
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

		/**
		 * Update user enumeration protection settings
		 */
		public function update_user_enumeration_settings($request)
		{
			$nonce_check = $this->verify_dashboard_rest_nonce( $request );
			if ( is_wp_error( $nonce_check ) ) {
				return $nonce_check;
			}

			$params = $request->get_params();
			$settings = isset($params['settings']) ? $params['settings'] : array();

			// Update settings
			$enable_protection = isset($settings['enable_protection']) && ($settings['enable_protection'] === true || $settings['enable_protection'] === 'on') ? 'on' : 'off';
			$stop_oembed_calls = isset($settings['stop_oembed_calls']) && ($settings['stop_oembed_calls'] === true || $settings['stop_oembed_calls'] === 'on') ? 'on' : 'off';
			$disable_author_sitemaps = isset($settings['disable_author_sitemaps']) && ($settings['disable_author_sitemaps'] === true || $settings['disable_author_sitemaps'] === 'on') ? 'on' : 'off';
			$remove_comment_numbers = isset($settings['remove_comment_numbers']) && ($settings['remove_comment_numbers'] === true || $settings['remove_comment_numbers'] === 'on') ? 'on' : 'off';
			$protect_rest_api = isset($settings['protect_rest_api']) && ($settings['protect_rest_api'] === true || $settings['protect_rest_api'] === 'on') ? 'on' : 'off';
			$login_registration_errors = isset($settings['login_registration_errors']) && ($settings['login_registration_errors'] === true || $settings['login_registration_errors'] === 'on') ? 'on' : 'off';

			update_option('aio_login_user_enumeration_enable', $enable_protection);
			update_option('aio_login_user_enumeration_oembed', $stop_oembed_calls);
			update_option('aio_login_user_enumeration_sitemaps', $disable_author_sitemaps);
			update_option('aio_login_user_enumeration_comments', $remove_comment_numbers);
			update_option('aio_login_user_enumeration_rest_api', $protect_rest_api);
			update_option('aio_login_user_enumeration_login_registration', $login_registration_errors);

			return rest_ensure_response(array('success' => true));
		}

		/**
		 * Get activity log settings
		 */
		public function get_activity_log_settings()
		{
			// Enumeration logging settings live in Pro only (G5).
			$settings = array(
				'enable_ip_geolocation'    => \AIO_Login\Aio_Login::has_pro()
					? get_option( 'aio_login_enable_ip_geolocation', 'off' )
					: 'off',
				'ip_geolocation_available' => \AIO_Login\Aio_Login::has_pro(),
			);

			$response = array(
				'success' => true,
				'data' => $settings,
				'nonce' => wp_create_nonce('aio_login_activity_log_settings'),
			);

			return rest_ensure_response($response);
		}

		/**
		 * Update activity log settings
		 */
		public function update_activity_log_settings($request)
		{
			$nonce_check = $this->verify_dashboard_rest_nonce( $request );
			if ( is_wp_error( $nonce_check ) ) {
				return $nonce_check;
			}

			$params = $request->get_params();
			$settings = isset($params['settings']) ? $params['settings'] : array();

			// Geolocation opt-in is Pro-only; Free ignores/disallows updates.
			// Enumeration logging is saved via aio-login-pro REST only.
			if ( array_key_exists( 'enable_ip_geolocation', $settings ) && \AIO_Login\Aio_Login::has_pro() ) {
				$enable_geo = ( true === $settings['enable_ip_geolocation'] || 'on' === $settings['enable_ip_geolocation'] ) ? 'on' : 'off';
				update_option( 'aio_login_enable_ip_geolocation', $enable_geo );
			}

			return rest_ensure_response(array('success' => true));
		}

		/**
		 * Default + normalized notification event flags (lockout is always on).
		 *
		 * @param string $option_name Option storing JSON.
		 * @return array{lockout:bool,failed_login:bool,user_enumeration:bool}
		 */
		/**
		 * Keep dashboard login redirect working for limited roles.
		 *
		 * @param string           $redirect_to Requested redirect URL.
		 * @param string           $requested_redirect_to Raw redirect request value.
		 * @param \WP_User|\WP_Error $user Logged in user object.
		 *
		 * @return string
		 */
		public function fix_subscriber_login_redirect($redirect_to, $requested_redirect_to, $user)
		{
			if (!($user instanceof \WP_User)) {
				return $redirect_to;
			}

			if (user_can($user, 'manage_options') || !user_can($user, 'read')) {
				return $redirect_to;
			}

			// Only force wp-admin when site-wide 2FA is on (setup / profile flows).
			if ( ! $this->is_any_site_2fa_method_enabled() ) {
				return $redirect_to;
			}

			return admin_url();
		}

		/**
		 * Prevent WooCommerce from forcing subscribers to My Account.
		 *
		 * Only bypass on profile screens when site-wide 2FA is enabled (AIOL-770).
		 *
		 * @param bool $prevent_access Whether Woo should block wp-admin.
		 *
		 * @return bool
		 */
		public function allow_subscriber_admin_access($prevent_access)
		{
			if (!is_user_logged_in()) {
				return $prevent_access;
			}

			if ( ! $this->is_any_site_2fa_method_enabled() ) {
				return $prevent_access;
			}

			global $pagenow;
			$allowed_pages = array( 'profile.php', 'user-edit.php' );
			if ( ! in_array( (string) $pagenow, $allowed_pages, true ) ) {
				return $prevent_access;
			}

			$current_user = wp_get_current_user();
			if (!($current_user instanceof \WP_User)) {
				return $prevent_access;
			}

			if ( user_can( $current_user, 'read' ) && ! user_can( $current_user, 'manage_options' ) ) {
				return false;
			}

			return $prevent_access;
		}

		/**
		 * Getting instance of class.
		 *
		 * @return Admin
		 */
		public static function get_instance()
		{
			static $instance = null;

			if (is_null($instance)) {
				$instance = new self();
			}

			return $instance;
		}
	}
}
