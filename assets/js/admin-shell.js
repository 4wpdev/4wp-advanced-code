(function () {
	document.addEventListener('click', function (event) {
		var card = event.target.closest('.forwp-ac-module-card');
		if (card) {
			var input = card.querySelector('.forwp-ac-module-card__check');
			if (!input) {
				return;
			}
			if (event.target === input) {
				syncCard(card, input);
				return;
			}
			input.checked = !input.checked;
			syncCard(card, input);
			return;
		}

		var btn = event.target.closest('.notice-dismiss');
		if (!btn || !window.forwpAcNotices) {
			return;
		}

		var notice = btn.closest('.forwp-ac-persistent-notice');
		var id = notice ? notice.getAttribute('data-notice') : '';
		if (!id) {
			return;
		}

		var body = new FormData();
		body.append('action', 'forwp_ac_dismiss_notice');
		body.append('notice', id);
		body.append('nonce', window.forwpAcNotices.nonce);
		fetch(window.forwpAcNotices.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		});
	});

	document.addEventListener('change', function (event) {
		var radio = event.target;
		if (!radio || !radio.matches || !radio.matches('.forwp-ac-align-btn input[type="radio"]')) {
			return;
		}
		var group = radio.closest('.forwp-ac-align-buttons');
		if (!group) {
			return;
		}
		group.querySelectorAll('.forwp-ac-align-btn').forEach(function (label) {
			label.classList.toggle('is-active', label.contains(radio) && radio.checked);
		});
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Enter' && event.key !== ' ') {
			return;
		}
		var card = event.target.closest('.forwp-ac-module-card');
		if (!card) {
			return;
		}
		event.preventDefault();
		card.click();
	});

	function syncCard(card, input) {
		card.classList.toggle('is-enabled', !!input.checked);
		card.setAttribute('aria-pressed', input.checked ? 'true' : 'false');
		var on = card.querySelector('.forwp-ac-badge--on');
		var off = card.querySelector('.forwp-ac-badge--off');
		if (on) {
			on.hidden = !input.checked;
		}
		if (off) {
			off.hidden = !!input.checked;
		}
	}
})();
