<template>
	<div>
		<a
			v-for="( tab, t ) in tabs"
			:href="getHref( tab )"
			:class="getClasses( tab )"
			:key="t"
			:style="( tab['is-pro'] ? 'padding-top: 16px;' : 'padding: 15px 24px;' )"
		>
			<img
				alt="icon"
				:src="getSrc( tab )"
				:style="getStyle( tab )"
			/>
			{{ tab.title }}
			<span class="aio-login__pro-tab" v-if="tab['is-pro'] && !hasPro">
				PRO
			</span>
		</a>
	</div>
</template>

<script>
export default {
	name: 'aio-login-tabs',
	props: {
		tabs: {
			type: Array,
			required: true,
		},
		assetsUrl: {
			type: String,
			required: true,
		},
	},

	data: ( vm ) => ( {
		test_tab: {},
		hasPro: 'true' === window.aio_login__app_object.has_pro,
	} ),

	watch: {
		test_tab( value ) {
			if ( this.isset( value['is-pro'] ) && this.isset( value['plan'] ) ) {
				this.$parent.current_is_pro = true;
			}
		},
	},

	methods: {
		getHref( tab ) {
			var location = window.aio_login__app_object.admin_url;
			return location + '&tab=' + tab.slug;
		},

		activeTab( tab ) {
			var location = window.location.href;
			var url = new URL( location );
			if ( ! url.searchParams.get( 'tab' ) ) {
				return tab.slug === 'dashboard';
			}
			return url.searchParams.get( 'tab' ) === tab.slug;
		},

		getClasses( tab ) {
			if ( this.activeTab( tab ) ) {
				this.test_tab = tab;
			}
			return {
				'active': this.activeTab( tab ),
				'aio-login__link-wrapper': true,
				'getpro': 'getpro' === tab.slug,
			}
		},

		getSrc( tab ) {
			if ( 'getpro' === tab.slug ) {
				return this.assetsUrl + `images/icons/${ tab.icon }.png`;
			}
			if ( 'social-login' === tab.slug || 'integrations' === tab.slug ) {
				return this.assetsUrl + `images/icons/${ tab.icon }${ this.activeTab( tab ) ? '-active' : '' }.svg`;
			}
			return this.assetsUrl + `images/icons/${ tab.icon }${ this.activeTab( tab ) ? '-active' : '' }.png`;
		},

		getStyle( tab ) {
			if ( 'dashboard' === tab.slug ) {
				return {
					marginBottom: '-5px',
				};
			}

			if ( 'getpro' === tab.slug ) {
				return {
					marginBottom: '-2px',
				};
			}

			return {
				marginBottom: '-7px'
			};
		},

		isset( arg ) {
			return typeof arg !== 'undefined';
		}
	}
}
</script>

<style scoped>
.aio-login__link-wrapper {
	padding: 13px 24px;
	color: #7691B2;
	font-weight: 600;
	font-size: 16px;
	text-decoration: none;
	background: #F8F8F8;
	display: inline-block;
	margin-right: 2px;
	border-radius: 4px 4px 0 0;
}

.aio-login__link-wrapper.active {
	color: #9516DF;
	background: #ffffff;
}

.aio-login__pro-tab {
	display: inline-flex;
	width: 40px;
	justify-content: center;
	background: linear-gradient(180deg, #6E16DF 0%, #510C79 121.05%);
	border-radius: 2px;
	text-align: center;
	color: #ffce50;
	font-family: Figtree;
	font-size: 12.88px;
	font-weight: 600;
	text-transform: uppercase;
	height: 20px;
	align-items: center;
	margin-left: 10px !important;
}

.aio-login__link-wrapper.getpro {
	background-image: linear-gradient(180deg, #6D16DF 0%, #490F95 100%);
	color: #fff;
}

</style>