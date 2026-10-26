<?php
/**
 * Free ↔ Pro release pairing guard.
 *
 * Free 2.5+ requires Pro 1.8+. An older active Pro is deactivated and an
 * admin notice asks the site owner to update Pro. No Pro feature code is
 * shipped or unlocked here (Guideline 5).
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

		const NOTICE_OPTION = 'aio_login_incompatible_pro_notice';

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
			add_action( 'plugins_loaded', array( __CLASS__, 'maybe_deactivate_incompatible_pro' ), 1 );
			add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
			add_action( 'network_admin_notices', array( __CLASS__, 'render_admin_notice' ) );
			add_action( 'wp_ajax_aio_login_dismiss_pair_notice', array( __CLASS__, 'ajax_dismiss_notice' ) );
		}

		/**
		 * @return void
		 */
		public static function maybe_deactivate_incompatible_pro() {
			if ( ! self::should_enforce() ) {
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

			if ( version_compare( $pro_version, self::required_pro_version(), '>=' ) ) {
				delete_option( self::NOTICE_OPTION );
				return;
			}

			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$network_wide = is_multisite() && function_exists( 'is_plugin_active_for_network' )
				&& is_plugin_active_for_network( $pro['file'] );

			deactivate_plugins( $pro['file'], true, $network_wide );

			// Pro already booted earlier this request — stop advertising has_pro until reload.
			add_filter( 'aio_login_has_pro', '__return_false', 999 );

			update_option(
				self::NOTICE_OPTION,
				array(
					'pro_version'      => $pro_version,
					'required_version' => self::required_pro_version(),
					'pro_file'         => $pro['file'],
					'time'             => time(),
				),
				false
			);
		}

		/**
		 * @return bool
		 */
		private static function should_enforce() {
			if ( wp_installing() ) {
				return false;
			}
			// Avoid running during bulk plugin operations that already manage activation.
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				return true;
			}
			return true;
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

			// Clear stale notice once a compatible Pro is active again.
			if ( defined( 'AIO_LOGIN_PRO__VERSION' )
				&& version_compare( (string) AIO_LOGIN_PRO__VERSION, self::required_pro_version(), '>=' ) ) {
				delete_option( self::NOTICE_OPTION );
				return;
			}

			$pro_version = esc_html( (string) $notice['pro_version'] );
			$required    = esc_html( self::required_pro_version() );
			$free_ver    = defined( 'AIO_LOGIN__VERSION' ) ? esc_html( (string) AIO_LOGIN__VERSION ) : '';

			$dismiss_url = wp_nonce_url(
				admin_url( 'admin-ajax.php?action=aio_login_dismiss_pair_notice' ),
				'aio_login_dismiss_pair_notice'
			);

			echo '<div class="notice notice-warning is-dismissible aio-login-pair-notice">';
			echo '<p><strong>' . esc_html__( 'All In One Login — incompatible Pro version deactivated', 'change-wp-admin-login' ) . '</strong></p>';
			echo '<p>';
			printf(
				/* translators: 1: Free version, 2: detected Pro version, 3: required Pro version */
				esc_html__( 'All In One Login %1$s requires AIO Login Pro %3$s or newer. Your Pro plugin (v%2$s) was deactivated to keep the site stable. Please update Pro to %3$s or later, then activate it again.', 'change-wp-admin-login' ),
				$free_ver ? $free_ver : '2.5+',
				$pro_version,
				$required
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
			delete_option( self::NOTICE_OPTION );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
			exit;
		}
	}
}
