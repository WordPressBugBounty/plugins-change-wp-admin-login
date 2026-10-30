<?php
/**
 * WordPress Customizer integration for wp-login.php design.
 *
 * @package AIO_Login_Pro
 */

namespace AIO_Login_Pro\Login_Customization;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login_Pro\\Login_Customization\\Login_Customizer' ) ) {
	/**
	 * Class Login_Customizer
	 */
	class Login_Customizer {

		/**
		 * Constructor.
		 */
		private function __construct() {
			add_filter( 'option_aio_login__customization_templates', array( $this, 'filter_option_template_when_free' ), 10, 1 );
			add_filter( 'option_aio_login_el_text_font_family', array( $this, 'filter_option_font_family_when_free' ), 10, 1 );
			add_filter( 'option_aio_login_disable_logo', array( $this, 'filter_option_disable_logo_legacy' ), 10, 1 );
			add_action( 'init', array( $this, 'maybe_persist_free_tier_defaults' ), 5 );

			add_action( 'setup_theme', array( $this, 'authenticate_preview_user' ), 1 );
			add_filter( 'authenticate', array( $this, 'filter_authenticate_for_preview' ), 999, 3 );
			add_filter( 'wp_login_errors', array( $this, 'filter_login_errors_for_preview' ), 999, 1 );
			add_filter( 'user_has_cap', array( $this, 'filter_user_has_cap_for_customizer' ), 10, 4 );
			add_filter( 'map_meta_cap', array( $this, 'filter_map_meta_cap_for_customizer' ), 10, 4 );
			$this->maybe_normalize_preview_post_request();

			add_action( 'customize_register', array( $this, 'customize_register' ) );
			add_action( 'customize_preview_init', array( $this, 'customize_preview_init' ) );
			add_action( 'login_enqueue_scripts', array( $this, 'customize_preview_init' ), 20 );
			add_action( 'login_footer', array( $this, 'output_customizer_preview_settings' ), 20 );
			add_action( 'customize_controls_enqueue_scripts', array( $this, 'customize_controls_enqueue_scripts' ) );
			add_action( 'customize_save_after', array( $this, 'customize_save_after' ) );
		}

		/**
		 * Template slugs allowed without Pro: Modern Center + Future Tech.
		 *
		 * @return array<int, string>
		 */
		private function get_free_template_slugs() {
			return array( 'default', 'template-8' );
		}

		/**
		 * Customizer premium login themes (beyond Modern Center + Future Tech).
		 */
		private function plan_allows_customizer_login_themes() {
			/**
			 * Premium templates ship only in the Pro add-on (Guideline 5).
			 * Free Customizer never registers locked local premium themes.
			 */
			return (bool) apply_filters( 'aio_login_plan_allows_customizer_login_themes', false );
		}

		/**
		 * Customizer Google Fonts catalog (non-Inherit).
		 */
		private function plan_allows_customizer_google_fonts() {
			/** Google Fonts catalog ships only in the Pro add-on (Guideline 5). */
			return (bool) apply_filters( 'aio_login_plan_allows_customizer_google_fonts', false );
		}

		/**
		 * When plan does not include premium templates, only free templates (default, template-8) are allowed.
		 *
		 * @param mixed $value Stored option value.
		 * @return string
		 */
		public function filter_option_template_when_free( $value ) {
			if ( $this->plan_allows_customizer_login_themes() ) {
				return $value;
			}
			$free = $this->get_free_template_slugs();
			return in_array( (string) $value, $free, true ) ? $value : 'default';
		}

		/**
		 * When plan does not include Google Fonts, only Inherit is allowed.
		 *
		 * @param mixed $value Stored option value.
		 * @return string
		 */
		public function filter_option_font_family_when_free( $value ) {
			if ( $this->plan_allows_customizer_google_fonts() ) {
				return $value;
			}
			$free = 'Inherit';
			return (string) $value === (string) $free ? $value : $free;
		}

		/**
		 * Persist allowed Customizer values when plan or Pro activation does not include premium options.
		 */
		public function maybe_persist_free_tier_defaults() {
			static $checked = false;
			if ( $checked ) {
				return;
			}
			$checked = true;

			if ( ! $this->plan_allows_customizer_login_themes() ) {
				remove_filter( 'option_aio_login__customization_templates', array( $this, 'filter_option_template_when_free' ), 10 );
				$t_raw = get_option( 'aio_login__customization_templates', 'default' );
				add_filter( 'option_aio_login__customization_templates', array( $this, 'filter_option_template_when_free' ), 10, 1 );

				if ( ! in_array( (string) $t_raw, $this->get_free_template_slugs(), true ) ) {
					update_option( 'aio_login__customization_templates', 'default', false );
				}
			}

			if ( ! $this->plan_allows_customizer_google_fonts() ) {
				remove_filter( 'option_aio_login_el_text_font_family', array( $this, 'filter_option_font_family_when_free' ), 10 );
				$f_raw = get_option( 'aio_login_el_text_font_family', 'Inherit' );
				add_filter( 'option_aio_login_el_text_font_family', array( $this, 'filter_option_font_family_when_free' ), 10, 1 );

				if ( (string) $f_raw !== 'Inherit' ) {
					update_option( 'aio_login_el_text_font_family', 'Inherit', false );
					$existing = get_option( 'aio_login_elements_settings', array() );
					if ( is_array( $existing ) ) {
						$existing['text_font_family'] = 'Inherit';
						update_option( 'aio_login_elements_settings', $existing, false );
					}
				}
			}
		}

		/**
		 * Sanitize template option; force free default when Pro is off.
		 *
		 * @param mixed $value Value.
		 * @return string
		 */
		public function sanitize_login_template_option( $value ) {
			$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
			if ( ! $this->plan_allows_customizer_login_themes() && ! in_array( $value, $this->get_free_template_slugs(), true ) ) {
				return 'default';
			}
			return $value;
		}

		/**
		 * Sanitize font family option; force Inherit when plan does not include Google Fonts.
		 *
		 * @param mixed $value Value.
		 * @return string
		 */
		public function sanitize_login_font_family_option( $value ) {
			$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
			if ( ! $this->plan_allows_customizer_google_fonts() && 'Inherit' !== $value ) {
				return 'Inherit';
			}
			return $value;
		}

		/**
		 * Legacy default was the string "off" (logo not disabled). WP_Customize_Control::checkbox
		 * uses checked( $value ) which treats any non-empty string as truthy, so "off" showed checked.
		 * Normalize to false for correct UI and for !!value in preview JS.
		 *
		 * @param mixed $value Stored option.
		 * @return mixed
		 */
		public function filter_option_disable_logo_legacy( $value ) {
			return ( 'off' === $value ) ? false : $value;
		}

		/**
		 * Authenticate Customizer preview via validated logged-in cookie only (G02-001).
		 *
		 * Never trust wp_parse_auth_cookie field values alone — require HMAC validation
		 * through wp_validate_auth_cookie and a customize-capable user.
		 */
		public function authenticate_preview_user() {
			if ( empty( $_REQUEST['aio_login_customizer_preview'] ) && empty( $_GET['aio_login_customizer_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			if ( is_user_logged_in() ) {
				return;
			}
			if ( ! defined( 'LOGGED_IN_COOKIE' ) || empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
				return;
			}

			$cookie  = sanitize_text_field( wp_unslash( $_COOKIE[ LOGGED_IN_COOKIE ] ) );
			$user_id = wp_validate_auth_cookie( $cookie, 'logged_in' );
			if ( ! $user_id ) {
				return;
			}

			$user = get_userdata( (int) $user_id );
			if ( ! ( $user instanceof \WP_User ) || ! $user->exists() ) {
				return;
			}

			// Preview is an admin Customizer surface — require customize capability.
			if ( ! user_can( $user, 'customize' ) && ! user_can( $user, 'manage_options' ) ) {
				return;
			}

			wp_set_current_user( (int) $user_id );
		}

		/**
		 * Prevent wp_signon() on wp-login.php from redirecting to dashboard when in customizer preview.
		 *
		 * @param \WP_User|\WP_Error|null $user     User object or error.
		 * @param string                  $username Username.
		 * @param string                  $password Password.
		 * @return \WP_User|\WP_Error|null
		 */
		public function filter_authenticate_for_preview( $user, $username, $password ) {
			unset( $username, $password );
			// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- Customizer preview bootstrap only.
			$is_preview  = ! empty( $_GET['aio_login_customizer_preview'] ) || ! empty( $_REQUEST['aio_login_customizer_preview'] );
			$empty_creds = empty( $_POST['log'] ) && empty( $_POST['pwd'] );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing
			if ( $is_preview && $empty_creds ) {
				// Use core's ignored code so wp_authenticate() does not fire wp_login_failed
				// (custom codes like aio_customizer_preview were polluting Failed Logins).
				return new \WP_Error( 'empty_username', '' );
			}
			return $user;
		}

		/**
		 * Remove dummy error so preview renders clean login screen with no error notices.
		 *
		 * @param \WP_Error $errors Error object.
		 * @return \WP_Error
		 */
		public function filter_login_errors_for_preview( $errors ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Customizer preview flag only.
			$is_preview = ! empty( $_GET['aio_login_customizer_preview'] ) || ! empty( $_REQUEST['aio_login_customizer_preview'] );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			if ( is_wp_error( $errors ) && $is_preview ) {
				$errors->remove( 'aio_customizer_preview' );
				$errors->remove( 'empty_username' );
				$errors->remove( 'empty_password' );
			}
			return $errors;
		}

		/**
		 * When Customizer preview submits a POST form with _method=GET to wp-login.php,
		 * normalize REQUEST_METHOD to GET so that wp-login.php does not treat it as a login submission.
		 */
		private function maybe_normalize_preview_post_request() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- Customizer preview bootstrap only.
			$is_preview = ! empty( $_REQUEST['aio_login_customizer_preview'] );
			$method     = isset( $_POST['_method'] ) ? sanitize_text_field( wp_unslash( $_POST['_method'] ) ) : '';
			$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing
			if ( $is_preview
				&& 'GET' === strtoupper( $method )
				&& 'POST' === strtoupper( $request_method ) ) {
				$_SERVER['REQUEST_METHOD'] = 'GET';
			}
		}

		/**
		 * Whether the current request is in AIO Login Customizer context.
		 *
		 * @return bool
		 */
		private function is_aio_customizer_request_context() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only Customizer context detection.
			if ( ! empty( $_REQUEST['aio_login_customizer'] )
				|| ! empty( $_REQUEST['aio_login_customizer_preview'] )
				|| ! empty( $_GET['aio_login_customizer_preview'] )
				|| ! empty( $_COOKIE['aio_login_customizing'] ) ) {
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
				return true;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			// Do not treat every Appearance → Customize visit as AIO Login context
			// (that forced login preview / capability grants too broadly).
			$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
			return (
				str_contains( $referer, 'aio_login_customizer' )
				|| str_contains( $referer, 'aio_login_customizer_preview' )
			);
		}

		/**
		 * Grant customize, edit_theme_options, and switch_themes capabilities to administrators
		 * while in AIO Login Customizer context, preventing Core Customizer 403 / cheatin() screens.
		 *
		 * @param array<string, bool> $allcaps All capabilities of the user.
		 * @param array<int, string>  $caps    Required primitive capabilities.
		 * @param array<int, mixed>   $args    Arguments passed to current_user_can().
		 * @param \WP_User            $user    User object.
		 * @return array<string, bool>
		 */
		public function filter_user_has_cap_for_customizer( $allcaps, $caps, $args, $user ) {
			if ( $this->is_aio_customizer_request_context() ) {
				$can_manage = ! empty( $allcaps['manage_options'] ) || ! empty( $allcaps['manage_network_options'] );
				if ( $can_manage ) {
					$allcaps['customize']          = true;
					$allcaps['edit_theme_options'] = true;
					$allcaps['switch_themes']      = true;
				}
			}

			return $allcaps;
		}

		/**
		 * Map customize, edit_theme_options, and switch_themes to manage_options when in AIO Login Customizer context.
		 *
		 * @param array<int, string> $caps    Primitive capabilities to check.
		 * @param string             $cap     Capability being checked.
		 * @param int                $user_id User ID.
		 * @param array<int, mixed>  $args    Additional arguments.
		 * @return array<int, string>
		 */
		public function filter_map_meta_cap_for_customizer( $caps, $cap, $user_id, $args ) {
			if ( in_array( $cap, array( 'customize', 'edit_theme_options', 'switch_themes' ), true ) ) {
				if ( $this->is_aio_customizer_request_context() ) {
					$user = get_userdata( $user_id );
					if ( $user && ( ! empty( $user->allcaps['manage_options'] ) || ! empty( $user->allcaps['manage_network_options'] ) || is_super_admin( $user_id ) ) ) {
						return array( 'manage_options' );
					}
				}
			}

			return $caps;
		}

		/**
		 * Register panels/sections/controls.
		 *
		 * @param \WP_Customize_Manager $wp_customize Customizer.
		 */
		public function customize_register( $wp_customize ) {
			// Always register settings so Customizer never flags them as "unrecognized" during save/refresh requests.
			$this->register_all_settings( $wp_customize );

			// Always register the AIO Login panel under Appearance → Customize (and AIO Login → Customize).
			// Live login preview still uses aio_login_customizer / preview query args from the plugin entry point.

			$wp_customize->add_panel(
				'aio_login_customizer',
				array(
					'title'       => __( 'AIO Login Customize', 'change-wp-admin-login' ),
					'priority'    => 35,
					'capability'  => is_multisite() ? 'manage_network_options' : 'manage_options',
					'description' => __( 'Customize your login screen with live preview (themes, logo, form, footer links, fonts, and WordPress Additional CSS).', 'change-wp-admin-login' ),
				)
			);

			// Order matches common LoginPress-style menus (no recaptcha / error / welcome message sections).
			$this->register_themes( $wp_customize );
			$this->register_logo( $wp_customize );
			$this->register_background( $wp_customize );
			$this->register_form( $wp_customize );
			$this->register_forgot( $wp_customize );
			$this->register_button( $wp_customize );
			$this->register_form_footer( $wp_customize );
			$this->register_typography( $wp_customize );
			$this->register_custom_css_link( $wp_customize );
			$this->register_reset_section( $wp_customize );
		}

		/**
		 * Menu item that opens WordPress core Additional CSS (no custom CSS stored by this plugin).
		 *
		 * @param \WP_Customize_Manager $wp_customize Customizer.
		 */
		private function register_custom_css_link( $wp_customize ) {
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-customization/class-customize-control-additional-css-link.php';

			if ( ! $wp_customize->get_setting( 'aio_login_additional_css_link_ui' ) ) {
				$wp_customize->add_setting(
					'aio_login_additional_css_link_ui',
					array(
						'type'              => 'option',
						'default'           => '',
						'sanitize_callback' => '__return_empty_string',
						'transport'         => 'postMessage',
					)
				);
			}

			$wp_customize->add_section(
				'aio_login_custom_css',
				array(
					'title'       => __( 'Custom CSS', 'change-wp-admin-login' ),
					'panel'       => 'aio_login_customizer',
					'priority'    => 90,
					'description' => __( 'Login page CSS is managed with WordPress Additional CSS. Use the button below to open it.', 'change-wp-admin-login' ),
				)
			);

			$wp_customize->add_control(
				new Customize_Control_Additional_Css_Link(
					$wp_customize,
					'aio_login_additional_css_link_ui',
					array(
						'section'     => 'aio_login_custom_css',
						'label'       => __( 'WordPress Additional CSS', 'change-wp-admin-login' ),
						'description' => __( 'Opens the built-in Additional CSS editor. CSS is saved by WordPress, not by this plugin.', 'change-wp-admin-login' ),
					)
				)
			);
		}

		/**
		 * Bottom panel: reset all login Customizer options to plugin defaults.
		 *
		 * @param \WP_Customize_Manager $wp_customize Customizer.
		 */
		private function register_reset_section( $wp_customize ) {
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-customization/class-customize-control-reset.php';

			if ( ! $wp_customize->get_setting( 'aio_login_customizer_reset_ui' ) ) {
				$wp_customize->add_setting(
					'aio_login_customizer_reset_ui',
					array(
						'type'              => 'option',
						'default'           => '',
						'sanitize_callback' => '__return_empty_string',
						'transport'         => 'postMessage',
					)
				);
			}

			$wp_customize->add_section(
				'aio_login_reset',
				array(
					'title'    => __( 'Reset customization', 'change-wp-admin-login' ),
					'panel'    => 'aio_login_customizer',
					'priority' => 1000,
				)
			);

			$wp_customize->add_control(
				new Customize_Control_Reset(
					$wp_customize,
					'aio_login_customizer_reset_ui',
					array(
						'section'     => 'aio_login_reset',
						'label'       => __( 'Master reset', 'change-wp-admin-login' ),
						'description' => __( 'Restore every AIO Login Customize option to its default. Click Publish to save. Other Customizer sections (theme, widgets, etc.) are not changed.', 'change-wp-admin-login' ),
					)
				)
			);
		}

		/**
		 * Default values for every option used by the login Customizer (must match register_all_settings defaults).
		 *
		 * @return array<string, mixed>
		 */
		public function get_customizer_default_values() {
			return array(
				'aio_login_background_color'           => '#f1f1f1',
				'aio_login_background_image'          => 0,
				'aio_login_background_image_mobile'    => 0,
				'aio_login__customization_templates'   => 'default',
				'aio_login_disable_logo'               => false,
				'aio_login_logo'                       => 0,
				'aio_login_logo_width'                 => 84,
				'aio_login_logo_height'                => 84,
				'aio_login_margin_bottom'              => 0,
				'aio_login_logo_url'                   => '',
				'aio_login_logo_title'                 => '',
				'aio_login_login_page_title'           => '',
				'aio_login_favicon'                    => 0,
				'aio_login_forgot_background_color'    => '',
				'aio_login_forgot_background_image'    => 0,
				'aio_login_el_text_font_family'        => 'Inherit',
				'aio_login_el_background_repeat'       => 'no-repeat',
				'aio_login_el_background_position'      => 'center center',
				'aio_login_el_background_size'         => 'cover',
				'aio_login_el_form_transparent'        => false,
				'aio_login_el_form_width'              => '',
				'aio_login_el_form_min_height'         => '',
				'aio_login_el_form_border_radius'      => '',
				'aio_login_el_form_shadow'             => '',
				'aio_login_el_form_shadow_opacity'     => '0.15',
				'aio_login_el_form_padding'            => '',
				'aio_login_el_form_border'             => '',
				'aio_login_el_btn_bg_color'            => '',
				'aio_login_el_btn_hover_color'         => '',
				'aio_login_el_btn_text_color'          => '',
				'aio_login_el_btn_border_color'        => '',
				'aio_login_el_btn_size'                => '',
				'aio_login_el_btn_padding'             => '',
				'aio_login_el_btn_padding_tb'          => '',
				'aio_login_el_btn_border_radius'       => '',
				'aio_login_el_btn_shadow'              => '',
				'aio_login_el_btn_shadow_opacity'      => '0.2',
				'aio_login_el_btn_text_size'           => '',
				'aio_login_el_label_color'             => '',
				'aio_login_el_remember_label_color'    => '',
				'aio_login_el_label_font_size'         => '',
				'aio_login_el_remember_font_size'      => '',
				'aio_login_el_input_margin'            => '',
				'aio_login_el_input_bg_color'          => '',
				'aio_login_el_input_text_color'        => '',
				'aio_login_el_input_width'             => '',
				'aio_login_el_link_color'              => '',
			);
		}

		/**
		 * Register all settings unconditionally (controls/sections are added only in our context).
		 *
		 * @param \WP_Customize_Manager $wp_customize Customizer.
		 */
		private function register_all_settings( $wp_customize ) {
			$cap = is_multisite() ? 'manage_network_options' : 'manage_options';
			$opt = function ( $id, $default = '', $sanitize = null, $transport = 'postMessage' ) use ( $wp_customize, $cap ) {
				$args = array(
					'type'              => 'option',
					'capability'        => $cap,
					'transport'         => $transport,
					'default'           => $default,
					// WP.org / Plugin Check: every Customizer setting must sanitize on save.
					'sanitize_callback' => $sanitize ? $sanitize : 'sanitize_text_field',
				);
				if ( ! $wp_customize->get_setting( $id ) ) {
					$wp_customize->add_setting( $id, $args );
				}
			};

			// Core options used by controls (must always exist to avoid "unrecognized" + invalid publish).
			$opt( 'aio_login_background_color', '#f1f1f1', array( $this, 'sanitize_hex_color_allow_empty' ) );
			$opt( 'aio_login_background_image', 0, 'absint', 'refresh' );
			$opt( 'aio_login_background_image_mobile', 0, 'absint', 'refresh' );
			$opt( 'aio_login__customization_templates', 'default', array( $this, 'sanitize_login_template_option' ), 'postMessage' );

			$opt( 'aio_login_disable_logo', false, array( $this, 'sanitize_checkbox_bool' ) );
			$opt( 'aio_login_logo', 0, 'absint', 'refresh' );
			$opt( 'aio_login_logo_width', 84, 'absint' );
			$opt( 'aio_login_logo_height', 84, 'absint' );
			$opt( 'aio_login_margin_bottom', 0, 'absint' );
			$opt( 'aio_login_logo_url', '', 'esc_url_raw' );
			$opt( 'aio_login_logo_title', '', 'sanitize_text_field' );

			$opt( 'aio_login_login_page_title', '', 'sanitize_text_field' );
			$opt( 'aio_login_favicon', 0, 'absint', 'refresh' );

			$opt( 'aio_login_forgot_background_color', '', array( $this, 'sanitize_hex_color_allow_empty' ) );
			$opt( 'aio_login_forgot_background_image', 0, 'absint', 'refresh' );

			// New Customizer-safe options (synced into aio_login_elements_settings on save).
			$opt( 'aio_login_el_text_font_family', 'Inherit', array( $this, 'sanitize_login_font_family_option' ) );
			$opt( 'aio_login_el_background_repeat', 'no-repeat', 'sanitize_text_field' );
			$opt( 'aio_login_el_background_position', 'center center', 'sanitize_text_field' );
			$opt( 'aio_login_el_background_size', 'cover', 'sanitize_text_field' );

			$opt( 'aio_login_el_form_transparent', false, array( $this, 'sanitize_checkbox_bool' ) );
			$opt( 'aio_login_el_form_width', '', 'absint' );
			$opt( 'aio_login_el_form_min_height', '', 'absint' );
			$opt( 'aio_login_el_form_border_radius', '', 'absint' );
			$opt( 'aio_login_el_form_shadow', '', 'sanitize_text_field' );
			$opt( 'aio_login_el_form_shadow_opacity', '0.15', array( $this, 'sanitize_float_0_1' ) );
			$opt( 'aio_login_el_form_padding', '', 'sanitize_text_field' );
			$opt( 'aio_login_el_form_border', '', 'sanitize_text_field' );

			$opt( 'aio_login_el_btn_bg_color', '', array( $this, 'sanitize_hex_color_allow_empty' ) );
			$opt( 'aio_login_el_btn_hover_color', '', array( $this, 'sanitize_hex_color_allow_empty' ) );
			$opt( 'aio_login_el_btn_text_color', '', array( $this, 'sanitize_hex_color_allow_empty' ) );
			$opt( 'aio_login_el_btn_border_color', '', array( $this, 'sanitize_hex_color_allow_empty' ) );
			$opt( 'aio_login_el_btn_size', '', 'absint' );
			$opt( 'aio_login_el_btn_padding', '', array( $this, 'sanitize_css_padding_shorthand' ) );
			$opt( 'aio_login_el_btn_padding_tb', '', 'absint' );
			$opt( 'aio_login_el_btn_border_radius', '', 'absint' );
			$opt( 'aio_login_el_btn_shadow', '', 'sanitize_text_field' );
			$opt( 'aio_login_el_btn_shadow_opacity', '0.2', array( $this, 'sanitize_float_0_1' ) );
			$opt( 'aio_login_el_btn_text_size', '', 'absint' );

			$opt( 'aio_login_el_label_color', '', array( $this, 'sanitize_hex_color_allow_empty' ) );
			$opt( 'aio_login_el_remember_label_color', '', array( $this, 'sanitize_hex_color_allow_empty' ) );
			$opt( 'aio_login_el_label_font_size', '', 'absint' );
			$opt( 'aio_login_el_remember_font_size', '', 'absint' );
			$opt( 'aio_login_el_input_margin', '', 'sanitize_text_field' );
			$opt( 'aio_login_el_input_bg_color', '', array( $this, 'sanitize_hex_color_allow_empty' ) );
			$opt( 'aio_login_el_input_text_color', '', array( $this, 'sanitize_hex_color_allow_empty' ) );
			$opt( 'aio_login_el_input_width', '', 'sanitize_text_field' );
			$opt( 'aio_login_el_link_color', '', array( $this, 'sanitize_hex_color_allow_empty' ) );
		}

		/**
		 * Sanitize float between 0 and 1 (string allowed).
		 *
		 * @param mixed $value Value.
		 * @return string
		 */
		public function sanitize_float_0_1( $value ) {
			$v = is_numeric( $value ) ? (float) $value : (float) preg_replace( '/[^0-9.]/', '', (string) $value );
			if ( $v < 0 ) { $v = 0; }
			if ( $v > 1 ) { $v = 1; }
			return (string) $v;
		}

		/**
		 * Themes / template (LoginPress-style first item).
		 *
		 * @param \WP_Customize_Manager $wp_customize Customizer.
		 */
		private function register_themes( $wp_customize ) {
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-customization/class-customize-control-template-grid.php';

			$section = 'aio_login_themes';
			$wp_customize->add_section(
				$section,
				array(
					'title'    => __( 'Templates', 'change-wp-admin-login' ),
					'panel'    => 'aio_login_customizer',
					'priority' => 10,
				)
			);

			$choices_free = array(
				'default'    => __( 'Modern Center', 'change-wp-admin-login' ),
				'template-8' => __( 'Future Tech Split', 'change-wp-admin-login' ),
			);
			$themes_unlocked = $this->plan_allows_customizer_login_themes();
			$choices_all     = apply_filters( 'aio_login_customizer_template_choices', $choices_free );
			// Guideline 5: Free Customizer never lists locked premium template teasers.
			$choices         = $themes_unlocked ? $choices_all : $choices_free;
			$description     = __( 'Click a thumbnail to apply that login design. The preview refreshes after you pick a template.', 'change-wp-admin-login' );

			$wp_customize->add_control(
				new Customize_Control_Template_Grid(
					$wp_customize,
					'aio_login__customization_templates',
					array(
						'section'     => $section,
						'label'       => __( 'Templates', 'change-wp-admin-login' ),
						'description' => $description,
						'choices'     => $choices,
						'has_pro'    => $themes_unlocked,
						'free_slugs' => array( 'default', 'template-8' ),
					)
				)
			);
		}

		/**
		 * Full Google Fonts catalog for the Customizer dropdown (bundled JSON from fonts.google.com metadata).
		 *
		 * @return array<string,string> Value => label.
		 */
		private function get_google_font_family_choices() {
			static $choices = null;
			if ( null !== $choices ) {
				return $choices;
			}
			$choices = array(
				'Inherit' => __( 'Inherit (Theme/WP Default)', 'change-wp-admin-login' ),
			);
			$file = apply_filters(
				'aio_login_google_fonts_families_json',
				AIO_LOGIN__DIR_PATH . 'includes/login-customization/data/google-fonts-families.json'
			);
			if ( ! is_readable( $file ) ) {
				return $choices;
			}
			$decoded = json_decode( (string) file_get_contents( $file ), true );
			if ( ! is_array( $decoded ) ) {
				return $choices;
			}
			foreach ( $decoded as $name ) {
				if ( ! is_string( $name ) || '' === $name ) {
					continue;
				}
				$choices[ $name ] = $name;
			}
			return $choices;
		}

		private function register_typography( $wp_customize ) {
			require_once AIO_LOGIN__DIR_PATH . 'includes/login-customization/class-customize-control-google-fonts-grid.php';

			$fonts_unlocked = $this->plan_allows_customizer_google_fonts();
			$section        = 'aio_login_typography';
			$wp_customize->add_section(
				$section,
				array(
					'title'       => __( 'Google Fonts', 'change-wp-admin-login' ),
					'panel'       => 'aio_login_customizer',
					'priority'    => 80,
					'description' => $fonts_unlocked
						? __( 'Pick any font from the Google Fonts catalog (~1,900 families). Applies across the login form text.', 'change-wp-admin-login' )
						: __( 'The full Google Fonts catalog unlocks on Professional or Business (tier 3+). Inherit stays available on all plans.', 'change-wp-admin-login' ),
				)
			);

			// Setting registered in register_all_settings() with sanitize_login_font_family_option.

			$font_choices = $fonts_unlocked
				? $this->get_google_font_family_choices()
				: array( 'Inherit' => __( 'Inherit (Theme/WP Default)', 'change-wp-admin-login' ) );

			$wp_customize->add_control(
				new Customize_Control_Google_Fonts_Grid(
					$wp_customize,
					'aio_login_el_text_font_family',
					array(
						'section'    => $section,
						'label'      => __( 'Font family', 'change-wp-admin-login' ),
						'choices'    => $font_choices,
						'has_pro'    => $fonts_unlocked,
						'free_value' => 'Inherit',
					)
				)
			);
		}

		private function register_background( $wp_customize ) {
			$section = 'aio_login_background';
			$wp_customize->add_section(
				$section,
				array(
					'title'    => __( 'Background', 'change-wp-admin-login' ),
					'panel'    => 'aio_login_customizer',
					'priority' => 30,
				)
			);

			// Settings registered in register_all_settings() with sanitize callbacks.
			$wp_customize->add_control(
				new \WP_Customize_Color_Control(
					$wp_customize,
					'aio_login_background_color',
					array(
						'section' => $section,
						'label'   => __( 'Background color', 'change-wp-admin-login' ),
					)
				)
			);

			$wp_customize->add_control(
				new \WP_Customize_Media_Control(
					$wp_customize,
					'aio_login_background_image',
					array(
						'section'   => $section,
						'label'     => __( 'Background image', 'change-wp-admin-login' ),
						'mime_type' => 'image',
					)
				)
			);

			$wp_customize->add_control(
				new \WP_Customize_Media_Control(
					$wp_customize,
					'aio_login_background_image_mobile',
					array(
						'section'   => $section,
						'label'     => __( 'Mobile background image', 'change-wp-admin-login' ),
						'mime_type' => 'image',
					)
				)
			);

			$wp_customize->add_control(
				'aio_login_el_background_repeat',
				array(
					'section' => $section,
					'label'   => __( 'Background repeat', 'change-wp-admin-login' ),
					'type'    => 'select',
					'choices' => array(
						'no-repeat' => __( 'No repeat', 'change-wp-admin-login' ),
						'repeat'    => __( 'Tile', 'change-wp-admin-login' ),
						'repeat-x'  => __( 'Repeat X', 'change-wp-admin-login' ),
						'repeat-y'  => __( 'Repeat Y', 'change-wp-admin-login' ),
					),
				)
			);

			$wp_customize->add_control(
				'aio_login_el_background_position',
				array(
					'section' => $section,
					'label'   => __( 'Background position', 'change-wp-admin-login' ),
					'type'    => 'select',
					'choices' => array(
						'left top'      => __( 'Left', 'change-wp-admin-login' ),
						'center center' => __( 'Center', 'change-wp-admin-login' ),
						'right top'     => __( 'Right', 'change-wp-admin-login' ),
					),
				)
			);

			$wp_customize->add_control(
				'aio_login_el_background_size',
				array(
					'section' => $section,
					'label'   => __( 'Background size', 'change-wp-admin-login' ),
					'type'    => 'select',
					'choices' => array(
						'cover'   => __( 'Cover', 'change-wp-admin-login' ),
						'contain' => __( 'Contain', 'change-wp-admin-login' ),
						'initial' => __( 'Initial', 'change-wp-admin-login' ),
					),
				)
			);
		}

		private function register_logo( $wp_customize ) {
			$section = 'aio_login_logo';
			$wp_customize->add_section(
				$section,
				array(
					'title'    => __( 'Logo', 'change-wp-admin-login' ),
					'panel'    => 'aio_login_customizer',
					'priority' => 20,
				)
			);

			$wp_customize->add_control(
				'aio_login_disable_logo',
				array(
					'section' => $section,
					'label'   => __( 'Disable logo', 'change-wp-admin-login' ),
					'type'    => 'checkbox',
				)
			);

			// Settings are registered in register_all_settings(); do not add_setting again here (overwrites sanitize/args).

			$wp_customize->add_control(
				new \WP_Customize_Media_Control(
					$wp_customize,
					'aio_login_logo',
					array(
						'section'   => $section,
						'label'     => __( 'Upload logo image', 'change-wp-admin-login' ),
						'mime_type' => 'image',
					)
				)
			);

			$wp_customize->add_control(
				'aio_login_logo_width',
				array(
					'section'     => $section,
					'label'       => __( 'Logo width (px)', 'change-wp-admin-login' ),
					'type'        => 'number',
					'input_attrs' => array( 'min' => 0, 'step' => 1 ),
				)
			);

			$wp_customize->add_control(
				'aio_login_logo_height',
				array(
					'section'     => $section,
					'label'       => __( 'Logo height (px)', 'change-wp-admin-login' ),
					'type'        => 'number',
					'input_attrs' => array( 'min' => 0, 'step' => 1 ),
				)
			);

			$wp_customize->add_control(
				'aio_login_margin_bottom',
				array(
					'section'     => $section,
					'label'       => __( 'Spacing below logo (px)', 'change-wp-admin-login' ),
					'type'        => 'number',
					'input_attrs' => array( 'min' => 0, 'step' => 1 ),
				)
			);

			$wp_customize->add_control(
				'aio_login_logo_url',
				array(
					'section' => $section,
					'label'   => __( 'Logo URL', 'change-wp-admin-login' ),
					'type'    => 'url',
				)
			);

			$wp_customize->add_control(
				'aio_login_logo_title',
				array(
					'section'     => $section,
					'label'       => __( 'Logo title', 'change-wp-admin-login' ),
					'type'        => 'text',
				)
			);
		}

		private function register_form( $wp_customize ) {
			$section = 'aio_login_form';
			$wp_customize->add_section(
				$section,
				array(
					'title'       => __( 'Customize Login Form', 'change-wp-admin-login' ),
					'panel'       => 'aio_login_customizer',
					'priority'    => 40,
					'description' => __( 'Form box, fields, and labels.', 'change-wp-admin-login' ),
				)
			);

			// Settings registered in register_all_settings() with sanitize callbacks.
			$wp_customize->add_control(
				'aio_login_el_form_transparent',
				array(
					'section' => $section,
					'label'   => __( 'Enable form transparency', 'change-wp-admin-login' ),
					'type'    => 'checkbox',
				)
			);

			$wp_customize->add_control(
				'aio_login_el_form_width',
				array(
					'section'     => $section,
					'label'       => __( 'Form width (px)', 'change-wp-admin-login' ),
					'type'        => 'number',
					'input_attrs' => array( 'min' => 0, 'step' => 1 ),
				)
			);

			$wp_customize->add_control(
				'aio_login_el_form_min_height',
				array(
					'section'     => $section,
					'label'       => __( 'Minimum height (px)', 'change-wp-admin-login' ),
					'type'        => 'number',
					'input_attrs' => array( 'min' => 0, 'step' => 1 ),
				)
			);

			$wp_customize->add_control(
				'aio_login_el_form_border_radius',
				array(
					'section'     => $section,
					'label'       => __( 'Border radius (px)', 'change-wp-admin-login' ),
					'type'        => 'number',
					'input_attrs' => array( 'min' => 0, 'step' => 1 ),
				)
			);

			$wp_customize->add_control(
				'aio_login_el_form_shadow',
				array(
					'section' => $section,
					'label'   => __( 'Shadow (e.g. 0 10px 30px)', 'change-wp-admin-login' ),
					'type'    => 'text',
				)
			);

			$wp_customize->add_control(
				'aio_login_el_form_shadow_opacity',
				array(
					'section'     => $section,
					'label'       => __( 'Shadow opacity (0-1)', 'change-wp-admin-login' ),
					'type'        => 'number',
					'input_attrs' => array( 'min' => 0, 'max' => 1, 'step' => 0.01 ),
				)
			);

			$wp_customize->add_control(
				'aio_login_el_form_padding',
				array(
					'section'     => $section,
					'label'       => __( 'Padding (top right bottom left) with units', 'change-wp-admin-login' ),
					'type'        => 'text',
					'description' => __( 'Example: 20px 24px 20px 24px', 'change-wp-admin-login' ),
				)
			);

			$wp_customize->add_control(
				'aio_login_el_form_border',
				array(
					'section'     => $section,
					'label'       => __( 'Border (CSS shorthand)', 'change-wp-admin-login' ),
					'type'        => 'text',
					'description' => __( 'Example: 2px dotted black', 'change-wp-admin-login' ),
				)
			);

			// Labels & inputs (merged here — LoginPress-style single "Customize Login Form" area).
			$this->color_setting( $wp_customize, $section, 'aio_login_el_label_color', __( 'Label color', 'change-wp-admin-login' ) );
			$this->color_setting( $wp_customize, $section, 'aio_login_el_remember_label_color', __( '"Remember Me" label color', 'change-wp-admin-login' ) );

			$wp_customize->add_control(
				'aio_login_el_label_font_size',
				array(
					'section' => $section,
					'label'   => __( 'Label font size (px)', 'change-wp-admin-login' ),
					'type'    => 'number',
				)
			);

			$wp_customize->add_control(
				'aio_login_el_remember_font_size',
				array(
					'section' => $section,
					'label'   => __( '"Remember Me" font size (px)', 'change-wp-admin-login' ),
					'type'    => 'number',
				)
			);

			$wp_customize->add_control(
				'aio_login_el_input_margin',
				array(
					'section'     => $section,
					'label'       => __( 'Input margin (top right bottom left) with units', 'change-wp-admin-login' ),
					'type'        => 'text',
					'description' => __( 'Example: 0 0 14px 0', 'change-wp-admin-login' ),
				)
			);

			$this->color_setting( $wp_customize, $section, 'aio_login_el_input_bg_color', __( 'Input background color', 'change-wp-admin-login' ) );
			$this->color_setting( $wp_customize, $section, 'aio_login_el_input_text_color', __( 'Input text color', 'change-wp-admin-login' ) );

			$wp_customize->add_control(
				'aio_login_el_input_width',
				array(
					'section'     => $section,
					'label'       => __( 'Field width (CSS)', 'change-wp-admin-login' ),
					'type'        => 'text',
					'description' => __( 'Example: 100% or 320px', 'change-wp-admin-login' ),
				)
			);
		}

		private function register_button( $wp_customize ) {
			$section = 'aio_login_button';
			$wp_customize->add_section(
				$section,
				array(
					'title'    => __( 'Button Customization', 'change-wp-admin-login' ),
					'panel'    => 'aio_login_customizer',
					'priority' => 60,
				)
			);

			$this->color_setting( $wp_customize, $section, 'aio_login_el_btn_bg_color', __( 'Button color', 'change-wp-admin-login' ) );
			$this->color_setting( $wp_customize, $section, 'aio_login_el_btn_hover_color', __( 'Hover color', 'change-wp-admin-login' ) );
			$this->color_setting( $wp_customize, $section, 'aio_login_el_btn_text_color', __( 'Text color', 'change-wp-admin-login' ) );
			$this->color_setting( $wp_customize, $section, 'aio_login_el_btn_border_color', __( 'Border color', 'change-wp-admin-login' ) );

			$wp_customize->add_control(
				'aio_login_el_btn_size',
				array(
					'section' => $section,
					'label'   => __( 'Button size (height px)', 'change-wp-admin-login' ),
					'type'    => 'number',
				)
			);

			$wp_customize->add_control(
				'aio_login_el_btn_padding',
				array(
					'section'     => $section,
					'label'       => __( 'Button padding', 'change-wp-admin-login' ),
					'type'        => 'text',
					'description' => __( 'CSS padding shorthand, e.g. 16px (all sides), 12px 24px (top/bottom left/right), or four values for top, right, bottom, left.', 'change-wp-admin-login' ),
				)
			);

			$wp_customize->add_control(
				'aio_login_el_btn_border_radius',
				array(
					'section' => $section,
					'label'   => __( 'Border radius (px)', 'change-wp-admin-login' ),
					'type'    => 'number',
				)
			);

			$wp_customize->add_control(
				'aio_login_el_btn_shadow',
				array(
					'section' => $section,
					'label'   => __( 'Shadow (e.g. 0 8px 20px)', 'change-wp-admin-login' ),
					'type'    => 'text',
				)
			);

			$wp_customize->add_control(
				'aio_login_el_btn_shadow_opacity',
				array(
					'section'     => $section,
					'label'       => __( 'Shadow opacity (0-1)', 'change-wp-admin-login' ),
					'type'        => 'number',
					'input_attrs' => array( 'min' => 0, 'max' => 1, 'step' => 0.01 ),
				)
			);

			$wp_customize->add_control(
				'aio_login_el_btn_text_size',
				array(
					'section' => $section,
					'label'   => __( 'Text size (px)', 'change-wp-admin-login' ),
					'type'    => 'number',
				)
			);
		}

		/**
		 * Sync Customizer-safe options into legacy aio_login_elements_settings array for output/back-compat.
		 *
		 * @param \WP_Customize_Manager $manager Manager.
		 */
		public function customize_save_after( $manager ) {
			$existing = get_option( 'aio_login_elements_settings', array() );
			if ( ! is_array( $existing ) ) {
				$existing = array();
			}

			$map = array(
				'text_font_family'      => 'aio_login_el_text_font_family',
				'background_repeat'     => 'aio_login_el_background_repeat',
				'background_position'   => 'aio_login_el_background_position',
				'background_size'       => 'aio_login_el_background_size',
				'form_transparent'      => 'aio_login_el_form_transparent',
				'form_width'            => 'aio_login_el_form_width',
				'form_min_height'       => 'aio_login_el_form_min_height',
				'form_border_radius'    => 'aio_login_el_form_border_radius',
				'form_shadow'           => 'aio_login_el_form_shadow',
				'form_shadow_opacity'   => 'aio_login_el_form_shadow_opacity',
				'form_padding'          => 'aio_login_el_form_padding',
				'form_border'           => 'aio_login_el_form_border',
				'btn_bg_color'          => 'aio_login_el_btn_bg_color',
				'btn_hover_color'       => 'aio_login_el_btn_hover_color',
				'btn_text_color'        => 'aio_login_el_btn_text_color',
				'btn_border_color'      => 'aio_login_el_btn_border_color',
				'btn_size'              => 'aio_login_el_btn_size',
				'btn_padding'           => 'aio_login_el_btn_padding',
				'btn_padding_tb'        => 'aio_login_el_btn_padding_tb',
				'btn_border_radius'     => 'aio_login_el_btn_border_radius',
				'btn_shadow'            => 'aio_login_el_btn_shadow',
				'btn_shadow_opacity'    => 'aio_login_el_btn_shadow_opacity',
				'btn_text_size'         => 'aio_login_el_btn_text_size',
				'label_color'           => 'aio_login_el_label_color',
				'remember_label_color'  => 'aio_login_el_remember_label_color',
				'label_font_size'       => 'aio_login_el_label_font_size',
				'remember_font_size'    => 'aio_login_el_remember_font_size',
				'input_margin'          => 'aio_login_el_input_margin',
				'input_bg_color'        => 'aio_login_el_input_bg_color',
				'input_text_color'      => 'aio_login_el_input_text_color',
				'input_width'           => 'aio_login_el_input_width',
				'link_color'            => 'aio_login_el_link_color',
			);

			foreach ( $map as $legacy_key => $opt_key ) {
				$val = get_option( $opt_key, null );
				if ( null !== $val ) {
					$existing[ $legacy_key ] = $val;
				}
			}

			update_option( 'aio_login_elements_settings', $existing );
		}

		private function register_form_footer( $wp_customize ) {
			$section = 'aio_login_form_footer';
			$wp_customize->add_section(
				$section,
				array(
					'title'       => __( 'Form Footer', 'change-wp-admin-login' ),
					'panel'       => 'aio_login_customizer',
					'priority'    => 70,
					'description' => __( 'Footer links (Lost password, Back to site) and browser tab title.', 'change-wp-admin-login' ),
				)
			);

			$wp_customize->add_control(
				new \WP_Customize_Color_Control(
					$wp_customize,
					'aio_login_el_link_color',
					array(
						'section' => $section,
						'label'   => __( 'Footer link color', 'change-wp-admin-login' ),
					)
				)
			);

			$wp_customize->add_control(
				'aio_login_login_page_title',
				array(
					'section' => $section,
					'label'   => __( 'Login page title', 'change-wp-admin-login' ),
					'type'    => 'text',
				)
			);

			$wp_customize->add_control(
				new \WP_Customize_Media_Control(
					$wp_customize,
					'aio_login_favicon',
					array(
						'section'   => $section,
						'label'     => __( 'Custom favicon', 'change-wp-admin-login' ),
						'mime_type' => 'image',
					)
				)
			);
		}

		private function register_forgot( $wp_customize ) {
			$section = 'aio_login_forgot';
			$wp_customize->add_section(
				$section,
				array(
					'title'    => __( 'Customize Forgot Password', 'change-wp-admin-login' ),
					'panel'    => 'aio_login_customizer',
					'priority' => 50,
				)
			);

			$wp_customize->add_control(
				new \WP_Customize_Color_Control(
					$wp_customize,
					'aio_login_forgot_background_color',
					array(
						'section' => $section,
						'label'   => __( 'Forgot form background color', 'change-wp-admin-login' ),
					)
				)
			);

			$wp_customize->add_control(
				new \WP_Customize_Media_Control(
					$wp_customize,
					'aio_login_forgot_background_image',
					array(
						'section'   => $section,
						'label'     => __( 'Forgot form background image', 'change-wp-admin-login' ),
						'mime_type' => 'image',
					)
				)
			);
		}

		private function color_setting( $wp_customize, $section, $setting_id, $label ) {
			// Settings (incl. sanitize_callback) are registered in register_all_settings().
			if ( ! $wp_customize->get_setting( $setting_id ) ) {
				$wp_customize->add_setting(
					$setting_id,
					array(
						'type'              => 'option',
						'transport'         => 'postMessage',
						'default'           => '',
						'sanitize_callback' => array( $this, 'sanitize_hex_color_allow_empty' ),
					)
				);
			}
			$wp_customize->add_control(
				new \WP_Customize_Color_Control(
					$wp_customize,
					$setting_id,
					array(
						'section' => $section,
						'label'   => $label,
					)
				)
			);
		}

		/**
		 * CSS padding shorthand for buttons (blocks obvious injection).
		 *
		 * @param mixed $value Value.
		 * @return string
		 */
		public function sanitize_css_padding_shorthand( $value ) {
			$value = is_string( $value ) ? trim( $value ) : '';
			if ( '' === $value ) {
				return '';
			}
			if ( preg_match( '/[;<>{}()\\\\]|url\s*:/i', $value ) ) {
				return '';
			}
			return sanitize_text_field( $value );
		}

		/**
		 * Allow empty color, otherwise sanitize hex.
		 *
		 * @param mixed $value Value.
		 * @return string
		 */
		public function sanitize_hex_color_allow_empty( $value ) {
			$v = is_string( $value ) ? trim( $value ) : '';
			if ( '' === $v ) {
				return '';
			}
			$san = sanitize_hex_color( $v );
			return $san ? $san : '';
		}

		/**
		 * Sanitize Customizer checkbox / boolean options.
		 *
		 * @param mixed $value Raw value.
		 * @return bool
		 */
		public function sanitize_checkbox_bool( $value ) {
			return ( true === $value || 1 === $value || '1' === $value || 'true' === $value || 'on' === $value );
		}

		/**
		 * Preview JS for live updates.
		 */
		public function customize_preview_init() {
			if ( ! \AIO_Login\Login_Customization\aio_login_may_enqueue_login_customizer_preview_scripts() ) {
				return;
			}
			wp_enqueue_script( 'customize-preview' );
			wp_enqueue_style( 'customize-preview' );
			// Premium template preview map lives in Pro; Free JS only knows default + template-8.
			$preview_src = AIO_LOGIN__DIR_URL . 'assets/js/login-customizer-preview.js';
			$preview_ver = defined( 'AIO_LOGIN__VERSION' ) ? AIO_LOGIN__VERSION : time();
			if ( defined( 'AIO_LOGIN_PRO__DIR_URL' ) && defined( 'AIO_LOGIN_PRO__VERSION' ) ) {
				$preview_src = AIO_LOGIN_PRO__DIR_URL . 'assets/js/login-customizer-preview.js';
				$preview_ver = AIO_LOGIN_PRO__VERSION;
			}
			wp_enqueue_script(
				'aio-login-pro-login-customizer-preview',
				$preview_src,
				array( 'jquery', 'customize-preview' ),
				$preview_ver,
				true
			);
			\AIO_Login\Login_Customization\aio_login_localize_login_customizer_preview_script( 'aio-login-pro-login-customizer-preview' );
		}

		/**
		 * Output _wpCustomizeSettings on wp-login.php in customizer preview iframe.
		 */
		public function output_customizer_preview_settings() {
			global $wp_customize;
			if ( ! empty( $wp_customize ) && method_exists( $wp_customize, 'customize_preview_settings' ) ) {
				$wp_customize->customize_preview_settings();
			}
		}

		/**
		 * Controls-frame JS (listen for preview "focus section" messages).
		 */
		public function customize_controls_enqueue_scripts() {
			wp_enqueue_media();
			wp_enqueue_style(
				'aio-login-pro-customizer-template-grid',
				AIO_LOGIN__DIR_URL . 'assets/css/login-customizer-template-grid.css',
				array(),
				time()
			);
			wp_enqueue_script(
				'aio-login-pro-login-customizer-controls',
				AIO_LOGIN__DIR_URL . 'assets/js/login-customizer-controls.js',
				array( 'jquery', 'customize-controls' ),
				time(),
				true
			);
			$appsumo_popup     = function_exists( 'aiologin_pro_is_custom_license_runtime' ) && aiologin_pro_is_custom_license_runtime();
			$pro_plugin_active = \AIO_Login\AIO_Login::has_pro();
			// Read-only Customizer launch flags (GET). No state change — nonce not applicable.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			$launch_customizer = isset( $_GET['aio_login_customizer'] )
				? sanitize_text_field( wp_unslash( $_GET['aio_login_customizer'] ) )
				: '';
			$launch_preview    = isset( $_GET['aio_login_customizer_preview'] )
				? sanitize_text_field( wp_unslash( $_GET['aio_login_customizer_preview'] ) )
				: '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$force_login_preview = ( '' !== $launch_customizer ) || ( '' !== $launch_preview );
			wp_localize_script(
				'aio-login-pro-login-customizer-controls',
				'aioLoginCustomizer',
				array(
					'hasPro'            => $pro_plugin_active,
					'upgradePopupVariant' => $appsumo_popup ? 'appsumo' : 'freemius',
					'freeTemplateSlugs' => array( 'default', 'template-8' ),
					// Match admin app / aio-login-pro-popup.vue (Freemius vs AppSumo build).
					'popupTitle'       => $appsumo_popup
						? __( 'Unlock premium features with the AppSumo deal', 'change-wp-admin-login' )
						: __( 'To access more features and options', 'change-wp-admin-login' ),
					'popupButtonLabel' => $appsumo_popup
						? __( 'View AppSumo Plans', 'change-wp-admin-login' )
						: ( $pro_plugin_active
							? __( 'Get Business Plan Now', 'change-wp-admin-login' )
							: __( 'Get AIO Login Pro', 'change-wp-admin-login' ) ),
					'popupUrl'         => $appsumo_popup
						? 'https://appsumo.com/products/aiologin/?p=1#pricePlans'
						: 'https://aiologin.com/pricing/?utm_source=plugin&utm_medium=pro_pop_up&utm_campaign=plugin',
					'pricingUrl'        => 'https://aiologin.com/pricing/?utm_source=plugin&utm_medium=pro_pop_up&utm_campaign=plugin',
					'assetsUrl'         => AIO_LOGIN__DIR_URL . 'assets/',
					'loginPhpUrl'       => site_url( 'wp-login.php' ),
					'homeUrl'           => home_url( '/' ),
					// Only AIO Login → Customize launch (or explicit preview flag) starts on wp-login.php.
					'forceLoginPreview' => $force_login_preview,
					'resetDefaults' => $this->get_customizer_default_values(),
					'resetConfirm'  => __( 'Reset all AIO Login Customize options to their default values? Click Publish to save.', 'change-wp-admin-login' ),
				)
			);

			// When launched from AIO Login → Customize, expand the AIO Login Customize panel only (not Themes).
			// Session cookie is short-lived and cleared when the AIO panel is collapsed (see controls JS).
			if ( '' !== $launch_customizer ) {
				wp_add_inline_script(
					'customize-controls',
					'document.cookie="aio_login_customizing=1; path=/; max-age=3600; SameSite=Lax";(function(api){api.bind("ready",function(){if(api.panel("aio_login_customizer")){api.panel("aio_login_customizer",function(p){p.expand();});}});})(wp.customize);',
					'after'
				);
			}
		}

		/**
		 * Singleton.
		 *
		 * @return self
		 */
		public static function get_instance() {
			static $instance = null;
			if ( null === $instance ) {
				$instance = new self();
			}
			return $instance;
		}
	}
}

