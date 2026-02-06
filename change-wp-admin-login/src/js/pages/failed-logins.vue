<template>
	<div>
		<h1>Failed Logins</h1>

		<aio-login-datatable
			:headers="headers"
			:rows="data"
		></aio-login-datatable>
	</div>
</template>

<script>

export default {
	name: 'failed-logins',

	data: ( vm ) => ( {
		namespace: 'aio-login/logs/failed-login',

		headers: [
			{ value: 'ID', key: 'id' },
			{ value: 'User Login', key: 'user_login' },
			{ value: 'Date & Time', key: 'time' },
			{ value: 'Country', key: 'country' },
			{ value: 'City', key: 'city' },
			{ value: 'User Agent', key: 'user_agent' },
			{ value: 'IP Address', key: 'ip_address' },
		],

		data: [],
	} ),

	methods: {
		get_logs() {
			axios.get( this.namespace )
				.then( response => {
					this.data = response.data;
				} )
				.catch( error => {

				} );
		}
	},

	mounted() {
		this.get_logs();
	}
}
</script>

<style scoped>

</style>