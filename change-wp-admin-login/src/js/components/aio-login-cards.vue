<template>
	<div class="aio-login__cards-container">
		<div>
			<aio-login-card
				type="success"
				:title="$t('Successful Logins')"
			></aio-login-card>
		</div>
		<div>
			<aio-login-card
				type="failed"
				:title="$t('Failed Logins')"
			></aio-login-card>
		</div>
		<div>
			<aio-login-card
				type="lockouts"
				:title="$t('Lockouts')"
			></aio-login-card>
		</div>
		<div>
			<aio-login-card type="customize" />
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-cards',
}
</script>

<style scoped>
.aio-login__cards-container {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 24px;
	overflow: visible;
}

@media screen and (max-width: 1200px) {
	.aio-login__cards-container {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media screen and (max-width: 782px) {
	.aio-login__cards-container {
		grid-template-columns: 1fr;
		gap: 12px;
	}
}
</style>
