<?php
/**
 * AIO Login Network Policies — native WordPress Network Settings format.
 *
 * @package AIO Login
 */

namespace AIO_Login\Multisite;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Multisite\\Network_WP_Settings' ) ) {
	/**
	 * Class Network_WP_Settings
	 */
	class Network_WP_Settings {

		/**
		 * Constructor.
		 */
		private function __construct() {
			if ( ! is_multisite() ) {
				return;
			}

			Network_Settings::bootstrap_option_hooks();

			// Full plugin menu is registered by Admin::network_admin_menu.
			// Policies live only on core Network Settings (not under AIO Login menu).
			add_action( 'wpmu_options', array( $this, 'render_policies_section' ) );
			add_action( 'update_wpmu_options', array( $this, 'save_policies' ) );
			add_action( 'network_admin_notices', array( $this, 'maybe_show_save_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			// Old bookmarks: /admin.php?page=aio-login-network-policies → Network Settings.
			add_action( 'network_admin_menu', array( $this, 'redirect_legacy_policies_page' ), 99 );
		}

		/**
		 * Redirect removed AIO Login → Network Policies submenu to Network Settings.
		 */
		public function redirect_legacy_policies_page() {
			if ( ! is_network_admin() ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			if ( 'aio-login-network-policies' !== $page ) {
				return;
			}
			wp_safe_redirect( network_admin_url( 'settings.php#aio-login-network-policies-heading' ) );
			exit;
		}

		/**
		 * @deprecated Kept for compatibility; use Network Admin → AIO Login instead.
		 */
		public function redirect_to_main_site_dashboard() {
			if ( ! current_user_can( 'manage_network_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage network options.', 'change-wp-admin-login' ) );
			}
			wp_safe_redirect( network_admin_url( 'admin.php?page=aio-login' ) );
			exit;
		}

		/**
		 * Minimal styles for Figma Site Override table/info box.
		 *
		 * @param string $hook Hook.
		 */
		/**
		 * Network Settings scripts (moved out of HEREDOC inline blocks).
		 *
		 * @param string $hook Hook.
		 */
		public function enqueue_scripts( $hook ) {
			if ( ! is_network_admin() || ! $this->can_manage() ) {
				return;
			}
			if ( 'settings.php' !== $hook ) {
				return;
			}

			$script_path = AIO_LOGIN__DIR_PATH . 'assets/js/network-wp-settings.js';
			$script_ver  = file_exists( $script_path ) ? (string) filemtime( $script_path ) : AIO_LOGIN__VERSION;
			wp_enqueue_script(
				'aio-login-network-wp-settings',
				AIO_LOGIN__DIR_URL . 'assets/js/network-wp-settings.js',
				array(),
				$script_ver,
				true
			);
			wp_localize_script(
				'aio-login-network-wp-settings',
				'aioLoginNetworkSettings',
				array(
					'moveWrap'     => true,
					'enabledText'  => __( 'Enabled — Network configuration is the default for every site.', 'change-wp-admin-login' ),
					'disabledText' => __( 'Disabled — Each site manages AIO Login independently.', 'change-wp-admin-login' ),
				)
			);
		}

		public function enqueue_styles( $hook ) {
			if ( ! is_network_admin() ) {
				return;
			}
			$allowed = array(
				'settings.php',
			);
			if ( ! in_array( $hook, $allowed, true ) ) {
				return;
			}
			wp_register_style( 'aio-login-network-policies', false, array(), AIO_LOGIN__VERSION );
			wp_enqueue_style( 'aio-login-network-policies' );
			wp_add_inline_style(
				'aio-login-network-policies',
				'
				.aio-np-policy-options {
					background: #fff;
					border: 1px solid #c3c4c7;
					border-radius: 2px;
					padding: 12px 14px;
					margin: 0 0 16px;
					width: 100%;
					max-width: 720px;
					box-sizing: border-box;
					line-height: 1.55;
					color: #1d2327;
					font-size: 13px;
				}
				.aio-np-policy-options strong { font-weight: 600; }
				.aio-np-policy-options em { font-style: italic; }
				.aio-np-features {
					width: 100%;
					max-width: 720px;
					border-collapse: collapse;
					border: 1px solid #c3c4c7;
					background: #fff;
					margin: 12px 0 0;
				}
				.aio-np-features thead th {
					background: #f0f0f1;
					border-bottom: 1px solid #c3c4c7;
					padding: 10px 14px;
					text-align: left;
					font-weight: 600;
					color: #1d2327;
					font-size: 13px;
				}
				.aio-np-features tbody td {
					padding: 12px 14px;
					border-bottom: 1px solid #dcdcde;
					vertical-align: middle;
					font-size: 13px;
				}
				.aio-np-features tbody tr:last-child td { border-bottom: 0; }
				.aio-np-features td.aio-np-features__policy { width: 240px; }
				.aio-np-features select { min-width: 200px; max-width: 100%; }
				.aio-np-pro {
					font-weight: 700;
					color: #1d2327;
				}
				'
			);
		}

		/**
		 * @return bool
		 */
		private function can_manage() {
			return is_network_admin() && current_user_can( 'manage_network_options' );
		}

		/**
		 * Render policies in native WP Network Settings form-table format.
		 */
		public function render_policies_section() {
			if ( ! $this->can_manage() ) {
				return;
			}

			$registry      = Network_Settings::get_feature_registry();
			$categories    = Network_Settings::filter_categories_for_install(
				Network_Settings::get_categories(),
				$registry
			);
			$policies      = Network_Settings::get_policies();
			$choices       = Network_Settings::get_policy_choices();
			$network_level = Network_Settings::is_network_level_enabled();
			$first_cat     = '';

			foreach ( array_keys( $categories ) as $slug ) {
				$first_cat = $slug;
				break;
			}

			echo '<div id="aio-login-network-policies-wrap">';
			echo '<input type="hidden" name="aio_login_network_policies_present" value="1" />';

			/*
			 * —— AIO Login Network Policies ——
			 * Same pattern as Operational / Registration Settings.
			 */
			echo '<h2 id="aio-login-network-policies-heading">' . esc_html__( 'AIO Login Network Policies', 'change-wp-admin-login' ) . '</h2>';
			echo '<table class="form-table" role="presentation">';
			echo '<tbody>';

			// Manage at Network Level.
			echo '<tr>';
			echo '<th scope="row">' . esc_html__( 'Manage AIO Login at Network Level', 'change-wp-admin-login' ) . '</th>';
			echo '<td>';
			echo '<label for="aio_login_network_level_enabled">';
			echo '<input name="aio_login_network_level_enabled" type="checkbox" id="aio_login_network_level_enabled" value="1"' . checked( $network_level, true, false ) . ' /> ';
			echo '<span id="aio_login_network_level_status">';
			echo $network_level
				? esc_html__( 'Enabled — Network configuration is the default for every site.', 'change-wp-admin-login' )
				: esc_html__( 'Disabled — Each site manages AIO Login independently.', 'change-wp-admin-login' );
			echo '</span></label>';
			echo '<p class="description">';
			echo esc_html__( 'When enabled, newly created sites automatically inherit all Network Managed settings, while the Main Site (Site ID 1) follows the same Network Policy.', 'change-wp-admin-login' );
			echo ' ';
			echo wp_kses(
				sprintf(
					'<a href="%1$s" target="_blank" rel="noopener noreferrer"><strong>%2$s</strong></a>',
					esc_url( 'https://aiologin.com/docs/getting-started/aio-login-wordpress-multisite' ),
					esc_html__( 'Learn more about Multisite Compatibility and Network Managed settings in the documentation.', 'change-wp-admin-login' )
				),
				array(
					'a'      => array(
						'href'   => true,
						'target' => true,
						'rel'    => true,
					),
					'strong' => array(),
				)
			);
			echo '</p>';
			echo '</td></tr>';

			echo '</tbody></table>';

			/*
			 * —— Site Override Permissions (Figma layout) ——
			 * Feature Category → Policy options box → Feature/Policy table
			 */
			echo '<div id="aio-login-site-override-section">';
			echo '<h2>' . esc_html__( 'Site Override Permissions', 'change-wp-admin-login' ) . '</h2>';
			echo '<p>';
			echo esc_html__( 'Choose a feature category, then select which specific features Site Administrators are allowed to override locally. All other features remain Network Managed and locked at the site level.', 'change-wp-admin-login' );
			echo '</p>';

			echo '<table class="form-table" role="presentation">';
			echo '<tbody>';

			// Feature Category.
			echo '<tr>';
			echo '<th scope="row"><label for="aio_login_feature_category">' . esc_html__( 'Feature Category', 'change-wp-admin-login' ) . '</label></th>';
			echo '<td>';
			echo '<select name="aio_login_feature_category" id="aio_login_feature_category">';
			foreach ( $categories as $slug => $cat ) {
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $slug ),
					selected( $slug, $first_cat, false ),
					esc_html( $cat['label'] )
				);
			}
			echo '</select>';
			echo '</td></tr>';
			echo '</tbody></table>';

			// Policy options info box — flush left (outside form-table td indent).
			echo '<div class="aio-np-policy-options">';
			echo '<strong>' . esc_html__( 'Policy options:', 'change-wp-admin-login' ) . '</strong><br>';
			echo '<strong>' . esc_html__( 'Site Override Allowed', 'change-wp-admin-login' ) . '</strong> — ';
			echo esc_html__( 'sites may reconfigure locally.', 'change-wp-admin-login' ) . '<br>';
			echo '<strong>' . esc_html__( 'Network Managed', 'change-wp-admin-login' ) . '</strong> — ';
			echo esc_html__( 'network default is enforced.', 'change-wp-admin-login' ) . '<br>';
			echo '<strong>' . esc_html__( 'Individual Settings', 'change-wp-admin-login' ) . '</strong> — ';
			echo esc_html__( 'not enforced network-wide; each site configures it independently.', 'change-wp-admin-login' );
			echo '</div>';

			// Feature · Policy tables (one per category, Figma table).
			foreach ( $categories as $cat_slug => $cat ) {
				$show = ( $cat_slug === $first_cat );
				echo '<table class="aio-np-features aio-login-network-policy-table" data-category="' . esc_attr( $cat_slug ) . '"' . ( $show ? '' : ' style="display:none;"' ) . '>';
				echo '<thead><tr>';
				echo '<th scope="col" class="aio-np-features__feature-heading">';
				echo esc_html( sprintf( /* translators: %s: category label */ __( '%s · Feature', 'change-wp-admin-login' ), $cat['label'] ) );
				echo '</th>';
				echo '<th scope="col">' . esc_html__( 'Policy', 'change-wp-admin-login' ) . '</th>';
				echo '</tr></thead><tbody>';

				if ( empty( $cat['features'] ) || ! is_array( $cat['features'] ) ) {
					echo '</tbody></table>';
					continue;
				}

				foreach ( $cat['features'] as $feature ) {
					if ( ! isset( $registry[ $feature ] ) ) {
						continue;
					}
					if ( ! Network_Settings::is_feature_available_on_install( $registry[ $feature ] ) ) {
						continue;
					}
					$policy = isset( $policies[ $feature ] ) ? $policies[ $feature ] : Network_Settings::POLICY_INDIVIDUAL_SITE;

					echo '<tr>';
					echo '<td>';
					echo esc_html( $registry[ $feature ]['label'] );
					echo '</td>';
					echo '<td class="aio-np-features__policy">';
					echo '<select name="aio_login_network_policies[' . esc_attr( $feature ) . ']" id="aio_login_policy_' . esc_attr( $feature ) . '">';
					foreach ( $choices as $value => $label ) {
						printf(
							'<option value="%1$s"%2$s>%3$s</option>',
							esc_attr( $value ),
							selected( $policy, $value, false ),
							esc_html( $label )
						);
					}
					echo '</select>';
					echo '</td></tr>';
				}

				echo '</tbody></table>';
			}

			echo '</div>'; // site override section
			echo '</div>'; // wrap
		}

		/**
		 * Save handler.
		 */
		public function save_policies() {
			if ( ! current_user_can( 'manage_network_options' ) ) {
				return;
			}

			// Only handle Network Settings saves that include our section.
			if ( empty( $_POST['aio_login_network_policies_present'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return;
			}

			$enabled = isset( $_POST['aio_login_network_level_enabled'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			Network_Settings::set_network_level_enabled( $enabled );

			if ( ! empty( $_POST['aio_login_network_policies'] ) && is_array( $_POST['aio_login_network_policies'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$posted   = wp_unslash( $_POST['aio_login_network_policies'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$registry = Network_Settings::get_feature_registry();
				$choices  = Network_Settings::get_policy_choices();
				$policies = Network_Settings::get_policies();

				foreach ( $posted as $feature => $policy ) {
					$feature = sanitize_key( $feature );
					$policy  = sanitize_key( $policy );
					if ( ! isset( $registry[ $feature ] ) || ! isset( $choices[ $policy ] ) ) {
						continue;
					}
					if ( ! Network_Settings::is_feature_available_on_install( $registry[ $feature ] ) ) {
						continue;
					}
					$policies[ $feature ] = $policy;
					if ( 'captcha' === $feature ) {
						$policies['google_recaptcha']     = $policy;
						$policies['hcaptcha']             = $policy;
						$policies['cloudflare_turnstile'] = $policy;
					}
				}

				update_site_option( Network_Settings::SITE_OPTION_POLICIES, $policies );
			}

			// Refresh managed feature values from main site and push to all sites (AIOL-542 AC-16).
			if ( $enabled ) {
				Network_Settings::sync_managed_features_to_all_sites();
			}

			if ( $enabled ) {
				// Policies saved — confirm managed features were synced.
				set_transient(
					'aio_login_network_apply_notice_' . get_current_user_id(),
					array(
						'type'    => 'synced',
						'count'   => count( Network_Settings::get_sites_list() ),
					),
					60
				);
			}
		}

		/**
		 * Show notice after Apply defaults / policy save redirect.
		 */
		public function maybe_show_save_notice() {
			if ( ! is_network_admin() || ! current_user_can( 'manage_network_options' ) ) {
				return;
			}

			$key  = 'aio_login_network_apply_notice_' . get_current_user_id();
			$data = get_transient( $key );
			if ( ! is_array( $data ) ) {
				return;
			}
			delete_transient( $key );

			if ( isset( $data['type'] ) && 'warning' === $data['type'] ) {
				echo '<div class="notice notice-warning is-dismissible"><p>';
				echo esc_html__( 'Enable “Manage AIO Login at Network Level” before applying defaults to existing sites.', 'change-wp-admin-login' );
				echo '</p></div>';
				return;
			}

			$count = isset( $data['count'] ) ? (int) $data['count'] : 0;
			if ( isset( $data['type'] ) && 'synced' === $data['type'] ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html(
					sprintf(
						/* translators: %d: number of sites */
						_n(
							'AIO Login network policies saved. Network Managed settings were synced to %d site.',
							'AIO Login network policies saved. Network Managed settings were synced to %d sites.',
							$count,
							'change-wp-admin-login'
						),
						$count
					)
				);
				echo '</p></div>';
				return;
			}

			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: %d: number of sites updated */
					_n(
						'AIO Login network defaults were applied to %d site. The “Apply defaults” checkbox is a one-time action and stays unchecked after save.',
						'AIO Login network defaults were applied to %d sites. The “Apply defaults” checkbox is a one-time action and stays unchecked after save.',
						$count,
						'change-wp-admin-login'
					),
					$count
				)
			);
			echo '</p></div>';
		}

		/**
		 * Singleton.
		 *
		 * @return Network_WP_Settings
		 */
		public static function get_instance() {
			static $instance = null;
			if ( is_null( $instance ) ) {
				$instance = new self();
			}
			return $instance;
		}
	}
}
