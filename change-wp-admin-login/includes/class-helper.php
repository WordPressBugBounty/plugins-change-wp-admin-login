<?php
/**
 * Class Helper
 *
 * @package AIO Login
 */

namespace AIO_Login\Helper;

use AIO_Login\Login_Controller\Failed_Logins;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Helper\\Helper' ) ) {
	/**
	 * Class Helper
	 */
	class Helper {
		/**
		 * Getting the IP address of the user
		 *
		 * @return string
		 */
		public static function get_ip() {
			if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ipaddress = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
			} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ipaddress = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			} elseif ( isset( $_SERVER['HTTP_X_FORWARDED'] ) ) {
				$ipaddress = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED'] ) );
			} elseif ( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
				$ipaddress = sanitize_text_field( wp_unslash( $_SERVER['HTTP_FORWARDED_FOR'] ) );
			} elseif ( isset( $_SERVER['HTTP_FORWARDED'] ) ) {
				$ipaddress = sanitize_text_field( wp_unslash( $_SERVER['HTTP_FORWARDED'] ) );
			} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				$ipaddress = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			} else {
				$ipaddress = 'UNKNOWN';
			}

			if ( '::1' === $ipaddress ) {
				$ipaddress = '127.0.0.1';
			}
			return $ipaddress;
		}

		/**
		 * Getting the location of the user by IP
		 *
		 * @param string $ip The IP address of the user.
		 *
		 * @return array
		 */
		public static function get_location( $ip = '' ) {
			if ( empty( $ip ) ) {
				$ip = self::get_ip();
			}

			$url      = 'https://ipapi.co/' . $ip . '/json/';
			$response = wp_remote_get( $url );
			$body     = wp_remote_retrieve_body( $response );

			return json_decode( $body, true );
		}

		/**
		 * Create table
		 *
		 * @param string $table_name Table name.
		 * @param array  $cols Columns.
		 *
		 * @return bool
		 */
		public static function create_table( $table_name, $cols = array() ) {
			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();
			$table_name      = $wpdb->prefix . 'aio_login_' . $table_name;

			$sql = "CREATE TABLE IF NOT EXISTS $table_name (
				id bigint(20) NOT NULL AUTO_INCREMENT,";

			foreach ( $cols as $col => $val ) {
				$sql .= $col . ' ' . $val . ',';
			}

			$sql .= "
				PRIMARY KEY (id)
			) $charset_collate;";

			if ( ! function_exists( 'maybe_create_table' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			return maybe_create_table( $table_name, $sql );
		}

		/**
		 * Drop table
		 *
		 * @param string $table_name Table name.
		 *
		 * @return bool
		 */
		public static function drop_table( $table_name ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_' . $table_name;

			$sql = "DROP TABLE IF EXISTS $table_name;";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sql
			);
		}

		/**
		 * Blocking user IP from login
		 *
		 * @param string $ip IP.
		 */
		public static function block_ip( $ip = '' ) {
			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$current_time = current_time( 'timestamp' );
			if ( empty( $ip ) ) {
				$ip = self::get_ip();
			}

			$location = self::get_location( $ip );
			$country  = isset( $location['country'] ) ? $location['country'] : 'Unknown';
			$city     = isset( $location['city'] ) ? $location['city'] : 'Unknown';

			$user_agent = 'Unknown';
			if ( isset( $_POST['aio_login__user_agent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$user_agent = sanitize_text_field( wp_unslash( $_POST['aio_login__user_agent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}

			$blocked_user_data = array(
				'ip_address' => $ip,
				'country'    => $country,
				'city'       => $city,
				'time'       => $current_time,
				'user_agent' => $user_agent,
			);

			Failed_Logins::log_blocked_user( $blocked_user_data );
		}

		/**
		 * Check if user IP is blocked
		 *
		 * @param string $ip IP.
		 *
		 * @return array|false
		 */
		public static function is_ip_blocked( $ip = '' ) {
			if ( empty( $ip ) ) {
				$ip = self::get_ip();
			}
			$blocked_ip = Failed_Logins::is_user_blocked( $ip );

			if ( \AIO_Login\Login_Controller\Login_Controller::get_instance()->is_enabled() ) {
				return $blocked_ip;
			}

			return false;
		}

		/**
		 * Update user attempt count
		 *
		 * @param string $ip IP.
		 * @param bool   $clear Clear.
		 */
		public static function update_user_attempt_count( $ip = '', $clear = false ) {
			if ( empty( $ip ) ) {
				$ip = self::get_ip();
			}

			$attempts = get_transient( 'aio_login__user_attempts_' . $ip );
			if ( false === $attempts ) {
				$attempts = 0;
			}

			++$attempts;

			if ( $clear ) {
				$attempts = 0;
			}

			set_transient( 'aio_login__user_attempts_' . $ip, $attempts, 60 * 60 );
		}

		/**
		 * Get user attempt count
		 *
		 * @param string $ip IP.
		 *
		 * @return int
		 */
		public static function get_user_attempt_count( $ip = '' ) {
			if ( empty( $ip ) ) {
				$ip = self::get_ip();
			}

			$attempts = get_transient( 'aio_login__user_attempts_' . $ip );
			if ( false === $attempts ) {
				$attempts = 0;
			}

			return $attempts;
		}

		/**
		 * Get lockout attempts count
		 *
		 * @param int $timestamp Timestamp.
		 *
		 * @return int
		 */
		public static function get_timeout( $timestamp ) {
			$timeout = get_option( 'aio_login_limit_attempts_timeout', 0 );
			if ( empty( $timeout ) ) {
				$timeout = 5;
			}
			return $timestamp + ( $timeout * 60 );
		}

		/**
		 * Getting time stamps between two dates
		 *
		 * @param string $between Between it should be ( today, last_7_days, last_14_days, last_month ).
		 *
		 * @return string[]
		 */
		public static function get_timestamps( $between ) {
			$timestamps   = array();
			$current_time = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			switch ( $between ) {
				case 'last_7_days':
					$timestamps['start'] = strtotime( '-7 days', $current_time );
					$timestamps['end']   = $current_time;
					break;
				case 'last_14_days':
					$timestamps['start'] = strtotime( '-14 days', $current_time );
					$timestamps['end']   = $current_time;
					break;
				case 'last_month':
					$timestamps['start'] = strtotime( 'first day of last month', $current_time );
					$timestamps['end']   = strtotime( 'last day of last month', $current_time );
					break;
				case 'today':
				default:
					$timestamps['start'] = strtotime( 'today', $current_time );
					$timestamps['end']   = strtotime( 'tomorrow', $current_time ) - 1;
					break;
			}

			return $timestamps;
		}

		/**
		 * Get logs
		 *
		 * @param string $type Type of attempts.
		 *
		 * @return array
		 */
		public static function get_logs( $type ) {
			if ( 'lockout' === $type ) {
				return Failed_Logins::get_locked_ips();
			}

			return Failed_Logins::query_all_logs( 'failed', '', 'id', 'desc', 0 );
		}

		public static function get_api_permission() {
			return is_user_logged_in() && current_user_can( 'manage_options' );
		}
	}
}
