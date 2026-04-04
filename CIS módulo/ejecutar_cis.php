<?php
// ejecutar_cis.php

header('Content-Type: application/json; charset=utf-8');

$script = __DIR__ . '/analizar_cis.py';

if (!file_exists($script)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'output' => ["No existe analizar_cis.py en: $script"]
    ]);
    exit;
}

$cmd = 'python ' . escapeshellarg($script) . ' 2>&1';
exec($cmd, $output, $result);

echo json_encode([
    'ok' => $result === 0,
    'result' => $result,
    'command' => $cmd,
    'output' => $output
]);
