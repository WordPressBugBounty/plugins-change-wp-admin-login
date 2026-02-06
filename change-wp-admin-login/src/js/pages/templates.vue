<template>
	<div>
		<aio-login-templates
			:has-pro="has_pro"
		/>
	</div>
</template>

<script>

export default {
	name: 'templates',

	data: ( vm ) => ( {
		has_pro: vm.$parent.current_is_pro,
	} )
}
</script>

<style scoped>

</style>