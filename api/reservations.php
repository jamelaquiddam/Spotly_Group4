<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
start_app_session();

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
	$valid_statuses = ['Pending', 'Approved', 'Rejected'];
	return $status !== null && in_array($status, $valid_statuses, true) ? $status : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	$status = reservation_status_filter(isset($_GET['status']) ? (string) $_GET['status'] : null);
	$query =
		"SELECT r.reservation_id, l.lab_type, l.room_name, l.room_code, l.capacity, l.floor,
				r.date, r.start_time, r.end_time, r.purpose, r.course_section,
				r.expected_attendees, r.status, r.created_at
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
	$reservations_response(['success' => true, 'reservations' => $statement->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	reservations_response(['success' => false, 'message' => 'Only GET and POST requests are allowed.'], 405);
}

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
$input = is_array($input) ? $input : $_POST;

if (($input['action'] ?? '') !== 'cancel') {
	reservations_response(['success' => false, 'message' => 'Unsupported reservation action.'], 400);
}
if (!verify_csrf_token($input['csrf_token'] ?? null)) {
	reservations_response(['success' => false, 'message' => 'Your form session expired. Refresh the page and try again.'], 419);
}

$reservation_id = filter_var($input['reservation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($reservation_id === false) {
	reservations_response(['success' => false, 'message' => 'A valid reservation is required.'], 400);
}

try {
	$pdo->beginTransaction();
	$lock_statement = $pdo->prepare(
		'SELECT reservation_id, status FROM reservations
		 WHERE reservation_id = :reservation_id AND user_id = :user_id FOR UPDATE'
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
	if ($reservation['status'] !== 'Pending') {
		$pdo->rollBack();
		reservations_response(['success' => false, 'message' => 'Only pending reservations can be cancelled.'], 409);
	}

	$notification_delete = $pdo->prepare('DELETE FROM notifications WHERE reservation_id = :reservation_id');
	$notification_delete->execute(['reservation_id' => $reservation_id]);
	$reservation_delete = $pdo->prepare('DELETE FROM reservations WHERE reservation_id = :reservation_id AND user_id = :user_id AND status = \'Pending\'');
	$reservation_delete->execute([
		'reservation_id' => $reservation_id,
		'user_id' => $_SESSION['user_id'],
	]);
	$pdo->commit();

	reservations_response(['success' => true, 'message' => 'Pending reservation cancelled.']);
} catch (Throwable $exception) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	reservations_response(['success' => false, 'message' => 'The reservation could not be cancelled. Please try again.'], 500);
}
