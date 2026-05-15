<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

$TOKEN_SECRETO = 'ZYPHER_BACKUP_TOKEN_2026';

function responder(bool $ok, array $data = []): void {
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function db(): PDO {
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASSWORD;

    $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";

    return new PDO($dsn, $DB_USER, $DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
}

$token = $_GET['token'] ?? '';
$agente_id = $_GET['agente_id'] ?? '';

if ($token !== $TOKEN_SECRETO) {
    responder(false, ['error' => 'Token inválido']);
}

if ($agente_id === '') {
    responder(false, ['error' => 'agente_id vacío']);
}

try {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT id, accion
        FROM backup_ordenes
        WHERE agente_id = :agente_id
          AND estado = 'pendiente'
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmt->execute([':agente_id' => $agente_id]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($orden) {
        $up = $pdo->prepare("
            UPDATE backup_ordenes
            SET estado = 'en_proceso',
                mensaje = 'Orden recibida por el agente',
                updated_at = NOW()
            WHERE id = :id
        ");
        $up->execute([':id' => $orden['id']]);
    }

    $stmt = $pdo->prepare("
        SELECT
            carpeta_codigo,
            CASE WHEN activa THEN 1 ELSE 0 END AS activa,
            frecuencia_dias,
            ultimo_backup_ok
        FROM backup_configuraciones
        WHERE agente_id = :agente_id
        ORDER BY carpeta_codigo ASC
    ");
    $stmt->execute([':agente_id' => $agente_id]);

    responder(true, [
        'orden' => $orden ?: null,
        'configuracion' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Throwable $e) {
    responder(false, ['error' => $e->getMessage()]);
}
