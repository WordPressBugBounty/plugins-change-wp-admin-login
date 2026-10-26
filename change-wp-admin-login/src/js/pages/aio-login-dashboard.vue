<template>
	<div>
		<div class="aio-login-dashboard-top">
			<h1 class="aio-login-dashboard-top__title">{{ $t('Welcome to Dashboard!') }}</h1>

			<div
				v-if="show_network_dashboard"
				class="aio-login-dashboard-site-filter"
			>
				<label for="aio-login-site-filter">{{ $t('Filter by site:') }}</label>
				<select
					id="aio-login-site-filter"
					v-model="selected_blog_id"
					@change="onSiteChange"
				>
					<option value="0">{{ $t('All Sites') }}</option>
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

		<aio-login-cards class="mb-25"></aio-login-cards>

		<aio-login-meta class="mb-25"></aio-login-meta>

		<div
			v-if="show_network_dashboard"
			class="aio-login-network-overview mb-25"
		>
			<div class="aio-login-network-overview__header">
				<div class="aio-login-network-overview__intro">
					<h2>Network overview</h2>
					<p>Centralized view of authentication and security across every site in your WordPress Multisite installation.</p>
				</div>
				<a
					class="aio-login-network-overview__admin-btn"
					:href="network_settings_url"
				>
					{{ $t('Network Admin Settings') }}
					<img
						:src="assets_url + 'images/icons/network-admin-arrow.svg'"
						alt=""
						width="13"
						height="10"
					>
				</a>
			</div>

			<div class="aio-login-network-overview__stats">
				<div class="aio-login-network-overview__stat">
					<div class="aio-login-network-overview__stat-icon">
						<img
							:src="assets_url + 'images/icons/network-sites.svg'"
							alt=""
							width="30"
							height="30"
						>
					</div>
					<div class="aio-login-network-overview__stat-body">
						<span class="aio-login-network-overview__stat-label">Total Network Sites</span>
						<strong class="aio-login-network-overview__stat-value">{{ overview.total_sites }}</strong>
					</div>
				</div>

				<div class="aio-login-network-overview__stat">
					<div class="aio-login-network-overview__stat-icon">
						<img
							:src="assets_url + 'images/icons/network-active-sites.svg'"
							alt=""
							width="30"
							height="30"
						>
					</div>
					<div class="aio-login-network-overview__stat-body">
						<span class="aio-login-network-overview__stat-label">Active Sites</span>
						<strong class="aio-login-network-overview__stat-value">{{ overview.active_sites }}</strong>
					</div>
				</div>

				<div class="aio-login-network-overview__stat">
					<div class="aio-login-network-overview__stat-icon">
						<img
							:src="assets_url + 'images/icons/network-pro-licensed.svg'"
							alt=""
							width="50"
							height="50"
						>
					</div>
					<div class="aio-login-network-overview__stat-body">
						<span class="aio-login-network-overview__stat-label">Active Pro Licensed Sites</span>
						<strong class="aio-login-network-overview__stat-value">{{ proLicensedLabel }}</strong>
					</div>
				</div>
			</div>
		</div>

		<aio-login-pro-branding v-if="! has_pro"></aio-login-pro-branding>
	</div>
</template>

<script>
export default {
	name: 'aio-login-dashboard',
	data: () => ( {
		has_pro: 'true' === aio_login__app_object.has_pro,
		is_multisite: 'true' === ( aio_login__app_object.is_multisite || '' ),
		is_network_admin: 'true' === ( aio_login__app_object.is_network_admin || '' ),
		assets_url: aio_login__app_object.assets_url || '',
		network_settings_url: aio_login__app_object.network_settings_url || '',
		sites: Array.isArray( aio_login__app_object.sites ) ? aio_login__app_object.sites : [],
		selected_blog_id: '0',
		overview: aio_login__app_object.network_overview || {
			total_sites: 0,
			active_sites: 0,
			pro_licensed: 0,
			license_seats: 0,
		},
	} ),

	computed: {
		show_network_dashboard() {
			// Site filter + network overview: Network Admin only (main site is a normal site).
			return this.is_multisite && this.is_network_admin;
		},
		proLicensedLabel() {
			const used = parseInt( this.overview.pro_licensed, 10 ) || 0;
			const seats = parseInt( this.overview.license_seats, 10 ) || parseInt( this.overview.total_sites, 10 ) || 0;
			return seats > 0 ? `${ used } / ${ seats }` : String( used );
		},
	},

	provide() {
		return {
			aioLoginDashboardBlogId: () => this.selected_blog_id,
		};
	},

	mounted() {
		if ( this.show_network_dashboard ) {
			this.refreshOverview();
		}
	},

	methods: {
		onSiteChange() {
			window.dispatchEvent(
				new CustomEvent( 'aio-login-dashboard-site-change', {
					detail: { blog_id: parseInt( this.selected_blog_id, 10 ) || 0 },
				} )
			);
		},

		refreshOverview() {
			window.axios
				.get( 'aio-login/network/overview' )
				.then( ( response ) => {
					this.overview = response.data || this.overview;
				} )
				.catch( () => {} );
		},
	},
};
</script>

