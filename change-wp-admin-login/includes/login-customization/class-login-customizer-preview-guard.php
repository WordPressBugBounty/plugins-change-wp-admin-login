<?php
/**
 * Guards for wp-login.php Customizer live-preview (AIOL-730).
 *
 * @package AIO_Login
 */

namespace AIO_Login\Login_Customization;

defined( 'ABSPATH' ) || exit;

/**
 * Site origin for postMessage allowlisting (scheme + host + port).
 *
 * @return string
 */
function aio_login_get_site_origin() {
	$parts = wp_parse_url( home_url( '/' ) );
	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return '';
	}
	$origin = $parts['scheme'] . '://' . $parts['host'];
	if ( ! empty( $parts['port'] ) ) {
		$origin .= ':' . (int) $parts['port'];
	}
	return $origin;
}

/**
 * Whether login customizer preview assets may load on this request.
 *
 * Query-string flags alone must not enable preview for anonymous visitors.
 *
 * @return bool
 */
function aio_login_may_enqueue_login_customizer_preview_scripts() {
	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		return true;
	}

	if ( ! is_user_logged_in() || ! current_user_can( 'customize' ) ) {
		return false;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Customizer preview entry uses core changeset/messenger params.
	$is_preview = (
		! empty( $_GET['customize_changeset_uuid'] )
		|| ! empty( $_GET['customize_messenger_channel'] )
		|| ! empty( $_GET['aio_login_customizer_preview'] )
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	return $is_preview;
}

/**
 * Whether wp-login.php should behave as an in-Customizer preview frame.
 *
 * @return bool
 */
function aio_login_is_login_customizer_preview_request() {
	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		return true;
	}

	if ( ! is_user_logged_in() || ! current_user_can( 'customize' ) ) {
		return false;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Customizer preview flags; capability checked above.
	if ( ! empty( $_GET['customize_changeset_uuid'] ) || ! empty( $_GET['customize_messenger_channel'] ) || ! empty( $_GET['aio_login_customizer_preview'] ) ) {
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return true;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_REQUEST['aio_login_customizer'] ) ) {
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return true;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	return false;
}

/**
 * Localize preview script config (origin allowlist for postMessage).
 *
 * @param string $handle Script handle.
 * @return void
 */
function aio_login_localize_login_customizer_preview_script( $handle ) {
	$pro_dir = defined( 'AIO_LOGIN_PRO__DIR_URL' ) ? AIO_LOGIN_PRO__DIR_URL : AIO_LOGIN__DIR_URL;
	wp_localize_script(
		$handle,
		'aioLoginPreviewConfig',
		array(
			'proAssetsUrl'   => $pro_dir . 'assets/',
			'freeAssetsUrl'  => AIO_LOGIN__DIR_URL . 'assets/',
			'allowedOrigin'  => aio_login_get_site_origin(),
		)
	);
}
