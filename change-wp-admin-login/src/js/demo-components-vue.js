import aioLoginBlockIpAddress from './demo/components/aio-login-ip-address.vue'
import aioLoginDisableCommonUsernames from './demo/components/aio-login-disable-common-usernames.vue';
import aioLoginTwoFactorAuthentication from './demo/components/aio-login-two-factor-authentication.vue';
import aioLoginCaptcha from './demo/components/aio-login-captcha.vue';

// Free-only feature UIs. Pro registers its own components when the Pro plugin is active.
window.aio_login__mount_helper_instance.mount( '#aio-login__app', {
	'aio-login-block-ip-address': aioLoginBlockIpAddress,
	'aio-login-disable-common-usernames': aioLoginDisableCommonUsernames,
	'aio-login-two-factor-authentication': aioLoginTwoFactorAuthentication,
	'aio-login-captcha': aioLoginCaptcha,
} );
