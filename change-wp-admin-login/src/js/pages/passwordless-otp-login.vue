<template>
	<div v-if="page_loaded" class="aio-login-2fa-methods aio-login-otp-login">
		<div class="aio-login-2fa-methods__list">
			<!-- Email OTP Login (Free) -->
			<div class="aio-login-2fa-methods__item aio-login-2fa-policies__card" :class="{ 'is-network-locked': !email_can_edit }">
				<div class="aio-login-2fa-policies__card-header">
					<span class="aio-login-2fa-policies__icon" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M4 6.5h16c.83 0 1.5.67 1.5 1.5v8c0 .83-.67 1.5-1.5 1.5H4A1.5 1.5 0 012.5 16V8c0-.83.67-1.5 1.5-1.5z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
							<path d="M3 7.5l8.65 5.77a1.5 1.5 0 001.7 0L22 7.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
					<div class="aio-login-2fa-methods__content">
						<h2 class="aio-login-2fa-methods__title">
							{{ $t('Email OTP Login') }}
							<aio-login-tooltip :content="tooltipContent.passwordlessEmailOtp.content" />
							<aio-login-network-notice
								feature="email_otp"
								v-on:can-edit="onEmailNetworkCanEdit"
							/>
						</h2>
						<div v-if="isCardExpanded('email')" class="aio-login-2fa-methods__desc">
							{{ $t('Allow users to sign in with a one-time verification code sent to their email address.') }}
						</div>
					</div>
					<div class="aio-login-2fa-methods__controls">
						<button
							type="button"
							class="aio-login-2fa-methods__expand-btn"
							@click="toggleCard('email')"
							:aria-expanded="isCardExpanded('email')"
							aria-label="Toggle Email OTP Login settings"
						>
							<span class="aio-login-2fa-methods__chevron" :class="{ 'is-expanded': isCardExpanded('email') }"></span>
						</button>
						<aio-login-toggle
							id="email_otp_enable"
							name="email_otp_enable"
							:enabled="form.email_enable"
							:disabled="!email_can_edit"
							v-on:toggle-input="onEmailEnableToggle"
						/>
					</div>
				</div>

				<div v-if="form.email_enable && isCardExpanded('email')" class="aio-login-2fa-policies__card-body">
					<div class="aio-login-2fa-methods__grid aio-login-2fa-methods__grid--four">
						<div>
							<label class="aio-login-2fa-methods__label" for="email_length">
								{{ $t('OTP Length') }}
								<aio-login-tooltip :content="tooltipContent.passwordlessOtpLength.content" />
							</label>
							<select id="email_length" v-model="form.email_length" class="aio-login-2fa-methods__input" :disabled="emailFieldsDisabled">
								<option value="6">{{ $t("6 digits") }}</option>
								<option value="8">{{ $t("8 digits") }}</option>
							</select>
						</div>
						<div>
							<label class="aio-login-2fa-methods__label" for="email_expiration">
								{{ $t('OTP Expiration (minutes)') }}
								<aio-login-tooltip :content="tooltipContent.passwordlessOtpExpiration.content" />
							</label>
							<input id="email_expiration" type="number" min="1" max="60" v-model="form.email_expiration" :disabled="emailFieldsDisabled" class="aio-login-2fa-methods__input" />
						</div>
						<div>
							<label class="aio-login-2fa-methods__label" for="email_resend">
								{{ $t('Resend timer (seconds)') }}
								<aio-login-tooltip :content="tooltipContent.passwordlessOtpResend.content" />
							</label>
							<input id="email_resend" type="number" min="30" max="600" v-model="form.email_resend_timer" :disabled="emailFieldsDisabled" class="aio-login-2fa-methods__input" />
						</div>
						<div>
							<label class="aio-login-2fa-methods__label" for="email_retries">
								{{ $t('Maximum retry attempts') }}
								<aio-login-tooltip :content="tooltipContent.passwordlessOtpRetries.content" />
							</label>
							<input id="email_retries" type="number" min="1" max="20" v-model="form.email_max_retries" :disabled="emailFieldsDisabled" class="aio-login-2fa-methods__input" />
						</div>
					</div>

					<div class="aio-login-2fa-methods__field aio-login-otp-login__field--half">
						<label class="aio-login-2fa-methods__label" for="email_block_duration">
							{{ $t('Block duration (minutes)') }}
							<aio-login-tooltip :content="tooltipContent.passwordlessOtpBlockDuration.content" />
						</label>
						<input id="email_block_duration" type="number" min="1" max="1440" v-model="form.email_block_duration" :disabled="emailFieldsDisabled" class="aio-login-2fa-methods__input" />
						<p class="aio-login-2fa-methods__help">{{ $t('How long the IP stays blocked after too many failed email OTP attempts. Shown under Activity Log → Lockouts.') }}</p>
					</div>
					<div class="aio-login-otp-login__toggle-field">
						<label class="aio-login-2fa-methods__label">
							{{ $t('Skip 2FA for Email login') }}
							<aio-login-tooltip :content="tooltipContent.passwordlessOtpSkip2fa.content" />
						</label>
						<p class="aio-login-2fa-methods__help">{{ $t('When enabled, users who sign in with email OTP are not prompted for AIO Login two-factor authentication.') }}</p>
						<aio-login-toggle
							id="email_skip_2fa"
							name="email_skip_2fa"
							:enabled="form.email_skip_2fa"
							:disabled="emailFieldsDisabled"
							v-on:toggle-input="form.email_skip_2fa = $event"
						/>
					</div>
				</div>
			</div>
		</div>

		<div class="aio-login-2fa-methods__actions">
			<button type="button" class="button button-primary aio-login-otp-login__save-btn" @click="saveSettings" :disabled="saving || !email_can_edit">
				{{ saving ? 'Saving…' : 'Save Changes' }}
			</button>
		</div>

		<aio-login-snackbar
			:message="snackbar.message"
			:duration="snackbar.duration"
			v-if="snackbar.show"
			v-on:close="snackbar.show = false"
		/>
	</div>
