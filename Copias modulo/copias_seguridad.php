<?php
declare(strict_types=1);

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

$AGENTE_ID = 'windows-agent-001';

$CARPETAS = [
    'desktop' => 'Escritorio',
    'documents' => 'Documentos',
    'downloads' => 'Descargas',
    'pictures' => 'Imágenes',
    'videos' => 'Vídeos',
    'music' => 'Música'
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

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_config'])) {
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

        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=config');
        exit;
    }

    if (isset($_POST['backup_ahora'])) {
        $stmt = $pdo->prepare("
            INSERT INTO backup_ordenes (agente_id, accion, estado)
            VALUES (:agente_id, 'backup_ahora', 'pendiente')
        ");
        $stmt->execute([':agente_id' => $AGENTE_ID]);

        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=orden');
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
    SELECT estado, carpetas, archivo_r2, tamano_mb, mensaje, created_at
    FROM backup_historial
    WHERE agente_id = :agente_id
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

        select, button {
            padding: 8px 12px;
            border-radius: 8px;
            border: 0;
        }

        button {
            background: #2563eb;
            color: white;
            cursor: pointer;
            margin-right: 10px;
        }

        button:hover {
            background: #1d4ed8;
        }

        .ok {
            color: #22c55e;
        }

        .error {
            color: #ef4444;
        }

        .pendiente {
            color: #facc15;
        }

        .muted {
            color: #9ca3af;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>Copias de seguridad</h1>
    <p>Equipo: <?php echo htmlspecialchars($AGENTE_ID); ?></p>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'config'): ?>
        <p class="ok">Configuración guardada correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'orden'): ?>
        <p class="ok">Orden de copia creada correctamente.</p>
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
        <button type="submit" name="backup_ahora">Ejecutar copia ahora</button>
    </form>
</div>

<div class="card">
    <h2>Historial</h2>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Carpetas</th>
                <th>Tamaño</th>
                <th>Archivo R2</th>
                <th>Mensaje</th>
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
            </tr>
        <?php endforeach; ?>

        <?php if (!$historial): ?>
            <tr>
                <td colspan="6" class="muted">Todavía no hay copias registradas.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
