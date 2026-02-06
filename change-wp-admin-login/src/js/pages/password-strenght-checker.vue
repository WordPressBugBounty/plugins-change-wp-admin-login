<template>
	<div>
		<aio-login-password-strenght-checker
			:has-pro="has_pro"
		/>
	</div>
</template>

<script>

export default {
	name: 'password-strenght-checker',

	data: ( vm ) => ( {
		has_pro: vm.$parent.current_is_pro,
	} ),
}
</script>

<style scoped>

</style>