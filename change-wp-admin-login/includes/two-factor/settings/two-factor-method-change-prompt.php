<?php
/**
 * After a successful 2FA step (Email OTP or TOTP) when the site requires a different method — interstitial on wp-login.php.
 *
 * Variable: $aio_twofa_method_redirect (from load_template args).
 *
 * @package AIO_Login_Pro
 */

defined( 'ABSPATH' ) || exit;

$current_user_id = get_current_user_id();
$required_method = \AIO_Login\Two_Factor\Two_Factor::get_required_site_method_for_user( $current_user_id );
$setup_label     = __( 'Set up new method', 'change-wp-admin-login' );
if ( 'totp' === $required_method ) {
	$setup_label = __( 'Set up Authenticator App', 'change-wp-admin-login' );
} elseif ( 'email' === $required_method ) {
	$setup_label = __( 'Set up Email One-Time Code', 'change-wp-admin-login' );
}

$redirect_safe = isset( $aio_twofa_method_redirect ) ? $aio_twofa_method_redirect : admin_url();

$setup_base = add_query_arg(
	array(
		'aio_login_pro_2fa_method_change' => '1',
		'aio_login_pro_2fa_method_setup'   => '1',
	),
	wp_login_url()
);
$setup_url  = wp_nonce_url( $setup_base, 'aio_login_pro_2fa_method_change_setup' );

$later_base = add_query_arg(
	array(
		'aio_login_pro_2fa_method_change' => '1',
		'aio_login_pro_2fa_method_later'   => '1',
		'redirect_to'                      => rawurlencode( $redirect_safe ),
	),
	wp_login_url()
);
$later_url  = wp_nonce_url( $later_base, 'aio_login_pro_2fa_method_change_later' );

$body_classes = apply_filters( 'login_body_class', array( 'login', 'login-action-login', 'wp-core-ui', 'aio-login-pro-2fa-setup-prompt', 'aio-login-pro-2fa-method-change-prompt' ), array() );
if ( ! is_array( $body_classes ) ) {
	$body_classes = array( 'login', 'login-action-login', 'wp-core-ui', 'aio-login-pro-2fa-setup-prompt', 'aio-login-pro-2fa-method-change-prompt' );
}

