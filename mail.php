<?php
declare(strict_types=1);

function sendVerificationEmail(string $to, string $token): void
{
    $baseUrl = 'https://zypher-herramienta-ciberseguridad.onrender.com';
    $verifyUrl = $baseUrl . '/verify.php?token=' . urlencode($token);

    $apiKey = 'xsmtpsib-c9902f680740616a3af1d49c7a7444b4772e24f136f7fee89cc142504b57aac3-ghDBBMRQt2cxaeh2';

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

    $options = [
        'http' => [
            'method' => 'POST',
            'header' =>
                "Accept: application/json\r\n" .
                "Content-Type: application/json\r\n" .
                "api-key: {$apiKey}\r\n",
            'content' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'ignore_errors' => true,
            'timeout' => 20
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents('https://api.brevo.com/v3/smtp/email', false, $context);

    if ($response === false) {
        throw new Exception('No se pudo conectar con la API de Brevo');
    }

    $statusCode = 0;

    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $statusCode = (int)$matches[1];
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new Exception('Brevo devolvió error: ' . $response);
    }
}
