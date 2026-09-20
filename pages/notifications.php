<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
		http_response_code(419);
		exit('Your form session expired. Refresh the page and try again.');
	}

	$action = (string) ($_POST['action'] ?? '');
	if ($action === 'mark_all') {
		$statement = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id');
		$statement->execute(['user_id' => $_SESSION['user_id']]);
	} elseif ($action === 'mark_read') {
		$notification_id = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($notification_id !== false) {
			$statement = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = :notification_id AND user_id = :user_id');
			$statement->execute([
				'notification_id' => $notification_id,
				'user_id' => $_SESSION['user_id'],
			]);
		}
	}

	header('Location: notifications.php');
	exit;
}

$statement = $pdo->prepare(
	'SELECT notification_id, reservation_id, message, is_read, created_at
	 FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC, notification_id DESC'
);
$statement->execute(['user_id' => $_SESSION['user_id']]);
$notifications = $statement->fetchAll();
$unread_count = count(array_filter($notifications, static fn (array $notification): bool => !(bool) $notification['is_read']));

$page_title = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
	<p class="eyebrow">Stay up to date</p>
	<h1>Notifications</h1>
	<p class="muted">Updates about your reservation requests appear here.</p>
</section>

<section class="notification-panel card">
	<div class="notification-toolbar">
		<div>
			<h2>Recent activity</h2>
			<p class="muted"><?= $unread_count ?> unread notification<?= $unread_count === 1 ? '' : 's' ?></p>
		</div>
		<?php if ($unread_count > 0): ?>
			<form method="post">
				<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
				<input type="hidden" name="action" value="mark_all">
				<button class="button button-outline-dark" type="submit">Mark all as read</button>
			</form>
		<?php endif; ?>
	</div>
	<?php if (!$notifications): ?>
		<div class="empty-state">You have no notifications yet.</div>
	<?php else: ?>
		<div class="notification-list">
			<?php foreach ($notifications as $notification): ?>
				<article class="notification-item <?= $notification['is_read'] ? 'is-read' : 'is-unread' ?>">
					<div class="notification-dot" aria-hidden="true"></div>
					<div class="notification-content">
						<?php if ($notification['reservation_id']): ?>
							<a href="my_reservations.php?reservation_id=<?= (int) $notification['reservation_id'] ?>"><?= htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8') ?></a>
						<?php else: ?>
							<p><?= htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8') ?></p>
						<?php endif; ?>
						<time datetime="<?= htmlspecialchars($notification['created_at'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($notification['created_at'], ENT_QUOTES, 'UTF-8') ?></time>
					</div>
					<?php if (!$notification['is_read']): ?>
						<form method="post">
							<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
							<input type="hidden" name="action" value="mark_read">
							<input type="hidden" name="notification_id" value="<?= (int) $notification['notification_id'] ?>">
							<button class="button button-small button-outline-dark" type="submit">Mark as read</button>
						</form>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
