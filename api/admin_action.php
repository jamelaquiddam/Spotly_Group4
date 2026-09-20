<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/email_rules.php';
require_once __DIR__ . '/../includes/release_noshows.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json; charset=utf-8');
start_app_session();
releaseNoShows($pdo);

function admin_response(array $payload, int $status = 200): never
{
	http_response_code($status);
	echo json_encode($payload);
	exit;
}

if (!isset($_SESSION['user_id'])) {
	admin_response(['success' => false, 'message' => 'Please log in first.'], 401);
}
if (($_SESSION['role'] ?? '') !== 'DOIT Staff/Admin') {
	admin_response(['success' => false, 'message' => 'Administrator access is required.'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	admin_response(['success' => false, 'message' => 'Only POST requests are allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : $_POST;
if (!verify_csrf_token($input['csrf_token'] ?? null)) {
	admin_response(['success' => false, 'message' => 'Your form session expired. Refresh the page and try again.'], 419);
}

$action = (string) ($input['action'] ?? '');
$reservation_id = filter_var($input['reservation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

	if (in_array($action, ['approve', 'reject', 'check_in'], true) && $reservation_id === false) {
	admin_response(['success' => false, 'message' => 'A valid reservation is required.'], 400);
}

try {
	$pdo->beginTransaction();

	if (in_array($action, ['approve', 'reject', 'check_in'], true)) {
		$lookup_statement = $pdo->prepare(
			'SELECT r.reservation_id, r.user_id, r.room_id, r.date, r.start_time, r.end_time,
					r.status, l.room_code
			 FROM reservations r INNER JOIN laboratories l ON l.room_id = r.room_id
			 WHERE r.reservation_id = :reservation_id'
		);
		$lookup_statement->execute(['reservation_id' => $reservation_id]);
		$reservation = $lookup_statement->fetch();
		if (!$reservation) {
			$pdo->rollBack();
			admin_response(['success' => false, 'message' => 'Reservation not found.'], 404);
		}
		if ($reservation['status'] !== 'Pending') {
			$pdo->rollBack();
			admin_response(['success' => false, 'message' => 'Only pending reservations can be processed.'], 409);
		}

		if ($action === 'check_in') {
			$room_lock = $pdo->prepare('SELECT status FROM laboratories WHERE room_id = :room_id FOR UPDATE');
			$room_lock->execute(['room_id' => $reservation['room_id']]);
			$reservation_lock = $pdo->prepare('SELECT status FROM reservations WHERE reservation_id = :reservation_id FOR UPDATE');
			$reservation_lock->execute(['reservation_id' => $reservation_id]);
			$start = strtotime($reservation['date'] . ' ' . $reservation['start_time']);
			if ($reservation_lock->fetchColumn() !== 'Approved' || time() < $start - 600 || time() > $start + (GRACE_PERIOD_MINUTES * 60)) {
				$pdo->rollBack();
				admin_response(['success' => false, 'message' => 'This reservation is outside its check-in window.'], 409);
			}
			$check_in = $pdo->prepare('UPDATE reservations SET checked_in_at = NOW() WHERE reservation_id = :reservation_id AND status = \'Approved\' AND checked_in_at IS NULL');
			$check_in->execute(['reservation_id' => $reservation_id]);
			$pdo->commit();
			admin_response(['success' => true, 'message' => 'Reservation marked as checked in.']);
		}

		if ($action === 'approve') {
			$room_lock = $pdo->prepare('SELECT room_id, status FROM laboratories WHERE room_id = :room_id FOR UPDATE');
			$room_lock->execute(['room_id' => $reservation['room_id']]);
			$locked_room = $room_lock->fetch();
			if (!$locked_room || $locked_room['status'] !== 'Available') {
				$pdo->rollBack();
				admin_response(['success' => false, 'message' => 'Cannot approve a reservation for a laboratory that is not available.'], 409);
			}
			$reservation_lock = $pdo->prepare('SELECT status FROM reservations WHERE reservation_id = :reservation_id FOR UPDATE');
			$reservation_lock->execute(['reservation_id' => $reservation_id]);
			if (($reservation_lock->fetchColumn() ?: '') !== 'Pending') {
				$pdo->rollBack();
				admin_response(['success' => false, 'message' => 'Only pending reservations can be processed.'], 409);
			}

			$conflict_statement = $pdo->prepare(
				"SELECT reservation_id FROM reservations
				 WHERE room_id = :room_id AND date = :date AND reservation_id <> :reservation_id
				   AND status = 'Approved' AND start_time < :new_end AND end_time > :new_start
				 FOR UPDATE"
			);
			$conflict_statement->execute([
				'room_id' => $reservation['room_id'],
				'date' => $reservation['date'],
				'reservation_id' => $reservation_id,
				'new_end' => $reservation['end_time'],
				'new_start' => $reservation['start_time'],
			]);
			if ($conflict_statement->fetch()) {
				$pdo->rollBack();
				admin_response(['success' => false, 'message' => 'Cannot approve: another approved reservation overlaps this time.'], 409);
			}

			$update = $pdo->prepare(
				"UPDATE reservations SET status = 'Approved', approved_by = :approved_by, approved_at = NOW()
				 WHERE reservation_id = :reservation_id"
			);
			$update->execute(['approved_by' => $_SESSION['user_id'], 'reservation_id' => $reservation_id]);
			$decision_message = 'Your reservation for ' . $reservation['room_code'] . ' on ' . date('M j', strtotime($reservation['date'])) . ' (' . date('g:i A', strtotime($reservation['start_time'])) . '-' . date('g:i A', strtotime($reservation['end_time'])) . ') was approved.';
		} else {
			$reservation_lock = $pdo->prepare('SELECT status FROM reservations WHERE reservation_id = :reservation_id FOR UPDATE');
			$reservation_lock->execute(['reservation_id' => $reservation_id]);
			if (($reservation_lock->fetchColumn() ?: '') !== 'Pending') {
				$pdo->rollBack();
				admin_response(['success' => false, 'message' => 'Only pending reservations can be processed.'], 409);
			}
			$reason = trim((string) ($input['reason'] ?? ''));
			if (strlen($reason) > 180) {
				$pdo->rollBack();
				admin_response(['success' => false, 'message' => 'Rejection reason must be 180 characters or fewer.'], 400);
			}
			$update = $pdo->prepare(
				"UPDATE reservations SET status = 'Rejected', approved_by = :approved_by, approved_at = NOW()
				 WHERE reservation_id = :reservation_id"
			);
			$update->execute(['approved_by' => $_SESSION['user_id'], 'reservation_id' => $reservation_id]);
			$decision_message = 'Your reservation for ' . $reservation['room_code'] . ' on ' . date('M j', strtotime($reservation['date'])) . ' (' . date('g:i A', strtotime($reservation['start_time'])) . '-' . date('g:i A', strtotime($reservation['end_time'])) . ') was rejected.';
			if ($reason !== '') {
				$decision_message .= ' Reason: ' . $reason;
			}
		}

		$notification = $pdo->prepare(
			'INSERT INTO notifications (user_id, reservation_id, message) VALUES (:user_id, :reservation_id, :message)'
		);
		$notification->execute([
			'user_id' => $reservation['user_id'],
			'reservation_id' => $reservation_id,
			'message' => $decision_message,
		]);
		$pdo->commit();
		admin_response(['success' => true, 'message' => $decision_message]);
	}

	if ($action === 'update_lab_status') {
		$room_id = filter_var($input['room_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		$status = (string) ($input['status'] ?? '');
		$valid_statuses = ['Available', 'Under Maintenance', 'Inactive'];
		if ($room_id === false || !in_array($status, $valid_statuses, true)) {
			$pdo->rollBack();
			admin_response(['success' => false, 'message' => 'Choose a valid room and status.'], 400);
		}
		$update = $pdo->prepare('UPDATE laboratories SET status = :status WHERE room_id = :room_id');
		$update->execute(['status' => $status, 'room_id' => $room_id]);
		if ($update->rowCount() === 0) {
			$pdo->rollBack();
			admin_response(['success' => false, 'message' => 'Laboratory not found or status was unchanged.'], 404);
		}
		$pdo->commit();
		admin_response(['success' => true, 'message' => 'Laboratory status updated.']);
	}

	if ($action === 'create_user') {
		$role = trim((string) ($input['role'] ?? ''));
		$email = strtolower(trim((string) ($input['email'] ?? '')));
		$student_employee_no = trim((string) ($input['student_employee_no'] ?? ''));
		$first_name = trim((string) ($input['first_name'] ?? ''));
		$last_name = trim((string) ($input['last_name'] ?? ''));
		$department = trim((string) ($input['department'] ?? ''));
		$password = (string) ($input['password'] ?? '');
		if ($role !== 'DOIT Staff/Admin' || !$email || !$first_name || !$last_name || !$department || strlen($student_employee_no) > 20 || strlen($password) < 8) {
			$pdo->rollBack();
			admin_response(['success' => false, 'message' => 'Complete the DOIT account fields with a password of at least 8 characters.'], 400);
		}
		$email_check = validateMapuaEmail($email, $role);
		if (!$email_check['valid']) {
			$pdo->rollBack();
			admin_response(['success' => false, 'message' => $email_check['message']], 400);
		}
		$duplicate = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
		$duplicate->execute(['email' => $email_check['email']]);
		if ($duplicate->fetch()) {
			$pdo->rollBack();
			admin_response(['success' => false, 'message' => 'An account with that email already exists.'], 409);
		}
		$create = $pdo->prepare('INSERT INTO users (student_employee_no, first_name, last_name, email, password_hash, role, department, is_verified, is_active) VALUES (:student_employee_no, :first_name, :last_name, :email, :password_hash, :role, :department, 1, 1)');
		$create->execute(['student_employee_no' => $student_employee_no, 'first_name' => $first_name, 'last_name' => $last_name, 'email' => $email_check['email'], 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role, 'department' => $department]);
		$pdo->commit();
		admin_response(['success' => true, 'message' => 'DOIT Staff/Admin account created.']);
	}

	if ($action === 'deactivate_user') {
		$user_id = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($user_id === false || $user_id === (int) $_SESSION['user_id']) {
			$pdo->rollBack();
			admin_response(['success' => false, 'message' => 'Choose another account to deactivate.'], 400);
		}
		$deactivate = $pdo->prepare('UPDATE users SET is_active = 0 WHERE user_id = :user_id');
		$deactivate->execute(['user_id' => $user_id]);
		if ($deactivate->rowCount() === 0) {
			$pdo->rollBack();
			admin_response(['success' => false, 'message' => 'Account not found or already inactive.'], 404);
		}
		$pdo->commit();
		admin_response(['success' => true, 'message' => 'Account deactivated.']);
	}

	$pdo->rollBack();
	admin_response(['success' => false, 'message' => 'Unsupported admin action.'], 400);
} catch (Throwable $exception) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	admin_response(['success' => false, 'message' => 'The admin action could not be completed. Please try again.'], 500);
}
