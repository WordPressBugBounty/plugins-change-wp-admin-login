<template>
	<div>
		<div class="aio-login-t-wrapper">
			<!-- WooCommerce Required Notice -->
			<div v-if="!woocommerceActive && !showSettings" class="woocommerce-admin-notice notice notice-warning is-dismissible">
				<p>
					<strong>WooCommerce Integration:</strong>
					To use WooCommerce Integration you have to install & activate WooCommerce.
					<button 
						@click="handleInstallActivateWooCommerce" 
						class="button button-primary" 
						style="margin-left: 10px;"
						:disabled="installingWooCommerce"
						v-if="!installingWooCommerce"
					>
						{{ installingWooCommerce ? 'Processing...' : (woocommerceInstalled ? 'Activate WooCommerce' : 'Install & Activate WooCommerce') }}
					</button>
					<span v-else style="margin-left: 10px; color: #666;">{{ woocommerceInstalled ? 'Activating WooCommerce...' : 'Installing & Activating WooCommerce...' }}</span>
				</p>
			</div>

			<!-- Show Card View -->
			<div v-if="!showSettings">
				<div>
					<h3>Integrations</h3>
				</div>
				<div class="aio-login-pro__social-login">
					<!-- WooCommerce Card -->
					<aio-login-woocommerce-card
						:has-pro="has_pro"
						:enabled="woocommerceEnabled"
						:config-data="woocommerceConfigData"
						@toggle-integration="handleToggleWooCommerce"
						@configure-integration="showWooCommerceSettings"
					/>
				</div>
			</div>

			<!-- Show Settings View -->
			<div v-else class="woocommerce-settings-view">
				<div class="settings-header">
					<div class="settings-title">
						<img :src="getWooCommerceIcon()" alt="WooCommerce" class="woocommerce-icon" />
						<h2>WooCommerce Integration Settings</h2>
					</div>
					<a href="#" class="back-link" @click.prevent="goBack">← Back</a>
				</div>

				<div class="woocommerce-settings">
					<!-- WooCommerce Integration Section -->
					<div class="settings-section">
						<div class="section-header">
							<div class="section-title-row">
								<h3>WooCommerce Integration</h3>
								<label class="toggle-switch" :class="{ 'disabled': !woocommerceActive }">
									<aio-login-toggle
										id="woocommerce-integration"
										name="woocommerce-integration"
										v-on:toggle-input="handleWooCommerceToggle"
										:enabled="settingsData.woocommerceEnabled && woocommerceActive"
										:disabled="!woocommerceActive"
									/>
								</label>
							</div>
							<p class="section-description">Enable WooCommerce integration to secure user interactions with Social Login and CAPTCHA.</p>
							<div v-if="!woocommerceActive" class="woocommerce-notice notice notice-warning inline">
								<p><strong>WooCommerce is required for this integration.</strong> Please install and activate the WooCommerce plugin to use this feature.</p>
							</div>
						</div>
					</div>

					<!-- Captcha Section -->
					<div class="settings-section">
						<div class="section-header">
							<div class="section-title-row">
								<h3>Captcha</h3>
								<label class="toggle-switch">
									<aio-login-toggle
										id="captcha-settings"
										name="captcha-settings"
										v-on:toggle-input="handleCaptchaToggle"
										:enabled="settingsData.captchaEnabled"
									/>
								</label>
							</div>
							<p class="section-description">
								To protect forms from bots and spam by adding Captcha, 
								<a href="#" class="link-text" @click.prevent="goToCaptcha">click here</a>.
							</p>
						</div>
						
					<div v-if="settingsData.captchaEnabled" class="captcha-providers">
						<div v-if="configuredProviders.captcha.length === 0" class="no-providers-message">
							<p>No Captcha providers are configured. Please configure a Captcha provider first by going to the <a href="#" class="link-text" @click.prevent="goToCaptcha">Captcha settings</a>.</p>
						</div>
						<table v-else class="settings-table">
							<thead>
								<tr>
									<th>Provider</th>
									<th>Login</th>
									<th>Registration</th>
								</tr>
							</thead>
							<tbody>
								<!-- reCAPTCHA -->
								<tr v-if="isCaptchaProviderConfigured('recaptcha')">
									<td>
										<div class="provider-cell">
											<img 
												:src="getProviderIcon('grecaptcha')" 
												alt="reCAPTCHA" 
												class="provider-icon" 
												@error="handleIconError($event, 'grecaptcha')"
											/>
											<span class="provider-name">Recaptcha</span>
										</div>
									</td>
									<td>
										<label class="toggle-switch">
											<aio-login-toggle
												id="recaptcha-login"
												name="recaptcha-login"
												v-on:toggle-input="(val) => updateProviderOption('recaptcha', 'login', val)"
												:enabled="settingsData.providers.recaptcha.login"
											/>
										</label>
									</td>
									<td>
										<label class="toggle-switch">
											<aio-login-toggle
												id="recaptcha-registration"
												name="recaptcha-registration"
												v-on:toggle-input="(val) => updateProviderOption('recaptcha', 'registration', val)"
												:enabled="settingsData.providers.recaptcha.registration"
											/>
										</label>
									</td>
								</tr>
								<!-- hCaptcha -->
								<tr v-if="isCaptchaProviderConfigured('hcaptcha')">
									<td>
										<div class="provider-cell">
											<img 
												:src="getProviderIcon('hcaptcha')" 
												alt="hCaptcha" 
												class="provider-icon" 
												@error="handleIconError($event, 'hcaptcha')"
											/>
											<span class="provider-name">Hcaptcha</span>
										</div>
									</td>
									<td>
										<label class="toggle-switch">
											<aio-login-toggle
												id="hcaptcha-login"
												name="hcaptcha-login"
												v-on:toggle-input="(val) => updateProviderOption('hcaptcha', 'login', val)"
												:enabled="settingsData.providers.hcaptcha.login"
											/>
										</label>
									</td>
									<td>
										<label class="toggle-switch">
											<aio-login-toggle
												id="hcaptcha-registration"
												name="hcaptcha-registration"
												v-on:toggle-input="(val) => updateProviderOption('hcaptcha', 'registration', val)"
												:enabled="settingsData.providers.hcaptcha.registration"
											/>
										</label>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					</div>

					<!-- Social Login Section -->
					<div class="settings-section">
						<div class="section-header">
							<div class="section-title-row">
								<h3>Social Login</h3>
								<label class="toggle-switch">
									<aio-login-toggle
										id="social-login-options"
										name="social-login-options"
										v-on:toggle-input="handleSocialLoginToggle"
										:enabled="settingsData.socialLoginEnabled"
									/>
								</label>
							</div>
							<p class="section-description">
								To allow users to log in with their social accounts, 
								<a href="#" class="link-text" @click.prevent="goToSocialLogin">click here</a>.
							</p>
						</div>
						
					<div v-if="settingsData.socialLoginEnabled" class="social-providers">
						<div v-if="configuredProviders.social.length === 0" class="no-providers-message">
							<p>No Social Login providers are configured. Please configure a Social Login provider first by going to the <a href="#" class="link-text" @click.prevent="goToSocialLogin">Social Login settings</a>.</p>
						</div>
						<table v-else class="settings-table">
							<thead>
								<tr>
									<th>Provider</th>
									<th>Login</th>
									<th>Registration</th>
									<th>Checkout</th>
								</tr>
							</thead>
							<tbody>
								<!-- Dynamic loop for all social providers -->
								<template v-for="provider in availableSocialProviders" :key="provider.key">
									<tr v-if="isSocialProviderConfigured(provider.key)">
									<td>
										<div class="provider-cell">
											<img 
												:src="getProviderIcon(provider.key)" 
												:alt="provider.name" 
												class="provider-icon" 
												@error="handleIconError($event, provider.key)"
											/>
											<span class="provider-name">{{ provider.name }}</span>
										</div>
									</td>
									<td>
										<label class="toggle-switch">
											<aio-login-toggle
												:id="`${provider.key}-login`"
												:name="`${provider.key}-login`"
												v-on:toggle-input="(val) => updateSocialProviderOption(provider.key, 'login', val)"
												:enabled="settingsData.socialProviders[provider.key] && settingsData.socialProviders[provider.key].login"
											/>
										</label>
									</td>
									<td>
										<label class="toggle-switch">
											<aio-login-toggle
												:id="`${provider.key}-registration`"
												:name="`${provider.key}-registration`"
												v-on:toggle-input="(val) => updateSocialProviderOption(provider.key, 'registration', val)"
												:enabled="settingsData.socialProviders[provider.key] && settingsData.socialProviders[provider.key].registration"
											/>
										</label>
									</td>
									<td>
										<label class="toggle-switch">
											<aio-login-toggle
												:id="`${provider.key}-checkout`"
												:name="`${provider.key}-checkout`"
												v-on:toggle-input="(val) => updateSocialProviderOption(provider.key, 'checkout', val)"
												:enabled="settingsData.socialProviders[provider.key] && settingsData.socialProviders[provider.key].checkout"
											/>
										</label>
									</td>
									</tr>
								</template>
							</tbody>
						</table>
					</div>
					</div>
				</div>

				<p class="submit">
					<button type="button" class="button aio-login__primary" @click="saveSettings">Save Changes</button>
				</p>
			</div>
		</div>

		<aio-login-snackbar
			:message="snackbar.message"
			v-if="snackbar.show"
			:duration="snackbar.duration"
			v-on:close="handleSnackbarClose"
		/>
	</div>
