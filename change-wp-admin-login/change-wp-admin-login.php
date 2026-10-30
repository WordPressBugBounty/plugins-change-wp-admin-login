<?php
/**
 * Plugin Name: All In One Login
 * Plugin URI: https://aiologin.com/
 * Author: AIO Login
 * Author URI: https://aiologin.com/
 * Description: AIO Login secures and customizes your WordPress login page with a custom login URL, email OTP, 2FA, reCAPTCHA, and more.
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Version: 2.5.1
 * Text Domain: change-wp-admin-login
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package All In One Login
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'AIO_LOGIN__FILE' ) ) {
	define( 'AIO_LOGIN__FILE', __FILE__ );
}

require_once plugin_dir_path( __FILE__ ) . 'aio-login.php';
