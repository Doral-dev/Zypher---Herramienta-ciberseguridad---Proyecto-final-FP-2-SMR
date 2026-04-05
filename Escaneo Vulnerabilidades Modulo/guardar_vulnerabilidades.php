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

if (!is_array($data) || !isset($data['vulnerabilidades']) || !is_array($data['vulnerabilidades'])) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'JSON invalido o falta vulnerabilidades'
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
        INSERT INTO vulns_resultados (
            agent_id,
            agent_name,
            cve,
            severidad,
            cvss,
            paquete,
            version_paquete,
            descripcion,
            referencia,
            remediacion,
            estado,
            actualizado_en
        )
        VALUES (
            :agent_id,
            :agent_name,
            :cve,
            :severidad,
            :cvss,
            :paquete,
            :version_paquete,
            :descripcion,
            :referencia,
            :remediacion,
            :estado,
            CURRENT_TIMESTAMP
        )
        ON CONFLICT (agent_id, cve, paquete, version_paquete)
        DO UPDATE SET
            severidad = EXCLUDED.severidad,
            cvss = EXCLUDED.cvss,
            descripcion = EXCLUDED.descripcion,
            referencia = EXCLUDED.referencia,
            actualizado_en = CURRENT_TIMESTAMP
    ";

    $stmt = $pdo->prepare($sql);
    $guardadas = 0;

    foreach ($data['vulnerabilidades'] as $v) {
        $stmt->execute([
            ':agent_id' => $v['agent_id'] ?? '',
            ':agent_name' => $v['agent_name'] ?? '',
            ':cve' => $v['cve'] ?? '',
            ':severidad' => $v['severity'] ?? '',
            ':cvss' => (isset($v['score']) && is_numeric($v['score']) && $v['score'] !== '') ? $v['score'] : null,
            ':paquete' => $v['paquete'] ?? '',
            ':version_paquete' => $v['version_paquete'] ?? '',
            ':descripcion' => $v['descripcion'] ?? '',
            ':referencia' => $v['referencia'] ?? '',
            ':remediacion' => '',
            ':estado' => ''
        ]);

        $guardadas++;
    }

    echo json_encode([
        'ok' => true,
        'guardadas' => $guardadas
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error interno',
        'detalle' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