</template>

<script>
export default {
	name: 'integrations',

	data: ( vm ) => ( {
		has_pro: 'true' === aio_login__app_object.has_pro,
		woocommerceEnabled: false,
		woocommerceConfigData: {},
		woocommerceActive: true, // Default to true, will be updated from API
		woocommerceInstalled: false, // Check if WooCommerce is installed but not activated
		installingWooCommerce: false,
		showSettings: false,
		assetsUrl: aio_login__app_object.assets_url,
		snackbar: {
			message: '',
			show: false,
			duration: 3000,
		},
		configuredProviders: {
			captcha: [],
			social: []
		},
		settingsData: {
			woocommerceEnabled: false,
			captchaEnabled: false,
			socialLoginEnabled: false,
			providers: {
				recaptcha: {
					login: false,
					registration: false
				},
				hcaptcha: {
					login: false,
					registration: false
				}
			},
			socialProviders: {
				microsoft: {
					login: false,
					registration: false,
					checkout: false
				},
				google: {
					login: false,
					registration: false,
					checkout: false
				},
				facebook: {
					login: false,
					registration: false,
					checkout: false
				},
				line: {
					login: false,
					registration: false,
					checkout: false
				}
			}
		}
	} ),

	computed: {
		availableSocialProviders() {
			// Return all available social providers with their display names
			return [
				{ key: 'google', name: 'Google' },
				{ key: 'microsoft', name: 'Microsoft' },
				{ key: 'facebook', name: 'Facebook' },
				{ key: 'line', name: 'LINE' }
			];
		}
	},

	async mounted() {
		// Check URL hash immediately (don't wait for data)
		this.checkUrlHash();
		
		// Listen for hash changes
		window.addEventListener('hashchange', this.checkUrlHash);
		
		// Load cached settings from localStorage first (instant)
		this.loadCachedSettings();
		
		// Load WooCommerce settings (fast, cached) - don't wait for providers
		this.loadWooCommerceSettings().then(() => {
			// If WooCommerce is configured, sync settings data
			if (this.woocommerceConfigData && Object.keys(this.woocommerceConfigData).length > 0) {
				this.settingsData = { ...this.settingsData, ...this.woocommerceConfigData };
				// Cache the settings
				localStorage.setItem('aio_login_woocommerce_settings', JSON.stringify(this.woocommerceConfigData));
			}
			// Load providers lazily if needed - force refresh to get latest enabled status
			if (this.settingsData.captchaEnabled || this.settingsData.socialLoginEnabled) {
				this.loadConfiguredProviders(true).catch(error => {
					console.error('Error loading providers:', error);
				});
			}
		}).catch(error => {
			console.error('Error loading initial settings:', error);
		});
	},
	
	beforeDestroy() {
		// Clean up event listener
		window.removeEventListener('hashchange', this.checkUrlHash);
	},

	methods: {
		loadCachedSettings() {
			// Load cached settings from localStorage for instant display
			try {
				const cached = localStorage.getItem('aio_login_woocommerce_settings');
				if (cached) {
					const settings = JSON.parse(cached);
					this.woocommerceConfigData = settings;
					this.woocommerceEnabled = settings.woocommerceEnabled || false;
					this.settingsData.woocommerceEnabled = this.woocommerceEnabled;
					this.settingsData.captchaEnabled = settings.captchaEnabled || false;
					this.settingsData.socialLoginEnabled = settings.socialLoginEnabled || false;
					if (settings.providers) {
						this.settingsData.providers = { ...this.settingsData.providers, ...settings.providers };
					}
					if (settings.socialProviders) {
						this.settingsData.socialProviders = { ...this.settingsData.socialProviders, ...settings.socialProviders };
					}
				}
				
				// Also load configured providers from cache
				const cachedProviders = localStorage.getItem('aio_login_configured_providers');
				if (cachedProviders) {
					const data = JSON.parse(cachedProviders);
					// Check if cache is less than 5 minutes old (or just use it regardless for better UX)
					if (data.providers) {
						this.configuredProviders = data.providers;
					}
				}
			} catch (error) {
				console.error('Error loading cached settings:', error);
			}
		},
		async loadConfiguredProviders(forceRefresh = false) {
			// Check cache first (5 minute cache) unless force refresh is requested
			const cacheKey = 'aio_login_configured_providers';
			const cacheExpiry = 5 * 60 * 1000; // 5 minutes
			if (!forceRefresh) {
				try {
					const cached = localStorage.getItem(cacheKey);
					if (cached) {
						const cachedData = JSON.parse(cached);
						if (cachedData.timestamp && (Date.now() - cachedData.timestamp) < cacheExpiry) {
							this.configuredProviders = cachedData.providers;
							return; // Use cached data
						}
					}
				} catch (error) {
					// Ignore cache errors
				}
			}

			// Reset configured providers
			this.configuredProviders.captcha = [];
			this.configuredProviders.social = [];

			// Load all providers in parallel for better performance
			try {
				// Load captcha providers in parallel
				const [grecaptchaResponse, hcaptchaResponse] = await Promise.all([
					axios.get('aio-login/grecaptcha/get-settings').catch(() => null),
					axios.get('aio-login/hcaptcha/get-settings').catch(() => null)
				]);

				// Check reCAPTCHA
				if (grecaptchaResponse && grecaptchaResponse.data) {
					const grecaptchaData = grecaptchaResponse.data;
					const hasV2Keys = grecaptchaData.v2_site_key && String(grecaptchaData.v2_site_key).trim().length > 0 && 
									  grecaptchaData.v2_secret_key && String(grecaptchaData.v2_secret_key).trim().length > 0;
					const hasV3Keys = grecaptchaData.v3_site_key && String(grecaptchaData.v3_site_key).trim().length > 0 && 
									  grecaptchaData.v3_secret_key && String(grecaptchaData.v3_secret_key).trim().length > 0;
					const isEnabled = grecaptchaData.enabled === true || grecaptchaData.enabled === 'on';
					if ((hasV2Keys || hasV3Keys) && isEnabled) {
						this.configuredProviders.captcha.push('recaptcha');
					}
				}

				// Check hCaptcha
				if (hcaptchaResponse && hcaptchaResponse.data) {
					const hcaptchaData = hcaptchaResponse.data;
					const hasSiteKey = hcaptchaData.site_key && String(hcaptchaData.site_key).trim().length > 0;
					const hasSecretKey = hcaptchaData.secret_key && String(hcaptchaData.secret_key).trim().length > 0;
					const isEnabled = hcaptchaData.enabled === true || hcaptchaData.enabled === 'on';
					if (hasSiteKey && hasSecretKey && isEnabled) {
						this.configuredProviders.captcha.push('hcaptcha');
					}
				}
			} catch (error) {
				// Silently handle errors
			}

			// Load configured social login providers in parallel
			try {
				// Get enabled status for all providers and all provider settings in parallel
				const [providersResponse, ...socialProviderResponses] = await Promise.all([
					axios.get('aio-login-pro/social-login/get-providers').catch(() => ({ data: { success: false } })),
					...['google', 'microsoft', 'facebook', 'line'].map(provider => 
						axios.get(`aio-login-pro/social-login/get-settings?provider=${provider}`).catch(() => null)
					)
				]);

				let enabledProviders = {};
				if (providersResponse.data && providersResponse.data.success && providersResponse.data.providers) {
					enabledProviders = providersResponse.data.providers;
				}

				// Process all social provider responses
				const socialProviders = ['google', 'microsoft', 'facebook', 'line'];
				socialProviderResponses.forEach((response, index) => {
					if (!response || !response.data) return;
					
					const provider = socialProviders[index];
					const responseData = response.data;
					let providerData = null;
					
					// Handle nested response structure
					if (responseData.success && responseData.data) {
						if (responseData.data.data) {
							providerData = responseData.data.data;
						} else if (responseData.data.client_id) {
							providerData = responseData.data;
						}
					} else if (responseData.client_id) {
						providerData = responseData;
					}

					if (providerData) {
						const hasClientId = providerData.client_id && String(providerData.client_id).trim().length > 0;
						const hasClientSecret = providerData.client_secret && String(providerData.client_secret).trim().length > 0;
						const isEnabled = enabledProviders[provider] === true || enabledProviders[provider] === '1';

						if (hasClientId && hasClientSecret && isEnabled) {
							this.configuredProviders.social.push(provider);
						}
					}
				});
				
				// Cache the providers for 5 minutes
				try {
					localStorage.setItem(cacheKey, JSON.stringify({
						providers: this.configuredProviders,
						timestamp: Date.now()
					}));
				} catch (error) {
					// Ignore cache errors
				}
			} catch (error) {
				// Silently handle errors
			}
		},
		isCaptchaProviderConfigured(provider) {
			return this.configuredProviders.captcha.includes(provider);
		},
		isSocialProviderConfigured(provider) {
			return this.configuredProviders.social.includes(provider);
		},
		checkUrlHash() {
			// Check if URL hash is #/woocommerce-integrations
			const hash = window.location.hash;
			if (hash === '#/woocommerce-integrations') {
				this.showSettings = true;
			} else {
				this.showSettings = false;
				// If hash is #/integrations or empty, remove it to keep URL clean
				if (hash === '#/integrations' || hash === '') {
					const baseUrl = window.location.href.split('#')[0];
					if (history.pushState) {
						history.replaceState(null, null, baseUrl);
					}
				}
			}
		},
		updateUrlHash(hash) {
			// Update URL hash without triggering page reload
			if (history.pushState) {
				// If hash starts with #, use it directly, otherwise add it
				const newHash = hash.startsWith('#') ? hash : '#' + hash;
				const newUrl = window.location.href.split('#')[0] + newHash;
				history.pushState(null, null, newUrl);
				// Trigger hashchange event manually
				window.dispatchEvent(new HashChangeEvent('hashchange'));
			} else {
				window.location.hash = hash;
			}
		},
		async loadWooCommerceSettings() {
			// Load WooCommerce settings from backend
			try {
				const response = await axios.get('aio-login-pro/woocommerce/get-settings', {
					timeout: 3000 // 3 second timeout for faster failure
				});
				if (response.data && response.data.success && response.data.data) {
					const settings = response.data.data;
					this.woocommerceConfigData = settings;
					this.woocommerceActive = settings.woocommerceActive !== undefined ? settings.woocommerceActive : true;
					this.woocommerceInstalled = settings.woocommerceInstalled !== undefined ? settings.woocommerceInstalled : false;
					// If WooCommerce is not active, disable the integration
					if (!this.woocommerceActive) {
						this.woocommerceEnabled = false;
						this.settingsData.woocommerceEnabled = false;
					} else {
						this.woocommerceEnabled = settings.woocommerceEnabled || false;
						this.settingsData.woocommerceEnabled = this.woocommerceEnabled;
					}
					this.settingsData.captchaEnabled = settings.captchaEnabled || false;
					this.settingsData.socialLoginEnabled = settings.socialLoginEnabled || false;
					this.settingsData.providers = { ...this.settingsData.providers, ...settings.providers };
					this.settingsData.socialProviders = { ...this.settingsData.socialProviders, ...settings.socialProviders };
					
				// Handle configured providers if returned
				// Note: We don't set configuredProviders from backend here because
				// loadConfiguredProviders() will fetch fresh data and verify enabled status
				// This prevents showing disabled providers due to stale backend snapshot

					// Cache the settings for next time
					localStorage.setItem('aio_login_woocommerce_settings', JSON.stringify(settings));
					
					return settings;
				}
			} catch (error) {
				console.error('Error loading WooCommerce settings:', error);
				// Don't reset if we have cached data
				if (!this.woocommerceConfigData || Object.keys(this.woocommerceConfigData).length === 0) {
					this.woocommerceConfigData = {};
					this.woocommerceEnabled = false;
				}
			}
			return null;
		},
		async handleToggleWooCommerce(enabled) {
			// Prevent enabling if WooCommerce is not active
			if (enabled && !this.woocommerceActive) {
				this.snackbar.message = 'WooCommerce is required for this integration. Please install and activate the WooCommerce plugin.';
				this.snackbar.show = true;
				return;
			}
			
			this.woocommerceEnabled = enabled;
			// Update local state immediately
			this.settingsData.woocommerceEnabled = enabled;
			
			// Cache immediately for instant feedback
			if (this.woocommerceConfigData) {
				this.woocommerceConfigData.woocommerceEnabled = enabled;
				localStorage.setItem('aio_login_woocommerce_settings', JSON.stringify(this.woocommerceConfigData));
			}
			
			// Save to backend in background (non-blocking)
			const currentSettings = { ...this.settingsData };
			currentSettings.woocommerceEnabled = enabled;
			axios.post('aio-login-pro/woocommerce/save-settings', currentSettings)
				.then((response) => {
					const status = enabled ? 'enabled' : 'disabled';
					this.snackbar.message = response.data.message || `WooCommerce integration ${status} successfully`;
					this.snackbar.show = true;
				})
				.catch(error => {
					console.error('Error saving WooCommerce toggle:', error);
					this.snackbar.message = 'Error updating WooCommerce integration. Please try again.';
					this.snackbar.show = true;
				});
		},
		async showWooCommerceSettings() {
			// Sync the WooCommerce enabled state from card to settings BEFORE loading from backend
			const currentEnabledState = this.woocommerceEnabled;
			
			// Load cached settings first for instant display
			this.loadCachedSettings();
			
			// Show settings UI immediately for better UX (don't wait for anything)
			this.showSettings = true;
			// Update URL hash
			this.updateUrlHash('#/woocommerce-integrations');
			
			// Override with the current enabled state from card immediately
			if (currentEnabledState !== this.settingsData.woocommerceEnabled) {
				this.settingsData.woocommerceEnabled = currentEnabledState;
				this.woocommerceEnabled = currentEnabledState;
			}
			
			// Load providers from cache first (instant display)
			const cacheKey = 'aio_login_configured_providers';
			try {
				const cached = localStorage.getItem(cacheKey);
				if (cached) {
					const cachedData = JSON.parse(cached);
					if (cachedData.providers) {
						this.configuredProviders = cachedData.providers;
					}
				}
			} catch (error) {
				// Ignore cache errors
			}
			
			// Load fresh WooCommerce settings and providers in parallel (non-blocking)
			// Don't wait for these - UI is already shown with cached data
			// Load fresh WooCommerce settings
			this.loadWooCommerceSettings().then((response) => {
				// Update configured providers from backend if available (without separate API calls)
				if (response && response.configuredProviders) {
					this.configuredProviders = response.configuredProviders;
					
					// Cache this for next time to make it instant
					try {
						localStorage.setItem('aio_login_configured_providers', JSON.stringify({
							providers: this.configuredProviders,
							timestamp: Date.now()
						}));
					} catch (e) {}
				}

				// Update enabled state if needed
				if (currentEnabledState !== this.settingsData.woocommerceEnabled) {
					this.settingsData.woocommerceEnabled = currentEnabledState;
					this.woocommerceEnabled = currentEnabledState;
				}
			}).catch(() => {});
		},
		
		loadConfiguredProvidersLazy() {
			// Load providers in background without blocking
			// Only load if captcha or social login is enabled
			if (this.settingsData.captchaEnabled || this.settingsData.socialLoginEnabled) {
				this.loadConfiguredProviders().catch(error => {
					console.error('Error loading providers:', error);
				});
			}
		},
		goBack() {
			this.showSettings = false;
			// Update URL hash to remove the settings hash
			const baseUrl = window.location.href.split('#')[0];
			if (history.pushState) {
				history.pushState(null, null, baseUrl);
				window.dispatchEvent(new HashChangeEvent('hashchange'));
			} else {
				window.location.hash = '';
			}
		},
		getWooCommerceIcon() {
			return this.assetsUrl + 'images/icons/woo.svg';
		},
		getProviderIcon(provider) {
			const iconMap = {
				'grecaptcha': 'grecaptcha',
				'hcaptcha': 'hcaptcha',
				'microsoft': 'microsoft',
				'google': 'google',
				'facebook': 'facebook',
				'line': 'line'
			};
			const iconName = iconMap[provider] || provider;
			// For social providers, try SVG first, then PNG as fallback
			if (['microsoft', 'google', 'facebook', 'line'].includes(iconName)) {
				return this.assetsUrl + `images/icons/${iconName}.svg`;
			}
			// For captcha providers, use PNG
			return this.assetsUrl + `images/icons/${iconName}.png`;
		},
		handleIconError(event, provider) {
			// If SVG fails to load, try PNG instead
			const iconMap = {
				'grecaptcha': 'grecaptcha',
				'hcaptcha': 'hcaptcha',
				'microsoft': 'microsoft',
				'google': 'google',
				'facebook': 'facebook',
				'line': 'line'
			};
			const iconName = iconMap[provider] || provider;
			const currentSrc = event.target.src;
			// Only fallback if current src is SVG
			if (currentSrc.endsWith('.svg')) {
				event.target.src = this.assetsUrl + `images/icons/${iconName}.png`;
			}
		},
		handleWooCommerceToggle(enabled) {
			this.settingsData.woocommerceEnabled = enabled;
		},
		handleCaptchaToggle(enabled) {
			this.settingsData.captchaEnabled = enabled;
		},
		handleSocialLoginToggle(enabled) {
			this.settingsData.socialLoginEnabled = enabled;
		},
		updateProviderOption(provider, option, value) {
			if (this.settingsData.providers[provider]) {
				this.settingsData.providers[provider][option] = value;
				
				// If enabling a captcha provider, disable the other one
				if (value === true && (option === 'login' || option === 'registration')) {
					// Check if this provider is configured
					const isConfigured = this.configuredProviders.captcha.includes(provider);
					
					if (isConfigured) {
						// Disable the other captcha provider
						if (provider === 'hcaptcha') {
							// Disable reCAPTCHA
							if (this.settingsData.providers.recaptcha) {
								this.settingsData.providers.recaptcha[option] = false;
							}
							if (this.settingsData.providers.grecaptcha) {
								this.settingsData.providers.grecaptcha[option] = false;
							}
						} else if (provider === 'recaptcha' || provider === 'grecaptcha') {
							// Disable hCaptcha
							if (this.settingsData.providers.hcaptcha) {
								this.settingsData.providers.hcaptcha[option] = false;
							}
							// Also disable the other reCAPTCHA variant
							if (provider === 'recaptcha' && this.settingsData.providers.grecaptcha) {
								this.settingsData.providers.grecaptcha[option] = false;
							}
							if (provider === 'grecaptcha' && this.settingsData.providers.recaptcha) {
								this.settingsData.providers.recaptcha[option] = false;
							}
						}
					}
				}
			}
		},
		updateSocialProviderOption(provider, option, value) {
			if (this.settingsData.socialProviders[provider]) {
				this.settingsData.socialProviders[provider][option] = value;
			}
		},
		goToCaptcha() {
			const url = new URL(window.location.href);
			url.searchParams.set('tab', 'security');
			url.hash = '#/captcha';
			window.location.href = url.toString();
		},
		goToSocialLogin() {
			const url = new URL(window.location.href);
			url.searchParams.set('tab', 'social-login');
			window.location.href = url.toString();
		},
				async saveSettings() {
					// Update config data
					this.woocommerceConfigData = { ...this.woocommerceConfigData, ...this.settingsData };

					try {
						// Save to backend via REST API
						const response = await axios.post('aio-login-pro/woocommerce/save-settings', this.settingsData);

						if (response.data && response.data.success) {
							// Show success message
							this.snackbar.message = response.data.message || 'Settings saved successfully';
							this.snackbar.show = true;

							// Also save to localStorage as backup
							localStorage.setItem('aio_login_woocommerce_settings', JSON.stringify(this.woocommerceConfigData));
							
							// Clear providers cache to force refresh on next load
							localStorage.removeItem('aio_login_configured_providers');
						} else {
							throw new Error('Failed to save settings');
						}
					} catch (error) {
						console.error('Error saving WooCommerce settings:', error);
						// Fallback to localStorage
						localStorage.setItem('aio_login_woocommerce_settings', JSON.stringify(this.woocommerceConfigData));

						// Show success message even if API fails (using localStorage)
						this.snackbar.message = 'Settings saved successfully (local storage)';
						this.snackbar.show = true;
					}

					// Don't close settings view - stay on the same page
				},
		handleSnackbarClose() {
			this.snackbar.show = false;
			this.snackbar.message = '';
		},
		async handleInstallActivateWooCommerce() {
			if (this.installingWooCommerce) {
				return;
			}

			this.installingWooCommerce = true;

			try {
				// Install/activate WooCommerce via AJAX
				// Get full absolute URL to bypass axios baseURL (REST API)
				let ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : (aio_login__app_object.ajax_url || window.location.origin + '/wp-admin/admin-ajax.php');
				
				// Ensure absolute URL (if relative, make it absolute)
				if (ajaxUrl && !ajaxUrl.startsWith('http')) {
					ajaxUrl = new URL(ajaxUrl, window.location.origin).href;
				}
				
				const formData = new FormData();
				formData.append('action', 'aio_login_install_activate_woocommerce');
				// Use REST API nonce for AJAX call
				formData.append('nonce', aio_login__app_object.nonce);

				// Create a new axios instance without baseURL to avoid REST API routing
				const axiosAjax = axios.create({
					baseURL: '',
					timeout: 60000, // 60 seconds for plugin installation
				});

				const response = await axiosAjax.post(ajaxUrl, formData, {
					headers: {
						'Content-Type': 'multipart/form-data',
					},
				});

				if (response.data && response.data.success) {
					this.snackbar.message = response.data.data.message || 'WooCommerce installed and activated successfully!';
					this.snackbar.show = true;
					
					// Reload WooCommerce settings to update status
					setTimeout(() => {
						this.loadWooCommerceSettings().then(() => {
							// Reload page to reflect changes
							window.location.reload();
						});
					}, 1000);
				} else {
					throw new Error(response.data.data?.message || 'Failed to install/activate WooCommerce');
				}
			} catch (error) {
				console.error('Error installing/activating WooCommerce:', error);
				this.snackbar.message = error.response?.data?.data?.message || error.message || 'Failed to install/activate WooCommerce. Please try again.';
				this.snackbar.show = true;
				this.installingWooCommerce = false;
			}
		}
	}
}
</script>

