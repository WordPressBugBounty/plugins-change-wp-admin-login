/**
 * Multisite CAPTCHA card UX (aligned with Social Login cards).
 */

export function shouldShowCaptchaConfigureButton( { requiresPro, hasPro, enabled } ) {
	if ( requiresPro && ! hasPro ) {
		return false;
	}
	return !! enabled;
}

/**
 * Network Admin edits network defaults — same as Social Login cards.
 *
 * @return {boolean}
 */
export function isNetworkAdminContext() {
	const o = ( typeof window !== 'undefined' && window.aio_login__app_object )
		? window.aio_login__app_object
		: {};
	return (
		o.is_network_admin === true
		|| o.is_network_admin === 'true'
		|| o.is_network_admin === 1
		|| o.is_network_admin === '1'
	);
}

/**
 * Configure stays clickable under Site Override Allowed (even when toggle is locked).
 * Only Network Managed disables the card Configure button on site screens.
 *
 * @param {string} networkPolicy Feature policy from network context.
 * @return {boolean}
 */
export function isCaptchaConfigureDisabledByNetwork( networkPolicy ) {
	if ( isNetworkAdminContext() ) {
		return false;
	}
	return 'network_managed' === networkPolicy;
}

/**
 * @param {string} storedPolicy Optional policy cached on the card component.
 * @return {string}
 */
export function resolveCaptchaNetworkPolicy( storedPolicy ) {
	if ( storedPolicy ) {
		return storedPolicy;
	}
	const contexts = ( typeof window !== 'undefined' && window.aio_login__app_object && window.aio_login__app_object.feature_contexts )
		? window.aio_login__app_object.feature_contexts
		: {};
	const ctx = contexts.captcha;
	return ( ctx && ctx.policy ) ? ctx.policy : '';
}
