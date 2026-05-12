<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

$TOKEN_VALIDO = 'ZYPHER_RESPUESTA_TOKEN_2026';

function responder(bool $ok, array $data = []): void {
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    responder(false, ['error' => 'JSON inválido']);
}

$token = $data['token'] ?? '';
$ordenId = (int)($data['orden_id'] ?? 0);
$estado = $data['estado'] ?? '';
$resultado = $data['resultado'] ?? '';
$error = $data['error'] ?? '';

if ($token !== $TOKEN_VALIDO) {
    responder(false, ['error' => 'Token inválido']);
}

if ($ordenId <= 0) {
    responder(false, ['error' => 'orden_id inválido']);
}

if (!in_array($estado, ['completada', 'error'], true)) {
    responder(false, ['error' => 'Estado inválido']);
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare("
        UPDATE respuesta_ordenes
        SET estado = :estado,
            resultado = :resultado::jsonb,
            error = :error,
            finalizado_en = CURRENT_TIMESTAMP
        WHERE id = :orden_id
    ");

    $resultadoJson = json_encode([
        'salida' => $resultado
    ], JSON_UNESCAPED_UNICODE);

    $stmt->execute([
        ':estado' => $estado,
        ':resultado' => $resultadoJson,
        ':error' => $error,
        ':orden_id' => $ordenId,
    ]);

    responder(true, ['mensaje' => 'Resultado guardado']);

} catch (Throwable $e) {
    responder(false, ['error' => $e->getMessage()]);
}
