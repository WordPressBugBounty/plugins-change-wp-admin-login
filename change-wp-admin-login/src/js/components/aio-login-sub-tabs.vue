<template>
	<div class="aio-login__subtabs-container">
		<div class="aio-login__subtabs-links">
			<router-link
				v-for="(subTab, index) in subTabs"
				:key="index"
				:to="subTab.slug"
				class="aio-login__subtab"
				active-class="active"
			>
				{{ subTab.title }}
				<span class="aio-login__pro-tab" v-if="subTab['is-pro'] && ! hasPro">PRO</span>
			</router-link>
		</div>
		<div class="aio-login__subtabs-actions">
			<slot name="actions"></slot>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-sub-tabs',

	props: {
		subTabs: {
			type: Array,
			required: true,
		},
	},

	computed: {
		hasPro() {
			const h = window.aio_login__app_object && window.aio_login__app_object.has_pro;
			return h === 'true' || h === true;
		},
	},
}
</script>

<style scoped>
.aio-login__subtabs-container {
	height: 48px;
	border-bottom: 1px solid #e8e8e8;
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding-right: 25px;
}

.aio-login__subtabs-actions {
	display: flex;
	align-items: center;
}

.aio-login__subtab {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 13px 24px;
	color: #7691B2;
	font-weight: 600;
	font-size: 16px;
	text-decoration: none;
	line-height: 1.25;
}

.aio-login__subtab.active {
	border-bottom: 2px solid #9516DF;
	color: #9516DF;
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
</style>
