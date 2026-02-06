<template>
	<div v-if="show" class="popup-overlay" @click="closePopup">
		<div class="popup-content" @click.stop>
			<div class="popup-header">
				<button class="close-btn" @click="closePopup">&times;</button>
			</div>

			<!-- Step Indicators -->
			<div class="popup-steps">
				<div class="step-container">
					<h4>Getting Started</h4>
					<div 
						class="step" 
						:class="{ active: currentStep >= 1 }" 
						:style="{ backgroundColor: currentStep >= 1 ? '#9516DF' : '#EBE8EB', color: currentStep < 1 ? '#9516DF' : 'white' }">
						1
					</div>
				</div>
				<div class="connector" :style="{ backgroundColor: currentStep >= 2 ? '#9516DF' : '#C9D2E3' }"></div>
				
				<div class="step-container">
					<h4>Configuration</h4>
					<div 
						class="step" 
						:class="{ active: currentStep >= 2 }" 
						:style="{ backgroundColor: currentStep >= 2 ? '#9516DF' : '#EBE8EB', color: currentStep < 2 ? '#9516DF' : 'white' }">
						2
					</div>
				</div>
				<div class="connector" :style="{ backgroundColor: currentStep >= 3 ? '#9516DF' : '#C9D2E3' }"></div>

				<div class="step-container">
					<h4>Settings</h4>
					<div 
						class="step" 
						:class="{ active: currentStep >= 3 }" 
						:style="{ backgroundColor: currentStep >= 3 ? '#9516DF' : '#EBE8EB', color: currentStep < 3 ? '#9516DF' : 'white' }">
						3
					</div>
				</div>
			</div>

			<!-- Step 1: Getting Started -->
			<div v-if="currentStep === 1" class="aio-login-pro__first-step">
				<h4 class="aio-login-pro__first-step__title">Getting Started</h4>
				<p class="aio-login-pro__first-step__content">To protect your website from spam and bots, start by adding hCaptcha to your WordPress login page.</p>
				<h5 class="aio-login-pro__first-step__instruction">Instruction:</h5>
				<p class="aio-login-pro__first-step__instructions">1. Register your site and get the keys → <a href="https://dashboard.hcaptcha.com/signup" target="_blank" class="aio-login-pro__first-step__doc-link">Click Here</a></p>
				<p class="aio-login-pro__first-step__instructions">2. Need help creating keys? → <a href="https://aiologin.com/docs/wordpress-login-security/setting-up-hcaptcha/" target="_blank" class="aio-login-pro__first-step__doc-link">Click Here</a></p>
				<p class="aio-login-pro__first-step__next-step-note">3. Once you have your keys, click Next to enter them.</p>
			</div>

			<!-- Step 2: Configuration -->
			<div v-if="currentStep === 2" class="aio-login-pro__step2">
				<p class="aio-login-pro__step2__description">
					Enter your hCaptcha details.
				</p>
				<div class="aio-login-pro__inline-form">
					<div class="aio-login-pro__form-group">
						<label for="hcaptcha-site-key" class="aio-login-pro__form-label">
							Site Key <span class="aio-login-pro__required">*</span>
						</label>
						<div class="input-with-delete">
							<input
								id="hcaptcha-site-key"
								type="text"
								v-model="formData.siteKey"
								class="aio-login-pro__form-input"
								:class="{ 'aio-login-pro__error': showValidationError && !formData.siteKey.trim() }"
								placeholder="Enter Site Key"
								autocomplete="off"
							/>
							<button v-if="formData.siteKey" @click="clearField('siteKey')" class="clear-btn">×</button>
						</div>
						<span 
							v-if="showValidationError && !formData.siteKey.trim()" 
							class="aio-login-pro__error-message"
						>
							This field is required
						</span>
					</div>
					<div class="aio-login-pro__form-group">
						<label for="hcaptcha-secret-key" class="aio-login-pro__form-label">
							Secret Key <span class="aio-login-pro__required">*</span>
						</label>
						<div class="input-with-delete">
							<input
								id="hcaptcha-secret-key"
								type="password"
								v-model="formData.secretKey"
								class="aio-login-pro__form-input"
								:class="{ 'aio-login-pro__error': showValidationError && !formData.secretKey.trim() }"
								placeholder="Enter Secret Key"
								autocomplete="new-password"
							/>
							<button v-if="formData.secretKey" @click="clearField('secretKey')" class="clear-btn">×</button>
						</div>
						<span 
							v-if="showValidationError && !formData.secretKey.trim()" 
							class="aio-login-pro__error-message"
						>
							This field is required
						</span>
					</div>
				</div>
			</div>

			<!-- Step 3: Other Settings -->
			<div v-if="currentStep === 3" class="aio-login-pro__step3">
				<p class="aio-login-pro__step3__description">
					These settings are not compulsory.
				</p>
				<div class="aio-login-pro__step3__layout">
					<div class="aio-login-pro__step3__column">
						<div class="aio-login-pro__form-group">
							<label for="hcaptcha-language" class="aio-login-pro__form-label">Language for Captcha:</label>
							<select id="hcaptcha-language" v-model="formData.language" class="aio-login-pro__form-input">
								<option value="en">English</option>
								<option value="ar">Arabic</option>
								<option value="af">Afrikaans</option>
								<option value="am">Amharic</option>
								<option value="hy">Armenian</option>
								<option value="az">Azerbaijani</option>
								<option value="eu">Basque</option>
								<option value="bn">Bengali</option>
								<option value="bg">Bulgarian</option>
								<option value="ca">Catalan</option>
								<option value="zh-HK">Chinese (Hong Kong)</option>
								<option value="zh-CN">Chinese (Simplified)</option>
								<option value="zh-TW">Chinese (Traditional)</option>
								<option value="hr">Croatian</option>
								<option value="cs">Czech</option>
								<option value="da">Danish</option>
								<option value="nl">Dutch</option>
								<option value="et">Estonian</option>
								<option value="fil">Filipino</option>
								<option value="fi">Finnish</option>
								<option value="fr">French</option>
								<option value="gl">Galician</option>
								<option value="ka">Georgian</option>
								<option value="de">German</option>
								<option value="el">Greek</option>
								<option value="gu">Gujarati</option>
								<option value="iw">Hebrew</option>
								<option value="hi">Hindi</option>
								<option value="hu">Hungarian</option>
								<option value="is">Icelandic</option>
								<option value="id">Indonesian</option>
								<option value="it">Italian</option>
								<option value="ja">Japanese</option>
								<option value="kn">Kannada</option>
								<option value="ko">Korean</option>
								<option value="lo">Lao</option>
								<option value="lv">Latvian</option>
								<option value="lt">Lithuanian</option>
								<option value="ms">Malay</option>
								<option value="ml">Malayalam</option>
								<option value="mr">Marathi</option>
								<option value="mn">Mongolian</option>
								<option value="my">Myanmar</option>
								<option value="ne">Nepali</option>
								<option value="no">Norwegian</option>
								<option value="fa">Persian</option>
								<option value="pl">Polish</option>
								<option value="pt">Portuguese</option>
								<option value="ro">Romanian</option>
								<option value="ru">Russian</option>
								<option value="sr">Serbian</option>
								<option value="si">Sinhalese</option>
								<option value="sk">Slovak</option>
								<option value="sl">Slovenian</option>
								<option value="es">Spanish</option>
								<option value="sw">Swahili</option>
								<option value="sv">Swedish</option>
								<option value="ta">Tamil</option>
								<option value="te">Telugu</option>
								<option value="th">Thai</option>
								<option value="tr">Turkish</option>
								<option value="uk">Ukrainian</option>
								<option value="ur">Urdu</option>
								<option value="vi">Vietnamese</option>
								<option value="zu">Zulu</option>
							</select>
						</div>
						<div class="aio-login-pro__form-group">
							<label for="hcaptcha-theme" class="aio-login-pro__form-label">Theme:</label>
							<select id="hcaptcha-theme" v-model="formData.theme" class="aio-login-pro__form-input">
								<option value="light">Light</option>
								<option value="dark">Dark</option>
							</select>
						</div>
						<div class="aio-login-pro__form-group">
							<label for="hcaptcha-size" class="aio-login-pro__form-label">Size:</label>
							<select id="hcaptcha-size" v-model="formData.size" class="aio-login-pro__form-input">
								<option value="normal">Normal</option>
								<option value="compact">Compact</option>
							</select>
						</div>
					</div>
				</div>
			</div>

			<!-- Navigation buttons -->
			<div class="popup-footer">
				<button v-if="currentStep === 2" @click="prevStep" class="back-btn">Back</button>
				<button v-if="currentStep === 3" @click="prevStep" class="back-btn">Back</button>
				<div class="popup-footer-left">
					<button v-if="currentStep === 1" @click="nextStep" class="next-btn">Next</button>
				</div>
				<button v-if="currentStep === 2" @click="nextStep" class="next-btn">Next</button>
				<button v-if="currentStep === 3" @click="finish" class="finish-btn">Finished</button>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'aio-login-hcaptcha-popup',

	props: {
		show: {
			type: Boolean,
			default: false
		},
		initialData: {
			type: Object,
			default: () => ({})
		}
	},

	data() {
		return {
			currentStep: 1,
			showValidationError: false,
			formData: {
				siteKey: '',
				secretKey: '',
				theme: 'light',
				size: 'normal',
				language: 'en'
			}
		}
	},

	computed: {
		canProceed() {
			if (this.currentStep === 1) return true;
			if (this.currentStep === 2) {
				return this.formData.siteKey.trim() !== '' && this.formData.secretKey.trim() !== '';
			}
			if (this.currentStep === 3) return true;
			return false;
		}
	},

	watch: {
		show(newVal) {
			if (newVal) {
				this.currentStep = 1;
				this.formData = { ...this.initialData };
				if (!this.formData.theme) this.formData.theme = 'light';
				if (!this.formData.size) this.formData.size = 'normal';
				if (!this.formData.language) this.formData.language = 'en';
				this.showValidationError = false;
				// Prevent body scroll when modal is open
				document.body.style.overflow = 'hidden';
				document.body.classList.add('aio-login-modal-open');
			} else {
				// Restore body scroll when modal is closed
				document.body.style.overflow = '';
				document.body.classList.remove('aio-login-modal-open');
			}
		}
	},

	methods: {
		closePopup() {
			this.$emit('close');
		},

		nextStep() {
			if (this.currentStep === 2) {
				this.showValidationError = true;
				if (!this.canProceed) {
					return;
				}
			}
			if (this.canProceed && this.currentStep < 3) {
				this.currentStep++;
			}
		},

		prevStep() {
			if (this.currentStep > 1) {
				this.currentStep--;
			}
		},

		clearField(field) {
			this.formData[field] = '';
		},

		finish() {
			this.$emit('save', this.formData);
		}
	}
}
</script>

