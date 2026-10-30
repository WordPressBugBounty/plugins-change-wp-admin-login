
// Vue / vue-router come from aio-login-vue (vue-vendor.js) — shared with Pro.
// Use WordPress core jQuery (webpack external) — do not bundle jQuery.
window.Datatable = require('datatables.net-dt');
if (typeof window.Datatable === 'function' && window.jQuery) {
	window.Datatable(window, window.jQuery);
}
window.axios = require('axios');

(function () {
	var appObject = window.aio_login__app_object || {};
	var restUrl = appObject.rest_url || '/wp-json/';
	var restNonce = appObject.nonce || '';

	function setRestNonce(nonce) {
		if (!nonce) {
			return;
		}
		restNonce = nonce;
		if (window.aio_login__app_object) {
			window.aio_login__app_object.nonce = nonce;
		}
		if (window.axios && window.axios.defaults && window.axios.defaults.headers) {
			window.axios.defaults.headers['X-WP-Nonce'] = nonce;
			if (window.axios.defaults.headers.common) {
				window.axios.defaults.headers.common['X-WP-Nonce'] = nonce;
			}
		}
	}

	function refreshRestNonce() {
		var ajaxUrl = (window.aio_login__app_object && window.aio_login__app_object.ajax_url) || (window.ajaxurl || '');
		var adminNonce = (window.aio_login__app_object && window.aio_login__app_object.admin_nonce) || '';
		if (!ajaxUrl) {
			return Promise.reject(new Error('Missing ajax_url'));
		}
		var body = new URLSearchParams();
		body.set('action', 'aio_login_refresh_rest_nonce');
		body.set('nonce', adminNonce);
		return fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		}).then(function (res) {
			return res.json();
		}).then(function (json) {
			if (!json || !json.success || !json.data || !json.data.nonce) {
				throw new Error('Unable to refresh REST nonce');
			}
			setRestNonce(json.data.nonce);
			if (json.data.rest_url && window.aio_login__app_object) {
				window.aio_login__app_object.rest_url = json.data.rest_url;
				if (window.axios && window.axios.defaults) {
					window.axios.defaults.baseURL = json.data.rest_url;
				}
			}
			return json.data.nonce;
		});
	}

	window.axios = axios.create({
		baseURL: restUrl,
		timeout: 1000 * 10 * 6,
		withCredentials: true,
		headers: {
			'X-WP-Nonce': restNonce,
		},
		params: (
			appObject.is_network_admin === true
			|| appObject.is_network_admin === 'true'
		)
			? { aio_login_network_admin: '1' }
			: {},
	});

	window.axios.interceptors.request.use(function (config) {
		config.headers = config.headers || {};
		config.headers['X-WP-Nonce'] = restNonce || (window.aio_login__app_object && window.aio_login__app_object.nonce) || '';
		return config;
	});

	window.axios.interceptors.response.use(function (response) {
		var headers = response && response.headers ? response.headers : {};
		var fresh = headers['x-wp-nonce'] || headers['X-WP-Nonce'];
		if (fresh) {
			setRestNonce(fresh);
		}
		return response;
	}, function (error) {
		var response = error && error.response;
		var data = response && response.data;
		var config = error && error.config;
		var isNonceError = response && response.status === 403 && data && data.code === 'rest_cookie_invalid_nonce';

		if (!isNonceError || !config || config.__aioLoginNonceRetried) {
			return Promise.reject(error);
		}

		config.__aioLoginNonceRetried = true;
		return refreshRestNonce().then(function (nonce) {
			config.headers = config.headers || {};
			config.headers['X-WP-Nonce'] = nonce;
			return window.axios.request(config);
		}).catch(function () {
			return Promise.reject(error);
		});
	});
})();


window.aio_login__object = {
	tabs: aio_login__app_object.tabs,
	pages: [],
	routes: [],
};

try {
	window.aio_login__object['qrcode'] = window.aioLoginQRcode
} catch (e) { }

var tabs = Object.values(aio_login__object.tabs);
var current_location = window.location.href;
current_location = new URL(current_location);
current_location = new URLSearchParams(current_location.search);
var current_tab = current_location.get('tab');

if (null == current_tab) {
	current_tab = 'dashboard';
}

tabs.forEach(function (tab) {
	if (current_tab === tab.slug) {
		aio_login__object.pages.push(tab);
	}
});

aio_login__object.pages.forEach(function (page) {
	if (null == page['sub-tabs']) {
		// Handle main tabs without sub-tabs (like integrations, social-login, temp-access)
		if (!window.aio_login__object.routes.length) {
			window.aio_login__object.routes.push({
				path: '/',
				redirect: to => ({
					path: '/' + page.slug,
				}),
			});
		}
		window.aio_login__object.routes.push({
			path: '/' + page.slug,
			my_slug: page.slug,
		});
		return;
	}

	var sub_tabs = Object.values(page['sub-tabs']);
	sub_tabs.forEach(function (v, i) {
		if (!i) {
			window.aio_login__object.routes.push({
				path: '/',
				redirect: to => ({
					path: '/' + v.slug,
				}),
			});
		}

		window.aio_login__object.routes.push({
			path: '/' + v.slug,
			my_slug: v.slug,
		})
	});
});

