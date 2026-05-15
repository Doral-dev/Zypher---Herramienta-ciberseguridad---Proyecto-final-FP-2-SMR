<?php
declare(strict_types=1);

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

$AGENTE_ID = 'windows-agent-001';

$R2_ACCOUNT_ID = '78f4ace1f56a3935fd458fcddc18c673';
$R2_ACCESS_KEY = '44e6bef699a6c87cdecc32610a535d32';
$R2_SECRET_KEY = '84d3beed801df3551bd1c54258bb281bfff435688dce218d3d07e1d93dc37a96';
$R2_BUCKET = 'zypher-modulo-copias';

$CARPETAS = [
    'desktop' => 'Escritorio / Desktop',
    'documents' => 'Documentos / Documents',
    'downloads' => 'Descargas / Downloads',
    'pictures' => 'Imágenes / Pictures',
    'videos' => 'Vídeos / Videos',
    'music' => 'Música / Music'
];

$FRECUENCIAS = [
    1 => 'Cada día',
    7 => 'Cada semana',
    30 => 'Cada mes',
    90 => 'Cada 3 meses',
    180 => 'Cada 6 meses',
    365 => 'Cada año'
];

$ESTADOS_ACTIVOS = [
    'pendiente',
    'en_proceso',
    'preparando',
    'comprimiendo',
    'cifrando',
    'subiendo'
];

function db(): PDO {
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASSWORD;

    $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";

    return new PDO($dsn, $DB_USER, $DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
}

function hmac_sha256_bin(string $key, string $data): string {
    return hash_hmac('sha256', $data, $key, true);
}

function aws_signature_key(string $secret, string $date, string $region, string $service): string {
    $kDate = hmac_sha256_bin('AWS4' . $secret, $date);
    $kRegion = hmac_sha256_bin($kDate, $region);
    $kService = hmac_sha256_bin($kRegion, $service);
    return hmac_sha256_bin($kService, 'aws4_request');
}

function r2_encode_path(string $path): string {
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

function r2_request(string $method, string $object_key): string {
    global $R2_ACCOUNT_ID, $R2_ACCESS_KEY, $R2_SECRET_KEY, $R2_BUCKET;

    $region = 'auto';
    $service = 's3';
    $host = $R2_ACCOUNT_ID . '.r2.cloudflarestorage.com';

    $canonical_uri = '/' . r2_encode_path($R2_BUCKET . '/' . $object_key);
    $url = 'https://' . $host . $canonical_uri;

    $payload_hash = hash('sha256', '');
    $amz_date = gmdate('Ymd\THis\Z');
    $date_stamp = gmdate('Ymd');

    $canonical_headers =
        "host:$host\n" .
        "x-amz-content-sha256:$payload_hash\n" .
        "x-amz-date:$amz_date\n";

    $signed_headers = 'host;x-amz-content-sha256;x-amz-date';

    $canonical_request = implode("\n", [
        $method,
        $canonical_uri,
        '',
        $canonical_headers,
        $signed_headers,
        $payload_hash
    ]);

    $credential_scope = "$date_stamp/$region/$service/aws4_request";

    $string_to_sign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $amz_date,
        $credential_scope,
        hash('sha256', $canonical_request)
    ]);

    $signing_key = aws_signature_key($R2_SECRET_KEY, $date_stamp, $region, $service);
    $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

    $authorization =
        'AWS4-HMAC-SHA256 ' .
        "Credential=$R2_ACCESS_KEY/$credential_scope, " .
        "SignedHeaders=$signed_headers, " .
        "Signature=$signature";

    $headers = [
        "Host: $host",
        "x-amz-date: $amz_date",
        "x-amz-content-sha256: $payload_hash",
        "Authorization: $authorization",
        "User-Agent: ZypherWeb/1.0"
    ];

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 300
        ]
    ]);

    $response = file_get_contents($url, false, $context);

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }

    if ($status < 200 || $status >= 300) {
        throw new Exception("Error R2 HTTP $status: " . (string)$response);
    }

    return (string)$response;
}

