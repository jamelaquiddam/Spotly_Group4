<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_login();

$page_title = 'My Reservations';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
	<p class="eyebrow">Your booking history</p>
	<h1>My Reservations</h1>
	<p class="muted">Track requests and manage reservations. Approved bookings must be checked in within 20 minutes of starting.</p>
</section>

<section class="reservation-panel card">
	<div class="reservation-toolbar">
		<div>
			<h2>Reservation requests</h2>
			<p id="reservation-count" class="muted"></p>
		</div>
		<div class="form-group filter-control">
			<label for="reservation-status">Filter by status</label>
			<select id="reservation-status">
				<option value="">All reservations</option>
				<option value="Pending">Pending</option>
				<option value="Approved">Approved</option>
				<option value="Rejected">Rejected</option>
				<option value="Cancelled">Cancelled</option>
				<option value="No-Show">No-Show</option>
			</select>
		</div>
	</div>
	<p id="reservation-message" class="calendar-message" aria-live="polite"></p>
	<div class="table-wrap">
		<table class="data-table reservation-table">
			<thead>
				<tr>
					<th>Laboratory</th>
					<th>Date and time</th>
					<th>Purpose</th>
					<th>Attendees</th>
					<th>Status</th>
					<th>Date submitted</th>
					<th><span class="sr-only">Actions</span></th>
				</tr>
			</thead>
			<tbody id="reservation-body"></tbody>
		</table>
	</div>
</section>
<dialog id="cancel-modal" class="booking-modal"><div class="modal-header"><div><p class="eyebrow">Cancel reservation</p><h2>Are you sure?</h2></div><button id="close-cancel-modal" class="modal-close" type="button" aria-label="Close">&times;</button></div><form id="cancel-form" class="form-stack"><input type="hidden" id="cancel-reservation-id"><div class="form-group"><label for="cancel-reason">Optional reason</label><textarea id="cancel-reason" maxlength="255" rows="4"></textarea></div><div id="cancel-message" class="form-message"></div><div class="modal-actions"><button id="cancel-no" class="button button-outline-dark" type="button">Keep reservation</button><button class="button button-primary" type="submit">Cancel reservation</button></div></form></dialog>
<script>
	window.spotlyReservationCsrf = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="../assets/js/reservations.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
