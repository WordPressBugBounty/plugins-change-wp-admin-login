<template>
	<div v-if="page_loaded" style="position: relative;" @click="iWasTriggered">
		<aio-login-form action="nonce" v-on:handle-submit="handleSubmit">
			<template v-slot:title>
				Two Factor Authentication
			</template>
			
			<template v-slot:form-fields>
				<tr>
					<th>
						<label for="enable">Enable Two Factor Authentication</label>
					</th>
					<td>
						<aio-login-toggle id="enable" name="enable" v-on:toggle-input="toggleInput" :enabled="enable" />
					</td>
				</tr>
			</template>
		</aio-login-form>



		<div style="position:absolute;top:0;left:0;width: 100%;height: 100%;backdrop-filter: blur(1px)" v-if="! hasPro">

		</div>
	</div>
</template>

<script>

export default {
	name: 'aio-login-two-factor-authentication',

	slug: '2fa',

	props: {
		hasPro: {
			type: Boolean,
			default: false,
		},
	},

	data: ( vm ) => ( {
		page_loaded: false,
		enable: false,
		modal: false,
		screen_1: false,
		screen_2: false,

		value_changed: false,

		qrcode: '',
		secret: '',
		user_enabled: false,

		nonce: '',

		form_data: {
			enabled: vm.enable,
			otp: '',
			secret: vm.secret,
			user_id: '',
		}
	} ),

	watch: {
		enable( value ) {
			if ( value ) {
				this.modal = true;
			}

			this.value_changed = true;
			this.form_data.enabled = value;
		},

		modal( value ) {
			if ( value ) {
				this.screen_1 = true;
			} else {
				this.screen_1 = false;
				this.screen_2 = false;
			}
		},

		secret( v ) {
			this.form_data.secret = v;
		}
	},

	methods: {
		iWasTriggered() {

			this.$parent.$parent.$parent.popup = true;
		},
		loadComponent() {
			this.$nextTick( () => {
				axios.get( 'aio-login-pro/tfa/get-settings' )
					.then( response => {
						this.page_loaded = true;

						this.enable = response.data.enabled;
						this.qrcode = response.data.qrcode_url;
						this.secret = response.data.base32;
						this.user_enabled = response.data.user_enabled;
						this.form_data.user_id = response.data.user_id;

						this.nonce = response.data.enable_nonce;
					} );
			} );

		},

		toggleInput( e ) {
			this.enable = e;
		},

		closePopup() {
			this.modal = false;
			this.enable = false;
		},

		verifyTOTP( e ) {
			e.preventDefault();

			axios.post( 'aio-login-pro/tfa/verify', this.form_data )
				.then()
				.catch( error => {
				} );
		},

		handleSubmit( e ) {
			let allow = true;
			if ( ! this.form_data.enabled && this.value_changed ) {
				allow = confirm( 'Are you sure you want to disable Two Factor Authentication? It will be disabled for all users' );
			}

			if ( allow ) {
				this.form_data['_wpnonce'] = this.nonce;

				axios.post( 'aio-login-pro/tfa/save-settings', this.form_data )
					.then( response => {
						this.value_changed = false;
					} );
			} else {
				this.enable = true;
			}
		}
	},

	mounted() {
		if ( ! this.hasPro ) {
			this.page_loaded = true;
		} else {
			this.loadComponent();
		}
	}
}
</script>

<style scoped>
.aio-login__screen {
	width: 100%;
	height: 400px;
}

/*
slide-fade-new-screen
slide-fade-prev-screen
*/

.slide-fade-new-screen-enter-active {
	transition: all 0.3s ease;
}

.slide-fade-new-screen-leave-active {
	transition: all 0.8s cubic-bezier(1, 0.5, 0.8, 1);
}

.slide-fade-new-screen-enter-from,
.slide-fade-new-screen-leave-to {
	transform: translateX(20px);
	opacity: 0;
}

.slide-fade-prev-screen-enter-active {
	transition: all 0.3s ease;
}

.slide-fade-prev-screen-leave-active {
	transition: all 0.8s cubic-bezier(1, 0.5, 0.8, 1);
}

.slide-fade-prev-screen-enter-from,
.slide-fade-prev-screen-leave-to {
	transform: translateX(20px);
	opacity: 0;
}

.aio-login__clearfix::after {
	clear: both;
	display: table;
	content: '';
}
</style>