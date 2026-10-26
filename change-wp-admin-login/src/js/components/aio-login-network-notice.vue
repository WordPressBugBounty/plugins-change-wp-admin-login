<template>
	<span
		v-if="show"
		class="aio-login-network-tag-wrap"
		:class="{ 'aio-login-network-tag-wrap--inline': inline, 'aio-login-network-tag-wrap--bar': ! inline }"
	>
		<span
			class="aio-login-network-tag"
			:class="tagClass"
			:title="hintText"
		>
			{{ tagLabel }}
		</span>
		<span
			v-if="! inline && hintText"
			class="aio-login-network-tag__hint"
		>
			{{ hintText }}
		</span>
		<div
			v-if="can_toggle_override"
			class="aio-login-network-tag__override"
			:class="{ 'aio-login-network-tag__override--inline': inline }"
		>
			<label
				class="aio-login-network-tag__override-checkbox"
				:for="overrideToggleId"
			>
				<input
					:id="overrideToggleId"
					type="checkbox"
					class="aio-login-network-tag__override-input"
					:checked="has_override"
					:disabled="saving"
					@change="onOverrideCheckboxChange"
				/>
				<span
					class="aio-login-network-tag__override-label"
					:class="{ 'aio-login-network-tag__override-label--compact': inline }"
				>{{ override_label }}</span>
			</label>
		</div>
	</span>
</template>

<script>
export default {
	name: 'aio-login-network-notice',

	props: {
		feature: {
			type: String,
			required: true,
		},
		/**
		 * Compact tag next to a feature title (child sites only).
		 */
		inline: {
			type: Boolean,
			default: true,
		},
		/**
		 * Limit which policies render this notice.
		 * Empty = show for both network_managed and site_override_allowed.
		 * Example: ['network_managed'] or ['site_override_allowed'].
		 */
		onlyPolicies: {
			type: Array,
			default: null,
		},
		/**
		 * Whether to show the "Enable local override" toggle (site_override_allowed only).
		 */
		showOverrideToggle: {
			type: Boolean,
			default: true,
		},
		/**
		 * When true, hide this tag unless the Pro plugin is active (e.g. hCaptcha/Turnstile cards use feature "captcha").
		 */
		requireProActive: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			context: null,
			saving: false,
			toggleUid: Math.random().toString( 36 ).slice( 2, 11 ),
		};
	},

	computed: {
		show() {
			// Tags on all site screens (main + child). Hide on Network Admin.
			if ( this.isNetworkAdmin ) {
				return false;
			}
			if ( this.shouldHideForInactivePro() ) {
				return false;
			}
			if ( ! this.context || ! this.context.is_network ) {
				return false;
			}
			const policyOk = (
				'network_managed' === this.policy
				|| 'site_override_allowed' === this.policy
			);
			if ( ! policyOk ) {
				return false;
			}
			if ( Array.isArray( this.onlyPolicies ) && this.onlyPolicies.length ) {
				return this.onlyPolicies.indexOf( this.policy ) !== -1;
			}
			return true;
		},
		isNetworkAdmin() {
			const o = window.aio_login__app_object || {};
			if ( typeof o.is_network_admin !== 'undefined' ) {
				return o.is_network_admin === true || o.is_network_admin === 'true' || o.is_network_admin === 1 || o.is_network_admin === '1';
			}
			return !!( this.context && this.context.is_network_admin );
		},
		isMainSite() {
			// Kept for compatibility; main site is no longer privileged.
			const o = window.aio_login__app_object || {};
			if ( typeof o.is_main_site !== 'undefined' ) {
				return o.is_main_site === true || o.is_main_site === 'true' || o.is_main_site === 1 || o.is_main_site === '1';
			}
			return !!( this.context && this.context.is_main_site );
		},
		policy() {
			return this.context ? this.context.policy : '';
		},
		tagLabel() {
			if ( 'network_managed' === this.policy ) {
				return 'Network Managed';
			}
			if ( 'site_override_allowed' === this.policy ) {
				return this.has_override ? 'Local Override' : 'Site Override Allowed';
			}
			return 'Network';
		},
		tagClass() {
			if ( 'network_managed' === this.policy ) {
				return 'aio-login-network-tag--managed';
			}
			if ( 'site_override_allowed' === this.policy ) {
				return this.has_override
					? 'aio-login-network-tag--override-on'
					: 'aio-login-network-tag--override';
			}
			return '';
		},
		hintText() {
			if ( 'network_managed' === this.policy ) {
				return 'Read-only on this site. Edit in Network Admin → AIO Login, or change the network policy.';
			}
			if ( 'site_override_allowed' === this.policy && ! this.has_override ) {
				return 'Inherited from network.';
			}
			if ( 'site_override_allowed' === this.policy && this.has_override ) {
				return 'Using local settings for this site.';
			}
			return '';
		},
		has_override() {
			return !!( this.context && this.context.has_site_override );
		},
		can_toggle_override() {
			return this.showOverrideToggle
				&& this.context
				&& this.context.policy === 'site_override_allowed';
		},
		override_label() {
			return 'Enable local override';
		},
		overrideToggleId() {
			return 'aio-login-site-override-' + this.feature + '-' + this.toggleUid;
		},
		can_edit() {
			return !!( this.context && this.context.can_edit );
		},
	},

	watch: {
		context: {
			immediate: true,
			handler( ctx ) {
				if ( ! ctx ) {
					return;
				}
				if ( typeof window !== 'undefined' && window.aio_login__app_object ) {
					window.aio_login__app_object.feature_contexts = window.aio_login__app_object.feature_contexts || {};
					window.aio_login__app_object.feature_contexts[ this.feature ] = ctx;
				}
				this.$emit( 'can-edit', !! ctx.can_edit );
				this.$emit( 'context', ctx );
				if ( typeof window !== 'undefined' && typeof window.dispatchEvent === 'function' ) {
					window.dispatchEvent( new CustomEvent( 'aio-login:feature-context-changed', {
						detail: {
							feature: this.feature,
							context: ctx,
							can_edit: !! ctx.can_edit,
						},
					} ) );
				}
			},
		},
	},

	mounted() {
		const contexts = window.aio_login__app_object.feature_contexts || {};
		if ( contexts[ this.feature ] ) {
			this.context = contexts[ this.feature ];
			return;
		}
		if ( 'true' !== ( window.aio_login__app_object.is_network || '' ) ) {
			return;
		}
		window.axios
			.get( 'aio-login/network/feature-context/' + this.feature )
			.then( ( response ) => {
				this.context = response.data;
			} )
			.catch( () => {} );
	},

	methods: {
		hasProPlugin() {
			const o = window.aio_login__app_object || {};
			return o.has_pro === true || o.has_pro === 'true';
		},
		isRegistryProFeature() {
			const map = ( window.aio_login__app_object || {} ).network_feature_is_pro;
			if ( ! map || ! this.feature || ! Object.prototype.hasOwnProperty.call( map, this.feature ) ) {
				return false;
			}
			return !! map[ this.feature ];
		},
		shouldHideForInactivePro() {
			if ( this.hasProPlugin() ) {
				return false;
			}
			return this.requireProActive || this.isRegistryProFeature();
		},
		onOverrideCheckboxChange( event ) {
			const input = event && event.target ? event.target : null;
			if ( ! input ) {
				return;
			}
			this.toggleOverride( !! input.checked );
		},
		toggleOverride( enabled ) {
			if ( this.saving ) {
				return;
			}
			this.saving = true;
			window.axios
				.post( 'aio-login/network/site-override', {
					feature: this.feature,
					enabled: !! enabled,
				} )
				.then( ( response ) => {
					this.context = response.data.context || this.context;
					this.saving = false;
					this.$emit( 'override-changed', this.context );
				} )
				.catch( () => {
					this.saving = false;
				} );
		},
	},
};
</script>

