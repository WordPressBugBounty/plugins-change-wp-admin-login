<template>
	<div class="aio-login-2fa-methods" v-if="page_loaded">
		<p class="aio-login-2fa-methods__method-note">
			{{ $t('Configure Email One-Time Code for two-factor authentication.') }}
		</p>

		<div class="aio-login-2fa-methods__list">
			<div class="aio-login-2fa-methods__item aio-login-2fa-policies__card" :class="{ 'is-network-locked': !network_can_edit }">
				<div class="aio-login-2fa-policies__card-header">
					<span class="aio-login-2fa-policies__icon" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M4 6.5h16c.83 0 1.5.67 1.5 1.5v8c0 .83-.67 1.5-1.5 1.5H4A1.5 1.5 0 012.5 16V8c0-.83.67-1.5 1.5-1.5z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
							<path d="M3 7.5l8.65 5.77a1.5 1.5 0 001.7 0L22 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
					<div class="aio-login-2fa-methods__content">
						<h2 class="aio-login-2fa-methods__title">
							{{ $t('Email One-Time Code') }}
							<aio-login-tooltip :content="tooltipContent.twoFactorEmailOtp.content" />
						</h2>
						<div class="aio-login-2fa-methods__desc">{{ $t('Send a one-time verification code to users via email during login.') }}</div>
					</div>
					<div class="aio-login-2fa-methods__controls">
						<button
							type="button"
							class="aio-login-2fa-methods__expand-btn"
							:disabled="!email_otp"
							:aria-expanded="email_otp && email_expanded ? 'true' : 'false'"
							aria-label="Toggle Email One-Time Code details"
							@click="toggleEmailExpanded"
						>
							<span
								class="aio-login-2fa-methods__chevron"
								:class="{ 'is-expanded': email_otp && email_expanded }"
							/>
						</button>
						<aio-login-toggle
							id="email_otp"
							name="email_otp"
							:enabled="email_otp"
							:disabled="!network_can_edit"
							v-on:toggle-input="toggleEmailOtp"
						/>
					</div>
				</div>
			</div>

			<div
				v-if="email_otp && email_expanded"
				class="aio-login-2fa-methods__email-settings"
			>
				<div class="aio-login-2fa-methods__grid">
					<div>
						<label class="aio-login-2fa-methods__label">{{ $t('Sender Name *') }}</label>
						<input
							class="aio-login-2fa-methods__input"
							type="text"
							v-model="form_data.email_settings.sender_name"
							:disabled="!network_can_edit"
						>
					</div>
					<div>
						<label class="aio-login-2fa-methods__label">{{ $t('Sender Email *') }}</label>
						<input
							class="aio-login-2fa-methods__input"
							type="email"
							v-model="form_data.email_settings.sender_email"
							:disabled="!network_can_edit"
						>
					</div>
				</div>
				<div class="aio-login-2fa-methods__field">
					<label class="aio-login-2fa-methods__label">{{ $t('Admin Notification Email') }}</label>
					<input
						class="aio-login-2fa-methods__input"
						type="email"
						v-model="form_data.email_settings.admin_notification_email"
						:disabled="!network_can_edit"
					>
					<div class="aio-login-2fa-methods__help">{{ $t('Admin will receive OTP code copy notifications on this email.') }}</div>
				</div>
				<div class="aio-login-2fa-methods__field">
					<label class="aio-login-2fa-methods__label">{{ $t('Email Subject') }}</label>
					<input
						class="aio-login-2fa-methods__input"
						type="text"
						v-model="form_data.email_settings.email_subject"
						:disabled="!network_can_edit"
					>
				</div>
				<div class="aio-login-2fa-methods__field">
					<label class="aio-login-2fa-methods__label">{{ $t('Email Template') }}</label>
					<textarea
						class="aio-login-2fa-methods__textarea"
						rows="8"
						v-model="form_data.email_settings.email_template"
						:disabled="!network_can_edit"
					></textarea>
					<div class="aio-login-2fa-methods__help">Supported placeholders: {code} {site_name} {site_url} {user_name} {expiry}</div>
				</div>
				<div class="aio-login-2fa-methods__grid aio-login-2fa-methods__grid--four">
					<div>
						<label class="aio-login-2fa-methods__label">{{ $t('OTP Expiry') }}</label>
						<select class="aio-login-2fa-methods__input" v-model.number="form_data.email_settings.otp_expiry" :disabled="!network_can_edit">
							<option :value="5">5 minutes</option>
							<option :value="10">10 minutes</option>
							<option :value="15">15 minutes</option>
							<option :value="30">30 minutes</option>
						</select>
					</div>
					<div>
						<label class="aio-login-2fa-methods__label">{{ $t('Resend Cooldown (sec)') }}</label>
						<input
							class="aio-login-2fa-methods__input"
							type="number"
							min="0"
							v-model.number="form_data.email_settings.resend_cooldown"
							:disabled="!network_can_edit"
						>
					</div>
					<div>
						<label class="aio-login-2fa-methods__label">{{ $t('Max OTP Attempts') }}</label>
						<input
							class="aio-login-2fa-methods__input"
							type="number"
							min="1"
							v-model.number="form_data.email_settings.max_otp_attempts"
							:disabled="!network_can_edit"
						>
					</div>
					<div>
						<label class="aio-login-2fa-methods__label">{{ $t('Block Duration (minutes)') }}</label>
						<input
							class="aio-login-2fa-methods__input"
							type="number"
							min="1"
							v-model.number="form_data.email_settings.block_duration_minutes"
							:disabled="!network_can_edit"
						>
					</div>
				</div>

				<div
					v-if="email_otp && show_im_ready"
					class="aio-login-2fa-methods__actions"
				>
					<button
						type="button"
						class="button aio-login-2fa-methods__ready-btn"
						:disabled="email_ready_btn_loading || !network_can_edit"
						@click="onImReadyClick"
					>
						{{ email_ready_btn_loading ? 'PROCESSING...' : "I'M READY" }}
					</button>
				</div>
			</div>
		</div>

		<div class="aio-login-2fa-methods__actions">
			<aio-login-submit-button :disabled="!network_can_edit" v-on:button-click="handleSave" />
		</div>

		<aio-login-popup
			v-if="verify_modal"
			v-on:close-popup="closeVerifyModal"
			width="420px"
			height="auto"
			container-class="aio-login-2fa-verify-popup"
		>
			<template v-slot:popup-title>
				{{ $t('Verify Email OTP') }}
			</template>
			<template v-slot:popup-content>
				<div class="aio-login-2fa-methods__verify">
					<div class="aio-login-2fa-methods__verify-sub">
						{{ $t('Enter the 6-digit code sent to your email.') }}
					</div>
					<div class="aio-login-2fa-methods__otp-boxes">
						<input
							v-for="(d, i) in otp_digits"
							:key="i"
							:ref="'otp_' + i"
							class="aio-login-2fa-methods__otp-box"
							type="text"
							inputmode="numeric"
							pattern="[0-9]*"
							maxlength="1"
							autocomplete="one-time-code"
							:value="otp_digits[i]"
							@input="onOtpInput(i, $event)"
							@keydown.backspace="onOtpBackspace(i, $event)"
						/>
					</div>
					<button
						type="button"
						class="button aio-login-2fa-methods__ready-btn aio-login-2fa-methods__verify-submit"
						:disabled="verify_loading || !network_can_edit"
						@click="verifyEmailOtp"
					>
						{{ verify_loading ? 'Verifying...' : $t('Verify') }}
					</button>
				</div>
			</template>
			<template v-slot:popup-footer>
				<div></div>
			</template>
		</aio-login-popup>

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

