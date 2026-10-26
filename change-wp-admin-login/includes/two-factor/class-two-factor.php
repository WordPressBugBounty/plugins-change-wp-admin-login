<?php
	/**
	 * Class Two_Factor — Free Email-only 2FA (Guideline 5 / AIOL-745).
	 * Full TOTP / backup codes / grace period live in aio-login-pro.
	 *
	 * @package AIO_Login
	 */

	namespace AIO_Login\Two_Factor;

	use AIO_Login\Multisite\Network_Settings;

	defined( 'ABSPATH' ) || exit;

	if ( ! class_exists( 'AIO_Login\\Two_Factor\\Two_Factor' ) ) {
		/**
		 * Class Two_Factor
		 */
		class Two_Factor {
			/**
			 * Two Factor constructor.
			 */
			private function __construct() {
				// When Pro is active, Pro owns full 2FA — do not register Free hooks.
				if ( self::should_defer_to_pro() ) {
					return;
				}

				add_action( 'wp_login', array( $this, 'maybe_flag_2fa_prompt' ), 10, 2 );
				add_action( 'admin_init', array( $this, 'handle_2fa_prompt_dismiss' ) );
				add_action( 'admin_init', array( $this, 'maybe_redirect_2fa_setup_from_admin' ), 1 );
				add_action( 'admin_init', array( $this, 'enforce_2fa_setup_on_admin_access' ), 2 );
				add_action( 'login_init', array( $this, 'maybe_render_2fa_setup_on_login' ), 1 );
				add_filter( 'login_redirect', array( $this, 'redirect_to_2fa_setup_screen' ), 101, 3 );
				add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
			}

			/**
			 * Whether Free 2FA should no-op because Pro owns 2FA.
			 *
			 * @return bool
			 */
			public static function should_defer_to_pro() {
				if ( defined( 'AIO_LOGIN_PRO__VERSION' ) ) {
					return true;
				}
				if ( class_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor', false ) ) {
					return true;
				}
				if ( class_exists( '\AIO_Login\Aio_Login' ) && \AIO_Login\Aio_Login::has_pro() ) {
					return true;
				}
				if ( ! function_exists( 'is_plugin_active' ) ) {
					$plugin_php = ABSPATH . 'wp-admin/includes/plugin.php';
					if ( file_exists( $plugin_php ) ) {
						include_once $plugin_php;
					}
				}
				return function_exists( 'is_plugin_active' ) && is_plugin_active( 'aio-login-pro/aio-login-pro.php' );
			}

			/**
			 * Available 2FA methods in Free (Email-only).
			 *
			 * @return string[]
			 */
			public static function get_available_methods() {
				if ( class_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor', false )
					&& method_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor', 'get_available_methods' ) ) {
					return \AIO_Login_Pro\Two_Factor\Two_Factor::get_available_methods();
				}
				return array( 'email' );
			}

			public function maybe_flag_2fa_prompt( $user_login, $user ) {
				if ( ! ( $user instanceof \WP_User ) ) {
					return;
				}
				if ( $this->should_show_2fa_prompt( (int) $user->ID ) ) {
					update_user_meta( (int) $user->ID, 'aio_login_pro__2fa_prompt_show', '1' );
				}
			}

			public function handle_2fa_prompt_dismiss() {
				if ( empty( $_GET['aio_login_pro_2fa_later'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return;
				}
				if ( ! is_user_logged_in() ) {
					return;
				}
				$user_id = get_current_user_id();
				check_admin_referer( 'aio_login_pro_2fa_later_' . $user_id );
				delete_user_meta( $user_id, 'aio_login_pro__2fa_prompt_show' );
				// Free has no grace period — soft dismiss for 24h unless strictly enforced.
				if ( ! $this->is_user_strictly_enforced_by_2fa_policy( $user_id ) ) {
					update_user_meta( $user_id, 'aio_login_pro__2fa_prompt_dismissed_until', time() + DAY_IN_SECONDS );
				}
				$redirect = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), admin_url() ) : admin_url(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( $redirect );
				exit;
			}

			public function render_2fa_setup_prompt() {
				$user_id = get_current_user_id();
				if ( $user_id <= 0 || ! $this->should_show_2fa_prompt( $user_id ) ) {
					return;
				}
				$setup_url = admin_url( 'admin.php?page=aio-login#/2fa' );
				$later_url = wp_nonce_url(
					add_query_arg(
						array(
							'aio_login_pro_2fa_later' => '1',
							'redirect_to'               => rawurlencode( admin_url() ),
						),
						admin_url()
					),
					'aio_login_pro_2fa_later_' . $user_id
				);
				?>
				<div class="notice notice-warning" style="padding: 12px 14px;">
					<p style="margin: 0 0 8px 0;">
						<strong><?php echo esc_html__( 'Set up Two-Factor Authentication', 'change-wp-admin-login' ); ?></strong>
						<?php echo esc_html__( 'To keep your account secure, please set up 2FA.', 'change-wp-admin-login' ); ?>
					</p>
					<p style="margin: 0;">
						<a class="button button-primary" href="<?php echo esc_url( $setup_url ); ?>">
							<?php echo esc_html__( 'Setup 2FA Now', 'change-wp-admin-login' ); ?>
						</a>
						<?php if ( ! $this->is_user_strictly_enforced_by_2fa_policy( $user_id ) ) : ?>
						<a class="button button-secondary" style="margin-left: 8px;" href="<?php echo esc_url( $later_url ); ?>">
							<?php echo esc_html__( 'Do it later', 'change-wp-admin-login' ); ?>
						</a>
						<?php endif; ?>
					</p>
				</div>
				<?php
			}

			private function user_has_pending_2fa_login_challenge( $user ) {
				if ( ! ( $user instanceof \WP_User ) ) {
					return false;
				}
				if ( ! class_exists( __NAMESPACE__ . '\\Two_Factor_Auth' ) ) {
					return false;
				}
				return Two_Factor_Auth::get_instance()->user_has_pending_login_challenge( $user );
			}

			public function redirect_to_2fa_setup_screen( $redirect_to, $requested_redirect_to, $user ) {
				if ( ! ( $user instanceof \WP_User ) ) {
					return $redirect_to;
				}
				if ( $this->user_has_pending_2fa_login_challenge( $user ) ) {
					return $redirect_to;
				}
				if ( ! $this->should_force_2fa_setup_screen( (int) $user->ID ) ) {
					return $redirect_to;
				}
				update_user_meta( (int) $user->ID, 'aio_login_pro__2fa_prompt_show', '1' );
				return add_query_arg(
					array(
						'aio_login_pro_2fa_setup' => '1',
						'redirect_to'               => rawurlencode( $redirect_to ),
					),
					wp_login_url()
				);
			}

			/**
			 * Legacy URL: wp-admin with ?aio_login_pro_2fa_setup=1 → same experience on wp-login.php.
			 */
			public function maybe_redirect_2fa_setup_from_admin() {
				if ( ! is_user_logged_in() ) {
					return;
				}

				if ( empty( $_GET['aio_login_pro_2fa_setup'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return;
				}

				wp_safe_redirect( add_query_arg( 'aio_login_pro_2fa_setup', '1', wp_login_url() ) );
				exit;
			}

			/**
			 * Hard gate: block wp-admin until enforced users complete 2FA setup (except the 2FA setup screen).
			 */
			public function enforce_2fa_setup_on_admin_access() {
				if ( ! is_user_logged_in() || ! is_admin() || wp_doing_ajax() ) {
					return;
				}
				if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
					return;
				}

				$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$current_tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( 'aio-login' === $current_page && '2fa' === $current_tab ) {
					return;
				}

				$user_id = get_current_user_id();
				if ( ! $this->should_force_2fa_setup_screen( $user_id ) ) {
					return;
				}

				wp_safe_redirect( admin_url( 'admin.php?page=aio-login&tab=2fa' ) );
				exit;
			}

			public function maybe_render_2fa_setup_on_login() {
				if ( empty( $_GET['aio_login_pro_2fa_setup'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return;
				}
				if ( ! is_user_logged_in() ) {
					return;
				}
				$user_id = get_current_user_id();
				if ( ! $this->should_show_2fa_prompt( $user_id ) && ! $this->should_force_2fa_setup_screen( $user_id ) ) {
					return;
				}
				update_user_meta( $user_id, 'aio_login_pro__2fa_prompt_show', '1' );
				$template_path = AIO_LOGIN__DIR_PATH . 'includes/two-factor/settings/two-factor-setup-prompt.php';
				$template_path = apply_filters( 'aio_login_pro__two_factor_setup_prompt_template_path', $template_path );
				if ( file_exists( $template_path ) ) {
					load_template( $template_path, true );
					exit;
				}
			}

			private function should_show_2fa_prompt( $user_id ) {
				if ( ! self::is_2fa_module_master_enabled() ) {
					return false;
				}

				$user = get_userdata( $user_id );
				if ( $user && $this->user_has_pending_2fa_login_challenge( $user ) ) {
					return false;
				}

				if ( $this->has_completed_user_2fa_setup( $user_id ) ) {
					delete_user_meta( $user_id, 'aio_login_pro__2fa_prompt_show' );
					return false;
				}

				if ( ! $this->is_user_covered_by_2fa_enforcement_policy( (int) $user_id ) ) {
					if ( ! self::is_security_update_required_for_user( (int) $user_id ) ) {
						return false;
					}
				}

				if ( ! $this->is_user_strictly_enforced_by_2fa_policy( (int) $user_id ) ) {
					$dismissed_until = (int) get_user_meta( $user_id, 'aio_login_pro__2fa_prompt_dismissed_until', true );
					if ( $dismissed_until && $dismissed_until > time() ) {
						return false;
					}
				}

				return true;
			}

			private function has_completed_user_2fa_setup( $user_id ) {
				$method         = (string) get_user_meta( $user_id, 'aio_login_pro__2fa_user_method', true );
				$email_verified = '1' === get_user_meta( $user_id, 'aio_login_pro__2fa_email_profile_verified', true );

				if ( 'email' === $method || '' === $method ) {
					return $email_verified;
				}

				// Free is Email-only — any non-email method counts as incomplete until Email is set up.
				return $email_verified && 'email' === $method;
			}

			/**
			 * @param int $user_id User ID.
			 * @return string 'email'|''
			 */
			public static function get_required_site_method_for_user( $user_id ) {
				$user_id = (int) $user_id;
				if ( $user_id <= 0 ) {
					return '';
				}
				if ( user_can( $user_id, 'manage_options' ) ) {
					return '';
				}
				if ( 'on' === get_option( 'aio_login_pro__2fa_email_otp_enable', 'off' ) ) {
					return 'email';
				}
				return '';
			}

			public static function is_security_update_required_for_user( $user_id ) {
				$user_id = (int) $user_id;
				if ( $user_id <= 0 || user_can( $user_id, 'manage_options' ) ) {
					return false;
				}
				$required = self::get_required_site_method_for_user( $user_id );
				if ( '' === $required ) {
					return false;
				}
				$method = (string) get_user_meta( $user_id, 'aio_login_pro__2fa_user_method', true );
				if ( $method !== $required ) {
					$email_verified = '1' === get_user_meta( $user_id, 'aio_login_pro__2fa_email_profile_verified', true );
					return ! ( 'email' === $required && $email_verified );
				}
				return false;
			}

			public static function clear_user_email_2fa_setup( $user_id ) {
				$user_id = (int) $user_id;
				if ( $user_id <= 0 ) {
					return;
				}
				delete_user_meta( $user_id, 'aio_login_pro__2fa_email_profile_verified' );
				delete_user_meta( $user_id, 'aio_login_pro__2fa_email_recipient' );
				delete_user_meta( $user_id, 'aio_login_pro__2fa_email_cc' );
				delete_user_meta( $user_id, 'aio_login_pro__email_otp_hash' );
				delete_user_meta( $user_id, 'aio_login_pro__email_otp_expires' );
				delete_user_meta( $user_id, 'aio_login_pro__email_otp_attempts' );
				delete_user_meta( $user_id, 'aio_login_pro__email_otp_last_sent' );
				delete_user_meta( $user_id, 'aio_login_pro__profile_email_otp_hash' );
				delete_user_meta( $user_id, 'aio_login_pro__profile_email_otp_expires' );
				delete_user_meta( $user_id, 'aio_login_pro__profile_email_otp_attempts' );
				delete_user_meta( $user_id, 'aio_login_pro__profile_email_otp_last_sent' );
				$method = (string) get_user_meta( $user_id, 'aio_login_pro__2fa_user_method', true );
				if ( 'email' === $method ) {
					delete_user_meta( $user_id, 'aio_login_pro__2fa_user_method' );
				}
			}

			private function should_force_2fa_setup_screen( $user_id ) {
				if ( ! self::is_2fa_module_master_enabled() ) {
					return false;
				}
				if ( $this->has_completed_user_2fa_setup( $user_id ) ) {
					return false;
				}
				return $this->is_user_strictly_enforced_by_2fa_policy( (int) $user_id );
			}

			private function is_user_strictly_enforced_by_2fa_policy( $user_id ) {
				if ( 'on' === get_option( 'aio_login_pro__2fa_policy_enforce_all', 'off' ) ) {
					return true;
				}

				$enforce_specific = 'on' === get_option( 'aio_login_pro__2fa_policy_enforce_specific', 'off' );
				if ( ! $enforce_specific ) {
					return false;
				}

				$selected_roles = array_values(
					array_filter(
						array_map( 'sanitize_key', (array) get_option( 'aio_login_pro__2fa_policy_roles', array() ) )
					)
				);
				if ( empty( $selected_roles ) ) {
					return false;
				}

				$user = get_userdata( (int) $user_id );
				if ( ! $user || empty( $user->roles ) ) {
					return false;
				}

				return (bool) array_intersect( (array) $user->roles, $selected_roles );
			}

			private function is_user_covered_by_2fa_enforcement_policy( $user_id ) {
				$enforce_all = 'on' === get_option( 'aio_login_pro__2fa_policy_enforce_all', 'off' );
				if ( $enforce_all ) {
					return true;
				}

				$enforce_specific = 'on' === get_option( 'aio_login_pro__2fa_policy_enforce_specific', 'off' );
				$selected_roles   = array_values(
					array_filter(
						array_map( 'sanitize_key', (array) get_option( 'aio_login_pro__2fa_policy_roles', array() ) )
					)
				);

				if ( $enforce_specific ) {
					if ( empty( $selected_roles ) ) {
						return false;
					}

					$user = get_userdata( $user_id );
					if ( ! $user || empty( $user->roles ) ) {
						return false;
					}

					return (bool) array_intersect( $user->roles, $selected_roles );
				}

				return false;
			}

			public function rest_api_init() {
				register_rest_route(
					'aio-login-pro/tfa',
					'/get-settings',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_settings' ),
						'permission_callback' => function () {
							return is_user_logged_in();
						},
					)
				);

				register_rest_route(
					'aio-login-pro/tfa',
					'/send-profile-email-setup-otp',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'send_profile_email_setup_otp' ),
						'permission_callback' => function () {
							return is_user_logged_in();
						},
					)
				);

				register_rest_route(
					'aio-login-pro/tfa',
					'/verify-profile-email-setup-otp',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'verify_profile_email_setup_otp' ),
						'permission_callback' => function () {
							return is_user_logged_in();
						},
					)
				);

				register_rest_route(
					'aio-login-pro/tfa',
					'/save-settings',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'save_tfa_settings' ),
						'permission_callback' => function () {
							return is_user_logged_in();
						},
					)
				);
			}

			/**
			 * Stub: backup codes are Pro-only.
			 *
			 * @return bool
			 */
			public static function is_backup_codes_feature_enabled() {
				return false;
			}

			public static function should_prompt_email_finish_setup( $user_id ) {
				$user_id = (int) $user_id;
				if ( $user_id <= 0 ) {
					return false;
				}
				if ( ! self::is_2fa_module_master_enabled() ) {
					return false;
				}
				$method          = (string) get_user_meta( $user_id, 'aio_login_pro__2fa_user_method', true );
				$email_global_on = ( 'on' === get_option( 'aio_login_pro__2fa_email_otp_enable', 'off' ) );
				if ( ! $email_global_on && 'email' !== $method ) {
					return false;
				}
				if ( ! user_can( $user_id, 'manage_options' ) && 'email' !== $method ) {
					return false;
				}
				if ( '1' === get_user_meta( $user_id, 'aio_login_pro__2fa_email_profile_verified', true ) ) {
					return false;
				}
				return true;
			}

			/**
			 * Block site-wide 2FA REST changes when the network policy locks this feature.
			 *
			 * @return \WP_Error|null
			 */
			private function rest_deny_if_site_2fa_locked() {
				if ( ! current_user_can( 'manage_options' ) ) {
					return null;
				}
				if ( ! Network_Settings::current_user_can_edit_feature( 'two_factor_authentication' ) ) {
					return new \WP_Error(
						'aio_login_network_managed',
						esc_html__( 'These settings are managed by the Network Administrator.', 'change-wp-admin-login' ),
						array( 'status' => 403 )
					);
				}
				return null;
			}

			public function get_settings() {
				global $current_user;

				$is_admin_user = current_user_can( 'manage_options' );
				$email_settings_defaults = array(
					'sender_name'              => get_bloginfo( 'name' ),
					'sender_email'             => get_option( 'admin_email', '' ),
					'admin_notification_email' => get_option( 'admin_email', '' ),
					'email_subject'            => __( 'Your One-Time Verification Code', 'change-wp-admin-login' ),
					'email_template'           => "Hello {user_name},\n\nYour verification code is: {code}\n\nThis code will expire in {expiry} minutes.\n\nIf you did not request this code, please ignore this email.\n\nThanks,\n{site_name}\n{site_url}",
					'otp_expiry'               => 5,
					'resend_cooldown'          => 30,
					'max_otp_attempts'         => 5,
					'block_duration_minutes'   => 15,
				);
				$saved_email_settings = get_option( 'aio_login_pro__2fa_email_settings', array() );
				if ( ! is_array( $saved_email_settings ) ) {
					$saved_email_settings = array();
				}
				foreach ( $email_settings_defaults as $key => $default_value ) {
					if ( ! isset( $saved_email_settings[ $key ] ) || '' === $saved_email_settings[ $key ] || null === $saved_email_settings[ $key ] ) {
						$saved_email_settings[ $key ] = $default_value;
					}
				}
				$email_settings = wp_parse_args( $saved_email_settings, $email_settings_defaults );
				$limited_email_settings = array(
					'recipient_email' => get_user_meta( $current_user->ID, 'aio_login_pro__2fa_email_recipient', true ),
					'cc_email'        => get_user_meta( $current_user->ID, 'aio_login_pro__2fa_email_cc', true ),
				);
				if ( empty( $limited_email_settings['recipient_email'] ) ) {
					$limited_email_settings['recipient_email'] = $current_user->user_email;
				}

				$user_method = (string) get_user_meta( $current_user->ID, 'aio_login_pro__2fa_user_method', true );
				$site_method = ( 'on' === get_option( 'aio_login_pro__2fa_email_otp_enable', 'off' ) ) ? 'email' : '';

				$settings = array(
					'master_2fa_enabled'            => self::is_2fa_module_master_enabled(),
					'site_method'                   => $site_method,
					'available_methods'             => self::get_available_methods(),
					'enabled'                       => false,
					'authenticator_app'             => false,
					'email_otp'                     => $is_admin_user ? ( 'on' === get_option( 'aio_login_pro__2fa_email_otp_enable', 'off' ) ) : ( 'email' === $user_method ),
					'remember_device'               => false,
					'is_admin_user'                 => $is_admin_user,
					'email_settings'                => $email_settings,
					'limited_email_settings'        => $limited_email_settings,
					'totp_settings'                 => array(),
					'remember_device_settings'      => array(),
					'trusted_devices'               => array(),
					'force_session_logout_enabled'  => false,
					'user_enabled'                  => false,
					'user_2fa_method'               => ( 'email' === $user_method ) ? 'email' : '',
					'qrcode_url'                    => '',
					'base32'                        => '',
					'enable_nonce'                  => wp_create_nonce( '_enable_totp' ),
					'verify_nonce'                  => wp_create_nonce( 'aio_login_pro_tfa' ),
					'user_id'                       => $current_user->ID,
					'backup_codes_enabled'          => false,
					'user_has_backup_codes'         => false,
					'email_2fa_profile_verified'    => '1' === get_user_meta( $current_user->ID, 'aio_login_pro__2fa_email_profile_verified', true ),
					'authenticator_plan_unlocked'   => false,
					'professional_features_unlocked'=> false,
					'network'                       => Network_Settings::get_feature_context( 'two_factor_authentication' ),
				);

				return rest_ensure_response( $settings );
			}

			public function send_profile_email_setup_otp( $request ) {
				$params = $request->get_params();
				if ( empty( $params['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), '_enable_totp' ) ) {
					return new \WP_Error(
						'aio_login_pro__error',
						esc_html__( 'Invalid security token', 'change-wp-admin-login' ),
						array( 'status' => 403 )
					);
				}

				if ( ! self::is_2fa_module_master_enabled() ) {
					return new \WP_Error(
						'aio_login_pro__error',
						esc_html__( 'Email OTP is not available.', 'change-wp-admin-login' ),
						array( 'status' => 400 )
					);
				}

				$locked = $this->rest_deny_if_site_2fa_locked();
				if ( $locked instanceof \WP_Error ) {
					return $locked;
				}

				$uid = get_current_user_id();
				$res = Two_Factor_Auth::get_instance()->send_profile_setup_email_otp( $uid );
				if ( is_wp_error( $res ) ) {
					return $res;
				}

				return rest_ensure_response( array( 'message' => esc_html__( 'Verification code sent to your email.', 'change-wp-admin-login' ) ) );
			}

			public function verify_profile_email_setup_otp( $request ) {
				$params = $request->get_params();
				if ( empty( $params['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), '_enable_totp' ) ) {
					return new \WP_Error(
						'aio_login_pro__error',
						esc_html__( 'Invalid security token', 'change-wp-admin-login' ),
						array( 'status' => 403 )
					);
				}

				$locked = $this->rest_deny_if_site_2fa_locked();
				if ( $locked instanceof \WP_Error ) {
					return $locked;
				}

				$otp = isset( $params['otp'] ) ? preg_replace( '/\D/', '', (string) $params['otp'] ) : '';
				if ( strlen( $otp ) !== 6 ) {
					return new \WP_Error(
						'aio_login_pro__error',
						esc_html__( 'Please enter the 6-digit code.', 'change-wp-admin-login' ),
						array( 'status' => 400 )
					);
				}

				$uid = get_current_user_id();
				$res = Two_Factor_Auth::get_instance()->verify_profile_setup_email_otp( $uid, $otp );
				if ( is_wp_error( $res ) ) {
					return $res;
				}

				if ( user_can( $uid, 'manage_options' ) ) {
					$result = Network_Settings::update_option( 'aio_login_pro__2fa_email_otp_enable', 'on' );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					$result = Network_Settings::update_option( 'aio_login_pro__two_factor_auth_enable', 'off' );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				}

				update_user_meta( $uid, 'aio_login_pro__2fa_email_profile_verified', '1' );
				Two_Factor_Auth::get_instance()->login_user( $uid );

				return rest_ensure_response(
					array(
						'message'      => esc_html__( 'Email verification successful.', 'change-wp-admin-login' ),
						'backup_codes' => null,
					)
				);
			}

			public function save_tfa_settings( $request ) {
				$params = $request->get_params();

				if ( ! isset( $params['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), '_enable_totp' ) ) {
					return new \WP_Error( 'aio_login_pro__error', esc_html__( 'Invalid request', 'change-wp-admin-login' ), array( 'status' => 403 ) );
				}

				$locked = $this->rest_deny_if_site_2fa_locked();
				if ( $locked instanceof \WP_Error ) {
					return $locked;
				}

				if ( ! current_user_can( 'manage_options' ) ) {
					$limited_raw     = isset( $params['limited_email_settings'] ) && is_array( $params['limited_email_settings'] ) ? $params['limited_email_settings'] : array();
					$recipient_email = isset( $limited_raw['recipient_email'] ) ? sanitize_email( wp_unslash( $limited_raw['recipient_email'] ) ) : '';
					$cc_email        = isset( $limited_raw['cc_email'] ) ? sanitize_email( wp_unslash( $limited_raw['cc_email'] ) ) : '';

					$email_active     = isset( $params['limited_email_method_active'] ) && filter_var( $params['limited_email_method_active'], FILTER_VALIDATE_BOOLEAN );
					$email_ready_flow = ! empty( $params['email_ready_flow'] ) && filter_var( $params['email_ready_flow'], FILTER_VALIDATE_BOOLEAN );
					if ( $email_ready_flow ) {
						$email_active = true;
					}

					$uid = get_current_user_id();
					update_user_meta( $uid, 'aio_login_pro__2fa_email_recipient', $recipient_email );
					update_user_meta( $uid, 'aio_login_pro__2fa_email_cc', $cc_email );

					if ( $email_active ) {
						update_user_meta( $uid, 'aio_login_pro__2fa_user_method', 'email' );
						delete_user_meta( $uid, 'aio_login_pro__user_secret' );
						update_user_meta( $uid, 'aio_login_pro__two_factor_auth_enable', 'off' );
					}

					$method_after_final = (string) get_user_meta( $uid, 'aio_login_pro__2fa_user_method', true );
					if ( 'email' !== $method_after_final ) {
						delete_user_meta( $uid, 'aio_login_pro__2fa_email_profile_verified' );
					}

					return rest_ensure_response(
						array(
							'message'                   => esc_html__( 'Settings saved successfully', 'change-wp-admin-login' ),
							'prompt_email_finish_setup' => self::should_prompt_email_finish_setup( $uid ),
						)
					);
				}

				$email_otp      = 'off';
				$email_settings = null;
				$email_requested = isset( $params['email_otp'] ) && filter_var( $params['email_otp'], FILTER_VALIDATE_BOOLEAN );

				if ( $email_requested ) {
					$email_otp = 'on';
				}

				if ( isset( $params['email_settings'] ) && is_array( $params['email_settings'] ) ) {
					$raw            = $params['email_settings'];
					$email_settings = array(
						'sender_name'              => isset( $raw['sender_name'] ) ? sanitize_text_field( wp_unslash( $raw['sender_name'] ) ) : '',
						'sender_email'             => isset( $raw['sender_email'] ) ? sanitize_email( wp_unslash( $raw['sender_email'] ) ) : '',
						'admin_notification_email' => isset( $raw['admin_notification_email'] ) ? sanitize_email( wp_unslash( $raw['admin_notification_email'] ) ) : '',
						'email_subject'            => isset( $raw['email_subject'] ) ? sanitize_text_field( wp_unslash( $raw['email_subject'] ) ) : '',
						'email_template'           => isset( $raw['email_template'] ) ? sanitize_textarea_field( wp_unslash( $raw['email_template'] ) ) : '',
						'otp_expiry'               => isset( $raw['otp_expiry'] ) ? max( 1, absint( $raw['otp_expiry'] ) ) : 5,
						'resend_cooldown'          => isset( $raw['resend_cooldown'] ) ? max( 0, absint( $raw['resend_cooldown'] ) ) : 30,
						'max_otp_attempts'         => isset( $raw['max_otp_attempts'] ) ? max( 1, absint( $raw['max_otp_attempts'] ) ) : 5,
						'block_duration_minutes'   => isset( $raw['block_duration_minutes'] ) ? max( 1, absint( $raw['block_duration_minutes'] ) ) : 15,
					);

					if ( 'on' === $email_otp ) {
						if ( '' === trim( (string) $email_settings['sender_name'] ) ) {
							return new \WP_Error(
								'aio_login_pro__error',
								esc_html__( 'Sender Name is required.', 'change-wp-admin-login' ),
								array( 'status' => 400 )
							);
						}
						if ( '' === trim( (string) $email_settings['sender_email'] ) || ! is_email( $email_settings['sender_email'] ) ) {
							return new \WP_Error(
								'aio_login_pro__error',
								esc_html__( 'Please enter a valid Sender Email.', 'change-wp-admin-login' ),
								array( 'status' => 400 )
							);
						}
					}
				}

				// Free is Email-only — never enable Authenticator / remember-device site options.
				$updates = array(
					'aio_login_pro__two_factor_auth_enable'       => 'off',
					'aio_login_pro__2fa_email_otp_enable'       => $email_otp,
					'aio_login_pro__2fa_remember_device_enable' => 'off',
				);
				foreach ( $updates as $option_name => $value ) {
					$result = Network_Settings::update_option( $option_name, $value );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				}
				if ( is_array( $email_settings ) ) {
					$result = Network_Settings::update_option( 'aio_login_pro__2fa_email_settings', $email_settings );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				}

				$uid = get_current_user_id();
				// Do not clear email_profile_verified on save — that disables the login OTP
				// challenge (is_active=false) and lets Login Redirection bypass Email 2FA.
				// Free UI can still show I'm Ready after save via client-side flag.

				return rest_ensure_response(
					array(
						'message'                   => esc_html__( 'Settings saved successfully', 'change-wp-admin-login' ),
						'prompt_email_finish_setup' => self::should_prompt_email_finish_setup( $uid ),
					)
				);
			}

			public static function is_2fa_module_master_enabled() {
				// When Pro owns 2FA, defer so TOTP-only sites still report master enabled.
				if ( class_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor', false ) ) {
					return \AIO_Login_Pro\Two_Factor\Two_Factor::is_2fa_module_master_enabled();
				}
				// Free: Email OTP is the only site-wide method (Network-aware).
				return ( 'on' === Network_Settings::get_option( 'aio_login_pro__2fa_email_otp_enable', 'off' ) );
			}

			/**
			 * Authenticator (TOTP) site toggle — Free never enables this; Pro owns it.
			 *
			 * @return bool
			 */
			public static function two_factor_enabled() {
				if ( class_exists( '\AIO_Login_Pro\Two_Factor\Two_Factor', false ) ) {
					return \AIO_Login_Pro\Two_Factor\Two_Factor::two_factor_enabled();
				}
				return false;
			}

			public static function get_current_user_secret( $user_id ) {
				return '';
			}

			public static function get_instance() {
				static $instance = null;

				if ( is_null( $instance ) ) {
					$instance = new self();
				}

				return $instance;
			}
		}
	}
