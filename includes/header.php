<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/db.php';

start_app_session();

$unread_notifications = 0;
$notification_statement = $pdo->prepare(
	'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0'
);
$notification_statement->execute(['user_id' => $_SESSION['user_id']]);
$unread_notifications = (int) $notification_statement->fetchColumn();

$display_name = trim((string) ($_SESSION['first_name'] ?? '') . ' ' . (string) ($_SESSION['last_name'] ?? ''));
$safe_display_name = htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8');
$is_admin = ($_SESSION['role'] ?? '') === 'DOIT Staff/Admin';
$page_title = $page_title ?? 'Spotly';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> | Spotly</title>
	<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
	<header class="site-header">
		<nav class="navbar container" aria-label="Main navigation">
			<a class="brand" href="dashboard.php">Spotly</a>
			<div class="nav-links">
				<a href="dashboard.php">Dashboard</a>
				<a href="book.php">Book a Lab</a>
				<a href="my_reservations.php">My Reservations</a>
				<a href="notifications.php" class="notification-link">
					Notifications
					<?php if ($unread_notifications > 0): ?>
						<span class="notification-badge"><?= $unread_notifications ?></span>
					<?php endif; ?>
				</a>
				<?php if ($is_admin): ?>
					<a href="admin.php">Admin</a>
				<?php endif; ?>
			</div>
			<div class="user-menu">
				<span class="user-name"><?= $safe_display_name ?></span>
				<a class="button button-small button-outline" href="../auth/logout.php">Logout</a>
			</div>
		</nav>
	</header>
	<main class="container page-content">
