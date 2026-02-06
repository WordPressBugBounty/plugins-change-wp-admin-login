<template>
	<div>
		<aio-login-header class="mb-25">
			<template v-slot:logo>
				<img class="aio-login-header-img" :src="assets_url + 'images/dashboard-logo.png'" alt="logo">
			</template>
			<template v-slot:version>
				<span class="aio-login-header-version">Version: {{ version }}</span>
			</template>
		</aio-login-header>

		<div class="container">

			<aio-login-tabs
				:assets-url="assets_url"
				:tabs="tabs"
			></aio-login-tabs>

			<div class="aio-login__container" :class="is_pro_class" :style="backgroundColor">

				<div v-if="hasSubTabs()">

					<aio-login-sub-tabs
						:sub-tabs="sub_tabs"
					></aio-login-sub-tabs>

				</div>

				<div class="aio-login__content-wrapper" style="position: relative;">
					<div v-if="hasSubTabs()">
						<router-view></router-view>
					</div>

					<div v-else>
						<aio-login-dashboard
							v-if="isDashboard()"
						></aio-login-dashboard>

						<aio-login-temp-access
							v-if="isTempAccess()"
						></aio-login-temp-access>

						<aio-login-social-login-main
							v-if="isSocialLogin()"
						></aio-login-social-login-main>

						<aio-login-integrations
							v-if="isIntegrations()"
						></aio-login-integrations>

						<aio-login-getpro
							v-if="isGetPro()"
						></aio-login-getpro>
					</div>

				</div>

			</div>

			<div v-if="! hasSubTabs() && isDashboard()" class="container">
				<aio-login-recent-activity class="mt-25"></aio-login-recent-activity>
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
		assets_url: window.aio_login__app_object.assets_url,
		tabs: [],
		sub_tabs: [],
		popup: false,
		current_is_pro: false,
	} ),

	computed: {
		backgroundColor() {
			if ( this.isGetPro() ) {
				return {
					backgroundImage: 'radial-gradient(128.97% 130.43% at 126.02% 137.76%, #B480F8 0%, #141B34 100%)',
          position: 'relative',
				};
			} else {
				return {
					backgroundImage: '#ffffff',
				};
			}
		},

    is_pro_class() {
      return {
        'aio-login__pro-container': this.isGetPro(),
      };
    }
	},

	methods: {
		getTabs() {
			this.tabs = Object.values( window.aio_login__object.tabs );
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

		isTempAccess() {
			return this.getActiveTab() === 'temp-access';
		},

		isSocialLogin() {
			return this.getActiveTab() === 'social-login';
		},

		isIntegrations() {
			return this.getActiveTab() === 'integrations';
		},

		isGetPro() {
			return this.getActiveTab() === 'getpro';
		},

		closePopup() {
			this.popup = false;
		}
	},

	mounted() {
		this.getTabs();

		this.getSubTabs();

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
	line-height: 100px;
	font-weight: 400;
	font-size: 14px;
}

.aio-login__container {
	background: #fff;
	border-radius: 0 8px 8px 8px;
}

.aio-login__content-wrapper {
	padding: 25px;
}

.aio-login__pro-container::before {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  width: 100%;
  height: 100%;
  background: url(../../images/aoibg.svg);
  z-index: 1;
}

.aio-login__pro-container .aio-login__content-wrapper {
  position: relative;
  z-index: 2;
}
</style>