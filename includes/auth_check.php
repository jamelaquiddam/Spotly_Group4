<?php
declare(strict_types=1);

function start_app_session(): void
{
	if (session_status() === PHP_SESSION_NONE) {
		ini_set('session.use_strict_mode', '1');
		ini_set('session.use_only_cookies', '1');
		session_set_cookie_params([
			'httponly' => true,
			'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
			'samesite' => 'Lax',
		]);
		session_start();
	}
}

function require_login(): void
{
	start_app_session();

	if (!isset($_SESSION['user_id'])) {
		header('Location: ../auth/login.php');
		exit;
	}
}

function require_role(string|array $roles): void
{
	require_login();

	$allowed_roles = (array) $roles;
	if (!in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
		http_response_code(403);
		exit('You do not have permission to access this page.');
	}
}

function csrf_token(): string
{
	start_app_session();

	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}

	return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
	start_app_session();

	return is_string($token)
		&& isset($_SESSION['csrf_token'])
		&& hash_equals($_SESSION['csrf_token'], $token);
}
