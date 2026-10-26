<?php
/**
 * Customizer control: login templates as a visual grid (not a select dropdown).
 *
 * @package AIO_Login_Pro
 */

namespace AIO_Login_Pro\Login_Customization;

defined( 'ABSPATH' ) || exit;

/**
 * Class Customize_Control_Template_Grid
 */
class Customize_Control_Template_Grid extends \WP_Customize_Control {

	/**
	 * Control type.
	 *
	 * @var string
	 */
	public $type = 'aio_login_template_grid';

	/**
	 * Template slug => label.
	 *
	 * @var array<string, string>
	 */
	public $choices = array();

	/**
	 * Whether Pro is active (unlocks non-free templates).
	 *
	 * @var bool
	 */
	public $has_pro = true;

	/**
	 * Template slugs usable without Pro (Modern Center + Future Tech).
	 *
	 * @var array<int, string>
	 */
	public $free_slugs = array( 'default', 'template-8' );

	/**
	 * Render control markup (Customizer left panel).
	 */
	protected function render_content() {
		$input_id = '_customize-input-' . $this->id;
		$value    = $this->value();

		if ( ! isset( $this->choices[ $value ] ) ) {
			$value = isset( $this->choices['default'] ) ? 'default' : ( $this->free_slugs[0] ?? '' );
			if ( ! isset( $this->choices[ $value ] ) ) {
				foreach ( $this->choices as $k => $_ ) {
					$value = $k;
					break;
				}
			}
		}

		$catalog_rest = array();
		foreach ( $this->choices as $slug => $label ) {
			if ( (string) $slug !== (string) $value ) {
				$catalog_rest[ $slug ] = $label;
			}
		}

		$catalog_free = array();
		$catalog_pro  = array();
		foreach ( $catalog_rest as $slug => $label ) {
			if ( in_array( (string) $slug, $this->free_slugs, true ) ) {
				$catalog_free[ $slug ] = $label;
			} else {
				$catalog_pro[ $slug ] = $label;
			}
		}

		?>
		<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php if ( ! empty( $this->description ) ) : ?>
			<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
		<?php endif; ?>
		<div class="aio-login__customizer-template-grid-wrap">
			<div class="aio-login__customizer-template-grid aio-login__customizer-template-grid--selected" role="list" aria-label="<?php esc_attr_e( 'Selected template', 'change-wp-admin-login' ); ?>">
				<?php
				if ( isset( $this->choices[ $value ] ) ) {
					$this->render_template_button( (string) $value, (string) $this->choices[ $value ], $value, false );
				}
				?>
			</div>
			<?php if ( count( $this->choices ) > 1 ) : ?>
				<?php
				// Guideline 5: Free never renders locked Pro template teasers — only unlocked choices.
				$catalog_to_show = $this->has_pro ? $catalog_rest : $catalog_free;
				?>
				<?php if ( ! empty( $catalog_to_show ) ) : ?>
			<div class="aio-login__customizer-pro-gate-area">
				<div class="aio-login__customizer-template-grid aio-login__customizer-template-grid--catalog" role="list" aria-label="<?php esc_attr_e( 'Login design templates', 'change-wp-admin-login' ); ?>">
					<?php
					foreach ( $catalog_to_show as $slug => $label ) {
						$this->render_template_button( (string) $slug, (string) $label, $value, false );
					}
					?>
				</div>
			</div>
				<?php endif; ?>
			<?php endif; ?>
			<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" <?php $this->link(); ?> value="<?php echo esc_attr( $value ); ?>" />
		</div>
		<?php
	}

	/**
	 * Single template tile; Pro-only catalog row uses a unified overlay (free row has no overlay).
	 *
	 * @param string $slug Template slug.
	 * @param string $label Label.
	 * @param string $value Current value.
	 * @param bool   $is_locked Whether Pro is required.
	 */
	private function render_template_button( $slug, $label, $value, $is_locked ) {
		$thumb = self::thumb_url_for_slug( $slug );
		$sel   = ( $value === $slug );
		?>
		<button type="button" role="listitem" class="aio-login__customizer-template-item<?php echo $sel ? ' is-selected' : ''; ?><?php echo $is_locked ? ' is-locked' : ''; ?>" data-value="<?php echo esc_attr( $slug ); ?>" aria-pressed="<?php echo $sel ? 'true' : 'false'; ?>"<?php echo $is_locked ? ' aria-disabled="true"' : ''; ?>>
			<span class="aio-login__customizer-template-thumb-wrap">
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" decoding="async" />
				<?php else : ?>
					<span class="aio-login__customizer-template-fallback"><?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( (string) $label, 0, 1 ) : substr( (string) $label, 0, 1 ) ); ?></span>
				<?php endif; ?>
			</span>
			<span class="aio-login__customizer-template-name"><?php echo esc_html( $label ); ?></span>
		</button>
		<?php
	}

	/**
	 * Preview image URL for a template slug.
	 * Free thumbs ship in Free; premium thumbs 01–07 live in Pro assets (Guideline 5).
	 *
	 * @param string $slug Setting value e.g. template-8.
	 * @return string
	 */
	public static function thumb_url_for_slug( $slug ) {
		$slug       = (string) $slug;
		$free_slugs = array( 'default', 'template-8' );
		$base       = '';

		if ( in_array( $slug, $free_slugs, true ) && defined( 'AIO_LOGIN__DIR_URL' ) ) {
			$base = AIO_LOGIN__DIR_URL;
		} elseif ( defined( 'AIO_LOGIN_PRO__DIR_URL' ) ) {
			$base = AIO_LOGIN_PRO__DIR_URL;
		} elseif ( defined( 'AIO_LOGIN__DIR_URL' ) ) {
			$base = AIO_LOGIN__DIR_URL;
		}

		/**
		 * Filter template thumbnail base URL / full URL resolution.
		 *
		 * @param string $base Plugin assets base URL.
		 * @param string $slug Template slug.
		 */
		$base = (string) apply_filters( 'aio_login_customizer_template_thumb_base', $base, $slug );

		if ( '' === $base ) {
			return '';
		}

		$map = array(
			'default'    => 'assets/images/templates/default.jpg',
			'template-1' => 'assets/images/templates/template-01.jpg',
			'template-2' => 'assets/images/templates/template-02.jpg',
			'template-3' => 'assets/images/templates/template-03.png',
			'template-4' => 'assets/images/templates/template-04.png',
			'template-5' => 'assets/images/templates/template-05.png',
			'template-6' => 'assets/images/templates/template-06.png',
			'template-7' => 'assets/images/templates/template-07.png',
			'template-8' => 'assets/images/templates/template-08.png',
		);

		/**
		 * Filter relative thumb path map (Pro may extend).
		 *
		 * @param array<string, string> $map Slug => relative path under plugin root.
		 */
		$map = apply_filters( 'aio_login_customizer_template_thumb_map', $map );

		if ( ! isset( $map[ $slug ] ) ) {
			$url = '';
		} else {
			$url = $base . $map[ $slug ];
		}

		/**
		 * Filter final template thumbnail URL.
		 *
		 * @param string $url  Absolute URL or empty.
		 * @param string $slug Template slug.
		 */
		return (string) apply_filters( 'aio_login_customizer_template_thumb_url', $url, $slug );
	}
}
