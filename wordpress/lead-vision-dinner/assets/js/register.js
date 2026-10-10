/* LEAD Vision Dinner: the public registration form ([lead_vision_dinner]). */
(function () {
	'use strict';

	var L = window.LVD;
	var page = document.querySelector('[data-lvd-register]');
	if (!L || !page) return;

	var reg = page.querySelector('.lvd-reg');
	var form = page.querySelector('[data-form]');
	var done = page.querySelector('[data-done]');
	var banner = form.querySelector('[data-banner]');
	var submit = form.querySelector('[data-submit]');
	var combo = form.querySelector('[data-combo]');
	var search = form.querySelector('[data-combo-input]');
	var list = form.querySelector('[data-combo-list]');
	var dinner = L.cfg.dinner || {};

	var state = {
		leaders: null,      // loaded on first focus
		leadersFailed: false,
		selected: null,     // { id, name, church, seats_left }
		seatMe: false,
		active: -1          // keyboard highlight in the list
	};

	L.bindPhone(form);
	L.bindErrorClearing(form);

	/* ---------- Role switch ---------- */
	function role() {
		var r = form.querySelector('input[name="role"]:checked');
		return r ? r.value : 'guest';
	}

	function applyRole() {
		var isGuest = role() === 'guest';
		Array.prototype.forEach.call(form.querySelectorAll('[data-guest-only]'), function (el) { el.hidden = !isGuest; });
		Array.prototype.forEach.call(form.querySelectorAll('[data-leader-only]'), function (el) { el.hidden = isGuest; });
		form.querySelector('[data-submit-label]').textContent = isGuest ? 'Reserve my seat' : 'Register as a Table Leader';
	}
	form.addEventListener('change', function (e) {
		if (e.target.name === 'role') applyRole();
		if (e.target.name === 'plus_one') form.querySelector('[data-plus]').hidden = !e.target.checked;
	});
	applyRole();

	/* ---------- Table Leader search ---------- */
	function loadLeaders() {
		if (state.leaders || state.loading) return;
		state.loading = true;
		L.api('leaders').then(function (res) {
			state.loading = false;
			state.leaders = res.ok && res.data.leaders ? res.data.leaders : [];
			state.leadersFailed = !res.ok;
			if (!list.hidden) renderList();
		});
	}

	function pill(seats) {
		if (seats <= 0) return { cls: 'lvd-pill--full', text: 'Full' };
		return { cls: seats <= 3 ? 'lvd-pill--few' : 'lvd-pill--many', text: seats === 1 ? '1 seat left' : seats + ' seats left' };
	}

	function matches() {
		var q = search.value.trim().toLowerCase();
		var all = state.leaders || [];
		if (!q || state.selected || state.seatMe) return all;
		return all.filter(function (l) {
			return l.name.toLowerCase().indexOf(q) !== -1 || (l.church || '').toLowerCase().indexOf(q) !== -1;
		});
	}

	function renderList() {
		list.innerHTML = '';
		var rows = matches();

		if (!state.leaders) {
			list.appendChild(note('Loading Table Leaders…'));
		} else if (state.leadersFailed) {
			list.appendChild(note('We couldn\'t load the list. Choose "Please seat me" below, or try again in a moment.'));
		} else if (!rows.length) {
			list.appendChild(note('No Table Leader matches that name.'));
		}

		rows.forEach(function (l, i) {
			var p = pill(l.seats_left);
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'lvd-opt-row';
			b.setAttribute('role', 'option');
			b.id = 'lvd-opt-' + i;
			b.innerHTML = '<span class="lvd-opt-row__text"><span class="lvd-opt-row__name"></span><span class="lvd-opt-row__church"></span></span><span class="lvd-pill ' + p.cls + '"></span>';
			b.querySelector('.lvd-opt-row__name').textContent = l.name;
			b.querySelector('.lvd-opt-row__church').textContent = l.church || '';
			b.querySelector('.lvd-pill').textContent = p.text;
			b.addEventListener('mousedown', function (e) { e.preventDefault(); });
			b.addEventListener('click', function () { pick(l); });
			list.appendChild(b);
		});

		var seat = document.createElement('button');
		seat.type = 'button';
		seat.className = 'lvd-opt-row lvd-opt-row--seatme';
		seat.setAttribute('role', 'option');
		seat.id = 'lvd-opt-seatme';
		seat.textContent = 'I don\'t have a Table Leader. Please seat me.';
		seat.addEventListener('mousedown', function (e) { e.preventDefault(); });
		seat.addEventListener('click', pickSeatMe);
		list.appendChild(seat);

		highlight(Math.min(state.active, options().length - 1));
	}

	function note(text) {
		var d = document.createElement('div');
		d.className = 'lvd-combo__empty';
		d.textContent = text;
		return d;
	}

	function options() { return list.querySelectorAll('.lvd-opt-row'); }

	function highlight(i) {
		var opts = options();
		state.active = i;
		Array.prototype.forEach.call(opts, function (o, j) { o.classList.toggle('is-active', j === i); o.setAttribute('aria-selected', j === i ? 'true' : 'false'); });
		if (opts[i]) {
			search.setAttribute('aria-activedescendant', opts[i].id);
			opts[i].scrollIntoView({ block: 'nearest' });
		} else {
			search.removeAttribute('aria-activedescendant');
		}
	}

	function open() {
		loadLeaders();
		list.hidden = false;
		search.setAttribute('aria-expanded', 'true');
		state.active = -1;
		renderList();
	}

	function close() {
		list.hidden = true;
		search.setAttribute('aria-expanded', 'false');
		search.removeAttribute('aria-activedescendant');
	}

	function clearLeaderError() {
		search.classList.remove('is-invalid');
		form.querySelector('[data-error-for="leader"]').hidden = true;
	}

	function pick(l) {
		state.selected = l;
		state.seatMe = false;
		search.value = l.name;
		close();
		clearLeaderError();
		form.querySelector('[data-full-note]').hidden = l.seats_left > 0;
		form.querySelector('[data-seatme-note]').hidden = true;
	}

	function pickSeatMe() {
		state.selected = null;
		state.seatMe = true;
		search.value = 'I don\'t have a Table Leader';
		close();
		clearLeaderError();
		form.querySelector('[data-full-note]').hidden = true;
		form.querySelector('[data-seatme-note]').hidden = false;
	}

	search.addEventListener('focus', open);
	search.addEventListener('click', function () { if (list.hidden) open(); });
	search.addEventListener('input', function () {
		// Typing again clears the selection.
		state.selected = null;
		state.seatMe = false;
		form.querySelector('[data-full-note]').hidden = true;
		form.querySelector('[data-seatme-note]').hidden = true;
		list.hidden = false;
		search.setAttribute('aria-expanded', 'true');
		state.active = -1;
		renderList();
	});
	search.addEventListener('keydown', function (e) {
		var n = options().length;
		if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) open(); highlight((state.active + 1) % n); }
		else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(state.active <= 0 ? n - 1 : state.active - 1); }
		else if (e.key === 'Enter' && !list.hidden && state.active >= 0) { e.preventDefault(); options()[state.active].click(); }
		else if (e.key === 'Escape') { close(); }
	});
	search.addEventListener('blur', function () { setTimeout(close, 120); });
	document.addEventListener('click', function (e) { if (!combo.contains(e.target)) close(); });

	/* ---------- Validation ---------- */
	function validate(v) {
		var errors = {};
		var isGuest = v.role === 'guest';
		if (isGuest && !state.selected && !state.seatMe) errors.leader = 'Choose your Table Leader, or "Please seat me".';
		if (!v.first) errors.first = 'Enter your first name.';
		if (!v.last) errors.last = 'Enter your last name.';
		if (!v.email) errors.email = 'Enter your email.';
		else if (!L.EMAIL.test(v.email)) errors.email = 'Enter a valid email address.';
		if (L.phoneDigits(v.phone).length !== 10) errors.phone = 'Enter a 10-digit mobile number.';
		if (v.plus_one) {
			if (!v.plus_first) errors.plus_first = 'Enter their first name.';
			if (!v.plus_last) errors.plus_last = 'Enter their last name.';
			if (v.plus_email && !L.EMAIL.test(v.plus_email)) errors.plus_email = 'Enter a valid email address.';
		}
		return errors;
	}

	/* ---------- Submit ---------- */
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		banner.hidden = true;
		var v = L.values(form);
		var errors = validate(v);
		if (Object.keys(errors).length) {
			L.showErrors(form, errors);
			return;
		}
		L.clearErrors(form);

		v.leader_id = state.selected ? state.selected.id : '';
		v.seat_me = state.seatMe;
		if (v.role !== 'guest') { v.leader_id = ''; v.seat_me = false; v.interest = false; }

		var restore = L.busy(submit);
		L.api('register', v).then(function (res) {
			restore();
			if (res.ok) return showDone(v, res.data);
			if (res.status === 422 && res.data.errors) return L.showErrors(form, res.data.errors);
			banner.textContent = res.status === 429
				? 'That\'s a lot of registrations from one place. Please wait a little and try again, or call 1-866-LEADCMA.'
				: 'Something went wrong. Please try again, or call 1-866-LEADCMA.';
			banner.hidden = false;
			banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
		});
	});

	/* ---------- Thank-you ---------- */
	function showDone(v, data) {
		var isLeader = v.role === 'leader';
		var headline = page.querySelector('[data-thanks-headline]');

		var returning = isLeader && data.returned;

		done.querySelector('[data-done-title]').textContent = returning
			? 'You\'re already registered.'
			: (isLeader ? 'Thank you for leading a table.' : 'See you ' + (dinner.dateShort || 'soon') + '.');
		done.querySelector('[data-done-body]').textContent = returning
			? 'You\'re a Table Leader for this dinner, so we didn\'t change anything. We\'ve emailed your private link to register guests. To update your details, contact your Table Leader Coordinator.'
			: (isLeader
				? 'We\'ve emailed your confirmation and your private guest link. Your Table Leader Coordinator will be in touch soon.'
				: 'We\'ve emailed your confirmation. Watch for a reminder a few days before the dinner.');
		done.querySelector('[data-done-table]').textContent = data.table || '';

		// The "Now, fill your table." block only appears for a new Table
		// Leader; a returning one gets the link by email only.
		var leaderBlock = done.querySelector('[data-done-leader]');
		leaderBlock.hidden = !isLeader || returning || !data.link;
		if (!leaderBlock.hidden) done.querySelector('[data-done-link]').href = data.link;

		headline.textContent = isLeader ? 'Thank you for saying yes.' : 'We saved you a seat.';
		headline.hidden = false;
		reg.classList.add('is-done');
		form.hidden = true;
		done.hidden = false;
		var expect = page.querySelector('[data-expect]');
		if (expect) expect.hidden = true;
		page.scrollIntoView({ behavior: 'smooth', block: 'start' });
		done.focus({ preventScroll: true });
	}

	done.querySelector('[data-ics]').addEventListener('click', L.downloadIcs);

	done.querySelector('[data-reset]').addEventListener('click', function () {
		form.reset();
		state.selected = null;
		state.seatMe = false;
		form.querySelector('[data-plus]').hidden = true;
		form.querySelector('[data-full-note]').hidden = true;
		form.querySelector('[data-seatme-note]').hidden = true;
		L.clearErrors(form);
		applyRole();
		page.querySelector('[data-thanks-headline]').hidden = true;
		reg.classList.remove('is-done');
		var expect = page.querySelector('[data-expect]');
		if (expect) expect.hidden = false;
		done.hidden = true;
		form.hidden = false;
		state.leaders = null; // seat counts have changed
		page.scrollIntoView({ behavior: 'smooth', block: 'start' });
	});
})();
