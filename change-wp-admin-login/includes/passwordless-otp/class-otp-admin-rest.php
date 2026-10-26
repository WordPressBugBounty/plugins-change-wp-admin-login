<?php
/**
 * REST API for passwordless OTP admin settings.
 *
 * Free saves email OTP only. Pro saves SMS/Twilio via
 * `aio_login_otp_save_settings` (Guideline 5 / AIOL-745).
 *
 * @package AIO_Login
 */

namespace AIO_Login\Passwordless_Otp;

use AIO_Login\Helper\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Passwordless_Otp\\OTP_Admin_Rest' ) ) {
	/**
	 * OTP_Admin_Rest
	 */
	final class OTP_Admin_Rest {

		/**
		 * @return self
		 */
		public static function get_instance() {
			static $instance = null;
			if ( null === $instance ) {
				$instance = new self();
			}
			return $instance;
		}

		private function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		public function register_routes() {
			register_rest_route(
				'aio-login/passwordless-otp',
				'/get-settings',
				array(
					'methods'             => 'GET',
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
					'callback'            => array( $this, 'get_settings' ),
				)
			);

			register_rest_route(
				'aio-login/passwordless-otp',
				'/save-settings',
				array(
					'methods'             => 'POST',
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
					'callback'            => array( $this, 'save_settings' ),
				)
			);
		}

		/**
		 * @return \WP_REST_Response
		 */
		public function get_settings() {
			return rest_ensure_response( OTP_Settings::get_admin_settings() );
		}

		/**
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function save_settings( $request ) {
			$params = $request->get_json_params();
			if ( ! is_array( $params ) ) {
				$params = $request->get_params();
			}

			if ( empty( $params['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $params['_wpnonce'] ) ), OTP_Settings::NONCE_ACTION ) ) {
				return new \WP_Error( 'forbidden', __( 'Invalid security token.', 'change-wp-admin-login' ), array( 'status' => 403 ) );
			}

			$email_can_edit = \AIO_Login\Multisite\Network_Settings::current_user_can_edit_feature( 'email_otp' );
			$sms_can_edit   = \AIO_Login\Multisite\Network_Settings::current_user_can_edit_feature( 'sms_otp' );

			/**
			 * Whether Pro (or another add-on) will handle SMS save for this request.
			 *
			 * @param bool               $will_save_sms Default false.
			 * @param array<string,mixed> $params       Request params.
			 */
			$pro_will_save_sms = (bool) apply_filters( 'aio_login_otp_pro_will_save_sms', false, $params );

			if ( ! $email_can_edit && ! ( $sms_can_edit && $pro_will_save_sms ) ) {
				return new \WP_Error(
					'aio_login_network_managed',
					__( 'These settings are managed by the Network Administrator and cannot be changed on this site.', 'change-wp-admin-login' ),
					array( 'status' => 403 )
				);
			}

			if ( $email_can_edit ) {
				$email_enable = ! empty( $params['email_enable'] ) ? 'on' : 'off';
				$result       = \AIO_Login\Multisite\Network_Settings::update_option( 'aio_login_otp_email_enable', $email_enable );
				if ( is_wp_error( $result ) ) {
					return $result;
				}

				\AIO_Login\Multisite\Network_Settings::update_option(
					'aio_login_otp_email_block_duration',
					(string) max( 1, min( 1440, absint( $params['email_block_duration'] ?? 15 ) ) )
				);
				\AIO_Login\Multisite\Network_Settings::update_option(
					'aio_login_otp_email_skip_2fa',
					! empty( $params['email_skip_2fa'] ) ? 'on' : 'off'
				);

				if ( 'on' === $email_enable ) {
					$this->save_email_settings( $params );
				}
			}

			/**
			 * Let Pro save SMS/Twilio settings when has_pro (and network allows).
			 *
			 * Handlers may return \WP_Error to abort the request.
			 *
			 * @param null|true|\WP_Error $result  Null to continue; WP_Error to abort.
			 * @param array<string,mixed> $params  Request params.
			 * @param bool                $sms_can_edit Whether current user can edit sms_otp.
			 */
			$sms_result = apply_filters( 'aio_login_otp_settings_before_response', null, $params, $sms_can_edit );
			if ( is_wp_error( $sms_result ) ) {
				return $sms_result;
			}

			/**
			 * Action after Free email save — Pro may persist SMS options here.
			 *
			 * @param array<string,mixed> $params       Request params.
			 * @param bool                $sms_can_edit Whether current user can edit sms_otp.
			 */
			do_action( 'aio_login_otp_save_settings', $params, $sms_can_edit );

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Settings saved successfully.', 'change-wp-admin-login' ),
					'data'    => OTP_Settings::get_admin_settings(),
				)
			);
		}

		/**
		 * @param array<string, mixed> $params Params.
		 */
		private function save_email_settings( $params ) {
			$length = isset( $params['email_length'] ) ? absint( $params['email_length'] ) : 6;
			if ( ! in_array( $length, array( 6, 8 ), true ) ) {
				$length = 6;
			}
			\AIO_Login\Multisite\Network_Settings::update_option( 'aio_login_otp_email_length', (string) $length );
			\AIO_Login\Multisite\Network_Settings::update_option( 'aio_login_otp_email_expiration', (string) max( 1, min( 60, absint( $params['email_expiration'] ?? 10 ) ) ) );
			\AIO_Login\Multisite\Network_Settings::update_option( 'aio_login_otp_email_resend_timer', (string) max( 30, min( 600, absint( $params['email_resend_timer'] ?? 60 ) ) ) );
			\AIO_Login\Multisite\Network_Settings::update_option( 'aio_login_otp_email_max_retries', (string) max( 1, min( 20, absint( $params['email_max_retries'] ?? 5 ) ) ) );
		}
	}
}
