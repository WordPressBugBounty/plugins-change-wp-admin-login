<template>
	<div>
		<div class="aio-login-activity-log-top">
			<h1>
				<span>{{ $t('Failed Logins') }}</span>
				<aio-login-tooltip
					:content="tooltipContent.failedLoginAttempts.content"
					:title="tooltipContent.failedLoginAttempts.title"
					placement="bottom"
				/>
			</h1>

			<div
				v-if="show_site_filter"
				class="aio-login-activity-site-filter"
			>
				<label for="aio-login-failed-site-filter">Filter by site:</label>
				<select
					id="aio-login-failed-site-filter"
					v-model="selected_blog_id"
					@change="get_logs"
				>
					<option value="0">All Sites</option>
					<option
						v-for="site in sites"
						:key="site.id"
						:value="String( site.id )"
					>
						{{ site.name }}
					</option>
				</select>
			</div>
		</div>

		<aio-login-datatable
			:key="'failed-logins-' + selected_blog_id"
			:headers="tableHeaders"
			:rows="data"
		></aio-login-datatable>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';
import { t } from '../i18n.js';

export default {
	name: 'failed-logins',

	data: () => ( {
		tooltipContent,
		namespace: 'aio-login/logs/failed-login',
		is_multisite: 'true' === ( window.aio_login__app_object.is_multisite || '' ),
		is_network_admin: 'true' === ( window.aio_login__app_object.is_network_admin || '' ),
		sites: Array.isArray( window.aio_login__app_object.sites ) ? window.aio_login__app_object.sites : [],
		selected_blog_id: '0',
		data: [],
	} ),

	computed: {
		hasPro() {
			const h = window.aio_login__app_object && window.aio_login__app_object.has_pro;
			return h === true || h === 'true';
		},
		baseHeaders() {
			const headers = [
				{ value: t( 'ID' ), key: 'id' },
				{ value: t( 'User Login' ), key: 'user_login' },
				{ value: t( 'Date & Time' ), key: 'time' },
			];
			if ( this.hasPro ) {
				headers.push(
					{ value: t( 'Country' ), key: 'country' },
					{ value: t( 'City' ), key: 'city' }
				);
			}
			headers.push(
				{ value: t( 'User Agent' ), key: 'user_agent' },
				{ value: t( 'IP Address' ), key: 'ip_address' }
			);
			return headers;
		},
		show_site_filter() {
			return this.is_multisite && this.is_network_admin;
		},
		tableHeaders() {
			if ( this.show_site_filter && '0' === this.selected_blog_id ) {
				return [
					{ value: 'Site', key: 'site_name' },
					...this.baseHeaders,
				];
			}
			return this.baseHeaders;
		},
	},

	methods: {
		formatTime( value ) {
			if ( typeof value === 'string' && value && ! /^\d+$/.test( value ) ) {
				return value;
			}
			const ts = parseInt( value, 10 );
			if ( ! ts ) {
				return value || '';
			}
			try {
				return new Date( ts * 1000 ).toLocaleString();
			} catch ( e ) {
				return String( value );
			}
		},

		normalizeLogs( rows ) {
			return ( rows || [] ).map( ( log ) => ( {
				...log,
				time: log.time_formatted || this.formatTime( log.time ),
				site_name: log.site_name || '',
			} ) );
		},

		get_logs() {
			if ( this.show_site_filter ) {
				window.axios
					.get( 'aio-login/network/logs', {
						params: {
							type: 'failed',
							blog_id: parseInt( this.selected_blog_id, 10 ) || 0,
							limit: 1000,
						},
					} )
					.then( ( response ) => {
						this.data = this.normalizeLogs( response.data && response.data.logs );
					} )
					.catch( () => {
						this.data = [];
					} );
				return;
			}

			window.axios
				.get( this.namespace )
				.then( ( response ) => {
					this.data = this.normalizeLogs( response.data );
				} )
				.catch( () => {
					this.data = [];
				} );
		},
	},

	mounted() {
		this.get_logs();
	},
};
</script>

<style scoped>
.aio-login-activity-log-top {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
	margin-bottom: 16px;
}

.aio-login-activity-log-top h1 {
	margin: 0;
}

.aio-login-activity-site-filter {
	display: flex;
	align-items: center;
	gap: 15px;
}

.aio-login-activity-site-filter label {
	margin: 0;
	font-size: 14px;
	font-weight: 500;
	line-height: 18px;
	color: #606c80;
}

.aio-login-activity-site-filter select {
	min-width: 270px;
	padding: 10px 15px;
	font-size: 12px;
	line-height: normal;
	color: #606c80;
	background-color: #fff;
	border: 1px solid #b8b8b8;
	border-radius: 4px;
	appearance: none;
	background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='5' viewBox='0 0 8 5' fill='none'%3E%3Cpath d='M1 1L4 4L7 1' stroke='%606060' stroke-width='1.2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
	background-repeat: no-repeat;
	background-position: right 15px center;
	padding-right: 36px;
}

@media screen and (max-width: 782px) {
	.aio-login-activity-site-filter {
		width: 100%;
		flex-wrap: wrap;
	}

	.aio-login-activity-site-filter select {
		min-width: 0;
		width: 100%;
	}
}
</style>
