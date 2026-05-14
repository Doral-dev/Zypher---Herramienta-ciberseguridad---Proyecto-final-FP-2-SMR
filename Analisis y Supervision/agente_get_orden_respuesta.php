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
    $token = $_GET['token'] ?? '';
    $agenteId = $_GET['agente_id'] ?? '';

    if ($token !== TOKEN) {
        responder(['ok' => false, 'error' => 'Token inválido']);
    }

    if ($agenteId === '') {
        responder(['ok' => false, 'error' => 'Falta agente_id']);
    }

    $pdo = getPDO();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            id AS orden_id,
            agente_id,
            codigo,
            parametros
        FROM respuesta_ordenes
        WHERE estado = 'pendiente'
          AND agente_id = :agente_id
        ORDER BY id ASC
        LIMIT 1
        FOR UPDATE SKIP LOCKED
    ");

    $stmt->execute([':agente_id' => $agenteId]);
    $orden = $stmt->fetch();

    if (!$orden) {
        $pdo->commit();
        responder(['ok' => true, 'orden' => null]);
    }

    $upd = $pdo->prepare("
        UPDATE respuesta_ordenes
        SET estado = 'en_proceso',
            ejecutado_en = CURRENT_TIMESTAMP,
            actualizado_en = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $upd->execute([':id' => $orden['orden_id']]);

    $pdo->commit();

    responder([
        'ok' => true,
        'orden' => [
            'orden_id' => (int)$orden['orden_id'],
            'agente_id' => $orden['agente_id'],
            'codigo' => $orden['codigo'],
            'parametros' => json_decode($orden['parametros'] ?? '{}', true) ?: []
        ]
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responder(['ok' => false, 'error' => $e->getMessage()]);
}
