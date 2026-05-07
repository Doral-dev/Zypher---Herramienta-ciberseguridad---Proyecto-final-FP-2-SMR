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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    responder(false, ['error' => 'JSON inválido']);
}

$token = $data['token'] ?? '';
$orden_id = (int)($data['orden_id'] ?? 0);
$estado = $data['estado'] ?? '';
$resultado = $data['resultado'] ?? null;
$error = $data['error'] ?? null;

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

    $stmt = $pdo->prepare("
        UPDATE politicas_ordenes
        SET 
            estado = :estado,
            ejecutado_en = CURRENT_TIMESTAMP,
            resultado = :resultado,
            error = :error
        WHERE id = :orden_id
    ");

    $stmt->execute([
        ':estado' => $estado,
        ':resultado' => $resultado,
        ':error' => $error,
        ':orden_id' => $orden_id
    ]);

    responder(true, ['mensaje' => 'Resultado guardado']);

} catch (Throwable $e) {
    http_response_code(500);
    responder(false, ['error' => $e->getMessage()]);
}
