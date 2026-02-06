<template>
	<div v-if="page_loaded">
		<aio-login-form
			:action="nonce"
			v-on:handle-submit="handleSubmit"
		>
			<template v-slot:title>
				Limit Login Attempts
			</template>

			<template v-slot:form-fields>
				<tr>
					<th scope="row">
						<label for="enable">Enable</label>
					</th>

					<td>
						<aio-login-toggle
							id="enable"
							name="aio_login__lla_enable"
							:enabled="form_data.enabled"
							v-on:toggle-input="handleValue"
						/>

						<p class="desc">
							<strong>
								Enable this option to limit login attempts.
							</strong>
						</p>
					</td>
				</tr>

				<tr v-if="form_data.enabled">
					<th scope="row">
						<label for="maximum-attempts">Maximum Attempts</label>
					</th>

					<td>
						<input
							id="maximum-attempts"
					        name="aio_login_limit_attempts_maximum_attempts"
					        v-model="form_data.maximum_attempts"
					        type="number"
							min="1"
							class="regular-text"
						/>
					</td>
				</tr>

				<tr v-if="form_data.enabled">
					<th scope="row">
						<label for="timeout">Timeout</label>
					</th>

					<td>
						<input
							id="timeout"
							name="aio_login_limit_attempts_timeout"
							v-model="form_data.timeout"
							type="number"
							min="1"
							class="regular-text"
						/>
					</td>
				</tr>

				<tr v-if="form_data.enabled">
					<th scope="row">
						<label for="lockout-message">Lockout Message</label>
					</th>

					<td>
						<textarea
							id="lockout-message"
							name="aio_login_limit_attempts_lockout_message"
							v-model="form_data.lockout_message"
							class="regular-text"
						></textarea>
					</td>
				</tr>
			</template>
		</aio-login-form>

		<aio-login-snackbar
			:message="snackbar.message"
			v-if="snackbar.show"
			:duration="snackbar.timeout"
			v-on:close="handleSnackbarClose"
		/>
	</div>
</template>

<script>

export default {
	name: 'limit-login-attempts',

	data: ( vm ) => ( {
		page_loaded: false,

		nonce: '',

		form_data: {
			enabled: false,
			maximum_attempts: '5',
			timeout: '5',
			lockout_message: 'You have been locked out due to too many login attempts.',
		},

		snackbar: {
			message: '',
			show: false,
			timeout: 3000,
		},

		namespace: 'aio-login/limit-login-attempts',
	} ),

	methods: {
		handleValue( value ) {
			this.form_data.enabled = value;
		},

		handleSubmit( e ) {

			axios.post( this.namespace + '/save-settings', {
				enabled: this.form_data.enabled,
				maximum_attempts: this.form_data.maximum_attempts,
				timeout: this.form_data.timeout,
				lockout_message: this.form_data.lockout_message,
				_wpnonce: this.nonce,
			} )
				.then( response => {

					this.snackbar.message = response.data.message;
					this.snackbar.show = true;

				} )
				.catch( error => {

				} );
		},

		handleSnackbarClose() {
			this.snackbar.show = false;
		},
	},

	mounted() {
		axios.get( this.namespace + '/get-settings' )
			.then( response => {
				this.form_data.enabled          = response.data.enabled;
				this.form_data.maximum_attempts = response.data.maximum_attempts;
				this.form_data.timeout          = response.data.timeout;
				this.form_data.lockout_message  = response.data.lockout_message;
				this.nonce 			    		= response.data.nonce;
				this.page_loaded                = true;
			} )
			.catch( error => {
				this.page_loaded = true;
			} );
	}
}
</script>

<style scoped>

</style>