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

    return new PDO(
        "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME",
        $DB_USER,
        $DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function h($txt): string {
    return htmlspecialchars((string)$txt, ENT_QUOTES, 'UTF-8');
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
    return verificar_password_secundaria($pdo, (string)($_POST['password_secundaria'] ?? ''));
}

function ultima_copia_ruta(PDO $pdo, string $agente_id, string $ruta): string {
    $stmt = $pdo->prepare("
        SELECT created_at
        FROM backup_historial
        WHERE agente_id = :agente_id
          AND estado = 'completada'
          AND COALESCE(oculto, false) = false
          AND carpetas ILIKE :ruta
        ORDER BY created_at DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':agente_id' => $agente_id,
        ':ruta' => '%' . $ruta . '%'
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (string)$row['created_at'] : '-';
}

$pdo = db();
$error = '';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS backup_personalizado_config (
        id SERIAL PRIMARY KEY,
        agente_id VARCHAR(100) NOT NULL,
        ruta TEXT NOT NULL,
        activa BOOLEAN DEFAULT TRUE,
        frecuencia_dias INTEGER DEFAULT 7,
        ultimo_backup_ok TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT NOW(),
        updated_at TIMESTAMP DEFAULT NOW()
    )
");

$pdo->exec("
    ALTER TABLE backup_personalizado_config
    ALTER COLUMN nombre DROP NOT NULL
");

$pdo->exec("
    ALTER TABLE backup_personalizado_config
    ALTER COLUMN tipo DROP NOT NULL
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['descargar_backup'])) {
    if (!validar_secundaria_post($pdo)) {
        $error = 'Contraseña secundaria incorrecta o no configurada.';
    } else {
        try {
            $id = (int)$_POST['backup_id'];

            $stmt = $pdo->prepare("
                SELECT archivo_r2
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
                throw new Exception('Backup no encontrado.');
            }

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['guardar_configuracion']) || isset($_POST['backup_ahora'])) {
            $ids = $_POST['id'] ?? [];
            $rutas = $_POST['ruta'] ?? [];
            $frecuencias = $_POST['frecuencia'] ?? [];

            foreach ($rutas as $i => $ruta) {
                $ruta = trim((string)$ruta);
                $ruta = trim($ruta, "\"' ");
                $id = (int)($ids[$i] ?? 0);
                $frecuencia = (int)($frecuencias[$i] ?? 7);
                $activa = isset($_POST['activa'][$i]);

                if ($ruta === '') {
                    continue;
                }

                if (!array_key_exists($frecuencia, $FRECUENCIAS)) {
                    $frecuencia = 7;
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE backup_personalizado_config
                        SET ruta = :ruta,
                            activa = :activa,
                            frecuencia_dias = :frecuencia_dias,
                            updated_at = NOW()
                        WHERE id = :id
                          AND agente_id = :agente_id
                    ");

                    $stmt->bindValue(':ruta', $ruta);
                    $stmt->bindValue(':activa', $activa, PDO::PARAM_BOOL);
                    $stmt->bindValue(':frecuencia_dias', $frecuencia, PDO::PARAM_INT);
                    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmt->bindValue(':agente_id', $AGENTE_ID);
                    $stmt->execute();
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO backup_personalizado_config
                            (agente_id, ruta, activa, frecuencia_dias, updated_at)
                        VALUES
                            (:agente_id, :ruta, :activa, :frecuencia_dias, NOW())
                    ");

                    $stmt->bindValue(':agente_id', $AGENTE_ID);
                    $stmt->bindValue(':ruta', $ruta);
                    $stmt->bindValue(':activa', $activa, PDO::PARAM_BOOL);
                    $stmt->bindValue(':frecuencia_dias', $frecuencia, PDO::PARAM_INT);
                    $stmt->execute();
                }
            }

            if (isset($_POST['backup_ahora'])) {
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM backup_ordenes
                    WHERE agente_id = :agente_id
                      AND estado IN ('pendiente', 'en_proceso', 'preparando', 'comprimiendo', 'cifrando', 'subiendo')
                    LIMIT 1
                ");
                $stmt->execute([':agente_id' => $AGENTE_ID]);
                $orden_activa = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$orden_activa) {
                    $stmt = $pdo->prepare("
                        INSERT INTO backup_ordenes
                            (agente_id, accion, estado, mensaje, created_at, updated_at)
                        VALUES
                            (:agente_id, 'backup_personalizado_ahora', 'pendiente', 'Backup personalizado pendiente', NOW(), NOW())
                    ");
                    $stmt->execute([':agente_id' => $AGENTE_ID]);
                }

                header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=orden');
                exit;
            }

            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=config');
            exit;
        }

        if (isset($_POST['eliminar_ruta'])) {
            $id = (int)($_POST['ruta_id'] ?? 0);

            $stmt = $pdo->prepare("
                DELETE FROM backup_personalizado_config
                WHERE id = :id
                  AND agente_id = :agente_id
            ");

            $stmt->execute([
                ':id' => $id,
                ':agente_id' => $AGENTE_ID
            ]);

            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=eliminado_ruta');
            exit;
        }

        if (isset($_POST['eliminar_backup'])) {
            if (!validar_secundaria_post($pdo)) {
                $error = 'Contraseña secundaria incorrecta o no configurada.';
            } else {
                $id = (int)$_POST['backup_id'];

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

                header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=eliminado_backup');
                exit;
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

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->prepare("
    SELECT id, ruta, activa, frecuencia_dias, ultimo_backup_ok
    FROM backup_personalizado_config
    WHERE agente_id = :agente_id
    ORDER BY id ASC
");
$stmt->execute([':agente_id' => $AGENTE_ID]);
$rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT id
    FROM backup_ordenes
    WHERE agente_id = :agente_id
      AND estado IN ('pendiente', 'en_proceso', 'preparando', 'comprimiendo', 'cifrando', 'subiendo')
    LIMIT 1
");
$stmt->execute([':agente_id' => $AGENTE_ID]);
$backup_en_proceso = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Backup personalizado - Zypher</title>
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

        input[type="text"],
        input[type="password"],
        select,
        button {
            padding: 8px 12px;
            border-radius: 8px;
            border: 0;
        }

        input[type="text"] {
            width: 95%;
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

        .ok { color: #22c55e; }
        .error { color: #ef4444; }
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

        .fila-botones {
            margin-top: 16px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .mini {
            max-width: 190px;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>Backup personalizado</h1>
    <p>Equipo: <?php echo h($AGENTE_ID); ?></p>

    <?php if ($error): ?>
        <p class="error"><?php echo h($error); ?></p>
    <?php endif; ?>

    <?php if (isset($_GET['ok'])): ?>
        <p class="ok">Acción realizada correctamente.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Configuración automática</h2>

    <form method="post" id="formBackup">
        <table id="tablaRutas">
            <thead>
                <tr>
                    <th>Ruta</th>
                    <th>Activar</th>
                    <th>Frecuencia</th>
                    <th>Última copia correcta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rutas): ?>
                <tr>
                    <td>
                        <input type="hidden" name="id[]" value="0">
                        <input type="text" name="ruta[]" placeholder="Ej: C:\Users\alex\Documents\Proyecto">
                    </td>
                    <td>
                        <input type="checkbox" name="activa[0]" checked>
                    </td>
                    <td>
                        <select name="frecuencia[]">
                            <?php foreach ($FRECUENCIAS as $dias => $label): ?>
                                <option value="<?php echo (int)$dias; ?>" <?php echo $dias === 7 ? 'selected' : ''; ?>>
                                    <?php echo h($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>-</td>
                    <td>-</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rutas as $i => $r): ?>
                    <tr>
                        <td>
                            <input type="hidden" name="id[]" value="<?php echo (int)$r['id']; ?>">
                            <input type="text" name="ruta[]" value="<?php echo h($r['ruta']); ?>">
                        </td>
                        <td>
                            <input type="checkbox" name="activa[<?php echo (int)$i; ?>]" <?php echo $r['activa'] ? 'checked' : ''; ?>>
                        </td>
                        <td>
                            <select name="frecuencia[]">
                                <?php foreach ($FRECUENCIAS as $dias => $label): ?>
                                    <option value="<?php echo (int)$dias; ?>" <?php echo ((int)$r['frecuencia_dias'] === (int)$dias) ? 'selected' : ''; ?>>
                                        <?php echo h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><?php echo h(ultima_copia_ruta($pdo, $AGENTE_ID, (string)$r['ruta'])); ?></td>
                        <td>
                            <button
                                type="submit"
                                name="eliminar_ruta"
                                class="danger"
                                onclick="document.getElementById('ruta_id').value='<?php echo (int)$r['id']; ?>'; return confirm('¿Eliminar esta ruta?');"
                            >
                                Eliminar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <input type="hidden" name="ruta_id" id="ruta_id" value="">

        <div class="fila-botones">
            <button type="button" onclick="anadirRuta()">Añadir ruta</button>
            <button type="submit" name="guardar_configuracion">Guardar configuración</button>

            <?php if ($backup_en_proceso): ?>
                <button type="button" disabled>En proceso...</button>
            <?php else: ?>
                <button type="submit" name="backup_ahora">Ejecutar copia ahora</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h2>Historial</h2>

    <form method="post" onsubmit="return confirm('¿Seguro que quieres limpiar el historial visual?');">
        <input class="mini" type="password" name="password_secundaria" placeholder="Contraseña secundaria">
        <button type="submit" name="limpiar_historial" class="danger">Limpiar historial</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Ruta / contenido</th>
                <th>Tamaño</th>
                <th>Archivo R2</th>
                <th>Mensaje</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$historial): ?>
            <tr>
                <td colspan="7" class="muted">Todavía no hay copias registradas.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?php echo h($h['created_at']); ?></td>
                    <td><?php echo h($h['estado']); ?></td>
                    <td><?php echo h($h['carpetas']); ?></td>
                    <td><?php echo h($h['tamano_mb'] ?? '-'); ?> MB</td>
                    <td><?php echo h($h['archivo_r2'] ?? '-'); ?></td>
                    <td><?php echo h($h['mensaje'] ?? '-'); ?></td>
                    <td>
                        <div class="acciones">
                            <?php if ($h['estado'] === 'completada' && !empty($h['archivo_r2'])): ?>
                                <form method="post">
                                    <input type="hidden" name="backup_id" value="<?php echo (int)$h['id']; ?>">
                                    <input class="mini" type="password" name="password_secundaria" placeholder="Contraseña secundaria" required>
                                    <button type="submit" name="descargar_backup">Descargar</button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($h['estado'], ['completada', 'error', 'cancelada'], true)): ?>
                                <form method="post" onsubmit="return confirm('¿Seguro que quieres eliminar este backup?');">
                                    <input type="hidden" name="backup_id" value="<?php echo (int)$h['id']; ?>">
                                    <input class="mini" type="password" name="password_secundaria" placeholder="Contraseña secundaria" required>
                                    <button type="submit" name="eliminar_backup" class="danger">Eliminar backup</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function anadirRuta() {
    const tbody = document.querySelector('#tablaRutas tbody');
    const index = tbody.querySelectorAll('tr').length;

    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td>
            <input type="hidden" name="id[]" value="0">
            <input type="text" name="ruta[]" placeholder="Ej: C:\\Users\\alex\\Desktop\\Proyecto">
        </td>
        <td>
            <input type="checkbox" name="activa[${index}]" checked>
        </td>
        <td>
            <select name="frecuencia[]">
                <option value="1">Cada día</option>
                <option value="7" selected>Cada semana</option>
                <option value="30">Cada mes</option>
                <option value="90">Cada 3 meses</option>
                <option value="180">Cada 6 meses</option>
                <option value="365">Cada año</option>
            </select>
        </td>
        <td>-</td>
        <td>-</td>
    `;

    tbody.appendChild(tr);
}
</script>

</body>
</html>
