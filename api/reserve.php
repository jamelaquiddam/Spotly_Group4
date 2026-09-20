<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/release_noshows.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

start_app_session();
releaseNoShows($pdo);

function reservation_response(array $payload, int $status = 200): never
{
	http_response_code($status);
	echo json_encode($payload);
	exit;
}

if (!isset($_SESSION['user_id'])) {
	reservation_response(['success' => false, 'message' => 'Please log in before making a reservation.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	reservation_response(['success' => false, 'message' => 'Only POST requests are allowed.'], 405);
}

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
$input = is_array($input) ? $input : $_POST;

if (!verify_csrf_token($input['csrf_token'] ?? null)) {
	reservation_response(['success' => false, 'message' => 'Your form session expired. Refresh the page and try again.'], 419);
}

$room_id = filter_var($input['room_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$date = trim((string) ($input['date'] ?? ''));
$start_time = trim((string) ($input['start_time'] ?? ''));
$end_time = trim((string) ($input['end_time'] ?? ''));
$purpose = trim((string) ($input['purpose'] ?? ''));
$course_section = trim((string) ($input['course_section'] ?? ''));
$expected_attendees = filter_var($input['expected_attendees'] ?? null, FILTER_VALIDATE_INT);
$selected_date = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
$date_errors = DateTimeImmutable::getLastErrors();
$has_date_errors = is_array($date_errors) && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0);

if ($room_id === false) {
	reservation_response(['success' => false, 'message' => 'Please choose a valid laboratory.'], 400);
}
if (!$selected_date || $has_date_errors || $date !== $selected_date->format('Y-m-d') || $date < date('Y-m-d')) {
	reservation_response(['success' => false, 'message' => 'The reservation date must be today or a future date.'], 400);
}

$no_show_statement = $pdo->prepare("SELECT COUNT(*) AS no_show_count, MAX(released_at) AS latest_no_show FROM reservations WHERE user_id = :user_id AND status = 'No-Show' AND released_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$no_show_statement->execute(['user_id' => $_SESSION['user_id']]);
$no_show_summary = $no_show_statement->fetch();
if ((int) $no_show_summary['no_show_count'] >= NO_SHOW_LIMIT_30_DAYS && $no_show_summary['latest_no_show'] && strtotime($no_show_summary['latest_no_show'] . ' +' . NO_SHOW_BLOCK_DAYS . ' days') > time()) {
	$reservation_response(['success' => false, 'message' => 'Booking is temporarily blocked for ' . NO_SHOW_BLOCK_DAYS . ' days because you have reached the recent no-show limit.'], 403);
}

$start_minutes = reserve_time_to_minutes($start_time);
$end_minutes = reserve_time_to_minutes($end_time);
if ($start_minutes === null || $end_minutes === null || $end_minutes <= $start_minutes || $start_minutes < 420 || $end_minutes > 1260) {
	reservation_response(['success' => false, 'message' => 'The time must be between 7:00 AM and 9:00 PM, with the end time after the start time.'], 400);
}
if ($date === date('Y-m-d')) {
	$rounded_now = (int) (ceil((time() - strtotime('today')) / (BOOKING_START_SLOT_MINUTES * 60)) * BOOKING_START_SLOT_MINUTES);
	if ($start_minutes < $rounded_now) reservation_response(['success' => false, 'message' => 'Today\'s reservation must start at or after the next available 30-minute slot.'], 400);
}
if ($purpose === '' || strlen($purpose) > 150) {
	reservation_response(['success' => false, 'message' => 'Purpose is required and must be 150 characters or fewer.'], 400);
}
if ($course_section === '' || strlen($course_section) > 30) {
	reservation_response(['success' => false, 'message' => 'Course/section is required and must be 30 characters or fewer.'], 400);
}
if ($expected_attendees === false || $expected_attendees < 1) {
	reservation_response(['success' => false, 'message' => 'Expected attendees must be at least 1.'], 400);
}

try {
	$pdo->beginTransaction();

	$room_statement = $pdo->prepare(
		'SELECT room_id, room_name, room_code, capacity, status
		 FROM laboratories WHERE room_id = :room_id FOR UPDATE'
	);
	$room_statement->execute(['room_id' => $room_id]);
	$room = $room_statement->fetch();

	if (!$room) {
		$pdo->rollBack();
		reservation_response(['success' => false, 'message' => 'Laboratory not found.'], 404);
	}
	if ($room['status'] !== 'Available') {
		$pdo->rollBack();
		reservation_response(['success' => false, 'message' => $room['status'] . '. This laboratory cannot be booked.'], 409);
	}
	if ($expected_attendees > (int) $room['capacity']) {
		$pdo->rollBack();
		reservation_response(['success' => false, 'message' => 'Expected attendees cannot exceed the room capacity of ' . $room['capacity'] . '.'], 400);
	}

	$conflict_statement = $pdo->prepare(
		"SELECT reservation_id FROM reservations
		 WHERE room_id = :room_id AND date = :date
		   AND status IN ('Pending', 'Approved')
		   AND start_time < :new_end AND end_time > :new_start
		 FOR UPDATE"
	);
	$conflict_statement->execute([
		'room_id' => $room_id,
		'date' => $date,
		'new_end' => $end_time,
		'new_start' => $start_time,
	]);
	if ($conflict_statement->fetch()) {
		$pdo->rollBack();
		reservation_response(['success' => false, 'message' => 'Conflict: this laboratory is already reserved during that time.'], 409);
	}

	$reservation_statement = $pdo->prepare(
		"INSERT INTO reservations
		 (user_id, room_id, date, start_time, end_time, purpose, course_section, expected_attendees, status)
		 VALUES (:user_id, :room_id, :date, :start_time, :end_time, :purpose, :course_section, :expected_attendees, 'Pending')"
	);
	$reservation_statement->execute([
		'user_id' => $_SESSION['user_id'],
		'room_id' => $room_id,
		'date' => $date,
		'start_time' => $start_time,
		'end_time' => $end_time,
		'purpose' => $purpose,
		'course_section' => $course_section,
		'expected_attendees' => $expected_attendees,
	]);
	$reservation_id = (int) $pdo->lastInsertId();

	$notification_statement = $pdo->prepare(
		'INSERT INTO notifications (user_id, reservation_id, message)
		 VALUES (:user_id, :reservation_id, :message)'
	);
	$notification_statement->execute([
		'user_id' => $_SESSION['user_id'],
		'reservation_id' => $reservation_id,
		'message' => 'Your reservation request for ' . $room['room_code'] . ' on ' . $date . ' is pending review by DOIT.',
	]);

	$pdo->commit();
	reservation_response([
		'success' => true,
		'message' => 'Reservation request submitted and is pending DOIT review.',
		'reservation_id' => $reservation_id,
	]);
} catch (Throwable $exception) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	reservation_response(['success' => false, 'message' => 'The reservation could not be saved. Please try again.'], 500);
}

function reserve_time_to_minutes(string $time): ?int
{
	if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
		return null;
	}

	[$hours, $minutes] = array_map('intval', explode(':', $time));
	return ($hours * 60) + $minutes;
}
