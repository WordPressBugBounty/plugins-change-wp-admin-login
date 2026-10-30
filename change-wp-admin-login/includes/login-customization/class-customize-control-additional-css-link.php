<?php
/**
 * Customizer control: open WordPress Additional CSS (no arbitrary CSS storage).
 *
 * @package AIO_Login
 */

namespace AIO_Login_Pro\Login_Customization;

defined( 'ABSPATH' ) || exit;

/**
 * Class Customize_Control_Additional_Css_Link
 */
class Customize_Control_Additional_Css_Link extends \WP_Customize_Control {

	/**
	 * Control type.
	 *
	 * @var string
	 */
	public $type = 'aio_login_additional_css_link';

	/**
	 * Render control markup.
	 */
	protected function render_content() {
		?>
		<?php if ( $this->label ) { ?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php } ?>
		<?php if ( $this->description ) { ?>
			<p class="description"><?php echo esc_html( $this->description ); ?></p>
		<?php } ?>
		<p>
			<button type="button" class="button button-primary aio-login-open-additional-css" id="aio-login-open-additional-css">
				<?php esc_html_e( 'Open Additional CSS', 'change-wp-admin-login' ); ?>
			</button>
		</p>
		<?php
	}
}