function guardar_configuracion(PDO $pdo): void {
    global $AGENTE_ID, $CARPETAS, $FRECUENCIAS;

    foreach ($CARPETAS as $codigo => $nombre) {
        $activa = isset($_POST['carpetas'][$codigo]);
        $frecuencia = (int)($_POST['frecuencia'][$codigo] ?? 7);

        if (!array_key_exists($frecuencia, $FRECUENCIAS)) {
            $frecuencia = 7;
        }

        $stmt = $pdo->prepare("
            INSERT INTO backup_configuraciones
                (agente_id, carpeta_codigo, activa, frecuencia_dias, updated_at)
            VALUES
                (:agente_id, :carpeta_codigo, :activa, :frecuencia_dias, NOW())
            ON CONFLICT (agente_id, carpeta_codigo)
            DO UPDATE SET
                activa = EXCLUDED.activa,
                frecuencia_dias = EXCLUDED.frecuencia_dias,
                updated_at = NOW()
        ");

        $stmt->bindValue(':agente_id', $AGENTE_ID);
        $stmt->bindValue(':carpeta_codigo', $codigo);
        $stmt->bindValue(':activa', $activa, PDO::PARAM_BOOL);
        $stmt->bindValue(':frecuencia_dias', $frecuencia, PDO::PARAM_INT);
        $stmt->execute();
    }
}

function verificar_password_secundaria(PDO $pdo, string $password): bool {
    if ($password === '') {
        return false;
    }

    $stmt = $pdo->query("
        SELECT secundaria_hash
        FROM usuario_seguridad
        WHERE activa = true
          AND secundaria_hash IS NOT NULL
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!empty($row['secundaria_hash']) && password_verify($password, $row['secundaria_hash'])) {
            return true;
        }
    }

    return false;
}

function validar_secundaria_post(PDO $pdo): bool {
    $password = (string)($_POST['password_secundaria'] ?? '');
    return verificar_password_secundaria($pdo, $password);
}

function clase_estado(string $estado): string {
    if ($estado === 'completada') {
        return 'ok';
    }

    if ($estado === 'error') {
        return 'error';
    }

    if ($estado === 'cancelada') {
        return 'cancelada';
    }

    if (in_array($estado, ['pendiente', 'en_proceso', 'preparando', 'comprimiendo', 'cifrando', 'subiendo'], true)) {
        return 'progreso';
    }

    return 'pendiente';
}

