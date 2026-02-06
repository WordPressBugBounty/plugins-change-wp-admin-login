<template>
	<div>
		<aio-login-social-login
			:has-pro="has_pro"
		/>
	</div>
</template>

<script>

export default {
	name: 'social-login-main',

	data: () => ( {
		has_pro: 'true' === aio_login__app_object.has_pro
	} )
}
</script>

<style scoped>

</style>
