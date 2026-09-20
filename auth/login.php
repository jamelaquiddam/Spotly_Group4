<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/email_rules.php';

start_app_session();
if (isset($_SESSION['user_id'])) {
	header('Location: ' . (($_SESSION['role'] ?? '') === 'DOIT Staff/Admin' ? '../pages/admin.php' : '../pages/dashboard.php'));
	exit;
}

$errors = [];
$email = '';
$generic_error = 'The email or password is incorrect.';
$ip_address = substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
function login_attempt_count(PDO $pdo, string $email, string $ip): int
{
	$statement = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE failed_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND (email = :email OR ip_address = :ip_address)');
	$statement->execute(['email' => $email, 'ip_address' => $ip]);
	return (int) $statement->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = strtolower(trim((string) ($_POST['email'] ?? '')));
	$password = (string) ($_POST['password'] ?? '');

	if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
		$errors[] = 'Your form session expired. Please try again.';
	} else {
		$domain_check = validateMapuaEmail($email, 'Student');
		$faculty_check = validateMapuaEmail($email, 'Faculty');
		if (!$domain_check['valid'] && !$faculty_check['valid']) {
			$errors[] = $generic_error;
		} elseif (login_attempt_count($pdo, $email, $ip_address) >= 5) {
			$errors[] = 'Too many failed attempts. Please try again in 15 minutes.';
		} elseif ($password === '') {
			$statement = $pdo->prepare('SELECT user_id, first_name, last_name, email, password_hash, role, is_verified, is_active FROM users WHERE email = :email LIMIT 1');
			$statement->execute(['email' => $email]);
			$user = $statement->fetch();
			$stored_domain_check = $user ? validateMapuaEmail($user['email'], $user['role']) : ['valid' => false];
			$valid_credentials = $user && (bool) $user['is_active'] && (bool) $user['is_verified'] && $stored_domain_check['valid'] && password_verify($password, $user['password_hash']);

			if (!$valid_credentials) {
				$failure = $pdo->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip_address)');
				$failure->execute(['email' => $email, 'ip_address' => $ip_address]);
				$errors[] = $user && !(bool) $user['is_verified'] ? 'Please verify your Mapua email before logging in.' : $generic_error;
			} else {
				$cleanup = $pdo->prepare('DELETE FROM login_attempts WHERE email = :email OR ip_address = :ip_address');
				$cleanup->execute(['email' => $email, 'ip_address' => $ip_address]);
				session_regenerate_id(true);
				$_SESSION['user_id'] = (int) $user['user_id'];
				$_SESSION['first_name'] = $user['first_name'];
				$_SESSION['last_name'] = $user['last_name'];
				$_SESSION['email'] = $user['email'];
				$_SESSION['role'] = $user['role'];
				header('Location: ' . ($user['role'] === 'DOIT Staff/Admin' ? '../pages/admin.php' : '../pages/dashboard.php'));
				exit;
			}
		}
	}
}

function escape_login_value(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Log In | Spotly</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body class="auth-page"><main class="auth-shell"><section class="auth-card card"><a class="brand auth-brand" href="../index.php">Spotly</a><p class="eyebrow">Laboratory reservations</p><h1>Welcome back</h1><p class="muted">Log in with your verified Mapua account.</p>
<?php if (isset($_GET['registered'])): ?><div class="alert alert-info">Account created. Verify your email before logging in.</div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?></div><?php endif; ?>
<form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><div class="form-group"><label for="email">Mapua email address</label><input id="email" name="email" type="email" maxlength="100" value="<?= escape_login_value($email) ?>" required autocomplete="email"></div><div class="form-group"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div><button class="button button-primary" type="submit">Log in</button></form>
<p class="auth-footer">Need an account? <a href="register.php">Register here</a> · <a href="resend_verification.php">Resend verification email</a></p></section></main></body></html>
