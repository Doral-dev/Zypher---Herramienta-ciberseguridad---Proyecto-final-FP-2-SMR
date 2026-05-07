<?php
header('Content-Type: application/json; charset=utf-8');

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

$API_TOKEN = 'ZYHPER_POLITICAS_TOKEN_2026';

function responder($ok, $data = []) {
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responder(false, ['error' => 'Método no permitido']);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    responder(false, ['error' => 'JSON inválido']);
}

$token = $data['token'] ?? '';
$orden_id = (int)($data['orden_id'] ?? 0);
$estado = $data['estado'] ?? '';
$resultado = $data['resultado'] ?? '';
$error = $data['error'] ?? '';

if ($token !== $API_TOKEN) {
    http_response_code(401);
    responder(false, ['error' => 'Token inválido']);
}

if ($orden_id <= 0) {
    http_response_code(400);
    responder(false, ['error' => 'orden_id inválido']);
}

if (!in_array($estado, ['completada', 'error'], true)) {
    http_response_code(400);
    responder(false, ['error' => 'Estado inválido']);
}

try {
    $pdo = new PDO(
        "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME",
        $DB_USER,
        $DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT 
            po.id,
            po.politica_id,
            po.agente_id,
            po.accion,
            ps.valor_recomendado
        FROM politicas_ordenes po
        INNER JOIN politicas_seguridad ps ON ps.id = po.politica_id
        WHERE po.id = :orden_id
        FOR UPDATE
    ");
    $stmt->execute([':orden_id' => $orden_id]);
    $orden = $stmt->fetch();

    if (!$orden) {
        $pdo->rollBack();
        http_response_code(404);
        responder(false, ['error' => 'Orden no encontrada']);
    }

    $upd = $pdo->prepare("
        UPDATE politicas_ordenes
        SET 
            estado = :estado,
            ejecutado_en = CURRENT_TIMESTAMP,
            resultado = :resultado,
            error = :error
        WHERE id = :orden_id
    ");
    $upd->execute([
        ':estado' => $estado,
        ':resultado' => $resultado,
        ':error' => $error,
        ':orden_id' => $orden_id
    ]);

    $cumple = ($estado === 'completada');

    $estadoAgente = $pdo->prepare("
        INSERT INTO politicas_estado_agente
            (politica_id, agente_id, valor_actual, valor_recomendado, cumple, ultima_revision)
        VALUES
            (:politica_id, :agente_id, :valor_actual, :valor_recomendado, :cumple, CURRENT_TIMESTAMP)
        ON CONFLICT (politica_id, agente_id)
        DO UPDATE SET
            valor_actual = EXCLUDED.valor_actual,
            valor_recomendado = EXCLUDED.valor_recomendado,
            cumple = EXCLUDED.cumple,
            ultima_revision = CURRENT_TIMESTAMP
    ");
    $estadoAgente->execute([
        ':politica_id' => $orden['politica_id'],
        ':agente_id' => $orden['agente_id'],
        ':valor_actual' => $resultado,
        ':valor_recomendado' => $orden['valor_recomendado'],
        ':cumple' => $cumple ? 1 : 0
    ]);

    $hist = $pdo->prepare("
        INSERT INTO politicas_historial
            (politica_id, agente_id, accion, estado, detalle)
        VALUES
            (:politica_id, :agente_id, :accion, :estado, :detalle)
    ");
    $hist->execute([
        ':politica_id' => $orden['politica_id'],
        ':agente_id' => $orden['agente_id'],
        ':accion' => $orden['accion'],
        ':estado' => $estado,
        ':detalle' => $cumple ? $resultado : $error
    ]);

    $pdo->commit();

    responder(true, ['mensaje' => 'Resultado guardado']);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    responder(false, ['error' => $e->getMessage()]);
}
