<?php
/**
 * Free security controls: IP allow/deny lists + disable common usernames.
 * Guideline 5 — fully usable without Pro / license checks.
 *
 * @package AIO_Login
 */

namespace AIO_Login\Login_Controller;

use AIO_Login\Helper\Helper;
use AIO_Login\Multisite\Network_Settings;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( __NAMESPACE__ . '\\Security_Controls' ) ) {
	/**
	 * Class Security_Controls
	 */
	final class Security_Controls {
		/**
		 * Fallback lockout copy stored when no custom message is configured.
		 *
		 * Keep this as a plain string in constructor-time code so translation APIs
		 * are not invoked before WordPress init.
		 */
		private const DEFAULT_BLOCK_MESSAGE = 'Your IP Address is blocked';

		/**
		 * @var self|null
		 */
		private static $instance = null;

		/**
		 * @var bool
		 */
		private $ip_enabled;

		/**
		 * @var string whitelist|blacklist
		 */
		private $ip_mode;

		/**
		 * @var string
		 */
		private $whitelist_raw;

		/**
		 * @var string
		 */
		private $blacklist_raw;

		/**
		 * @var string
		 */
		private $block_message;

		/**
		 * @return self
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			$this->ip_enabled    = 'on' === get_option( 'aio_login_block_ip_address_enable', 'off' );
			$this->ip_mode       = (string) get_option( 'aio_login_block_ip_address_mode', 'whitelist' );
			$this->whitelist_raw = (string) get_option( 'aio_login_block_ip_address_whitelist', '' );
			$this->blacklist_raw = (string) get_option( 'aio_login_block_ip_address_blacklist', '' );
			$this->block_message = (string) get_option( 'aio_login_block_ip_address_block_message', '' );

			if ( '' === $this->block_message ) {
				$this->block_message = self::DEFAULT_BLOCK_MESSAGE;
			}
			if ( 'blacklist' !== $this->ip_mode ) {
				$this->ip_mode = 'whitelist';
			}

			add_filter( 'aio_login__wp_authenticate_user', array( $this, 'filter_authenticate_by_ip' ) );
			add_filter( 'aio_login__limit_attempts_enabled', array( $this, 'disable_limit_attempts_for_blocked_ip' ) );
			add_filter( 'login_errors', array( $this, 'filter_login_errors' ) );
			add_filter( 'illegal_user_logins', array( $this, 'filter_illegal_usernames' ), 999 );
			add_filter( 'wpmu_validate_user_signup', array( $this, 'dedupe_signup_errors' ), 999 );
			add_filter( 'registration_errors', array( $this, 'dedupe_registration_errors' ), 999 );
			add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		}

		/**
		 * Whether the current visitor IP is blocked by Ban/Whitelist settings.
		 *
		 * @param string $ip Optional IP (defaults to current visitor).
		 * @return bool
		 */
		public function is_ip_blocked( $ip = '' ) {
			if ( ! $this->ip_enabled ) {
				return false;
			}

			$ip = is_string( $ip ) && '' !== $ip ? $ip : Helper::get_ip();
			if ( 'blacklist' === $this->ip_mode ) {
				$list = $this->parse_ip_list( $this->blacklist_raw );
				return (bool) ( $list && in_array( $ip, $list, true ) );
			}

			$list = $this->parse_ip_list( $this->whitelist_raw );
			return (bool) ( $list && ! in_array( $ip, $list, true ) );
		}

		/**
		 * Message shown when an IP is blocked.
		 *
		 * @return string
		 */
		public function get_block_message() {
			if ( self::DEFAULT_BLOCK_MESSAGE === (string) $this->block_message ) {
				return (string) __( 'Your IP Address is blocked', 'change-wp-admin-login' );
			}
			return (string) $this->block_message;
		}

		/**
		 * @param \WP_User|\WP_Error $user User.
		 * @return \WP_User|\WP_Error
		 */
		public function filter_authenticate_by_ip( $user ) {
			if ( ! $this->ip_enabled || is_wp_error( $user ) ) {
				return $user;
			}

			if ( $this->is_ip_blocked() ) {
				return new \WP_Error( 'blocked_ip_address', esc_html( $this->get_block_message() ) );
			}

			return $user;
		}

		/**
		 * @param bool $enabled Enabled.
		 * @return bool
		 */
		public function disable_limit_attempts_for_blocked_ip( $enabled ) {
			if ( ! $this->ip_enabled || 'whitelist' !== $this->ip_mode ) {
				return $enabled;
			}
			$list = $this->parse_ip_list( $this->whitelist_raw );
			$ip   = Helper::get_ip();
			if ( $list && ! in_array( $ip, $list, true ) ) {
				return false;
			}
			return $enabled;
		}

		/**
		 * @param string $error Error HTML.
		 * @return string
		 */
		public function filter_login_errors( $error ) {
			if ( ! $this->ip_enabled ) {
				return $error;
			}
			$ip = Helper::get_ip();
			if ( 'whitelist' === $this->ip_mode ) {
				$list = $this->parse_ip_list( $this->whitelist_raw );
				if ( $list && ! in_array( $ip, $list, true ) ) {
					return esc_html( $this->get_block_message() );
				}
			} else {
				$list = $this->parse_ip_list( $this->blacklist_raw );
				if ( $list && in_array( $ip, $list, true ) ) {
					return esc_html( $this->get_block_message() );
				}
			}
			return $error;
		}

		/**
		 * @param array $usernames Usernames.
		 * @return array
		 */
		public function filter_illegal_usernames( $usernames ) {
			if ( ! $this->is_common_usernames_enabled() ) {
				return is_array( $usernames ) ? $usernames : array();
			}
			if ( ! is_array( $usernames ) ) {
				$usernames = array();
			}
			$merged = array_merge( $usernames, $this->get_disabled_usernames() );
			$merged = array_map( 'strtolower', array_map( 'trim', $merged ) );
			return array_values( array_unique( array_filter( $merged ) ) );
		}

		/**
		 * @param array $result Signup result.
		 * @return array
		 */
		public function dedupe_signup_errors( $result ) {
			if ( isset( $result['errors'] ) && $result['errors'] instanceof \WP_Error ) {
				$this->unique_wp_error_messages( $result['errors'] );
			}
			return $result;
		}

		/**
		 * @param \WP_Error $errors Errors.
		 * @return \WP_Error
		 */
		public function dedupe_registration_errors( $errors ) {
			if ( $errors instanceof \WP_Error ) {
				$this->unique_wp_error_messages( $errors );
			}
			return $errors;
		}

		/**
		 * Register Free REST routes (+ Pro path aliases for existing Vue).
		 */
		public function register_rest_routes() {
			$routes = array(
				array( 'aio-login/block-ip-address', 'aio-login-pro/block-ip-address', 'get-settings', 'GET', 'get_ip_settings' ),
				array( 'aio-login/block-ip-address', 'aio-login-pro/block-ip-address', 'save-settings', 'POST', 'save_ip_settings' ),
				array( 'aio-login/disable-common-usernames', 'aio-login-pro/disable-common-usernames', 'get-settings', 'GET', 'get_common_username_settings' ),
				array( 'aio-login/disable-common-usernames', 'aio-login-pro/disable-common-usernames', 'save-settings', 'POST', 'save_common_username_settings' ),
			);

			foreach ( $routes as $r ) {
				foreach ( array( $r[0], $r[1] ) as $ns ) {
					register_rest_route(
						$ns,
						'/' . $r[2],
						array(
							'methods'             => $r[3],
							'permission_callback' => array( Helper::class, 'get_api_permission' ),
							'callback'            => array( $this, $r[4] ),
						)
					);
				}
			}
		}

		/**
		 * @return \WP_REST_Response
		 */
		public function get_ip_settings() {
			return rest_ensure_response(
				array(
					'enabled'                => $this->ip_enabled,
					'mode'                   => $this->ip_mode,
					'blacklist_mode_allowed' => true,
					'blacklist'              => $this->blacklist_raw,
					'whitelist'              => $this->whitelist_raw,
					'block_message'          => $this->block_message,
					'nonce'                  => wp_create_nonce( 'update-block-ip-address' ),
				)
			);
		}

		/**
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function save_ip_settings( $request ) {
			$params = $request->get_params();
			$nonce  = isset( $params['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'update-block-ip-address' ) ) {
				return new \WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'change-wp-admin-login' ), array( 'status' => 403 ) );
			}

			$enabled = ( ! empty( $params['enabled'] ) && ( true === $params['enabled'] || 'on' === $params['enabled'] || '1' === (string) $params['enabled'] ) ) ? 'on' : 'off';
			update_option( 'aio_login_block_ip_address_enable', $enabled );

			if ( isset( $params['mode'] ) ) {
				$mode = sanitize_text_field( wp_unslash( $params['mode'] ) );
				update_option( 'aio_login_block_ip_address_mode', ( 'blacklist' === $mode ) ? 'blacklist' : 'whitelist' );
			}
			if ( isset( $params['blacklist'] ) ) {
				update_option( 'aio_login_block_ip_address_blacklist', $this->sanitize_ip_textarea( $params['blacklist'] ) );
			}
			if ( isset( $params['whitelist'] ) ) {
				update_option( 'aio_login_block_ip_address_whitelist', $this->sanitize_ip_textarea( $params['whitelist'] ) );
			}
			if ( isset( $params['block_message'] ) ) {
				update_option( 'aio_login_block_ip_address_block_message', sanitize_text_field( wp_unslash( $params['block_message'] ) ) );
			}

			return rest_ensure_response( array( 'message' => __( 'Settings saved successfully.', 'change-wp-admin-login' ) ) );
		}

		/**
		 * @return \WP_REST_Response
		 */
		public function get_common_username_settings() {
			$enabled   = Network_Settings::get_option( 'aio_login_pro__disable_common_usernames_enable', 'off' );
			$usernames = Network_Settings::get_option(
				'aio_login_pro__disable_common_usernames_custom_usernames',
				'admin, test, user, admin123, test123, username'
			);
			if ( ! is_string( $usernames ) || '' === $usernames ) {
				$usernames = 'admin, test, user, admin123, test123, username';
			}

			return rest_ensure_response(
				array(
					'enabled'   => ( 'on' === $enabled || true === $enabled ),
					'usernames' => $usernames,
					'nonce'     => wp_create_nonce( 'update-disable-common-usernames' ),
				)
			);
		}

		/**
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function save_common_username_settings( $request ) {
			if ( ! Network_Settings::current_user_can_edit_feature( 'disable_common_usernames' ) ) {
				return new \WP_Error( 'rest_forbidden', __( 'You do not have permission to edit this feature.', 'change-wp-admin-login' ), array( 'status' => 403 ) );
			}

			$params = $request->get_params();
			$nonce  = isset( $params['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'update-disable-common-usernames' ) ) {
				return new \WP_Error( 'invalid_nonce', __( 'Invalid nonce', 'change-wp-admin-login' ), array( 'status' => 403 ) );
			}

			$enabled = ( ! empty( $params['enabled'] ) && ( true === $params['enabled'] || 'on' === $params['enabled'] || '1' === (string) $params['enabled'] ) ) ? 'on' : 'off';
			Network_Settings::update_option( 'aio_login_pro__disable_common_usernames_enable', $enabled );

			if ( isset( $params['usernames'] ) ) {
				Network_Settings::update_option(
					'aio_login_pro__disable_common_usernames_custom_usernames',
					sanitize_textarea_field( wp_unslash( $params['usernames'] ) )
				);
			}

			return rest_ensure_response( array( 'message' => __( 'Settings saved successfully.', 'change-wp-admin-login' ) ) );
		}

		/**
		 * @return bool
		 */
		private function is_common_usernames_enabled() {
			$enabled = Network_Settings::get_option( 'aio_login_pro__disable_common_usernames_enable', 'off' );
			return ( 'on' === $enabled || true === $enabled || 1 === (int) $enabled || '1' === (string) $enabled );
		}

		/**
		 * @return array
		 */
		private function get_disabled_usernames() {
			$raw = Network_Settings::get_option( 'aio_login_pro__disable_common_usernames_custom_usernames', '' );
			if ( ! is_string( $raw ) ) {
				$raw = '';
			}
			$raw    = str_replace( array( "\r\n", "\n\r", "\r", "\n" ), ',', $raw );
			$custom = explode( ',', $raw );
			$base   = apply_filters( 'aio_login__disabled_usernames', array() );
			if ( ! is_array( $base ) ) {
				$base = array();
			}
			return array_merge( $base, $custom );
		}

		/**
		 * @param string $raw Raw list.
		 * @return string[]|false
		 */
		private function parse_ip_list( $raw ) {
			if ( '' === trim( (string) $raw ) ) {
				return false;
			}
			$normalized = str_replace( array( "\r\n", "\n\r", "\r", "\n" ), ',', (string) $raw );
			$list       = array_filter( array_map( 'trim', explode( ',', $normalized ) ) );
			return empty( $list ) ? false : array_values( $list );
		}

		/**
		 * @param mixed $raw Raw textarea.
		 * @return string
		 */
		private function sanitize_ip_textarea( $raw ) {
			$lines = explode( "\n", (string) wp_unslash( $raw ) );
			$lines = array_map( 'sanitize_text_field', $lines );
			return implode( "\n", $lines );
		}

		/**
		 * @param \WP_Error $wp_error Error bag.
		 */
		private function unique_wp_error_messages( \WP_Error $wp_error ) {
			if ( empty( $wp_error->errors ) || ! is_array( $wp_error->errors ) ) {
				return;
			}
			foreach ( $wp_error->errors as $code => $messages ) {
				if ( is_array( $messages ) && count( $messages ) > 1 ) {
					$wp_error->errors[ $code ] = array_values( array_unique( $messages ) );
				}
			}
		}
	}
}