<style scoped>
.aio-login-dashboard-top {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
	margin-bottom: 20px;
}

.aio-login-dashboard-top__title {
	margin: 0;
	font-size: 20px;
	font-weight: 600;
	line-height: 18px;
	color: #404280;
}

.aio-login-dashboard-site-filter {
	display: flex;
	align-items: center;
	gap: 15px;
}

.aio-login-dashboard-site-filter label {
	margin: 0;
	font-size: 14px;
	font-weight: 500;
	line-height: 18px;
	color: #606c80;
}

.aio-login-dashboard-site-filter select {
	min-width: 270px;
	padding: 10px 15px;
	font-size: 12px;
	line-height: normal;
	color: #606c80;
	background: #fff;
	border: 1px solid #b8b8b8;
	border-radius: 4px;
	appearance: none;
	background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='5' viewBox='0 0 8 5' fill='none'%3E%3Cpath d='M1 1L4 4L7 1' stroke='%606060' stroke-width='1.2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
	background-repeat: no-repeat;
	background-position: right 15px center;
	padding-right: 36px;
}

.aio-login-network-overview {
	background: #fff;
	border: 1px solid #ebe8eb;
	border-radius: 8px;
	box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.aio-login-network-overview__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
}

.aio-login-network-overview__intro h2 {
	margin: 0 0 10px;
	font-size: 18px;
	font-weight: 600;
	line-height: 18px;
	color: #404280;
}

.aio-login-network-overview__intro p {
	margin: 0;
	font-size: 14px;
	font-weight: 400;
	line-height: 18px;
	color: #606c80;
	max-width: 720px;
}

.aio-login-network-overview__admin-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 10px;
	padding: 12px 24px;
	background: #f7ecfd;
	border-radius: 4px;
	color: #6e16df;
	font-size: 14px;
	font-weight: 600;
	line-height: 1;
	text-decoration: none;
	white-space: nowrap;
	flex-shrink: 0;
}

.aio-login-network-overview__admin-btn:hover,
.aio-login-network-overview__admin-btn:focus {
	color: #6e16df;
	opacity: 0.9;
}

.aio-login-network-overview__stats {
	display: flex;
	gap: 20px;
	align-items: stretch;
}

.aio-login-network-overview__stat {
	flex: 1 1 0;
	min-width: 0;
	display: flex;
	align-items: flex-start;
	gap: 20px;
	padding: 20px 10px;
	background: #fff;
	border: 1px solid #f1f1f1;
	border-radius: 4px;
}

.aio-login-network-overview__stat-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 50px;
	height: 50px;
	flex-shrink: 0;
}

.aio-login-network-overview__stat-icon img {
	display: block;
	max-width: 100%;
	height: auto;
}

.aio-login-network-overview__stat-body {
	display: flex;
	flex-direction: column;
	gap: 14px;
	min-width: 0;
}

.aio-login-network-overview__stat-label {
	font-size: 18px;
	font-weight: 600;
	line-height: 18px;
	color: #404280;
}

.aio-login-network-overview__stat-value {
	font-size: 24px;
	font-weight: 600;
	line-height: 18px;
	color: #4a4a4a;
}

@media screen and (max-width: 1100px) {
	.aio-login-network-overview__stats {
		flex-direction: column;
	}
}

@media screen and (max-width: 782px) {
	.aio-login-dashboard-top {
		align-items: flex-start;
	}

	.aio-login-dashboard-site-filter {
		width: 100%;
		flex-wrap: wrap;
	}

	.aio-login-dashboard-site-filter select {
		min-width: 0;
		width: 100%;
		max-width: 100%;
	}

	.aio-login-network-overview__admin-btn {
		width: 100%;
	}
}
</style>
