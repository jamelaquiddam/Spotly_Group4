<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_role('DOIT Staff/Admin');

$valid_lab_types = ['Cisco Laboratory', 'Regular Computer Laboratory'];
$valid_statuses = ['Pending', 'Approved', 'Rejected'];
$filter_lab_type = trim((string) ($_GET['lab_type'] ?? ''));
$filter_status = trim((string) ($_GET['status'] ?? ''));
$filter_date = trim((string) ($_GET['date'] ?? ''));

if (!in_array($filter_lab_type, $valid_lab_types, true)) {
	$filter_lab_type = '';
}
if (!in_array($filter_status, $valid_statuses, true)) {
	$filter_status = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) {
	$filter_date = '';
}

$pending_statement = $pdo->prepare(
	"SELECT r.reservation_id, r.date, r.start_time, r.end_time, r.purpose, r.course_section,
			r.expected_attendees, r.status, u.first_name, u.last_name, u.role,
			l.lab_type, l.room_name, l.room_code
	 FROM reservations r
	 INNER JOIN users u ON u.user_id = r.user_id
	 INNER JOIN laboratories l ON l.room_id = r.room_id
	 WHERE r.status = 'Pending'
	 ORDER BY r.date, r.start_time, r.created_at"
);
$pending_statement->execute();
$pending_reservations = $pending_statement->fetchAll();

$approved_today_statement = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE status = 'Approved' AND DATE(approved_at) = CURDATE()");
$approved_today_statement->execute();
$total_statement = $pdo->prepare('SELECT COUNT(*) FROM reservations');
$total_statement->execute();
$maintenance_statement = $pdo->prepare("SELECT COUNT(*) FROM laboratories WHERE status = 'Under Maintenance'");
$maintenance_statement->execute();
$summary = [
	'pending' => count($pending_reservations),
	'approved_today' => (int) $approved_today_statement->fetchColumn(),
	'total' => (int) $total_statement->fetchColumn(),
	'maintenance' => (int) $maintenance_statement->fetchColumn(),
];

$all_query =
	"SELECT r.reservation_id, r.date, r.start_time, r.end_time, r.purpose, r.course_section,
			r.expected_attendees, r.status, r.created_at, u.first_name, u.last_name,
			l.lab_type, l.room_code, l.room_name
	 FROM reservations r
	 INNER JOIN users u ON u.user_id = r.user_id
	 INNER JOIN laboratories l ON l.room_id = r.room_id
	 WHERE 1 = 1";
$all_parameters = [];
if ($filter_lab_type !== '') {
	$all_query .= ' AND l.lab_type = :lab_type';
	$all_parameters['lab_type'] = $filter_lab_type;
}
if ($filter_status !== '') {
	$all_query .= ' AND r.status = :status';
	$all_parameters['status'] = $filter_status;
}
if ($filter_date !== '') {
	$all_query .= ' AND r.date = :date';
	$all_parameters['date'] = $filter_date;
}
$all_query .= ' ORDER BY r.date DESC, r.start_time DESC, r.created_at DESC';
$all_statement = $pdo->prepare($all_query);
$all_statement->execute($all_parameters);
$all_reservations = $all_statement->fetchAll();

$laboratory_statement = $pdo->prepare('SELECT room_id, room_name, room_code, lab_type, capacity, floor, status FROM laboratories ORDER BY lab_type, room_code');
$laboratory_statement->execute();
$laboratories = $laboratory_statement->fetchAll();

$page_title = 'Admin';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
	<p class="eyebrow">DOIT control center</p>
	<h1>Administration</h1>
	<p class="muted">Review reservation requests and keep the laboratory catalog current.</p>
</section>

<section class="admin-summary-grid">
	<div class="summary-card card"><span>Pending requests</span><strong><?= $summary['pending'] ?></strong></div>
	<div class="summary-card card"><span>Approved today</span><strong><?= $summary['approved_today'] ?></strong></div>
	<div class="summary-card card"><span>Total reservations</span><strong><?= $summary['total'] ?></strong></div>
	<div class="summary-card card"><span>Labs under maintenance</span><strong><?= $summary['maintenance'] ?></strong></div>
</section>

