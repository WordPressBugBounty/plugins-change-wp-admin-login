<template>
	<div class="aio-login__subtabs-container">
		<router-link
			v-for="(subTab, index) in subTabs"
			:to="subTab.slug"
			class="aio-login__subtab"
			active-class="active"
			>
			{{ subTab.title }}
			<span class="aio-login__pro-tab" v-if="subTab['is-pro']">PRO</span>
		</router-link>
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
	}
}
</script>

<style scoped>
.aio-login__subtabs-container {
	height: 48px;
	border-bottom: 1px solid #e8e8e8;
}

.aio-login__subtab {
	padding: 13px 24px;
	color: #7691B2;
	font-weight: 600;
	font-size: 16px;
	text-decoration: none;
	line-height: 50px;
}

.aio-login__subtab.active {
	border-bottom: 2px solid #9516DF;
	color: #9516DF;
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
</style>