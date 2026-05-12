<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

const TOKEN = 'ZYPHER_RESPUESTA_TOKEN_2026';

function responder(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        responder(['ok' => false, 'error' => 'JSON inválido']);
    }

    if (($data['token'] ?? '') !== TOKEN) {
        responder(['ok' => false, 'error' => 'Token inválido']);
    }

    $ordenId = (int)($data['orden_id'] ?? 0);
    $estado = $data['estado'] ?? '';
    $resultado = $data['resultado'] ?? null;
    $error = $data['error'] ?? '';

    if ($ordenId <= 0) {
        responder(['ok' => false, 'error' => 'orden_id inválido']);
    }

    if (!in_array($estado, ['completada', 'error'], true)) {
        responder(['ok' => false, 'error' => 'Estado inválido']);
    }

    $pdo = getPDO();

    $stmt = $pdo->prepare("
        UPDATE respuesta_ordenes
        SET estado = :estado,
            resultado = :resultado::jsonb,
            error = :error,
            finalizado_en = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $stmt->execute([
        ':estado' => $estado,
        ':resultado' => json_encode($resultado, JSON_UNESCAPED_UNICODE),
        ':error' => $error,
        ':id' => $ordenId
    ]);

    responder(['ok' => true]);

} catch (Throwable $e) {
    responder(['ok' => false, 'error' => $e->getMessage()]);
}
