<?php
/**
 * Plugin Name: All In One Login
 * Plugin URI: https://aiologin.com/
 * Author: AIO Login
 * Author URI: https://aiologin.com/
 * Description: AIO Login secures and customizes your WordPress login page with a custom login URL, email OTP, 2FA, reCAPTCHA, and more.
 * Requires PHP: 7.4 or higher
 * Version: 2.5.0
 * Text Domain: change-wp-admin-login
 * Domain Path: /languages
 *
 * @package All In One Login
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'AIO_LOGIN__FILE' ) ) {
	define( 'AIO_LOGIN__FILE', __FILE__ );
}

require_once plugin_dir_path( __FILE__ ) . 'aio-login.php';
