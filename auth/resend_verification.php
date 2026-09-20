<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/email_rules.php';
require_once __DIR__ . '/../config/mail.php';

start_app_session();
$errors = [];
$email = '';
$link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $statement = $pdo->prepare('SELECT user_id, first_name, role, is_verified, is_active FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if ($user && !$user['is_verified'] && $user['is_active'] && validateMapuaEmail($email, $user['role'])['valid']) {
            $token = bin2hex(random_bytes(32));
            $update = $pdo->prepare('UPDATE users SET verification_token = :token, token_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE user_id = :user_id');
            $update->execute(['token' => $token, 'user_id' => $user['user_id']]);
            $link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME']) . '/verify.php?token=' . urlencode($token);
            if (!sendVerificationEmail($email, $user['first_name'], $link)) $errors[] = 'The verification email could not be sent. Please contact DOIT.';
        } else {
            $errors[] = 'If the account is eligible, a verification link has been sent.';
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Resend Verification | Spotly</title><link rel="stylesheet" href="../assets/css/style.css"></head><body class="auth-page"><main class="auth-shell"><section class="auth-card card"><a class="brand auth-brand" href="../index.php">Spotly</a><p class="eyebrow">Email verification</p><h1>Resend verification</h1><p class="muted">Enter your Mapua email to request a new link.</p><?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $error): ?><p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?></div><?php endif; ?><?php if ($link && DEV_MODE): ?><div class="alert alert-info">DEV_MODE verification link: <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>">Verify this account</a></div><?php endif; ?><form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><div class="form-group"><label for="email">Mapua email</label><input id="email" name="email" type="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required></div><button class="button button-primary" type="submit">Send verification link</button></form><p class="auth-footer"><a href="login.php">Back to login</a></p></section></main></body></html>
