<template>
	<div>
		<h1>
			<span>Failed Logins</span>
			<aio-login-tooltip
				:content="tooltipContent.failedLoginAttempts.content"
				:title="tooltipContent.failedLoginAttempts.title"
				placement="bottom"
			/>
		</h1>

		<aio-login-datatable
			:headers="headers"
			:rows="data"
		></aio-login-datatable>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';

export default {
	name: 'failed-logins',

	data: ( vm ) => ( {
		tooltipContent,
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