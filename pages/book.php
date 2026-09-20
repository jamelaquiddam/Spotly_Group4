<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_login();

$page_title = 'Book a Lab';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
	<p class="eyebrow">Guided booking</p>
	<h1>Book a laboratory</h1>
	<p class="muted">Choose a room and time, then submit your reservation request for DOIT review.</p>
	<p class="alert alert-info">Reservations are released if you do not check in within 20 minutes of the start time.</p>
</section>

<section class="booking-flow card">
	<div class="step-indicator" aria-label="Booking steps">
		<span class="active" data-step-indicator="1">1. Lab type</span>
		<span data-step-indicator="2">2. Room</span>
		<span data-step-indicator="3">3. Date and time</span>
		<span data-step-indicator="4">4. Confirm</span>
	</div>
	<form id="step-booking-form" class="form-stack">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
		<section class="booking-step active" data-step="1">
			<p class="eyebrow">Step 1</p>
			<h2>Select a laboratory type</h2>
			<div class="form-group">
				<label for="book-lab-type">Lab type</label>
				<select id="book-lab-type" required>
					<option value="">Choose a type</option>
					<option value="Cisco Laboratory">Cisco Laboratory</option>
					<option value="Regular Computer Laboratory">Regular Computer Laboratory</option>
				</select>
			</div>
			<div class="step-actions"><button class="button button-primary" type="button" data-next="2">Continue</button></div>
		</section>

		<section class="booking-step" data-step="2">
			<p class="eyebrow">Step 2</p>
			<h2>Choose a room</h2>
			<div class="form-group">
				<label for="book-room">Room</label>
				<select id="book-room" required></select>
				<span id="book-room-info" class="field-note"></span>
			</div>
			<div class="step-actions">
				<button class="button button-outline-dark" type="button" data-back="1">Back</button>
				<button class="button button-primary" type="button" data-next="3">Continue</button>
			</div>
		</section>

		<section class="booking-step" data-step="3">
			<p class="eyebrow">Step 3</p>
			<h2>Choose a date and time</h2>
			<div class="form-grid">
				<div class="form-group">
					<label for="book-date">Date</label>
					<input id="book-date" type="date" name="date" required>
				</div>
				<div class="form-group">
					<label for="book-start-time">Start time</label>
					<input id="book-start-time" type="time" name="start_time" min="07:00" max="20:30" step="1800" required>
				</div>
				<div class="form-group">
					<label for="book-end-time">End time</label>
					<input id="book-end-time" type="time" name="end_time" min="07:30" max="21:00" step="1800" required>
				</div>
				<div id="step-availability" class="availability-message" aria-live="polite">Choose a date and time to check availability.</div>
			</div>
			<div class="form-grid">
				<div class="form-group">
					<label for="book-purpose">Purpose</label>
					<input id="book-purpose" type="text" name="purpose" maxlength="150" required>
				</div>
				<div class="form-group">
					<label for="book-course-section">Course/section</label>
					<input id="book-course-section" type="text" name="course_section" maxlength="30" required>
				</div>
				<div class="form-group">
					<label for="book-attendees">Expected attendees</label>
					<input id="book-attendees" type="number" name="expected_attendees" min="1" required>
				</div>
			</div>
			<div class="step-actions">
				<button class="button button-outline-dark" type="button" data-back="2">Back</button>
				<button class="button button-primary" type="button" data-next="4">Review request</button>
			</div>
		</section>

		<section class="booking-step" data-step="4">
			<p class="eyebrow">Step 4</p>
			<h2>Confirm reservation request</h2>
			<p class="alert alert-info">Reservations are released if you do not check in within 20 minutes of the start time.</p>
			<dl id="booking-summary" class="booking-summary-list"></dl>
			<div id="step-form-message" class="form-message" aria-live="polite"></div>
			<div class="step-actions">
				<button class="button button-outline-dark" type="button" data-back="3">Edit</button>
				<button id="step-submit" class="button button-primary" type="submit">Submit reservation</button>
			</div>
		</section>
	</form>
</section>
<script src="../assets/js/booking.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
