<?php
/**
 * Template Settings
 *
 * @package AIO Login
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="aio-login__preloader" id="aio-login__preloader">

</div>

<div class="aio-login__dashboard-wrapper" id="aio-login__app">

	<div class="aio-login__header-navigation">
		<div class="aio-login__header-logo">
			<img src="<?php echo esc_url( AIO_LOGIN__DIR_URL . 'assets/images/dashboard-logo.png' ); ?>" alt="AIO Login">
		</div>
		<div class="aio-login__header-version">
			<p><?php esc_attr_e( 'Version:', 'aio-login' ); ?> <?php echo esc_attr( AIO_LOGIN__VERSION ); ?></p>
		</div>
	</div>

	<div class="aio-login__container">

		<?php require_once AIO_LOGIN__DIR_PATH . 'includes/admin/settings/navigation.php'; ?>

		<div class="aio-login__content-container">

			<?php require_once AIO_LOGIN__DIR_PATH . 'includes/admin/settings/submenu.php'; ?>

			<div class="aio-login__content-wrapper <?php echo ( 'getpro' === $setting_tab_slug ) ? 'aio-login__getpro' : ''; ?> ">

					<?php settings_errors(); ?>

					<?php if ( ! empty( $setting_tab_slug ) ) : ?>
						<?php if ( ! empty( $setting_sub_tab_slug ) ) : ?>
							<?php do_action( 'aio_login__tab_' . $setting_tab_slug . '_' . $setting_sub_tab_slug ); ?>
						<?php else : ?>
							<?php do_action( 'aio_login__tab_' . $setting_tab_slug ); ?>
						<?php endif; ?>
					<?php endif; ?>

			</div>
		</div>

		<?php do_action( 'aio_login__footer' ); ?>

	</div>

	<?php if ( ! AIO_Login\AIO_Login::has_pro() ) : ?>
		<div v-if="pro_popup" class="aio-login__pro-box-wrapper">
			<div class="aio-login__box-banner">
				<div class="aio-login__close-button" @click="close_popup">
					<button>&times;</button>
				</div>
				<div class="aio-login__box-banner-content">
					<div>
						<img src="<?php echo esc_url( AIO_LOGIN__DIR_URL ); ?>assets/images/aio-logo.png" alt="Pro Logo">

						<h1><?php esc_attr_e( 'To access more features and options', 'aio-login' ); ?></h1>
					</div>
					<div class="mt-40">
						<a href="https://aiologin.com/pricing/?utm_source=plugin&utm_medium=pro_pop_up&utm_campaign=plugin" class="aio-login__button aio-login__pro" target="_blank">
							<?php esc_attr_e( 'Get AIO Login Pro', 'aio-login' ); ?>
						</a>
					</div>
				</div>
			</div>

		</div>

	<?php endif; ?>

	<aio-login-snackbar v-if="snackbar.show" :message="snackbar.message" :type="snackbar.type" v-on:close-snackbar="close_snackbar"></aio-login-snackbar>
</div>