<style scoped>
.aio-login-network-tag-wrap {
	display: inline-flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px 10px;
	vertical-align: middle;
}

.aio-login-network-tag-wrap--inline {
	margin-left: 8px;
}

.aio-login-network-tag-wrap--bar {
	display: flex;
	width: 100%;
	margin: 0 0 16px;
	gap: 8px 12px;
	justify-content: flex-end;
	align-items: center;
}

.aio-login-network-tag {
	display: inline-flex;
	align-items: center;
	padding: 3px 10px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.03em;
	line-height: 1.4;
	text-transform: uppercase;
	white-space: nowrap;
}

.aio-login-network-tag--managed {
	background: #e8f0fe;
	color: #1d4ed8;
	border: 1px solid #bfdbfe;
}

.aio-login-network-tag--override {
	background: #fff7ed;
	color: #c2410c;
	border: 1px solid #fed7aa;
}

.aio-login-network-tag--override-on {
	background: #ecfdf5;
	color: #047857;
	border: 1px solid #a7f3d0;
}

.aio-login-network-tag__hint {
	font-size: 13px;
	color: #64748b;
	line-height: 1.4;
}

.aio-login-network-tag__override {
	display: inline-flex;
	align-items: center;
	gap: 10px;
}

.aio-login-network-tag-wrap--bar .aio-login-network-tag__override {
	margin-left: 0;
}

.aio-login-network-tag__override--inline {
	gap: 8px;
}

.aio-login-network-tag__override-label {
	font-size: 13px;
	font-weight: 500;
	color: #374151;
	line-height: 1.4;
}

.aio-login-network-tag__override-label--compact {
	font-size: 12px;
	font-weight: 500;
	color: #64748b;
}

.aio-login-network-tag__override-checkbox {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	margin: 0;
	cursor: pointer;
	line-height: normal;
}

.aio-login-network-tag__override-input {
	margin: 0;
	width: 16px;
	height: 16px;
	flex-shrink: 0;
	cursor: pointer;
}

.aio-login-network-tag__override-input:disabled {
	cursor: not-allowed;
	opacity: 0.6;
}
</style>