<style scoped>
/* Exact copy of pro plugin social login popup styling */
.popup-overlay {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.5);
	display: flex;
	justify-content: center;
	align-items: center;
	z-index: 1000;
}

.popup-content {
	background-color: white;
	padding: 10px 50px 50px 50px;
	border-radius: 8px;
	width: 80%;
	max-width: 800px;
}

.popup-header {
	text-align: right;
	padding-bottom: 60px;
}

.popup-steps {
	display: flex;
	justify-content: space-between;
	margin-bottom: 20px;
	width: 100%;
}

.step {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	display: flex;
	justify-content: center;
	align-items: center;
	color: white;
	font-weight: bold;
	font-size: 16px;
	transition: background-color 0.3s ease;
	background-color: #EBE8EB;
}

.step.active {
	background-color: #9516DF;
}

.popup-footer {
	display: flex;
	justify-content: space-between;
	width: 100%;
	margin-top: 40px;
}

.popup-footer-left {
	flex-grow: 1;
	text-align: right;
}

.next-btn, .finish-btn, .back-btn {
	padding: 10px 20px;
	border: none;
	border-radius: 5px;
	font-size: 16px;
	cursor: pointer;
}

.next-btn {
	background: linear-gradient(262.54deg, #F7ECFD -5.51%, #8076FF 252.88%);
	color: #6E16DF;
}

.finish-btn {
	background: linear-gradient(262.54deg, #F7ECFD -5.51%, #8076FF 252.88%);
	color: #6E16DF;
}

.back-btn {
	background: linear-gradient(262.54deg, #EBE8EB -5.51%, #C9D2E3 252.88%);
	color: #8498B4;
}

.close-btn {
	position: relative;
	font-size: 21px;
	border-radius: 50%;
	cursor: pointer;
	background: none;
	border: 1px solid #7E869E40;
}

.connector {
	height: 2px;
	width: 100%;
	background-color: #C9D2E3;
	margin-top: 20px;
}

.step-container {
	display: flex;
	flex-direction: column;
	align-items: center;
	width: 100px;
	position: relative;
}

.step-container h4 {
	position: absolute;
	top: -60px;
	font-size: 13px;
	font-weight: bold;
	color: #9516DF;
}

/* First Step Styling */
.aio-login-pro__first-step .aio-login-pro__first-step__title{
	color: #404280;
	font-size: 24px;
	font-style: normal;
	font-weight: 600;
	line-height: normal;
}

.aio-login-pro__first-step .aio-login-pro__first-step__instruction{
	color: #404280;
	font-size: 20px;
	font-style: normal;
	font-weight: 600;
	line-height: normal;
}

.aio-login-pro__first-step .aio-login-pro__first-step__content{
	color: #606C80;
	font-size: 14px;
	font-style: normal;
	font-weight: 400;
}

.aio-login-pro__first-step .aio-login-pro__first-step__doc-link{
	color: #6E16DF;
	font-family: Figtree;
	font-size: 12px;
	font-style: normal;
	font-weight: 700;
	line-height: 12px;
	text-decoration-line: underline;
	text-decoration-style: solid;
	text-decoration-skip-ink: none;
	text-decoration-thickness: auto;
	text-underline-offset: auto;
	text-underline-position: from-font;
}

.aio-login-pro__first-step p{
	color: #606C80;
	font-family: Figtree;
	font-size: 12px;
	font-style: normal;
	font-weight: 400;
}

/* Step 2 Styling */
.aio-login-pro__step2__description {
	color: #606C80;
	font-size: 14px;
	margin-bottom: 20px;
}

.aio-login-pro__inline-form {
	display: flex;
	gap: 20px;
	margin-top: 10px;
}

.aio-login-pro__form-group {
	display: flex;
	flex-direction: column;
	flex: 1;
}

.aio-login-pro__form-label {
	font-size: 14px;
	margin-bottom: 5px;
	font-weight: 600;
}

.aio-login-pro__form-input {
	padding: 10px 35px 10px 10px;
	font-size: 14px;
	border-radius: 4px;
	transition: border-color 0.2s ease-in-out;
	border: 1px solid #EBE8EB !important;
	background: #FFF;
	height: 40px;
	width: 100%;
	box-sizing: border-box;
}

.aio-login-pro__form-input[type="text"] {
	padding: 10px 35px 10px 10px;
}

.aio-login-pro__form-input[type="password"] {
	padding: 10px 35px 10px 10px;
}

select.aio-login-pro__form-input {
	padding: 12px 30px 12px 10px;
	background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
	background-repeat: no-repeat;
	background-position: right 10px center;
	background-size: 16px;
	appearance: none;
	-moz-appearance: none;
	-webkit-appearance: none;
	text-align: left;
	text-align-last: left;
	line-height: 1.4;
	height: 44px;
	box-sizing: border-box;
}

select.aio-login-pro__form-input option {
	text-align: left;
	padding: 5px;
}

.aio-login-pro__form-input:focus {
	outline: none;
	border-color: #9516df;
}

.aio-login-pro__required {
	color: #ff0000;
}

.aio-login-pro__error {
	border-color: #ff0000 !important;
}

.aio-login-pro__error-message {
	color: #ff0000;
	font-size: 12px;
	margin-top: 5px;
}

/* Step 3 Styling */
.aio-login-pro__step3__description {
	color: #606C80;
	font-size: 14px;
	margin-bottom: 20px;
}

.aio-login-pro__step3__layout {
	display: flex;
	gap: 20px;
}

.aio-login-pro__step3__column {
	flex: 1;
}

/* Input with delete button */
.input-with-delete {
	position: relative;
}

.clear-btn {
	position: absolute;
	right: 8px;
	top: 50%;
	transform: translateY(-50%);
	background: transparent;
	color: #999;
	border: none;
	width: 24px;
	height: 24px;
	cursor: pointer;
	font-size: 18px;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10;
	pointer-events: auto;
}

.clear-btn:hover {
	color: #999;
}

/* Global overrides for pro plugin compatibility */
body.aio-login-modal-open {
	overflow: hidden !important;
}

/* Ensure modal is always on top */
.popup-overlay * {
	box-sizing: border-box !important;
}
</style>

