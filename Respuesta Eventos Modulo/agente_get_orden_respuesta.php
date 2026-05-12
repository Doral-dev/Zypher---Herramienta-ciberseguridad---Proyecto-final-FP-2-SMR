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
            ro.id AS orden_id,
            ro.parametros,
            re.agente_id,
            re.velociraptor_client_id,
            re.hostname,
            ra.codigo,
            ra.nombre,
            ra.artefacto_velociraptor
        FROM respuesta_ordenes ro
        INNER JOIN respuesta_equipos re ON re.id = ro.equipo_id
        INNER JOIN respuesta_acciones ra ON ra.id = ro.accion_id
        WHERE ro.estado = 'pendiente'
          AND re.agente_id = :agente_id
          AND re.activo = TRUE
          AND ra.activa = TRUE
        ORDER BY ro.id ASC
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
            ejecutado_en = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $upd->execute([':id' => $orden['orden_id']]);

    $pdo->commit();

    responder([
        'ok' => true,
        'orden' => [
            'orden_id' => (int)$orden['orden_id'],
            'agente_id' => $orden['agente_id'],
            'velociraptor_client_id' => $orden['velociraptor_client_id'],
            'hostname' => $orden['hostname'],
            'codigo' => $orden['codigo'],
            'nombre' => $orden['nombre'],
            'artefacto_velociraptor' => $orden['artefacto_velociraptor'],
            'parametros' => json_decode($orden['parametros'] ?? '{}', true) ?: []
        ]
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responder(['ok' => false, 'error' => $e->getMessage()]);
}
