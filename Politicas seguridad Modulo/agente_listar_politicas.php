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

    $stmt = $pdo->query("
        SELECT
            id AS politica_id,
            codigo,
            categoria,
            subcategoria,
            nombre,
            descripcion,
            comando_aplicar,
            comando_verificar,
            valor_recomendado
        FROM politicas_seguridad
        WHERE activa = TRUE
        ORDER BY categoria, subcategoria, id
    ");

    responder(true, [
        'politicas' => $stmt->fetchAll()
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    responder(false, ['error' => $e->getMessage()]);
}
