<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
$token = trim((string) ($_GET['token'] ?? ''));
$message = 'This verification link is invalid or has expired.';
$success = false;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $statement = $pdo->prepare('SELECT user_id FROM users WHERE verification_token = :token AND token_expires_at > NOW() AND is_active = 1 LIMIT 1');
    $statement->execute(['token' => $token]);
    $user = $statement->fetch();
    if ($user) {
        $update = $pdo->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE user_id = :user_id');
        $update->execute(['user_id' => $user['user_id']]);
        $message = 'Your email is verified. You can now log in.';
        $success = true;
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Verify Email | Spotly</title><link rel="stylesheet" href="../assets/css/style.css"></head><body class="auth-page"><main class="auth-shell"><section class="auth-card card"><a class="brand auth-brand" href="../index.php">Spotly</a><p class="eyebrow">Email verification</p><h1><?= $success ? 'Verified' : 'Verification failed' ?></h1><div class="alert <?= $success ? 'alert-success' : 'alert-error' ?>" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><a class="button button-primary" href="login.php">Go to login</a></section></main></body></html>
