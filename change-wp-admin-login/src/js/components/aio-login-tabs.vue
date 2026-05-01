<template>
	<div>
		<a
			v-for="( tab, t ) in tabs"
			:href="getHref( tab )"
			:class="getClasses( tab )"
			:key="t"
		>
			<img
				v-if="'getpro' !== tab.slug"
				class="aio-login__tab-icon"
				alt=""
				:src="getSrc( tab )"
			/>
			{{ tab.title }}
			<img
				v-if="'getpro' === tab.slug"
				class="aio-login__getpro-crown"
				alt=""
				:src="assetsUrl + 'images/pro-crown.svg'"
			/>
			<span class="aio-login__pro-tab" v-if="tab['is-pro'] && ! hasPro">
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
	} ),

	computed: {
		hasPro() {
			const h = window.aio_login__app_object && window.aio_login__app_object.has_pro;
			return h === 'true' || h === true;
		},
	},

	watch: {
		test_tab() {
			// Main tab + active sub-tab (router) both drive overlay; keep logic in aio-login-app.
			if ( this.$parent && typeof this.$parent.syncCurrentTabAccess === 'function' ) {
				this.$parent.syncCurrentTabAccess();
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
			if ( 'social-login' === tab.slug || 'integrations' === tab.slug ) {
				return this.assetsUrl + `images/icons/${ tab.icon }${ this.activeTab( tab ) ? '-active' : '' }.svg`;
			}
			return this.assetsUrl + `images/icons/${ tab.icon }${ this.activeTab( tab ) ? '-active' : '' }.png`;
		},

		isset( arg ) {
			return typeof arg !== 'undefined';
		}
	}
}
</script>

<style scoped>
.aio-login__link-wrapper {
	padding: 14px 20px;
	color: #7691B2;
	font-weight: 600;
	font-size: 16px;
	line-height: 1.25;
	text-decoration: none;
	background: #F8F8F8;
	display: inline-flex;
	align-items: center;
	gap: 8px;
	margin-right: 2px;
	border-radius: 4px 4px 0 0;
	vertical-align: bottom;
}

.aio-login__tab-icon {
	display: block;
	flex-shrink: 0;
	align-self: center;
	max-height: 24px;
	width: auto;
	height: auto;
	object-fit: contain;
}

.aio-login__link-wrapper.active {
	color: #9516DF;
	background: #ffffff;
}

.aio-login__pro-tab {
	display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    min-width: 34px;
    padding: 5px 6px;
    margin: 0;
    background: #9516DF;
    border-radius: 3px;
    color: #FFFFFF;
    font-family: Figtree, sans-serif;
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    /* height: 18px; */
    flex-shrink: 0;
}

.aio-login__link-wrapper.getpro {
	background-image: linear-gradient(180deg, #9516df 0%, #510c79 100%);
	color: #fff;
	gap: 6px;
	font-weight: 700;
}

.aio-login__getpro-crown {
	width: 17px;
	height: 12px;
	object-fit: contain;
}

</style>