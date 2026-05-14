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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    responder(false, ['error' => 'JSON inválido']);
}

if (($data['token'] ?? '') !== $TOKEN_SECRETO) {
    responder(false, ['error' => 'Token inválido']);
}

$agente_id = (string)($data['agente_id'] ?? '');
$orden_id = $data['orden_id'] ?? null;
$estado = (string)($data['estado'] ?? 'error');
$carpetas = $data['carpetas'] ?? [];
$archivo_r2 = $data['archivo_r2'] ?? null;
$tamano_mb = $data['tamano_mb'] ?? null;
$mensaje = $data['mensaje'] ?? null;

if ($agente_id === '') {
    responder(false, ['error' => 'agente_id vacío']);
}

if (!is_array($carpetas)) {
    $carpetas = [];
}

$estados_validos = ['pendiente', 'en_proceso', 'completada', 'error'];

if (!in_array($estado, $estados_validos, true)) {
    $estado = 'error';
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    if ($orden_id !== null && $orden_id !== '') {
        $stmt = $pdo->prepare("
            UPDATE backup_ordenes
            SET estado = :estado,
                mensaje = :mensaje,
                updated_at = NOW()
            WHERE id = :id
              AND agente_id = :agente_id
        ");
        $stmt->execute([
            ':estado' => $estado,
            ':mensaje' => $mensaje,
            ':id' => (int)$orden_id,
            ':agente_id' => $agente_id
        ]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO backup_historial
            (agente_id, estado, carpetas, archivo_r2, tamano_mb, mensaje)
        VALUES
            (:agente_id, :estado, :carpetas, :archivo_r2, :tamano_mb, :mensaje)
    ");

    $stmt->execute([
        ':agente_id' => $agente_id,
        ':estado' => $estado,
        ':carpetas' => implode(', ', $carpetas),
        ':archivo_r2' => $archivo_r2,
        ':tamano_mb' => $tamano_mb,
        ':mensaje' => $mensaje
    ]);

    if ($estado === 'completada') {
        foreach ($carpetas as $carpeta) {
            $stmt = $pdo->prepare("
                UPDATE backup_configuraciones
                SET ultimo_backup_ok = NOW(),
                    updated_at = NOW()
                WHERE agente_id = :agente_id
                  AND carpeta_codigo = :carpeta_codigo
            ");
            $stmt->execute([
                ':agente_id' => $agente_id,
                ':carpeta_codigo' => $carpeta
            ]);
        }
    }

    $pdo->commit();

    responder(true);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    responder(false, ['error' => $e->getMessage()]);
}