const defaultEmailSettings = () => {
	const app = ( typeof window !== 'undefined' && window.aio_login__app_object ) ? window.aio_login__app_object : {};
	const blogName = typeof app.blog_name === 'string' ? app.blog_name : '';
	const adminEmail = typeof app.admin_email === 'string' ? app.admin_email : '';

	return {
		sender_name: blogName,
		sender_email: adminEmail,
		admin_notification_email: adminEmail,
		email_subject: 'Your One-Time Verification Code',
		email_template: "Hello {user_name},\n\nYour verification code is: {code}\n\nThis code will expire in {expiry} minutes.\n\nIf you did not request this code, please ignore this email.\n\nThanks,\n{site_name}\n{site_url}",
		otp_expiry: 5,
		resend_cooldown: 30,
		max_otp_attempts: 5,
		block_duration_minutes: 15,
	};
};

function mergeEmailSettings( saved ) {
	const defaults = defaultEmailSettings();
	const src = saved && typeof saved === 'object' ? saved : {};
	const out = { ...defaults };
	Object.keys( defaults ).forEach( ( key ) => {
		const val = src[ key ];
		if ( val === undefined || val === null || ( typeof val === 'string' && val.trim() === '' ) ) {
			return;
		}
		out[ key ] = val;
	} );
	return out;
}

