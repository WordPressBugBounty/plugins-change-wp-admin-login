<template>
	<div class="aio-login__cards-container">
		<div>
			<aio-login-card
				type="success"
				title="Success"
			></aio-login-card>
		</div>
		<div>
			<aio-login-card
				type="failed"
				title="Failed"
			></aio-login-card>
		</div>
		<div>
			<aio-login-card
				type="lockouts"
				title="Lockouts"
			></aio-login-card>
		</div>
		<div>
			<aio-login-card type="customize" />
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-cards'
}
</script>

<style scoped>
.aio-login__cards-container {
	display: grid;
	grid-template-columns: 25% 25% 25% 25%;
}
</style>