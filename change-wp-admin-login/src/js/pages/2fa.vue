<template>
	<div>
		<aio-login-two-factor-authentication
			:has-pro="has_pro"
		/>
	</div>
</template>

<script>
export default {
	name: '2fa',

	data: ( vm ) => ( {
		has_pro: vm.$parent.current_is_pro,
	} )
}
</script>

<style scoped>

</style>