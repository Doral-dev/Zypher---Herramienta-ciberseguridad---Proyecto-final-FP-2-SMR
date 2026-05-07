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
$agente_id = $data['agente_id'] ?? '';
$politica_id = (int)($data['politica_id'] ?? 0);
$cumple = $data['cumple'] ?? null;
$valor_actual = $data['valor_actual'] ?? '';
$valor_recomendado = $data['valor_recomendado'] ?? '';
$detalle = $data['detalle'] ?? '';

if ($token !== $API_TOKEN) {
    http_response_code(401);
    responder(false, ['error' => 'Token inválido']);
}

if ($agente_id === '') {
    http_response_code(400);
    responder(false, ['error' => 'agente_id requerido']);
}

if ($politica_id <= 0) {
    http_response_code(400);
    responder(false, ['error' => 'politica_id inválido']);
}

if (!is_bool($cumple)) {
    http_response_code(400);
    responder(false, ['error' => 'cumple debe ser true o false']);
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
        INSERT INTO politicas_estado_agente
            (politica_id, agente_id, valor_actual, valor_recomendado, cumple, ultima_revision)
        VALUES
            (:politica_id, CAST(:agente_id_insert AS VARCHAR(120)), :valor_actual, :valor_recomendado, :cumple, CURRENT_TIMESTAMP)
        ON CONFLICT (politica_id, agente_id)
        DO UPDATE SET
            valor_actual = EXCLUDED.valor_actual,
            valor_recomendado = EXCLUDED.valor_recomendado,
            cumple = EXCLUDED.cumple,
            ultima_revision = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        ':politica_id' => $politica_id,
        ':agente_id_insert' => $agente_id,
        ':valor_actual' => $valor_actual,
        ':valor_recomendado' => $valor_recomendado,
        ':cumple' => $cumple ? 1 : 0,
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO politicas_historial
            (politica_id, agente_id, accion, estado, detalle)
        VALUES
            (:politica_id, CAST(:agente_id_historial AS VARCHAR(120)), 'verificacion_auto', :estado, :detalle)
    ");

    $stmt->execute([
        ':politica_id' => $politica_id,
        ':agente_id_historial' => $agente_id,
        ':estado' => $cumple ? 'correcto' : 'incorrecto',
        ':detalle' => $detalle,
    ]);

    $pdo->commit();

    responder(true, ['mensaje' => 'Estado guardado']);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    responder(false, ['error' => $e->getMessage()]);
}
