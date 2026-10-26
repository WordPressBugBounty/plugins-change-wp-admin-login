/**
 * Remove leftover reCAPTCHA DOM nodes injected outside Vue mounts.
 * Prevents Google reCAPTCHA error chrome from persisting across modals (AIOL-656).
 *
 * Free ships reCAPTCHA only. Pro providers use Pro's captcha-dom-cleanup.js.
 *
 * @param {string} provider recaptcha|all
 * @param {HTMLElement|null} keepInside Optional mount to preserve.
 */
export function cleanupCaptchaProviderDom( provider, keepInside ) {
	const selectorMap = {
		recaptcha: [
			'iframe[src*="google.com/recaptcha"]',
			'iframe[src*="recaptcha.net"]',
			'iframe[src*="gstatic.com/recaptcha"]',
			'iframe[title="reCAPTCHA"]',
			'.grecaptcha-badge',
			'div.g-recaptcha',
			'textarea.g-recaptcha-response',
			'textarea[name="g-recaptcha-response"]',
		],
	};

	const providers = 'all' === provider
		? Object.keys( selectorMap )
		: [ provider ];

	providers.forEach( ( slug ) => {
		const selectors = selectorMap[ slug ] || [];
		selectors.forEach( ( selector ) => {
			try {
				document.querySelectorAll( selector ).forEach( ( node ) => {
					if ( keepInside && keepInside.contains( node ) ) {
						return;
					}
					if ( node.closest && node.closest( '.aio-login-captcha-verify__widget' ) ) {
						return;
					}
					removeCaptchaNode( node, keepInside );
				} );
			} catch ( e ) {}
		} );
	} );

	if ( 'all' === provider || 'recaptcha' === provider ) {
		removeRecaptchaErrorBoxes( keepInside );
	}
}

/**
 * Soft modal switch cleanup: remove leftover DOM only.
 *
 * @param {string[]} keepProviders Providers that must keep their scripts.
 */
export function cleanupForeignCaptchaDom( keepProviders ) {
	keepProviders = keepProviders || [];
	[ 'recaptcha' ].forEach( ( provider ) => {
		if ( keepProviders.indexOf( provider ) === -1 ) {
			cleanupCaptchaProviderDom( provider );
		}
	} );
}

/**
 * @param {Node} node
 * @param {HTMLElement|null} keepInside
 */
function removeCaptchaNode( node, keepInside ) {
	if ( ! node || ! node.parentNode ) {
		return;
	}

	if ( keepInside && ( keepInside === node || keepInside.contains( node ) ) ) {
		return;
	}

	let root = node;
	for ( let i = 0; i < 3; i++ ) {
		const parent = root.parentNode;
		if ( ! parent || parent === document.body || parent === document.documentElement ) {
			break;
		}
		if ( keepInside && ( keepInside === parent || keepInside.contains( parent ) ) ) {
			break;
		}
		const style = parent.getAttribute && parent.getAttribute( 'style' );
		const looksLikeOverlay = style && (
			style.indexOf( 'position: absolute' ) !== -1 ||
			style.indexOf( 'position:absolute' ) !== -1 ||
			style.indexOf( 'position: fixed' ) !== -1 ||
			style.indexOf( 'position:fixed' ) !== -1
		);
		if ( looksLikeOverlay && parent.childNodes && parent.childNodes.length <= 2 ) {
			root = parent;
			continue;
		}
		break;
	}

	root.remove();
}

/**
 * @param {HTMLElement|null} keepInside
 */
function removeRecaptchaErrorBoxes( keepInside ) {
	const candidates = document.querySelectorAll( 'body > div, body > div > div' );
	candidates.forEach( ( el ) => {
		if ( keepInside && keepInside.contains( el ) ) {
			return;
		}
		if ( el.closest && el.closest( '.popup-overlay, #aio-login__app' ) ) {
			return;
		}
		const text = ( el.textContent || '' ).trim();
		if ( ! text ) {
			return;
		}
		const isErrorHeader = (
			0 === text.indexOf( 'ERROR for site owner' ) ||
			text.indexOf( 'Invalid domain for site key' ) !== -1 ||
			text.indexOf( 'Invalid site key' ) !== -1
		);
		if ( ! isErrorHeader ) {
			return;
		}
		if ( el.children && el.children.length > 2 ) {
			return;
		}
		removeCaptchaNode( el, keepInside );
	} );
}

export function purgeCaptchaProviderGlobal( provider ) {
	if ( 'recaptcha' === provider ) {
		try {
			delete window.grecaptcha;
		} catch ( e ) {
			window.grecaptcha = undefined;
		}
		try {
			delete window.___grecaptcha_cfg;
		} catch ( e ) {
			window.___grecaptcha_cfg = undefined;
		}
	}
}

/**
 * Remove provider script tags from the page.
 *
 * @param {string} provider recaptcha
 */
export function removeCaptchaProviderScripts( provider ) {
	const patterns = {
		recaptcha: [ 'www.google.com/recaptcha', 'www.gstatic.com/recaptcha' ],
	};
	( patterns[ provider ] || [] ).forEach( ( pattern ) => {
		document.querySelectorAll( 'script[src*="' + pattern + '"]' ).forEach( ( script ) => {
			script.remove();
		} );
	} );
}

/**
 * Tear down a provider so it cannot pollute another modal (scripts + globals + DOM).
 *
 * @param {string} provider
 */
export function unloadCaptchaProvider( provider ) {
	cleanupCaptchaProviderDom( provider );
	removeCaptchaProviderScripts( provider );
	purgeCaptchaProviderGlobal( provider );
}

/**
 * Keep only the active provider bootstrapped.
 *
 * @param {string} activeProvider
 * @param {HTMLElement|null} keepInside Optional mount to preserve for the active provider.
 */
export function isolateActiveCaptchaProvider( activeProvider, keepInside ) {
	[ 'recaptcha' ].forEach( ( provider ) => {
		if ( provider !== activeProvider ) {
			unloadCaptchaProvider( provider );
		} else {
			cleanupCaptchaProviderDom( provider, keepInside || null );
		}
	} );
}

/**
 * Soft reset for modal open/close — DOM leftovers only.
 */
export function resetCaptchaEnvironment() {
	cleanupCaptchaProviderDom( 'all' );
}
