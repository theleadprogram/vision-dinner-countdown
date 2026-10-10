/* LEAD Vision Dinner: helpers shared by the registration and guest pages. */
(function () {
	'use strict';

	var cfg = window.LEAD_VD || {};

	/* 100vw includes a desktop scrollbar; the full-bleed page subtracts it. */
	function measureScrollbar() {
		var sb = window.innerWidth - document.documentElement.clientWidth;
		document.documentElement.style.setProperty('--lvd-scrollbar', Math.max(0, sb) + 'px');
	}
	measureScrollbar();
	window.addEventListener('resize', measureScrollbar);

	/** POST/GET to /wp-json/lead-dinner/v1/… Resolves { ok, status, data }. */
	function api(path, body) {
		var opts = { method: body ? 'POST' : 'GET', headers: { Accept: 'application/json' }, credentials: 'omit' };
		if (body) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(body);
		}
		return fetch(cfg.api + path, opts).then(function (res) {
			return res.json().catch(function () { return {}; }).then(function (data) {
				return { ok: res.ok, status: res.status, data: data || {} };
			});
		}, function () {
			return { ok: false, status: 0, data: {} };
		});
	}

	/** (330) 555-0100 from whatever was typed. A leading 1 is dropped. */
	function formatPhone(value) {
		var d = String(value || '').replace(/\D/g, '');
		if (d.length === 11 && d.charAt(0) === '1') d = d.slice(1);
		d = d.slice(0, 10);
		if (d.length < 4) return d.length ? '(' + d : '';
		if (d.length < 7) return '(' + d.slice(0, 3) + ') ' + d.slice(3);
		return '(' + d.slice(0, 3) + ') ' + d.slice(3, 6) + '-' + d.slice(6);
	}

	function phoneDigits(value) {
		var d = String(value || '').replace(/\D/g, '');
		if (d.length === 11 && d.charAt(0) === '1') d = d.slice(1);
		return d.slice(0, 10);
	}

	/** Format tel inputs as you type; deleting works normally. */
	function bindPhone(root) {
		root.addEventListener('input', function (e) {
			var el = e.target;
			if (!el.matches || !el.matches('input[type="tel"]')) return;
			if (/delete/i.test(e.inputType || '')) return;
			el.value = formatPhone(el.value);
		});
	}

	var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

	/** Read named inputs into an object (checkboxes as booleans). */
	function values(form) {
		var out = {};
		Array.prototype.forEach.call(form.querySelectorAll('input[name]'), function (el) {
			if (el.type === 'radio') {
				if (el.checked) out[el.name] = el.value;
			} else if (el.type === 'checkbox') {
				out[el.name] = el.checked;
			} else {
				out[el.name] = el.value.trim();
			}
		});
		return out;
	}

	/** Show inline errors ({ field: message }); scroll to and focus the first. */
	function showErrors(form, errors) {
		clearErrors(form);
		var first = null;
		Object.keys(errors).forEach(function (name) {
			var box = form.querySelector('[data-error-for="' + name + '"]');
			var input = form.querySelector('[name="' + name + '"]') || (name === 'leader' ? form.querySelector('[data-combo-input]') : null);
			if (box) {
				box.textContent = errors[name];
				box.hidden = false;
			}
			if (input) {
				input.classList.add('is-invalid');
				input.setAttribute('aria-invalid', 'true');
				if (box) {
					box.id = box.id || 'lvd-err-' + Math.random().toString(36).slice(2);
					input.setAttribute('aria-describedby', box.id);
				}
				if (!first || (first.compareDocumentPosition(input) & Node.DOCUMENT_POSITION_PRECEDING)) first = input;
			}
		});
		if (first) {
			first.scrollIntoView({ behavior: 'smooth', block: 'center' });
			setTimeout(function () { first.focus({ preventScroll: true }); }, 300);
		}
	}

	function clearErrors(form) {
		Array.prototype.forEach.call(form.querySelectorAll('[data-error-for]'), function (box) {
			box.hidden = true;
			box.textContent = '';
		});
		Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'), function (el) {
			el.classList.remove('is-invalid');
			el.removeAttribute('aria-invalid');
			el.removeAttribute('aria-describedby');
		});
	}

	/** Clear one field's error as soon as it's edited. */
	function bindErrorClearing(form) {
		form.addEventListener('input', function (e) {
			var el = e.target;
			if (!el.classList || !el.classList.contains('is-invalid')) return;
			el.classList.remove('is-invalid');
			el.removeAttribute('aria-invalid');
			var box = form.querySelector('[data-error-for="' + el.name + '"]');
			if (box) box.hidden = true;
		});
	}

	/** Disable a button and show a spinner. Returns a function that restores it. */
	function busy(button) {
		if (!button) return function () {};
		var html = button.innerHTML;
		var width = button.offsetWidth;
		button.disabled = true;
		button.style.minWidth = width + 'px';
		button.setAttribute('aria-busy', 'true');
		button.innerHTML = '<span class="lvd-spinner" aria-hidden="true"></span><span class="screen-reader-text" style="position:absolute;left:-10000px">Working…</span>';
		return function () {
			button.disabled = false;
			button.style.minWidth = '';
			button.removeAttribute('aria-busy');
			button.innerHTML = html;
		};
	}

	/** Download an .ics for the dinner. */
	function downloadIcs() {
		var d = cfg.dinner || {};
		if (!d.icsStart) return;
		var where = [d.venueSet ? d.venue : '', d.address].filter(Boolean).join(', ') || 'Cleveland–Akron area';
		var esc = function (s) { return String(s).replace(/([,;\\])/g, '\\$1').replace(/\n/g, '\\n'); };
		var stamp = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d+/, '');
		var lines = [
			'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//LEAD//Vision Dinner//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:vision-dinner-' + d.icsStart + '@leadcma.org',
			'DTSTAMP:' + stamp,
			'DTSTART;TZID=America/New_York:' + d.icsStart,
			'DTEND;TZID=America/New_York:' + (d.icsEnd || d.icsStart),
			'SUMMARY:' + esc(d.title),
			'LOCATION:' + esc(where),
			'DESCRIPTION:' + esc('Reception ' + d.reception + '. Business attire strongly suggested. leadcma.org/dinner'),
			'END:VEVENT', 'END:VCALENDAR'
		];
		var blob = new Blob([lines.join('\r\n')], { type: 'text/calendar;charset=utf-8' });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = 'lead-vision-dinner.ics';
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
	}

	var NUMBERS = ['Zero', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten'];

	window.LVD = {
		cfg: cfg,
		api: api,
		formatPhone: formatPhone,
		phoneDigits: phoneDigits,
		bindPhone: bindPhone,
		EMAIL: EMAIL,
		values: values,
		showErrors: showErrors,
		clearErrors: clearErrors,
		bindErrorClearing: bindErrorClearing,
		busy: busy,
		downloadIcs: downloadIcs,
		NUMBERS: NUMBERS
	};
})();
