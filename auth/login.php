<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

start_app_session();

if (isset($_SESSION['user_id'])) {
	$destination = ($_SESSION['role'] ?? '') === 'DOIT Staff/Admin'
		? '../pages/admin.php'
		: '../pages/dashboard.php';
	header('Location: ' . $destination);
	exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim((string) ($_POST['email'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');

	if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
		$errors[] = 'Your form session expired. Please try again.';
	} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
		$errors[] = 'Please enter a valid email address.';
	} elseif ($password === '') {
		$errors[] = 'Please enter your password.';
	} else {
		$statement = $pdo->prepare('SELECT user_id, first_name, last_name, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
		$statement->execute(['email' => $email]);
		$user = $statement->fetch();

		if (!$user || !password_verify($password, $user['password_hash'])) {
			$errors[] = 'The email or password is incorrect.';
		} else {
			session_regenerate_id(true);
			$_SESSION['user_id'] = (int) $user['user_id'];
			$_SESSION['first_name'] = $user['first_name'];
			$_SESSION['last_name'] = $user['last_name'];
			$_SESSION['email'] = $user['email'];
			$_SESSION['role'] = $user['role'];

			$destination = $user['role'] === 'DOIT Staff/Admin'
				? '../pages/admin.php'
				: '../pages/dashboard.php';
			header('Location: ' . $destination);
			exit;
		}
	}
}

function escape_login_value(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Log In | Spotly</title>
	<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
	<main class="auth-shell">
		<section class="auth-card card">
			<a class="brand auth-brand" href="../index.php">Spotly</a>
			<p class="eyebrow">Laboratory reservations</p>
			<h1>Welcome back</h1>
			<p class="muted">Log in to manage your lab reservations.</p>

			<?php if ($errors): ?>
				<div class="alert alert-error" role="alert">
					<?php foreach ($errors as $error): ?>
						<p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<form method="post" class="form-stack">
				<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
				<div class="form-group">
					<label for="email">Email address</label>
					<input id="email" name="email" type="email" maxlength="100" value="<?= escape_login_value($email) ?>" required autocomplete="email">
				</div>
				<div class="form-group">
					<label for="password">Password</label>
					<input id="password" name="password" type="password" required autocomplete="current-password">
				</div>
				<button class="button button-primary" type="submit">Log in</button>
			</form>
			<p class="auth-footer">Need an account? <a href="register.php">Register here</a></p>
		</section>
	</main>
</body>
</html>
