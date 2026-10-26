<template>
	<div>
		<div class="aio-login-t-wrapper">
			<div>
				<div class="aio-login-captcha-header">
					<h3 class="aio-login-captcha-title">
						<span>{{ $t('Captcha Settings') }}</span>
						<aio-login-tooltip
							:content="tooltipContent.captcha.content"
							placement="bottom"
						/>
					</h3>
				</div>
			</div>
			<div class="aio-login-pro__social-login">
				<!-- Google reCAPTCHA (Free) -->
				<aio-login-captcha
					:has-pro="true"
					:enabled="form_data.enabled"
					:config-data="configData"
					:api-nonce="nonce"
					:can-edit="recaptcha_can_edit"
					@can-edit="onCaptchaCanEdit"
					@toggle-captcha="handleToggleCaptcha"
					@save-settings="handleSaveSettings"
				/>
			</div>
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
import tooltipContent from '../tooltip-content.js';

export default {
	name: 'grecaptcha',

	data: () => ( {
		tooltipContent,
		nonce: '',
		namespace: 'aio-login/grecaptcha',
		recaptcha_can_edit: true,
		form_data: {
			enabled: false,
			version: 'v2',
			v2_site_key: '',
			v2_secret_key: '',
			theme: 'light',
			v3_site_key: '',
			v3_secret_key: '',
			threshold: '0.5',
			validated: false,
		},
		snackbar: {
			message: '',
			duration: 3000,
			show: false,
		},
	} ),

	computed: {
		configData() {
			return {
				version: this.normalizeRecaptchaVersion( this.form_data.version ),
				siteKey: this.form_data.version === 'v2' ? this.form_data.v2_site_key : this.form_data.v3_site_key,
				secretKey: this.form_data.version === 'v2' ? this.form_data.v2_secret_key : this.form_data.v3_secret_key,
				theme: this.form_data.theme,
				threshold: this.form_data.threshold,
				validated: this.form_data.validated,
			};
		},
	},

	methods: {
		normalizeRecaptchaVersion( version ) {
			const value = String( version || 'v2' ).toLowerCase().trim();
			return ( 'v2' === value || 'v3' === value ) ? value : 'v2';
		},

		onCaptchaCanEdit( canEdit ) {
			this.recaptcha_can_edit = canEdit !== false;
		},

		handleToggleCaptcha(enabled) {
			if ( ! this.recaptcha_can_edit ) {
				return;
			}
			this.form_data.enabled = enabled;
			this.saveSettings();
		},

		handleSaveSettings(data) {
			if ( ! this.recaptcha_can_edit ) {
				return;
			}
			this.form_data.version = data.version;
			this.form_data.theme = data.theme;
			this.form_data.threshold = data.threshold;
			this.form_data.validated = !!data.validated;

			if (data.version === 'v2') {
				this.form_data.v2_site_key = data.siteKey;
				this.form_data.v2_secret_key = data.secretKey;
			} else {
				this.form_data.v3_site_key = data.siteKey;
				this.form_data.v3_secret_key = data.secretKey;
			}

			this.saveSettings(true);
		},

		saveSettings(fromVerifiedSave = false) {
			if ( ! this.recaptcha_can_edit ) {
				return;
			}
			const payload = { ...this.form_data, _wpnonce: this.nonce };
			if (fromVerifiedSave) {
				payload.validated = true;
			}
			axios.post(this.namespace + '/save-settings', payload)
				.then(response => {
					if (response.data && typeof response.data.validated !== 'undefined') {
						this.form_data.validated = !!response.data.validated;
					} else if (fromVerifiedSave) {
						this.form_data.validated = true;
					}
					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				})
				.catch(error => {
					const message = (error.response && error.response.data && error.response.data.message)
						? error.response.data.message
						: 'Error saving settings.';
					this.snackbar.message = message;
					this.snackbar.show = true;
				});
		},

		handleCloseSnackbar() {
			this.snackbar.show = false;
		},
	},

	beforeUnmount() {
		if ( this._onCaptchaFeatureContext ) {
			window.removeEventListener( 'aio-login:feature-context-changed', this._onCaptchaFeatureContext );
		}
	},

	mounted() {
		this._onCaptchaFeatureContext = ( event ) => {
			const detail = event && event.detail;
			if ( ! detail || detail.feature !== 'captcha' ) {
				return;
			}
			this.onCaptchaCanEdit( !! detail.can_edit );
		};
		window.addEventListener( 'aio-login:feature-context-changed', this._onCaptchaFeatureContext );

		axios.get(this.namespace + '/get-settings')
			.then(response => {
				this.form_data.enabled = response.data.enabled;
				this.form_data.version = this.normalizeRecaptchaVersion( response.data.version );
				this.form_data.v2_site_key = response.data.v2_site_key;
				this.form_data.v2_secret_key = response.data.v2_secret_key;
				this.form_data.theme = response.data.theme;
				this.form_data.v3_site_key = response.data.v3_site_key;
				this.form_data.v3_secret_key = response.data.v3_secret_key;
				this.form_data.threshold = response.data.threshold;
				this.form_data.validated = !!response.data.validated;
				this.nonce = response.data.nonce;
				if (response.data && response.data.network && typeof response.data.network.can_edit !== 'undefined') {
					this.recaptcha_can_edit = !!response.data.network.can_edit;
				}
			})
			.catch(error => {
				console.error('Error loading settings:', error);
			});
	}
}
</script>

<style scoped>
.aio-login-captcha-header {
  margin-bottom: 12px;
}

.aio-login-captcha-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}

.aio-login-pro__social-login {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}
</style>
