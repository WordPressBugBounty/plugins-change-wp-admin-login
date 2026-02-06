<template>
	<div class="aio-login__metadata-container container">
		<h2>{{ title }}</h2>
		<p>{{ description }}</p>
		<slot name="configuration"></slot>
	</div>
</template>

<script>
export default {
	name: 'aio-login-metadata',

	props: {
		title: {
			type: String,
			default: ''
		},
		description: {
			type: String,
			default: ''
		}
	}
}
</script>

<style scoped>
.aio-login__metadata-container {
	height: 180px;
	position: relative;
	border: 1px solid #ebebeb;
	background: #fff;
	border-radius: 8px;
	padding-top: 25px !important;
	padding-bottom: 25px !important;
	margin-right: 15px;
	margin-bottom: 15px;
	box-shadow: 0px 4px 16px 0px #00000014;
}

.aio-login__metadata-container h2 {
	font-size: 18px;
	font-weight: 600;
	line-height: 18px;
	color: #405980;
}

.aio-login__metadata-container p {
	font-size: 14px;
	font-weight: 400;
	line-height: 21px;
	color: #405980;
}
</style>