<template>
	<div class="aio-login-captcha-verify">
		<div v-if="!verified" class="aio-login-captcha-verify__card">
			<h4 class="aio-login-captcha-verify__title">{{ $t('Test Connection') }}</h4>
			<p class="aio-login-captcha-verify__text">
				{{ helperText }}
			</p>

			<div
				v-if="needsClientChallenge"
				ref="challengeMount"
				class="aio-login-captcha-verify__widget"
			></div>

			<button
				type="button"
				class="aio-login-captcha-verify__button"
				:disabled="testing || !canRunTest"
				@click="runTest"
			>
				{{ testing ? 'Testing...' : 'Test Connection' }}
			</button>
			<p v-if="challengeReadyMessage" class="aio-login-captcha-verify__ready">{{ challengeReadyMessage }}</p>
			<p v-if="errorMessage" class="aio-login-captcha-verify__error">{{ errorMessage }}</p>
		</div>
		<div v-else class="aio-login-captcha-verify__card aio-login-captcha-verify__card--success">
			<div class="aio-login-captcha-verify__success-icon" aria-hidden="true">✓</div>
			<h4 class="aio-login-captcha-verify__title aio-login-captcha-verify__title--success">{{ $t('Connection Verified') }}</h4>
			<p class="aio-login-captcha-verify__text">{{ $t('Your credentials are valid. You can finish setup now.') }}</p>
			<button type="button" class="aio-login-captcha-verify__link" @click="resetVerification">{{ $t('Verify Again') }}</button>
		</div>
	</div>
</template>

<script>
import {
	cleanupCaptchaProviderDom,
	purgeCaptchaProviderGlobal,
	isolateActiveCaptchaProvider,
} from '../captcha-dom-cleanup.js';

