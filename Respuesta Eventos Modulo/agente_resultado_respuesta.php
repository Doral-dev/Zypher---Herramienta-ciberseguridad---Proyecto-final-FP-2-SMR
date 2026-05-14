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
    $resultado = (string)($data['resultado'] ?? '');
    $error = (string)($data['error'] ?? '');
    $flowId = (string)($data['flow_id'] ?? '');

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
            resultado = :resultado,
            error = :error,
            flow_id = NULLIF(:flow_id, ''),
            actualizado_en = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $stmt->execute([
        ':estado' => $estado,
        ':resultado' => $resultado,
        ':error' => $error,
        ':flow_id' => $flowId,
        ':id' => $ordenId
    ]);

    $stmtHistorial = $pdo->prepare("
        INSERT INTO respuesta_historial
        (orden_id, agente_id, accion, estado, resultado, error, fecha)
        SELECT
            id,
            agente_id,
            codigo,
            estado,
            resultado,
            error,
            CURRENT_TIMESTAMP
        FROM respuesta_ordenes
        WHERE id = :id
    ");

    $stmtHistorial->execute([':id' => $ordenId]);

    responder(['ok' => true]);

} catch (Throwable $e) {
    responder(['ok' => false, 'error' => $e->getMessage()]);
}
