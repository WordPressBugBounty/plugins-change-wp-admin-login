<?php
/**
 * Multisite Network Admin UI + REST.
 *
 * @package AIO Login
 */

namespace AIO_Login\Multisite;

use AIO_Login\Helper\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'AIO_Login\\Multisite\\Network_Admin' ) ) {
	/**
	 * Class Network_Admin
	 */
	class Network_Admin {

		/**
		 * Constructor.
		 */
		private function __construct() {
			add_filter( 'aio_login__app_object_l10n', array( $this, 'localize_multisite' ) );
			add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
			Network_Settings::bootstrap_option_hooks();
		}

		/**
		 * Expose Multisite flags to Vue app.
		 *
		 * @param array $l10n Localized data.
		 * @return array
		 */
		public function localize_multisite( $l10n ) {
			$l10n['is_multisite']   = Network_Settings::is_multisite_install() ? 'true' : 'false';
			$l10n['is_network']     = Network_Settings::is_network_context() ? 'true' : 'false';
			// Network-level UI (filter, overview, license badge) — Network Admin only.
			// Main site site-admin is treated like any other site (child).
			$l10n['is_network_admin'] = is_network_admin() ? 'true' : 'false';
			$l10n['is_main_site']     = 'false';
			$l10n['is_super_admin']   = is_super_admin() ? 'true' : 'false';
			$l10n['current_blog_id']  = (string) get_current_blog_id();
			$show_network_dashboard  = is_multisite() && is_network_admin();
			if ( $show_network_dashboard ) {
				Network_Settings::seed_network_settings_from_main_site( true );
			}
			$l10n['sites']            = $show_network_dashboard ? Network_Settings::get_sites_list() : array();
			$l10n['network_overview'] = $show_network_dashboard
				? Network_Settings::get_network_overview()
				: array();
			$l10n['network_settings_url'] = is_multisite()
				? network_admin_url( 'settings.php#aio-login-network-policies-heading' )
				: '';
			$l10n['network_policies_url'] = is_multisite()
				? network_admin_url( 'settings.php#aio-login-network-policies-heading' )
				: '';
			$l10n['feature_contexts']        = array();
			$l10n['network_feature_is_pro'] = array();
			foreach ( Network_Settings::get_feature_registry() as $feature => $config ) {
				$l10n['network_feature_is_pro'][ $feature ] = ! empty( $config['is_pro'] );
			}

			if ( Network_Settings::is_network_context() ) {
				foreach ( array_keys( Network_Settings::get_feature_registry() ) as $feature ) {
					$l10n['feature_contexts'][ $feature ] = Network_Settings::get_feature_context( $feature );
				}
				$l10n['feature_contexts']['google_recaptcha']     = Network_Settings::get_feature_context( 'google_recaptcha' );
				$l10n['feature_contexts']['hcaptcha']             = Network_Settings::get_feature_context( 'hcaptcha' );
				$l10n['feature_contexts']['cloudflare_turnstile'] = Network_Settings::get_feature_context( 'cloudflare_turnstile' );
			}

			return $l10n;
		}

		/**
		 * Register REST routes.
		 */
		public function register_rest_routes() {
			register_rest_route(
				'aio-login/network',
				'/settings',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage_network_ui' ),
				)
			);

			register_rest_route(
				'aio-login/network',
				'/policies',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_policy' ),
					'permission_callback' => array( $this, 'can_manage_network' ),
				)
			);

			register_rest_route(
				'aio-login/network',
				'/settings',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage_network' ),
				)
			);

			register_rest_route(
				'aio-login/network',
				'/overview',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_overview' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/network',
				'/sites',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_sites' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/network',
				'/counts',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_counts' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/network',
				'/logs',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_logs' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/network',
				'/site-override',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_site_override' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);

			register_rest_route(
				'aio-login/network',
				'/feature-context/(?P<feature>[a-z0-9_\-]+)',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_feature_context' ),
					'permission_callback' => array( Helper::class, 'get_api_permission' ),
				)
			);
		}

		/**
		 * Permission: view Network UI.
		 *
		 * @return bool
		 */
		public function can_manage_network_ui() {
			if ( ! is_user_logged_in() ) {
				return false;
			}
			return is_super_admin() || current_user_can( 'manage_options' );
		}

		/**
		 * Permission: mutate network policies.
		 *
		 * @return bool
		 */
		public function can_manage_network() {
			return is_user_logged_in() && current_user_can( 'manage_network_options' );
		}

		/**
		 * GET /network/settings
		 *
		 * @return \WP_REST_Response
		 */
		public function get_settings() {
			return rest_ensure_response( Network_Settings::get_admin_payload() );
		}

		/**
		 * POST /network/policies
		 *
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function update_policy( $request ) {
			$feature = sanitize_key( $request->get_param( 'feature' ) );
			$policy  = sanitize_key( $request->get_param( 'policy' ) );

			if ( ! Network_Settings::set_policy( $feature, $policy ) ) {
				return new \WP_Error(
					'invalid_policy',
					__( 'Invalid feature or policy.', 'change-wp-admin-login' ),
					array( 'status' => 400 )
				);
			}

			return rest_ensure_response(
				array(
					'status'  => 'success',
					'feature' => $feature,
					'policy'  => $policy,
					'message' => __( 'Network policy updated.', 'change-wp-admin-login' ),
				)
			);
		}

		/**
		 * POST /network/settings — save network-managed option values.
		 *
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function update_settings( $request ) {
			$settings = $request->get_param( 'settings' );
			if ( ! is_array( $settings ) ) {
				return new \WP_Error(
					'invalid_settings',
					__( 'Invalid settings payload.', 'change-wp-admin-login' ),
					array( 'status' => 400 )
				);
			}

			$allowed = array();
			foreach ( Network_Settings::get_feature_registry() as $config ) {
				foreach ( $config['options'] as $option ) {
					$allowed[] = $option;
				}
			}

			$sanitized = array();
			foreach ( $settings as $key => $value ) {
				$key = sanitize_key( $key );
				if ( ! in_array( $key, $allowed, true ) ) {
					continue;
				}
				$sanitized[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}

			Network_Settings::update_network_settings( $sanitized );

			return rest_ensure_response(
				array(
					'status'  => 'success',
					'message' => __( 'Network settings saved.', 'change-wp-admin-login' ),
				)
			);
		}

		/**
		 * GET /network/overview
		 *
		 * @return \WP_REST_Response
		 */
		public function get_overview() {
			return rest_ensure_response( Network_Settings::get_network_overview() );
		}

		/**
		 * GET /network/sites
		 *
		 * @return \WP_REST_Response
		 */
		public function get_sites() {
			return rest_ensure_response( Network_Settings::get_sites_list() );
		}

		/**
		 * GET /network/counts
		 *
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response
		 */
		public function get_counts( $request ) {
			$type     = sanitize_text_field( (string) $request->get_param( 'type' ) );
			$duration = sanitize_text_field( (string) $request->get_param( 'duration' ) );
			$blog_id  = absint( $request->get_param( 'blog_id' ) );

			if ( empty( $type ) ) {
				$type = 'success';
			}
			if ( empty( $duration ) ) {
				$duration = 'today';
			}

			$count = Network_Settings::get_network_counts( $type, $duration, $blog_id );

			return rest_ensure_response( array( 'count' => absint( $count ) ) );
		}

		/**
		 * GET /network/logs
		 *
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response
		 */
		public function get_logs( $request ) {
			$type    = sanitize_text_field( (string) $request->get_param( 'type' ) );
			$blog_id = absint( $request->get_param( 'blog_id' ) );
			$limit   = absint( $request->get_param( 'limit' ) );

			if ( empty( $type ) ) {
				$type = 'failed';
			}
			if ( $limit < 1 ) {
				$limit = 50;
			}

			$logs = Network_Settings::get_network_logs( $type, $limit, $blog_id );
			$logs = array_map(
				static function ( $log ) {
					if ( isset( $log['time'] ) && is_numeric( $log['time'] ) ) {
						$log['time_formatted'] = date_i18n( 'F j, Y, g:i a', (int) $log['time'] );
						$log['time']           = $log['time_formatted'];
					}
					return $log;
				},
				$logs
			);

			return rest_ensure_response(
				array(
					'logs'  => $logs,
					'total' => count( $logs ),
				)
			);
		}

		/**
		 * POST /network/site-override
		 *
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response|\WP_Error
		 */
		public function update_site_override( $request ) {
			$feature = sanitize_key( $request->get_param( 'feature' ) );
			$enabled = (bool) $request->get_param( 'enabled' );

			if ( in_array( $feature, array( 'google_recaptcha', 'hcaptcha', 'cloudflare_turnstile' ), true ) ) {
				$feature = 'captcha';
			}

			$registry = Network_Settings::get_feature_registry();
			if ( ! isset( $registry[ $feature ] ) ) {
				return new \WP_Error(
					'invalid_feature',
					__( 'Invalid feature.', 'change-wp-admin-login' ),
					array( 'status' => 400 )
				);
			}

			$policy = Network_Settings::get_policy( $feature );
			if ( Network_Settings::POLICY_SITE_OVERRIDE !== $policy ) {
				return new \WP_Error(
					'override_not_allowed',
					__( 'Local override is not allowed for this feature policy.', 'change-wp-admin-login' ),
					array( 'status' => 403 )
				);
			}

			Network_Settings::set_site_override( $feature, $enabled );

			return rest_ensure_response(
				array(
					'status'  => 'success',
					'context' => Network_Settings::get_feature_context( $feature ),
					'message' => $enabled
						? __( 'Local override enabled.', 'change-wp-admin-login' )
						: __( 'Local override disabled. Using network settings.', 'change-wp-admin-login' ),
				)
			);
		}

		/**
		 * GET /network/feature-context/{feature}
		 *
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response
		 */
		public function get_feature_context( $request ) {
			$feature = sanitize_key( $request['feature'] );
			if ( in_array( $feature, array( 'google_recaptcha', 'hcaptcha', 'cloudflare_turnstile' ), true ) ) {
				$feature = 'captcha';
			}
			return rest_ensure_response( Network_Settings::get_feature_context( $feature ) );
		}

		/**
		 * Singleton.
		 *
		 * @return Network_Admin
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