$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['descargar_backup'])) {
    if (!validar_secundaria_post($pdo)) {
        $error = 'Contraseña secundaria incorrecta o no configurada.';
    } else {
        $id = (int)$_POST['backup_id'];

        $stmt = $pdo->prepare("
            SELECT id, archivo_r2
            FROM backup_historial
            WHERE id = :id
              AND agente_id = :agente_id
              AND estado = 'completada'
              AND COALESCE(oculto, false) = false
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $id,
            ':agente_id' => $AGENTE_ID
        ]);

        $backup = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$backup || empty($backup['archivo_r2'])) {
            $error = 'Backup no encontrado.';
        } else {
            try {
                $contenido_enc = r2_request('GET', $backup['archivo_r2']);
                $nombre_enc = basename($backup['archivo_r2']);
                $nombre_zip = str_replace('.zip.zypher.enc', '', $nombre_enc) . '_cifrado.zip';

                if (!class_exists('ZipArchive')) {
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . $nombre_enc . '"');
                    echo $contenido_enc;
                    exit;
                }

                $tmp = tempnam(sys_get_temp_dir(), 'zypher_backup_');
                $zip = new ZipArchive();

                if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
                    throw new Exception('No se pudo preparar la descarga.');
                }

                $zip->addFromString($nombre_enc, $contenido_enc);
                $zip->close();

                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $nombre_zip . '"');
                header('Content-Length: ' . filesize($tmp));

                readfile($tmp);
                unlink($tmp);
                exit;

            } catch (Throwable $e) {
                $error = 'Error descargando backup: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_config'])) {
        guardar_configuracion($pdo);

        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=config');
        exit;
    }

    if (isset($_POST['backup_ahora'])) {
        guardar_configuracion($pdo);

        $stmt = $pdo->prepare("
            SELECT id
            FROM backup_ordenes
            WHERE agente_id = :agente_id
              AND estado IN ('pendiente', 'en_proceso', 'preparando', 'comprimiendo', 'cifrando', 'subiendo')
            LIMIT 1
        ");
        $stmt->execute([':agente_id' => $AGENTE_ID]);
        $activa = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$activa) {
            $stmt = $pdo->prepare("
                INSERT INTO backup_ordenes (agente_id, accion, estado, mensaje)
                VALUES (:agente_id, 'backup_ahora', 'pendiente', 'Esperando al agente')
            ");
            $stmt->execute([':agente_id' => $AGENTE_ID]);
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=orden');
        exit;
    }

    if (isset($_POST['cancelar_backup'])) {
        if (!validar_secundaria_post($pdo)) {
            $error = 'Contraseña secundaria incorrecta o no configurada.';
        } else {
            $orden_id = (int)$_POST['orden_id'];

            $stmt = $pdo->prepare("
                UPDATE backup_ordenes
                SET estado = 'cancelada',
                    mensaje = 'Cancelada por el usuario',
                    updated_at = NOW()
                WHERE id = :id
                  AND agente_id = :agente_id
                  AND estado IN ('pendiente', 'en_proceso', 'preparando', 'comprimiendo', 'cifrando', 'subiendo')
            ");
            $stmt->execute([
                ':id' => $orden_id,
                ':agente_id' => $AGENTE_ID
            ]);

            $stmt = $pdo->prepare("
                INSERT INTO backup_historial
                    (agente_id, estado, carpetas, archivo_r2, tamano_mb, mensaje)
                VALUES
                    (:agente_id, 'cancelada', '-', NULL, NULL, 'Backup cancelado por el usuario')
            ");
            $stmt->execute([':agente_id' => $AGENTE_ID]);

            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=cancelado');
            exit;
        }
    }

    if (isset($_POST['eliminar_backup'])) {
        if (!validar_secundaria_post($pdo)) {
            $error = 'Contraseña secundaria incorrecta o no configurada.';
        } else {
            $id = (int)$_POST['backup_id'];

            try {
                $stmt = $pdo->prepare("
                    SELECT archivo_r2
                    FROM backup_historial
                    WHERE id = :id
                      AND agente_id = :agente_id
                    LIMIT 1
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':agente_id' => $AGENTE_ID
                ]);

                $backup = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($backup && !empty($backup['archivo_r2'])) {
                    r2_request('DELETE', $backup['archivo_r2']);
                }

                $stmt = $pdo->prepare("
                    DELETE FROM backup_historial
                    WHERE id = :id
                      AND agente_id = :agente_id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':agente_id' => $AGENTE_ID
                ]);

                header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=eliminado');
                exit;

            } catch (Throwable $e) {
                $error = 'Error eliminando backup: ' . $e->getMessage();
            }
        }
    }

    if (isset($_POST['limpiar_historial'])) {
        if (!validar_secundaria_post($pdo)) {
            $error = 'Contraseña secundaria incorrecta o no configurada.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE backup_historial
                SET oculto = true
                WHERE agente_id = :agente_id
            ");
            $stmt->execute([':agente_id' => $AGENTE_ID]);

            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=historial');
            exit;
        }
    }
}

$stmt = $pdo->prepare("
    SELECT 
        carpeta_codigo,
        CASE WHEN activa THEN 1 ELSE 0 END AS activa,
        frecuencia_dias,
        ultimo_backup_ok
    FROM backup_configuraciones
    WHERE agente_id = :agente_id
");
$stmt->execute([':agente_id' => $AGENTE_ID]);

$configs = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $configs[$row['carpeta_codigo']] = $row;
}

$stmt = $pdo->prepare("
    SELECT id, estado, accion, mensaje, created_at, updated_at
    FROM backup_ordenes
    WHERE agente_id = :agente_id
      AND estado IN ('pendiente', 'en_proceso', 'preparando', 'comprimiendo', 'cifrando', 'subiendo')
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([':agente_id' => $AGENTE_ID]);
$orden_activa = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT id, estado, carpetas, archivo_r2, tamano_mb, mensaje, created_at
    FROM backup_historial
    WHERE agente_id = :agente_id
      AND COALESCE(oculto, false) = false
    ORDER BY created_at DESC
    LIMIT 30
");
$stmt->execute([':agente_id' => $AGENTE_ID]);
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

$backup_en_proceso = $orden_activa ? true : false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Copias de seguridad - Zypher</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: #e5e7eb;
            margin: 0;
            padding: 30px;
        }

        .card {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 24px;
        }

        h1, h2 {
            margin-top: 0;
        }

        p {
            color: #9ca3af;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #374151;
            text-align: left;
            vertical-align: middle;
        }

        th {
            color: #93c5fd;
        }

        input[type="checkbox"] {
            transform: scale(1.2);
        }

        input[type="password"],
        select,
        button {
            padding: 8px 12px;
            border-radius: 8px;
            border: 0;
        }

        button {
            background: #2563eb;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        button:disabled {
            background: #6b7280;
            cursor: not-allowed;
        }

        .danger {
            background: #dc2626;
        }

        .danger:hover {
            background: #b91c1c;
        }

        .warning {
            background: #ca8a04;
        }

        .warning:hover {
            background: #a16207;
        }

        .ok { color: #22c55e; }
        .error { color: #ef4444; }
        .pendiente { color: #facc15; }
        .progreso { color: #38bdf8; }
        .cancelada { color: #f97316; }
        .muted { color: #9ca3af; }

        .acciones {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .acciones form {
            margin: 0;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .mini {
            max-width: 190px;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>Copias de seguridad</h1>
    <p>Equipo: <?php echo htmlspecialchars($AGENTE_ID); ?></p>

    <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'config'): ?>
        <p class="ok">Configuración guardada correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'orden'): ?>
        <p class="ok">Orden de copia gestionada correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'cancelado'): ?>
        <p class="ok">Backup cancelado correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'eliminado'): ?>
        <p class="ok">Backup eliminado correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'historial'): ?>
        <p class="ok">Historial limpiado correctamente. Los archivos de R2 no se han borrado.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Configuración automática</h2>

    <form method="post">
        <table>
            <thead>
                <tr>
                    <th>Carpeta</th>
                    <th>Activar</th>
                    <th>Frecuencia</th>
                    <th>Última copia correcta</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($CARPETAS as $codigo => $nombre): ?>
                <?php
                    $cfg = $configs[$codigo] ?? null;
                    $activa = $cfg && (int)$cfg['activa'] === 1;
                    $freq = $cfg['frecuencia_dias'] ?? 7;
                    $ultimo = $cfg['ultimo_backup_ok'] ?? '-';
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($nombre); ?></td>
                    <td>
                        <input type="checkbox" name="carpetas[<?php echo htmlspecialchars($codigo); ?>]" <?php echo $activa ? 'checked' : ''; ?>>
                    </td>
                    <td>
                        <select name="frecuencia[<?php echo htmlspecialchars($codigo); ?>]">
                            <?php foreach ($FRECUENCIAS as $dias => $label): ?>
                                <option value="<?php echo (int)$dias; ?>" <?php echo ((int)$freq === (int)$dias) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><?php echo htmlspecialchars((string)$ultimo); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <br>

        <button type="submit" name="guardar_config">Guardar configuración</button>

        <?php if ($backup_en_proceso): ?>
            <button type="button" disabled>En proceso...</button>
        <?php else: ?>
            <button type="submit" name="backup_ahora">Ejecutar copia ahora</button>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>Historial</h2>

    <form method="post" onsubmit="return confirm('¿Seguro que quieres limpiar el historial visual? No se borrarán los archivos de R2.');">
        <input class="mini" type="password" name="password_secundaria" placeholder="Contraseña secundaria" required>
        <button type="submit" name="limpiar_historial" class="danger">Limpiar historial</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Carpetas</th>
                <th>Tamaño</th>
                <th>Archivo R2</th>
                <th>Mensaje</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>

        <?php if ($orden_activa): ?>
            <?php
                $estado = (string)$orden_activa['estado'];
                $clase = clase_estado($estado);
            ?>
            <tr>
                <td><?php echo htmlspecialchars((string)$orden_activa['updated_at']); ?></td>
                <td class="<?php echo $clase; ?>"><?php echo htmlspecialchars($estado); ?></td>
                <td>-</td>
                <td>-</td>
                <td>-</td>
                <td><?php echo htmlspecialchars((string)($orden_activa['mensaje'] ?? 'Backup en proceso')); ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('¿Seguro que quieres cancelar este backup?');">
                        <input type="hidden" name="orden_id" value="<?php echo (int)$orden_activa['id']; ?>">
                        <input class="mini" type="password" name="password_secundaria" placeholder="Contraseña secundaria" required>
                        <button type="submit" name="cancelar_backup" class="warning">Cancelar backup</button>
                    </form>
                </td>
            </tr>
        <?php endif; ?>

        <?php foreach ($historial as $h): ?>
            <?php
                $estado = (string)$h['estado'];
                $estadoClase = clase_estado($estado);
            ?>
            <tr>
                <td><?php echo htmlspecialchars((string)$h['created_at']); ?></td>
                <td class="<?php echo $estadoClase; ?>">
                    <?php echo htmlspecialchars($estado); ?>
                </td>
                <td><?php echo htmlspecialchars((string)($h['carpetas'] ?? '-')); ?></td>
                <td><?php echo htmlspecialchars((string)($h['tamano_mb'] ?? '-')); ?> MB</td>
                <td><?php echo htmlspecialchars((string)($h['archivo_r2'] ?? '-')); ?></td>
                <td><?php echo htmlspecialchars((string)($h['mensaje'] ?? '-')); ?></td>
                <td>
                    <div class="acciones">
                        <?php if ($estado === 'completada' && !empty($h['archivo_r2'])): ?>
                            <form method="post">
                                <input type="hidden" name="backup_id" value="<?php echo (int)$h['id']; ?>">
                                <input class="mini" type="password" name="password_secundaria" placeholder="Contraseña secundaria" required>
                                <button type="submit" name="descargar_backup">Descargar</button>
                            </form>
                        <?php endif; ?>

                        <?php if (in_array($estado, ['completada', 'error', 'cancelada'], true)): ?>
                            <form method="post" onsubmit="return confirm('¿Seguro que quieres eliminar este backup del historial y de R2 si existe?');">
                                <input type="hidden" name="backup_id" value="<?php echo (int)$h['id']; ?>">
                                <input class="mini" type="password" name="password_secundaria" placeholder="Contraseña secundaria" required>
                                <button type="submit" name="eliminar_backup" class="danger">Eliminar backup</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$historial && !$orden_activa): ?>
            <tr>
                <td colspan="7" class="muted">Todavía no hay copias registradas.</td>
            </tr>
        <?php endif; ?>

        </tbody>
    </table>
</div>

</body>
</html>
