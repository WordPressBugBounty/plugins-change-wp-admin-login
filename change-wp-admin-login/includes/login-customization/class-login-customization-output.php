<?php
/**
 * Class Login_Customization_Output
 *
 * @package AIO Login
 */

namespace AIO_Login\Login_Customization;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Login_Customization\\Login_Customization_Output' ) ) {
	/**
	 * Class Login_Customization_Output
	 */
	class Login_Customization_Output {
		/**
		 * Logo.
		 *
		 * @var int $logo Logo.
		 */
		private $logo;

		/**
		 * Logo URL.
		 *
		 * @var string $logo_url Logo URL.
		 */
		private $logo_url;

		/**
		 * Logo width.
		 *
		 * @var int $logo_width Logo width.
		 */
		private $logo_width;

		/**
		 * Logo height.
		 *
		 * @var int $logo_height Logo height.
		 */
		private $logo_height;

		/**
		 * Logo margin bottom.
		 *
		 * @var int $logo_margin_bottom Logo margin bottom.
		 */
		private $logo_margin_bottom;

		/**
		 * Background color.
		 *
		 * @var string $background_color Background color.
		 */
		private $background_color;

		/**
		 * Background image.
		 *
		 * @var string $background_image Background image.
		 */
		private $background_image;

		/**
		 * Login_Customization_Output constructor.
		 */
		private function __construct() {

			$this->logo               = get_option( 'aio_login_logo', false );
			$this->logo_url           = get_option( 'aio_login_logo_url', '' );
			$this->logo_width         = get_option( 'aio_login_logo_width', '' );
			$this->logo_height        = get_option( 'aio_login_logo_height', '' );
			$this->logo_margin_bottom = get_option( 'aio_login_margin_bottom', '' );

			$this->background_color = get_option( 'aio_login_background_color', '' );
			$this->background_image = get_option( 'aio_login_background_image', false );

			$this->logo             = Login_Customization::file_exists( $this->logo, true );
			$this->background_image = Login_Customization::file_exists( $this->background_image );

			add_action( 'login_enqueue_scripts', array( $this, 'login_output' ), 15 );
			// After template CSS is registered so Additional CSS prints last and overrides template !important rules.
			add_action( 'login_enqueue_scripts', array( $this, 'output_additional_login_css_last' ), 999 );
			add_filter( 'login_headerurl', array( $this, 'login_header_url' ) );
			add_filter( 'login_headertext', array( $this, 'login_header_text' ) );
		}

		/**
		 * Login output.
		 */
		public function login_output() {
			$custom_css = '';

			$disable_logo = get_option( 'aio_login_disable_logo', false );
			$logo_hidden  = ( true === $disable_logo || 1 === $disable_logo || '1' === $disable_logo || 'on' === strtolower( (string) $disable_logo ) );

			if ( ! $logo_hidden && ! empty( $this->logo ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						background-image: url(' . esc_url( $this->logo ) . ');
						background-size: contain;
					}
				';
			}

			if ( ! $logo_hidden && ! empty( $this->logo_width ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						width: ' . esc_attr( $this->logo_width ) . 'px;
					}
				';
			}

			if ( ! $logo_hidden && ! empty( $this->logo_height ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						height: ' . esc_attr( $this->logo_height ) . 'px;
					}
				';
			}

			if ( ! $logo_hidden && ! empty( $this->logo_margin_bottom ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						margin-bottom: ' . esc_attr( $this->logo_margin_bottom ) . 'px;
					}
				';
			}

			// Skip default gray only when selected template uses a dark body (option slugs for template-02/05/08).
			$dark_template_slugs = array( 'template-2', 'template-4', 'template-7' );
			$tpl_key             = get_option( 'aio_login__customization_templates', 'default' );
			$is_dark_body        = in_array( (string) $tpl_key, $dark_template_slugs, true );
			$bg_trim             = trim( (string) $this->background_color );
			$is_default_wp_gray  = ( '' !== $bg_trim && 0 === strcasecmp( $bg_trim, '#f1f1f1' ) );
			if ( ! empty( $this->background_color ) && ( ! $is_default_wp_gray || ! $is_dark_body ) ) {
				$custom_css .= '
					body.login {
						background-color: ' . esc_attr( $this->background_color ) . ';
					}
				';
			}

			if ( ! empty( $this->background_image ) ) {
				$custom_css .= '
					body.login {
						background-image: url(' . esc_url( $this->background_image ) . ');
						background-size: cover;
						background-position: center;
						background-repeat: no-repeat;
						
					}
				';
			}

			$custom_css = apply_filters( 'aio_login__custom_css', $custom_css );

			if ( ! empty( $custom_css ) ) {
				// Print on the AIO template handle when present so rules win over template .css (e.g. template-09
				// logo 100px !important). Inline on `login` loads before aio-login-*-template and was overridden.
				$handle = 'login';
				if ( wp_style_is( 'aio-login-pro-template', 'enqueued' ) ) {
					$handle = 'aio-login-pro-template';
				} elseif ( wp_style_is( 'aio-login-free-template', 'enqueued' ) ) {
					$handle = 'aio-login-free-template';
				}
				/**
				 * Style handle for merged login customization CSS (free base + aio_login__custom_css filter).
				 *
				 * @param string $handle      Registered style handle.
				 * @param string $custom_css  Full CSS string about to be printed.
				 */
				$handle = apply_filters( 'aio_login__custom_css_style_handle', $handle, $custom_css );
				if ( is_string( $handle ) && '' !== $handle ) {
					wp_add_inline_style( $handle, $custom_css );
				}
			}
		}

		/**
		 * Output "Additional CSS" on the last AIO template stylesheet handle so it always wins over template files.
		 *
		 * When rules were inlined on `login`, the template file (`aio-login-*-template`) loaded after `login`
		 * and could override the same specificity / !important as user rules.
		 *
		 * @return void
		 */
		public function output_additional_login_css_last() {
			$css = get_option( 'aio_login_custom-css', '' );
			if ( ! is_string( $css ) || '' === trim( $css ) ) {
				return;
			}

			$handle = 'login';
			if ( wp_style_is( 'aio-login-pro-template', 'enqueued' ) ) {
				$handle = 'aio-login-pro-template';
			} elseif ( wp_style_is( 'aio-login-free-template', 'enqueued' ) ) {
				$handle = 'aio-login-free-template';
			}

			/**
			 * Which style handle Additional CSS is printed on (default: template handle, else `login`).
			 *
			 * @param string $handle Style handle.
			 * @param string $css    Raw Additional CSS.
			 */
			$handle = apply_filters( 'aio_login_additional_css_style_handle', $handle, $css );

			if ( ! is_string( $handle ) || '' === $handle ) {
				return;
			}

			wp_add_inline_style( $handle, trim( $css ) );
		}

		/**
		 * Login header URL.
		 *
		 * @param string $url URL.
		 *
		 * @return string
		 */
		public function login_header_url( $url ) {
			if ( ! empty( $this->logo_url ) ) {
				$url = $this->logo_url;
			}
			return $url;
		}

		/**
		 * Login logo link text (core prints this inside the anchor; also used for accessibility).
		 *
		 * @param string $text Default text from core.
		 * @return string
		 */
		public function login_header_text( $text ) {
			$custom = get_option( 'aio_login_logo_title', '' );
			return ! empty( $custom ) ? sanitize_text_field( $custom ) : $text;
		}

		/**
		 * Get instance.
		 *
		 * @return Login_Customization_Output
		 */
		public static function get_instance() {
			static $instance = null;

			if ( null === $instance ) {
				$instance = new Login_Customization_Output();
			}

			return $instance;
		}
	}
}
