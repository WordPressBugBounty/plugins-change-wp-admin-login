<template>
	<div>
		<aio-login-header class="mb-25">
			<template v-slot:logo>
				<img class="aio-login-header-img" :src="assets_url + 'images/dashboard-logo.png'" alt="logo">
			</template>
			<template v-slot:version>
				<div class="aio-login-header-version">
					<span class="aio-login-header-version__badge aio-login-header-version__badge--free">
						Free v{{ version }}
					</span>
					<span
						v-if="show_pro_version && ! show_network_license"
						class="aio-login-header-version__badge aio-login-header-version__badge--pro"
					>
						Pro v{{ pro_version }}
					</span>
					<span
						v-if="show_network_license"
						class="aio-login-header-version__badge aio-login-header-version__badge--network"
					>
						Network License <strong>· Active</strong>
					</span>
				</div>
			</template>
			<template v-slot:actions>
			</template>
		</aio-login-header>

		<div class="container">

			<aio-login-tabs
				:assets-url="assets_url"
				:tabs="tabs"
			></aio-login-tabs>

			<div class="aio-login__container">

				<div v-if="hasSubTabs()">
					<aio-login-sub-tabs :sub-tabs="sub_tabs">
						<template v-slot:actions v-if="getActiveTab() === '2fa'">
							<aio-login-network-notice
								feature="two_factor_authentication"
								:inline="true"
							/>
						</template>
					</aio-login-sub-tabs>
				</div>

				<div class="aio-login__content-wrapper" style="position: relative;">
					<div v-if="hasSubTabs()">
						<router-view></router-view>
					</div>

					<div v-else>
						<router-view v-if="!isDashboard() && !isGetPro()"></router-view>

						<aio-login-dashboard
							v-if="isDashboard()"
						></aio-login-dashboard>

						<aio-login-getpro
							v-if="isGetPro()"
						></aio-login-getpro>
					</div>

				</div>

			</div>

			<div v-if="! hasSubTabs() && isDashboard()" class="container">
				<aio-login-recent-activity class="mt-25"></aio-login-recent-activity>
				<aio-login-dashboard-docs></aio-login-dashboard-docs>
			</div>

		</div>

		<aio-login-pro-popup v-if="popup" v-on:close-popup="closePopup" />
	</div>
</template>

<script>

