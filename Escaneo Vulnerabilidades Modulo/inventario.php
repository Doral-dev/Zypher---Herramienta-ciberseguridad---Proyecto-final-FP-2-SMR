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

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'JSON invalido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$required = [
    'agent_id',
    'hostname',
    'ip_objetivo',
    'sistema',
    'programas_instalados',
    'fecha_inventario'
];

foreach ($required as $field) {
    if (!array_key_exists($field, $data)) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Falta campo obligatorio: ' . $field
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$agent_id = trim((string)$data['agent_id']);
$hostname = trim((string)$data['hostname']);
$ip_objetivo = trim((string)$data['ip_objetivo']);
$sistema = trim((string)$data['sistema']);
$version_windows = isset($data['version_windows']) ? trim((string)$data['version_windows']) : null;
$build_windows = isset($data['build_windows']) ? trim((string)$data['build_windows']) : null;
$producto_windows = isset($data['producto_windows']) ? trim((string)$data['producto_windows']) : null;
$arquitectura = isset($data['arquitectura']) ? trim((string)$data['arquitectura']) : null;
$fecha_inventario = trim((string)$data['fecha_inventario']);

if (!is_array($data['programas_instalados'])) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'programas_instalados debe ser un array'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($agent_id === '' || $hostname === '' || $ip_objetivo === '' || $sistema === '' || $fecha_inventario === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Hay campos obligatorios vacios'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$programas_instalados = json_encode($data['programas_instalados'], JSON_UNESCAPED_UNICODE);

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
        INSERT INTO vulns_inventario_equipos (
            agent_id,
            hostname,
            ip_objetivo,
            sistema,
            version_windows,
            build_windows,
            producto_windows,
            arquitectura,
            programas_instalados,
            fecha_inventario,
            actualizado_en
        )
        VALUES (
            :agent_id,
            :hostname,
            :ip_objetivo,
            :sistema,
            :version_windows,
            :build_windows,
            :producto_windows,
            :arquitectura,
            CAST(:programas_instalados AS jsonb),
            :fecha_inventario,
            CURRENT_TIMESTAMP
        )
        ON CONFLICT (agent_id)
        DO UPDATE SET
            hostname = EXCLUDED.hostname,
            ip_objetivo = EXCLUDED.ip_objetivo,
            sistema = EXCLUDED.sistema,
            version_windows = EXCLUDED.version_windows,
            build_windows = EXCLUDED.build_windows,
            producto_windows = EXCLUDED.producto_windows,
            arquitectura = EXCLUDED.arquitectura,
            programas_instalados = EXCLUDED.programas_instalados,
            fecha_inventario = EXCLUDED.fecha_inventario,
            actualizado_en = CURRENT_TIMESTAMP
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':agent_id' => $agent_id,
        ':hostname' => $hostname,
        ':ip_objetivo' => $ip_objetivo,
        ':sistema' => $sistema,
        ':version_windows' => $version_windows,
        ':build_windows' => $build_windows,
        ':producto_windows' => $producto_windows,
        ':arquitectura' => $arquitectura,
        ':programas_instalados' => $programas_instalados,
        ':fecha_inventario' => $fecha_inventario
    ]);

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Inventario guardado correctamente'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error interno guardando inventario',
        'detalle' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
