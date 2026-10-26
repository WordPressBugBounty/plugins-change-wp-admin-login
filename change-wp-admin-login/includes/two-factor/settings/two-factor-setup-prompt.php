<?php
/**
 * Post-login “Set up 2FA” screen on the WordPress login page (same shell as OTP challenge).
 *
 * @package AIO_Login_Pro
 */

defined( 'ABSPATH' ) || exit;

$current_user_id = get_current_user_id();
$required_method = \AIO_Login\Two_Factor\Two_Factor::get_required_site_method_for_user( $current_user_id );
$security_update_required = \AIO_Login\Two_Factor\Two_Factor::is_security_update_required_for_user( $current_user_id );
$show_later_button = ! $security_update_required;

// Free has no grace period — hide "Do it later" under strict role enforcement.
if ( $show_later_button ) {
	$enforce_all      = 'on' === get_option( 'aio_login_pro__2fa_policy_enforce_all', 'off' );
	$enforce_specific = 'on' === get_option( 'aio_login_pro__2fa_policy_enforce_specific', 'off' );
	$strict_enforced  = false;

	if ( $enforce_all ) {
		$strict_enforced = true;
	} elseif ( $enforce_specific ) {
		$selected_roles = array_values(
			array_filter(
				array_map( 'sanitize_key', (array) get_option( 'aio_login_pro__2fa_policy_roles', array() ) )
			)
		);
		$user = get_userdata( (int) $current_user_id );
		if ( ! empty( $selected_roles ) && $user && ! empty( $user->roles ) ) {
			$strict_enforced = (bool) array_intersect( (array) $user->roles, $selected_roles );
		}
	}

	if ( $strict_enforced ) {
		$show_later_button = false;
	}
}
$setup_button_text = __( 'Set up Email One-Time Code', 'change-wp-admin-login' );
if ( 'email' !== $required_method && '' !== $required_method ) {
	$setup_button_text = __( 'Setup 2FA Now', 'change-wp-admin-login' );
}

$setup_url = admin_url( 'admin.php?page=aio-login&tab=2fa' );
$later_url = wp_nonce_url(
	add_query_arg( 'aio_login_pro_2fa_prompt', 'later', admin_url( 'index.php' ) ),
	'aio_login_pro_2fa_prompt'
);

$body_classes = apply_filters( 'login_body_class', array( 'login', 'login-action-login', 'wp-core-ui', 'aio-login-pro-2fa-setup-prompt' ), array() );
if ( ! is_array( $body_classes ) ) {
	$body_classes = array( 'login', 'login-action-login', 'wp-core-ui', 'aio-login-pro-2fa-setup-prompt' );
}

// Same filters as wp-login.php — themes/plugins customize logo URL / image via CSS on this block.
$login_header_url   = apply_filters( 'login_headerurl', 'https://wordpress.org/' );
$login_header_title = '';
$login_header_title = apply_filters_deprecated(
	'login_headertitle',
	array( $login_header_title ),
	'5.2.0',
	'login_headertext',
	__( 'Usage of the title attribute on the login logo is not recommended for accessibility reasons. Use the link text instead.', 'change-wp-admin-login' )
);
$login_header_text = empty( $login_header_title ) ? __( 'Powered by WordPress', 'change-wp-admin-login' ) : $login_header_title;
$login_header_text = apply_filters( 'login_headertext', $login_header_text );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( __( 'Set up Two-Factor Authentication', 'change-wp-admin-login' ) . ' &lsaquo; ' . get_bloginfo( 'name', 'display' ) ); ?></title>
	<?php
	$aio_2fa_prompt_css = '/**
		 * Last in document so ghost buttons beat core .button rules.
		 * Do NOT strip template backgrounds, ::after heroes, or #login grid — only place the 2FA
		 * block like the login form (e.g. template-09 column 2).
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

		/*
		 * Template-09: hide logo link text so AIO background-image shows (same idea as core .login h1 a).
		 * Do not touch background-image — Login Customizer inline CSS must win.
		 */
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

		/* --- Template-06: same column as form (left copy / right image) --- */
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

		/* --- Template-03 (default): card matches form chrome --- */
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-03 .aio-login-pro__2fa-setup-prompt-card {
			margin-top: 20px !important;
			padding: 26px 24px 34px !important;
			background: #fff !important;
			border: 1px solid #c3c4c7 !important;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
			border-radius: 4px !important;
			box-sizing: border-box !important;
		}

		/* --- Template-07: #login is already the white card --- */
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-07 .aio-login-pro__2fa-setup-prompt-card {
			background: transparent !important;
			border: none !important;
			box-shadow: none !important;
			padding: 0 !important;
			margin: 0 !important;
			box-sizing: border-box !important;
		}

		/* --- Template-08 / 04 / 05: frosted or photo bg — light copy like form labels --- */
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

		/* --- Template-02: night --- */
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

		/* --- Template-01: match form width --- */
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

		/* --- Shared base (do not set display — template-09 uses flex on the card) --- */
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

		/* Beat template rules on #login links (higher specificity than body... #login h1 a). */
		html body.login.aio-login-pro-2fa-setup-prompt.aio-login__template-09 #login .aio-login-pro__2fa-setup-prompt-card p.aio-login-pro__2fa-setup-actions a.aio-setup-btn.aio-setup-btn--secondary {
			border: 1px solid #8c9196 !important;
			border-color: #8c9196 !important;
			color: #2d7d7d !important;
			background: transparent !important;
			background-image: none !important;
		}';
	wp_enqueue_style( 'login' );
	wp_register_style( 'aio-login-2fa-setup-prompt', false, array( 'login' ), defined( 'AIO_LOGIN__VERSION' ) ? AIO_LOGIN__VERSION : null );
	wp_enqueue_style( 'aio-login-2fa-setup-prompt' );
	wp_add_inline_style( 'aio-login-2fa-setup-prompt', $aio_2fa_prompt_css );
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
				<?php echo $security_update_required ? esc_html__( 'Security Update Required', 'change-wp-admin-login' ) : esc_html__( 'Set up Two-Factor Authentication', 'change-wp-admin-login' ); ?>
			</h2>
			<hr class="aio-login-pro__2fa-setup-divider" />
			<p class="aio-login-pro__2fa-setup-desc">
				<?php if ( $security_update_required ) : ?>
					<?php esc_html_e( 'Your current authentication method no longer meets the site\'s security requirements.', 'change-wp-admin-login' ); ?>
					<br><br>
					<?php esc_html_e( 'Please configure a new authentication method to continue.', 'change-wp-admin-login' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Before continuing, please secure your account by setting up 2FA.', 'change-wp-admin-login' ); ?>
				<?php endif; ?>
			</p>
			<p class="aio-login-pro__2fa-setup-actions">
				<a class="aio-setup-btn aio-setup-btn--primary" href="<?php echo esc_url( $setup_url ); ?>">
					<?php echo esc_html( $setup_button_text ); ?>
				</a>
				<?php if ( $show_later_button ) : ?>
					<a class="aio-setup-btn aio-setup-btn--secondary" href="<?php echo esc_url( $later_url ); ?>">
						<?php esc_html_e( 'Do it later', 'change-wp-admin-login' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>
	</div>
	<?php do_action( 'login_footer' ); wp_print_footer_scripts(); ?>
</body>
</html>
