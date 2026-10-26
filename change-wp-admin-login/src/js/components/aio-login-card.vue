<template>
	<div :class="container_class">
		<div v-if="'customize' === type">
			<div class="aio-login__card-content">
				<p class="aio-login__card-content__title">
					{{ $t('Customize Your WP Login') }}
				</p>
				<p class="aio-login__card-content__description">
					<em>{{ $t('Personalize your login page with real-time customization and instant preview.') }}</em>
				</p>
				<p class="aio-login__card-content__action">
					<a :href="adminURL">{{ $t('Customize Now') }}</a>
				</p>
			</div>
		</div>
		<div v-else class="aio-login__card-kpi">
			<span class="aio-login__card-kpi__icon" aria-hidden="true"></span>
			<div class="aio-login__card-kpi__top">
				<h3 class="aio-login__card-kpi__title">{{ title }}</h3>
				<aio-login-days-selector
					:type="type"
					v-on:update-days="updateDays"
				></aio-login-days-selector>
			</div>
			<p class="aio-login__card-kpi__count">{{ count }}</p>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-card',
	props: {
		type: {
			type: String,
			required: true
		},
		title: {
			type: String,
			default: ''
		}
	},

	data: () => ( {
		count: 0,
		duration: 'today',
		blog_id: 0,

		adminURL: ( typeof aio_login__app_object.login_customizer_url === 'string' && aio_login__app_object.login_customizer_url )
			? aio_login__app_object.login_customizer_url
			: aio_login__app_object.admin_url,
	} ),

	computed: {
		container_class() {
			return {
				'aio-login__card-container': true,
				'container': true,
				'container-success': 'success' === this.type,
				'container-failed': 'failed' === this.type,
				'container-lockouts': 'lockouts' === this.type,
				'container-customize': 'customize' === this.type
			};
		}
	},

	methods: {
		updateDays( days, type ) {
			this.duration = days;
			this.getCount( type, days );
		},

		onSiteChange( event ) {
			this.blog_id = event.detail && event.detail.blog_id ? event.detail.blog_id : 0;
			if ( 'customize' !== this.type ) {
				this.getCount( this.type, this.duration );
			}
		},

		getCount( type, duration ) {
			const params = {
				type: type,
				duration: duration,
			};
			if (
				'true' === ( window.aio_login__app_object.is_multisite || '' )
				&& 'true' === ( window.aio_login__app_object.is_network_admin || '' )
			) {
				params.blog_id = this.blog_id;
			}
			axios.get( 'aio-login/dashboard/get-counts', {
				params,
			} )
			.then( response => {
				this.count = response.data.count;
			} )
			.catch( () => {} );
		},
	},

	mounted() {
		if ( 'customize' !== this.type ) {
			this.getCount( this.type, 'today' )
		}
		window.addEventListener( 'aio-login-dashboard-site-change', this.onSiteChange );
	},

	beforeUnmount() {
		window.removeEventListener( 'aio-login-dashboard-site-change', this.onSiteChange );
	},
}
</script>

<style scoped>
/* Figma 766:3716 — KPI cards only (Customize unchanged). */
.aio-login__card-container {
	display: flex;
	flex-direction: column;
	border-radius: 8px;
	height: auto;
	min-height: 163px;
	position: relative;
	padding: 24px 20px 20px !important;
	margin-right: 0;
	box-sizing: border-box;
	overflow: visible;
}

@media screen and (max-width: 782px) {
	.aio-login__card-container {
		min-height: 140px;
		padding: 20px 16px 16px !important;
	}
}

.aio-login__card-kpi {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-height: 0;
	position: relative;
}

.aio-login__card-kpi__icon {
	position: absolute;
	top: 0;
	right: 0;
	width: 35px;
	height: 35px;
	background-repeat: no-repeat;
	background-position: center;
	background-size: contain;
	pointer-events: none;
}

.container-success .aio-login__card-kpi__icon {
	background-image: url( "../../images/dashboard-kpi/icon-success.svg" );
}

.container-failed .aio-login__card-kpi__icon {
	background-image: url( "../../images/dashboard-kpi/icon-failed.svg" );
}

.container-lockouts .aio-login__card-kpi__icon {
	background-image: url( "../../images/dashboard-kpi/icon-lockouts.svg" );
}

.aio-login__card-kpi__top {
	padding-right: 48px;
}

.aio-login__card-kpi__title {
	margin: 0 0 6px;
	font-size: 18px;
	font-weight: 600;
	line-height: 1.2;
}

.aio-login__card-kpi__count {
	margin: auto 0 0;
	padding-top: 20px;
	font-size: 32px;
	font-weight: 600;
	line-height: 1;
	align-self: flex-start;
}

.container-success {
	color: #6B998F;
	background: #ECFAF7;
}

.container-success .aio-login__card-kpi__title,
.container-success .aio-login__card-kpi__count {
	color: #6B998F;
}

.container-failed {
	color: #99876B;
	background: #FAF4EB;
}

.container-failed .aio-login__card-kpi__title,
.container-failed .aio-login__card-kpi__count {
	color: #99876B;
}

.container-lockouts {
	color: #796B99;
	background: #EEEBFA;
}

.container-lockouts .aio-login__card-kpi__title,
.container-lockouts .aio-login__card-kpi__count {
	color: #796B99;
}

.container-customize {
	background-image: url( "../../images/magic-wand-larg.png" ), url( "../../images/magic-wand-small.png" ), linear-gradient( 180deg, #00cc99 0%, #00b386 100% );
	background-repeat: no-repeat;
	background-position: top left, bottom right;
	color: #fff;
}

.aio-login__card-content {
	text-align: center;
}

.aio-login__card-content__title {
	margin-top: 28px;
	margin-bottom: 10px;
	font-size: 18px;
	font-weight: 600;
	color: #fff;
	line-height: 1.25;
}

.aio-login__card-content__description {
	margin: 0 12px 18px;
	font-size: 14px;
	font-weight: 400;
	color: rgba(255, 255, 255, 0.92);
	line-height: 1.45;
}

.aio-login__card-content__description em {
	font-style: italic;
}

.aio-login__card-content__action {
	margin: 0 0 20px;
}

.aio-login__card-content a {
	color: #fff;
	border: 1px solid #00A27A;
	background: #EAC25A;
	box-shadow: 0 4px 16px 0 #00cd9a;
	text-decoration: none;
	font-size: 16px;
	font-weight: 600;
	padding: 12px 24px;
	border-radius: 4px;
}
</style>