if (window.aio_login__object.routes.length) {
	window.aio_login__object.routes.forEach(function (route, i) {
		var proPages = window.aio_login_pro_route_pages || {};

		function assignFreeOrPro(slug, freeFactory) {
			if (proPages[slug]) {
				window.aio_login__object.routes[i].component = proPages[slug];
				return;
			}
			if (typeof freeFactory === 'function') {
				window.aio_login__object.routes[i].component = freeFactory();
			}
		}

		if ('change-login-url' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/change-login-url.vue').default;
		}

		if ('limit-login-attempts' === route.my_slug) {
			assignFreeOrPro('limit-login-attempts', null);
		}

		if ('block-ip-addresses' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/block-ip-addresses.vue').default;
		}

		if ('disable-common-usernames' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/disable-common-usernames.vue').default;
		}

		if ('password-strenght-checker' === route.my_slug) {
			assignFreeOrPro('password-strenght-checker', null);
		}
		if ('login-redirection' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/login-redirection.vue').default;
		}

		if ('lockouts' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/lockouts.vue').default;
		}

		if ('failed-logins' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/failed-logins.vue').default;
		}

		if ('captcha' === route.my_slug) {
			assignFreeOrPro('captcha', function () {
				return require('./pages/grecaptcha.vue').default;
			});
		}

		if ('authentication-methods' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/2fa.vue').default;
		}

		if ('2fa-policies' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/2fa.vue').default;
		}

		if ('advanced-settings' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/2fa.vue').default;
		}

		if ('logo' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/logo.vue').default;
		}

		if ('background' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/background.vue').default;
		}

		if ('templates' === route.my_slug) {
			assignFreeOrPro('templates', null);
		}

		if ('social-login' === route.my_slug) {
			assignFreeOrPro('social-login', null);
		}

		if ('integrations' === route.my_slug) {
			assignFreeOrPro('integrations', null);
			// Keep Integrations settings route available after browser reload.
			// Pro component navigates to #/woocommerce-integrations internally.
			if (window.aio_login__object.routes[i].component && !window.aio_login__object.routes.some(function (r) {
				return r.path === '/woocommerce-integrations';
			})) {
				window.aio_login__object.routes.push({
					path: '/woocommerce-integrations',
					my_slug: 'integrations',
					component: window.aio_login__object.routes[i].component,
				});
			}
		}

		if ('temp-access' === route.my_slug) {
			assignFreeOrPro('temp-access', null);
		}

		if ('user-enumeration-protection' === route.my_slug) {
			window.aio_login__object.routes[i].component = require('./pages/user-enumeration-protection.vue').default;
		}

		if ('enumeration-protection-logs' === route.my_slug) {
			assignFreeOrPro('enumeration-protection-logs', null);
		}

		if ('elements' === route.my_slug) {
			assignFreeOrPro('elements', null);
		}

		if ('notifications' === route.my_slug) {
			assignFreeOrPro('notifications', null);
		}

		if ('otp-login' === route.my_slug) {
			assignFreeOrPro('otp-login', function () {
				return require('./pages/passwordless-otp-login.vue').default;
			});
		}

		if ('login-link' === route.my_slug) {
			assignFreeOrPro('login-link', null);
		}
	});
}


function aio_login_set_menu_position() {
	let current_url = window.location.href;
	let url = new URL(current_url);
	let searchParams = url.searchParams;
	let page, tab;
	page = searchParams.get('page');
	tab = searchParams.get('tab');

	if ('login-protection' === tab) {
		aio_login_active_menu(3);
	} else if ('2fa' === tab) {
		aio_login_active_menu(4);
	} else if ('passwordless-authentication' === tab) {
		aio_login_active_menu(5);
	} else if ('activity-log' === tab) {
		aio_login_active_menu(6);
	} else if ('security' === tab) {
		aio_login_active_menu(7);
	} else if ('temp-access' === tab) {
		aio_login_active_menu(8);
	} else if ('social-login' === tab) {
		aio_login_active_menu(9);
	} else if ('integrations' === tab) {
		aio_login_active_menu(10);
	} else if ('getpro' === tab) {
		aio_login_active_menu(11);
	} else {
		aio_login_active_menu(2);
	}
}

function aio_login_active_menu($index) {
	jQuery(`#toplevel_page_aio-login > ul > li`).removeClass('current');
	jQuery(`#toplevel_page_aio-login > ul > li:nth-child( ${$index} )`).addClass('current');
}

aio_login_set_menu_position();

require('./vue');


/**
 * This code is auto-generated and should not be modified
 */
