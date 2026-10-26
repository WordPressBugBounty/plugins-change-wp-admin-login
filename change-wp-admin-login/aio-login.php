<?php
/**
 * Main Loader file for the plugin.
 *
 * @package AIO Login
 */

defined( 'ABSPATH' ) || exit;

global $all_in_one_login;

if ( is_null( $all_in_one_login ) ) {
	define( 'AIO_LOGIN__DIR_PATH', plugin_dir_path( AIO_LOGIN__FILE ) );
	define( 'AIO_LOGIN__DIR_URL', plugin_dir_url( AIO_LOGIN__FILE ) );
	define( 'AIO_LOGIN__VERSION', '2.5.0' );
	/** Minimum AIO Login Pro version allowed alongside this Free build. */
	define( 'AIO_LOGIN__PAIRED_PRO_VERSION', '1.8.0' );
	define(
		'AIO_LOGIN_GET_PRO_URL',
		'https://aiologin.com/?utm_source=plugin&utm_medium=wp_plugin_get_pro&utm_campaign=upgrade_pro&utm_id=upgrade_pro'
	);

	require_once AIO_LOGIN__DIR_PATH . 'includes/class-plugin-pair-guard.php';
	\AIO_Login\Plugin_Pair_Guard::init();

	require_once AIO_LOGIN__DIR_PATH . 'includes/freemius.php';
	require_once AIO_LOGIN__DIR_PATH . 'includes/class-aio-login.php';

	$all_in_one_login = AIO_Login\AIO_Login::get_instance();
}

return $all_in_one_login;