export default {
	name: 'aio-login-captcha-verify',

	props: {
		namespace: {
			type: String,
			required: true,
		},
		nonce: {
			type: String,
			default: '',
		},
		payload: {
			type: Object,
			default: () => ( {} ),
		},
		verified: {
			type: Boolean,
			default: false,
		},
	},

	emits: [ 'update:verified', 'verified' ],

	data() {
		return {
			testing: false,
			errorMessage: '',
			widgetId: null,
			pendingToken: '',
			lastPayloadFingerprint: '',
			widgetPrepared: false,
			widgetPrepareInFlight: false,
			loadedV3SiteKey: '',
		};
	},

	computed: {
		providerSlug() {
			return 'recaptcha';
		},
		needsClientChallenge() {
			// reCAPTCHA v3 is invisible — key check is secret-only on button click.
			return 'v3' !== this.recaptchaVersion;
		},
		isRecaptchaV3() {
			return 'v3' === this.recaptchaVersion;
		},
		helperText() {
			if ( this.needsClientChallenge ) {
				return 'Complete the captcha challenge below, then click Test Connection to verify your keys.';
			}
			return 'Click Test Connection to verify your reCAPTCHA keys with Google.';
		},
		canRunTest() {
			if ( ! this.siteKey || ! this.secretKey ) {
				return false;
			}
			if ( this.needsClientChallenge && ! this.pendingToken ) {
				return false;
			}
			return true;
		},
		challengeReadyMessage() {
			if ( this.needsClientChallenge && this.pendingToken && ! this.testing && ! this.errorMessage ) {
				return 'Challenge completed. Click Test Connection to verify.';
			}
			return '';
		},
		siteKey() {
			return String(
				this.payload.site_key || this.payload.siteKey || this.payload.v2_site_key || this.payload.v3_site_key || ''
			).trim();
		},
		secretKey() {
			return String(
				this.payload.secret_key || this.payload.secretKey || this.payload.v2_secret_key || this.payload.v3_secret_key || ''
			).trim();
		},
		recaptchaVersion() {
			return this.payload.version || 'v2';
		},
	},

	watch: {
		payload: {
			deep: true,
			handler( newPayload ) {
				const fingerprint = this.getPayloadFingerprint( newPayload );
				if ( fingerprint === this.lastPayloadFingerprint ) {
					return;
				}
				this.lastPayloadFingerprint = fingerprint;
				this.resetClientWidget();
				this.pendingToken = '';
				this.errorMessage = '';
				this.$emit( 'update:verified', false );
				this.scheduleWidgetPrepare();
			},
		},
		verified( isVerified ) {
			if ( ! isVerified ) {
				this.scheduleWidgetPrepare();
			}
		},
	},

	mounted() {
		this.lastPayloadFingerprint = this.getPayloadFingerprint( this.payload );
		this.scheduleWidgetPrepare();
	},

	beforeUnmount() {
		this.pendingToken = '';
		this.errorMessage = '';
		this.resetClientWidget();
		cleanupCaptchaProviderDom( this.providerSlug );
	},

	methods: {
		getPayloadFingerprint( payload ) {
			const data = payload || {};
			return [
				this.providerSlug,
				data.version || '',
				data.site_key || data.siteKey || data.v2_site_key || data.v3_site_key || '',
				data.secret_key || data.secretKey || data.v2_secret_key || data.v3_secret_key || '',
			].join( '|' );
		},

		extractErrorMessage( error ) {
			if ( error && error.response && error.response.data ) {
				if ( error.response.data.message ) {
					return error.response.data.message;
				}
				if ( error.response.data.data && error.response.data.data.message ) {
					return error.response.data.data.message;
				}
			}
			return error && error.message ? error.message : 'Connection test failed. Please check your keys and try again.';
		},

		scheduleWidgetPrepare() {
			if ( ! this.needsClientChallenge || this.verified ) {
				return;
			}
			this.$nextTick( () => {
				this.prepareClientWidget();
			} );
		},

		runTest() {
			if ( ! this.namespace ) {
				this.errorMessage = 'Unable to test connection right now. Please reload the page.';
				return;
			}
			if ( ! this.nonce ) {
				this.errorMessage = 'Security token is still loading. Wait a moment and try Test Connection again.';
				return;
			}

			if ( ! this.siteKey || ! this.secretKey ) {
				this.errorMessage = 'Enter both site key and secret key before testing.';
				return;
			}

			if ( this.isRecaptchaV3 ) {
				this.testing = true;
				this.errorMessage = '';
				isolateActiveCaptchaProvider( this.providerSlug );
				this.generateRecaptchaV3Token()
					.then( ( token ) => {
						this.runBackendTest( { response: token } );
					} )
					.catch( ( error ) => {
						this.testing = false;
						this.errorMessage = this.getProviderLoadErrorMessage( error );
					} );
				return;
			}

			if ( this.needsClientChallenge && ! this.pendingToken ) {
				this.errorMessage = 'Complete the captcha challenge above, then click Test Connection.';
				return;
			}

			this.errorMessage = '';

			if ( this.pendingToken ) {
				this.runBackendTest( { response: this.pendingToken } );
				return;
			}

			this.runBackendTest( {} );
		},

		generateRecaptchaV3Token() {
			const siteKey = this.siteKey;
			return this.loadRecaptchaV3Script( siteKey ).then( () => new Promise( ( resolve, reject ) => {
				window.grecaptcha.ready( () => {
					window.grecaptcha.execute( siteKey, { action: 'test_connection' } )
						.then( resolve )
						.catch( reject );
				} );
			} ) );
		},

		loadRecaptchaV3Script( siteKey ) {
			const isReady = () => (
				window.grecaptcha &&
				typeof window.grecaptcha.execute === 'function'
			);

			if ( isReady() && this.loadedV3SiteKey === siteKey ) {
				return Promise.resolve();
			}

			this.removeProviderScripts( 'www.google.com/recaptcha' );
			this.removeProviderScripts( 'www.gstatic.com/recaptcha' );
			this.purgeProviderGlobals( 'recaptcha' );

			return new Promise( ( resolve, reject ) => {
				const script = document.createElement( 'script' );
				script.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent( siteKey );
				script.async = true;
				script.defer = true;
				script.setAttribute( 'data-aio-login-captcha-script', script.src );
				script.onload = () => {
					this.loadedV3SiteKey = siteKey;
					this.waitFor( isReady, 0, 50 ).then( resolve ).catch( reject );
				};
				script.onerror = () => reject( new Error( 'Script load failed' ) );
				document.head.appendChild( script );
			} );
		},

		runBackendTest( extraFields ) {
			this.testing = true;
			this.errorMessage = '';

			const body = {
				...this.payload,
				...extraFields,
				_wpnonce: this.nonce,
			};

			axios.post( this.namespace + '/test-connection', body )
				.then( ( response ) => {
					if ( ! response || ! response.data || ! response.data.success ) {
						throw new Error( 'Connection test failed. Please check your keys and try again.' );
					}
					this.pendingToken = '';
					this.$emit( 'update:verified', true );
					this.errorMessage = '';
					if ( response.data.message ) {
						this.$emit( 'verified', response.data.message );
					}
				} )
				.catch( ( error ) => {
					this.$emit( 'update:verified', false );
					this.pendingToken = '';
					this.errorMessage = this.extractErrorMessage( error );
					this.scheduleWidgetPrepare();
				} )
				.finally( () => {
					this.testing = false;
				} );
		},

		purgeProviderGlobals( provider ) {
			purgeCaptchaProviderGlobal( provider );
		},

		cleanupProviderArtifacts( provider ) {
			cleanupCaptchaProviderDom( provider, this.$refs.challengeMount || null );
		},

		removeProviderScripts( pattern ) {
			document.querySelectorAll( 'script[src*="' + pattern + '"]' ).forEach( ( script ) => {
				script.remove();
			} );
		},

		loadScript( src, provider ) {
			return new Promise( ( resolve, reject ) => {
				const marker = 'data-aio-login-captcha-script';
				const srcBase = src.split( '?' )[0];

				const isReady = () => {
					if ( 'recaptcha' === provider ) {
						return (
							window.grecaptcha &&
							typeof window.grecaptcha.render === 'function' &&
							!! window.___grecaptcha_cfg
						);
					}
					return true;
				};

				const injectMarkedScript = () => {
					const script = document.createElement( 'script' );
					script.src = src;
					script.async = true;
					script.defer = true;
					script.setAttribute( marker, src );
					script.onload = () => {
						script.setAttribute( 'data-loaded', '1' );
						resolve();
					};
					script.onerror = () => reject( new Error( 'Script load failed' ) );
					document.head.appendChild( script );
				};

				if ( provider && isReady() ) {
					resolve();
					return;
				}

				const findSrcScript = () => Array.prototype.slice
					.call( document.querySelectorAll( 'script[src]' ) )
					.find( ( script ) => ( script.getAttribute( 'src' ) || '' ).indexOf( srcBase ) !== -1 );

				const existingSrcScript = findSrcScript();
				if ( existingSrcScript ) {
					this.waitFor( isReady, 0, 50 ).then( resolve ).catch( () => {
						existingSrcScript.remove();
						this.purgeProviderGlobals( provider );
						injectMarkedScript();
					} );
					return;
				}

				if ( provider && 'recaptcha' === provider ) {
					this.removeProviderScripts( 'www.google.com/recaptcha' );
					this.removeProviderScripts( 'www.gstatic.com/recaptcha' );
					this.purgeProviderGlobals( provider );
				}

				document.querySelectorAll( 'script[' + marker + ']' ).forEach( ( script ) => {
					if ( script.src !== src ) {
						script.remove();
					}
				} );

				const existing = document.querySelector( 'script[' + marker + '="' + src + '"]' );
				if ( existing ) {
					existing.remove();
				}

				injectMarkedScript();
			} );
		},

		waitFor( predicate, attempts, maxAttempts ) {
			attempts = attempts || 0;
			maxAttempts = maxAttempts || 100;
			return new Promise( ( resolve, reject ) => {
				if ( predicate() ) {
					resolve();
					return;
				}
				if ( attempts >= maxAttempts ) {
					reject( new Error( 'Timed out waiting for captcha script.' ) );
					return;
				}
				setTimeout( () => {
					this.waitFor( predicate, attempts + 1, maxAttempts ).then( resolve ).catch( reject );
				}, 100 );
			} );
		},

		waitForMountRef( attempts ) {
			attempts = attempts || 0;
			return new Promise( ( resolve, reject ) => {
				const mount = this.$refs.challengeMount;
				if ( mount ) {
					resolve( mount );
					return;
				}
				if ( attempts >= 30 ) {
					reject( new Error( 'Unable to render captcha widget.' ) );
					return;
				}
				this.$nextTick( () => {
					this.waitForMountRef( attempts + 1 ).then( resolve ).catch( reject );
				} );
			} );
		},

		getProviderLoadErrorMessage( error ) {
			const message = error && error.message ? error.message : '';

			if ( 'Script load failed' === message ) {
				return 'Unable to load the captcha provider script. Check your network connection or firewall settings.';
			}

			if ( 'Timed out waiting for captcha script.' === message ) {
				return 'Captcha script did not initialize in time. Please reload and try again.';
			}

			if ( 'Unable to render captcha widget.' === message ) {
				return 'Unable to render the captcha widget. Please go back one step and try again.';
			}

			return 'Unable to load reCAPTCHA. Check your site key and try again.';
		},

		onChallengeToken( token ) {
			this.pendingToken = token;
			this.errorMessage = '';
		},

		prepareClientWidget( force ) {
			if ( ! this.needsClientChallenge || this.verified ) {
				return;
			}
			if ( ! this.siteKey || ! this.secretKey ) {
				return;
			}
			if ( this.widgetPrepareInFlight && ! force ) {
				return;
			}
			if ( this.widgetPrepared && ! force ) {
				return;
			}

			isolateActiveCaptchaProvider( this.providerSlug, this.$refs.challengeMount || null );
			this.prepareRecaptchaWidget();
		},

		prepareRecaptchaWidget() {
			this.widgetPrepared = true;
			this.loadScript( 'https://www.google.com/recaptcha/api.js?render=explicit', 'recaptcha' )
				.then( () => this.waitFor( () => (
					window.grecaptcha &&
					typeof window.grecaptcha.render === 'function' &&
					!! window.___grecaptcha_cfg
				) ) )
				.then( () => this.waitForMountRef() )
				.then( ( mount ) => {
					if ( null !== this.widgetId && mount && mount.childNodes && mount.childNodes.length ) {
						return;
					}

					this.resetClientWidget();

					const renderWidget = () => {
						try {
							this.widgetId = window.grecaptcha.render( mount, {
								sitekey: this.siteKey,
								callback: ( token ) => {
									this.onChallengeToken( token );
								},
								'error-callback': () => {
									this.widgetPrepared = false;
									this.errorMessage = 'reCAPTCHA could not be loaded. Check your site key.';
								},
								'expired-callback': () => {
									this.pendingToken = '';
									this.$emit( 'update:verified', false );
									this.widgetPrepared = false;
									this.scheduleWidgetPrepare();
								},
							} );
						} catch ( error ) {
							this.widgetPrepared = false;
							this.errorMessage = this.getProviderLoadErrorMessage( error );
						}
					};

					if ( window.grecaptcha && typeof window.grecaptcha.ready === 'function' ) {
						window.grecaptcha.ready( renderWidget );
					} else {
						renderWidget();
					}
				} )
				.catch( ( error ) => {
					this.widgetPrepared = false;
					this.errorMessage = this.getProviderLoadErrorMessage( error );
				} );
		},

		resetClientWidget() {
			this.widgetPrepareInFlight = false;

			if ( this.$refs.challengeMount ) {
				this.$refs.challengeMount.innerHTML = '';
			}

			this.widgetId = null;
			this.widgetPrepared = false;
		},

		resetVerification() {
			this.resetClientWidget();
			this.pendingToken = '';
			this.errorMessage = '';
			this.$emit( 'update:verified', false );
			this.scheduleWidgetPrepare();
		},
	},
};
</script>

