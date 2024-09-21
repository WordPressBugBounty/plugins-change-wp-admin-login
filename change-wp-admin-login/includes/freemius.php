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
	 */
	function aio_login__fs() {
		global $aio_login__fs;

		if ( ! isset( $aio_login__fs ) ) {
			require_once AIO_LOGIN__DIR_PATH . 'includes/freemius/start.php';
			$aio_login__fs = fs_dynamic_init(
				array(
					'id'                  => '15560',
					'slug'                => 'change-wp-admin-login',
					'has_premium_version' => true,
					'type'                => 'plugin',
					'public_key'          => 'pk_fb318b83149851203c925d67261af',
					'is_premium'          => false,
					'has_addons'          => true,
					'has_paid_plans'      => false,
					'menu'                => array(
						'slug'    => 'aio-login',
						'support' => false,
						'account' => false,
						'contact' => false,
						'addons'  => false,
					),
				)
			);
		}

		return $aio_login__fs;
	}

	aio_login__fs();
	do_action( 'aio_login__fs_loaded' );
}
