<template>
	<div class="aio-login-2fa-subtabs">
		<div class="aio-login-2fa-subtabs__inner">
			<!-- Free: Email OTP Authentication Methods. Pro overwrites this component when active. -->
			<aio-login-two-factor-authentication
				v-if="currentRouteSlug === 'authentication-methods'"
				:key="currentRouteSlug"
				:has-pro="has_pro"
			/>

			<!-- Pro injects Policies / Advanced sub-tabs + components via aio_login_pro.plugin. -->
			<template v-else-if="currentRouteSlug === '2fa-policies'">
				<aio-login-two-factor-policies
					:key="currentRouteSlug"
					:has-pro="has_pro"
				/>
			</template>

			<template v-else-if="currentRouteSlug === 'advanced-settings'">
				<aio-login-two-factor-advanced-settings
					:key="currentRouteSlug"
					:has-pro="has_pro"
				/>
			</template>
		</div>
	</div>
</template>

<script>
export default {
	name: '2fa',

	data: () => ( {
		is_limited_user: window.aio_login__app_object.is_limited_user === 'true',
	} ),

	computed: {
		has_pro() {
			let p = this.$parent;
			while ( p ) {
				if ( 'current_is_pro' in p ) {
					return p.current_is_pro === true || p.current_is_pro === 'true';
				}
				p = p.$parent;
			}
			return false;
		},

		currentRouteSlug() {
			const path = this.$route && this.$route.path ? this.$route.path : '';
			return path.replace( '/', '' ) || 'authentication-methods';
		},
	},

	mounted() {
		if ( this.is_limited_user && this.currentRouteSlug !== 'authentication-methods' ) {
			this.$router.replace( '/authentication-methods' );
		}
	},
}
</script>

<style scoped>
.aio-login-2fa-subtabs {
	position: relative;
	min-height: 380px;
}

.aio-login-2fa-subtabs__inner {
	position: relative;
	min-height: inherit;
}
</style>
