(function () {
	var C = window.SCB, COOKIE = 'scb_consent', DAY = 864e5;
	if (!C) return;

	function readConsent() {
		var m = document.cookie.match(new RegExp('(?:^|; )' + COOKIE + '=([^;]*)'));
		if (!m) return null;
		try {
			var d = JSON.parse(decodeURIComponent(m[1]));
			return d.v === C.version ? d : null;
		} catch (e) { return null; }
	}

	function writeConsent(analytics) {
		var exp = new Date(Date.now() + 365 * DAY).toUTCString();
		document.cookie = COOKIE + '=' + encodeURIComponent(JSON.stringify({ v: C.version, a: analytics ? 1 : 0, t: Date.now() })) +
			'; expires=' + exp + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
	}

	function deleteGaCookies() {
		var host = location.hostname.split('.'), domains = [location.hostname];
		for (var i = 1; i < host.length - 1; i++) domains.push('.' + host.slice(i).join('.'));
		document.cookie.split('; ').forEach(function (c) {
			var name = c.split('=')[0];
			if (name !== '_ga' && name.indexOf('_ga_') !== 0 && name !== '_gid') return;
			domains.forEach(function (d) {
				document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=' + d;
			});
			document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
		});
	}

	// Tell the WP Consent API (Site Kit etc.) about the choice.
	function syncConsentApi(analytics) {
		if (typeof window.wp_set_consent !== 'function') return;
		window.wp_set_consent('functional', 'allow');
		window.wp_set_consent('statistics', analytics ? 'allow' : 'deny');
		window.wp_set_consent('statistics-anonymous', analytics ? 'allow' : 'deny');
		window.wp_set_consent('preferences', 'deny');
		window.wp_set_consent('marketing', 'deny');
	}

	var analyticsLoaded = false;
	function loadAnalytics() {
		if (analyticsLoaded) return;
		analyticsLoaded = true;
		if (C.gaId) {
			window.dataLayer = window.dataLayer || [];
			window.gtag = function () { window.dataLayer.push(arguments); };
			window.gtag('js', new Date());
			window.gtag('config', C.gaId);
			var s = document.createElement('script');
			s.async = true;
			s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(C.gaId);
			document.head.appendChild(s);
		}
		document.querySelectorAll('script[type="text/plain"][data-scb-category="analytics"]').forEach(function (el) {
			var s = document.createElement('script');
			s.textContent = el.textContent;
			el.parentNode.replaceChild(s, el);
		});
	}

	function el(tag, attrs, children) {
		var e = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) { e.setAttribute(k, attrs[k]); });
		(children || []).forEach(function (c) { e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
		return e;
	}

	var bar, modal, lastFocus;

	function decide(analytics) {
		var had = readConsent();
		writeConsent(analytics);
		syncConsentApi(analytics);
		closeModal(true);
		if (bar) { bar.remove(); bar = null; }
		if (analytics) loadAnalytics();
		else if (analyticsLoaded || (had && had.a)) { deleteGaCookies(); location.reload(); }
	}

	function privacyLink() {
		return C.privacyUrl ? [' ', el('a', { href: C.privacyUrl }, ['Datenschutzerklärung'])] : [];
	}

	function showBar() {
		if (bar) return;
		var p = el('p', { id: 'scb-text' }, [C.text].concat(privacyLink()));
		var accept = el('button', { type: 'button', 'class': 'scb-btn scb-primary' }, ['Akzeptieren']);
		var settings = el('button', { type: 'button', 'class': 'scb-btn scb-link' }, ['Einstellungen']);
		accept.onclick = function () { decide(true); };
		settings.onclick = openModal;
		bar = el('div', { id: 'scb-bar', role: 'region', 'aria-label': 'Cookie-Hinweis' }, [p, el('div', { 'class': 'scb-actions' }, [settings, accept])]);
		document.body.appendChild(bar);
	}

	function openModal(e) {
		if (e && e.preventDefault) e.preventDefault();
		if (modal) return;
		lastFocus = document.activeElement;
		var cur = readConsent();

		var cb = el('input', { type: 'checkbox', id: 'scb-analytics' });
		cb.checked = !!(cur && cur.a);
		var necessary = el('input', { type: 'checkbox', checked: '', disabled: '' });

		var save = el('button', { type: 'button', 'class': 'scb-btn scb-primary' }, ['Auswahl speichern']);
		var rejectAll = el('button', { type: 'button', 'class': 'scb-btn' }, ['Alle ablehnen']);
		var acceptAll = el('button', { type: 'button', 'class': 'scb-btn' }, ['Alle akzeptieren']);
		var close = el('button', { type: 'button', 'class': 'scb-close', 'aria-label': 'Schliessen' }, ['×']);
		save.onclick = function () { decide(cb.checked); };
		rejectAll.onclick = function () { decide(false); };
		acceptAll.onclick = function () { decide(true); };
		close.onclick = function () { closeModal(); };

		var box = el('div', { 'class': 'scb-box', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'scb-title' }, [
			close,
			el('h2', { id: 'scb-title' }, ['Cookie-Einstellungen']),
			el('label', { 'class': 'scb-row' }, [necessary, el('span', {}, [el('strong', {}, ['Notwendig']), el('br'), 'Für den Betrieb des Shops (Warenkorb, Login, Einwilligung). Immer aktiv.'])]),
			el('label', { 'class': 'scb-row' }, [cb, el('span', {}, [el('strong', {}, ['Analyse']), el('br'), 'Google Analytics: hilft uns zu verstehen, wie die Website genutzt wird.'])]),
			el('div', { 'class': 'scb-actions' }, [rejectAll, acceptAll, save])
		]);
		modal = el('div', { id: 'scb-modal' }, [box]);
		modal.addEventListener('mousedown', function (ev) { if (ev.target === modal) closeModal(); });
		modal.addEventListener('keydown', trapKeys);
		document.body.appendChild(modal);
		cb.focus();
	}

	function closeModal(skipFocus) {
		if (!modal) return;
		modal.remove();
		modal = null;
		if (!skipFocus && lastFocus && document.contains(lastFocus)) lastFocus.focus();
	}

	function trapKeys(ev) {
		if (ev.key === 'Escape') { closeModal(); return; }
		if (ev.key !== 'Tab') return;
		var f = modal.querySelectorAll('button, input:not([disabled]), a[href]');
		var first = f[0], last = f[f.length - 1];
		if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
		else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
	}

	document.addEventListener('click', function (ev) {
		var a = ev.target.closest && ev.target.closest('.scb-open, a[href="#cookie-einstellungen"]');
		if (a) openModal(ev);
	});

	var consent = readConsent();
	if (!consent) showBar();
	else {
	syncConsentApi(!!consent.a);
	if (consent.a) loadAnalytics();
}
})();
