<template>
	<div class="aio-login-captcha-verify">
		<div v-if="!verified" class="aio-login-captcha-verify__card">
			<h4 class="aio-login-captcha-verify__title">Test Connection</h4>
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
				:disabled="testing"
				@click="runTest"
			>
				{{ testing ? 'Testing...' : 'Test Connection' }}
			</button>
			<p v-if="errorMessage" class="aio-login-captcha-verify__error">{{ errorMessage }}</p>
		</div>
		<div v-else class="aio-login-captcha-verify__card aio-login-captcha-verify__card--success">
			<div class="aio-login-captcha-verify__success-icon" aria-hidden="true">✓</div>
			<h4 class="aio-login-captcha-verify__title aio-login-captcha-verify__title--success">Connection Verified</h4>
			<p class="aio-login-captcha-verify__text">Your credentials are valid. You can finish setup now.</p>
			<button type="button" class="aio-login-captcha-verify__link" @click="resetVerification">Verify Again</button>
		</div>
	</div>
</template>

<script>
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
		};
	},

	computed: {
		providerSlug() {
			if ( this.namespace.indexOf( 'hcaptcha' ) !== -1 ) {
				return 'hcaptcha';
			}
			if ( this.namespace.indexOf( 'turnstile' ) !== -1 ) {
				return 'turnstile';
			}
			return 'recaptcha';
		},
		needsClientChallenge() {
			return 'recaptcha' !== this.providerSlug;
		},
		helperText() {
			if ( 'recaptcha' === this.providerSlug ) {
				return 'Click Test Connection to verify your reCAPTCHA keys with Google.';
			}
			return 'Complete the captcha challenge below. Your keys will be verified automatically.';
		},
		siteKey() {
			return this.payload.site_key || this.payload.siteKey || this.payload.v2_site_key || this.payload.v3_site_key || '';
		},
		secretKey() {
			return this.payload.secret_key || this.payload.secretKey || this.payload.v2_secret_key || this.payload.v3_secret_key || '';
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
		this.resetClientWidget();
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
			if ( ! this.namespace || ! this.nonce ) {
				this.errorMessage = 'Unable to test connection right now. Please reload the page.';
				return;
			}

			if ( ! this.siteKey || ! this.secretKey ) {
				this.errorMessage = 'Enter both site key and secret key before testing.';
				return;
			}

			this.errorMessage = '';

			if ( 'recaptcha' === this.providerSlug ) {
				this.runBackendTest( {} );
				return;
			}

			if ( this.pendingToken ) {
				this.runBackendTest( { response: this.pendingToken } );
				return;
			}

			this.prepareClientWidget( true );
			this.errorMessage = 'Complete the captcha challenge above, then click Test Connection again.';
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

		loadScript( src ) {
			return new Promise( ( resolve, reject ) => {
				const marker = 'data-aio-login-captcha-script';
				const scripts = document.querySelectorAll( 'script[' + marker + ']' );
				scripts.forEach( ( script ) => {
					if ( script.src !== src ) {
						script.remove();
					}
				} );

				const existing = document.querySelector( 'script[' + marker + '="' + src + '"]' );
				if ( existing ) {
					if ( '1' === existing.getAttribute( 'data-loaded' ) ) {
						resolve();
						return;
					}
					existing.addEventListener( 'load', () => resolve(), { once: true } );
					existing.addEventListener( 'error', () => reject( new Error( 'Script load failed' ) ), { once: true } );
					return;
				}

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
			} );
		},

		waitFor( predicate, attempts ) {
			attempts = attempts || 0;
			return new Promise( ( resolve, reject ) => {
				if ( predicate() ) {
					resolve();
					return;
				}
				if ( attempts >= 50 ) {
					reject( new Error( 'Timed out waiting for captcha script.' ) );
					return;
				}
				setTimeout( () => {
					this.waitFor( predicate, attempts + 1 ).then( resolve ).catch( reject );
				}, 100 );
			} );
		},

		onChallengeToken( token ) {
			this.pendingToken = token;
			this.errorMessage = '';
			this.runBackendTest( { response: token } );
		},

		prepareClientWidget( force ) {
			if ( ! this.needsClientChallenge || this.verified ) {
				return;
			}
			if ( ! this.siteKey || ! this.secretKey ) {
				return;
			}
			if ( this.widgetPrepared && ! force ) {
				return;
			}

			if ( 'hcaptcha' === this.providerSlug ) {
				this.prepareHcaptchaWidget();
				return;
			}

			if ( 'turnstile' === this.providerSlug ) {
				this.prepareTurnstileWidget();
			}
		},

		prepareHcaptchaWidget() {
			this.widgetPrepared = true;
			this.loadScript( 'https://js.hcaptcha.com/1/api.js?render=explicit' )
				.then( () => this.waitFor( () => window.hcaptcha && typeof window.hcaptcha.render === 'function' ) )
				.then( () => this.$nextTick() )
				.then( () => {
					this.resetClientWidget();
					const mount = this.$refs.challengeMount;
					if ( ! mount ) {
						throw new Error( 'Unable to render hCaptcha widget.' );
					}

					this.widgetId = window.hcaptcha.render( mount, {
						sitekey: this.siteKey,
						callback: ( token ) => {
							this.onChallengeToken( token );
						},
						'error-callback': () => {
							this.widgetPrepared = false;
							this.errorMessage = 'hCaptcha could not be loaded. Check your site key.';
						},
						'expired-callback': () => {
							this.pendingToken = '';
							this.$emit( 'update:verified', false );
							this.widgetPrepared = false;
							this.scheduleWidgetPrepare();
						},
					} );
				} )
				.catch( () => {
					this.widgetPrepared = false;
					this.errorMessage = 'Unable to load hCaptcha. Check your site key and try again.';
				} );
		},

		prepareTurnstileWidget() {
			this.widgetPrepared = true;
			this.loadScript( 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit' )
				.then( () => this.waitFor( () => window.turnstile && typeof window.turnstile.render === 'function' ) )
				.then( () => this.$nextTick() )
				.then( () => {
					this.resetClientWidget();
					const mount = this.$refs.challengeMount;
					if ( ! mount ) {
						throw new Error( 'Unable to render Turnstile widget.' );
					}

					this.widgetId = window.turnstile.render( mount, {
						sitekey: this.siteKey,
						callback: ( token ) => {
							this.onChallengeToken( token );
						},
						'error-callback': () => {
							this.widgetPrepared = false;
							this.errorMessage = 'Turnstile could not be loaded. Check your site key.';
						},
						'expired-callback': () => {
							this.pendingToken = '';
							this.$emit( 'update:verified', false );
							this.widgetPrepared = false;
							this.scheduleWidgetPrepare();
						},
					} );
				} )
				.catch( () => {
					this.widgetPrepared = false;
					this.errorMessage = 'Unable to load Turnstile. Check your site key and try again.';
				} );
		},

		resetClientWidget() {
			if ( 'hcaptcha' === this.providerSlug && null !== this.widgetId && window.hcaptcha && typeof window.hcaptcha.remove === 'function' ) {
				try {
					window.hcaptcha.remove( this.widgetId );
				} catch ( e ) {}
			}

			if ( 'turnstile' === this.providerSlug && null !== this.widgetId && window.turnstile && typeof window.turnstile.remove === 'function' ) {
				try {
					window.turnstile.remove( this.widgetId );
				} catch ( e ) {}
			}

			this.widgetId = null;
			this.widgetPrepared = false;
			if ( this.$refs.challengeMount ) {
				this.$refs.challengeMount.innerHTML = '';
			}
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
