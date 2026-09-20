<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_login();

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
	<p class="eyebrow">Your Spotly workspace</p>
	<h1>Welcome, <?= htmlspecialchars((string) $_SESSION['first_name'], ENT_QUOTES, 'UTF-8') ?></h1>
	<p class="muted">Check laboratory availability and find a time that works for your class.</p>
</section>

<section class="dashboard-toolbar card" aria-label="Calendar filters">
	<div class="form-group">
		<label for="lab-type">Lab type</label>
		<select id="lab-type">
			<option value="">All laboratories</option>
			<option value="Cisco Laboratory">Cisco Laboratory</option>
			<option value="Regular Computer Laboratory">Regular Computer Laboratory</option>
		</select>
	</div>
	<div class="form-group">
		<label for="room-select">Room</label>
		<select id="room-select" aria-describedby="room-status" required></select>
		<span id="room-status" class="field-note" aria-live="polite"></span>
	</div>
	<div class="form-group">
		<label for="calendar-date">Calendar date</label>
		<input id="calendar-date" type="date">
	</div>
	<div class="calendar-actions">
		<button class="button button-outline-dark" id="today-button" type="button">Today</button>
		<button class="button button-outline-dark" id="previous-button" type="button" aria-label="Previous week">&larr; Prev</button>
		<button class="button button-outline-dark" id="next-button" type="button" aria-label="Next week">Next &rarr;</button>
	</div>
</section>

<section class="calendar-section">
	<div class="calendar-heading">
		<div>
			<p class="eyebrow">Weekly availability</p>
			<h2 id="calendar-title">Loading schedule...</h2>
		</div>
		<div class="calendar-legend" aria-label="Availability legend">
			<span><i class="legend-swatch available"></i>Available</span>
			<span><i class="legend-swatch pending"></i>Pending</span>
			<span><i class="legend-swatch approved"></i>Booked</span>
			<span><i class="legend-swatch unavailable"></i>Unavailable</span>
		</div>
	</div>
	<p id="calendar-message" class="calendar-message" aria-live="polite"></p>
	<div id="calendar-grid" class="calendar-grid" aria-live="polite"></div>
	<p class="calendar-hint">Select an available start time, then an end time to request a reservation.</p>
</section>

<section class="catalog-section">
	<div class="section-heading">
		<div>
			<p class="eyebrow">Explore the rooms</p>
			<h2>Laboratory Catalog</h2>
		</div>
		<span id="catalog-count" class="catalog-count"></span>
	</div>
	<div class="table-wrap">
		<table class="catalog-table">
			<thead>
				<tr>
					<th>Room</th>
					<th>Type</th>
					<th>Capacity</th>
					<th>Floor</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody id="catalog-body"></tbody>
		</table>
	</div>
</section>
<dialog id="booking-modal" class="booking-modal">
	<div class="modal-header">
		<div>
			<p class="eyebrow">New reservation</p>
			<h2>Request this time</h2>
		</div>
		<button id="close-booking-modal" class="modal-close" type="button" aria-label="Close booking form">&times;</button>
	</div>
	<form id="booking-form" class="form-stack">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
		<input type="hidden" id="booking-room-id" name="room_id">
		<div class="booking-summary">
			<strong id="booking-room-label"></strong>
			<span id="booking-time-label"></span>
		</div>
		<input type="hidden" id="booking-date" name="date">
		<input type="hidden" id="booking-start-time" name="start_time">
		<input type="hidden" id="booking-end-time" name="end_time">
		<div id="booking-availability" class="availability-message" aria-live="polite"></div>
		<div class="form-group">
			<label for="booking-purpose">Purpose</label>
			<input id="booking-purpose" name="purpose" maxlength="150" required>
		</div>
		<div class="form-group">
			<label for="booking-course-section">Course/section</label>
			<input id="booking-course-section" name="course_section" maxlength="30" required>
		</div>
		<div class="form-group">
			<label for="booking-attendees">Expected attendees</label>
			<input id="booking-attendees" name="expected_attendees" type="number" min="1" required>
		</div>
		<div id="booking-form-message" class="form-message" aria-live="polite"></div>
		<div class="modal-actions">
			<button id="cancel-booking" class="button button-outline-dark" type="button">Cancel</button>
			<button id="submit-booking" class="button button-primary" type="submit">Submit request</button>
		</div>
	</form>
</dialog>
<script src="../assets/js/calendar.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
