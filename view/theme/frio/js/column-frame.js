/**
 * @file view/theme/frio/js/column-frame.js
 * Behaviour of a page that is embedded as a column in the deck (mode=column).
 *
 * @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPLv3-or-later
 * SPDX-FileCopyrightText: 2010-2026 the Friendica project
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(function () {
	"use strict";

	if (window.parent === window) {
		return;
	}

	const MODE = "column";
	const noInterceptSelector = "[data-toggle], [data-fancybox], [data-remote], [data-gallery], [rel~=lightbox]";

	function withMode(url) {
		const parsed = new URL(url, document.baseURI);
		parsed.searchParams.set("mode", MODE);
		return parsed.href;
	}

	function post(message) {
		window.parent.postMessage(Object.assign({ friendicaColumns: true }, message), window.location.origin);
	}

	// Links stay in the column when they lead to the same module, otherwise they open in a new column.
	window.addEventListener("click", function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
			return;
		}

		const link = e.target.closest ? e.target.closest("a[href]") : null;
		if (!link || link.target || link.hasAttribute("download") || link.matches(noInterceptSelector)) {
			return;
		}

		const href = link.getAttribute("href");
		if (href.charAt(0) === "#" || /^(javascript|mailto|tel):/i.test(href)) {
			return;
		}

		const url = new URL(link.href, document.baseURI);
		e.preventDefault();

		if (url.origin !== window.location.origin) {
			window.open(url.href, "_blank", "noopener,noreferrer");
		} else if (url.pathname === window.location.pathname) {
			window.location.href = withMode(url.href);
		} else {
			url.searchParams.delete("mode");
			post({ action: "open", url: url.pathname + url.search });
		}
	});

	document.addEventListener("DOMContentLoaded", function () {
		// GET forms (filters, search) and the composer have to keep the column mode.
		document.querySelectorAll("form").forEach(function (form) {
			const action = form.getAttribute("action") || "";
			if ((form.method || "get").toLowerCase() === "get") {
				const input = document.createElement("input");
				input.type = "hidden";
				input.name = "mode";
				input.value = MODE;
				form.appendChild(input);
			} else if (/^compose(\/|\?|$)/.test(action)) {
				form.setAttribute("action", withMode(action));
			}
		});

		const params = new URLSearchParams(window.location.search);
		if (params.has("posted")) {
			params.delete("posted");
			const query = params.toString();
			window.history.replaceState(null, "", window.location.pathname + (query ? "?" + query : ""));
			post({ action: "posted" });
		}
	});
})();
