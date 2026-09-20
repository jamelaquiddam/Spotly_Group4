<?php
declare(strict_types=1);

// DEV_MODE must be false in production. When true, verification links are displayed instead of emailed.
const DEV_MODE = true;

const MAIL_HOST = 'smtp.gmail.com';
const MAIL_PORT = 587;
const MAIL_USERNAME = '';
const MAIL_PASSWORD = '';
const MAIL_ENCRYPTION = 'tls';
const MAIL_FROM_ADDRESS = 'spotly@example.com';
const MAIL_FROM_NAME = 'Spotly';

function sendVerificationEmail(string $recipient, string $name, string $verification_link): bool
{
    if (DEV_MODE) {
        return true;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        return false;
    }

    require_once $autoload;
    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host = MAIL_HOST;
        $mailer->SMTPAuth = true;
        $mailer->Username = MAIL_USERNAME;
        $mailer->Password = MAIL_PASSWORD;
        $mailer->SMTPSecure = MAIL_ENCRYPTION;
        $mailer->Port = MAIL_PORT;
        $mailer->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mailer->addAddress($recipient, $name);
        $mailer->isHTML(true);
        $mailer->Subject = 'Verify your Spotly account';
        $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safe_link = htmlspecialchars($verification_link, ENT_QUOTES, 'UTF-8');
        $mailer->Body = "<p>Hello {$safe_name},</p><p>Verify your Spotly account within 24 hours:</p><p><a href=\"{$safe_link}\">Verify my account</a></p>";
        $mailer->AltBody = "Verify your Spotly account: {$verification_link}";
        return $mailer->send();
    } catch (Throwable) {
        return false;
    }
}
