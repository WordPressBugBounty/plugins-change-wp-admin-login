<template>
	<aio-login-temporary-access
		:has-pro="has_pro"
	/>
</template>

<script>

export default {
	name: 'aio-login-temp-access',

	data: ( vm ) => ( {
		has_pro: vm.$parent.current_is_pro,
	} ),

	methods: {

	}
}
</script>

<style scoped>

</style>