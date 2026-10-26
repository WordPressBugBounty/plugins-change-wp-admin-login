<?php
/**
 * Freemius integration.
 *
 * @package AIO Login
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'aio_login__fs' ) ) {
	/**
	 * Freemius integration.
	 *
	 * @return Freemius
	 */
	function aio_login__fs() {
		global $aio_login__fs;

		if ( ! isset( $aio_login__fs ) ) {
			// Activate multisite network integration.
			if ( ! defined( 'WP_FS__PRODUCT_15560_MULTISITE' ) ) {
				define( 'WP_FS__PRODUCT_15560_MULTISITE', true );
			}
			require_once AIO_LOGIN__DIR_PATH . 'includes/freemius/start.php';
			$aio_login__fs = fs_dynamic_init(
				array(
					'id'                  => '15560',
					'slug'                => 'change-wp-admin-login',
					'type'                => 'plugin',
					'public_key'          => 'pk_fb318b83149851203c925d67261af',
					// Guideline 8 / WP.org Free: no premium code version in this package.
					// Licensing/opt-in stay enabled; Pro ships as a separate plugin (product 15561).
					'is_premium'          => false,
					'is_premium_only'     => false,
					'has_premium_version' => false,
					'has_addons'          => true,
					'has_paid_plans'      => false,
					'is_org_compliant'    => true,
					'menu'                => array(
						'slug'    => 'aio-login',
						'support' => false,
						'account' => false,
						'contact' => false,
						'addons'  => false,
						'network' => true,
					),
				)
			);

			/*
			 * Guideline 8: disable Freemius premium self-install / ZIP download from the
			 * WordPress.org Free plugin. Keep SDK for opt-in, licensing sync, and Pro parent hooks.
			 *
			 * - has_premium_version / has_paid_plans / is_org_compliant above mark this as serviceware
			 *   (no premium release on Freemius for the Free product).
			 * - download_latest_url emptied so Account “download latest” links are inert.
			 * - AJAX install_premium_version short-circuited before Plugin_Upgrader runs.
			 */
			$aio_login__fs->add_filter( 'download_latest_url', '__return_empty_string' );
			$aio_login__fs->add_filter( 'show_trial', '__return_false' );
			$aio_login__fs->add_filter(
				'is_submenu_visible',
				static function ( $is_visible, $menu_id ) {
					if ( in_array( $menu_id, array( 'pricing', 'contact', 'addons' ), true ) ) {
						return false;
					}
					return $is_visible;
				},
				10,
				2
			);

			$aio_login__fs->add_filter(
				'pricing_url',
				static function () {
					return defined( 'AIO_LOGIN_GET_PRO_URL' )
						? AIO_LOGIN_GET_PRO_URL
						: 'https://aiologin.com/?utm_source=plugin&utm_medium=wp_plugin_get_pro&utm_campaign=upgrade_pro&utm_id=upgrade_pro';
				}
			);

			// Always register so this wins even when Freemius only attaches its handler on matching AJAX.
			add_action(
				'wp_ajax_fs_install_premium_version_15560',
				'aio_login__fs_block_premium_install',
				0
			);

			// Defer to `init`: __() must not run before translations are allowed to load.
			add_action(
				'init',
				static function () use ( $aio_login__fs ) {
					$aio_login__fs->override_i18n(
						array(
							'upgrade' => __( 'Get Pro', 'change-wp-admin-login' ),
						)
					);
				}
			);
		}

		return $aio_login__fs;
	}

	/**
	 * Guideline 8: refuse Freemius premium auto-install AJAX from the Free plugin.
	 *
	 * @return void
	 */
	function aio_login__fs_block_premium_install() {
		wp_send_json_error(
			array(
				'message' => __(
					'Premium installation from the WordPress.org Free plugin is disabled. Please install AIO Login Pro separately.',
					'change-wp-admin-login'
				),
				'code'    => 'org_premium_install_disabled',
			)
		);
	}

	aio_login__fs();
	do_action( 'aio_login__fs_loaded' );
}
