(function () {
	'use strict';

	const statusFilter = document.querySelector('#reservation-status');
	const tableBody = document.querySelector('#reservation-body');
	const count = document.querySelector('#reservation-count');
	const message = document.querySelector('#reservation-message');

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
			const cancel = reservation.status === 'Pending'
				? `<button class="button button-small cancel-button" type="button" data-reservation-id="${escapeHtml(reservation.reservation_id)}">Cancel</button>`
				: '';
			return `<tr>
				<td><strong>${escapeHtml(reservation.room_code)}</strong><span class="table-subtext">${escapeHtml(reservation.room_name)}<br>${escapeHtml(reservation.lab_type)}</span></td>
				<td>${escapeHtml(formatDate(reservation.date))}<span class="table-subtext">${escapeHtml(formatTime(reservation.start_time))} - ${escapeHtml(formatTime(reservation.end_time))}</span></td>
				<td>${escapeHtml(reservation.purpose)}<span class="table-subtext">${escapeHtml(reservation.course_section)}</span></td>
				<td>${escapeHtml(reservation.expected_attendees)} / ${escapeHtml(reservation.capacity)}</td>
				<td><span class="status-pill status-${escapeHtml(reservation.status.toLowerCase())}">${escapeHtml(reservation.status)}</span></td>
				<td>${escapeHtml(formatDateTime(reservation.created_at))}</td>
				<td>${cancel}</td>
			</tr>`;
		}).join('');
		tableBody.querySelectorAll('.cancel-button').forEach((button) => button.addEventListener('click', () => cancelReservation(button.dataset.reservationId)));
	}

	async function cancelReservation(reservationId) {
		if (!window.confirm('Cancel this pending reservation?')) return;
		try {
			const response = await fetch('../api/reservations.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify({ action: 'cancel', reservation_id: reservationId, csrf_token: window.spotlyReservationCsrf })
			});
			const data = await response.json();
			if (!response.ok || !data.success) throw new Error(data.message || 'Unable to cancel reservation.');
			message.textContent = data.message;
			await fetchReservations();
		} catch (error) {
			message.textContent = error.message;
		}
	}

	statusFilter.addEventListener('change', fetchReservations);
	fetchReservations();
}());