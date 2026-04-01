<?php
declare(strict_types=1);

function sendVerificationEmail(string $to, string $token): void
{
    $baseUrl = 'https://zypher-herramienta-ciberseguridad.onrender.com';
    $verifyUrl = $baseUrl . '/verify.php?token=' . urlencode($token);

    $apiKey = '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';

    $data = [
        'sender' => [
            'name' => 'NoReply Zypher',
            'email' => 'adoral296@gmail.com'
        ],
        'to' => [
            [
                'email' => $to
            ]
        ],
        'subject' => 'Verifica tu cuenta de Zypher',
        'textContent' => "Hola,\n\nPulsa este enlace para verificar tu cuenta de Zypher:\n\n{$verifyUrl}\n\nSi no has sido tú, ignora este correo."
    ];

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'api-key: ' . $apiKey
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('No se pudo conectar con la API de Brevo: ' . $error);
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new Exception('Brevo devolvió error: ' . $response);
    }
}
