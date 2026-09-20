<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/email_rules.php';
require_once __DIR__ . '/../config/mail.php';

start_app_session();
if (isset($_SESSION['user_id'])) {
    header('Location: ../pages/dashboard.php');
    exit;
}

$errors = [];
$verification_link = '';
$form = ['student_employee_no' => '', 'role' => 'Student', 'first_name' => '', 'last_name' => '', 'email' => '', 'department' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $field => $value) {
        $form[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $errors[] = 'Your form session expired. Please try again.';
    if ($form['student_employee_no'] === '' || strlen($form['student_employee_no']) > 20) $errors[] = 'Student/employee number is required and must be 20 characters or fewer.';
    if (!in_array($form['role'], ['Student', 'Faculty'], true)) $errors[] = 'Only Student and Faculty accounts can be registered here.';
    foreach (['first_name' => 'First name', 'last_name' => 'Last name', 'department' => 'Department'] as $field => $label) {
        if ($form[$field] === '' || strlen($form[$field]) > ($field === 'department' ? 100 : 50)) $errors[] = $label . ' is required and is too long.';
    }
    $email_check = validateMapuaEmail($form['email'], $form['role']);
    if (!$email_check['valid'] || strlen($email_check['email']) > 100) $errors[] = $email_check['message'];
    else $form['email'] = $email_check['email'];
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $duplicate_check = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
        $duplicate_check->execute(['email' => $form['email']]);
        if ($duplicate_check->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $verification_token = bin2hex(random_bytes(32));
            $statement = $pdo->prepare('INSERT INTO users (student_employee_no, first_name, last_name, email, password_hash, role, department, is_verified, verification_token, token_expires_at) VALUES (:student_employee_no, :first_name, :last_name, :email, :password_hash, :role, :department, 0, :verification_token, DATE_ADD(NOW(), INTERVAL 24 HOUR))');
            $statement->execute(['student_employee_no' => $form['student_employee_no'], 'first_name' => $form['first_name'], 'last_name' => $form['last_name'], 'email' => $form['email'], 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $form['role'], 'department' => $form['department'], 'verification_token' => $verification_token]);
            $verification_link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME']) . '/verify.php?token=' . urlencode($verification_token);
            if (!sendVerificationEmail($form['email'], $form['first_name'], $verification_link)) $errors[] = 'The account was created, but the verification email could not be sent. Use Resend verification email.';
        }
    }
}

function escape_register_value(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Register | Spotly</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body class="auth-page"><main class="auth-shell"><section class="auth-card card auth-card-wide"><a class="brand auth-brand" href="../index.php">Spotly</a><p class="eyebrow">Create your account</p><h1>Join Spotly</h1><p class="muted">Register with your Mapua University account.</p>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?></div><?php endif; ?>
<form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><div class="form-grid">
<div class="form-group"><label for="student_employee_no">Student/employee no.</label><input id="student_employee_no" name="student_employee_no" maxlength="20" value="<?= escape_register_value($form['student_employee_no']) ?>" required></div>
<div class="form-group"><label for="role">Role</label><select id="role" name="role" required><?php foreach (['Student', 'Faculty'] as $role): ?><option value="<?= escape_register_value($role) ?>" <?= $form['role'] === $role ? 'selected' : '' ?>><?= escape_register_value($role) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label for="first_name">First name</label><input id="first_name" name="first_name" maxlength="50" value="<?= escape_register_value($form['first_name']) ?>" required></div>
<div class="form-group"><label for="last_name">Last name</label><input id="last_name" name="last_name" maxlength="50" value="<?= escape_register_value($form['last_name']) ?>" required></div>
<div class="form-group"><label for="email">Mapua email address</label><input id="email" name="email" type="email" maxlength="100" value="<?= escape_register_value($form['email']) ?>" placeholder="juan.delacruz@mymail.mapua.edu.ph" required autocomplete="email"><span id="email-hint" class="field-note">Students must use @mymail.mapua.edu.ph.</span></div>
<div class="form-group"><label for="department">Department</label><input id="department" name="department" maxlength="100" value="<?= escape_register_value($form['department']) ?>" required></div>
<div class="form-group"><label for="password">Password</label><input id="password" name="password" type="password" minlength="8" required autocomplete="new-password"></div>
<div class="form-group"><label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" required autocomplete="new-password"></div>
</div><button class="button button-primary" type="submit">Create account</button></form>
<?php if ($verification_link && DEV_MODE): ?><div class="alert alert-info" role="status">DEV_MODE verification link: <a href="<?= escape_register_value($verification_link) ?>">Verify this account</a></div><?php elseif (!$errors && $verification_link): ?><div class="alert alert-success" role="status">Check your Mapua email for a verification link before logging in.</div><?php endif; ?>
<p class="auth-footer">Already registered? <a href="login.php">Log in here</a> · <a href="resend_verification.php">Resend verification email</a></p></section></main>
<script>
const roleField=document.querySelector('#role'),emailField=document.querySelector('#email'),emailHint=document.querySelector('#email-hint');
function updateEmailRule(){const student=roleField.value==='Student';emailField.placeholder=student?'juan.delacruz@mymail.mapua.edu.ph':'juan.delacruz@mapua.edu.ph';emailHint.textContent=student?'Students must use @mymail.mapua.edu.ph.':'Faculty must use @mapua.edu.ph.';}
roleField.addEventListener('change',updateEmailRule);document.querySelector('form').addEventListener('submit',event=>{const value=emailField.value.trim().toLowerCase(),at=value.lastIndexOf('@'),domain=at<0?'':value.slice(at+1),expected=roleField.value==='Student'?'mymail.mapua.edu.ph':'mapua.edu.ph';if(at<1||domain!==expected){event.preventDefault();emailField.setCustomValidity(`Use your @${expected} email for this role.`);emailField.reportValidity();}else emailField.setCustomValidity('');});updateEmailRule();
</script></body></html>
