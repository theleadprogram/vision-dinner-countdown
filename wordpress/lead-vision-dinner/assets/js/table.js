/* LEAD Vision Dinner: a Table Leader's private guest page ([lead_vision_dinner_table]). */
(function () {
	'use strict';

	var L = window.LVD;
	var page = document.querySelector('[data-lvd-table]');
	if (!L || !page) return;

	var token = new URLSearchParams(window.location.search).get('t') || '';
	var loading = page.querySelector('[data-loading]');
	var lost = page.querySelector('[data-lost]');
	var view = page.querySelector('[data-table]');
	var banner = view.querySelector('[data-banner]');
	var addForm = view.querySelector('[data-add]');
	var listEl = view.querySelector('[data-list]');
	var template = document.querySelector('[data-row-template]');

	var state = { table: null, editing: null };

	L.bindPhone(page);
	L.bindErrorClearing(addForm);

	/* ---------- Load ---------- */
	if (!/^[a-f0-9]{40}$/.test(token)) {
		showLost(false);
	} else {
		L.api('table?t=' + encodeURIComponent(token)).then(function (res) {
			if (res.ok) return render(res.data);
			showLost(res.status === 403);
			if (res.status !== 403) {
				lost.querySelector('[data-lost-title]').textContent = 'We couldn\'t load your table.';
				lost.querySelector('[data-lost-body]').textContent = 'Please refresh in a moment, or call 1-866-LEADCMA. You can also have your link emailed to you again.';
			}
		});
	}

	function showLost(badLink) {
		loading.hidden = true;
		view.hidden = true;
		lost.hidden = false;
		if (badLink) {
			lost.querySelector('[data-lost-title]').textContent = 'This link isn\'t working.';
			lost.querySelector('[data-lost-body]').textContent = 'It may be incomplete or out of date. Enter the email you registered with and we\'ll send you a fresh link.';
		}
	}

	/* ---------- Lost your link? ---------- */
	var lostForm = lost.querySelector('[data-lost-form]');
	L.bindErrorClearing(lostForm);
	lostForm.addEventListener('submit', function (e) {
		e.preventDefault();
		var email = lostForm.querySelector('[name="email"]').value.trim();
		if (!L.EMAIL.test(email)) return L.showErrors(lostForm, { email: 'Enter a valid email address.' });
		L.clearErrors(lostForm);
		var restore = L.busy(lostForm.querySelector('[data-submit]'));
		L.api('resend-link', { email: email }).then(function (res) {
			restore();
			var sent = lostForm.querySelector('[data-lost-sent]');
			sent.textContent = res.ok
				? 'If that email belongs to a Table Leader, your link is on its way. Check your inbox in a few minutes.'
				: (res.status === 429 ? 'Please wait a little before trying again, or call 1-866-LEADCMA.' : 'Something went wrong. Please try again, or call 1-866-LEADCMA.');
			sent.hidden = false;
		});
	});

	/* ---------- Render ---------- */
	function render(table) {
		state.table = table;
		loading.hidden = true;
		lost.hidden = true;
		view.hidden = false;

		view.querySelector('[data-welcome]').textContent = 'Welcome back, ' + table.leader.first + '.';

		var size = table.size || 10;
		var filled = table.guests.filter(function (g) { return g.status !== 'Cancelled'; }).length;
		var left = size - filled;

		view.querySelector('[data-count]').textContent = filled + ' of ' + size;
		view.querySelector('[data-filled]').textContent = filled;
		view.querySelector('.lvd-meter__of').textContent = 'of ' + size + ' seats filled';
		view.querySelector('[data-seats-msg]').textContent = left <= 0
			? 'Your table is full. Thank you!'
			: (left === 1 ? 'One seat to go. Someone is waiting to be asked.' : (L.NUMBERS[left] || left) + ' seats to go. Someone is waiting to be asked.');

		var bar = view.querySelector('[data-bar]');
		bar.innerHTML = '';
		for (var i = 0; i < size; i++) {
			var s = document.createElement('span');
			if (i < filled) s.className = 'is-filled';
			bar.appendChild(s);
		}

		listEl.innerHTML = '';
		table.guests.forEach(function (g) { listEl.appendChild(row(g)); });
		view.querySelector('[data-empty]').hidden = table.guests.length > 0;
	}

	function row(g) {
		var el = template.content.firstElementChild.cloneNode(true);
		var cancelled = g.status === 'Cancelled';
		var editing = state.editing === g.id;
		var form = el.querySelector('[data-g="form"]');

		el.classList.toggle('is-cancelled', cancelled);
		el.classList.toggle('is-editing', editing);
		el.querySelector('[data-g="name"]').textContent = (g.first + ' ' + g.last).trim();
		el.querySelector('[data-g="email"]').textContent = g.email || '—';
		el.querySelector('[data-g="phone"]').textContent = g.phone || '—';
		var badge = el.querySelector('[data-g="status"]');
		badge.textContent = cancelled ? 'Cancelled' : (g.leader ? 'Table Leader' : 'Registered');
		badge.classList.toggle('lvd-badge--neutral', cancelled);

		var toggle = el.querySelector('[data-g="toggle"]');
		toggle.textContent = editing ? 'Close' : 'Edit';
		toggle.setAttribute('aria-expanded', editing ? 'true' : 'false');
		toggle.setAttribute('aria-label', (editing ? 'Close ' : 'Edit ') + g.first);
		// Cancelled guests and the Table Leader's own seat have nothing to edit here.
		if (cancelled || g.leader) toggle.hidden = true;

		// Unique ids for the cloned fields.
		Array.prototype.forEach.call(form.querySelectorAll('.lvd-field'), function (f) {
			var input = f.querySelector('input');
			var label = f.querySelector('label');
			input.id = 'lvd-g' + g.id + '-' + input.name;
			label.setAttribute('for', input.id);
			input.value = g[input.name] || '';
		});

		form.hidden = !editing;
		L.bindErrorClearing(form);

		toggle.addEventListener('click', function () {
			state.editing = editing ? null : g.id;
			render(state.table);
			if (!editing) {
				var first = listEl.querySelector('.is-editing input');
				if (first) first.focus();
			}
		});
		el.querySelector('[data-g="cancel"]').addEventListener('click', function () {
			state.editing = null;
			render(state.table);
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var v = L.values(form);
			var errors = validateGuest(v);
			if (Object.keys(errors).length) return L.showErrors(form, errors);
			v.t = token;
			var restore = L.busy(form.querySelector('[data-submit]'));
			send('table/guests/' + g.id, v, form, restore);
		});

		el.querySelector('[data-g="release"]').addEventListener('click', function (e) {
			if (!window.confirm('Release ' + g.first + '\'s seat? Your coordinator will be told right away.')) return;
			var restore = L.busy(e.currentTarget);
			send('table/guests/' + g.id + '/release', { t: token }, form, restore);
		});

		return el;
	}

	function validateGuest(v) {
		var errors = {};
		if (!v.first) errors.first = 'Enter a first name.';
		if (v.email && !L.EMAIL.test(v.email)) errors.email = 'Enter a valid email address.';
		if (v.phone && L.phoneDigits(v.phone).length !== 10) errors.phone = 'Enter a 10-digit mobile number.';
		return errors;
	}

	/** POST, then re-render from the fresh table the server returns. */
	function send(path, body, form, restore, onDone) {
		banner.hidden = true;
		return L.api(path, body).then(function (res) {
			restore();
			if (res.ok) {
				state.editing = null;
				render(res.data);
				if (onDone) onDone();
				return;
			}
			if (res.status === 422 && res.data.errors) return L.showErrors(form, res.data.errors);
			if (res.status === 403) return showLost(true);
			banner.textContent = res.status === 429
				? 'That\'s a lot of changes at once. Please wait a little and try again, or call 1-866-LEADCMA.'
				: 'Something went wrong. Please try again, or call 1-866-LEADCMA.';
			banner.hidden = false;
			banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
		});
	}

	/* ---------- Add a guest ---------- */
	addForm.addEventListener('submit', function (e) {
		e.preventDefault();
		var v = L.values(addForm);
		var errors = validateGuest(v);
		if (Object.keys(errors).length) return L.showErrors(addForm, errors);
		L.clearErrors(addForm);
		v.t = token;
		var restore = L.busy(addForm.querySelector('[data-submit]'));
		var name = v.first;
		send('table/guests', v, addForm, restore, function () {
			addForm.reset();
			var added = addForm.querySelector('[data-added]');
			added.textContent = name + ' is registered.';
			added.hidden = false;
			setTimeout(function () { added.hidden = true; }, 5000);
			addForm.querySelector('[name="first"]').focus();
		});
	});
})();