export default {
	name: 'aio-login-app',

	data: () => ( {
		version: window.aio_login__app_object.version,
		pro_version: window.aio_login__app_object.pro_version || '',
		assets_url: window.aio_login__app_object.assets_url,
		tabs: [],
		sub_tabs: [],
		popup: false,
		current_is_pro: window.aio_login__app_object.has_pro === 'true' || window.aio_login__app_object.has_pro === true,
	} ),

	computed: {
		show_pro_version() {
			return this.has_pro_enabled && !!this.pro_version;
		},

		show_network_license() {
			const o = window.aio_login__app_object || {};
			const licensed = o.has_active_license === true || o.has_active_license === 'true';
			const networkAdmin = o.is_network_admin === true || o.is_network_admin === 'true';
			return (
				licensed
				&& ( o.is_multisite === true || o.is_multisite === 'true' )
				&& networkAdmin
			);
		},

		has_pro_enabled() {
			return window.aio_login__app_object.has_pro === 'true' || window.aio_login__app_object.has_pro === true;
		},

	},

	watch: {
		$route() {
			this.syncCurrentTabAccess();
		},
	},

	methods: {
		/**
		 * Active router path is /{sub-tab-slug} for tabbed sections (e.g. /password-strenght-checker).
		 */
		getActiveSubTabSlug() {
			if ( typeof this.$route === 'undefined' || ! this.$route.path ) {
				return '';
			}
			return String( this.$route.path ).replace( /^\/+|\/+$/g, '' );
		},

		getActiveSubTabConfig() {
			if ( ! this.sub_tabs.length ) {
				return null;
			}
			let slug = this.getActiveSubTabSlug();
			if ( ! slug && this.sub_tabs[0] && this.sub_tabs[0].slug ) {
				slug = this.sub_tabs[0].slug;
			}
			if ( ! slug ) {
				return null;
			}
			return this.sub_tabs.find( ( st ) => st.slug === slug ) || null;
		},

		/**
		 * "Unlocked" content uses current_is_pro === true. Plan-locked main tab or sub-tab => false (blur overlay).
		 */
		syncCurrentTabAccess() {
			const activeTab = this.getActiveTabConfig();
			if ( activeTab && activeTab['is-pro'] === true ) {
				this.current_is_pro = false;
				return;
			}
			if ( this.hasSubTabs() ) {
				const sub = this.getActiveSubTabConfig();
				if ( sub && sub['is-pro'] === true ) {
					this.current_is_pro = false;
					return;
				}
			}
			this.current_is_pro = true;
		},

		getActiveTabConfig() {
			const activeSlug = this.getActiveTab();
			return this.tabs.find((tab) => tab.slug === activeSlug) || null;
		},

		getTabs() {
			this.tabs = Object.values( window.aio_login__object.tabs );
			this.getSubTabs();
			this.syncCurrentTabAccess();
		},

		getSubTabs() {
			this.sub_tabs = this.tabs.filter( tab => tab.slug === this.getActiveTab() );
			try {
				this.sub_tabs = Object.values( this.sub_tabs[0]['sub-tabs'] );
			} catch ( e ) {
				this.sub_tabs = [];
			}
		},

		getActiveTab() {
			var location = window.location.href;
			var url = new URL( location );
			if ( ! url.searchParams.get( 'tab' ) ) {
				return 'dashboard';
			}
			return url.searchParams.get( 'tab' );
		},

		hasSubTabs() {
			return this.sub_tabs.length > 0;
		},

		isDashboard() {
			return this.getActiveTab() === 'dashboard';
		},

		isGetPro() {
			return this.getActiveTab() === 'getpro';
		},

		closePopup() {
			this.popup = false;
		},

		openProPopup() {
			this.popup = true;
		},
	},

	mounted() {
		this.getTabs();

		document.body.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.aio-login__popup-wrapper' ) && ! e.target.closest( '.aio-login__popup-container' ) ) {
				this.popup = false;
			}
		}.bind( this ) );
	}
}
</script>

<style scoped>
.aio-login-header-img {
	margin-top: 15px;
}

.aio-login-header-version {
	min-height: 100px;
	display: inline-flex;
	align-items: center;
	gap: 8px;
}

.aio-login-header-version__badge {
	display: inline-flex;
	align-items: center;
	padding: 8px 14px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 700;
	line-height: 1;
	letter-spacing: 0.01em;
}

.aio-login-header-version__badge--free {
	font-size: 12px;
	font-weight: 600;
	color: #606c80;
	background: #fff;
	border: 1px solid #606c80;
}

.aio-login-header-version__badge--pro {
	color: #ffffff;
	background: linear-gradient(180deg, #9516df 0%, #510c79 100%);
	border: 1px solid #7a27bf;
}

.aio-login-header-version__badge--network {
	color: #6e16df;
	background: #efe2ff;
	border: 1px solid #6e16df;
	font-weight: 600;
	font-size: 11px;
}

.aio-login-header-version__badge--network strong {
	font-weight: 700;
}

.aio-login__container {
	background: #fff;
	border-radius: 0 8px 8px 8px;
	max-width: 100%;
	overflow: hidden;
}

.aio-login__content-wrapper {
	padding: 25px;
}

@media screen and (max-width: 782px) {
	.aio-login-header-version {
		min-height: auto;
		flex-wrap: wrap;
	}

	.aio-login__content-wrapper {
		padding: 16px;
	}
}

.aio-login-subtabs-locked {
	position: relative;
}

.aio-login-subtabs-locked::after {
	content: '';
	position: absolute;
	inset: 0;
	background: rgba(255, 255, 255, 0.38);
	backdrop-filter: blur(1px);
	z-index: 20;
	cursor: pointer;
}

.aio-login-sub-tabs--locked {
	pointer-events: none;
	opacity: 0.45;
}
</style>
