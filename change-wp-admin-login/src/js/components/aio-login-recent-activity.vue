<template>
	<div>
		<div class="aio-login__recent-activity-wrapper">
			<h2>Recent Activity</h2>

			<div class="aio-login__table-wrapper">
				<div class="aio-login-table-nav">
					<div
						v-for="(tab, i ) in tabs"
						:class="{'aio-login__table-nav-item': true, 'active': tab.active }"
					>
						<a
							class="aio-login__activate"
							:href="'#' + tab.slug"
							@click="e => toggleShift( e, tab )"
						>{{ tab.title }}</a>
					</div>
				</div>

				<div style="border-radius: 0 0 5px 5px;overflow: hidden;" v-if="'lockouts' === current_tab">
					<table class="aio-login__table">
						<thead>
						<tr>
							<th v-for="lockout in lockout_headers">
								{{ lockout.title }}
							</th>
						</tr>
						</thead>

						<tr v-if="lockouts.length" v-for="lockout in lockouts">
							<td v-for="lockout_header in lockout_headers">
								{{ lockout[ lockout_header.value ] }}
							</td>
						</tr>
						<tr v-else>
							<td colspan="5">No lockouts found</td>
						</tr>
					</table>
				</div>

				<div style="border-radius: 0 0 5px 5px;overflow: hidden;" v-if="'failed-logins' === current_tab">
					<table class="aio-login__table">
						<thead>
						<tr>
							<th v-for="failed_login in failed_login_headers">
								{{ failed_login.title }}
							</th>
						</tr>
						</thead>

						<tr v-if="failed_logins.length" v-for="failed_login in failed_logins">
							<td v-for="failed_login_header in failed_login_headers">
								{{ failed_login[ failed_login_header.value ] }}
							</td>
						</tr>

						<tr v-else>
							<td colspan="7">No failed logins found</td>
						</tr>
					</table>
				</div>

			</div>
		</div>

		<div class="mt-25 mb-25 text-center">
			<a
				:href="view_all_activity_logs"
				class="aio-login__view-all-activity"
			>View All Activity</a>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-recent-activity',

	data: () => ( {
		view_all_activity_logs: aio_login__app_object.admin_url + '&tab=activity-log',

		tabs: [
			{
				title: 'Lockouts',
				slug: 'lockouts',
				url: '',
				active: true,
			},
			{
				title: 'Failed Logins',
				slug: 'failed-logins',
				url: '',
				active: false,
			},
		],

		lockout_headers: [
			{ title: 'Date & Time', value: 'time' },
			{ title: 'Country', value: 'country' },
			{ title: 'City', value: 'city' },
			{ title: 'User Agent', value: 'user_agent' },
			{ title: 'IP Address', value: 'ip_address' },
		],
		lockouts: [],

		failed_login_headers: [
			{ title: 'ID', value: 'id' },
			{ title: 'User login', value: 'user_login' },
			{ title: 'Date & Time', value: 'time' },
			{ title: 'Country', value: 'country' },
			{ title: 'City', value: 'city' },
			{ title: 'User Agent', value: 'user_agent' },
			{ title: 'IP Address', value: 'ip_address' },
		],
		failed_logins: [],

		current_tab: 'lockouts',
	} ),

	watch: {
		current_tab( val ) {
			if ( 'lockouts' === val ) {
				// this.getLockoutLogs();
				this.view_all_activity_logs = aio_login__app_object.admin_url + '&tab=activity-log#/lockouts';
			}
			if ( 'failed-logins' === val ) {
				// this.getFailedAttemptLogs();
				this.view_all_activity_logs = aio_login__app_object.admin_url + '&tab=activity-log#/failed-logins';
			}
		}
	},

	methods: {
		toggleShift( e, tab ) {
			e.preventDefault();

			this.tabs.forEach( tab => {
				tab.active = false;
			} );

			tab.active       = true;
			this.current_tab = tab.slug;
		},

		getFailedAttemptLogs() {
			axios.get( 'aio-login/dashboard/logs/lockouts' )
				.then( response => {
					this.lockouts = response.data;
				} )
				.catch( error => {} );
		},

		getLockoutLogs() {
			axios.get( 'aio-login/dashboard/logs/failed-logins' )
				.then( response => {
					this.failed_logins = response.data;
				} )
				.catch( error => {} );
		},
	},

	mounted() {
		this.getFailedAttemptLogs();
		this.getLockoutLogs();
	},
}
</script>

<style scoped>
.aio-login__recent-activity-wrapper h2 {
	color: #405980;
	font-size: 24px;
	font-weight: 600;
}

.aio-login-table-nav {
	height: 48px;
	border-radius: 8px 8px 0 0;
	background: #FBF5FF;
}

.aio-login__table-nav-item {
	padding: 15px 30px;
	display: inline-block;
}

.aio-login__table-nav-item a {
	color: #9516DF;
	font-weight: 600;
	font-size: 16px;
	line-height: 16px;
	text-decoration: none;
}

.aio-login__table-nav-item:first-child {
	border-radius: 8px 0 0 0;
}

.aio-login__table-nav-item.active {
	background: #9516DF;
}

.aio-login__table-nav-item.active a {
	color: #fff;
}

.aio-login__activate {
	cursor: pointer;
	display: inline-block;
}

.aio-login__table {
	width: 100%;
	border-collapse: collapse;
}

.aio-login__table thead th {
	font-size: 16px;
	color: #405980;
	padding: 15px 30px;
	background: #e8e5e8;
	text-align: left;
	border-bottom: 1px solid #EBE8EB;
	font-weight: 600;
}

.aio-login__table td {
	font-weight: 400;
	font-size: 16px;
	color: #405980;
	background: #ffffff;
	border-bottom: 1px solid #EBE8EB;
	padding: 15px 30px;
	text-align: left;
}

.aio-login__table tr:last-child td {
	border-bottom: none;
}

.aio-login__view-all-activity {
	color: #9516DF;
	font-weight: 600;
	font-size: 16px;
	line-height: 16px;
	text-decoration: none;
	background: #fff;
	padding: 12px 24px;
	border-radius: 4px;
}
</style>