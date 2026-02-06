<template>
	<div class="aio-login__header-container">
		<div class="container clearfix">
			<div class="float-left">
				<slot name="logo"></slot>
			</div>
			<div class="float-right">
				<slot name="version"></slot>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-header'
}
</script>

<style scoped>
.aio-login__header-container {
	background: #fff;
	height: 100px;
}
</style>