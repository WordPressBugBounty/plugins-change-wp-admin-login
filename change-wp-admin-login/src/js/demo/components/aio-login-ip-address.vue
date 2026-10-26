<template>
	<div v-if="page_loaded" class="aio-login-ipa-wrapper">
		<div>
			<aio-login-form
				:action="nonce"
				v-on:handle-submit="handleSubmit"
			>
				<template v-slot:title>
					<span>{{ $t('Block IP Address') }}</span>
					<aio-login-tooltip
						:content="tooltipContent.blockIpAddresses.content"
						:title="tooltipContent.blockIpAddresses.title"
						placement="bottom"
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
								v-on:toggle-input="toggleInput"
							></aio-login-toggle>
							<p class="desc">
								<strong>
									{{ $t('Enable to control access using whitelist or blacklist.') }}
								</strong>
							</p>
						</td>
					</tr>

					<tr v-if="form_data.enabled">
						<th>
							<label for="mode">{{ $t('Mode') }}</label>
						</th>
						<td>
							<select id="mode" class="regular-text aio-login-ipa-mode-select" v-model="form_data.mode">
								<option value="whitelist">{{ $t('Whitelist mode - allow access to specific IPs, lockout all others') }}</option>
								<option value="blacklist">{{ $t('Blacklist mode - block access to specific IPs, allow all others') }}</option>
							</select>
						</td>
					</tr>

					<tr v-if="form_data.enabled && 'blacklist' === form_data.mode">
						<th><label for="blacklist">{{ $t('Blacklist') }}</label></th>
						<td><textarea id="blacklist" class="regular-text" v-model="form_data.blacklist"></textarea></td>
					</tr>

					<tr v-if="form_data.enabled && 'whitelist' === form_data.mode">
						<th><label for="whitelist">{{ $t('Whitelist') }}</label></th>
						<td><textarea id="whitelist" class="regular-text" v-model="form_data.whitelist"></textarea></td>
					</tr>

					<tr v-if="form_data.enabled">
						<th><label for="block-message">{{ $t('Block Message') }}</label></th>
						<td><textarea id="block-message" class="regular-text" v-model="form_data.block_message"></textarea></td>
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
	name: 'aio-login-block-ip-address',
	slug: 'block-ip-addresses',
	props: {
		hasPro: { type: Boolean, default: true },
	},
	data: () => ( {
		tooltipContent,
		nonce: '',
		form_data: {
			enabled: false,
			mode: 'whitelist',
			blacklist: '',
			whitelist: '',
			block_message: 'Your IP Address is blocked',
		},
		page_loaded: false,
		snackbar: {
			message: '',
			show: false,
			duration: 3000,
		},
	} ),
	methods: {
		toggleInput( val ) {
			this.form_data.enabled = val;
		},

		showSnackbar( message ) {
			this.snackbar.message = message || this.$t( 'Settings saved successfully.' );
			this.snackbar.show = true;
		},

		handleSubmit() {
			this.form_data._wpnonce = this.nonce;
			axios.post( 'aio-login/block-ip-address/save-settings', this.form_data )
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

		loadComponent() {
			this.$nextTick( () => {
				this.page_loaded = true;
			} );
			axios.get( 'aio-login/block-ip-address/get-settings' )
				.then( ( response ) => {
					this.nonce = response.data.nonce;
					this.form_data.enabled = response.data.enabled;
					this.form_data.mode = response.data.mode;
					this.form_data.blacklist = response.data.blacklist;
					this.form_data.whitelist = response.data.whitelist;
					this.form_data.block_message = response.data.block_message;
				} )
				.catch( () => {} );
		},
	},
	mounted() {
		this.loadComponent();
	},
};
</script>

<style scoped>
.aio-login-ipa-wrapper {
	position: relative;
}
</style>
