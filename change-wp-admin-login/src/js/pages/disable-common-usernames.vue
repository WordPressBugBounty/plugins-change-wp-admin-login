<template>
	<div>
		<aio-login-disable-common-usernames
			:has-pro="has_pro"
		/>
	</div>
</template>

<script>

export default {
	name: 'disable-common-usernames',

	data: ( vm ) => ( {
		has_pro: vm.$parent.current_is_pro,
	} ),
}
</script>

<style scoped>

</style>