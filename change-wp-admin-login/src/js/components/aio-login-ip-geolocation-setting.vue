<template>
	<div class="aio-login-ip-geolocation-setting">
		<label class="aio-login-ip-geolocation-setting__label" for="aio-login-enable-ip-geolocation">
			{{ $t('IP geolocation (country / city)') }}
		</label>
		<aio-login-toggle
			id="aio-login-enable-ip-geolocation"
			name="aio_login_enable_ip_geolocation"
			:enabled="enabled"
			:disabled="saving"
			v-on:toggle-input="onToggle"
		/>
		<p class="description">
			{{ $t('Pro feature. Off by default. When enabled, visitor IPs from failed logins and lockouts are sent to ipapi.co to look up approximate country and city.') }}
		</p>
		<p v-if="notice" class="aio-login-ip-geolocation-setting__notice">{{ notice }}</p>
	</div>
</template>

<script>
import { t } from '../i18n.js';

export default {
	name: 'aio-login-ip-geolocation-setting',

	data: () => ( {
		enabled: false,
		saving: false,
		loaded: false,
		notice: '',
	} ),

	methods: {
		load() {
			window.axios
				.get( 'aio-login/dashboard/activity-log-settings' )
				.then( ( response ) => {
					const data = response.data && response.data.data ? response.data.data : {};
					this.enabled = data.enable_ip_geolocation === 'on' || data.enable_ip_geolocation === true;
					this.loaded = true;
				} )
				.catch( () => {
					this.enabled = false;
					this.loaded = true;
				} );
		},

		onToggle( value ) {
			const next = value === true || value === 'on' || value === 'true';
			this.enabled = next;
			this.saving = true;
			this.notice = '';

			window.axios
				.post( 'aio-login/dashboard/update/activity-log-settings', {
					settings: {
						enable_ip_geolocation: next ? 'on' : 'off',
					},
				} )
				.then( () => {
					this.notice = next
						? t( 'IP geolocation enabled.' )
						: t( 'IP geolocation disabled.' );
				} )
				.catch( () => {
					this.enabled = ! next;
					this.notice = t( 'Could not save settings. Please try again.' );
				} )
				.finally( () => {
					this.saving = false;
				} );
		},
	},

	mounted() {
		this.load();
	},
};
</script>

<style scoped>
.aio-login-ip-geolocation-setting {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 12px 16px;
	margin: 0 0 16px;
	padding: 12px 16px;
	background: #f6f7f7;
	border: 1px solid #dcdcde;
	border-radius: 4px;
}

.aio-login-ip-geolocation-setting__label {
	margin: 0;
	font-size: 14px;
	font-weight: 600;
	color: #1d2327;
}

.aio-login-ip-geolocation-setting .description {
	flex: 1 1 100%;
	margin: 0;
	color: #646970;
}

.aio-login-ip-geolocation-setting__notice {
	flex: 1 1 100%;
	margin: 0;
	color: #1d2327;
}
</style>
