/* ConveyThis — WordPress deactivation churn survey.
 *
 * On the Plugins screen, intercepts THIS plugin's "Deactivate" link, asks the
 * merchant why, pings ConveyThis (so the reason lands in the same churn list as
 * dashboard/Shopify offs), then lets the normal deactivation continue. It never
 * blocks deactivation: closing the modal cancels (stays active); Skip or a
 * reason proceeds; any error still proceeds. Config comes from ConveyThisChurn
 * (wp_localize_script). PHP 7.4 era browsers — plain ES5, no build step. */
(function () {
	var CFG = window.ConveyThisChurn || {};
	if (!CFG.plugin) { return; }

	var reasons = CFG.reasons || [];
	var T = CFG.i18n || {};

	function esc(x) {
		return String(x).replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	function injectStyles() {
		if (document.getElementById('ctwpc-css')) { return; }
		var css = ''
			+ '.ctwpc-ov{position:fixed;inset:0;z-index:100050;display:flex;align-items:center;justify-content:center;background:rgba(17,20,28,.55);padding:16px}'
			+ '.ctwpc-mo{position:relative;width:100%;max-width:460px;background:#fff;color:#1d2327;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);font:14px/1.5 -apple-system,"Segoe UI",Roboto,sans-serif;overflow:hidden}'
			+ '.ctwpc-hd{padding:18px 20px 6px}'
			+ '.ctwpc-hd h3{margin:0 0 4px;font-size:17px;font-weight:600}'
			+ '.ctwpc-hd p{margin:0;color:#646970;font-size:13px}'
			+ '.ctwpc-bd{padding:8px 20px 4px;max-height:52vh;overflow:auto}'
			+ '.ctwpc-op{display:flex;align-items:center;gap:10px;padding:9px 10px;border:1px solid #dcdcde;border-radius:7px;margin:7px 0;cursor:pointer}'
			+ '.ctwpc-op:hover{background:#f6f7f7}'
			+ '.ctwpc-op input{margin:0}'
			+ '.ctwpc-op.sel{border-color:#2271b1;background:#f0f6fc}'
			+ '.ctwpc-cm{width:100%;box-sizing:border-box;margin:6px 0 2px;padding:9px 10px;border:1px solid #dcdcde;border-radius:7px;font:inherit;resize:vertical;min-height:58px}'
			+ '.ctwpc-ft{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 20px 18px}'
			+ '.ctwpc-skip{background:none;border:0;color:#646970;cursor:pointer;font:inherit;text-decoration:underline}'
			+ '.ctwpc-go{background:#2271b1;border:1px solid #2271b1;color:#fff;cursor:pointer;font:inherit;font-weight:600;padding:8px 16px;border-radius:5px}'
			+ '.ctwpc-go:hover{background:#135e96;border-color:#135e96}'
			+ '.ctwpc-go:disabled,.ctwpc-skip:disabled{opacity:.6;cursor:default}'
			+ '.ctwpc-x{position:absolute;top:8px;right:12px;border:0;background:none;font-size:22px;line-height:1;color:#8c8f94;cursor:pointer}';
		var s = document.createElement('style');
		s.id = 'ctwpc-css';
		s.textContent = css;
		document.head.appendChild(s);
	}

	function findLink() {
		var row = document.querySelector('tr[data-plugin="' + CFG.plugin + '"]');
		if (!row) { return null; }
		return row.querySelector('span.deactivate a, .deactivate a');
	}

	/* Fire the reason at ConveyThis and continue regardless. keepalive lets the
	   request outlive the page navigation that deactivation triggers. */
	function ping(reason, comment, done) {
		var proceeded = false;
		function once() { if (!proceeded) { proceeded = true; done(); } }
		try {
			var body = new FormData();
			body.append('action', 'conveythis_churn');
			body.append('nonce', CFG.nonce || '');
			body.append('reason', reason || '');
			body.append('comment', comment || '');
			if (window.fetch) {
				window.fetch(CFG.ajaxUrl, { method: 'POST', credentials: 'same-origin', keepalive: true, body: body })
					.then(once, once);
			}
		} catch (e) { /* fall through to the timer */ }
		setTimeout(once, 1200); // never wait on the network to deactivate
	}

	function openModal(href) {
		injectStyles();
		var ov = document.createElement('div');
		ov.className = 'ctwpc-ov';
		var selected = '';
		var opts = reasons.map(function (r) {
			return '<label class="ctwpc-op" data-k="' + esc(r[0]) + '">'
				+ '<input type="radio" name="ctwpc-r" value="' + esc(r[0]) + '">'
				+ '<span>' + esc(r[1]) + '</span></label>';
		}).join('');

		ov.innerHTML =
			'<div class="ctwpc-mo" role="dialog" aria-modal="true">'
			+ '<button type="button" class="ctwpc-x" aria-label="Close">&times;</button>'
			+ '<div class="ctwpc-hd"><h3>' + esc(T.title || 'Before you deactivate') + '</h3>'
			+ '<p>' + esc(T.subtitle || "What's the main reason? (optional)") + '</p></div>'
			+ '<div class="ctwpc-bd">' + opts
			+ '<textarea class="ctwpc-cm" placeholder="' + esc(T.placeholder || 'Anything to add? (optional)') + '"></textarea>'
			+ '</div>'
			+ '<div class="ctwpc-ft"><button type="button" class="ctwpc-skip">' + esc(T.skip || 'Skip') + '</button>'
			+ '<button type="button" class="ctwpc-go">' + esc(T.submit || 'Deactivate') + '</button></div>'
			+ '</div>';
		document.body.appendChild(ov);

		var left = false;
		function proceed() { if (left) { return; } left = true; window.location.href = href; }
		function remove() { if (ov.parentNode) { ov.parentNode.removeChild(ov); } document.removeEventListener('keydown', onKey); }
		function cancel() { remove(); } // reconsidered — stay active
		function onKey(e) { if (e.key === 'Escape') { cancel(); } }
		document.addEventListener('keydown', onKey);
		ov.addEventListener('click', function (e) { if (e.target === ov) { cancel(); } });
		ov.querySelector('.ctwpc-x').addEventListener('click', cancel);

		Array.prototype.forEach.call(ov.querySelectorAll('.ctwpc-op'), function (op) {
			op.addEventListener('click', function () {
				selected = op.getAttribute('data-k');
				Array.prototype.forEach.call(ov.querySelectorAll('.ctwpc-op'), function (o) { o.classList.remove('sel'); });
				op.classList.add('sel');
				var inp = op.querySelector('input');
				if (inp) { inp.checked = true; }
			});
		});

		ov.querySelector('.ctwpc-skip').addEventListener('click', function () {
			this.disabled = true;
			ov.querySelector('.ctwpc-go').disabled = true;
			ping('', '', function () { remove(); proceed(); });
		});
		ov.querySelector('.ctwpc-go').addEventListener('click', function () {
			var cm = ov.querySelector('.ctwpc-cm');
			this.disabled = true;
			ov.querySelector('.ctwpc-skip').disabled = true;
			ping(selected, cm ? cm.value.trim() : '', function () { remove(); proceed(); });
		});
	}

	function init() {
		var a = findLink();
		if (!a) { return; }
		a.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			openModal(a.href);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
