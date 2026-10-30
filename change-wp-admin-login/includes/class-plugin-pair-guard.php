<?php
/**
 * Free ↔ Pro release pairing guard.
 *
 * Free 2.5+ requires Pro 1.8+. An older active Pro is flagged with an admin
 * notice so the site owner can update or deactivate Pro themselves.
 * This plugin does not activate or deactivate other plugins (Guideline).
 *
 * @package AIO_Login
 */

namespace AIO_Login;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Plugin_Pair_Guard' ) ) {
	/**
	 * Plugin_Pair_Guard
	 */
	final class Plugin_Pair_Guard {

		const NOTICE_OPTION   = 'aio_login_incompatible_pro_notice';
		const DISMISS_OPTION  = 'aio_login_incompatible_pro_notice_dismissed';

		/**
		 * Minimum Pro version that may stay active with this Free build.
		 *
		 * @return string
		 */
		public static function required_pro_version() {
			return defined( 'AIO_LOGIN__PAIRED_PRO_VERSION' )
				? (string) AIO_LOGIN__PAIRED_PRO_VERSION
				: '1.8.0';
		}

		/**
		 * Bootstrap hooks.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'plugins_loaded', array( __CLASS__, 'maybe_flag_incompatible_pro' ), 1 );
			add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
			add_action( 'network_admin_notices', array( __CLASS__, 'render_admin_notice' ) );
			add_action( 'wp_ajax_aio_login_dismiss_pair_notice', array( __CLASS__, 'ajax_dismiss_notice' ) );
		}

		/**
		 * Detect incompatible Pro and store a notice. Does not deactivate plugins.
		 *
		 * @return void
		 */
		public static function maybe_flag_incompatible_pro() {
			if ( wp_installing() ) {
				return;
			}

			$pro = self::find_active_pro_plugin();
			if ( empty( $pro['file'] ) ) {
				return;
			}

			$pro_version = $pro['version'];
			if ( '' === $pro_version ) {
				return;
			}

			$required = self::required_pro_version();

			if ( version_compare( $pro_version, $required, '>=' ) ) {
				delete_option( self::NOTICE_OPTION );
				delete_option( self::DISMISS_OPTION );
				return;
			}

			// Soft-disable Pro feature advertising until a compatible Pro is active.
			add_filter( 'aio_login_has_pro', '__return_false', 999 );

			if ( self::is_dismissed_for( $pro_version, $required ) ) {
				return;
			}

			$existing = get_option( self::NOTICE_OPTION, null );
			if (
				is_array( $existing )
				&& isset( $existing['pro_version'], $existing['required_version'] )
				&& (string) $existing['pro_version'] === (string) $pro_version
				&& (string) $existing['required_version'] === (string) $required
			) {
				// Already flagged for this pair — do not rewrite every request.
				return;
			}

			update_option(
				self::NOTICE_OPTION,
				array(
					'pro_version'      => $pro_version,
					'required_version' => $required,
					'pro_file'         => $pro['file'],
					'time'             => time(),
				),
				false
			);
		}

		/**
		 * Whether the admin dismissed the notice for this Pro/required pair.
		 *
		 * @param string $pro_version Detected Pro version.
		 * @param string $required    Required Pro version.
		 * @return bool
		 */
		private static function is_dismissed_for( $pro_version, $required ) {
			$dismissed = get_option( self::DISMISS_OPTION, null );
			if ( ! is_array( $dismissed ) ) {
				return false;
			}
			return isset( $dismissed['pro_version'], $dismissed['required_version'] )
				&& (string) $dismissed['pro_version'] === (string) $pro_version
				&& (string) $dismissed['required_version'] === (string) $required;
		}

		/**
		 * Locate an active AIO Login Pro plugin and its version.
		 *
		 * @return array{file:string,version:string}
		 */
		private static function find_active_pro_plugin() {
			$result = array(
				'file'    => '',
				'version' => '',
			);

			if ( defined( 'AIO_LOGIN_PRO__VERSION' ) ) {
				$result['version'] = (string) AIO_LOGIN_PRO__VERSION;
			}
			if ( defined( 'AIO_LOGIN_PRO__FILE' ) ) {
				$result['file'] = plugin_basename( AIO_LOGIN_PRO__FILE );
				if ( '' === $result['version'] ) {
					$result['version'] = self::read_plugin_version( AIO_LOGIN_PRO__FILE );
				}
				return $result;
			}

			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$candidates = array(
				'aio-login-pro/aio-login-pro.php',
				'aio-login-pro-released/aio-login-pro.php',
			);

			foreach ( $candidates as $file ) {
				$active = is_plugin_active( $file )
					|| ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $file ) );
				if ( ! $active ) {
					continue;
				}
				$path = WP_PLUGIN_DIR . '/' . $file;
				if ( ! is_readable( $path ) ) {
					continue;
				}
				$result['file']    = $file;
				$result['version'] = self::read_plugin_version( $path );
				return $result;
			}

			return $result;
		}

		/**
		 * @param string $plugin_file Absolute path.
		 * @return string
		 */
		private static function read_plugin_version( $plugin_file ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$data = get_plugin_data( $plugin_file, false, false );
			return isset( $data['Version'] ) ? (string) $data['Version'] : '';
		}

		/**
		 * @return void
		 */
		public static function render_admin_notice() {
			if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'manage_network_plugins' ) ) {
				return;
			}

			$notice = get_option( self::NOTICE_OPTION, null );
			if ( ! is_array( $notice ) || empty( $notice['pro_version'] ) ) {
				return;
			}

			$required    = isset( $notice['required_version'] ) ? (string) $notice['required_version'] : self::required_pro_version();
			$pro_version = (string) $notice['pro_version'];

			if ( self::is_dismissed_for( $pro_version, $required ) ) {
				return;
			}

			// Clear stale notice once a compatible Pro is active again.
			if ( defined( 'AIO_LOGIN_PRO__VERSION' )
				&& version_compare( (string) AIO_LOGIN_PRO__VERSION, self::required_pro_version(), '>=' ) ) {
				delete_option( self::NOTICE_OPTION );
				delete_option( self::DISMISS_OPTION );
				return;
			}

			$free_ver = defined( 'AIO_LOGIN__VERSION' ) ? (string) AIO_LOGIN__VERSION : '';

			$dismiss_url = wp_nonce_url(
				admin_url( 'admin-ajax.php?action=aio_login_dismiss_pair_notice' ),
				'aio_login_dismiss_pair_notice'
			);

			echo '<div class="notice notice-warning is-dismissible aio-login-pair-notice">';
			echo '<p><strong>' . esc_html__( 'All In One Login — incompatible Pro version detected', 'change-wp-admin-login' ) . '</strong></p>';
			echo '<p>';
			printf(
				/* translators: 1: Free version, 2: detected Pro version, 3: required Pro version */
				esc_html__( 'All In One Login %1$s requires AIO Login Pro %3$s or newer. Your Pro plugin (v%2$s) is outdated. Please update Pro to %3$s or later, or deactivate it from the Plugins screen.', 'change-wp-admin-login' ),
				esc_html( $free_ver ? $free_ver : '2.5+' ),
				esc_html( $pro_version ),
				esc_html( $required )
			);
			echo '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Go to Plugins', 'change-wp-admin-login' ) . '</a> ';
			echo '<a class="button" href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss', 'change-wp-admin-login' ) . '</a></p>';
			echo '</div>';
		}

		/**
		 * @return void
		 */
		public static function ajax_dismiss_notice() {
			if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'manage_network_plugins' ) ) {
				wp_die( -1, 403 );
			}
			check_admin_referer( 'aio_login_dismiss_pair_notice' );

			$notice = get_option( self::NOTICE_OPTION, null );
			$pro    = is_array( $notice ) && ! empty( $notice['pro_version'] )
				? (string) $notice['pro_version']
				: '';
			$required = is_array( $notice ) && ! empty( $notice['required_version'] )
				? (string) $notice['required_version']
				: self::required_pro_version();

			if ( '' !== $pro ) {
				update_option(
					self::DISMISS_OPTION,
					array(
						'pro_version'      => $pro,
						'required_version' => $required,
						'time'             => time(),
					),
					false
				);
			}

			delete_option( self::NOTICE_OPTION );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
			exit;
		}
	}
}
