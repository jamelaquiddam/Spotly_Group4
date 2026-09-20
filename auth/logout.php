<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

start_app_session();
$_SESSION = [];

if (ini_get('session.use_cookies')) {
	$cookie_parameters = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $cookie_parameters['path'], $cookie_parameters['domain'], $cookie_parameters['secure'], $cookie_parameters['httponly']);
}

session_destroy();
header('Location: login.php?logged_out=1');
exit;
