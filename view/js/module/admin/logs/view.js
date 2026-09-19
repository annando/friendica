// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

(function(){
	// re-visiting this page via SPA re-runs this script (see
	// syncOutOfBandScripts), which would otherwise stack duplicate handlers
	if (window.__friendica_admin_logs_view_bound) {
		return;
	}
	window.__friendica_admin_logs_view_bound = true;

	function log_show_details(elm) {
		const id = elm.id;
		var hidden = true;
		document
			.querySelectorAll('[data-id="' + id + '"]')
			.forEach(edetails => {
				hidden = edetails.classList.toggle('hidden');
			});
		document
			.querySelectorAll('[aria-expanded="true"]')
			.forEach(eexpanded => {
				eexpanded.setAttribute('aria-expanded', false);
			});
		
		if (!hidden) {
			elm.setAttribute('aria-expanded', true);
		}
	}

	// delegated on document, since SPA mode replaces the table on every
	// search without re-running this script
	document.addEventListener("click", evt => {
		const elm = evt.target.closest('.log-event');
		if (elm) {
			log_show_details(elm);
		}
	});
	document.addEventListener("keydown", evt => {
		if (evt.keyCode == 13 || evt.keyCode == 32) {
			const elm = evt.target.closest('.log-event');
			if (elm) {
				log_show_details(elm);
			}
		}
	});
})();