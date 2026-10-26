<?php
/**
 * Passwordless OTP option keys and defaults.
 *
 * Email OTP lives in Free. SMS/Twilio option readers remain as thin bridges so
 * Pro can reuse shared helpers when active (Guideline 5 / AIOL-745).
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

use AIO_Login\Multisite\Network_Settings;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Settings' ) ) {
	/**
	 * OTP_Settings
	 */
	final class OTP_Settings {

		public const NONCE_ACTION = 'aio-login-passwordless-otp';

		/**
		 * Whether SMS OTP is enabled in saved settings (ignores Pro availability).
		 *
		 * @return bool
		 */
		public static function is_sms_enabled_in_settings() {
			return 'on' === Network_Settings::get_option( 'aio_login_otp_sms_enable', 'off' );
		}

		/**
		 * Persist SMS OTP as disabled in settings (admin explicitly turns it off).
		 */
		public static function disable_sms_channel() {
			Network_Settings::update_option( 'aio_login_otp_sms_enable', 'off' );
		}

		/**
		 * Default email body template.
		 *
		 * @return string
		 */
		public static function default_email_body() {
			return "Hello {{display_name}},\n\nYour One-Time Password (OTP) for secure login is:\n\n{{otp_code}}\n\nThis verification code will expire in {{expiration_time}} minutes.\n\nIf you did not request this code, please ignore this email. No changes will be made to your account.\n\nDevice Information:\n{{device_info}}\n\nFor security reasons, do not share this OTP with anyone.\n\nRegards,\n{{site_name}}\n{{site_url}}";
		}

		/**
		 * Set activation defaults (email OTP only — SMS defaults live in Pro).
		 */
		public static function set_defaults() {
			$defaults = array(
				'aio_login_otp_email_enable'         => 'off',
				'aio_login_otp_email_length'         => '6',
				'aio_login_otp_email_expiration'     => '10',
				'aio_login_otp_email_resend_timer'   => '60',
				'aio_login_otp_email_max_retries'    => '5',
				'aio_login_otp_email_block_duration' => '15',
				'aio_login_otp_email_skip_2fa'       => 'on',
			);

			foreach ( $defaults as $key => $value ) {
				if ( false === get_option( $key, false ) ) {
					add_option( $key, $value, '', false );
				}
			}

			self::maybe_raise_default_otp_length();
		}

		/**
		 * One-time security hardening for in-place upgrades
		 */
		public static function apply_passwordless_upgrade_hardening() {
			if ( '1' !== get_option( 'aio_login_otp_upgrade_hardened_241', '' ) ) {
				$email_on                  = 'on' === get_option( 'aio_login_otp_email_enable', 'off' );
				$length_4                  = '4' === (string) get_option( 'aio_login_otp_email_length', '6' );
				$insecure_factory_defaults = $email_on && $length_4;

				self::maybe_raise_default_otp_length();

				if ( $insecure_factory_defaults ) {
					update_option( 'aio_login_otp_email_enable', 'off', false );
				}

				update_option( 'aio_login_otp_upgrade_hardened_241', '1', false );
			}

			self::force_minimum_otp_length_six();
		}

		/**
		 * 4-digit OTP is no longer allowed (AIOL-697).
		 */
		public static function force_minimum_otp_length_six() {
			foreach ( array( 'aio_login_otp_email_length', 'aio_login_otp_sms_length' ) as $key ) {
				$current = (string) get_option( $key, '6' );
				if ( '4' === $current || '' === $current || ! in_array( $current, array( '6', '8' ), true ) ) {
					update_option( $key, '6', false );
				}
			}
		}

		/**
		 * One-time bump of shipped 4-digit OTP default to 6 (AIOL-690).
		 */
		public static function maybe_raise_default_otp_length() {
			if ( '1' === get_option( 'aio_login_otp_length_hardened_6', '' ) ) {
				return;
			}

			foreach ( array( 'aio_login_otp_email_length', 'aio_login_otp_sms_length' ) as $key ) {
				$current = (string) get_option( $key, '6' );
				if ( '4' === $current || '' === $current ) {
					update_option( $key, '6', false );
				}
			}

			update_option( 'aio_login_otp_length_hardened_6', '1', false );
		}

		/**
		 * @param string $channel email|sms.
		 * @return bool
		 */
		public static function is_channel_enabled( $channel ) {
			if ( 'email' === $channel ) {
				return 'on' === Network_Settings::get_option( 'aio_login_otp_email_enable', 'off' );
			}
			if ( 'sms' === $channel ) {
				if ( ! \AIO_Login\AIO_Login::has_pro() ) {
					return false;
				}
				return 'on' === Network_Settings::get_option( 'aio_login_otp_sms_enable', 'off' );
			}
			return false;
		}

		/**
		 * @param string $channel email|sms.
		 * @return int
		 */
		public static function get_otp_length( $channel ) {
			$key    = 'email' === $channel ? 'aio_login_otp_email_length' : 'aio_login_otp_sms_length';
			$length = absint( get_option( $key, 6 ) );
			if ( ! in_array( $length, array( 6, 8 ), true ) ) {
				$length = 6;
			}
			return $length;
		}

		/**
		 * @param string $channel email|sms.
		 * @return int Minutes.
		 */
		public static function get_expiration_minutes( $channel ) {
			$key = 'email' === $channel ? 'aio_login_otp_email_expiration' : 'aio_login_otp_sms_expiration';
			return max( 1, min( 60, absint( Network_Settings::get_option( $key, 10 ) ) ) );
		}

		/**
		 * @param string $channel email|sms.
		 * @return int Seconds.
		 */
		public static function get_resend_seconds( $channel ) {
			$key = 'email' === $channel ? 'aio_login_otp_email_resend_timer' : 'aio_login_otp_sms_resend_timer';
			return max( 30, min( 600, absint( Network_Settings::get_option( $key, 60 ) ) ) );
		}

		/**
		 * @param string $channel email|sms.
		 * @return int
		 */
		public static function get_max_retries( $channel ) {
			$key = 'email' === $channel ? 'aio_login_otp_email_max_retries' : 'aio_login_otp_sms_max_retries';
			return max( 1, min( 20, absint( Network_Settings::get_option( $key, 5 ) ) ) );
		}

		/**
		 * Max OTP send/resend requests per IP before lockout (15-minute window, not configurable in admin).
		 */
		public static function get_max_send_requests() {
			return 5;
		}

		/**
		 * Whether successful OTP login for this channel should bypass AIO Login 2FA.
		 *
		 * @param string $channel email|sms.
		 */
		public static function should_skip_two_factor( $channel ) {
			$channel = 'sms' === $channel ? 'sms' : 'email';
			$key     = 'email' === $channel ? 'aio_login_otp_email_skip_2fa' : 'aio_login_otp_sms_skip_2fa';
			return 'on' === Network_Settings::get_option( $key, 'on' );
		}

		/**
		 * IP block duration after too many OTP attempts (minutes).
		 *
		 * @param string $channel email|sms.
		 */
		public static function get_block_duration_minutes( $channel = 'email' ) {
			$channel = 'sms' === $channel ? 'sms' : 'email';
			$key     = 'email' === $channel ? 'aio_login_otp_email_block_duration' : 'aio_login_otp_sms_block_duration';

			$legacy   = absint( get_option( 'aio_login_otp_block_duration', 0 ) );
			$fallback = $legacy > 0 ? $legacy : 15;

			return max( 1, min( 1440, absint( Network_Settings::get_option( $key, $fallback ) ) ) );
		}

		/**
		 * Admin settings payload for REST (email + Pro capability stub).
		 * Pro merges SMS/Twilio fields via `aio_login_otp_admin_settings`.
		 *
		 * @return array<string, mixed>
		 */
		public static function get_admin_settings() {
			$settings = array(
				'nonce'                => wp_create_nonce( self::NONCE_ACTION ),
				'email_enable'         => 'on' === Network_Settings::get_option( 'aio_login_otp_email_enable', 'off' ),
				'email_block_duration' => (string) self::get_block_duration_minutes( 'email' ),
				'email_length'         => (string) self::get_otp_length( 'email' ),
				'email_expiration'     => (string) self::get_expiration_minutes( 'email' ),
				'email_resend_timer'   => (string) self::get_resend_seconds( 'email' ),
				'email_max_retries'    => (string) self::get_max_retries( 'email' ),
				'email_skip_2fa'       => self::should_skip_two_factor( 'email' ),
				'has_pro'              => \AIO_Login\AIO_Login::has_pro(),
				'email_network'        => Network_Settings::get_feature_context( 'email_otp' ),
				// SMS stubs so Free Vue does not break when Pro inactive; Pro overwrites via filter.
				'sms_enable'           => false,
				'sms_active'           => false,
				'sms_length'           => '6',
				'sms_expiration'       => '10',
				'sms_resend_timer'     => '60',
				'sms_max_retries'      => '5',
				'sms_block_duration'   => '15',
				'sms_skip_2fa'         => true,
				'twilio_account_sid'   => '',
				'twilio_auth_token'    => '',
				'twilio_auth_token_stored' => false,
				'twilio_sender_number' => '',
				'sms_default_country'  => '',
				'sms_default_country_iso' => '',
				'sms_allowed_countries' => array(),
				'country_codes'        => array(),
				'sms_network'          => array( 'can_edit' => false ),
			);

			/**
			 * Filter admin OTP settings payload (Pro merges SMS/Twilio fields).
			 *
			 * @param array<string, mixed> $settings Settings.
			 */
			return apply_filters( 'aio_login_otp_admin_settings', $settings );
		}

		/**
		 * Common country dial codes for SMS UI (loaded by Pro).
		 *
		 * @return array<int, array{code:string,label:string,iso:string}>
		 */
		public static function get_country_codes() {
			if ( class_exists( __NAMESPACE__ . '\\OTP_Country_Codes' ) ) {
				return OTP_Country_Codes::all();
			}
			return array();
		}

		/**
		 * Sanitize allowed-country ISO list from option or request payload.
		 *
		 * @param mixed $raw Raw value.
		 * @return string[]
		 */
		public static function sanitize_sms_allowed_country_isos( $raw ) {
			if ( ! is_array( $raw ) ) {
				return array();
			}

			$isos = array();
			foreach ( $raw as $iso ) {
				$iso = strtoupper( sanitize_text_field( (string) $iso ) );
				if ( preg_match( '/^[A-Z]{2}$/', $iso ) ) {
					$isos[] = $iso;
				}
			}

			return array_values( array_unique( $isos ) );
		}

		/**
		 * Allowed SMS country ISO codes from settings.
		 *
		 * @return string[]
		 */
		public static function get_sms_allowed_country_isos() {
			$allowed = get_option( 'aio_login_otp_sms_allowed_countries', '[]' );
			if ( is_string( $allowed ) ) {
				$allowed = json_decode( $allowed, true );
			}

			return self::sanitize_sms_allowed_country_isos( $allowed );
		}

		/**
		 * Country list for wp-login SMS picker (filtered by allowed countries).
		 *
		 * @return array<int, array{code:string,label:string,iso:string}>
		 */
		public static function get_login_country_codes() {
			$all     = self::get_country_codes();
			$allowed = self::get_sms_allowed_country_isos();
			if ( empty( $allowed ) ) {
				return array();
			}

			$set = array_fill_keys( $allowed, true );
			$out = array();
			foreach ( $all as $country ) {
				if ( isset( $set[ $country['iso'] ] ) ) {
					$out[] = $country;
				}
			}

			return $out;
		}

		/**
		 * Default SMS country ISO (unique; dial codes like +1 are shared by multiple countries).
		 *
		 * @return string ISO 3166-1 alpha-2.
		 */
		public static function get_sms_default_country_iso() {
			$iso = strtoupper( (string) Network_Settings::get_option( 'aio_login_otp_sms_default_country_iso', '' ) );

			if ( preg_match( '/^[A-Z]{2}$/', $iso ) ) {
				foreach ( self::get_country_codes() as $country ) {
					if ( $country['iso'] === $iso ) {
						return $iso;
					}
				}
			}

			return 'US';
		}

		/**
		 * @param string $iso ISO 3166-1 alpha-2.
		 * @return string Dial code e.g. +1.
		 */
		public static function get_dial_code_for_country_iso( $iso ) {
			$iso = strtoupper( sanitize_text_field( (string) $iso ) );
			foreach ( self::get_country_codes() as $country ) {
				if ( $country['iso'] === $iso ) {
					return $country['code'];
				}
			}

			return '+1';
		}

		/**
		 * Default dial code + ISO for login country select (must be in allowed list).
		 *
		 * @return array{code:string,iso:string}
		 */
		public static function get_login_sms_default_country() {
			$iso   = self::get_sms_default_country_iso();
			$codes = self::get_login_country_codes();

			foreach ( $codes as $country ) {
				if ( $country['iso'] === $iso ) {
					return array(
						'code' => $country['code'],
						'iso'  => $country['iso'],
					);
				}
			}

			if ( ! empty( $codes ) ) {
				return array(
					'code' => $codes[0]['code'],
					'iso'  => $codes[0]['iso'],
				);
			}

			return array(
				'code' => self::get_dial_code_for_country_iso( $iso ),
				'iso'  => $iso,
			);
		}

		/**
		 * Whether SMS passwordless login should appear on wp-login.
		 * Thin bridge: requires Pro + SMS channel enabled + allowed countries.
		 *
		 * @return bool
		 */
		public static function is_sms_login_available() {
			if ( ! \AIO_Login\AIO_Login::has_pro() || ! self::is_channel_enabled( 'sms' ) ) {
				return false;
			}

			return ! empty( self::get_sms_allowed_country_isos() );
		}
	}
}
