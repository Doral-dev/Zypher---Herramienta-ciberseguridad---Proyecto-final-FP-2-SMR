<?php
// ejecutar_cis.php

header('Content-Type: application/json');

$script = escapeshellarg(__DIR__ . '/analizar_cis.py');
exec("python3 $script 2>&1", $output, $result);

if ($result !== 0) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'output' => $output
    ]);
    exit;
}

echo json_encode([
    'ok' => true
]);