<section class="admin-section card">
	<div class="section-heading">
		<div><p class="eyebrow">Needs attention</p><h2>Pending Requests</h2></div>
		<span class="catalog-count"><?= count($pending_reservations) ?> request<?= count($pending_reservations) === 1 ? '' : 's' ?></span>
	</div>
	<p id="admin-message" class="calendar-message" aria-live="polite"></p>
	<div class="table-wrap">
		<table class="data-table admin-table">
			<thead><tr><th>Requester</th><th>Laboratory</th><th>Date and time</th><th>Purpose</th><th>Course/section</th><th>Attendees</th><th>Actions</th></tr></thead>
			<tbody>
			<?php if (!$pending_reservations): ?>
				<tr><td colspan="7" class="empty-table">There are no pending requests.</td></tr>
			<?php else: ?>
				<?php foreach ($pending_reservations as $reservation): ?>
					<tr>
						<td><strong><?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name'], ENT_QUOTES, 'UTF-8') ?></strong><span class="table-subtext"><?= htmlspecialchars($reservation['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
						<td><strong><?= htmlspecialchars($reservation['room_code'], ENT_QUOTES, 'UTF-8') ?></strong><span class="table-subtext"><?= htmlspecialchars($reservation['lab_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
						<td><?= htmlspecialchars($reservation['date'], ENT_QUOTES, 'UTF-8') ?><span class="table-subtext"><?= htmlspecialchars(date('g:i A', strtotime($reservation['start_time'])) . ' - ' . date('g:i A', strtotime($reservation['end_time'])), ENT_QUOTES, 'UTF-8') ?></span></td>
						<td><?= htmlspecialchars($reservation['purpose'], ENT_QUOTES, 'UTF-8') ?></td>
						<td><?= htmlspecialchars($reservation['course_section'], ENT_QUOTES, 'UTF-8') ?></td>
						<td><?= (int) $reservation['expected_attendees'] ?></td>
						<td class="admin-actions"><button class="button button-small button-primary decision-button" data-action="approve" data-reservation-id="<?= (int) $reservation['reservation_id'] ?>" type="button">Approve</button><button class="button button-small reject-button decision-button" data-action="reject" data-reservation-id="<?= (int) $reservation['reservation_id'] ?>" type="button">Reject</button></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</section>

<section class="admin-section card">
	<div class="reservation-toolbar">
		<div><p class="eyebrow">Complete record</p><h2>All Reservations</h2></div>
		<form class="admin-filters" method="get">
			<select name="lab_type" aria-label="Filter by lab type"><option value="">All lab types</option><?php foreach ($valid_lab_types as $lab_type): ?><option value="<?= htmlspecialchars($lab_type, ENT_QUOTES, 'UTF-8') ?>" <?= $filter_lab_type === $lab_type ? 'selected' : '' ?>><?= htmlspecialchars($lab_type, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
			<input type="date" name="date" aria-label="Filter by date" value="<?= htmlspecialchars($filter_date, ENT_QUOTES, 'UTF-8') ?>">
			<select name="status" aria-label="Filter by status"><option value="">All statuses</option><?php foreach ($valid_statuses as $status): ?><option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= $filter_status === $status ? 'selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
			<button class="button button-outline-dark button-small" type="submit">Filter</button>
		</form>
	</div>
	<div class="table-wrap">
		<table class="data-table admin-table">
			<thead><tr><th>Requester</th><th>Laboratory</th><th>Date and time</th><th>Purpose</th><th>Course/section</th><th>Attendees</th><th>Status</th></tr></thead>
			<tbody>
			<?php if (!$all_reservations): ?><tr><td colspan="7" class="empty-table">No reservations match these filters.</td></tr>
			<?php else: foreach ($all_reservations as $reservation): ?><tr>
				<td><?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
				<td><strong><?= htmlspecialchars($reservation['room_code'], ENT_QUOTES, 'UTF-8') ?></strong><span class="table-subtext"><?= htmlspecialchars($reservation['lab_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
				<td><?= htmlspecialchars($reservation['date'], ENT_QUOTES, 'UTF-8') ?><span class="table-subtext"><?= htmlspecialchars(date('g:i A', strtotime($reservation['start_time'])) . ' - ' . date('g:i A', strtotime($reservation['end_time'])), ENT_QUOTES, 'UTF-8') ?></span></td>
				<td><?= htmlspecialchars($reservation['purpose'], ENT_QUOTES, 'UTF-8') ?></td>
				<td><?= htmlspecialchars($reservation['course_section'], ENT_QUOTES, 'UTF-8') ?></td>
				<td><?= (int) $reservation['expected_attendees'] ?></td>
				<td><span class="status-pill status-<?= htmlspecialchars(strtolower($reservation['status']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($reservation['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
			</tr><?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
</section>

<section class="admin-section card">
	<div class="section-heading"><div><p class="eyebrow">Room availability</p><h2>Laboratory Management</h2></div></div>
	<div class="table-wrap">
		<table class="data-table admin-table"><thead><tr><th>Room</th><th>Type</th><th>Capacity</th><th>Floor</th><th>Status</th><th>Update</th></tr></thead><tbody>
		<?php foreach ($laboratories as $laboratory): ?><tr>
			<td><strong><?= htmlspecialchars($laboratory['room_code'], ENT_QUOTES, 'UTF-8') ?></strong><span class="table-subtext"><?= htmlspecialchars($laboratory['room_name'], ENT_QUOTES, 'UTF-8') ?></span></td>
			<td><?= htmlspecialchars($laboratory['lab_type'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $laboratory['capacity'] ?></td><td><?= htmlspecialchars($laboratory['floor'], ENT_QUOTES, 'UTF-8') ?></td>
			<td><span class="status-pill status-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $laboratory['status'])), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($laboratory['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
			<td><select class="lab-status-select" data-room-id="<?= (int) $laboratory['room_id'] ?>" aria-label="Status for <?= htmlspecialchars($laboratory['room_code'], ENT_QUOTES, 'UTF-8') ?>"><option>Available</option><option <?= $laboratory['status'] === 'Under Maintenance' ? 'selected' : '' ?>>Under Maintenance</option><option <?= $laboratory['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></td>
		</tr><?php endforeach; ?></tbody></table>
	</div>
</section>
<script>window.spotlyAdminCsrf = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="../assets/js/admin.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
