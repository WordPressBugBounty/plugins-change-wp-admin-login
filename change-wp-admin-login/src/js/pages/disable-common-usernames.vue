<template>
	<div>
		<!-- Free feature: always unlock UI (ignore plan-tab blur). -->
		<aio-login-disable-common-usernames :has-pro="true" />
	</div>
</template>

<script>
export default {
	name: 'disable-common-usernames',
}
</script>

<style scoped>

</style>