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

$token = $_GET['token'] ?? '';
$agente_id = $_GET['agente_id'] ?? '';

if ($token !== $API_TOKEN) {
    http_response_code(401);
    responder(false, ['error' => 'Token inválido']);
}

if ($agente_id === '') {
    http_response_code(400);
    responder(false, ['error' => 'agente_id requerido']);
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
            po.id AS orden_id,
            po.accion,
            po.agente_id,
            ps.id AS politica_id,
            ps.codigo,
            ps.categoria,
            ps.subcategoria,
            ps.nombre,
            ps.descripcion,
            ps.comando_aplicar,
            ps.comando_verificar,
            ps.valor_recomendado
        FROM politicas_ordenes po
        INNER JOIN politicas_seguridad ps ON ps.id = po.politica_id
        WHERE po.estado = 'pendiente'
          AND po.agente_id = :agente_id
          AND ps.activa = TRUE
        ORDER BY po.id ASC
        LIMIT 1
        FOR UPDATE SKIP LOCKED
    ");

    $stmt->execute([
        ':agente_id' => $agente_id
    ]);

    $orden = $stmt->fetch();

    if (!$orden) {
        $pdo->commit();
        responder(true, ['orden' => null]);
    }

    $update = $pdo->prepare("
        UPDATE politicas_ordenes
        SET estado = 'en_proceso'
        WHERE id = :id
    ");

    $update->execute([
        ':id' => $orden['orden_id']
    ]);

    $pdo->commit();

    responder(true, ['orden' => $orden]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    responder(false, ['error' => $e->getMessage()]);
}
