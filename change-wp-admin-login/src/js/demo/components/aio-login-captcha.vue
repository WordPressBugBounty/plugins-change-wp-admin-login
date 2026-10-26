<template>
	<div v-if="page_loaded" class="aio-login-pro__social-login__card">
				<div class="aio-login-pro__social-login__network-badge">
					<aio-login-network-notice
						feature="captcha"
						:inline="true"
						:show-override-toggle="false"
						@can-edit="$emit('can-edit', $event)"
						@context="onCaptchaNetworkContext"
					/>
				</div>
				<!-- Configured Tag -->
				<div 
					v-if="statusBadge" 
					class="configured-tag"
					:class="{ 'enabled': statusBadge === 'green', 'disabled': statusBadge === 'orange' }"
				>
					{{ statusBadgeText }}
				</div>
				
				<!-- Top Section -->
				<div class="aio-login-pro__social-login__card__top">
					<img :src="getSrc('grecaptcha')" :alt="'Google reCAPTCHA'" />
					<p>
						<span>{{ $t('Google reCAPTCHA') }}</span>
					</p>
					<span class="version-badge">{{ $t('v2 & v3') }}</span>
				</div>
				<!-- Bottom Section -->
				<div class="aio-login-pro__social-login__card__bottom">
					<label class="toggle-switch">
						<aio-login-toggle
							id="grecaptcha"
							name="grecaptcha"
							v-on:toggle-input="handleToggle"
							:enabled="enabled"
							:disabled="!canEdit"
						/>
					</label>
					<button
						v-if="showConfigureButton"
						type="button"
						class="configure-btn"
						:disabled="configureButtonDisabled"
						@click="configureCaptcha"
						@mouseenter="onHover"
						@mouseleave="onLeave"
					>
						{{ $t('Configure') }}
					</button>
				</div>

		<!-- Multi-step Popup -->
		<aio-login-recaptcha-popup
			:show="showPopup"
			:initial-data="popupData"
			:api-nonce="apiNonce"
			:can-edit="canEdit"
			@can-edit="$emit('can-edit', $event)"
			@close="closePopup"
			@save="saveSettings"
		/>
	</div>
</template>

<script>
import tooltipContent from '../../tooltip-content.js';
import {
	shouldShowCaptchaConfigureButton,
	isCaptchaConfigureDisabledByNetwork,
	resolveCaptchaNetworkPolicy,
} from '../../captcha-card-network.js';

export default {
	name: 'aio-login-captcha',

	slug: 'grecaptcha',

	props: {
		hasPro: {
			type: Boolean,
			default: false,
		},
		enabled: {
			type: Boolean,
			default: false,
		},
		configData: {
			type: Object,
			default: () => ({})
		},
		apiNonce: {
			type: String,
			default: '',
		},
		canEdit: {
			type: Boolean,
			default: true,
		},
	},

	data: ( vm ) => ( {
		tooltipContent,
		assetsUrl: aio_login__app_object.assets_url,
		page_loaded: false,
		showPopup: false,
		popupData: {},
		isHovered: false,
		captchaNetworkPolicy: '',
	} ),

	computed: {
		showConfigureButton() {
			return shouldShowCaptchaConfigureButton( {
				enabled: this.enabled,
				requiresPro: false,
				hasPro: true,
			} );
		},
		configureButtonDisabled() {
			return isCaptchaConfigureDisabledByNetwork(
				resolveCaptchaNetworkPolicy( this.captchaNetworkPolicy )
			);
		},
		statusBadge() {
			if ( ! this.hasValidKeys() ) {
				return null;
			}
			return this.enabled ? 'green' : 'orange';
		},

		statusBadgeText() {
			return this.statusBadge ? 'Configured' : '';
		}
	},

	methods: {
		loadComponent() {
			this.$nextTick( () => {
				this.page_loaded = true;
			} );
		},

		getSrc( icon ) {
			return this.assetsUrl + `images/icons/${ icon }.png`;
		},

		hasValidKeys() {
			return this.configData.siteKey && this.configData.secretKey;
		},

		handleToggle( enabled ) {
			if ( ! this.canEdit ) {
				return;
			}
			this.$emit('toggle-captcha', enabled);
		},

		onCaptchaNetworkContext( ctx ) {
			if ( ctx && ctx.policy ) {
				this.captchaNetworkPolicy = ctx.policy;
			}
		},

		configureCaptcha() {
			if ( this.configureButtonDisabled || ! this.showConfigureButton ) {
				return;
			}
			this.popupData = { ...this.configData };
			this.showPopup = true;
		},

		closePopup() {
			this.showPopup = false;
			this.popupData = {};
		},

		saveSettings( data ) {
			if ( ! this.canEdit ) {
				return;
			}
			this.$emit('save-settings', data);
			this.closePopup();
		},

		onHover() {
			this.isHovered = true;
		},

		onLeave() {
			this.isHovered = false;
		}
	},

	mounted() {
		this.page_loaded = true;
	}
}
</script>

<style scoped>
/* Exact copy of social login pro card styling */
.configured-tag {
  position: absolute !important;
  top: 8px !important;
  right: 8px !important;
  padding: 3px 6px !important;
  font-size: 10px !important;
  font-weight: 700 !important;
  border-radius: 8px !important;
  color: white !important;
  z-index: 999 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.3px !important;
  line-height: 1 !important;
  display: inline-block !important;
  white-space: nowrap !important;
}

.configured-tag.enabled {
  background-color: #22c55e !important;
  box-shadow: 0 1px 3px rgba(34, 197, 94, 0.4) !important;
}

.configured-tag.disabled {
  background-color: #f97316 !important;
  box-shadow: 0 1px 3px rgba(249, 115, 22, 0.4) !important;
}

.aio-login-pro__social-login__card {
  position: relative !important;
  overflow: visible !important;
  border: 1px solid #ebe8eb;
  border-radius: 10px;
  height: 230px;
  width: 320px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.aio-login-pro__social-login__card__top {
  flex: 4;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 28px 10px 10px;
  text-align: center;
}

.aio-login-pro__social-login__card__top img {
  width: 48px;
  height: 48px;
  margin-bottom: 10px;
  object-fit: contain;
}

.aio-login-pro__social-login__card__top p {
  font-size: 14px;
  font-weight: bold;
  margin: 0 0 5px 0;
}

.aio-login-pro__social-login__card__bottom {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px;
  border-top: 1px solid #ebe8eb;
}

.configure-btn {
  background: #f7ecfd;
  color: #6e16df;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
  transition: 0.3s;
  padding: 9px 18px;
}

.configure-btn:hover:not(:disabled) {
  background-color: #C9D2E3;
  color: #6e16df;
}

.configure-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.aio-login-pro__social-login__network-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  z-index: 999;
  max-width: calc(100% - 16px);
}

.aio-login-pro__social-login__network-badge .aio-login-network-tag-wrap {
  margin: 0;
}
</style>
