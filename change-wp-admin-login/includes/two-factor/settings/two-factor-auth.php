<?php
/**
 * Two-Factor Authentication — Free Email OTP challenge screen.
 *
 * @package AIO_Login
 */

defined( 'ABSPATH' ) || exit;

$redirect = '';
if ( isset( $_REQUEST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$redirect = wp_validate_redirect( esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), admin_url() ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

$show_resend      = false;
$resend_remaining = 0;
if ( is_user_logged_in() ) {
	$user_id           = get_current_user_id();
	$email_settings    = get_option( 'aio_login_pro__2fa_email_settings', array() );
	$resend_cooldown   = isset( $email_settings['resend_cooldown'] ) ? max( 0, absint( $email_settings['resend_cooldown'] ) ) : 30;
	$last_sent         = (int) get_user_meta( $user_id, 'aio_login_pro__email_otp_last_sent', true );
	$is_fresh_resend   = isset( $_GET['resent_otp'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['resent_otp'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $is_fresh_resend ) {
		$resend_remaining = $resend_cooldown;
	} else {
		$resend_remaining = max( 0, $resend_cooldown - ( time() - $last_sent ) );
	}
	$show_resend = $resend_remaining <= 0;
}

$resend_url = wp_nonce_url(
	add_query_arg(
		array(
			'action'      => 'aio_login_pro__two_factor_auth',
			'resend_otp'  => 1,
			'redirect_to' => $redirect,
		),
		wp_login_url()
	),
	'aio_login_pro_resend_otp'
);

$body_classes = apply_filters( 'login_body_class', array( 'login', 'login-action-login', 'wp-core-ui' ) );
if ( ! is_array( $body_classes ) ) {
	$body_classes = array( 'login', 'login-action-login', 'wp-core-ui' );
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<title><?php bloginfo( 'name' ); ?></title>
	<?php
	$aio_2fa_otp_css = '.aio-login-otp-resend-wrap{margin-top:8px;display:flex;align-items:center;justify-content:center;gap:8px;width:100%;text-align:center;clear:both}.aio-login-otp-resend-note{color:#6b7280;font-size:12px}.aio-login-otp-resend-btn{display:inline-block;font-size:12px;font-weight:600;text-decoration:none;border:1px solid #b6c2d4;color:#243046;background:#f5f8fc;padding:6px 12px;border-radius:4px}.aio-login-otp-resend-btn:hover{background:#eaf0f7;color:#1b2435}#loginform>.aio-login-otp-resend-wrap{order:99}';
	wp_enqueue_style( 'login' );
	wp_register_style( 'aio-login-2fa-otp', false, array( 'login' ), defined( 'AIO_LOGIN__VERSION' ) ? AIO_LOGIN__VERSION : null );
	wp_enqueue_style( 'aio-login-2fa-otp' );
	wp_add_inline_style( 'aio-login-2fa-otp', $aio_2fa_otp_css );
	do_action( 'login_enqueue_scripts' );
	wp_print_styles();
	wp_print_head_scripts();
	do_action( 'login_head' );
	?>
</head>
<body class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $body_classes ) ) ); ?>">
	<div id="login">
		<?php do_action( 'login_header' ); ?>
		<form name="loginform" id="loginform" action="<?php echo esc_url( AIO_Login\Two_Factor\Two_Factor_Auth::get_verification_url( $redirect ) ); ?>" method="post">
			<?php do_action( 'aio_login_pro__tfa_errors' ); ?>
			<input type="hidden" name="aio_2fa_challenge" id="aio_2fa_challenge" value="otp" />
			<p id="aio-login-2fa-otp-wrap" class="aio-login-2fa-otp-section" style="margin-bottom: 16px;">
				<label for="totp" style="font-weight: 600; color: #374151; display: block; margin-bottom: 6px;"><?php esc_attr_e( 'Enter Code', 'change-wp-admin-login' ); ?></label>
				<input type="text" name="totp" id="totp" class="input" value="" size="20" autocomplete="one-time-code" style="margin-bottom: 6px;">
				<span class="description" style="display: block; font-size: 13px; font-weight: 600; color: #374151;"><?php esc_attr_e( 'Enter the 6-digit OTP code.', 'change-wp-admin-login' ); ?></span>
			</p>
			<p class="submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Verify', 'change-wp-admin-login' ); ?>">
				<?php wp_nonce_field( 'verify_otp' ); ?>
				<?php if ( ! empty( $redirect ) ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
				<?php endif; ?>
			</p>
			<div class="aio-login-otp-resend-wrap">
				<a
					id="aio-login-resend-btn"
					class="aio-login-otp-resend-btn"
					href="<?php echo esc_url( $resend_url ); ?>"
					<?php echo $show_resend ? '' : 'style="display:none;"'; ?>
				>
					<?php esc_html_e( 'Resend Code', 'change-wp-admin-login' ); ?>
				</a>
				<span
					id="aio-login-resend-note"
					class="aio-login-otp-resend-note"
					data-remaining="<?php echo esc_attr( (int) $resend_remaining ); ?>"
					<?php echo $show_resend ? 'style="display:none;"' : ''; ?>
				>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: seconds until resend is available */
							__( 'Resend available in %d sec', 'change-wp-admin-login' ),
							$resend_remaining
						)
					);
					?>
				</span>
			</div>
		</form>
	</div>
	<?php
	/* translators: %d: seconds until resend is available */
	$resend_tpl = wp_json_encode( __( 'Resend available in %d sec', 'change-wp-admin-login' ) );
	$aio_2fa_otp_js = 'document.addEventListener("DOMContentLoaded",function(){var note=document.getElementById("aio-login-resend-note"),btn=document.getElementById("aio-login-resend-btn");if(!note||!btn){return;}var remaining=parseInt(note.getAttribute("data-remaining")||"0",10),tpl=' . $resend_tpl . ';if(remaining<=0){note.style.display="none";btn.style.display="inline-block";return;}var tick=function(){if(remaining<=0){note.style.display="none";btn.style.display="inline-block";return;}note.textContent=tpl.replace("%d",String(remaining));remaining-=1;window.setTimeout(tick,1000);};tick();});';
	wp_register_script( 'aio-login-2fa-otp', false, array(), defined( 'AIO_LOGIN__VERSION' ) ? AIO_LOGIN__VERSION : null, true );
	wp_enqueue_script( 'aio-login-2fa-otp' );
	wp_add_inline_script( 'aio-login-2fa-otp', $aio_2fa_otp_js );
	do_action( 'login_footer' );
	wp_print_footer_scripts();
	?>
</body>
</html>
