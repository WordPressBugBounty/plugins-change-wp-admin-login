<template>
	<div>
		<aio-login-block-ip-address
			:has-pro="has_pro"
		/>
	</div>
</template>

<script>

export default {
	name: 'block-ip-addresses',

	data: ( vm ) => ( {
		has_pro: vm.$parent.current_is_pro,
	} ),
}
</script>

<style scoped>

</style>