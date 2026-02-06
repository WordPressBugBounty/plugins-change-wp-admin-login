<template>
	<div class="aio-login__meta-container">
		<div>
			<aio-login-metadata
				title="Custom Login URL"
				description="Attackers often try exploits on /wp-login or /wp-admin as a default login URL for WordPress. Change it to avoid these attacks and have an easily memorizable login URL."
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch aio-login-configuration-btn-wrapper">
						<a class="aio-login-configuration-btn" :href="adminURL + '&tab=login-protection'">Configure</a>
					</div>
				</template>
			</aio-login-metadata>
		</div>
		<div>
			<aio-login-metadata
				title="Limit Login Attempts"
				description="Limit the number of times a user IP can attempt to log in to your wp-admin with incorrect credentials. Once the login attempt limit is reached, the IP from which the attempts have originated will be blocked for default period of time."
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch">
						<select class="aio-login__configuration-select" v-model="limit_login_attempts" @change="limitLoginAttempts">
							<option value="on">On</option>
							<option value="off">Off</option>
						</select>
					</div>
				</template>
			</aio-login-metadata>
		</div>
		<div>
			<aio-login-metadata
				title="Two Factor Authentication"
				description="Two-factor authentication forces admin users to login only after providing a token, generated from the Authenticator applications. When you enable this option, all admin users will be asked to configure their two-factor authentication in the Authenticator app on their next login."
				@click="iWasTriggered"
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch">
						<div class="">
							<aio-login-toggle
								id="toggle-tfa"
								name="toggle-tfa"
								:disabled="! has_pro"
								v-on:toggle-input="twoFactorAuthenticationSettings"
								:enabled="two_factor_auth"
							/>
						</div>
					</div>
				</template>
			</aio-login-metadata>
		</div>
		<div>
			<aio-login-metadata
				title="Block IP Address"
				description="By default your WordPress login can be accessed by any IP or user. You can use this feature to allow login only for specific IPs or users in order to prevent brute-force attacks or malicious login attempts."
				@click="iWasTriggered"
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch">
						<div class="">
							<aio-login-toggle
								id="toggle-bipa"
								name="toggle-bipa"
								:disabled="! has_pro"
								v-on:toggle-input="blockIPAddressSettings"
								:enabled="block_ip_address"
							/>
						</div>
					</div>
				</template>
			</aio-login-metadata>
		</div>


		<aio-login-snackbar
			:message="snackbar.message"
			:duration="snackbar.duration"
			v-if="snackbar.show"

			v-on:close="handleCloseSnackbar"
		/>
	</div>
</template>

<script>
export default {
	name: 'aio-login-meta',

	data: ( vm ) => ( {
		adminURL: aio_login__app_object.admin_url,

		has_pro: 'true' === aio_login__app_object.has_pro,

		limit_login_attempts: 'off',

		two_factor_auth: false,
		block_ip_address: false,

		snackbar: {
			message: 'Settings saved successfully',
			duration: 3000,
			show: false
		},
	} ),

	watch: {
	},

	methods: {

		iWasTriggered() {
			if ( ! this.has_pro ) {
				this.$parent.$parent.popup = true;
			}
		},

		limitLoginAttempts() {
			axios.post( 'aio-login/dashboard/update/limit-login-attempts', {
				value: this.limit_login_attempts,
			} )
			.then( response => {
				if ( 'success' === response.data.status	) {
					if ( 'on' === this.limit_login_attempts ) {
						window.location.href = aio_login__app_object.admin_url + '&tab=login-protection#/limit-login-attempts';
					}
				}

				this.snackbar.message = response.data.message;
				this.snackbar.show = true;
			} )
			.catch( error => {

			}  );
		},

		handleCloseSnackbar() {
			this.snackbar.show = false;
		},

		twoFactorAuthenticationSettings( i ) {
			var value = i ? 'on' : 'off';

			axios.post( 'aio-login/dashboard/update/two-factor-authentication', {
				value: value
			} )
				.then( response => {
					if ( 'success' === response.data.status	) {
						if ( i ) {
							window.location.href = aio_login__app_object.admin_url + '&tab=security#/2fa';
						}
					}

					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				} )
				.catch( error => {

				}  );
		},

		blockIPAddressSettings( i ) {
			var value = i ? 'on' : 'off';

			axios.post( 'aio-login/dashboard/update/block-ip-address', {
				value: value
			} )
				.then( response => {
					if ( 'success' === response.data.status	) {
						if ( i ) {
							window.location.href = aio_login__app_object.admin_url + '&tab=security#/block-ip-addresses';
						}
					}

					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				} )
				.catch( error => {

				}  );
		},


	},

	mounted() {
		axios.get( 'aio-login/dashboard/get-settings' )
			.then( response => {
				this.limit_login_attempts = response.data.limit_login_attempts;
				this.two_factor_auth = 'on' === response.data.two_factor_auth;
				this.block_ip_address = 'on' === response.data.block_ip_address;
			} )
			.catch( error => {} );
	},
}
</script>

<style scoped>
.aio-login__meta-container {
	display: grid;
	grid-template-columns: 50% 50%;
}

.aio-login-configuration-btn-wrapper {
	background: #F7ECFD;
	border-radius: 4px;
	padding: 12px 24px;
}

.aio-login-configuration-btn {
	color: #9516DF;
	line-height: 14px;
	text-decoration: none;
	font-size: 16px;
	font-weight: 600;
}

.aio-login__configuration-select {
	margin-top: 20px !important;
	padding: 12px 16px !important;
	width: 100px !important;
	font-size: 14px !important;
	line-height: 14px !important;
	font-weight: 600 !important;
	color: #9516DF !important;
	border: 1px solid #9516DF !important;
}

.aio-login__configuration-switch {
	position: absolute;
	bottom: 25px;
	right: 25px;
}
</style>