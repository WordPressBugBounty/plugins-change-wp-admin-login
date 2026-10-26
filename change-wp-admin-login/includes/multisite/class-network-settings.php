<?php
/**
 * Multisite network settings and policy resolution.
 *
 * @package AIO Login
 */

namespace AIO_Login\Multisite;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Multisite\\Network_Settings' ) ) {
	/**
	 * Class Network_Settings
	 *
	 * Resolves feature options based on network policies:
	 * - network_managed
	 * - site_override_allowed
	 * - individual_site
	 */
	class Network_Settings {

		const POLICY_NETWORK_MANAGED      = 'network_managed';
		const POLICY_SITE_OVERRIDE        = 'site_override_allowed';
		const POLICY_INDIVIDUAL_SITE      = 'individual_site';

		const SITE_OPTION_POLICIES        = 'aio_login_network_policies';
		const SITE_OPTION_SETTINGS        = 'aio_login_network_settings';
		const SITE_OPTION_NETWORK_LEVEL   = 'aio_login_network_level_enabled';
		const OPTION_SITE_OVERRIDES       = 'aio_login_site_feature_overrides';

		/**
		 * Skip pre_option / pre_update_option recursion while writing raw site options.
		 *
		 * @var bool
		 */
		private static $bypass_option_hooks = false;

		/**
		 * Whether option hooks were registered.
		 *
		 * @var bool
		 */
		private static $option_hooks_bootstrapped = false;

		/**
		 * Register get/update option filters for all registry options.
		 *
		 * Ensures Network Managed / Site Override policies apply even when feature
		 * code calls WordPress get_option() / update_option() directly.
		 */
		public static function bootstrap_option_hooks() {
			if ( self::$option_hooks_bootstrapped || ! is_multisite() ) {
				return;
			}
			self::$option_hooks_bootstrapped = true;

			add_action( 'init', array( __CLASS__, 'register_option_hooks' ), 5 );
		}

		/**
		 * Attach filters once network context is knowable.
		 */
		public static function register_option_hooks() {
			if ( ! self::is_network_context() ) {
				return;
			}

			foreach ( self::get_registered_option_names() as $option ) {
				add_filter( "pre_option_{$option}", array( __CLASS__, 'filter_pre_option' ), 10, 3 );
				add_filter( "pre_update_option_{$option}", array( __CLASS__, 'filter_pre_update_option' ), 10, 3 );
			}
		}

		/**
		 * Flat list of option names from the feature registry.
		 *
		 * @return string[]
		 */
		public static function get_registered_option_names() {
			static $names = null;
			if ( null === $names ) {
				$names = array();
				foreach ( self::get_feature_registry() as $config ) {
					if ( empty( $config['options'] ) || ! is_array( $config['options'] ) ) {
						continue;
					}
					foreach ( $config['options'] as $option ) {
						$names[ $option ] = $option;
					}
				}
				$names = array_values( $names );
			}
			return $names;
		}

		/**
		 * Whether an option stays site-local under Site Override Allowed
		 * even when local override is off (enable/disable + labels only).
		 * Credential options still inherit from the network until override.
		 *
		 * @param string $option  Option name.
		 * @param string $feature Feature slug.
		 * @return bool
		 */
		public static function is_site_local_under_override_policy( $option, $feature ) {
			if ( 0 !== strpos( (string) $feature, 'social_' ) ) {
				return false;
			}

			if ( preg_match( '/_enabled$/', $option ) ) {
				return true;
			}

			if ( preg_match( '/_(login_label|logout_label)$/', $option ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Resolve network-managed option values for get_option().
		 *
		 * @param mixed  $pre     Existing short-circuit value.
		 * @param string $option  Option name.
		 * @param mixed  $default Default.
		 * @return mixed
		 */
		public static function filter_pre_option( $pre, $option, $default = false ) {
			if ( self::$bypass_option_hooks || false !== $pre ) {
				return $pre;
			}

			$feature = self::get_feature_for_option( $option );
			if ( null === $feature ) {
				return $pre;
			}

			$policy = self::get_policy( $feature );
			if ( self::POLICY_INDIVIDUAL_SITE === $policy ) {
				return $pre;
			}
			if ( self::POLICY_SITE_OVERRIDE === $policy && self::has_site_override( $feature ) ) {
				return $pre;
			}
			// Site Override Allowed without override: enable/labels stay local.
			if ( self::POLICY_SITE_OVERRIDE === $policy && self::is_site_local_under_override_policy( $option, $feature ) ) {
				return $pre;
			}

			$network = self::get_network_settings();
			if ( array_key_exists( $option, $network ) ) {
				return $network[ $option ];
			}

			return $pre;
		}

		/**
		 * Enforce network policies on update_option().
		 *
		 * @param mixed  $value     New value.
		 * @param mixed  $old_value Old value.
		 * @param string $option    Option name.
		 * @return mixed
		 */
		public static function filter_pre_update_option( $value, $old_value, $option ) {
			if ( self::$bypass_option_hooks || ! self::is_network_context() ) {
				return $value;
			}

			$feature = self::get_feature_for_option( $option );
			if ( null === $feature ) {
				return $value;
			}

			$policy = self::get_policy( $feature );

			if ( self::POLICY_INDIVIDUAL_SITE === $policy ) {
				return $value;
			}

			// If current site has a local override enabled,
			// write strictly to this site's local wp_options table. Do NOT touch network settings.
			if ( self::POLICY_SITE_OVERRIDE === $policy && self::has_site_override( $feature ) ) {
				return $value;
			}

			// Network defaults: Network Admin (or flagged REST) writes to network settings bag and pushes to non-overridden sites.
			if ( self::can_edit_network_defaults() ) {
				self::update_network_settings( array( $option => $value ) );
				self::$bypass_option_hooks = true;
				self::push_option_to_all_sites( $option, $value, $feature );
				self::$bypass_option_hooks = false;
				return $value;
			}

			// Site screens (without local override): Network Managed or Inherited is locked.
			return $old_value;
		}

		/**
		 * Feature definitions keyed by feature slug.
		 *
		 * Categories / features follow AIOL-542.
		 * Labels use literal gettext strings (Plugin Check / WP.org i18n).
		 *
		 * @return array
		 */
		public static function get_feature_registry() {
			$registry = array(
				// Authentication.
				'two_factor_authentication' => array(
					'label'    => __( 'Two-Factor Authentication (2FA)', 'change-wp-admin-login' ),
					'category' => 'authentication',
					'is_pro'   => false,
					'options'  => array(
						'aio_login_pro__two_factor_auth_enable',
						'aio_login_pro__2fa_email_otp_enable',
						'aio_login_pro__2fa_remember_device_enable',
						'aio_login_pro__2fa_email_settings',
						'aio_login_pro__2fa_totp_max_otp_attempts',
						'aio_login_pro__2fa_totp_block_duration_minutes',
						'aio_login_pro__2fa_trust_duration_days',
						'aio_login_pro__2fa_policy_enforce_all',
						'aio_login_pro__2fa_policy_enforce_specific',
						'aio_login_pro__2fa_policy_roles',
						'aio_login_pro__2fa_advanced',
					),
				),
				'disable_common_usernames'  => array(
					'label'    => __( 'Disable Common Usernames', 'change-wp-admin-login' ),
					'category' => 'authentication',
					'is_pro'   => false,
					'options'  => array(
						'aio_login_pro__disable_common_usernames_enable',
						'aio_login_pro__disable_common_usernames_custom_usernames',
					),
				),
				'limit_login_attempts'      => array(
					'label'    => __( 'Limit Login Attempts', 'change-wp-admin-login' ),
					'category' => 'authentication',
					'is_pro'   => true,
					'options'  => array(
						'aio_login_limit_attempts_enable',
						'aio_login_limit_attempts_maximum_attempts',
						'aio_login_limit_attempts_timeout',
						'aio_login_limit_attempts_lockout_message',
					),
				),

				// Passwordless Login.
				'email_otp'                 => array(
					'label'    => __( 'Email OTP', 'change-wp-admin-login' ),
					'category' => 'passwordless_login',
					'is_pro'   => false,
					'options'  => array(
						'aio_login_otp_email_enable',
						'aio_login_otp_email_length',
						'aio_login_otp_email_expiration',
						'aio_login_otp_email_resend_timer',
						'aio_login_otp_email_max_retries',
						'aio_login_otp_email_block_duration',
						'aio_login_otp_email_skip_2fa',
					),
				),
				'sms_otp'                   => array(
					'label'    => __( 'SMS OTP', 'change-wp-admin-login' ),
					'category' => 'passwordless_login',
					'is_pro'   => true,
					'options'  => array(
						'aio_login_otp_sms_enable',
						'aio_login_otp_sms_length',
						'aio_login_otp_sms_expiration',
						'aio_login_otp_sms_resend_timer',
						'aio_login_otp_sms_max_retries',
						'aio_login_otp_sms_block_duration',
						'aio_login_otp_sms_skip_2fa',
						'aio_login_otp_twilio_account_sid',
						'aio_login_otp_twilio_auth_token',
						'aio_login_otp_twilio_sender_number',
						'aio_login_otp_sms_default_country',
						'aio_login_otp_sms_default_country_iso',
						'aio_login_otp_sms_allowed_countries',
					),
				),
				'magic_login_link'          => array(
					'label'    => __( 'Magic Login Link', 'change-wp-admin-login' ),
					'category' => 'passwordless_login',
					'is_pro'   => true,
					'options'  => array(
						'aio_login_magic_link_enable',
						'aio_login_magic_link_validity_value',
						'aio_login_magic_link_validity_unit',
						'aio_login_magic_link_max_requests',
						'aio_login_magic_link_skip_2fa',
					),
				),

				// Spam Protection (CAPTCHA).
				'captcha'                   => array(
					'label'    => __( 'CAPTCHA', 'change-wp-admin-login' ),
					'category' => 'spam_protection',
					'is_pro'   => false,
					'options'  => array(
						'aio_login_google_recaptcha_enable',
						'aio_login_google_recaptcha_version',
						'aio_login_google_recaptcha_v2_site_key',
						'aio_login_google_recaptcha_v2_secret_key',
						'aio_login_google_recaptcha_v2_theme',
						'aio_login_google_recaptcha_v3_site_key',
						'aio_login_google_recaptcha_v3_secret_key',
						'aio_login_google_recaptcha_v3_threshold',
						'aio_login_hcaptcha_enable',
						'aio_login_hcaptcha_site_key',
						'aio_login_hcaptcha_secret_key',
						'aio_login_hcaptcha_theme',
						'aio_login_hcaptcha_size',
						'aio_login_hcaptcha_language',
						'aio_login_turnstile_enable',
						'aio_login_turnstile_site_key',
						'aio_login_turnstile_secret_key',
						'aio_login_turnstile_theme',
						'aio_login_turnstile_size',
						'aio_login_turnstile_language',
					),
				),

				// Social Login.
				'social_google'             => array(
					'label'    => __( 'Google', 'change-wp-admin-login' ),
					'category' => 'social_login',
					'is_pro'   => true,
					'options'  => array(
						'aio_google_enabled',
						'aio_google_client_id',
						'aio_google_client_secret',
					),
				),
				'social_microsoft'          => array(
					'label'    => __( 'Microsoft', 'change-wp-admin-login' ),
					'category' => 'social_login',
					'is_pro'   => true,
					'options'  => array(
						'aio_microsoft_enabled',
						'aio_microsoft_client_id',
						'aio_microsoft_client_secret',
					),
				),
				'social_line'               => array(
					'label'    => __( 'LINE', 'change-wp-admin-login' ),
					'category' => 'social_login',
					'is_pro'   => true,
					'options'  => array(
						'aio_line_enabled',
						'aio_line_client_id',
						'aio_line_client_secret',
					),
				),
				'social_facebook'           => array(
					'label'    => __( 'Facebook', 'change-wp-admin-login' ),
					'category' => 'social_login',
					'is_pro'   => true,
					'options'  => array(
						'aio_facebook_enabled',
						'aio_facebook_client_id',
						'aio_facebook_client_secret',
					),
				),
				'social_discord'            => array(
					'label'    => __( 'Discord', 'change-wp-admin-login' ),
					'category' => 'social_login',
					'is_pro'   => true,
					'options'  => array(
						'aio_discord_enabled',
						'aio_discord_client_id',
						'aio_discord_client_secret',
					),
				),
				'social_github'             => array(
					'label'    => __( 'GitHub', 'change-wp-admin-login' ),
					'category' => 'social_login',
					'is_pro'   => true,
					'options'  => array(
						'aio_github_enabled',
						'aio_github_client_id',
						'aio_github_client_secret',
					),
				),
				'social_apple'              => array(
					'label'    => __( 'Apple', 'change-wp-admin-login' ),
					'category' => 'social_login',
					'is_pro'   => true,
					'options'  => array(
						'aio_apple_enabled',
						'aio_apple_service_id',
						'aio_apple_client_id',
						'aio_apple_key_id',
						'aio_apple_team_id',
						'aio_apple_private_key',
					),
				),
			);

			/**
			 * Filter Multisite feature registry.
			 *
			 * @param array $registry Feature registry.
			 */
			return apply_filters( 'aio_login_network_feature_registry', $registry );
		}

		/**
		 * Whether a registry feature is available on this install (Free vs Pro).
		 *
		 * @param array<string,mixed> $config Feature registry entry.
		 * @return bool
		 */
		public static function is_feature_available_on_install( $config ) {
			if ( empty( $config['is_pro'] ) ) {
				return true;
			}

			return class_exists( '\AIO_Login\AIO_Login' ) && \AIO_Login\AIO_Login::has_pro();
		}

		/**
		 * Categories with Pro-only features removed on Free (AIOL-766).
		 *
		 * @param array<string,array<string,mixed>> $categories Category definitions.
		 * @param array<string,array<string,mixed>> $registry   Feature registry.
		 * @return array<string,array<string,mixed>>
		 */
		public static function filter_categories_for_install( $categories, $registry ) {
			$filtered = array();

			foreach ( $categories as $slug => $cat ) {
				$features = array();
				if ( ! empty( $cat['features'] ) && is_array( $cat['features'] ) ) {
					foreach ( $cat['features'] as $feature ) {
						if ( ! isset( $registry[ $feature ] ) ) {
							continue;
						}
						if ( self::is_feature_available_on_install( $registry[ $feature ] ) ) {
							$features[] = $feature;
						}
					}
				}
				if ( empty( $features ) ) {
					continue;
				}
				$cat['features'] = $features;
				$filtered[ $slug ] = $cat;
			}

			return $filtered;
		}

		/**
		 * Category definitions for Network Settings UI (AIOL-542).
		 *
		 * @return array
		 */
		public static function get_categories() {
			return array(
				'authentication'     => array(
					'label'    => __( 'Authentication', 'change-wp-admin-login' ),
					'features' => array(
						'two_factor_authentication',
						'disable_common_usernames',
						'limit_login_attempts',
					),
				),
				'passwordless_login' => array(
					'label'    => __( 'Passwordless Login', 'change-wp-admin-login' ),
					'features' => array(
						'email_otp',
						'sms_otp',
						'magic_login_link',
					),
				),
				'spam_protection'    => array(
					'label'    => __( 'Spam Protection', 'change-wp-admin-login' ),
					'features' => array(
						'captcha',
					),
				),
				'social_login'       => array(
					'label'    => __( 'Social Login', 'change-wp-admin-login' ),
					'features' => array(
						'social_google',
						'social_microsoft',
						'social_line',
						'social_facebook',
						'social_discord',
						'social_github',
						'social_apple',
					),
				),
			);
		}

		/**
		 * Policy dropdown options.
		 *
		 * @return array
		 */
		public static function get_policy_choices() {
			return array(
				self::POLICY_NETWORK_MANAGED => __( 'Network Managed', 'change-wp-admin-login' ),
				self::POLICY_SITE_OVERRIDE   => __( 'Site Override Allowed', 'change-wp-admin-login' ),
				self::POLICY_INDIVIDUAL_SITE => __( 'Individual Settings', 'change-wp-admin-login' ),
			);
		}

		/**
		 * Whether Multisite network mode is active for this plugin.
		 *
		 * @return bool
		 */
		public static function is_network_context() {
			if ( ! is_multisite() ) {
				return false;
			}

			return self::is_network_level_enabled();
		}

		/**
		 * Whether the current request is from Network Admin (UI or REST/AJAX).
		 *
		 * Main site site-admin is treated like any other site — only Network Admin
		 * may edit network defaults.
		 *
		 * @return bool
		 */
		public static function is_network_admin_request() {
			if ( ! is_multisite() ) {
				return false;
			}

			if ( is_network_admin() ) {
				return true;
			}

			// Subsites (blog_id > 1) can NEVER perform network admin requests or edit network defaults.
			if ( ! is_main_site() ) {
				return false;
			}

			// REST/AJAX from Network Admin on Main Site.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_REQUEST['aio_login_network_admin'] ) && '1' === (string) wp_unslash( $_REQUEST['aio_login_network_admin'] ) ) {
				return current_user_can( 'manage_network_options' );
			}

			return false;
		}

		/**
		 * Whether the current user may edit network-level defaults.
		 *
		 * @return bool
		 */
		public static function can_edit_network_defaults() {
			return current_user_can( 'manage_network_options' ) && self::is_network_admin_request();
		}

		/**
		 * Whether current request is Multisite (site or network activated).
		 *
		 * @return bool
		 */
		public static function is_multisite_install() {
			return is_multisite();
		}

		/**
		 * Master switch: Manage AIO Login at Network Level.
		 *
		 * @return bool
		 */
		public static function is_network_level_enabled() {
			$value = get_site_option( self::SITE_OPTION_NETWORK_LEVEL, 'on' );
			return 'on' === $value || true === $value || 1 === (int) $value;
		}

		/**
		 * Set master network-level switch.
		 *
		 * @param bool $enabled Enabled.
		 * @return bool
		 */
		public static function set_network_level_enabled( $enabled ) {
			return update_site_option( self::SITE_OPTION_NETWORK_LEVEL, $enabled ? 'on' : 'off' );
		}

		/**
		 * Overwrite existing site plugin options with network defaults.
		 *
		 * Seeds network defaults from the main site (force refresh), then pushes
		 * those values to every site.
		 *
		 * @return int Sites updated.
		 */
		public static function apply_defaults_to_existing_sites() {
			self::seed_network_settings_from_main_site( true );

			$network = self::get_network_settings();
			if ( empty( $network ) || ! is_array( $network ) ) {
				return 0;
			}

			$updated = 0;
			foreach ( self::get_sites_list() as $site ) {
				switch_to_blog( (int) $site['id'] );
				self::$bypass_option_hooks = true;
				foreach ( $network as $option => $value ) {
					update_option( $option, $value );
				}
				self::$bypass_option_hooks = false;
				++$updated;
				restore_current_blog();
			}

			return $updated;
		}

		/**
		 * Copy registered feature options from the main site into the network settings bag.
		 *
		 * @param bool $force When true, overwrite existing network values from main site.
		 * @return array Updated network settings.
		 */
		public static function seed_network_settings_from_main_site( $force = false ) {
			$current = self::get_network_settings();
			$main_id = function_exists( 'get_main_site_id' ) ? get_main_site_id() : 1;

			switch_to_blog( (int) $main_id );
			self::$bypass_option_hooks = true;
			foreach ( self::get_feature_registry() as $config ) {
				if ( empty( $config['options'] ) || ! is_array( $config['options'] ) ) {
					continue;
				}
				foreach ( $config['options'] as $option ) {
					if ( $force || ! array_key_exists( $option, $current ) ) {
						$current[ $option ] = get_option( $option, '' );
					}
				}
			}
			self::$bypass_option_hooks = false;
			restore_current_blog();

			update_site_option( self::SITE_OPTION_SETTINGS, $current );

			return $current;
		}

		/**
		 * Refresh network bag from main site for Network Managed / Site Override features,
		 * then push those option values to every site (unless a site override is active).
		 *
		 * @return int Sites updated.
		 */
		public static function sync_managed_features_to_all_sites() {
			$current = self::get_network_settings();
			$main_id = function_exists( 'get_main_site_id' ) ? get_main_site_id() : 1;
			$registry = self::get_feature_registry();

			switch_to_blog( (int) $main_id );
			self::$bypass_option_hooks = true;
			foreach ( $registry as $feature => $config ) {
				$policy = self::get_policy( $feature );
				if ( self::POLICY_INDIVIDUAL_SITE === $policy ) {
					continue;
				}
				if ( empty( $config['options'] ) || ! is_array( $config['options'] ) ) {
					continue;
				}
				foreach ( $config['options'] as $option ) {
					$current[ $option ] = get_option( $option, '' );
				}
			}
			self::$bypass_option_hooks = false;
			restore_current_blog();

			update_site_option( self::SITE_OPTION_SETTINGS, $current );

			$updated = 0;
			foreach ( self::get_sites_list() as $site ) {
				switch_to_blog( (int) $site['id'] );
				self::$bypass_option_hooks = true;
				foreach ( $registry as $feature => $config ) {
					$policy = self::get_policy( $feature );
					if ( self::POLICY_INDIVIDUAL_SITE === $policy ) {
						continue;
					}
					if ( self::POLICY_SITE_OVERRIDE === $policy && self::has_site_override( $feature ) ) {
						continue;
					}
					if ( empty( $config['options'] ) || ! is_array( $config['options'] ) ) {
						continue;
					}
					foreach ( $config['options'] as $option ) {
						if ( array_key_exists( $option, $current ) ) {
							update_option( $option, $current[ $option ] );
						}
					}
				}
				self::$bypass_option_hooks = false;
				++$updated;
				restore_current_blog();
			}

			return $updated;
		}

		/**
		 * Get all network policies.
		 *
		 * @return array
		 */
		public static function get_policies() {
			$policies = get_site_option( self::SITE_OPTION_POLICIES, array() );
			if ( ! is_array( $policies ) ) {
				$policies = array();
			}

			$defaults = array();
			foreach ( array_keys( self::get_feature_registry() ) as $feature ) {
				$defaults[ $feature ] = self::POLICY_INDIVIDUAL_SITE;
			}

			return wp_parse_args( $policies, $defaults );
		}

		/**
		 * Get policy for a feature.
		 *
		 * @param string $feature Feature slug.
		 * @return string
		 */
		public static function get_policy( $feature ) {
			if ( in_array( $feature, array( 'google_recaptcha', 'hcaptcha', 'cloudflare_turnstile' ), true ) ) {
				$feature = 'captcha';
			}
			$policies = self::get_policies();
			return isset( $policies[ $feature ] ) ? $policies[ $feature ] : self::POLICY_INDIVIDUAL_SITE;
		}

		/**
		 * Update a single feature policy.
		 *
		 * @param string $feature Feature slug.
		 * @param string $policy  Policy value.
		 * @return bool
		 */
		public static function set_policy( $feature, $policy ) {
			if ( in_array( $feature, array( 'google_recaptcha', 'hcaptcha', 'cloudflare_turnstile' ), true ) ) {
				$feature = 'captcha';
			}
			$registry = self::get_feature_registry();
			if ( ! isset( $registry[ $feature ] ) ) {
				return false;
			}

			$choices = self::get_policy_choices();
			if ( ! isset( $choices[ $policy ] ) ) {
				return false;
			}

			$policies                         = self::get_policies();
			$policies[ $feature ]             = $policy;
			$policies['google_recaptcha']     = $policy;
			$policies['hcaptcha']             = $policy;
			$policies['cloudflare_turnstile'] = $policy;
			return update_site_option( self::SITE_OPTION_POLICIES, $policies );
		}

		/**
		 * Get network-level settings bag.
		 *
		 * @return array
		 */
		public static function get_network_settings() {
			$settings = get_site_option( self::SITE_OPTION_SETTINGS, array() );
			return is_array( $settings ) ? $settings : array();
		}

		/**
		 * Update network-level settings (merge).
		 *
		 * @param array $settings Settings to merge.
		 * @return bool
		 */
		public static function update_network_settings( $settings ) {
			$current = self::get_network_settings();
			$merged  = array_merge( $current, $settings );
			return update_site_option( self::SITE_OPTION_SETTINGS, $merged );
		}

		/**
		 * Find which feature owns an option name.
		 *
		 * @param string $option_name Option name.
		 * @return string|null
		 */
		public static function get_feature_for_option( $option_name ) {
			static $map = null;
			if ( null === $map ) {
				$map = array();
				foreach ( self::get_feature_registry() as $feature => $config ) {
					if ( ! empty( $config['options'] ) && is_array( $config['options'] ) ) {
						foreach ( $config['options'] as $opt ) {
							$map[ $opt ] = $feature;
						}
					}
				}
			}
			return isset( $map[ $option_name ] ) ? $map[ $option_name ] : null;
		}

		/**
		 * Whether the current site has a local override enabled for a feature.
		 *
		 * @param string $feature Feature slug.
		 * @return bool
		 */
		public static function has_site_override( $feature ) {
			if ( in_array( $feature, array( 'google_recaptcha', 'hcaptcha', 'cloudflare_turnstile' ), true ) ) {
				$feature = 'captcha';
			}
			$overrides = get_option( self::OPTION_SITE_OVERRIDES, array() );
			if ( ! is_array( $overrides ) ) {
				return false;
			}
			return ! empty( $overrides[ $feature ] ) || ! empty( $overrides['google_recaptcha'] ) || ! empty( $overrides['hcaptcha'] ) || ! empty( $overrides['cloudflare_turnstile'] );
		}

		/**
		 * Enable/disable local override for a feature.
		 *
		 * @param string $feature Feature slug.
		 * @param bool   $enabled Enabled.
		 * @return bool
		 */
		public static function set_site_override( $feature, $enabled ) {
			$overrides = get_option( self::OPTION_SITE_OVERRIDES, array() );
			if ( ! is_array( $overrides ) ) {
				$overrides = array();
			}
			if ( in_array( $feature, array( 'google_recaptcha', 'hcaptcha', 'cloudflare_turnstile', 'captcha' ), true ) ) {
				$targets = array( 'captcha', 'google_recaptcha', 'hcaptcha', 'cloudflare_turnstile' );
				foreach ( $targets as $target ) {
					if ( $enabled ) {
						$overrides[ $target ] = 1;
					} else {
						unset( $overrides[ $target ] );
					}
				}
			} else {
				if ( $enabled ) {
					$overrides[ $feature ] = 1;
				} else {
					unset( $overrides[ $feature ] );
				}
			}
			return update_option( self::OPTION_SITE_OVERRIDES, $overrides, false );
		}

		/**
		 * Resolve effective option value for the current site.
		 *
		 * @param string $option_name Option name.
		 * @param mixed  $default     Default.
		 * @return mixed
		 */
		public static function get_option( $option_name, $default = false ) {
			if ( ! self::is_network_context() ) {
				return get_option( $option_name, $default );
			}

			$feature = self::get_feature_for_option( $option_name );
			if ( null === $feature ) {
				return get_option( $option_name, $default );
			}

			$policy = self::get_policy( $feature );

			if ( self::POLICY_INDIVIDUAL_SITE === $policy ) {
				return get_option( $option_name, $default );
			}

			if ( self::POLICY_SITE_OVERRIDE === $policy && self::has_site_override( $feature ) ) {
				return get_option( $option_name, $default );
			}

			// Site Override Allowed without override: enable/labels use site values.
			if ( self::POLICY_SITE_OVERRIDE === $policy && self::is_site_local_under_override_policy( $option_name, $feature ) ) {
				return get_option( $option_name, $default );
			}

			// Network Managed, or Site Override Allowed without local override (credentials).
			$network = self::get_network_settings();
			if ( array_key_exists( $option_name, $network ) ) {
				return $network[ $option_name ];
			}

			// Fall back to site value (pre-network defaults) then plugin default.
			$site_value = get_option( $option_name, null );
			if ( null !== $site_value ) {
				return $site_value;
			}

			return $default;
		}

		/**
		 * Persist an option respecting the current network policy.
		 *
		 * @param string $option_name Option name.
		 * @param mixed  $value       Value.
		 * @return bool|\WP_Error
		 */
		public static function update_option( $option_name, $value ) {
			if ( ! self::is_network_context() ) {
				return update_option( $option_name, $value );
			}

			$feature = self::get_feature_for_option( $option_name );
			if ( null === $feature ) {
				return update_option( $option_name, $value );
			}

			$policy = self::get_policy( $feature );

			if ( self::POLICY_NETWORK_MANAGED === $policy ) {
				if ( ! self::can_edit_network_defaults() ) {
					return new \WP_Error(
						'aio_login_network_managed',
						__( 'This setting is managed by the Network Administrator.', 'change-wp-admin-login' ),
						array( 'status' => 403 )
					);
				}
				$result = self::update_network_settings( array( $option_name => $value ) );
				self::push_option_to_all_sites( $option_name, $value );
				return $result;
			}

			if ( self::POLICY_SITE_OVERRIDE === $policy ) {
				if ( self::has_site_override( $feature ) ) {
					return update_option( $option_name, $value );
				}

				// Enable/labels stay editable on the site without a full local override.
				if ( self::is_site_local_under_override_policy( $option_name, $feature ) ) {
					return update_option( $option_name, $value );
				}

				// Network defaults only from Network Admin (main site is treated like any site).
				if ( self::can_edit_network_defaults() ) {
					$result = self::update_network_settings( array( $option_name => $value ) );
					self::push_option_to_all_sites( $option_name, $value, $feature );
					return $result;
				}

				return new \WP_Error(
					'aio_login_override_required',
					__( 'Enable a local override before changing this network setting.', 'change-wp-admin-login' ),
					array( 'status' => 403 )
				);
			}

			return update_option( $option_name, $value );
		}

		/**
		 * Write a single option value to every site (skip sites with local override).
		 *
		 * @param string      $option_name Option name.
		 * @param mixed       $value       Value.
		 * @param string|null $feature     Feature slug for override check.
		 */
		private static function push_option_to_all_sites( $option_name, $value, $feature = null ) {
			if ( null === $feature ) {
				$feature = self::get_feature_for_option( $option_name );
			}

			$was_bypassing = self::$bypass_option_hooks;
			self::$bypass_option_hooks = true;

			foreach ( self::get_sites_list() as $site ) {
				switch_to_blog( (int) $site['id'] );
				if ( $feature && self::POLICY_SITE_OVERRIDE === self::get_policy( $feature ) && self::has_site_override( $feature ) ) {
					restore_current_blog();
					continue;
				}
				update_option( $option_name, $value );
				restore_current_blog();
			}

			self::$bypass_option_hooks = $was_bypassing;
		}

		/**
		 * Feature context for admin UI (read-only flags, notices).
		 *
		 * @param string $feature Feature slug.
		 * @return array
		 */
		public static function get_feature_context( $feature ) {
			$canonical_feature = $feature;
			if ( in_array( $feature, array( 'google_recaptcha', 'hcaptcha', 'cloudflare_turnstile' ), true ) ) {
				$canonical_feature = 'captcha';
			}
			$policy   = self::get_policy( $canonical_feature );
			$override = self::has_site_override( $canonical_feature );
			$network  = self::is_network_context();

			$can_edit = true;
			$source   = 'site';
			$notice   = '';

			$from_network_admin = self::is_network_admin_request();

			if ( $network ) {
				if ( self::POLICY_NETWORK_MANAGED === $policy ) {
					// All site screens (including main site) are read-only.
					// Network defaults are edited only from Network Admin.
					$can_edit = self::can_edit_network_defaults();
					$source   = 'network';
					if ( ! $from_network_admin ) {
						$notice = __( 'This feature is Network Managed. Site administrators cannot enable, disable, or change its settings. Update it in Network Admin → AIO Login, or change the policy to Site Override Allowed.', 'change-wp-admin-login' );
					}
				} elseif ( self::POLICY_SITE_OVERRIDE === $policy ) {
					$source   = $override ? 'site_override' : 'network';
					$can_edit = $override || self::can_edit_network_defaults();
					if ( ! $from_network_admin && ! $override ) {
						$notice = __( 'Inherited from network. Enable a local override to customize this site.', 'change-wp-admin-login' );
					}
				}
			}

			return array(
				'feature'           => $feature,
				'policy'            => $policy,
				'has_site_override' => $override,
				'can_edit'          => (bool) $can_edit,
				'source'            => $source,
				'notice'            => $notice,
				'is_network'        => $network,
				'is_main_site'      => is_multisite() && is_main_site() && ! $from_network_admin,
				'is_network_admin'  => $from_network_admin,
			);
		}

		/**
		 * Whether the current user may change a feature's options on this site.
		 *
		 * @param string $feature Feature slug.
		 * @return bool
		 */
		public static function current_user_can_edit_feature( $feature ) {
			$context = self::get_feature_context( $feature );
			return ! empty( $context['can_edit'] );
		}

		/**
		 * List network sites for filters / overview.
		 *
		 * @return array
		 */
		public static function get_sites_list() {
			if ( ! is_multisite() ) {
				return array();
			}

			$sites  = get_sites(
				array(
					'number'   => 500,
					'archived' => 0,
					'spam'     => 0,
					'deleted'  => 0,
				)
			);
			$result = array();

			foreach ( $sites as $site ) {
				$blog_id = (int) $site->blog_id;
				$result[] = array(
					'id'     => $blog_id,
					'name'   => get_blog_option( $blog_id, 'blogname', $site->domain ),
					'url'    => get_site_url( $blog_id ),
					'domain' => $site->domain . $site->path,
				);
			}

			return $result;
		}

		/**
		 * Network overview stats for Dashboard.
		 *
		 * @return array
		 */
		public static function get_network_overview() {
			if ( ! is_multisite() ) {
				$licensed = \AIO_Login\AIO_Login::has_pro() ? 1 : 0;
				return array(
					'total_sites'  => 1,
					'active_sites' => 1,
					'pro_licensed' => $licensed,
					'license_seats' => 1,
				);
			}

			$total_raw = get_sites(
				array(
					'number' => 0,
					'count'  => true,
				)
			);
			$active_raw = get_sites(
				array(
					'number'   => 0,
					'archived' => 0,
					'spam'     => 0,
					'deleted'  => 0,
					'count'    => true,
				)
			);

			$total  = is_numeric( $total_raw ) ? (int) $total_raw : count( self::get_sites_list() );
			$active = is_numeric( $active_raw ) ? (int) $active_raw : $total;
			$pro_licensed = 0;

			if ( self::is_network_context() && \AIO_Login\AIO_Login::has_pro() ) {
				$pro_licensed = $active;
			} else {
				foreach ( self::get_sites_list() as $site ) {
					if ( get_blog_option( $site['id'], 'aio_login_pro_license_active', false ) ) {
						++$pro_licensed;
					}
				}
				if ( 0 === $pro_licensed && \AIO_Login\AIO_Login::has_pro() ) {
					$pro_licensed = 1;
				}
			}

			return array(
				'total_sites'   => $total,
				'active_sites'  => $active,
				'pro_licensed'  => $pro_licensed,
				'license_seats' => $total > 0 ? $total : $active,
			);
		}

		/**
		 * Aggregate activity counts across network sites.
		 *
		 * @param string $type     success|failed|lockouts.
		 * @param string $duration Duration key.
		 * @param int    $blog_id  0 = all sites.
		 * @return int
		 */
		public static function get_network_counts( $type, $duration = 'today', $blog_id = 0 ) {
			if ( ! is_multisite() || $blog_id > 0 ) {
				$target = $blog_id > 0 ? $blog_id : get_current_blog_id();
				switch_to_blog( $target );
				if ( 'lockouts' === $type ) {
					$count = \AIO_Login\Login_Controller\Failed_Logins::get_lockout_attempts_count( $duration );
				} else {
					$count = \AIO_Login\Login_Controller\Failed_Logins::get_attempts_count( $type, $duration );
				}
				restore_current_blog();
				return (int) $count;
			}

			$total = 0;
			foreach ( self::get_sites_list() as $site ) {
				switch_to_blog( $site['id'] );
				if ( 'lockouts' === $type ) {
					$total += (int) \AIO_Login\Login_Controller\Failed_Logins::get_lockout_attempts_count( $duration );
				} else {
					$total += (int) \AIO_Login\Login_Controller\Failed_Logins::get_attempts_count( $type, $duration );
				}
				restore_current_blog();
			}

			return $total;
		}

		/**
		 * Collect recent logs across sites for Activity Log / Dashboard.
		 *
		 * @param string $type    lockout|failed.
		 * @param int    $limit   Max rows.
		 * @param int    $blog_id 0 = all.
		 * @return array
		 */
		public static function get_network_logs( $type, $limit = 50, $blog_id = 0 ) {
			$logs = array();

			$sites = self::get_sites_list();
			if ( $blog_id > 0 ) {
				$sites = array_values(
					array_filter(
						$sites,
						static function ( $site ) use ( $blog_id ) {
							return (int) $site['id'] === (int) $blog_id;
						}
					)
				);
			}

			if ( empty( $sites ) && ! is_multisite() ) {
				$raw = \AIO_Login\Helper\Helper::get_logs( $type );
				foreach ( array_slice( $raw, 0, $limit ) as $row ) {
					$row['site_id']   = get_current_blog_id();
					$row['site_name'] = get_bloginfo( 'name' );
					$logs[]           = $row;
				}
				return $logs;
			}

			foreach ( $sites as $site ) {
				switch_to_blog( $site['id'] );
				$raw = \AIO_Login\Helper\Helper::get_logs( $type );
				foreach ( array_slice( $raw, 0, $limit ) as $row ) {
					$row['site_id']   = $site['id'];
					$row['site_name'] = $site['name'];
					$logs[]           = $row;
				}
				restore_current_blog();
			}

			usort(
				$logs,
				static function ( $a, $b ) {
					$ta = isset( $a['time'] ) ? (int) $a['time'] : 0;
					$tb = isset( $b['time'] ) ? (int) $b['time'] : 0;
					return $tb <=> $ta;
				}
			);

			return array_slice( $logs, 0, $limit );
		}

		/**
		 * Payload for Network Settings screen.
		 *
		 * @return array
		 */
		public static function get_admin_payload() {
			$registry   = self::get_feature_registry();
			$policies   = self::get_policies();
			$settings   = self::get_network_settings();
			$categories = array();

			$category_defs = self::filter_categories_for_install( self::get_categories(), $registry );

			foreach ( $category_defs as $cat_key => $cat ) {
				$features = array();
				foreach ( $cat['features'] as $feature ) {
					if ( ! isset( $registry[ $feature ] ) ) {
						continue;
					}
					if ( ! self::is_feature_available_on_install( $registry[ $feature ] ) ) {
						continue;
					}
					$feature_settings = array();
					foreach ( $registry[ $feature ]['options'] as $option ) {
						$feature_settings[ $option ] = array_key_exists( $option, $settings )
							? $settings[ $option ]
							: get_site_option( $option, get_option( $option, '' ) );
					}
					$features[] = array(
						'slug'     => $feature,
						'label'    => $registry[ $feature ]['label'],
						'policy'   => $policies[ $feature ],
						'settings' => $feature_settings,
					);
				}
				$categories[] = array(
					'slug'     => $cat_key,
					'label'    => $cat['label'],
					'features' => $features,
				);
			}

			return array(
				'is_multisite'     => self::is_multisite_install(),
				'is_network'       => self::is_network_context(),
				'is_super_admin'   => is_super_admin(),
				'policy_choices'   => self::get_policy_choices(),
				'categories'       => $categories,
				'sites'            => self::get_sites_list(),
				'network_overview' => self::get_network_overview(),
			);
		}
	}
}
