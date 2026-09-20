(function () {
	'use strict';

	const state = {
		rooms: [],
		selectedRoomId: '',
		selectedDate: new Date(),
		reservations: [],
		laboratory: null,
		selectionStart: null,
		selectionEnd: null
	};

	const elements = {
		labType: document.querySelector('#lab-type'),
		roomSelect: document.querySelector('#room-select'),
		roomStatus: document.querySelector('#room-status'),
		calendarDate: document.querySelector('#calendar-date'),
		calendarTitle: document.querySelector('#calendar-title'),
		calendarMessage: document.querySelector('#calendar-message'),
		calendarGrid: document.querySelector('#calendar-grid'),
		catalogBody: document.querySelector('#catalog-body'),
		catalogCount: document.querySelector('#catalog-count'),
		todayButton: document.querySelector('#today-button'),
		previousButton: document.querySelector('#previous-button'),
		nextButton: document.querySelector('#next-button')
		,bookingModal: document.querySelector('#booking-modal')
		,bookingForm: document.querySelector('#booking-form')
		,closeBookingModal: document.querySelector('#close-booking-modal')
		,cancelBooking: document.querySelector('#cancel-booking')
		,bookingRoomId: document.querySelector('#booking-room-id')
		,bookingRoomLabel: document.querySelector('#booking-room-label')
		,bookingTimeLabel: document.querySelector('#booking-time-label')
		,bookingDate: document.querySelector('#booking-date')
		,bookingStartTime: document.querySelector('#booking-start-time')
		,bookingEndTime: document.querySelector('#booking-end-time')
		,bookingAvailability: document.querySelector('#booking-availability')
		,bookingFormMessage: document.querySelector('#booking-form-message')
		,submitBooking: document.querySelector('#submit-booking')
	};

	function formatDate(date) {
		const year = date.getFullYear();
		const month = String(date.getMonth() + 1).padStart(2, '0');
		const day = String(date.getDate()).padStart(2, '0');
		return `${year}-${month}-${day}`;
	}

	function parseDate(value) {
		const [year, month, day] = value.split('-').map(Number);
		return new Date(year, month - 1, day);
	}

	function startOfWeek(date) {
		const result = new Date(date);
		const day = result.getDay();
		const difference = day === 0 ? -6 : 1 - day;
		result.setDate(result.getDate() + difference);
		result.setHours(0, 0, 0, 0);
		return result;
	}

	function addDays(date, amount) {
		const result = new Date(date);
		result.setDate(result.getDate() + amount);
		return result;
	}

	function formatDay(date) {
		return new Intl.DateTimeFormat('en', { weekday: 'short', month: 'short', day: 'numeric' }).format(date);
	}

	function formatTime(value) {
		const [hours, minutes] = value.split(':').map(Number);
		const suffix = hours >= 12 ? 'PM' : 'AM';
		const hour = hours % 12 || 12;
		return `${hour}:${String(minutes).padStart(2, '0')} ${suffix}`;
	}

	function timeToMinutes(value) {
		const [hours, minutes] = value.split(':').map(Number);
		return hours * 60 + minutes;
	}

	function roomIsUnavailable() {
		return !state.laboratory || state.laboratory.status !== 'Available';
	}

	async function fetchJson(url) {
		const response = await fetch(url, { headers: { Accept: 'application/json' } });
		const data = await response.json();
		if (!response.ok || !data.success) {
			throw new Error(data.message || 'Unable to load availability.');
		}
		return data;
	}

	async function loadRooms() {
		elements.roomStatus.textContent = 'Loading rooms...';
		try {
			const type = elements.labType.value;
			const params = new URLSearchParams({ mode: 'rooms' });
			if (type) params.set('lab_type', type);
			const data = await fetchJson(`../api/availability.php?${params.toString()}`);
			state.rooms = data.laboratories;
			renderRoomOptions();
			renderCatalog();
			if (state.selectedRoomId) await loadSchedule();
		} catch (error) {
			elements.roomStatus.textContent = error.message;
			elements.calendarMessage.textContent = error.message;
		}
	}

	function renderRoomOptions() {
		const previous = String(state.selectedRoomId);
		elements.roomSelect.innerHTML = '';
		state.rooms.forEach((room) => {
			const option = document.createElement('option');
			option.value = room.room_id;
			option.textContent = `${room.room_code} - ${room.room_name}`;
			elements.roomSelect.appendChild(option);
		});
		if (!state.rooms.length) {
			elements.roomStatus.textContent = 'No rooms match this filter.';
			state.selectedRoomId = '';
			return;
		}
		const stillExists = state.rooms.some((room) => String(room.room_id) === previous);
		state.selectedRoomId = stillExists ? previous : String(state.rooms[0].room_id);
		elements.roomSelect.value = state.selectedRoomId;
		elements.roomStatus.textContent = `${state.rooms.length} room${state.rooms.length === 1 ? '' : 's'} available in this filter.`;
	}

	function renderCatalog() {
		elements.catalogBody.innerHTML = '';
		elements.catalogCount.textContent = `${state.rooms.length} room${state.rooms.length === 1 ? '' : 's'}`;
		state.rooms.forEach((room) => {
			const row = document.createElement('tr');
			row.tabIndex = 0;
			row.className = String(room.room_id) === String(state.selectedRoomId) ? 'selected-row' : '';
			row.innerHTML = `
				<td><strong>${escapeHtml(room.room_name)}</strong><span class="table-subtext">${escapeHtml(room.room_code)}</span></td>
				<td>${escapeHtml(room.lab_type)}</td>
				<td>${escapeHtml(String(room.capacity))}</td>
				<td>${escapeHtml(room.floor)}</td>
				<td><span class="status-pill status-${slugify(room.status)}">${escapeHtml(room.status)}</span></td>`;
			row.addEventListener('click', () => selectRoom(room.room_id));
			row.addEventListener('keydown', (event) => {
				if (event.key === 'Enter' || event.key === ' ') selectRoom(room.room_id);
			});
			elements.catalogBody.appendChild(row);
		});
	}

	function selectRoom(roomId) {
		state.selectedRoomId = String(roomId);
		elements.roomSelect.value = state.selectedRoomId;
		renderCatalog();
		loadSchedule();
	}

	async function loadSchedule() {
		if (!state.selectedRoomId) {
			renderEmpty('Select a room to view its schedule.');
			return;
		}
		const weekStart = startOfWeek(state.selectedDate);
		const weekEnd = addDays(weekStart, 5);
		elements.calendarMessage.textContent = 'Loading schedule...';
		try {
			const params = new URLSearchParams({
				room_id: state.selectedRoomId,
				start_date: formatDate(weekStart),
				end_date: formatDate(weekEnd)
			});
			const data = await fetchJson(`../api/availability.php?${params.toString()}`);
			state.laboratory = data.laboratory;
			state.reservations = data.reservations;
			state.selectionStart = null;
			state.selectionEnd = null;
			elements.calendarDate.value = formatDate(state.selectedDate);
			elements.calendarTitle.textContent = `${state.laboratory.room_code} availability`;
			elements.calendarMessage.textContent = '';
			renderGrid();
		} catch (error) {
			renderEmpty(error.message);
		}
	}

	function renderEmpty(message) {
		elements.calendarGrid.innerHTML = `<div class="calendar-empty">${escapeHtml(message)}</div>`;
		elements.calendarMessage.textContent = message;
	}

	function renderGrid() {
		const weekStart = startOfWeek(state.selectedDate);
		const isMobile = window.matchMedia('(max-width: 700px)').matches;
		const days = isMobile
			? [new Date(state.selectedDate)]
			: Array.from({ length: 6 }, (_, index) => addDays(weekStart, index));
		const fragment = document.createDocumentFragment();
		elements.calendarGrid.innerHTML = '';
		elements.calendarGrid.style.setProperty('--calendar-days', days.length);
		const corner = document.createElement('div');
		corner.className = 'calendar-corner';
		corner.textContent = 'Time';
		fragment.appendChild(corner);
		days.forEach((day) => {
			const heading = document.createElement('div');
			heading.className = 'calendar-day-heading';
			heading.dataset.date = formatDate(day);
			heading.innerHTML = `<strong>${formatDay(day)}</strong>`;
			fragment.appendChild(heading);
		});
		for (let hour = 7; hour < 21; hour += 0.5) {
			const minutes = Math.round(hour * 60);
			const time = `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
			const label = document.createElement('div');
			label.className = 'calendar-time';
			label.textContent = formatTime(`${time}:00`);
			fragment.appendChild(label);
			days.forEach((day) => fragment.appendChild(createSlot(day, time)));
		}
		elements.calendarGrid.appendChild(fragment);
	}

	function createSlot(day, time) {
		const date = formatDate(day);
		const slot = document.createElement('button');
		const slotStart = timeToMinutes(time);
		const slotEnd = slotStart + 30;
		const reservation = state.reservations.find((item) => item.date === date && timeToMinutes(item.start_time) < slotEnd && timeToMinutes(item.end_time) > slotStart);
		const past = new Date(`${date}T${time}:00`) <= new Date();
		let slotType = 'available';
		let message = 'Available. Select this slot as a start or end time.';
		if (roomIsUnavailable() || past) {
			slotType = 'unavailable';
			message = roomIsUnavailable() ? `${state.laboratory.status}. This room cannot be booked.` : 'This time has already passed.';
		} else if (reservation) {
			slotType = reservation.status === 'Approved' ? 'approved' : 'pending';
			message = reservation.status === 'Approved' ? 'Booked by an approved reservation.' : 'Pending approval. This time cannot be selected.';
		}
		if (state.selectionStart && isSelected(date, time)) slotType += ' selected';
		slot.className = `calendar-slot ${slotType}`;
		slot.type = 'button';
		slot.dataset.date = date;
		slot.dataset.start = time;
		slot.dataset.end = `${String(Math.floor(slotEnd / 60)).padStart(2, '0')}:${String(slotEnd % 60).padStart(2, '0')}`;
		slot.title = message;
		slot.setAttribute('aria-label', `${date} ${formatTime(`${time}:00`)}: ${message}`);
		if (reservation && time === reservation.start_time.slice(0, 5)) {
			slot.innerHTML = `<span class="reservation-time">${formatTime(reservation.start_time)}</span><span>${escapeHtml(reservation.course_section)}</span>${reservation.is_mine ? '<b class="mine-badge">Mine</b>' : ''}`;
		}
		if (slotType.startsWith('available')) slot.addEventListener('click', () => selectSlot(slot));
		else slot.addEventListener('click', () => { elements.calendarMessage.textContent = message; });
		return slot;
	}

	function selectSlot(slot) {
		const selected = { date: slot.dataset.date, start: slot.dataset.start, end: slot.dataset.end };
		if (!state.selectionStart || state.selectionEnd) {
			state.selectionStart = selected;
			state.selectionEnd = null;
			elements.calendarMessage.textContent = 'Start selected. Choose an available end time.';
		} else {
			if (selected.date !== state.selectionStart.date) {
				elements.calendarMessage.textContent = 'Choose an end time on the same day as the start time.';
			} else if (selected.start <= state.selectionStart.start) {
				state.selectionStart = selected;
				elements.calendarMessage.textContent = 'Start updated. Choose an end time after it.';
			} else {
				state.selectionEnd = selected;
				openBookingModal(state.selectionStart, selected);
			}
		}
		renderGrid();
	}

	function openBookingModal(start, end) {
		const room = state.laboratory;
		elements.bookingRoomId.value = state.selectedRoomId;
		elements.bookingRoomLabel.textContent = `${room.room_code} - ${room.room_name}`;
		elements.bookingTimeLabel.textContent = `${start.date}, ${formatTime(`${start.start}:00`)} - ${formatTime(`${end.end}:00`)}`;
		elements.bookingDate.value = start.date;
		elements.bookingStartTime.value = start.start;
		elements.bookingEndTime.value = end.end;
		elements.bookingAvailability.textContent = 'Checking availability...';
		elements.bookingAvailability.className = 'availability-message';
		elements.bookingFormMessage.textContent = '';
		elements.bookingForm.reset();
		elements.bookingRoomId.value = state.selectedRoomId;
		elements.bookingDate.value = start.date;
		elements.bookingStartTime.value = start.start;
		elements.bookingEndTime.value = end.end;
		if (typeof elements.bookingModal.showModal === 'function') elements.bookingModal.showModal();
		else elements.bookingModal.setAttribute('open', '');
		checkBookingAvailability();
	}

	function closeBookingModal() {
		if (typeof elements.bookingModal.close === 'function') elements.bookingModal.close();
		else elements.bookingModal.removeAttribute('open');
		state.selectionStart = null;
		state.selectionEnd = null;
		renderGrid();
	}

	async function checkBookingAvailability() {
		const params = new URLSearchParams({
			mode: 'check',
			room_id: elements.bookingRoomId.value,
			date: elements.bookingDate.value,
			start_time: elements.bookingStartTime.value,
			end_time: elements.bookingEndTime.value
		});
		try {
			const data = await fetchJson(`../api/availability.php?${params.toString()}`);
			elements.bookingAvailability.textContent = data.message;
			elements.bookingAvailability.className = `availability-message ${data.available ? 'is-available' : 'is-conflict'}`;
			elements.submitBooking.disabled = !data.available;
		} catch (error) {
			elements.bookingAvailability.textContent = error.message;
			elements.bookingAvailability.className = 'availability-message is-conflict';
			elements.submitBooking.disabled = true;
		}
	}

	async function submitBooking(event) {
		event.preventDefault();
		elements.submitBooking.disabled = true;
		elements.bookingFormMessage.textContent = 'Submitting request...';
		const formData = new FormData(elements.bookingForm);
		const payload = Object.fromEntries(formData.entries());
		try {
			const response = await fetch('../api/reserve.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify(payload)
			});
			const data = await response.json();
			if (!response.ok || !data.success) throw new Error(data.message || 'Unable to submit reservation.');
			closeBookingModal();
			elements.calendarMessage.textContent = data.message;
			await loadSchedule();
		} catch (error) {
			elements.bookingFormMessage.textContent = error.message;
			elements.submitBooking.disabled = false;
			checkBookingAvailability();
		}
	}

	function isSelected(date, time) {
		if (!state.selectionStart) return false;
		if (!state.selectionEnd) return date === state.selectionStart.date && time === state.selectionStart.start;
		const current = `${date}T${time}`;
		const start = `${state.selectionStart.date}T${state.selectionStart.start}`;
		const end = `${state.selectionEnd.date}T${state.selectionEnd.end}`;
		return current >= start && current < end;
	}

	function escapeHtml(value) {
		return String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
	}

	function slugify(value) {
		return value.toLowerCase().replace(/\s+/g, '-');
	}

	elements.labType.addEventListener('change', loadRooms);
	elements.roomSelect.addEventListener('change', () => selectRoom(elements.roomSelect.value));
	elements.calendarDate.addEventListener('change', () => {
		state.selectedDate = parseDate(elements.calendarDate.value);
		loadSchedule();
	});
	elements.todayButton.addEventListener('click', () => {
		state.selectedDate = new Date();
		loadSchedule();
	});
	elements.previousButton.addEventListener('click', () => {
		state.selectedDate = addDays(state.selectedDate, -7);
		loadSchedule();
	});
	elements.nextButton.addEventListener('click', () => {
		state.selectedDate = addDays(state.selectedDate, 7);
		loadSchedule();
	});
	elements.closeBookingModal.addEventListener('click', closeBookingModal);
	elements.cancelBooking.addEventListener('click', closeBookingModal);
	elements.bookingForm.addEventListener('submit', submitBooking);
	elements.bookingModal.addEventListener('click', (event) => {
		if (event.target === elements.bookingModal) closeBookingModal();
	});
	window.addEventListener('resize', renderGrid);

	elements.calendarDate.value = formatDate(state.selectedDate);
	loadRooms();
}());
