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
		 * Custom CSS.
		 *
		 * @var string Custom CSS.
		 */
		private $custom_css;

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

			$this->custom_css = get_option( 'aio_login_custom-css', '' );

			add_action( 'login_enqueue_scripts', array( $this, 'login_output' ), 15 );
			add_filter( 'login_headerurl', array( $this, 'login_header_url' ) );
		}

		/**
		 * Login output.
		 */
		public function login_output() {
			$custom_css = '';

			if ( ! empty( $this->logo ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						background-image: url(' . esc_url( $this->logo ) . ');
						background-size: contain;
					}
				';
			}

			if ( ! empty( $this->logo_width ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						width: ' . esc_attr( $this->logo_width ) . 'px;
					}
				';
			}

			if ( ! empty( $this->logo_height ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						height: ' . esc_attr( $this->logo_height ) . 'px;
					}
				';
			}

			if ( ! empty( $this->logo_margin_bottom ) ) {
				$custom_css .= '
					.login .wp-login-logo a {
						margin-bottom: ' . esc_attr( $this->logo_margin_bottom ) . 'px;
					}
				';
			}

			if ( ! empty( $this->background_color ) ) {
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

			if ( ! empty( $this->custom_css ) ) {
				$custom_css .= $this->custom_css;
			}

			$custom_css = apply_filters( 'aio_login__custom_css', $custom_css );

			if ( ! empty( $custom_css ) ) {
				wp_add_inline_style( 'login', $custom_css );
			}
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
