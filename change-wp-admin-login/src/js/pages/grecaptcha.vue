<template>
	<div>
		<div class="aio-login-t-wrapper">
			<div>
				<h3>Captcha Settings</h3>
				<div v-if="(form_data.enabled || hcaptcha_form_data.enabled) && !noteDismissed" class="captcha-note">
					<span>Note: You can only enable one captcha at a time.</span>
					<button type="button" class="dismiss-btn" @click="dismissNote" aria-label="Dismiss">×</button>
				</div>
			</div>
			<div class="aio-login-pro__social-login">
				<!-- Google reCAPTCHA Card -->
				<aio-login-captcha
					:has-pro="has_pro"
					:enabled="form_data.enabled"
					:config-data="configData"
					@toggle-captcha="handleToggleCaptcha"
					@save-settings="handleSaveSettings"
				/>

				<!-- hCaptcha Card -->
				<aio-login-hcaptcha-card
					:has-pro="has_pro"
					:enabled="hcaptcha_form_data.enabled"
					:config-data="hcaptchaConfigData"
					@toggle-captcha="handleToggleHCaptcha"
					@save-settings="handleSaveHCaptchaSettings"
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

export default {
	name: 'grecaptcha',

	data: () => ( {
		has_pro: 'true' === aio_login__app_object.has_pro,
		nonce: '',
		namespace: 'aio-login/grecaptcha',
		form_data: {
			enabled: false,
			version: 'v2',
			v2_site_key: '',
			v2_secret_key: '',
			theme: 'light',
			v3_site_key: '',
			v3_secret_key: '',
			threshold: '0.5',
		},
		hcaptcha_namespace: 'aio-login/hcaptcha',
		hcaptcha_nonce: '',
		hcaptcha_form_data: {
			enabled: false,
			site_key: '',
			secret_key: '',
			theme: 'light',
			size: 'normal',
			language: 'en',
		},
		snackbar: {
			message: '',
			duration: 3000,
			show: false,
		},
		noteDismissed: false
	} ),

	computed: {
		configData() {
			return {
				version: this.form_data.version,
				siteKey: this.form_data.version === 'v2' ? this.form_data.v2_site_key : this.form_data.v3_site_key,
				secretKey: this.form_data.version === 'v2' ? this.form_data.v2_secret_key : this.form_data.v3_secret_key,
				theme: this.form_data.theme,
				threshold: this.form_data.threshold
			};
		},
		hcaptchaConfigData() {
			return {
				siteKey: this.hcaptcha_form_data.site_key,
				secretKey: this.hcaptcha_form_data.secret_key,
				theme: this.hcaptcha_form_data.theme,
				size: this.hcaptcha_form_data.size,
				language: this.hcaptcha_form_data.language
			};
		}
	},

	methods: {
		handleToggleCaptcha(enabled) {
			this.form_data.enabled = enabled;
			
			// If enabling reCAPTCHA, disable hCaptcha
			if (enabled) {
				this.hcaptcha_form_data.enabled = false;
				// Save hCaptcha disabled state
				this.saveHCaptchaSettings();
			}
			
			this.saveSettings();
		},

		handleSaveSettings(data) {
			// Update form data with popup data
			this.form_data.version = data.version;
			this.form_data.theme = data.theme;
			this.form_data.threshold = data.threshold;
			
			if (data.version === 'v2') {
				this.form_data.v2_site_key = data.siteKey;
				this.form_data.v2_secret_key = data.secretKey;
			} else {
				this.form_data.v3_site_key = data.siteKey;
				this.form_data.v3_secret_key = data.secretKey;
			}
			
			this.saveSettings();
		},

		saveSettings() {
			this.form_data._wpnonce = this.nonce;
			axios.post(this.namespace + '/save-settings', this.form_data)
				.then(response => {
					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				})
				.catch(error => {
					console.error('Error saving settings:', error);
				});
		},

		handleCloseSnackbar() {
			this.snackbar.show = false;
		},

		dismissNote() {
			this.noteDismissed = true;
		},

		handleToggleHCaptcha(enabled) {
			this.hcaptcha_form_data.enabled = enabled;
			
			// If enabling hCaptcha, disable reCAPTCHA
			if (enabled) {
				this.form_data.enabled = false;
				// Save reCAPTCHA disabled state
				this.saveSettings();
			}
			
			this.saveHCaptchaSettings();
		},

		handleSaveHCaptchaSettings(data) {
			this.hcaptcha_form_data.theme = data.theme;
			this.hcaptcha_form_data.size = data.size;
			this.hcaptcha_form_data.language = data.language;
			this.hcaptcha_form_data.site_key = data.siteKey;
			this.hcaptcha_form_data.secret_key = data.secretKey;
			this.saveHCaptchaSettings();
		},

		saveHCaptchaSettings() {
			this.hcaptcha_form_data._wpnonce = this.hcaptcha_nonce;
			axios.post(this.hcaptcha_namespace + '/save-settings', this.hcaptcha_form_data)
				.then(response => {
					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				})
				.catch(error => {
					console.error('Error saving hCaptcha settings:', error);
				});
		},


		loadHCaptchaSettings() {
			axios.get(this.hcaptcha_namespace + '/get-settings')
				.then(response => {
					this.hcaptcha_form_data.enabled = response.data.enabled;
					this.hcaptcha_form_data.site_key = response.data.site_key || '';
					this.hcaptcha_form_data.secret_key = response.data.secret_key || '';
					this.hcaptcha_form_data.theme = response.data.theme || 'light';
					this.hcaptcha_form_data.size = response.data.size || 'normal';
					this.hcaptcha_form_data.language = response.data.language || 'en';
					this.hcaptcha_nonce = response.data.nonce;
				})
				.catch(error => {
					console.error('Error loading hCaptcha settings:', error);
				});
		}
	},

	mounted() {
		// Load Google reCAPTCHA settings
		axios.get(this.namespace + '/get-settings')
			.then(response => {
				this.form_data.enabled = response.data.enabled;
				this.form_data.version = response.data.version;
				this.form_data.v2_site_key = response.data.v2_site_key;
				this.form_data.v2_secret_key = response.data.v2_secret_key;
				this.form_data.theme = response.data.theme;
				this.form_data.v3_site_key = response.data.v3_site_key;
				this.form_data.v3_secret_key = response.data.v3_secret_key;
				this.form_data.threshold = response.data.threshold;
				this.nonce = response.data.nonce;
			})
			.catch(error => {
				console.error('Error loading settings:', error);
			});

		// Load hCaptcha settings
		this.loadHCaptchaSettings();
	}
}
</script>

<style scoped>
/* Social Login Container */
.aio-login-pro__social-login {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}

.captcha-note {
  margin: 10px 0 20px 0;
  padding: 12px 16px;
  background-color: #FFF3CD;
  border-left: 4px solid #FFC107;
  color: #856404;
  font-size: 14px;
  border-radius: 4px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
}

.captcha-note span {
  flex: 1;
}

.dismiss-btn {
  background: none;
  border: none;
  color: #856404;
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  margin-left: 12px;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: background-color 0.2s ease;
}

.dismiss-btn:hover {
  background-color: rgba(133, 100, 4, 0.1);
}
</style>