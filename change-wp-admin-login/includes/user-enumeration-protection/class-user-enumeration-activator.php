<?php
/**
 * User Enumeration Protection Activator (Free)
 *
 * Enumeration logs table is owned by Pro. Free activator is a no-op for G5.
 *
 * @package All In One Login
 */

namespace AIO_Login\User_Enumeration_Protection;

defined( 'ABSPATH' ) || exit;

/**
 * Class User_Enumeration_Activator
 */
class User_Enumeration_Activator {

	/**
	 * No-op: logs table is created by aio-login-pro only.
	 */
	public static function create_table() {
		// Intentionally empty — Free must not ship enumeration logging schema (Guideline 5).
	}
}
