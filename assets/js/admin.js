(function () {
	'use strict';
	const message = document.querySelector('#admin-message');

	async function performAction(payload) {
		const response = await fetch('../api/admin_action.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
			body: JSON.stringify({ ...payload, csrf_token: window.spotlyAdminCsrf })
		});
		const data = await response.json();
		if (!response.ok || !data.success) throw new Error(data.message || 'Admin action failed.');
		return data;
	}

	document.querySelectorAll('.decision-button').forEach((button) => button.addEventListener('click', async () => {
		let reason = '';
		if (button.dataset.action === 'reject') {
			reason = window.prompt('Optional rejection reason:') || '';
		}
		button.disabled = true;
		try {
			const data = await performAction({ action: button.dataset.action, reservation_id: button.dataset.reservationId, reason });
			message.textContent = data.message;
			window.location.reload();
		} catch (error) {
			message.textContent = error.message;
			button.disabled = false;
		}
	}));

	document.querySelectorAll('.lab-status-select').forEach((select) => select.addEventListener('change', async () => {
		const previous = select.dataset.previous || select.value;
		try {
			const data = await performAction({ action: 'update_lab_status', room_id: select.dataset.roomId, status: select.value });
			select.dataset.previous = select.value;
			message.textContent = data.message;
		} catch (error) {
			select.value = previous;
			message.textContent = error.message;
		}
	}));
}());