$login_header_url   = apply_filters( 'login_headerurl', home_url( '/' ) );
$login_header_title = '';
$login_header_title = apply_filters_deprecated(
	'login_headertitle',
	array( $login_header_title ),
	'5.2.0',
	'login_headertext',
	__( 'Usage of the title attribute on the login logo is not recommended for accessibility reasons. Use the link text instead.', 'change-wp-admin-login' )
);
// Use the site name — never a default "Powered by" credit on user-facing screens.
$login_header_text = empty( $login_header_title ) ? get_bloginfo( 'name', 'display' ) : $login_header_title;
$login_header_text = apply_filters( 'login_headertext', $login_header_text );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( __( 'Two-factor method updated', 'change-wp-admin-login' ) . ' &lsaquo; ' . get_bloginfo( 'name', 'display' ) ); ?></title>
	<?php
	$aio_2fa_prompt_css = '/**
		 * Same rules as two-factor-setup-prompt.php so Login Customizer templates (01–09) match.
		 */
		/* --- Template-09: same cell + panel as #login form (split layout) --- */
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-09 .aio-login-pro__2fa-setup-prompt-card {
			grid-column: 2 !important;
			grid-row: 1 !important;
			width: 100% !important;
			max-width: 420px !important;
			min-height: 660px !important;
			height: auto !important;
			background: #ffffff !important;
			border: none !important;
			border-radius: 12px !important;
			box-shadow: 0 20px 50px rgba(139, 92, 246, 0.08) !important;
			display: flex !important;
			flex-direction: column !important;
			justify-content: flex-start !important;
			box-sizing: border-box !important;
			margin: 0 !important;
			padding: 124px 30px 28px !important;
			position: relative !important;
			z-index: 10 !important;
			text-align: left !important;
		}

		/*
		 * Keep WP logo in the right panel header area for interstitial screens.
		 * Without explicit grid placement it can fall into an implicit row at the bottom.
		 */
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-09 #login h1.wp-login-logo {
			grid-column: 2 !important;
			grid-row: 1 !important;
			justify-self: center !important;
			align-self: start !important;
			width: 96px !important;
			height: 96px !important;
			margin: 16px auto 0 !important;
			position: relative !important;
			z-index: 20 !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-09 #login h1.wp-login-logo a {
			overflow: hidden !important;
			text-indent: -9999px !important;
			white-space: nowrap !important;
		}

		@media (max-width: 820px) {
			html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-09 .aio-login-pro__2fa-setup-prompt-card {
				grid-column: 1 !important;
				grid-row: 1 !important;
				max-width: none !important;
				min-height: 0 !important;
				width: 100% !important;
				padding-top: 116px !important;
			}

			html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-09 #login h1.wp-login-logo {
				grid-column: 1 !important;
				margin-top: 12px !important;
			}
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-06 #login .aio-login-pro__2fa-setup-prompt-card {
			width: 350px !important;
			margin-left: 0 !important;
			margin-right: 0 !important;
			position: relative !important;
			z-index: 5 !important;
			float: none !important;
			box-sizing: border-box !important;
			background: transparent !important;
			border: none !important;
			box-shadow: none !important;
			padding: 0 !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-03 .aio-login-pro__2fa-setup-prompt-card {
			margin-top: 20px !important;
			padding: 26px 24px 34px !important;
			background: #fff !important;
			border: 1px solid #c3c4c7 !important;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
			border-radius: 4px !important;
			box-sizing: border-box !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-07 .aio-login-pro__2fa-setup-prompt-card {
			background: transparent !important;
			border: none !important;
			box-shadow: none !important;
			padding: 0 !important;
			margin: 0 !important;
			box-sizing: border-box !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-08 .aio-login-pro__2fa-setup-prompt-card,
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-04 .aio-login-pro__2fa-setup-prompt-card,
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-05 .aio-login-pro__2fa-setup-prompt-card {
			background: transparent !important;
			border: none !important;
			box-shadow: none !important;
			padding: 0 !important;
			margin: 0 !important;
			box-sizing: border-box !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-08 .aio-login-pro__2fa-setup-title,
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-04 .aio-login-pro__2fa-setup-title,
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-05 .aio-login-pro__2fa-setup-title {
			color: #ffffff !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-08 .aio-login-pro__2fa-setup-desc,
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-04 .aio-login-pro__2fa-setup-desc,
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-05 .aio-login-pro__2fa-setup-desc {
			color: rgba(255, 255, 255, 0.88) !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-08 .aio-login-pro__2fa-setup-divider,
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-04 .aio-login-pro__2fa-setup-divider,
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-05 .aio-login-pro__2fa-setup-divider {
			border-top-color: rgba(255, 255, 255, 0.28) !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-02 .aio-login-pro__2fa-setup-prompt-card {
			background: transparent !important;
			border: none !important;
			box-shadow: none !important;
			padding: 0 !important;
			margin: 0 !important;
			box-sizing: border-box !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-02 .aio-login-pro__2fa-setup-title {
			color: #ffffff !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-02 .aio-login-pro__2fa-setup-desc {
			color: #a3a3a3 !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-02 .aio-login-pro__2fa-setup-divider {
			border-top-color: #333333 !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-01 .aio-login-pro__2fa-setup-prompt-card {
			max-width: 420px !important;
			margin-left: auto !important;
			margin-right: auto !important;
			border: 0 !important;
			background: transparent !important;
			box-shadow: none !important;
			padding-left: 25px !important;
			padding-right: 25px !important;
			box-sizing: border-box !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-prompt-card {
			box-sizing: border-box !important;
			text-align: left !important;
			position: relative !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-title {
			margin: 0 0 16px !important;
			padding: 0 !important;
			font-size: 22px !important;
			font-weight: 700 !important;
			line-height: 1.35 !important;
			color: #2c3338 !important;
			letter-spacing: -0.02em !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-09 .aio-login-pro__2fa-setup-title {
			text-align: center !important;
			margin-top: 0 !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-09 .aio-login-pro__2fa-setup-desc {
			margin-bottom: 34px !important;
			font-size: 15px !important;
			line-height: 1.7 !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-09 .aio-login-pro__2fa-setup-actions {
			margin-top: 6px !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-divider {
			display: block !important;
			border: none !important;
			border-top: 1px solid #e2e4e7 !important;
			margin: 0 0 18px !important;
			width: 100% !important;
			height: 0 !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-desc {
			margin: 0 0 28px !important;
			padding: 0 !important;
			font-size: 14px !important;
			font-weight: 400 !important;
			color: #646970 !important;
			line-height: 1.6 !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-actions {
			margin: 0 !important;
			padding: 0 !important;
			display: flex !important;
			flex-wrap: wrap !important;
			align-items: stretch !important;
			gap: 12px !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-actions .aio-setup-btn {
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			min-height: 40px !important;
			padding: 10px 18px !important;
			font-size: 14px !important;
			font-weight: 500 !important;
			line-height: 1.4 !important;
			text-decoration: none !important;
			border-radius: 5px !important;
			cursor: pointer !important;
			box-sizing: border-box !important;
			transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease !important;
			box-shadow: none !important;
			text-shadow: none !important;
			-webkit-appearance: none !important;
			appearance: none !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-actions .aio-setup-btn--primary {
			border: 1px solid #2d7d7d !important;
			color: #2d7d7d !important;
			background: transparent !important;
			background-image: none !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-actions .aio-setup-btn--primary:hover,
		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-actions .aio-setup-btn--primary:focus {
			background: rgba(45, 125, 125, 0.06) !important;
			border-color: #246868 !important;
			color: #1f5c5c !important;
			outline: none !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-actions .aio-setup-btn--secondary {
			border: 1px solid #8c9196 !important;
			color: #2d7d7d !important;
			background: transparent !important;
			background-image: none !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-actions .aio-setup-btn--secondary:hover,
		html body.login.aio-login-pro-2fa-setup-prompt .aio-login-pro__2fa-setup-actions .aio-setup-btn--secondary:focus {
			background: rgba(45, 125, 125, 0.05) !important;
			border-color: #6d7175 !important;
			color: #1f5c5c !important;
			outline: none !important;
		}

		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-09 #login .aio-login-pro__2fa-setup-prompt-card p.aio-login-pro__2fa-setup-actions a.aio-setup-btn.aio-setup-btn--secondary {
			border: 1px solid #8c9196 !important;
			border-color: #8c9196 !important;
			color: #2d7d7d !important;
			background: transparent !important;
			background-image: none !important;
		}';
	wp_enqueue_style( 'login' );
	wp_register_style( 'aio-login-2fa-method-change', false, array( 'login' ), defined( 'AIO_LOGIN__VERSION' ) ? AIO_LOGIN__VERSION : null );
	wp_enqueue_style( 'aio-login-2fa-method-change' );
	wp_add_inline_style( 'aio-login-2fa-method-change', $aio_2fa_prompt_css );
	do_action( 'login_enqueue_scripts' );
	wp_print_styles();
	wp_print_head_scripts();
	do_action( 'login_head' );
	?>
</head>
<body class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $body_classes ) ) ); ?>">
	<?php do_action( 'login_header' ); ?>
	<div id="login">
		<h1 role="presentation" class="wp-login-logo"><a href="<?php echo esc_url( $login_header_url ); ?>"><?php echo esc_html( $login_header_text ); ?></a></h1>

		<div class="aio-login-pro__2fa-setup-prompt-card">
			<h2 class="aio-login-pro__2fa-setup-title">
				<?php esc_html_e( 'Authentication method changed', 'change-wp-admin-login' ); ?>
			</h2>
			<hr class="aio-login-pro__2fa-setup-divider" />
			<p class="aio-login-pro__2fa-setup-desc">
				<?php esc_html_e( 'Your site administrator updated which two-factor sign-in method is required.', 'change-wp-admin-login' ); ?>
				<br><br>
				<?php esc_html_e( 'Set up the new method to stay protected, or choose Do it later to remove your previous two-factor authentication method and continue.', 'change-wp-admin-login' ); ?>
			</p>
			<p class="aio-login-pro__2fa-setup-actions">
				<a class="aio-setup-btn aio-setup-btn--primary" href="<?php echo esc_url( $setup_url ); ?>">
					<?php echo esc_html( $setup_label ); ?>
				</a>
				<a class="aio-setup-btn aio-setup-btn--secondary" href="<?php echo esc_url( $later_url ); ?>">
					<?php esc_html_e( 'Do it later', 'change-wp-admin-login' ); ?>
				</a>
			</p>
		</div>
	</div>
	<?php do_action( 'login_footer' ); wp_print_footer_scripts(); ?>
</body>
</html>
