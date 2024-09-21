<?php
/**
 * Class Login_Customization
 *
 * @package AIO Login
 */

namespace AIO_Login\Login_Customization;

use WpOrg\Requests\Exception;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Login_Customization\\Login_Customization' ) ) {
	/**
	 * Class Login_Customization
	 */
	class Login_Customization {
		/**
		 * Login_Customization constructor.
		 */
		private function __construct() {
			add_action( 'admin_init', array( $this, 'admin_init' ) );

			add_action( 'aio_login__tab_customization_logo', array( $this, 'login_customization_logo_content' ) );
			add_action( 'wp_ajax_aio_login_save_logo_options', array( $this, 'save_logo_options' ) );

			add_action( 'aio_login__tab_customization_background', array( $this, 'background_content' ) );
			add_action( 'wp_ajax_aio_login_save_background_options', array( $this, 'save_background_options' ) );

			add_action( 'aio_login__tab_customization_custom-css', array( $this, 'custom_css' ) );
			add_action( 'wp_ajax_aio_login_save_custom_css', array( $this, 'save_custom_css' ) );
		}

		/**
		 * Admin init.
		 */
		public function admin_init() {
			/**
			 * Logo.
			 */
			register_setting( 'aio_login__login-customization--logo', 'aio_login_logo' );
			register_setting( 'aio_login__login-customization--logo', 'aio_login_logo_url', 'esc_url' );
			register_setting( 'aio_login__login-customization--logo', 'aio_login_logo_width', 'absint' );
			register_setting( 'aio_login__login-customization--logo', 'aio_login_logo_height', 'absint' );
			register_setting( 'aio_login__login-customization--logo', 'aio_login_margin_bottom', 'absint' );

			add_settings_section(
				'aio_login_logo',
				__( 'Logo Customization', 'aio-login' ),
				'__return_null',
				'aio-login-setting&tab=login-customization&sub-tab=logo'
			);
			add_settings_field(
				'aio_login_logo',
				__( 'Logo', 'aio-login' ),
				array( $this, 'logo_field' ),
				'aio-login-setting&tab=login-customization&sub-tab=logo',
				'aio_login_logo',
				array( 'label_for' => 'aio_login_logo' )
			);
			add_settings_field(
				'aio_login_logo_url',
				__( 'Logo Redirect URL', 'aio-login' ),
				array( $this, 'logo_url_field' ),
				'aio-login-setting&tab=login-customization&sub-tab=logo',
				'aio_login_logo',
				array( 'label_for' => 'aio_login_logo_url' )
			);
			add_settings_field(
				'aio_login_logo_width',
				__( 'Logo Width', 'aio-login' ),
				array( $this, 'logo_width_field' ),
				'aio-login-setting&tab=login-customization&sub-tab=logo',
				'aio_login_logo',
				array( 'label_for' => 'aio_login_logo_width' )
			);
			add_settings_field(
				'aio_login_logo_height',
				__( 'Logo Height', 'aio-login' ),
				array( $this, 'logo_height_field' ),
				'aio-login-setting&tab=login-customization&sub-tab=logo',
				'aio_login_logo',
				array( 'label_for' => 'aio_login_logo_height' )
			);
			add_settings_field(
				'aio_login_margin_bottom',
				__( 'Margin Bottom', 'aio-login' ),
				array( $this, 'margin_bottom_field' ),
				'aio-login-setting&tab=login-customization&sub-tab=logo',
				'aio_login_logo',
				array( 'label_for' => 'aio_login_margin_bottom' )
			);

			/**
			 * Background.
			 */
			register_setting( 'aio_login__login-customization--background', 'aio_login_background_color' );
			register_setting( 'aio_login__login-customization--background', 'aio_login_background_image' );

			add_settings_section(
				'aio_login_background',
				__( 'Background Customization', 'aio-login' ),
				'__return_null',
				'aio-login-setting&tab=login-customization&sub-tab=background'
			);
			add_settings_field(
				'aio_login_background_color',
				__( 'Background Color', 'aio-login' ),
				array( $this, 'background_color_field' ),
				'aio-login-setting&tab=login-customization&sub-tab=background',
				'aio_login_background',
				array( 'label_for' => 'aio_login_background_color' )
			);
			add_settings_field(
				'aio_login_background_image',
				__( 'Background Image', 'aio-login' ),
				array( $this, 'background_image_field' ),
				'aio-login-setting&tab=login-customization&sub-tab=background',
				'aio_login_background',
				array( 'label_for' => 'aio_login_background_image' )
			);

			/**
			 * Custom CSS.
			 */
			register_setting( 'aio_login__login-customization--custom-css', 'aio_login_custom-css' );

			add_settings_section(
				'aio_login_custom-css',
				__( 'Custom CSS', 'aio-login' ),
				array( $this, 'custom_css_section' ),
				'aio-login-setting&tab=login-customization&sub-tab=custom-css'
			);
			add_settings_field(
				'aio_login_custom-css',
				__( 'Custom CSS', 'aio-login' ),
				array( $this, 'custom_css_field' ),
				'aio-login-setting&tab=login-customization&sub-tab=custom-css',
				'aio_login_custom-css',
				array( 'label_for' => 'aio_login_custom-css' )
			);
		}

		/**
		 * Logo field.
		 *
		 * @param array $args Arguments.
		 */
		public function logo_field( $args = array() ) {
			$media_id = get_option( $args['label_for'], false );
			$logo_url = self::file_exists( $media_id, true );

			echo '<aio-login-media-uploader
				id="' . esc_attr( $args['label_for'] ) . '"
				image="' . esc_url( $logo_url ) . '"
				default-img="' . esc_url( admin_url( 'images/wordpress-logo.svg' ) ) . '"
				value="' . esc_attr( $media_id ) . '"
				title="Upload Logo"
			></aio-login-media-uploader>';
		}

		/**
		 * Logo URL field.
		 *
		 * @param array $args Arguments.
		 */
		public function logo_url_field( $args = array() ) {
			$logo_url = get_option( $args['label_for'], home_url() );
			echo '<input type="text" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" value="' . esc_url( $logo_url ) . '" class="regular-text">';
		}

		/**
		 * Logo width field.
		 *
		 * @param array $args Arguments.
		 */
		public function logo_width_field( $args = array() ) {
			$logo_width = get_option( $args['label_for'], 84 );
			if ( empty( $logo_width ) ) {
				$logo_width = 84;
			}
			echo '<input max="400" min="0" type="number" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" value="' . esc_attr( $logo_width ) . '" class="small-text"> px';
		}

		/**
		 * Logo height field.
		 *
		 * @param array $args Arguments.
		 */
		public function logo_height_field( $args = array() ) {
			$logo_height = get_option( $args['label_for'], 84 );
			if ( empty( $logo_height ) ) {
				$logo_height = 84;
			}
			echo '<input max="350" min="0" type="number" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" value="' . esc_attr( $logo_height ) . '" class="small-text"> px';
		}

		/**
		 * Margin bottom field.
		 *
		 * @param array $args Arguments.
		 */
		public function margin_bottom_field( $args = array() ) {
			$margin_bottom = get_option( $args['label_for'], 0 );
			echo '<input max="100" type="number" name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" value="' . esc_attr( $margin_bottom ) . '" class="small-text"> px';
		}

		/**
		 * Background color field.
		 *
		 * @param array $args Arguments.
		 */
		public function background_color_field( $args = array() ) {
			$background_color = get_option( $args['label_for'], '#f1f1f1' );
			echo '<aio-login-color-picker
				id="' . esc_attr( $args['label_for'] ) . '"
				value="' . esc_attr( $background_color ) . '"
				name="' . esc_attr( $args['label_for'] ) . '"
			></aio-login-color-picker>';
		}

		/**
		 * Background image field.
		 *
		 * @param array $args Arguments.
		 */
		public function background_image_field( $args = array() ) {
			$media_id         = get_option( $args['label_for'], false );
			$background_image = self::file_exists( $media_id, false );

			echo '<aio-login-media-uploader
				id="' . esc_attr( $args['label_for'] ) . '"
				default-img=""
				image="' . esc_attr( $background_image ) . '"
				value="' . esc_attr( $media_id ) . '"
				title="Upload Background"
			></aio-login-media-uploader>';
		}

		/**
		 * Custom CSS section.
		 */
		public function custom_css_section() {
			echo '<p>' . esc_attr__( 'Custom CSS for login page.', 'aio-login' ) . '</p>';
			echo '<p><strong>'
				. sprintf(
					// translators: %1$s is <style></style> tag.
					esc_attr__( 'Enter your custom CSS without adding %1$s tag', 'aio-login' ),
					'<code>&lt;style&gt;&lt;/style&gt;</code>'
				) .
			'</strong></p>';
		}

		/**
		 * Custom CSS field.
		 *
		 * @param array $args Arguments.
		 */
		public function custom_css_field( $args = array() ) {
			$custom_css = get_option( $args['label_for'] );
			echo '<textarea name="' . esc_attr( $args['label_for'] ) . '" id="' . esc_attr( $args['label_for'] ) . '" class="aio-login__ace-css--editor">' . esc_textarea( $custom_css ) . '</textarea>';
		}

		/**
		 * Login customization logo content.
		 */
		public function login_customization_logo_content() {
			echo '<aio-login-settings-form action="aio_login_save_logo_options">
                <template v-slot:settings-fields>';
				settings_fields( 'aio_login__login-customization--logo' );
				do_settings_sections( 'aio-login-setting&tab=login-customization&sub-tab=logo' );
			echo '</template>
            </aio-login-settings-form>';
		}

		/**
		 * Save logo options.
		 */
		public function save_logo_options() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'aio-login' ) );
			}

			if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'aio_login__login-customization--logo-options' ) ) {
				if ( isset( $_POST['aio_login_logo'] ) ) {
					update_option( 'aio_login_logo', sanitize_text_field( wp_unslash( $_POST['aio_login_logo'] ) ) );
				}

				if ( isset( $_POST['aio_login_logo_url'] ) ) {
					update_option( 'aio_login_logo_url', esc_url( wp_unslash( $_POST['aio_login_logo_url'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				}

				if ( isset( $_POST['aio_login_logo_width'] ) ) {
					update_option( 'aio_login_logo_width', absint( wp_unslash( $_POST['aio_login_logo_width'] ) ) );
				}

				if ( isset( $_POST['aio_login_logo_height'] ) ) {
					update_option( 'aio_login_logo_height', absint( wp_unslash( $_POST['aio_login_logo_height'] ) ) );
				}

				if ( isset( $_POST['aio_login_margin_bottom'] ) ) {
					update_option( 'aio_login_margin_bottom', absint( wp_unslash( $_POST['aio_login_margin_bottom'] ) ) );
				}

				wp_send_json_success( array( 'message' => esc_attr__( 'Logo options saved successfully', 'aio-login' ) ) );
			}
			exit( 0 );
		}

		/**
		 * Background content.
		 */
		public function background_content() {
			echo '<aio-login-settings-form action="aio_login_save_background_options">
                <template v-slot:settings-fields>';
					settings_fields( 'aio_login__login-customization--background' );
					do_settings_sections( 'aio-login-setting&tab=login-customization&sub-tab=background' );
			echo '</template>
            </aio-login-settings-form>';
		}

		/**
		 * Save background options.
		 */
		public function save_background_options() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'aio-login' ) );
			}

			if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'aio_login__login-customization--background-options' ) ) {
				if ( isset( $_POST['aio_login_background_color'] ) ) {
					update_option( 'aio_login_background_color', sanitize_text_field( wp_unslash( $_POST['aio_login_background_color'] ) ) );
				}

				if ( isset( $_POST['aio_login_background_image'] ) ) {
					update_option( 'aio_login_background_image', absint( wp_unslash( $_POST['aio_login_background_image'] ) ) );
				}

				wp_send_json_success( array( 'message' => esc_attr__( 'Background options saved successfully.', 'aio-login' ) ) );
			}
			exit( 0 );
		}

		/**
		 * Custom CSS.
		 */
		public function custom_css() {
			echo '<aio-login-settings-form action="aio_login_save_custom_css">
                <template v-slot:settings-fields>';
					settings_fields( 'aio_login__login-customization--custom-css' );
					do_settings_sections( 'aio-login-setting&tab=login-customization&sub-tab=custom-css' );
			echo '</template>
            </aio-login-settings-form>';
		}

		/**
		 * Save custom CSS.
		 */
		public function save_custom_css() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'aio-login' ) );
			}

			if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'aio_login__login-customization--custom-css-options' ) ) {
				if ( isset( $_POST['aio_login_custom-css'] ) ) {
					update_option( 'aio_login_custom-css', sanitize_text_field( wp_unslash( $_POST['aio_login_custom-css'] ) ) );
				}

				wp_send_json_success( array( 'message' => esc_attr__( 'Custom CSS saved successfully.', 'aio-login' ) ) );
			}
			exit( 0 );
		}

		/**
		 * Check if file exists.
		 *
		 * @param int  $file_id File.
		 * @param bool $logo Logo.
		 *
		 * @return string
		 */
		public static function file_exists( $file_id, $logo = false ) {
			$file_path = get_attached_file( $file_id );
			if ( ! file_exists( $file_path ) ) {
				if ( $logo ) {
					return admin_url( 'images/wordpress-logo.svg' );
				}

				return '';
			}

			return wp_get_attachment_url( $file_id );
		}

		/**
		 * Get instance.
		 *
		 * @return Login_Customization
		 */
		public static function get_instance() {
			static $instance = null;

			if ( null === $instance ) {
				$instance = new Login_Customization();
			}

			return $instance;
		}
	}
}
