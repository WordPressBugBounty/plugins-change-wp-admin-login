<template>
	<div v-if="page_loaded" class="aio-login-dcu-wrapper" @click="iWasTriggered">
		<div>
			<aio-login-form
				:action="nonce"
				v-on:handle-submit="handleSubmit"
			>
				<template v-slot:title>
					<span>{{ $t('Disable Common Usernames') }}</span>
					<aio-login-tooltip
						:content="tooltipContent.disableCommonUsernames.content"
						:title="tooltipContent.disableCommonUsernames.title"
						placement="bottom"
					/>
					<aio-login-network-notice
						feature="disable_common_usernames"
						v-on:can-edit="onNetworkCanEdit"
					/>
				</template>

				<template v-slot:form-fields>
					<tr>
						<th>
							<label for="enable">{{ $t('Enable') }}</label>
						</th>
						<td>
							<aio-login-toggle
								id="enable"
								name="enable"
								:enabled="form_data.enabled"
								:disabled="!network_can_edit"
								v-on:toggle-input="toggleInput"
							/>
							<p class="desc">
								<strong>
									{{ $t('Enable to prevent use of common usernames.') }}
								</strong>
							</p>
						</td>
					</tr>

					<tr v-if="form_data.enabled">
						<th>
							<label for="disable-usernames">{{ $t('Usernames to disable') }}</label>
						</th>
						<td>
							<textarea
								class="regular-text"
								v-model="form_data.usernames"
								:disabled="!network_can_edit"
							></textarea>
						</td>
					</tr>
				</template>
			</aio-login-form>
		</div>

		<aio-login-snackbar
			:message="snackbar.message"
			v-if="snackbar.show"
			:duration="snackbar.duration"
			v-on:close="snackbar.show = false"
		/>
	</div>
</template>

<script>
import tooltipContent from '../../tooltip-content.js';

export default {
	name: 'aio-login-disable-common-usernames',

	slug: 'disable-common-usernames',

	props: {
		hasPro: {
			type: Boolean,
			default: false,
		},
	},

	data: () => ( {
		tooltipContent,
		nonce: '',
		network_can_edit: true,
		form_data: {
			enabled: false,
			usernames: 'admin, test, user, admin123, test123, username',
		},

		page_loaded: false,

		snackbar: {
			message: '',
			show: false,
			duration: 3000,
		},
	} ),

	methods: {
		onNetworkCanEdit( can ) {
			this.network_can_edit = !! can;
		},
		iWasTriggered() {
			/* Free feature — no Pro upsell overlay. */
		},
		toggleInput( value ) {
			this.form_data.enabled = value;
		},

		showSnackbar( message ) {
			this.snackbar.message = message || this.$t( 'Settings saved successfully.' );
			this.snackbar.show = true;
		},

		loadComponent() {
			this.$nextTick( () => {
				this.page_loaded = true;
			} );

			axios.get( 'aio-login/disable-common-usernames/get-settings' )
				.then( response => {
					this.form_data.enabled   = response.data.enabled;
					this.form_data.usernames = response.data.usernames;
					this.nonce               = response.data.nonce;
				} )
				.catch( () => {} );
		},

		handleSubmit() {
			if ( ! this.network_can_edit ) {
				return;
			}
			this.form_data['_wpnonce'] = this.nonce;
			axios.post( 'aio-login/disable-common-usernames/save-settings', this.form_data )
				.then( ( response ) => {
					const message = ( response.data && response.data.message )
						? response.data.message
						: this.$t( 'Settings saved successfully.' );
					this.showSnackbar( message );
				} )
				.catch( ( error ) => {
					const message = ( error.response && error.response.data && error.response.data.message )
						? error.response.data.message
						: this.$t( 'Could not save settings. Please try again.' );
					this.showSnackbar( message );
				} );
		},
	},

	mounted() {
		this.loadComponent();
	},
};
</script>

<style scoped>
.aio-login-dcu-wrapper {
	position: relative;
}

.aio-login-dcu-content-overflow {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;

	backdrop-filter: blur(1px);
}
</style>
