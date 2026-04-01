<?php
declare(strict_types=1);

function sendVerificationEmail(string $to, string $token): void
{
    $baseUrl = 'https://TU-DOMINIO';
    $verifyUrl = $baseUrl . '/verify.php?token=' . urlencode($token);

    $subject = 'Verifica tu cuenta de Zypher';

    $message = "Hola,\n\n";
    $message .= "Pulsa este enlace para verificar tu cuenta de Zypher:\n\n";
    $message .= $verifyUrl . "\n\n";
    $message .= "Si no has sido tú, ignora este correo.\n";

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/plain; charset=UTF-8';
    $headers[] = 'From: Zypher <no-reply@tu-dominio.com>';

    $sent = mail($to, $subject, $message, implode("\r\n", $headers));

    if (!$sent) {
        throw new Exception('No se pudo enviar el correo de verificación');
    }
}
