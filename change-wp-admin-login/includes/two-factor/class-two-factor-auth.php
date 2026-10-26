<?php
/**
 * Class Two_Factor_Auth — Free Email-only login challenge (Guideline 5 / AIOL-745).
 *
 * @package AIO_Login
 */

namespace AIO_Login\Two_Factor;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Two_Factor\\Two_Factor_Auth' ) ) {
	/**
	 * Class Two_Factor_Auth
	 */
	class Two_Factor_Auth {

		/**
		 * @var \WP_User $current_user Current user.
		 */
		private $current_user;

		/**
		 * @var \WP_Error $wp_error WP Error.
		 */
		private $wp_error;

		/**
		 * Two Factor Auth constructor.
		 */
		private function __construct() {
			$this->wp_error = new \WP_Error();

			// When Pro is active, Pro owns full 2FA login challenge.
			if ( Two_Factor::should_defer_to_pro() ) {
				return;
			}

			add_action( 'login_init', array( $this, 'two_factor_authentication' ) );
			add_action( 'aio_login_pro__tfa_errors', array( $this, 'display_errors' ) );
			add_action( 'wp_logout', array( $this, 'two_factor_authentication_logout' ) );
			add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
			// After Login Redirection (100), 2FA setup (101), and passwordless finalize (102)
			// so the OTP verification URL is the final redirect target.
			add_filter( 'login_redirect', array( $this, 'maybe_redirect_to_tfa' ), 103, 3 );
			// Hard gate: if Login Redirection (or anything else) skipped the OTP URL, force it.
			add_action( 'template_redirect', array( $this, 'maybe_force_pending_otp_challenge' ), 1 );
			add_action( 'admin_init', array( $this, 'maybe_force_pending_otp_challenge' ), 1 );
			// Also block AJAX / REST while Email OTP is still pending (G02-002).
			add_action( 'admin_init', array( $this, 'maybe_block_pending_otp_ajax' ), 0 );
			add_filter( 'rest_authentication_errors', array( $this, 'maybe_block_pending_otp_rest' ), 99 );
		}

		public function two_factor_authentication() {
			global $current_user;
			$this->current_user = $current_user;
			if ( $this->is_active() ) {

				if ( isset( $_GET['action'] ) && 'aio_login_pro__two_factor_auth' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$this->process_tfa();
				}

				if ( ! $this->is_user_logged_in() ) {
					$this->maybe_handle_email_otp_resend();
					$this->maybe_issue_email_otp();
					$template_path = AIO_LOGIN__DIR_PATH . 'includes/two-factor/settings/two-factor-auth.php';
					$template_path = apply_filters( 'aio_login_pro__two_factor_template_path', $template_path );
					if ( file_exists( $template_path ) ) {
						load_template( $template_path, true );
						exit;
					}
				}
			}
		}

		public function user_has_pending_login_challenge( $user ) {
			if ( ! ( $user instanceof \WP_User ) ) {
				return false;
			}
			$prev               = $this->current_user;
			$this->current_user = $user;
			$pending            = $this->is_active() && ! $this->is_user_logged_in();
			$this->current_user = $prev;
			return $pending;
		}

		public function is_active() {
			$user_id = 0;
			if ( $this->current_user instanceof \WP_User ) {
				$user_id = (int) $this->current_user->ID;
			}
			if ( $user_id <= 0 && is_user_logged_in() ) {
				$user_id = (int) get_current_user_id();
			}
			if ( $user_id <= 0 ) {
				return false;
			}
			if ( ! ( $this->current_user instanceof \WP_User ) || (int) $this->current_user->ID !== $user_id ) {
				$this->current_user = get_user_by( 'id', $user_id );
				if ( ! ( $this->current_user instanceof \WP_User ) ) {
					return false;
				}
			}

			if ( ! Two_Factor::is_2fa_module_master_enabled() ) {
				return false;
			}

			$user_id        = (int) $this->current_user->ID;
			$email_verified = '1' === get_user_meta( $user_id, 'aio_login_pro__2fa_email_profile_verified', true );
			$user_method    = (string) get_user_meta( $user_id, 'aio_login_pro__2fa_user_method', true );

			if ( $email_verified && ( 'email' === $user_method || '' === $user_method ) ) {
				return apply_filters( 'aio_login_pro__two_factor_enabled', true, $user_id );
			}

			return apply_filters( 'aio_login_pro__two_factor_enabled', false, $user_id );
		}

		/**
		 * Free is Email-only — always use email OTP when 2FA is active for the user.
		 *
		 * @param int $user_id User ID.
		 * @return bool
		 */
		public function should_use_email_otp_challenge( $user_id ) {
			return $this->is_email_otp_enabled();
		}

		public function process_tfa() {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'verify_otp' ) ) {
				return;
			}

			if ( $this->is_otp_lockout_active( (int) $this->current_user->ID ) ) {
				$remaining = (int) ceil( ( $this->get_otp_lockout_until( (int) $this->current_user->ID ) - time() ) / 60 );
				$remaining = max( 1, $remaining );
				$this->wp_error->add(
					'otp_locked',
					sprintf( esc_html__( 'Too many OTP attempts. Try again in %d minute(s).', 'change-wp-admin-login' ), $remaining )
				);
				return;
			}

			$otp = '';
			if ( isset( $_POST['totp'] ) ) {
				$otp = sanitize_text_field( wp_unslash( $_POST['totp'] ) );
			}

			if ( $this->verify_email_otp( $otp ) ) {
				$this->clear_otp_attempt_state( (int) $this->current_user->ID );
				$this->login_user();
				$redirect_to = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), admin_url() ) : admin_url(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				wp_safe_redirect( $redirect_to );
				exit;
			}

			$this->record_failed_otp_attempt( (int) $this->current_user->ID );
			if ( ! $this->wp_error->has_errors() ) {
				$user_id   = (int) $this->current_user->ID;
				$cfg       = $this->get_otp_lockout_settings( $user_id );
				$failed    = (int) get_user_meta( $user_id, 'aio_login_pro__2fa_failed_attempts', true );
				$remaining = max( 0, (int) $cfg['max_attempts'] - $failed );
				$this->wp_error->add(
					'invalid_otp',
					sprintf(
						/* translators: %d: number of OTP attempts remaining before lockout */
						esc_html__( 'Invalid OTP. %d attempt(s) remaining.', 'change-wp-admin-login' ),
						$remaining
					)
				);
			}
		}

		private function get_otp_lockout_settings( $user_id ) {
			$email_settings = get_option( 'aio_login_pro__2fa_email_settings', array() );
			$max_attempts   = isset( $email_settings['max_otp_attempts'] ) ? max( 1, min( 50, absint( $email_settings['max_otp_attempts'] ) ) ) : 5;
			$duration       = isset( $email_settings['block_duration_minutes'] ) ? max( 1, min( 10080, absint( $email_settings['block_duration_minutes'] ) ) ) : 15;
			return array(
				'enabled'          => true,
				'max_attempts'     => $max_attempts,
				'duration_minutes' => $duration,
			);
		}

		private function get_otp_lockout_until( $user_id ) {
			return (int) get_user_meta( $user_id, 'aio_login_pro__2fa_lockout_until', true );
		}

		private function is_otp_lockout_active( $user_id ) {
			$cfg = $this->get_otp_lockout_settings( $user_id );
			if ( ! $cfg['enabled'] ) {
				return false;
			}
			$until = $this->get_otp_lockout_until( $user_id );
			if ( $until <= 0 ) {
				return false;
			}
			if ( $until > time() ) {
				return true;
			}
			delete_user_meta( $user_id, 'aio_login_pro__2fa_lockout_until' );
			delete_user_meta( $user_id, 'aio_login_pro__2fa_failed_attempts' );
			return false;
		}

		private function clear_otp_attempt_state( $user_id ) {
			delete_user_meta( $user_id, 'aio_login_pro__2fa_failed_attempts' );
			delete_user_meta( $user_id, 'aio_login_pro__2fa_lockout_until' );
			delete_user_meta( $user_id, 'aio_login_pro__totp_attempts' );
		}

		private function record_failed_otp_attempt( $user_id ) {
			$cfg    = $this->get_otp_lockout_settings( $user_id );
			$failed = (int) get_user_meta( $user_id, 'aio_login_pro__2fa_failed_attempts', true );
			$failed++;
			update_user_meta( $user_id, 'aio_login_pro__2fa_failed_attempts', $failed );
			if ( $failed >= (int) $cfg['max_attempts'] ) {
				update_user_meta( $user_id, 'aio_login_pro__2fa_lockout_until', time() + ( (int) $cfg['duration_minutes'] * MINUTE_IN_SECONDS ) );
				$this->log_otp_lockout_event();
				$this->wp_error->add(
					'otp_locked',
					sprintf( esc_html__( 'Too many OTP attempts. Try again in %d minute(s).', 'change-wp-admin-login' ), (int) $cfg['duration_minutes'] )
				);
			}
		}

		private function log_otp_lockout_event() {
			if ( ! class_exists( '\AIO_Login\Login_Controller\Failed_Logins' ) ) {
				return;
			}
			$ip      = class_exists( '\AIO_Login\Helper\Helper' ) ? \AIO_Login\Helper\Helper::get_ip() : '';
			$country = '';
			$city    = '';
			if ( class_exists( '\AIO_Login\Helper\Helper' ) ) {
				$loc     = \AIO_Login\Helper\Helper::get_location( $ip );
				$country = isset( $loc['country'] ) ? (string) $loc['country'] : '';
				$city    = isset( $loc['city'] ) ? (string) $loc['city'] : '';
			}
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'UNKNOWN';

			\AIO_Login\Login_Controller\Failed_Logins::log_blocked_user(
				array(
					'ip_address' => $ip,
					'country'    => $country,
					'city'       => $city,
					'time'       => current_time( 'timestamp' ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
					'user_agent' => $user_agent,
				)
			);
		}

		public function login_user( $user = null ) {
			$user_id = 0;
			if ( $user instanceof \WP_User ) {
				$this->current_user = $user;
				$user_id            = (int) $user->ID;
			} elseif ( is_numeric( $user ) && (int) $user > 0 ) {
				$user_id = (int) $user;
			} elseif ( $this->current_user instanceof \WP_User ) {
				$user_id = (int) $this->current_user->ID;
			} elseif ( is_numeric( $this->current_user ) && (int) $this->current_user > 0 ) {
				$user_id = (int) $this->current_user;
			}

			if ( $user_id <= 0 ) {
				$user_id = (int) get_current_user_id();
			}
			if ( $user_id <= 0 ) {
				return;
			}
			if ( ! ( $this->current_user instanceof \WP_User ) || (int) $this->current_user->ID !== $user_id ) {
				$this->current_user = get_user_by( 'id', $user_id );
				if ( ! ( $this->current_user instanceof \WP_User ) ) {
					return;
				}
			}

			$cookie_name = 'bid_' . get_current_blog_id() . '_aio_login_pro__two_factor_auth';
			$user_hash   = $this->get_user_hash();
			$hash        = $user_id . '|' . $user_hash;

			$aio_login_cookie_expire = apply_filters( 'aio_login_pro__two_factor_expire', 14 * DAY_IN_SECONDS );

			setcookie( $cookie_name, $hash, time() + $aio_login_cookie_expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			if ( SITECOOKIEPATH !== COOKIEPATH ) {
				setcookie( $cookie_name, $hash, time() + $aio_login_cookie_expire, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			}
			// Same-request login_redirect / template_redirect checks read $_COOKIE, not just Set-Cookie.
			$_COOKIE[ $cookie_name ] = $hash;
		}

		private function get_user_hash() {
			if ( $this->current_user instanceof \WP_User ) {
				$user_id = (int) $this->current_user->ID;
			} elseif ( is_numeric( $this->current_user ) && (int) $this->current_user > 0 ) {
				$user_id            = (int) $this->current_user;
				$this->current_user = get_user_by( 'id', $user_id );
			} else {
				$user_id = (int) get_current_user_id();
				if ( $user_id > 0 ) {
					$this->current_user = get_user_by( 'id', $user_id );
				}
			}

			if ( $user_id <= 0 ) {
				return '';
			}

			$hash = get_user_meta( $user_id, 'aio_login_pro__user_logged_in_token', true );
			if ( empty( $hash ) ) {
				$hash = wp_generate_password( 32, false );
				update_user_meta( $user_id, 'aio_login_pro__user_logged_in_token', $hash );
			}

			return $hash;
		}

		public function is_user_logged_in() {
			global $blog_id;
			$cookie_name = 'bid_' . $blog_id . '_aio_login_pro__two_factor_auth';

			$cookie = false;
			if ( isset( $_COOKIE[ $cookie_name ] ) ) {
				$cookie = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
			}

			if ( ! $cookie ) {
				return false;
			}

			$cookie = explode( '|', $cookie );
			if ( count( $cookie ) !== 2 ) {
				return false;
			}

			list( $user_id, $hash ) = $cookie;

			if ( absint( $user_id ) !== absint( $this->current_user->ID ) ) {
				return false;
			}

			$user_secret = $this->get_user_hash();
			if ( empty( $user_secret ) ) {
				return false;
			}

			if ( $hash !== $user_secret ) {
				return false;
			}

			return true;
		}

		public static function get_verification_url( $redirect_to = '' ) {
			$args = array(
				'action' => 'aio_login_pro__two_factor_auth',
			);

			if ( ! empty( $redirect_to ) ) {
				$args['redirect_to'] = wp_validate_redirect( $redirect_to, admin_url() );
			}

			return add_query_arg(
				$args,
				wp_login_url()
			);
		}

		public function maybe_redirect_to_tfa( $redirect_to, $requested_redirect_to, $user ) {
			if ( ! ( $user instanceof \WP_User ) ) {
				return $redirect_to;
			}

			$this->current_user = $user;
			if ( ! $this->is_active() ) {
				return $redirect_to;
			}
			if ( $this->is_user_logged_in() ) {
				return $redirect_to;
			}

			$this->maybe_issue_email_otp();

			return self::get_verification_url( $redirect_to );
		}

		/**
		 * Force OTP challenge if the user is WP-authenticated but has not completed 2FA.
		 * Covers Login Redirection (and similar) sending users to frontend/admin without the OTP URL.
		 *
		 * @return void
		 */
		public function maybe_force_pending_otp_challenge() {
			if ( ! is_user_logged_in() ) {
				return;
			}
			if ( wp_doing_cron() ) {
				return;
			}
			// AJAX / REST handled by dedicated blockers — avoid query-param bypasses here.
			if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				return;
			}

			$user = wp_get_current_user();
			if ( ! ( $user instanceof \WP_User ) || $user->ID <= 0 ) {
				return;
			}

			$this->current_user = $user;
			if ( ! $this->user_has_pending_login_challenge( $user ) ) {
				return;
			}

			if ( $this->is_allowed_pending_otp_request() ) {
				return;
			}

			$dest = '';
			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$dest = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
			$dest = wp_validate_redirect( $dest, admin_url() );

			wp_safe_redirect( self::get_verification_url( $dest ) );
			exit;
		}

		/**
		 * Block admin-ajax while Email OTP is pending (except logout).
		 *
		 * @return void
		 */
		public function maybe_block_pending_otp_ajax() {
			if ( ! wp_doing_ajax() || ! is_user_logged_in() ) {
				return;
			}

			$user = wp_get_current_user();
			if ( ! ( $user instanceof \WP_User ) || $user->ID <= 0 ) {
				return;
			}

			$this->current_user = $user;
			if ( ! $this->user_has_pending_login_challenge( $user ) ) {
				return;
			}

			// Logout must go through wp-login.php?action=logout&_wpnonce=… — never via admin-ajax.
			wp_send_json_error(
				array(
					'code'    => 'aio_login_2fa_required',
					'message' => __( 'Two-factor authentication is required before continuing.', 'change-wp-admin-login' ),
				),
				403
			);
		}

		/**
		 * Block cookie-authenticated REST while Email OTP is pending.
		 *
		 * @param mixed $result Existing auth result.
		 * @return mixed|\WP_Error
		 */
		public function maybe_block_pending_otp_rest( $result ) {
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( ! is_user_logged_in() ) {
				return $result;
			}

			$user = wp_get_current_user();
			if ( ! ( $user instanceof \WP_User ) || $user->ID <= 0 ) {
				return $result;
			}

			$this->current_user = $user;
			if ( ! $this->user_has_pending_login_challenge( $user ) ) {
				return $result;
			}

			return new \WP_Error(
				'aio_login_2fa_required',
				__( 'Two-factor authentication is required before continuing.', 'change-wp-admin-login' ),
				array( 'status' => 403 )
			);
		}

		/**
		 * Requests allowed while OTP is still pending (challenge screen + real logout only).
		 *
		 * @return bool
		 */
		private function is_allowed_pending_otp_request() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

			// Core logout only: wp-login.php?action=logout with a valid log-out nonce.
			if ( 'logout' === $action && $this->is_wp_login_request() && $this->is_valid_logout_request() ) {
				return true;
			}

			// Only the real OTP challenge on wp-login.php — not arbitrary pages with ?action=…
			if ( 'aio_login_pro__two_factor_auth' !== $action ) {
				return false;
			}

			return $this->is_wp_login_request();
		}

		/**
		 * Whether the current request is served by wp-login.php.
		 *
		 * @return bool
		 */
		private function is_wp_login_request() {
			$script = isset( $_SERVER['SCRIPT_NAME'] ) ? (string) wp_unslash( $_SERVER['SCRIPT_NAME'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( '' !== $script && false !== strpos( $script, 'wp-login.php' ) ) {
				return true;
			}

			return isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'];
		}

		/**
		 * Validate WordPress core logout nonce (same action as wp_nonce_url( …, 'log-out' )).
		 *
		 * @return bool
		 */
		private function is_valid_logout_request() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			return '' !== $nonce && (bool) wp_verify_nonce( $nonce, 'log-out' );
		}

		private function is_email_otp_enabled() {
			return 'on' === get_option( 'aio_login_pro__2fa_email_otp_enable', 'off' );
		}

		private function maybe_issue_email_otp() {
			if ( ! ( $this->current_user instanceof \WP_User ) ) {
				return;
			}
			$user_id = (int) $this->current_user->ID;
			$now     = time();

			$existing_hash = (string) get_user_meta( $user_id, 'aio_login_pro__email_otp_hash', true );
			$expires_at    = (int) get_user_meta( $user_id, 'aio_login_pro__email_otp_expires', true );
			$last_sent     = (int) get_user_meta( $user_id, 'aio_login_pro__email_otp_last_sent', true );
			if ( ! empty( $existing_hash ) && $expires_at > $now ) {
				return;
			}

			$email_settings = get_option( 'aio_login_pro__2fa_email_settings', array() );
			$cc_email       = '';

			if ( user_can( $user_id, 'manage_options' ) ) {
				$admin_email = isset( $email_settings['admin_notification_email'] ) ? sanitize_email( $email_settings['admin_notification_email'] ) : '';
				$recipient   = ! empty( $admin_email ) ? $admin_email : $this->current_user->user_email;
			} else {
				$user_recipient = sanitize_email( (string) get_user_meta( $user_id, 'aio_login_pro__2fa_email_recipient', true ) );
				$user_cc        = sanitize_email( (string) get_user_meta( $user_id, 'aio_login_pro__2fa_email_cc', true ) );
				$recipient      = ! empty( $user_recipient ) ? $user_recipient : $this->current_user->user_email;
				$cc_email       = $user_cc;
			}

			if ( empty( $recipient ) || ! is_email( $recipient ) ) {
				$this->wp_error->add( 'email_otp_failed', esc_html__( 'Invalid OTP recipient email. Please update Email One-Time Code settings.', 'change-wp-admin-login' ) );
				return;
			}

			$otp        = (string) wp_rand( 100000, 999999 );
			$otp_expiry = isset( $email_settings['otp_expiry'] ) ? max( 1, absint( $email_settings['otp_expiry'] ) ) : 5;
			$subject    = isset( $email_settings['email_subject'] ) ? sanitize_text_field( $email_settings['email_subject'] ) : __( 'Your One-Time Verification Code', 'change-wp-admin-login' );
			$template   = isset( $email_settings['email_template'] ) ? (string) $email_settings['email_template'] : '';
			if ( empty( $template ) ) {
				$template = "Hello {user_name},\n\nYour verification code is: {code}\n\nThis code will expire in {expiry} minutes.\n\nIf you did not request this code, please ignore this email.\n\nThanks,\n{site_name}\n{site_url}";
			}

			$body = str_replace(
				array( '{user_name}', '{code}', '{expiry}', '{site_name}', '{site_url}' ),
				array(
					$this->current_user->display_name,
					$otp,
					(string) $otp_expiry,
					wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					home_url(),
				),
				$template
			);

			$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
			if ( ! empty( $cc_email ) && is_email( $cc_email ) ) {
				$headers[] = 'Cc: ' . $cc_email;
			}
			$sender_name  = isset( $email_settings['sender_name'] ) ? sanitize_text_field( $email_settings['sender_name'] ) : '';
			$sender_email = isset( $email_settings['sender_email'] ) ? sanitize_email( $email_settings['sender_email'] ) : '';
			$from_name    = ! empty( $sender_name ) ? $sender_name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$from_email   = ( ! empty( $sender_email ) && is_email( $sender_email ) ) ? $sender_email : '';
			if ( empty( $from_email ) ) {
				$from_email = sanitize_email( get_option( 'admin_email', '' ) );
			}
			if ( empty( $from_email ) || ! is_email( $from_email ) ) {
				$domain     = wp_parse_url( home_url(), PHP_URL_HOST );
				$domain     = $domain ? $domain : 'localhost';
				$from_email = 'wordpress@' . $domain;
			}
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );

			$sent = wp_mail( $recipient, $subject, $body, $headers );
			if ( ! $sent ) {
				$retry_headers = array( 'Content-Type: text/plain; charset=UTF-8' );
				if ( ! empty( $cc_email ) && is_email( $cc_email ) ) {
					$retry_headers[] = 'Cc: ' . $cc_email;
				}
				$retry_from_mail = sanitize_email( get_option( 'admin_email', '' ) );
				if ( ! empty( $retry_from_mail ) && is_email( $retry_from_mail ) ) {
					$retry_headers[] = sprintf( 'From: %s <%s>', $from_name, $retry_from_mail );
				} else {
					$retry_headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
				}
				$sent = wp_mail( $recipient, $subject, $body, $retry_headers );
			}

			if ( ! $sent ) {
				$this->wp_error->add(
					'email_otp_failed',
					sprintf(
						/* translators: %s recipient email */
						esc_html__( 'Unable to send OTP email to %s. Please verify email settings/SMTP and try again.', 'change-wp-admin-login' ),
						esc_html( $recipient )
					)
				);
				return;
			}

			update_user_meta( $user_id, 'aio_login_pro__email_otp_hash', wp_hash_password( $otp ) );
			update_user_meta( $user_id, 'aio_login_pro__email_otp_expires', $now + ( $otp_expiry * MINUTE_IN_SECONDS ) );
			update_user_meta( $user_id, 'aio_login_pro__email_otp_attempts', 0 );
			update_user_meta( $user_id, 'aio_login_pro__email_otp_last_sent', $now );
		}

		private function maybe_handle_email_otp_resend() {
			if ( empty( $_GET['resend_otp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'aio_login_pro_resend_otp' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->wp_error->add( 'email_otp_resend_nonce', esc_html__( 'Invalid resend request. Please try again.', 'change-wp-admin-login' ) );
				return;
			}

			$user_id          = (int) $this->current_user->ID;
			$email_settings   = get_option( 'aio_login_pro__2fa_email_settings', array() );
			$resend_cooldown  = isset( $email_settings['resend_cooldown'] ) ? max( 0, absint( $email_settings['resend_cooldown'] ) ) : 30;
			$last_sent        = (int) get_user_meta( $user_id, 'aio_login_pro__email_otp_last_sent', true );
			if ( $resend_cooldown > 0 && ( time() - $last_sent ) < $resend_cooldown ) {
				$wait = $resend_cooldown - ( time() - $last_sent );
				$this->wp_error->add(
					'email_otp_resend_wait',
					sprintf(
						/* translators: %d seconds */
						esc_html__( 'Please wait %d second(s) before requesting another code.', 'change-wp-admin-login' ),
						max( 1, $wait )
					)
				);
				return;
			}

			delete_user_meta( $user_id, 'aio_login_pro__email_otp_hash' );
			delete_user_meta( $user_id, 'aio_login_pro__email_otp_expires' );
			$this->maybe_issue_email_otp();

			$redirect_to = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), admin_url() ) : admin_url(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect(
				add_query_arg(
					array(
						'action'     => 'aio_login_pro__two_factor_auth',
						'resent_otp' => '1',
						'redirect_to'=> $redirect_to,
					),
					wp_login_url()
				)
			);
			exit;
		}

		private function verify_email_otp( $otp ) {
			$user_id    = (int) $this->current_user->ID;
			$hash       = (string) get_user_meta( $user_id, 'aio_login_pro__email_otp_hash', true );
			$expires_at = (int) get_user_meta( $user_id, 'aio_login_pro__email_otp_expires', true );
			$attempts   = (int) get_user_meta( $user_id, 'aio_login_pro__email_otp_attempts', true );

			if ( empty( $hash ) || $expires_at < time() ) {
				$this->wp_error->add( 'email_otp_expired', esc_html__( 'OTP expired. Please login again to receive a new code.', 'change-wp-admin-login' ) );
				return false;
			}

			if ( ! wp_check_password( (string) $otp, $hash ) ) {
				$attempts++;
				update_user_meta( $user_id, 'aio_login_pro__email_otp_attempts', $attempts );
				return false;
			}

			delete_user_meta( $user_id, 'aio_login_pro__email_otp_hash' );
			delete_user_meta( $user_id, 'aio_login_pro__email_otp_expires' );
			delete_user_meta( $user_id, 'aio_login_pro__email_otp_attempts' );
			delete_user_meta( $user_id, 'aio_login_pro__email_otp_last_sent' );
			return true;
		}

		public function display_errors() {
			if ( $this->wp_error->has_errors() ) {
				foreach ( $this->wp_error->get_error_messages() as $message ) {
					echo '<div id="login_error"><p>' . wp_kses_post( $message ) . '</p></div>';
				}
			}
		}

		public function two_factor_authentication_logout() {
			$cookie_name = 'bid_' . get_current_blog_id() . '_aio_login_pro__two_factor_auth';
			if ( isset( $_COOKIE[ $cookie_name ] ) ) {
				setcookie( $cookie_name, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
				if ( SITECOOKIEPATH !== COOKIEPATH ) {
					setcookie( $cookie_name, '', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
				}
			}
		}

		public function hide_admin_bar( $show_admin_bar ) {
			if ( ! is_user_logged_in() ) {
				return $show_admin_bar;
			}
			$this->current_user = wp_get_current_user();
			if ( $this->is_active() && ! $this->is_user_logged_in() ) {
				return false;
			}
			return $show_admin_bar;
		}

		public function send_profile_setup_email_otp( $user_id ) {
			$user_id = (int) $user_id;
			$user    = get_userdata( $user_id );
			if ( ! $user ) {
				return new \WP_Error( 'aio_login_pro__error', esc_html__( 'Invalid user.', 'change-wp-admin-login' ), array( 'status' => 400 ) );
			}

			if ( ! Two_Factor::is_2fa_module_master_enabled() && ! $this->is_email_otp_enabled() ) {
				return new \WP_Error( 'aio_login_pro__error', esc_html__( 'Email OTP is not available.', 'change-wp-admin-login' ), array( 'status' => 400 ) );
			}

			$email_settings  = get_option( 'aio_login_pro__2fa_email_settings', array() );
			$resend_cooldown = isset( $email_settings['resend_cooldown'] ) ? max( 0, absint( $email_settings['resend_cooldown'] ) ) : 30;
			$last_sent       = (int) get_user_meta( $user_id, 'aio_login_pro__profile_email_otp_last_sent', true );
			if ( $resend_cooldown > 0 && ( time() - $last_sent ) < $resend_cooldown ) {
				$wait = $resend_cooldown - ( time() - $last_sent );
				return new \WP_Error(
					'aio_login_pro__error',
					sprintf(
						/* translators: %d seconds */
						esc_html__( 'Please wait %d second(s) before requesting another code.', 'change-wp-admin-login' ),
						max( 1, $wait )
					),
					array( 'status' => 429 )
				);
			}

			$cc_email = '';
			if ( user_can( $user_id, 'manage_options' ) ) {
				$admin_email = isset( $email_settings['admin_notification_email'] ) ? sanitize_email( $email_settings['admin_notification_email'] ) : '';
				$recipient   = ! empty( $admin_email ) ? $admin_email : $user->user_email;
			} else {
				$user_recipient = sanitize_email( (string) get_user_meta( $user_id, 'aio_login_pro__2fa_email_recipient', true ) );
				$user_cc        = sanitize_email( (string) get_user_meta( $user_id, 'aio_login_pro__2fa_email_cc', true ) );
				$recipient      = ! empty( $user_recipient ) ? $user_recipient : $user->user_email;
				$cc_email       = $user_cc;
			}

			if ( empty( $recipient ) || ! is_email( $recipient ) ) {
				return new \WP_Error( 'aio_login_pro__error', esc_html__( 'Invalid OTP recipient email. Please update Email One-Time Code settings.', 'change-wp-admin-login' ), array( 'status' => 400 ) );
			}

			$otp        = (string) wp_rand( 100000, 999999 );
			$otp_expiry = isset( $email_settings['otp_expiry'] ) ? max( 1, absint( $email_settings['otp_expiry'] ) ) : 5;
			$subject    = isset( $email_settings['email_subject'] ) ? sanitize_text_field( $email_settings['email_subject'] ) : __( 'Your One-Time Verification Code', 'change-wp-admin-login' );
			$template   = isset( $email_settings['email_template'] ) ? (string) $email_settings['email_template'] : '';
			if ( empty( $template ) ) {
				$template = "Hello {user_name},\n\nYour verification code is: {code}\n\nThis code will expire in {expiry} minutes.\n\nIf you did not request this code, please ignore this email.\n\nThanks,\n{site_name}\n{site_url}";
			}

			$body = str_replace(
				array( '{user_name}', '{code}', '{expiry}', '{site_name}', '{site_url}' ),
				array(
					$user->display_name,
					$otp,
					(string) $otp_expiry,
					wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					home_url(),
				),
				$template
			);

			$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
			if ( ! empty( $cc_email ) && is_email( $cc_email ) ) {
				$headers[] = 'Cc: ' . $cc_email;
			}
			$sender_name  = isset( $email_settings['sender_name'] ) ? sanitize_text_field( $email_settings['sender_name'] ) : '';
			$sender_email = isset( $email_settings['sender_email'] ) ? sanitize_email( $email_settings['sender_email'] ) : '';
			$from_name    = ! empty( $sender_name ) ? $sender_name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$from_email   = ( ! empty( $sender_email ) && is_email( $sender_email ) ) ? $sender_email : sanitize_email( get_option( 'admin_email', '' ) );
			if ( empty( $from_email ) || ! is_email( $from_email ) ) {
				$domain     = wp_parse_url( home_url(), PHP_URL_HOST );
				$from_email = 'wordpress@' . ( $domain ? $domain : 'localhost' );
			}
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );

			$sent = wp_mail( $recipient, $subject, $body, $headers );
			if ( ! $sent ) {
				return new \WP_Error(
					'aio_login_pro__error',
					sprintf(
						/* translators: %s recipient email */
						esc_html__( 'Unable to send OTP email to %s. Please verify email settings/SMTP and try again.', 'change-wp-admin-login' ),
						esc_html( $recipient )
					),
					array( 'status' => 500 )
				);
			}

			$now = time();
			update_user_meta( $user_id, 'aio_login_pro__profile_email_otp_hash', wp_hash_password( $otp ) );
			update_user_meta( $user_id, 'aio_login_pro__profile_email_otp_expires', $now + ( $otp_expiry * MINUTE_IN_SECONDS ) );
			update_user_meta( $user_id, 'aio_login_pro__profile_email_otp_attempts', 0 );
			update_user_meta( $user_id, 'aio_login_pro__profile_email_otp_last_sent', $now );

			return true;
		}

		public function verify_profile_setup_email_otp( $user_id, $otp ) {
			$user_id = (int) $user_id;
			$hash    = (string) get_user_meta( $user_id, 'aio_login_pro__profile_email_otp_hash', true );
			$expires = (int) get_user_meta( $user_id, 'aio_login_pro__profile_email_otp_expires', true );
			$attempts = (int) get_user_meta( $user_id, 'aio_login_pro__profile_email_otp_attempts', true );
			$email_settings = get_option( 'aio_login_pro__2fa_email_settings', array() );
			$max_attempts   = isset( $email_settings['max_otp_attempts'] ) ? max( 1, min( 50, absint( $email_settings['max_otp_attempts'] ) ) ) : 5;

			if ( empty( $hash ) || $expires < time() ) {
				return new \WP_Error( 'aio_login_pro__error', esc_html__( 'OTP expired. Please request a new code.', 'change-wp-admin-login' ), array( 'status' => 400 ) );
			}

			if ( ! wp_check_password( (string) $otp, $hash ) ) {
				$attempts++;
				update_user_meta( $user_id, 'aio_login_pro__profile_email_otp_attempts', $attempts );
				$remaining = max( 0, $max_attempts - $attempts );
				return new \WP_Error(
					'aio_login_pro__error',
					sprintf( esc_html__( 'Invalid code. %d attempt(s) remaining.', 'change-wp-admin-login' ), $remaining ),
					array( 'status' => 400 )
				);
			}

			delete_user_meta( $user_id, 'aio_login_pro__profile_email_otp_hash' );
			delete_user_meta( $user_id, 'aio_login_pro__profile_email_otp_expires' );
			delete_user_meta( $user_id, 'aio_login_pro__profile_email_otp_attempts' );
			delete_user_meta( $user_id, 'aio_login_pro__profile_email_otp_last_sent' );

			update_user_meta( $user_id, 'aio_login_pro__2fa_user_method', 'email' );
			delete_user_meta( $user_id, 'aio_login_pro__user_secret' );
			update_user_meta( $user_id, 'aio_login_pro__two_factor_auth_enable', 'off' );

			return true;
		}

		public function is_aio_2fa_cookie_valid_for_user( $user_id ) {
			$user_id = (int) $user_id;
			if ( $user_id <= 0 ) {
				return false;
			}
			$user = get_userdata( $user_id );
			if ( ! $user instanceof \WP_User ) {
				return false;
			}
			$prev               = $this->current_user;
			$this->current_user = $user;
			$ok                 = $this->is_user_logged_in();
			$this->current_user = $prev;
			return $ok;
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
