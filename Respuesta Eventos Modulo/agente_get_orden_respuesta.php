<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

$TOKEN_VALIDO = 'ZYPHER_RESPUESTA_TOKEN_2026';

function responder(bool $ok, array $data = []): void {
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

$token = $_GET['token'] ?? '';
$agenteId = $_GET['agente_id'] ?? '';

if ($token !== $TOKEN_VALIDO) {
    responder(false, ['error' => 'Token inválido']);
}

if ($agenteId === '') {
    responder(false, ['error' => 'Falta agente_id']);
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            ro.id AS orden_id,
            ro.parametros,
            ra.codigo,
            ra.nombre,
            ra.descripcion,
            re.agente_id,
            re.hostname
        FROM respuesta_ordenes ro
        JOIN respuesta_equipos re ON re.id = ro.equipo_id
        JOIN respuesta_acciones ra ON ra.id = ro.accion_id
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
        responder(true, ['orden' => null]);
    }

    $upd = $pdo->prepare("
        UPDATE respuesta_ordenes
        SET estado = 'en_proceso',
            ejecutado_en = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $upd->execute([':id' => $orden['orden_id']]);

    $pdo->commit();

    responder(true, ['orden' => $orden]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responder(false, ['error' => $e->getMessage()]);
}