<style scoped>
.aio-login-pro__social-login {
	display: flex;
	flex-wrap: wrap;
	gap: 20px;
}

.woocommerce-settings-view {
	padding: 20px;
}

.settings-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 30px;
	padding-bottom: 20px;
	border-bottom: 1px solid #EBE8EB;
}

.settings-title {
	display: flex;
	align-items: center;
	gap: 15px;
}

.woocommerce-icon {
	width: 48px;
	height: 48px;
}

.settings-title h2 {
	margin: 0;
	font-size: 24px;
	font-weight: 600;
	color: #404280;
}

.back-link {
	color: #6E16DF;
	text-decoration: none;
	font-weight: 500;
}

.back-link:hover {
	text-decoration: underline;
}

.woocommerce-settings {
	margin-bottom: 30px;
}

.settings-section {
	margin-bottom: 40px;
}

.section-header {
	margin-bottom: 20px;
}

.section-title-row {
	display: flex;
	align-items: center;
	margin-bottom: 10px;
}

.section-header h3 {
	margin: 0;
	font-size: 20px;
	font-weight: 600;
	color: #404280;
	white-space: nowrap;
	flex-shrink: 0;
	min-width: 250px;
}

.section-title-row .toggle-switch {
	margin-left: 15px;
	flex-shrink: 0;
}

