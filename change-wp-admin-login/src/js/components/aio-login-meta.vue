<template>
	<div class="aio-login__meta-container" ref="metaContainer">
		<div>
			<aio-login-metadata
				title="Custom Login URL"
				description="Attackers often try exploits on /wp-login or /wp-admin as a default login URL for WordPress. Change it to avoid these attacks and have an easily memorizable login URL."
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch aio-login-configuration-btn-wrapper">
						<a class="aio-login-configuration-btn" :href="adminURL + '&tab=login-protection'">{{ $t('Configure') }}</a>
					</div>
				</template>
			</aio-login-metadata>
		</div>
		<div>
			<aio-login-metadata
				title="Disable Common Usernames"
				description="Block commonly used or custom usernames to prevent brute-force attacks and strengthen login security."
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch aio-login-configuration-btn-wrapper">
						<a class="aio-login-configuration-btn" :href="adminURL + '&tab=login-protection#/disable-common-usernames'">{{ $t('Configure') }}</a>
					</div>
				</template>
			</aio-login-metadata>
		</div>
		<div>
			<aio-login-metadata
				title="Two Factor Authentication"
				description="Two-factor authentication adds a verification step after password login. Enable it here, then choose Email OTP (or Authenticator App with Pro) under 2FA → Authentication Methods."
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch">
						<div class="">
							<aio-login-toggle
								id="toggle-tfa"
								name="toggle-tfa"
								v-on:toggle-input="twoFactorAuthenticationSettings"
								:enabled="two_factor_auth"
							/>
						</div>
					</div>
				</template>
			</aio-login-metadata>
		</div>
		<div>
			<aio-login-metadata
				title="Block IP Address"
				description="By default your WordPress login can be accessed by any IP or user. You can use this feature to allow login only for specific IPs or users in order to prevent brute-force attacks or malicious login attempts."
			>
				<template v-slot:configuration>
					<div class="aio-login__configuration-switch">
						<div class="">
							<aio-login-toggle
								id="toggle-bipa"
								name="toggle-bipa"
								v-on:toggle-input="blockIPAddressSettings"
								:enabled="block_ip_address"
							/>
						</div>
					</div>
				</template>
			</aio-login-metadata>
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
	name: 'aio-login-meta',

	data: ( vm ) => ( {
		adminURL: aio_login__app_object.admin_url,

		two_factor_auth: false,
		block_ip_address: false,

		resizeTimer: null,

		snackbar: {
			message: 'Settings saved successfully',
			duration: 3000,
			show: false
		},
	} ),

	methods: {
		handleCloseSnackbar() {
			this.snackbar.show = false;
		},

		twoFactorAuthenticationSettings( i ) {
			var value = i ? 'on' : 'off';
			var previous = this.two_factor_auth;
			this.two_factor_auth = i;

			axios.post( 'aio-login/dashboard/update/two-factor-authentication', {
				value: value
			} )
				.then( response => {
					if ( 'success' === response.data.status	) {
						if ( i ) {
							window.location.href = aio_login__app_object.admin_url + '&tab=2fa#/authentication-methods';
						}
					} else {
						this.two_factor_auth = previous;
					}

					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				} )
				.catch( () => {
					this.two_factor_auth = previous;
				}  );
		},

		blockIPAddressSettings( i ) {
			var value = i ? 'on' : 'off';
			var previous = this.block_ip_address;
			this.block_ip_address = i;

			axios.post( 'aio-login/dashboard/update/block-ip-address', {
				value: value
			} )
				.then( response => {
					if ( 'success' === response.data.status	) {
						if ( i ) {
							window.location.href = aio_login__app_object.admin_url + '&tab=security#/block-ip-addresses';
						}
					} else {
						this.block_ip_address = previous;
					}

					this.snackbar.message = response.data.message;
					this.snackbar.show = true;
				} )
				.catch( () => {
					this.block_ip_address = previous;
				}  );
		},

		/** CSS Grid rows size independently, so match all 4 card heights to the tallest one via JS. */
		equalizeCardHeights() {
			const cards = this.$refs.metaContainer
				? this.$refs.metaContainer.querySelectorAll( '.aio-login__metadata-container' )
				: [];
			if ( ! cards.length ) {
				return;
			}
			cards.forEach( ( card ) => { card.style.minHeight = ''; } );
			const tallest = Math.max( ...Array.from( cards ).map( ( card ) => card.offsetHeight ) );
			cards.forEach( ( card ) => { card.style.minHeight = tallest + 'px'; } );
		},

		onResize() {
			clearTimeout( this.resizeTimer );
			this.resizeTimer = setTimeout( () => this.equalizeCardHeights(), 100 );
		},
	},

	mounted() {
		axios.get( 'aio-login/dashboard/get-settings' )
			.then( response => {
				this.two_factor_auth = response.data.two_factor_auth === 'on';
				this.block_ip_address = response.data.block_ip_address === 'on';
				this.$nextTick( () => this.equalizeCardHeights() );
			} )
			.catch( () => {} );

		window.addEventListener( 'resize', this.onResize );
		this.$nextTick( () => this.equalizeCardHeights() );
	},

	beforeUnmount() {
		window.removeEventListener( 'resize', this.onResize );
		clearTimeout( this.resizeTimer );
	},
}
</script>

<style scoped>
.aio-login__meta-container {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 15px;
}

@media screen and (max-width: 782px) {
	.aio-login__meta-container {
		grid-template-columns: 1fr;
		gap: 12px;
	}
}

.aio-login-configuration-btn-wrapper {
	background: #F7ECFD;
	border-radius: 4px;
	padding: 12px 24px;
}

.aio-login-configuration-btn {
	color: #9516DF;
	line-height: 14px;
	text-decoration: none;
	font-size: 16px;
	font-weight: 600;
}

.aio-login__configuration-switch {
	margin-top: auto;
	align-self: flex-end;
	padding-top: 16px;
	flex-shrink: 0;
}
</style>