<style scoped>
.aio-login-captcha-verify__card {
	background: #f9f9fb;
	border: 1px solid #e3e7ef;
	border-radius: 8px;
	padding: 24px 20px;
	margin-top: 20px;
	text-align: center;
}

.aio-login-captcha-verify__title {
	margin: 0 0 10px;
	color: #404280;
	font-size: 18px;
	font-weight: 600;
}

.aio-login-captcha-verify__title--success {
	color: #16a34a;
}

.aio-login-captcha-verify__text {
	margin: 0 0 16px;
	color: #606c80;
	font-size: 14px;
	line-height: 1.5;
}

.aio-login-captcha-verify__widget {
	display: flex;
	justify-content: center;
	margin: 0 0 16px;
	min-height: 78px;
}

.aio-login-captcha-verify__button {
	background: #9516df;
	color: #fff;
	border: none;
	border-radius: 6px;
	padding: 12px 24px;
	font-size: 15px;
	font-weight: 600;
	cursor: pointer;
}

.aio-login-captcha-verify__button:disabled {
	opacity: 0.7;
	cursor: not-allowed;
}

.aio-login-captcha-verify__ready {
	margin: 14px 0 0;
	color: #16a34a;
	font-size: 13px;
}

.aio-login-captcha-verify__error {
	margin: 14px 0 0;
	color: #dc2626;
	font-size: 13px;
}

.aio-login-captcha-verify__success-icon {
	font-size: 36px;
	color: #22c55e;
	margin-bottom: 8px;
}

.aio-login-captcha-verify__link {
	background: none;
	border: none;
	color: #9516df;
	cursor: pointer;
	text-decoration: underline;
	font-size: 14px;
}
</style>
