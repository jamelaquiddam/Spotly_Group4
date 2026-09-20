(function () {
	'use strict';

	const statusFilter = document.querySelector('#reservation-status');
	const tableBody = document.querySelector('#reservation-body');
	const count = document.querySelector('#reservation-count');
	const message = document.querySelector('#reservation-message');
	const cancelModal = document.querySelector('#cancel-modal');
	const cancelForm = document.querySelector('#cancel-form');
	const cancelReason = document.querySelector('#cancel-reason');
	const cancelId = document.querySelector('#cancel-reservation-id');
	const cancelMessage = document.querySelector('#cancel-message');

	function escapeHtml(value) {
		return String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
	}

	function formatTime(value) {
		const [hours, minutes] = value.split(':').map(Number);
		const suffix = hours >= 12 ? 'PM' : 'AM';
		return `${hours % 12 || 12}:${String(minutes).padStart(2, '0')} ${suffix}`;
	}

	function formatDate(value) {
		return new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(`${value}T00:00:00`));
	}

	function formatDateTime(value) {
		const date = new Date(value.replace(' ', 'T'));
		return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
	}

	function canCheckIn(reservation) {
		if (reservation.status !== 'Approved' || reservation.checked_in_at) return false;
		const start = new Date(`${reservation.date}T${reservation.start_time}`);
		const now = new Date();
		return now >= new Date(start.getTime() - 10 * 60000) && now <= new Date(start.getTime() + 20 * 60000);
	}

	function startCountdowns() {
		tableBody.querySelectorAll('[data-countdown]').forEach((element) => {
			const update = () => {
				const start = new Date(element.dataset.start).getTime();
				const remaining = Math.max(0, Math.floor((start + 1200000 - Date.now()) / 1000));
				element.textContent = `Check in within ${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
			};
			update();
			setInterval(update, 1000);
		});
	}

	async function fetchReservations() {
		message.textContent = 'Loading reservations...';
		const params = new URLSearchParams();
		if (statusFilter.value) params.set('status', statusFilter.value);
		try {
			const response = await fetch(`../api/reservations.php?${params.toString()}`, { headers: { Accept: 'application/json' } });
			const data = await response.json();
			if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load reservations.');
			renderReservations(data.reservations);
		} catch (error) {
			message.textContent = error.message;
			tableBody.innerHTML = '<tr><td colspan="7" class="empty-table">Unable to load reservations.</td></tr>';
		}
	}

	function renderReservations(reservations) {
		message.textContent = '';
		count.textContent = `${reservations.length} reservation${reservations.length === 1 ? '' : 's'}`;
		if (!reservations.length) {
			tableBody.innerHTML = '<tr><td colspan="7" class="empty-table">No reservations match this filter.</td></tr>';
			return;
		}
		tableBody.innerHTML = reservations.map((reservation) => {
			const cancel = ['Pending', 'Approved'].includes(reservation.status) && new Date(`${reservation.date}T${reservation.start_time}`) > new Date()
				? `<button class="button button-small cancel-button" type="button" data-reservation-id="${escapeHtml(reservation.reservation_id)}">Cancel</button>`
				: '';
			const checkIn = canCheckIn(reservation) ? `<button class="button button-small check-in-button" type="button" data-reservation-id="${escapeHtml(reservation.reservation_id)}">Check In</button>` : '';
			const checked = reservation.checked_in_at ? '<span class="status-pill status-checked-in">Checked in</span>' : (canCheckIn(reservation) ? `<span class="countdown" data-start="${escapeHtml(`${reservation.date}T${reservation.start_time}`)}"></span>` : '');
			return `<tr>
				<td><strong>${escapeHtml(reservation.room_code)}</strong><span class="table-subtext">${escapeHtml(reservation.room_name)}<br>${escapeHtml(reservation.lab_type)}</span></td>
				<td>${escapeHtml(formatDate(reservation.date))}<span class="table-subtext">${escapeHtml(formatTime(reservation.start_time))} - ${escapeHtml(formatTime(reservation.end_time))}</span></td>
				<td>${escapeHtml(reservation.purpose)}<span class="table-subtext">${escapeHtml(reservation.course_section)}</span></td>
				<td>${escapeHtml(reservation.expected_attendees)} / ${escapeHtml(reservation.capacity)}</td>
				<td><span class="status-pill status-${escapeHtml(reservation.status.toLowerCase().replace(/ /g, '-'))}">${escapeHtml(reservation.status)}</span>${checked}</td>
				<td>${escapeHtml(formatDateTime(reservation.created_at))}</td>
				<td>${cancel}${checkIn}</td>
			</tr>`;
		}).join('');
		tableBody.querySelectorAll('.cancel-button').forEach((button) => button.addEventListener('click', () => openCancel(button.dataset.reservationId)));
		tableBody.querySelectorAll('.check-in-button').forEach((button) => button.addEventListener('click', () => checkIn(button.dataset.reservationId)));
		startCountdowns();
	}

	function openCancel(reservationId) {
		cancelId.value = reservationId;
		cancelReason.value = '';
		cancelMessage.textContent = '';
		cancelModal.showModal();
	}

	async function cancelReservation(event) {
		event.preventDefault();
		try {
			const response = await fetch('../api/reservations.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify({ action: 'cancel', reservation_id: cancelId.value, reason: cancelReason.value, csrf_token: window.spotlyReservationCsrf })
			});
			const data = await response.json();
			if (!response.ok || !data.success) throw new Error(data.message || 'Unable to cancel reservation.');
			message.textContent = data.message;
			cancelModal.close();
			await fetchReservations();
		} catch (error) {
			cancelMessage.textContent = error.message;
		}
	}

	async function checkIn(reservationId) {
		const response = await fetch('../api/reservations.php', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ action: 'check_in', reservation_id: reservationId, csrf_token: window.spotlyReservationCsrf }) });
		const data = await response.json();
		message.textContent = data.message;
		if (data.success) fetchReservations();
	}

	statusFilter.addEventListener('change', fetchReservations);
	cancelForm.addEventListener('submit', cancelReservation);
	document.querySelector('#cancel-no').addEventListener('click', () => cancelModal.close());
	document.querySelector('#close-cancel-modal').addEventListener('click', () => cancelModal.close());
	fetchReservations();
}());