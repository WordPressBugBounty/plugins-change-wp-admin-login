<template>
	<div>
		<h1>
			<span>Lockouts</span>
			<aio-login-tooltip
				:content="tooltipContent.loginAttemptLogs.content"
				:title="tooltipContent.loginAttemptLogs.title"
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
	name: 'lockouts',

	data: ( vm ) => ( {
		tooltipContent,
		namespace: 'aio-login/logs/lockouts',

		headers: [
			{ key: 'time', value: 'Date & Time' },
			{ key: 'country', value: 'Country' },
			{ key: 'city', value: 'City' },
			{ key: 'user_agent', value: 'User Agent' },
			{ key: 'ip_address', value: 'IP Address' },
		],

		data: [],
	} ),

	methods: {
		getLogs() {
			axios.get( this.namespace )
				.then( response => {
					this.data = response.data;
				} );
		}
	},

	mounted() {
		this.getLogs();
	},
}
</script>

<style scoped>

</style>