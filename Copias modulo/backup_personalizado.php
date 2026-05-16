<?php
declare(strict_types=1);

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

$AGENTE_ID = 'windows-agent-001';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['guardar_configuracion']) || isset($_POST['backup_ahora'])) {
            $ids = $_POST['id'] ?? [];
            $rutas = $_POST['ruta'] ?? [];
            $frecuencias = $_POST['frecuencia'] ?? [];

            foreach ($rutas as $i => $ruta) {
                $ruta = trim((string)$ruta);
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

            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=eliminado');
            exit;
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

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'config'): ?>
        <p class="ok">Configuración guardada correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'orden'): ?>
        <p class="ok">Orden de copia personalizada enviada correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === 'eliminado'): ?>
        <p class="ok">Ruta eliminada correctamente.</p>
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
                        <td><?php echo h($r['ultimo_backup_ok'] ?? '-'); ?></td>
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
                <th>Carpetas</th>
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
                    <td>-</td>
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
