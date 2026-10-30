<?php
/**
 * Class Failed_Logins
 *
 * @package AIO Login
 */

namespace AIO_Login\Login_Controller;

use AIO_Login\Helper\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Login_Controller\\Failed_Logins' ) ) {
	/**
	 * Class Failed_Logins
	 */
	class Failed_Logins {
		/**
		 * Get the instance of this class
		 *
		 * @var Failed_Logins $instance The single instance of the class.
		 */
		private static $instance;

		/**
		 * Insert Logs
		 *
		 * @param array $login_details Login details.
		 *
		 * @return int
		 */
		public static function insert_logs( $login_details ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_login_attempts';

			$result = $wpdb->insert( $table_name, $login_details ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( false !== $result && isset( $login_details['status'] ) && 'failed' === $login_details['status'] ) {
				do_action( 'aio_login_after_failed_login', $login_details );
			}

			return $result;
		}

		/**
		 * Delete Logs
		 *
		 * @param array|int $id ID.
		 *
		 * @return bool
		 */
		public static function delete_logs( $id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_login_attempts';

			if ( 'all' === $id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
				return $wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table_name ) );
			}

			$ids = array_values( array_filter( array_map( 'absint', (array) $id ) ) );
			if ( empty( $ids ) ) {
				return false;
			}

			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$sql          = "DELETE FROM %i WHERE id IN ($placeholders)";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- dynamic IN list.
			$prepared = $wpdb->prepare( $sql, array_merge( array( $table_name ), $ids ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
			return $wpdb->query( $prepared );
		}

		/**
		 * Delete lockouts
		 *
		 * @param array|int $id ID.
		 *
		 * @return bool
		 */
		public static function delete_lockouts( $id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_login_lockouts';

			if ( 'all' === $id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
				return $wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $table_name ) );
			}

			$ids = array_values( array_filter( array_map( 'absint', (array) $id ) ) );
			if ( empty( $ids ) ) {
				return false;
			}

			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$sql          = "DELETE FROM %i WHERE id IN ($placeholders)";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- dynamic IN list.
			$prepared = $wpdb->prepare( $sql, array_merge( array( $table_name ), $ids ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
			return $wpdb->query( $prepared );
		}

		/**
		 * Query Logs
		 *
		 * @param string $status Status.
		 * @param string $search Search.
		 * @param string $orderby Orderby.
		 * @param string $order Order.
		 * @param int    $limit Limit.
		 *
		 * @return array|object|\stdClass[]
		 */
		public static function query_all_logs( $status = 'success', $search = '', $orderby = '', $order = '', $limit = 0 ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_login_attempts';

			$allowed_orderby = array( 'id', 'user_login', 'ip_address', 'time', 'status', 'country', 'city' );
			$orderby         = in_array( (string) $orderby, $allowed_orderby, true ) ? (string) $orderby : 'id';
			$order           = ( 'ASC' === strtoupper( (string) $order ) ) ? 'ASC' : 'DESC';
			$limit           = absint( $limit );

			$sql    = 'SELECT * FROM %i WHERE status = %s';
			$params = array( $table_name, $status );

			if ( ! empty( $search ) ) {
				$sql     .= ' AND (user_login LIKE %s OR ip_address LIKE %s)';
				$like     = '%' . $wpdb->esc_like( $search ) . '%';
				$params[] = $like;
				$params[] = $like;
			}

			// ORDER BY column/direction are allowlisted above — safe to interpolate.
			$sql .= ' ORDER BY ' . $orderby . ' ' . $order;

			if ( $limit > 0 ) {
				$sql     .= ' LIMIT %d';
				$params[] = $limit;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$prepared = $wpdb->prepare( $sql, $params );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
			return $wpdb->get_results( $prepared, ARRAY_A );
		}

		/**
		 * Get lockout attempts count
		 *
		 * @param string $between Between.
		 *
		 * @return int
		 */
		public static function get_lockout_attempts_count( $between = 'today' ) {
			$timestamps = Helper::get_timestamps( $between );

			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_login_lockouts';
			$sql        = 'SELECT COUNT(*) FROM %i WHERE time BETWEEN %d AND %d';
			$sql        = $wpdb->prepare( $sql, $table_name, $timestamps['start'], $timestamps['end'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			return $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		}

		/**
		 * Get failed attempts count
		 *
		 * @param string $type Type.
		 * @param string $between Between.
		 *
		 * @return int
		 */
		public static function get_attempts_count( $type = 'success', $between = 'today' ) {
			$timestamps = Helper::get_timestamps( $between );

			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_login_attempts';
			$sql        = 'SELECT COUNT(*) FROM %i WHERE status = %s AND time BETWEEN %d AND %d';
			$sql        = $wpdb->prepare( $sql, $table_name, $type, $timestamps['start'], $timestamps['end'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			return $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		}

		/**
		 * Get lockout attempts count
		 *
		 * @return array
		 */
		public static function get_locked_ips() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_login_lockouts';
			$sql        = 'SELECT * FROM %i ORDER BY time DESC ';

			$sql    = $wpdb->prepare( $sql, $table_name ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$result = array_map( array( self::get_instance(), 'add_blocked_tag' ), $result );
			return $result;
		}

		/**
		 * Add blocked tag
		 *
		 * @param array $data Data.
		 *
		 * @return array
		 */
		public function add_blocked_tag( $data ) {
			$ip           = isset( $data['ip_address'] ) ? (string) $data['ip_address'] : '';
			$timeout      = Helper::get_timeout( (int) $data['time'], $ip );
			$current_time = current_time( 'timestamp' );

			if ( $timeout >= $current_time ) {
				$data['ip_address'] = $data['ip_address'] . ' [' . __( 'Blocked', 'change-wp-admin-login' ) . ']';
			}

			return $data;
		}

		/**
		 * Update status
		 *
		 * @param array $data Data.
		 * @param array $where Where.
		 *
		 * @return bool|int
		 */
		public static function update_status( $data, $where = array() ) {

			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_login_attempts';

			return $wpdb->update( $table_name, $data, $where ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		}

		/**
		 * Log blocked user
		 *
		 * @param array $data Data.
		 *
		 * @return int
		 */
		public static function log_blocked_user( $data ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_login_lockouts';

			$result = $wpdb->insert( $table_name, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( false !== $result ) {
				do_action( 'aio_login_after_lockout', $data );
			}

			return $result;
		}

		/**
		 * Check if user is blocked
		 *
		 * @param string $ip IP.
		 *
		 * @return array|false
		 */
		public static function is_user_blocked( $ip = '' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_login_lockouts';

			$sql  = 'SELECT * FROM %i WHERE `ip_address` = %s ORDER BY `time` DESC LIMIT 1';
			$sql  = $wpdb->prepare( $sql, $table_name, $ip ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$data = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

			if ( is_array( $data ) && isset( $data['time'] ) ) {
				$timeout      = Helper::get_timeout( (int) $data['time'], $ip );
				$current_time = current_time( 'timestamp' );

				if ( $timeout >= $current_time ) {
					return $data;
				}
			}

			return false;
		}

		/**
		 * Latest lockout row for IP without expiry check.
		 *
		 * @param string $ip IP.
		 * @return array<string, mixed>|false
		 */
		public static function is_user_blocked_raw( $ip = '' ) {
			if ( empty( $ip ) ) {
				$ip = Helper::get_ip();
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'aio_login_login_lockouts';

			$sql  = 'SELECT * FROM %i WHERE `ip_address` = %s ORDER BY `time` DESC LIMIT 1';
			$sql  = $wpdb->prepare( $sql, $table_name, $ip ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$data = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

			return is_array( $data ) ? $data : false;
		}

		/**
		 * Get the instance of this class
		 *
		 * @return Failed_Logins
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}
	}
}