export default {
	name: 'aio-login-two-factor-authentication',

	slug: 'authentication-methods',

	props: {
		hasPro: {
			type: Boolean,
			default: false,
		},
	},

	data: () => ( {
		tooltipContent,
		network_can_edit: ( () => {
			const o = ( typeof window !== 'undefined' && window.aio_login__app_object ) ? window.aio_login__app_object : {};
			const ctx = ( o.feature_contexts && o.feature_contexts.two_factor_authentication ) || null;
			return ctx ? !! ctx.can_edit : true;
		} )(),
		page_loaded: false,
		email_otp: false,
		email_expanded: false,
		nonce: '',
		show_im_ready: false,
		email_ready_btn_loading: false,
		verify_modal: false,
		otp_digits: [ '', '', '', '', '', '' ],
		verify_loading: false,
		form_data: {
			email_otp: false,
			enabled: false,
			remember_device: false,
			master_2fa_enabled: true,
			email_settings: defaultEmailSettings(),
		},
		snackbar: {
			message: '',
			show: false,
			duration: 3000,
		},
	} ),

	methods: {
		showSnackbar( message ) {
			this.snackbar.message = message || this.$t( 'Settings saved successfully' );
			this.snackbar.show = true;
		},

		toggleEmailExpanded() {
			if ( ! this.email_otp ) {
				return;
			}
			this.email_expanded = ! this.email_expanded;
		},

		toggleEmailOtp( value ) {
			if ( ! this.network_can_edit ) {
				return;
			}
			this.email_otp = !! value;
			this.form_data.email_otp = this.email_otp;
			if ( this.email_otp ) {
				this.form_data.enabled = false;
				this.email_expanded = true;
			} else {
				this.email_expanded = false;
				this.show_im_ready = false;
			}
		},

		loadComponent() {
			this.$nextTick( () => {
				this.page_loaded = true;
			} );

			axios.get( 'aio-login-pro/tfa/get-settings' )
				.then( ( response ) => {
					const data = response.data || {};
					this.email_otp = !! data.email_otp;
					this.form_data.email_otp = this.email_otp;
					this.form_data.enabled = false;
					this.form_data.remember_device = false;
					this.email_expanded = this.email_otp;
					this.form_data.email_settings = mergeEmailSettings( data.email_settings );
					this.nonce = data.enable_nonce || data.verify_nonce || '';
					if ( data.network && typeof data.network.can_edit !== 'undefined' ) {
						this.network_can_edit = !! data.network.can_edit;
					}
					this.show_im_ready = !! this.email_otp;
					this.page_loaded = true;
				} )
				.catch( () => {
					this.form_data.email_settings = mergeEmailSettings( null );
					this.page_loaded = true;
				} );
		},

		handleSave() {
			if ( ! this.network_can_edit ) {
				this.showSnackbar( this.$t( 'These settings are managed by the Network Administrator.' ) );
				return;
			}
			this.form_data.email_otp = this.email_otp;
			this.form_data.enabled = false;
			this.form_data.remember_device = false;
			this.form_data.master_2fa_enabled = true;
			if ( this.nonce ) {
				this.form_data._wpnonce = this.nonce;
			}
			axios.post( 'aio-login-pro/tfa/save-settings', this.form_data )
				.then( ( response ) => {
					const data = response.data || {};
					const message = data.message || this.$t( 'Settings saved successfully' );
					this.showSnackbar( message );
					// After saving Email OTP settings, always offer I'm Ready to re-verify.
					if ( this.email_otp ) {
						this.show_im_ready = true;
						this.email_expanded = true;
					} else {
						this.show_im_ready = false;
					}
				} )
				.catch( ( error ) => {
					const message = ( error.response && error.response.data && error.response.data.message )
						? error.response.data.message
						: this.$t( 'Could not save settings. Please try again.' );
					this.showSnackbar( message );
				} );
		},

		onImReadyClick() {
			if ( ! this.network_can_edit || this.email_ready_btn_loading ) {
				return;
			}
			this.email_ready_btn_loading = true;
			axios.post( 'aio-login-pro/tfa/send-profile-email-setup-otp', {
				_wpnonce: this.nonce,
			} )
				.then( ( response ) => {
					const message = ( response.data && response.data.message )
						? response.data.message
						: this.$t( 'Check your email for the verification code.' );
					this.showSnackbar( message );
					this.otp_digits = [ '', '', '', '', '', '' ];
					this.verify_modal = true;
					this.$nextTick( () => {
						const ref = this.$refs.otp_0;
						const el = Array.isArray( ref ) ? ref[ 0 ] : ref;
						if ( el && el.focus ) {
							el.focus();
						}
					} );
				} )
				.catch( ( error ) => {
					const message = ( error.response && error.response.data && error.response.data.message )
						? error.response.data.message
						: this.$t( 'Could not send email. Check SMTP and try again.' );
					this.showSnackbar( message );
				} )
				.finally( () => {
					this.email_ready_btn_loading = false;
				} );
		},

		closeVerifyModal() {
			this.verify_modal = false;
			this.otp_digits = [ '', '', '', '', '', '' ];
		},

		focusOtpBox( index ) {
			const ref = this.$refs[ 'otp_' + index ];
			const el = Array.isArray( ref ) ? ref[ 0 ] : ref;
			if ( el && el.focus ) {
				el.focus();
			}
		},

		onOtpInput( i, ev ) {
			const raw = ev && ev.target ? String( ev.target.value || '' ) : '';
			const val = raw.replace( /\D/g, '' ).slice( 0, 1 );
			const next = [ ...this.otp_digits ];
			next[ i ] = val;
			this.otp_digits = next;
			if ( val && i < 5 ) {
				this.focusOtpBox( i + 1 );
			}
		},

		onOtpBackspace( i, ev ) {
			if ( ! ev ) {
				return;
			}
			const key = ev.key || '';
			if ( key !== 'Backspace' ) {
				return;
			}
			if ( this.otp_digits[ i ] ) {
				const next = [ ...this.otp_digits ];
				next[ i ] = '';
				this.otp_digits = next;
				return;
			}
			if ( i > 0 ) {
				this.focusOtpBox( i - 1 );
				const next = [ ...this.otp_digits ];
				next[ i - 1 ] = '';
				this.otp_digits = next;
			}
		},

		verifyEmailOtp() {
			if ( ! this.network_can_edit ) {
				return;
			}
			const otp = ( this.otp_digits || [] ).join( '' ).replace( /\D/g, '' );
			if ( otp.length !== 6 ) {
				this.showSnackbar( this.$t( 'Enter the full 6-digit code.' ) );
				return;
			}
			this.verify_loading = true;
			axios.post( 'aio-login-pro/tfa/verify-profile-email-setup-otp', {
				_wpnonce: this.nonce,
				otp,
			} )
				.then( ( response ) => {
					const message = ( response.data && response.data.message )
						? response.data.message
						: this.$t( 'Email verified successfully' );
					this.showSnackbar( message );
					this.show_im_ready = !! this.email_otp;
					this.closeVerifyModal();
				} )
				.catch( ( error ) => {
					const message = ( error.response && error.response.data && error.response.data.message )
						? error.response.data.message
						: this.$t( 'Invalid or expired code. Try again or request a new email.' );
					this.showSnackbar( message );
					this.otp_digits = [ '', '', '', '', '', '' ];
					this.$nextTick( () => {
						this.focusOtpBox( 0 );
					} );
				} )
				.finally( () => {
					this.verify_loading = false;
				} );
		},
	},

	mounted() {
		this.loadComponent();
		this._networkNoticeHandler = ( e ) => {
			if ( e.detail && e.detail.feature === 'two_factor_authentication' ) {
				this.network_can_edit = !! e.detail.can_edit;
			}
		};
		if ( typeof window !== 'undefined' ) {
			window.addEventListener( 'aio-login:feature-context-changed', this._networkNoticeHandler );
		}
	},

	beforeUnmount() {
		if ( typeof window !== 'undefined' && this._networkNoticeHandler ) {
			window.removeEventListener( 'aio-login:feature-context-changed', this._networkNoticeHandler );
		}
	},
};
</script>

<style scoped>
.aio-login-2fa-methods__expand-btn[disabled] {
	opacity: 0.6;
	cursor: default;
	pointer-events: none;
}

.aio-login-2fa-methods__email-settings {
	margin: 0 0 16px;
	padding: 16px 20px 20px;
	background: #fafafa;
	border: 1px solid #ececec;
	border-radius: 10px;
}

.aio-login-2fa-methods__ready-btn {
	margin-top: 8px;
}
</style>