.section-description {
	margin: 0;
	color: #606C80;
	font-size: 14px;
}

.link-text {
	color: #6E16DF;
	text-decoration: none;
	cursor: pointer;
}

.no-providers-message {
	padding: 20px;
	background: #F7ECFD;
	border: 1px solid #EBE8EB;
	border-radius: 8px;
	margin-top: 15px;
}

.no-providers-message p {
	margin: 0;
	color: #606C80;
	font-size: 14px;
	line-height: 1.6;
}

.link-text:hover {
	text-decoration: underline;
}


.captcha-providers,
.social-providers {
	margin-top: 20px;
}

.settings-table {
	width: 700px;
	border-collapse: collapse;
	background: white;
	border-radius: 8px;
	overflow: hidden;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.settings-table thead {
	background: #F9F9F9;
}

.settings-table th {
	padding: 15px 20px;
	text-align: center;
	font-size: 14px;
	font-weight: 600;
	color: #404280;
	border-bottom: 1px solid #EBE8EB;
}

.settings-table th:first-child {
	text-align: left;
}

.settings-table td {
	padding: 15px 20px;
	border-bottom: 1px solid #EBE8EB;
	vertical-align: middle;
}

.settings-table td:first-child {
	text-align: left;
}

.settings-table td:not(:first-child) {
	text-align: center;
}

.settings-table td .toggle-switch {
	display: inline-flex;
	justify-content: center;
	align-items: center;
}

.settings-table tbody tr:last-child td {
	border-bottom: none;
}

.settings-table tbody tr:hover {
	background: #F9F9F9;
}

.provider-cell {
	display: flex;
	align-items: center;
	gap: 12px;
}

.provider-icon {
	width: 32px;
	height: 32px;
}

.provider-name {
	font-size: 14px;
	font-weight: 600;
	color: #404280;
}

.submit {
	margin-top: 20px;
	padding-top: 20px;
	border-top: 1px solid #EBE8EB;
}

.submit .button {
	padding: 6px 12px;
	font-size: 13px;
	line-height: 1.5;
	height: auto;
	margin: 0;
	cursor: pointer;
	border-width: 1px;
	border-style: solid;
	border-radius: 3px;
	white-space: nowrap;
	box-sizing: border-box;
}

.submit .button.aio-login__primary {
	border-color: #9516df;
	background-color: #9416de;
	color: #fff;
}

.submit .button.aio-login__primary:hover {
	background-color: #9416de;
	color: #fff;
}

.woocommerce-notice {
	margin-top: 15px;
	padding: 12px;
	background: #fff3cd;
	border-left: 4px solid #ffc107;
}

.woocommerce-notice p {
	margin: 0;
	color: #856404;
}

.toggle-switch.disabled {
	opacity: 0.6;
	cursor: not-allowed;
	pointer-events: none;
}

.woocommerce-admin-notice {
	margin: 20px 0;
	padding: 12px;
	background: #fff3cd;
	border-left: 4px solid #ffc107;
}

.woocommerce-admin-notice p {
	margin: 0;
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 10px;
}

.woocommerce-admin-notice .button {
	margin-left: 0 !important;
}
</style>

