(function () {
	'use strict';

	function insertAfter(node, reference) {
		if (!reference || !reference.parentNode) {
			return;
		}
		if (reference.nextSibling) {
			reference.parentNode.insertBefore(node, reference.nextSibling);
		} else {
			reference.parentNode.appendChild(node);
		}
	}

	/**
	 * Row immediately above Log In / Register (captcha sits directly above it).
	 */
	function getSubmitRow(form) {
		var btn = form.querySelector(
			'.woocommerce-form-login__submit, .woocommerce-form-register__submit, #wp-submit, p.submit input[type="submit"]'
		);
		if (btn) {
			var row = btn.closest('p');
			if (row && row.parentNode === form) {
				return row;
			}
		}
		return form.querySelector('p.submit');
	}

	/**
	 * Move captcha to the bottom: after OTP → Login Link → Social, right above submit.
	 */
	function positionLoginFormCaptchas() {
		var forms = document.querySelectorAll('form#loginform, form.woocommerce-form-login, form.woocommerce-form-register');
		forms.forEach(function (form) {
			var selectors = [
				'input[name="cf-turnstile-error-code"]',
				'.cf-turnstile',
				'.aio-login-turnstile-widget-error',
				'.h-captcha',
				'.g-recaptcha',
				'#g-recaptcha-response',
			];

			var nodes = [];
			selectors.forEach(function (sel) {
				form.querySelectorAll(sel).forEach(function (el) {
					if (el.closest('#aio-login-otp-panel, #aio-login-magic-link-panel')) {
						return;
					}
					if (nodes.indexOf(el) === -1) {
						nodes.push(el);
					}
				});
			});

			if (!nodes.length) {
				return;
			}

			var submitRow = getSubmitRow(form);
			if (!submitRow) {
				return;
			}

			nodes.forEach(function (node) {
				if (node.parentNode !== form) {
					return;
				}
				form.insertBefore(node, submitRow);
			});
		});
	}

	function schedulePosition() {
		positionLoginFormCaptchas();
		setTimeout(positionLoginFormCaptchas, 0);
		setTimeout(positionLoginFormCaptchas, 150);
		setTimeout(positionLoginFormCaptchas, 500);
		setTimeout(positionLoginFormCaptchas, 800);
	}

	window.aioLoginPositionFormCaptchas = positionLoginFormCaptchas;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', schedulePosition);
	} else {
		schedulePosition();
	}
})();