</template>

<script>
import tooltipContent from '../tooltip-content.js';
export default {
	name: 'passwordless-otp-login',

	data: () => ({
		tooltipContent,
		page_loaded: false,
		saving: false,
		nonce: '',
		email_can_edit: true,
		expandedCards: {
			email: true,
		},
		form: {
			email_enable: false,
			email_block_duration: '15',
			email_length: '6',
			email_expiration: '10',
			email_resend_timer: '60',
			email_max_retries: '5',
			email_skip_2fa: true,
		},
		snackbar: { message: '', duration: 3000, show: false },
	}),

	computed: {
		emailFieldsDisabled() {
			return ! this.form.email_enable || ! this.email_can_edit;
		},
	},

	mounted() {
		this.loadSettings();
	},

	methods: {
		isCardExpanded(key) {
			return !!this.expandedCards[key];
		},
		toggleCard(key) {
			this.expandedCards[key] = !this.expandedCards[key];
		},
		onEmailNetworkCanEdit( canEdit ) {
			this.email_can_edit = canEdit !== false;
		},
		onEmailEnableToggle( enabled ) {
			if ( ! this.email_can_edit ) {
				return;
			}
			this.form.email_enable = enabled;
		},
		loadSettings() {
			axios.get('aio-login/passwordless-otp/get-settings').then((res) => {
				const d = res.data;
				this.nonce = d.nonce;
				this.form.email_enable = !!d.email_enable;
				this.form.email_length = [ '6', '8' ].includes( String( d.email_length ) ) ? String( d.email_length ) : '6';
				this.form.email_expiration = String(d.email_expiration || '10');
				this.form.email_resend_timer = String(d.email_resend_timer || '60');
				this.form.email_max_retries = String(d.email_max_retries || '5');
				this.form.email_block_duration = String(d.email_block_duration || '15');
				this.form.email_skip_2fa = !!d.email_skip_2fa;
				if ( d.email_network && typeof d.email_network.can_edit !== 'undefined' ) {
					this.email_can_edit = !!d.email_network.can_edit;
				}
				this.page_loaded = true;
			});
		},
		saveSettings() {
			if ( ! this.email_can_edit ) {
				this.snackbar.message = 'These settings are managed by the Network Administrator.';
				this.snackbar.show = true;
				return;
			}
			this.saving = true;
			const payload = {
				_wpnonce: this.nonce,
				...this.form,
			};
			axios.post('aio-login/passwordless-otp/save-settings', payload).then((res) => {
				this.saving = false;
				if (res.data && res.data.success) {
					this.snackbar.message = res.data.message || 'Saved';
					this.snackbar.show = true;
					if (res.data.data) {
						this.loadSettings();
					}
				}
			}).catch((err) => {
				this.saving = false;
				this.snackbar.message = err?.response?.data?.message || 'Save failed';
				this.snackbar.show = true;
			});
		},
	},
};
</script>
