<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function sendVerificationEmail(string $to, string $token): void
{
    $baseUrl = 'https://zypher-herramienta-ciberseguridad.onrender.com';
    $verifyUrl = $baseUrl . '/verify.php?token=' . urlencode($token);

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->Port = 587;
        $mail->SMTPAuth = true;
        $mail->Username = 'a6d6b9001@smtp-brevo.com';
        $mail->Password = 'PON_AQUI_TU_SMTP_KEY';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('a6d6b9001@smtp-brevo.com', 'Zypher');
        $mail->addAddress($to);

        $mail->Subject = 'Verifica tu cuenta de Zypher';
        $mail->Body = "Hola,\n\nPulsa este enlace para verificar tu cuenta de Zypher:\n\n{$verifyUrl}\n\nSi no has sido tú, ignora este correo.";

        $mail->send();
    } catch (Exception $e) {
        throw new Exception('No se pudo enviar el correo: ' . $mail->ErrorInfo);
    }
}
