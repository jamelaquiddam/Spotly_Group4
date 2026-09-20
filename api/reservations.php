<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/release_noshows.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json; charset=utf-8');
start_app_session();
releaseNoShows($pdo);

function reservations_response(array $payload, int $status = 200): never
{
	http_response_code($status);
	echo json_encode($payload);
	exit;
}

if (!isset($_SESSION['user_id'])) {
	reservations_response(['success' => false, 'message' => 'Please log in to view reservations.'], 401);
}

function reservation_status_filter(?string $status): ?string
{
	$valid_statuses = ['Pending', 'Approved', 'Rejected', 'Cancelled', 'No-Show'];
	return $status !== null && in_array($status, $valid_statuses, true) ? $status : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$status = reservation_status_filter(isset($_GET['status']) ? (string) $_GET['status'] : null);
	$query =
		"SELECT r.reservation_id, l.lab_type, l.room_name, l.room_code, l.capacity, l.floor,
				r.date, r.start_time, r.end_time, r.purpose, r.course_section,
				r.expected_attendees, r.status, r.created_at, r.cancelled_at, r.cancel_reason,
				r.checked_in_at, r.released_at
		 FROM reservations r
		 INNER JOIN laboratories l ON l.room_id = r.room_id
		 WHERE r.user_id = :user_id";
	$parameters = ['user_id' => $_SESSION['user_id']];
	if ($status !== null) {
		$query .= ' AND r.status = :status';
		$parameters['status'] = $status;
	}
	$query .= ' ORDER BY r.date DESC, r.start_time DESC, r.created_at DESC';

	$statement = $pdo->prepare($query);
	$statement->execute($parameters);
	reservations_response(['success' => true, 'reservations' => $statement->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	reservations_response(['success' => false, 'message' => 'Only GET and POST requests are allowed.'], 405);
}

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
$input = is_array($input) ? $input : $_POST;

if (!verify_csrf_token($input['csrf_token'] ?? null)) {
	reservations_response(['success' => false, 'message' => 'Your form session expired. Refresh the page and try again.'], 419);
}

$action = (string) ($input['action'] ?? '');
$reservation_id = filter_var($input['reservation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($reservation_id === false) {
	reservations_response(['success' => false, 'message' => 'A valid reservation is required.'], 400);
}

try {
	$pdo->beginTransaction();
	$lock_statement = $pdo->prepare(
		'SELECT r.reservation_id, r.status, r.date, r.start_time, r.end_time, r.room_id, l.room_code
		 FROM reservations r INNER JOIN laboratories l ON l.room_id = r.room_id
		 WHERE r.reservation_id = :reservation_id AND r.user_id = :user_id FOR UPDATE'
	);
	$lock_statement->execute([
		'reservation_id' => $reservation_id,
		'user_id' => $_SESSION['user_id'],
	]);
	$reservation = $lock_statement->fetch();

	if (!$reservation) {
		$pdo->rollBack();
		reservations_response(['success' => false, 'message' => 'Reservation not found or you do not own it.'], 404);
	}
	if ($action === 'check_in') {
		if ($reservation['status'] !== 'Approved') {
			$pdo->rollBack();
			reservations_response(['success' => false, 'message' => 'Only approved reservations can be checked in.'], 409);
		}
		$start = strtotime($reservation['date'] . ' ' . $reservation['start_time']);
		$now = time();
		if ($now < $start - 600 || $now > $start + (GRACE_PERIOD_MINUTES * 60)) {
			$pdo->rollBack();
			reservations_response(['success' => false, 'message' => 'Check-in is available from 10 minutes before the start until ' . GRACE_PERIOD_MINUTES . ' minutes after it.'], 409);
		}
		$update = $pdo->prepare('UPDATE reservations SET checked_in_at = NOW() WHERE reservation_id = :reservation_id AND checked_in_at IS NULL');
		$update->execute(['reservation_id' => $reservation_id]);
		$pdo->commit();
		reservations_response(['success' => true, 'message' => 'You are checked in.']);
	}

	if ($action !== 'cancel') {
		$pdo->rollBack();
		reservations_response(['success' => false, 'message' => 'Unsupported reservation action.'], 400);
	}
	if (!in_array($reservation['status'], ['Pending', 'Approved'], true)) {
		$pdo->rollBack();
		reservations_response(['success' => false, 'message' => 'Only pending or approved reservations can be cancelled.'], 409);
	}
	if (strtotime($reservation['date'] . ' ' . $reservation['start_time']) <= time()) {
		$pdo->rollBack();
		reservations_response(['success' => false, 'message' => 'This reservation can no longer be cancelled because it has started.'], 409);
	}
	$reason = trim((string) ($input['reason'] ?? ''));
	if (strlen($reason) > 255) {
		$pdo->rollBack();
		reservations_response(['success' => false, 'message' => 'The cancellation reason is too long.'], 400);
	}
	$was_approved = $reservation['status'] === 'Approved';
	$update = $pdo->prepare("UPDATE reservations SET status = 'Cancelled', cancelled_at = NOW(), cancel_reason = :reason WHERE reservation_id = :reservation_id AND user_id = :user_id AND status IN ('Pending', 'Approved')");
	$update->execute(['reason' => $reason !== '' ? $reason : null, 'reservation_id' => $reservation_id, 'user_id' => $_SESSION['user_id']]);
	$notify = $pdo->prepare('INSERT INTO notifications (user_id, reservation_id, message) VALUES (:user_id, :reservation_id, :message)');
	$notify->execute(['user_id' => $_SESSION['user_id'], 'reservation_id' => $reservation_id, 'message' => 'You cancelled your reservation for ' . $reservation['room_code'] . ' on ' . $reservation['date'] . ', ' . date('g:i A', strtotime($reservation['start_time'])) . '-' . date('g:i A', strtotime($reservation['end_time'])) . '.']);
	if ($was_approved) {
		$admin_statement = $pdo->prepare("SELECT user_id FROM users WHERE role = 'DOIT Staff/Admin' AND is_active = 1");
		$admin_statement->execute();
		$admins = $admin_statement->fetchAll();
		$admin_notify = $pdo->prepare('INSERT INTO notifications (user_id, reservation_id, message) VALUES (:user_id, :reservation_id, :message)');
		foreach ($admins as $admin) $admin_notify->execute(['user_id' => $admin['user_id'], 'reservation_id' => $reservation_id, 'message' => 'An approved reservation for ' . $reservation['room_code'] . ' on ' . $reservation['date'] . ' was cancelled by the requester.']);
	}
	$pdo->commit();

	reservations_response(['success' => true, 'message' => 'Reservation cancelled.']);
} catch (Throwable $exception) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	reservations_response(['success' => false, 'message' => 'The reservation could not be cancelled. Please try again.'], 500);
}
