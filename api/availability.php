<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/release_noshows.php';

header('Content-Type: application/json; charset=utf-8');

start_app_session();
releaseNoShows($pdo);
if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Please log in to view laboratory availability.']);
	exit;
}

function availability_response(array $payload, int $status = 200): never
{
	http_response_code($status);
	echo json_encode($payload);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	availability_response(['success' => false, 'message' => 'Only GET requests are allowed.'], 405);
}

$mode = (string) ($_GET['mode'] ?? 'schedule');
$lab_type = trim((string) ($_GET['lab_type'] ?? ''));
$valid_lab_types = ['Cisco Laboratory', 'Regular Computer Laboratory'];

if ($mode === 'rooms') {
	if ($lab_type !== '' && !in_array($lab_type, $valid_lab_types, true)) {
		availability_response(['success' => false, 'message' => 'Invalid laboratory type.'], 400);
	}

	$query = 'SELECT room_id, room_name, room_code, lab_type, capacity, floor, status
			  FROM laboratories';
	$parameters = [];
	if ($lab_type !== '') {
		$query .= ' WHERE lab_type = :lab_type';
		$parameters['lab_type'] = $lab_type;
	}
	$query .= ' ORDER BY lab_type, room_code';

	$statement = $pdo->prepare($query);
	$statement->execute($parameters);
	availability_response(['success' => true, 'laboratories' => $statement->fetchAll()]);
}

if ($mode === 'check') {
	$room_id = filter_var($_GET['room_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
	$date = (string) ($_GET['date'] ?? '');
	$start_time = (string) ($_GET['start_time'] ?? '');
	$end_time = (string) ($_GET['end_time'] ?? '');
	$selected_date = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
	$date_errors = DateTimeImmutable::getLastErrors();
	$has_date_errors = is_array($date_errors) && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0);
	$start_minutes = time_to_minutes($start_time);
	$end_minutes = time_to_minutes($end_time);

	if ($room_id === false || !$selected_date || $has_date_errors || $date !== $selected_date->format('Y-m-d') || $date < date('Y-m-d') || $start_minutes === null || $end_minutes === null || $start_minutes < 420 || $end_minutes > 1260 || $end_minutes <= $start_minutes) {
		availability_response(['success' => false, 'message' => 'Choose a valid date and time between 7:00 AM and 9:00 PM.'], 400);
	}

	$room_statement = $pdo->prepare('SELECT room_id, room_name, room_code, capacity, status FROM laboratories WHERE room_id = :room_id LIMIT 1');
	$room_statement->execute(['room_id' => $room_id]);
	$room = $room_statement->fetch();
	if (!$room) {
		availability_response(['success' => false, 'message' => 'Laboratory not found.'], 404);
	}

	if ($room['status'] !== 'Available') {
		availability_response(['success' => true, 'available' => false, 'message' => $room['status'] . '. This laboratory cannot be booked.']);
	}

	$conflict_statement = $pdo->prepare(
		"SELECT reservation_id FROM reservations
		 WHERE room_id = :room_id AND date = :date
		   AND status IN ('Pending', 'Approved')
		   AND start_time < :new_end AND end_time > :new_start
		 LIMIT 1"
	);
	$conflict_statement->execute([
		'room_id' => $room_id,
		'date' => $date,
		'new_end' => $end_time,
		'new_start' => $start_time,
	]);

	$available = !$conflict_statement->fetch();
	availability_response([
		'success' => true,
		'available' => $available,
		'message' => $available ? 'Available' : 'Conflict: already reserved.',
	]);
}

$room_id = filter_var($_GET['room_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$start_date = (string) ($_GET['start_date'] ?? '');
$end_date = (string) ($_GET['end_date'] ?? '');

$start = DateTimeImmutable::createFromFormat('!Y-m-d', $start_date);
$end = DateTimeImmutable::createFromFormat('!Y-m-d', $end_date);
$date_errors = DateTimeImmutable::getLastErrors();
$has_date_errors = is_array($date_errors) && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0);

if ($room_id === false || !$start || !$end || $has_date_errors || $start_date !== $start->format('Y-m-d') || $end_date !== $end->format('Y-m-d') || $end < $start) {
	availability_response(['success' => false, 'message' => 'A valid room and date range are required.'], 400);
}

if ($start->diff($end)->days > 31) {
	availability_response(['success' => false, 'message' => 'The date range cannot exceed 31 days.'], 400);
}

$room_statement = $pdo->prepare(
	'SELECT room_id, room_name, room_code, lab_type, capacity, floor, status
	 FROM laboratories WHERE room_id = :room_id LIMIT 1'
);
$room_statement->execute(['room_id' => $room_id]);
$room = $room_statement->fetch();

if (!$room) {
	availability_response(['success' => false, 'message' => 'Laboratory not found.'], 404);
}

$reservation_statement = $pdo->prepare(
		"SELECT reservation_id, date, start_time, end_time, status, course_section, checked_in_at,
			CASE WHEN user_id = :current_user THEN 1 ELSE 0 END AS is_mine
	 FROM reservations
	 WHERE room_id = :room_id
	   AND date BETWEEN :start_date AND :end_date
	   AND status IN ('Pending', 'Approved')
	 ORDER BY date, start_time"
);
$reservation_statement->execute([
	'current_user' => $_SESSION['user_id'],
	'room_id' => $room_id,
	'start_date' => $start_date,
	'end_date' => $end_date,
]);

$reservations = array_map(static function (array $reservation): array {
	$reservation['reservation_id'] = (int) $reservation['reservation_id'];
	$reservation['is_mine'] = (bool) $reservation['is_mine'];
	return $reservation;
}, $reservation_statement->fetchAll());

availability_response([
	'success' => true,
	'laboratory' => $room,
	'reservations' => $reservations,
]);

function time_to_minutes(string $time): ?int
{
	if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) {
		return null;
	}

	[$hours, $minutes] = array_map('intval', explode(':', $time));
	return ($hours * 60) + $minutes;
}
