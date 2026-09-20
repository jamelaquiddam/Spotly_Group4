<?php
declare(strict_types=1);

function validateMapuaEmail(string $email, string $role): array
{
    $normalized_email = strtolower(trim($email));
    $at_position = strrpos($normalized_email, '@');
    $local_part = $at_position === false ? '' : substr($normalized_email, 0, $at_position);
    $domain = $at_position === false ? '' : substr($normalized_email, $at_position + 1);

    if ($local_part === '' || !filter_var($normalized_email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'email' => $normalized_email, 'message' => 'Please enter a valid Mapua email address.'];
    }

    $allowed_domain = match ($role) {
        'Student' => 'mymail.mapua.edu.ph',
        'Faculty', 'DOIT Staff/Admin' => 'mapua.edu.ph',
        default => '',
    };

    if ($allowed_domain === '' || $domain !== $allowed_domain) {
        $message = $role === 'Student'
            ? 'Students must use your @mymail.mapua.edu.ph email.'
            : 'Faculty and DOIT Staff/Admin must use your @mapua.edu.ph email.';
        return ['valid' => false, 'email' => $normalized_email, 'message' => $message];
    }

    return ['valid' => true, 'email' => $normalized_email, 'message' => ''];
}
