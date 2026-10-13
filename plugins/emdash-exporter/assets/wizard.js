/**
 * EmDash migration wizard: key generation, copy, revoke.
 * Vanilla JS, no dependencies.
 */
(function () {
	"use strict";

	var cfg = window.emdashWizard || {};
	var generateBtn = document.getElementById("emdash-generate-key");
	var revokeBtn = document.getElementById("emdash-revoke-key");
	var result = document.getElementById("emdash-key-result");
	var keyInput = document.getElementById("emdash-key-value");
	var copyBtn = document.getElementById("emdash-copy-key");
	var errorBox = document.getElementById("emdash-key-error");
	var existsNote = document.getElementById("emdash-key-exists");

	function post(action) {
		var body = new URLSearchParams();
		body.set("action", action);
		body.set("nonce", cfg.nonce);
		return fetch(cfg.ajaxUrl, {
			method: "POST",
			credentials: "same-origin",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: body.toString(),
		}).then(function (response) {
			return response.json();
		});
	}

	function showError(message) {
		if (!errorBox) return;
		errorBox.querySelector("p").textContent = message || cfg.i18n.error;
		errorBox.hidden = false;
	}

	if (generateBtn) {
		generateBtn.addEventListener("click", function () {
			generateBtn.disabled = true;
			var originalText = generateBtn.textContent;
			generateBtn.textContent = cfg.i18n.generating;
			errorBox.hidden = true;

			post("emdash_generate_key")
				.then(function (data) {
					if (data && data.success && data.data && data.data.key) {
						keyInput.value = data.data.key;
						result.hidden = false;
						originalText = cfg.i18n.regenerate;
						if (revokeBtn) revokeBtn.hidden = false;
						if (existsNote) existsNote.hidden = true;
						keyInput.focus();
						keyInput.select();
					} else {
						showError(data && data.data && data.data.message);
					}
				})
				.catch(function () {
					showError();
				})
				.finally(function () {
					generateBtn.disabled = false;
					generateBtn.textContent = originalText;
				});
		});
	}

	if (copyBtn) {
		copyBtn.addEventListener("click", function () {
			keyInput.select();
			var done = function () {
				copyBtn.textContent = cfg.i18n.copied;
				setTimeout(function () {
					copyBtn.textContent = cfg.i18n.copy;
				}, 2000);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(keyInput.value).then(done);
			} else {
				document.execCommand("copy");
				done();
			}
		});
	}

	if (revokeBtn) {
		revokeBtn.addEventListener("click", function () {
			if (!window.confirm(cfg.i18n.revokeConfirm)) return;
			revokeBtn.disabled = true;
			post("emdash_revoke_key")
				.then(function () {
					window.location.reload();
				})
				.catch(function () {
					revokeBtn.disabled = false;
					showError();
				});
		});
	}
})();
