/**
 * Network Settings helpers for AIO Login Free.
 */
(function () {
	'use strict';

	function movePoliciesWrap() {
		var wrap = document.getElementById('aio-login-network-policies-wrap');
		if (!wrap) {
			return;
		}
		var form = document.querySelector('.wrap form[action="settings.php"], .wrap form');
		var headings = document.querySelectorAll('.wrap form > h2, .wrap > form > h2');
		var newSiteHeading = null;
		headings.forEach(function (h) {
			if (/new site settings/i.test((h.textContent || '').trim()) && !wrap.contains(h)) {
				newSiteHeading = h;
			}
		});
		if (newSiteHeading && newSiteHeading.parentNode) {
			newSiteHeading.parentNode.insertBefore(wrap, newSiteHeading);
		} else if (form && !form.contains(wrap)) {
			var submit = form.querySelector('p.submit');
			form.insertBefore(wrap, submit || null);
		}
		if (form) {
			form.addEventListener('submit', function () {
				wrap.querySelectorAll('select, input').forEach(function (el) {
					el.disabled = false;
				});
			});
		}
	}

	function syncCategoryTables() {
		var select = document.getElementById('aio_login_feature_category');
		if (!select) {
			return;
		}
		function sync() {
			var cat = select.value;
			document.querySelectorAll('.aio-login-network-policy-table').forEach(function (table) {
				table.style.display = table.getAttribute('data-category') === cat ? '' : 'none';
			});
		}
		select.addEventListener('change', sync);
		sync();
	}

	function syncMasterToggle() {
		var input = document.getElementById('aio_login_network_level_enabled');
		var status = document.getElementById('aio_login_network_level_status');
		var section = document.getElementById('aio-login-site-override-section');
		if (!input) {
			return;
		}
		var cfg = window.aioLoginNetworkSettings || {};
		var enabledText = cfg.enabledText || '';
		var disabledText = cfg.disabledText || '';
		function sync() {
			if (status) {
				status.textContent = input.checked ? enabledText : disabledText;
			}
			if (section) {
				section.style.opacity = input.checked ? '1' : '0.55';
				section.style.pointerEvents = input.checked ? '' : 'none';
			}
		}
		input.addEventListener('change', sync);
		sync();
	}

	document.addEventListener('DOMContentLoaded', function () {
		var cfg = window.aioLoginNetworkSettings || {};
		if (cfg.moveWrap) {
			movePoliciesWrap();
		}
		syncCategoryTables();
		syncMasterToggle();
	});
})();
