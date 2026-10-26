/**
 * Admin UI translator — uses PHP-localized map (aio_login__app_object.i18n).
 *
 * Prefer:
 * - Templates: {{ $t('…') }} or {{ __('…') }} (registered globally in vue.js)
 * - Scripts: import { t } from '../i18n.js' then t('…')
 *
 * @param {string} text English source string.
 * @return {string}
 */
function lookupInMap( map, text ) {
	if ( ! map || typeof text !== 'string' || text === '' ) {
		return null;
	}

	if ( Object.prototype.hasOwnProperty.call( map, text ) ) {
		const translated = map[ text ];
		// Identity entries (Admin_I18n without loaded .mo) are not real hits.
		if ( typeof translated === 'string' && translated !== '' && translated !== text ) {
			return translated;
		}
	}

	// Legacy catalogs sometimes stored HTML entities in msgids.
	const encoded = text
		.replace( /&/g, '&amp;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&apos;' );
	if ( encoded !== text && Object.prototype.hasOwnProperty.call( map, encoded ) ) {
		const translated = map[ encoded ];
		if ( typeof translated === 'string' && translated !== '' && translated !== encoded ) {
			return translated
				.replace( /&quot;/g, '"' )
				.replace( /&apos;/g, "'" )
				.replace( /&amp;/g, '&' );
		}
	}

	return null;
}

/**
 * Tooltips pass HTML like <p>…</p>, but .mo catalogs often store plain msgid.
 *
 * @param {string} text
 * @return {{ inner: string, wrap: function(string): string }|null}
 */
function unwrapTooltipHtml( text ) {
	const trimmed = text.trim();
	const match = trimmed.match( /^<p>([\s\S]*)<\/p>$/i );
	if ( ! match ) {
		return null;
	}
	return {
		inner: match[1],
		wrap: ( translated ) => {
			// Avoid double-wrapping if catalog already returned HTML.
			if ( /^<p[\s>]/i.test( translated.trim() ) ) {
				return translated;
			}
			return `<p>${ translated }</p>`;
		},
	};
}

export function __( text ) {
	if ( typeof text !== 'string' || text === '' ) {
		return text;
	}

	const map = ( typeof window !== 'undefined'
		&& window.aio_login__app_object
		&& window.aio_login__app_object.i18n
		&& typeof window.aio_login__app_object.i18n === 'object' )
		? window.aio_login__app_object.i18n
		: null;

	if ( ! map ) {
		return text;
	}

	const direct = lookupInMap( map, text );
	if ( direct !== null ) {
		return direct;
	}

	const unwrapped = unwrapTooltipHtml( text );
	if ( unwrapped ) {
		const candidates = [ unwrapped.inner ];
		const decoded = unwrapped.inner
			.replace( /&quot;/g, '"' )
			.replace( /&apos;/g, "'" )
			.replace( /&#39;/g, "'" )
			.replace( /&amp;/g, '&' );
		if ( decoded !== unwrapped.inner ) {
			candidates.push( decoded );
		}
		for ( const candidate of candidates ) {
			const innerHit = lookupInMap( map, candidate );
			if ( innerHit !== null ) {
				return unwrapped.wrap( innerHit );
			}
		}
	}

	return text;
}

export const t = __;

if ( typeof window !== 'undefined' ) {
	window.aioLoginTranslate = __;
}

export default __;
