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
    $parts = explode('/', $path);
    $encoded = array_map('rawurlencode', $parts);
    return implode('/', $encoded);
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

function guardar_opciones(PDO $pdo): void {
    global $AGENTE_ID;

    $proteger = isset($_POST['proteger_descarga']);
    $password = (string)($_POST['password_descarga'] ?? '');

    $stmt = $pdo->prepare("
        SELECT password_hash
        FROM backup_opciones
        WHERE agente_id = :agente_id
    ");
    $stmt->execute([':agente_id' => $AGENTE_ID]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);

    $hash = $actual['password_hash'] ?? null;

    if ($proteger && $password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }

    if (!$proteger) {
        $hash = null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO backup_opciones
            (agente_id, proteger_descarga, password_hash, updated_at)
        VALUES
            (:agente_id, :proteger_descarga, :password_hash, NOW())
        ON CONFLICT (agente_id)
        DO UPDATE SET
            proteger_descarga = EXCLUDED.proteger_descarga,
            password_hash = EXCLUDED.password_hash,
            updated_at = NOW()
    ");

    $stmt->bindValue(':agente_id', $AGENTE_ID);
    $stmt->bindValue(':proteger_descarga', $proteger, PDO::PARAM_BOOL);
    $stmt->bindValue(':password_hash', $hash);
    $stmt->execute();
}

$pdo = db();
$error = '';

$stmt = $pdo->prepare("
    SELECT proteger_descarga, password_hash
    FROM backup_opciones
    WHERE agente_id = :agente_id
");
$stmt->execute([':agente_id' => $AGENTE_ID]);
$opciones = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'proteger_descarga' => false,
    'password_hash' => null
];

if (isset($_GET['download'])) {
    $id = (int)$_GET['download'];

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
        die('Backup no encontrado.');
    }

    $proteger = filter_var($opciones['proteger_descarga'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $hash = $opciones['password_hash'] ?? null;

    if ($proteger) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['password_confirmacion'])) {
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <title>Confirmar descarga</title>
                <style>
                    body { font-family: Arial, sans-serif; background:#0f172a; color:#e5e7eb; padding:40px; }
                    .card { background:#111827; border:1px solid #1f2937; border-radius:14px; padding:22px; max-width:420px; }
                    input, button { padding:10px; border-radius:8px; border:0; margin-top:10px; width:100%; }
                    button { background:#2563eb; color:white; cursor:pointer; }
                    a { color:#93c5fd; }
                </style>
            </head>
            <body>
                <div class="card">
                    <h2>Contraseña de descarga</h2>
                    <form method="post">
                        <input type="password" name="password_confirmacion" placeholder="Contraseña" required>
                        <button type="submit">Descargar backup</button>
                    </form>
                    <p><a href="copias_seguridad.php">Volver</a></p>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        $password = (string)($_POST['password_confirmacion'] ?? '');

        if (!$hash || !password_verify($password, $hash)) {
            die('Contraseña incorrecta.');
        }
    }

    $contenido = r2_request('GET', $backup['archivo_r2']);
    $filename = basename($backup['archivo_r2']);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($contenido));
    echo $contenido;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_config'])) {
        guardar_configuracion($pdo);
        guardar_opciones($pdo);

        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=config');
        exit;
    }

    if (isset($_POST['backup_ahora'])) {
        guardar_configuracion($pdo);
        guardar_opciones($pdo);

        $stmt = $pdo->prepare("
            UPDATE backup_ordenes
            SET estado = 'error',
                mensaje = 'Cancelada automáticamente por nueva orden manual',
                updated_at = NOW()
            WHERE agente_id = :agente_id
              AND estado IN ('pendiente', 'en_proceso', 'preparando', 'comprimiendo', 'cifrando', 'subiendo')
        ");
        $stmt->execute([':agente_id' => $AGENTE_ID]);

        $stmt = $pdo->prepare("
            INSERT INTO backup_ordenes (agente_id, accion, estado, mensaje)
            VALUES (:agente_id, 'backup_ahora', 'pendiente', 'Esperando al agente')
        ");
        $stmt->execute([':agente_id' => $AGENTE_ID]);

        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=orden');
        exit;
    }

    if (isset($_POST['eliminar_backup'])) {
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

    if (isset($_POST['limpiar_historial'])) {
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
    ORDER BY id DESC
    LIMIT 5
");
$stmt->execute([':agente_id' => $AGENTE_ID]);
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

$stmt = $pdo->prepare("
    SELECT proteger_descarga, password_hash
    FROM backup_opciones
    WHERE agente_id = :agente_id
");
$stmt->execute([':agente_id' => $AGENTE_ID]);
$opciones = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'proteger_descarga' => false,
    'password_hash' => null
];

$proteger_descarga = filter_var($opciones['proteger_descarga'] ?? false, FILTER_VALIDATE_BOOLEAN);
$tiene_password = !empty($opciones['password_hash']);
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
        }

        th {
            color: #93c5fd;
        }

        input[type="checkbox"] {
            transform: scale(1.2);
        }

        input[type="password"], select, button, a.btn {
            padding: 8px 12px;
            border-radius: 8px;
            border: 0;
        }

        button, a.btn {
            background: #2563eb;
            color: white;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        button:hover, a.btn:hover {
            background: #1d4ed8;
        }

        .danger {
            background: #dc2626;
        }

        .danger:hover {
            background: #b91c1c;
        }

        .ok { color: #22c55e; }
        .error { color: #ef4444; }
        .pendiente { color: #facc15; }
        .progreso { color: #38bdf8; }
        .muted { color: #9ca3af; }

        .acciones {
            display: flex;
            gap: 8px;
            align-items: center;
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
        <p class="ok">Configuración guardada y orden de copia creada correctamente.</p>
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

        <h2>Seguridad de descarga</h2>

        <p>
            <label>
                <input type="checkbox" name="proteger_descarga" <?php echo $proteger_descarga ? 'checked' : ''; ?>>
                Proteger descargas con contraseña
            </label>
        </p>

        <p>
            <input type="password" name="password_descarga" placeholder="<?php echo $tiene_password ? 'Nueva contraseña opcional' : 'Contraseña de descarga'; ?>">
        </p>

        <br>

        <button type="submit" name="guardar_config">Guardar configuración</button>
        <button type="submit" name="backup_ahora">Ejecutar copia ahora</button>
    </form>
</div>

<div class="card">
    <h2>Progreso</h2>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Mensaje</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($ordenes as $o): ?>
            <?php
                $clase = 'pendiente';
                if (in_array($o['estado'], ['preparando', 'comprimiendo', 'cifrando', 'subiendo', 'en_proceso'], true)) {
                    $clase = 'progreso';
                } elseif ($o['estado'] === 'completada') {
                    $clase = 'ok';
                } elseif ($o['estado'] === 'error') {
                    $clase = 'error';
                }
            ?>
            <tr>
                <td><?php echo htmlspecialchars((string)$o['updated_at']); ?></td>
                <td class="<?php echo $clase; ?>"><?php echo htmlspecialchars((string)$o['estado']); ?></td>
                <td><?php echo htmlspecialchars((string)($o['mensaje'] ?? '-')); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$ordenes): ?>
            <tr>
                <td colspan="3" class="muted">Sin órdenes recientes.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Historial</h2>

    <form method="post" onsubmit="return confirm('¿Seguro que quieres limpiar el historial visual? No se borrarán los archivos de R2.');">
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
        <?php foreach ($historial as $h): ?>
            <?php
                $estadoClase = 'pendiente';

                if ($h['estado'] === 'completada') {
                    $estadoClase = 'ok';
                } elseif ($h['estado'] === 'error') {
                    $estadoClase = 'error';
                }
            ?>
            <tr>
                <td><?php echo htmlspecialchars((string)$h['created_at']); ?></td>
                <td class="<?php echo $estadoClase; ?>">
                    <?php echo htmlspecialchars((string)$h['estado']); ?>
                </td>
                <td><?php echo htmlspecialchars((string)($h['carpetas'] ?? '-')); ?></td>
                <td><?php echo htmlspecialchars((string)($h['tamano_mb'] ?? '-')); ?> MB</td>
                <td><?php echo htmlspecialchars((string)($h['archivo_r2'] ?? '-')); ?></td>
                <td><?php echo htmlspecialchars((string)($h['mensaje'] ?? '-')); ?></td>
                <td>
                    <div class="acciones">
                        <?php if ($h['estado'] === 'completada' && !empty($h['archivo_r2'])): ?>
                            <a class="btn" href="?download=<?php echo (int)$h['id']; ?>">Descargar</a>
                        <?php endif; ?>

                        <form method="post" onsubmit="return confirm('¿Seguro que quieres eliminar este backup completo de R2 y del historial?');">
                            <input type="hidden" name="backup_id" value="<?php echo (int)$h['id']; ?>">
                            <button type="submit" name="eliminar_backup" class="danger">Eliminar backup</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$historial): ?>
            <tr>
                <td colspan="7" class="muted">Todavía no hay copias registradas.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
