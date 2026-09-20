(function () {
	'use strict';

	const state = { step: 1, rooms: [], available: false };
	const form = document.querySelector('#step-booking-form');
	const elements = {
		labType: document.querySelector('#book-lab-type'),
		room: document.querySelector('#book-room'),
		roomInfo: document.querySelector('#book-room-info'),
		date: document.querySelector('#book-date'),
		start: document.querySelector('#book-start-time'),
		end: document.querySelector('#book-end-time'),
		attendees: document.querySelector('#book-attendees'),
		availability: document.querySelector('#step-availability'),
		summary: document.querySelector('#booking-summary'),
		formMessage: document.querySelector('#step-form-message'),
		submit: document.querySelector('#step-submit')
	};

	function today() {
		const date = new Date();
		return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
	}

	async function fetchJson(url, options) {
		const response = await fetch(url, options);
		const data = await response.json();
		if (!response.ok || !data.success) throw new Error(data.message || 'Unable to complete this request.');
		return data;
	}

	async function loadRooms() {
		elements.roomInfo.textContent = 'Loading rooms...';
		const params = new URLSearchParams({ mode: 'rooms', lab_type: elements.labType.value });
		try {
			const data = await fetchJson(`../api/availability.php?${params.toString()}`);
			state.rooms = data.laboratories;
			elements.room.innerHTML = '<option value="">Choose a room</option>';
			state.rooms.forEach((room) => {
				const option = document.createElement('option');
				option.value = room.room_id;
				option.textContent = `${room.room_code} - ${room.room_name}`;
				elements.room.appendChild(option);
			});
			if (!state.rooms.length) elements.roomInfo.textContent = 'No rooms match this laboratory type.';
		} catch (error) {
			elements.roomInfo.textContent = error.message;
		}
	}

	function selectedRoom() {
		return state.rooms.find((room) => String(room.room_id) === elements.room.value);
	}

	function showStep(step) {
		state.step = step;
		document.querySelectorAll('.booking-step').forEach((section) => section.classList.toggle('active', section.dataset.step === String(step)));
		document.querySelectorAll('[data-step-indicator]').forEach((indicator) => indicator.classList.toggle('active', indicator.dataset.stepIndicator === String(step)));
		if (step === 2) loadRooms();
		if (step === 4) renderSummary();
	}

	function updateRoomInfo() {
		const room = selectedRoom();
		elements.roomInfo.textContent = room ? `${room.capacity} seats | ${room.floor} | ${room.status}` : '';
		if (room) elements.attendees.max = room.capacity;
	}

	async function checkAvailability() {
		const room = selectedRoom();
		if (!room || !elements.date.value || !elements.start.value || !elements.end.value) {
			state.available = false;
			elements.availability.textContent = 'Choose a date and time to check availability.';
			elements.availability.className = 'availability-message';
			return false;
		}
		const params = new URLSearchParams({ mode: 'check', room_id: room.room_id, date: elements.date.value, start_time: elements.start.value, end_time: elements.end.value });
		try {
			const data = await fetchJson(`../api/availability.php?${params.toString()}`);
			state.available = data.available;
			elements.availability.textContent = data.message;
			elements.availability.className = `availability-message ${data.available ? 'is-available' : 'is-conflict'}`;
			return data.available;
		} catch (error) {
			state.available = false;
			elements.availability.textContent = error.message;
			elements.availability.className = 'availability-message is-conflict';
			return false;
		}
	}

	function renderSummary() {
		const room = selectedRoom();
		const values = [
			['Lab type', room ? room.lab_type : ''],
			['Room', room ? `${room.room_code} - ${room.room_name}` : ''],
			['Date', elements.date.value],
			['Time', `${elements.start.value} - ${elements.end.value}`],
			['Purpose', document.querySelector('#book-purpose').value],
			['Course/section', document.querySelector('#book-course-section').value],
			['Attendees', document.querySelector('#book-attendees').value]
		];
		elements.summary.innerHTML = values.map(([label, value]) => `<dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd>`).join('');
	}

	async function submit(event) {
		event.preventDefault();
		if (!await checkAvailability()) {
			showStep(3);
			return;
		}
		elements.submit.disabled = true;
		elements.formMessage.textContent = 'Submitting reservation...';
		const formData = new FormData(form);
		formData.set('room_id', elements.room.value);
		try {
			const data = await fetchJson('../api/reserve.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify(Object.fromEntries(formData.entries()))
			});
			elements.formMessage.textContent = data.message;
			elements.submit.disabled = false;
			setTimeout(() => { window.location.href = 'dashboard.php'; }, 700);
		} catch (error) {
			elements.formMessage.textContent = error.message;
			elements.submit.disabled = false;
		}
	}

	function escapeHtml(value) {
		return String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
	}

	elements.date.min = today();
	elements.labType.addEventListener('change', loadRooms);
	elements.room.addEventListener('change', updateRoomInfo);
	[elements.date, elements.start, elements.end].forEach((element) => element.addEventListener('change', checkAvailability));
	document.querySelectorAll('[data-next]').forEach((button) => button.addEventListener('click', async () => {
		if (button.dataset.next === '2' && !elements.labType.value) return;
		if (button.dataset.next === '3' && !elements.room.value) return;
		if (button.dataset.next === '4' && !await checkAvailability()) return;
		showStep(Number(button.dataset.next));
	}));
	document.querySelectorAll('[data-back]').forEach((button) => button.addEventListener('click', () => showStep(Number(button.dataset.back))));
	form.addEventListener('submit', submit);
}());