<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Metodo no permitido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data['eventos']) || !is_array($data['eventos'])) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'JSON invalido o falta eventos'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

try {
    $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $sql = "
        INSERT INTO fim_eventos (
            agent_id,
            hostname,
            accion,
            ruta,
            tamano_anterior,
            tamano_actual,
            fecha_mod_anterior,
            fecha_mod_actual,
            fecha_evento
        )
        VALUES (
            :agent_id,
            :hostname,
            :accion,
            :ruta,
            :tamano_anterior,
            :tamano_actual,
            :fecha_mod_anterior,
            :fecha_mod_actual,
            :fecha_evento
        )
    ";

    $stmt = $pdo->prepare($sql);
    $guardados = 0;

    foreach ($data['eventos'] as $e) {
        $stmt->execute([
            ':agent_id' => $e['agent_id'] ?? '',
            ':hostname' => $e['hostname'] ?? '',
            ':accion' => $e['accion'] ?? '',
            ':ruta' => $e['ruta'] ?? '',
            ':tamano_anterior' => isset($e['tamano_anterior']) && $e['tamano_anterior'] !== '' ? $e['tamano_anterior'] : null,
            ':tamano_actual' => isset($e['tamano_actual']) && $e['tamano_actual'] !== '' ? $e['tamano_actual'] : null,
            ':fecha_mod_anterior' => !empty($e['fecha_mod_anterior']) ? $e['fecha_mod_anterior'] : null,
            ':fecha_mod_actual' => !empty($e['fecha_mod_actual']) ? $e['fecha_mod_actual'] : null,
            ':fecha_evento' => $e['fecha_evento'] ?? date('c')
        ]);
        $guardados++;
    }

    echo json_encode([
        'ok' => true,
        'guardados' => $guardados
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error interno',
        'detalle' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
