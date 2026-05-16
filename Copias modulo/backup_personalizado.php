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

$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['crear_ruta'])) {
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $ruta = trim((string)($_POST['ruta'] ?? ''));
            $tipo = (string)($_POST['tipo'] ?? 'carpeta');
            $frecuencia = (int)($_POST['frecuencia_dias'] ?? 7);

            if ($nombre === '') {
                throw new Exception('El nombre no puede estar vacío.');
            }

            if ($ruta === '') {
                throw new Exception('La ruta no puede estar vacía.');
            }

            if (!in_array($tipo, ['archivo', 'carpeta'], true)) {
                throw new Exception('Tipo no válido.');
            }

            if (!array_key_exists($frecuencia, $FRECUENCIAS)) {
                $frecuencia = 7;
            }

            $stmt = $pdo->prepare("
                INSERT INTO backup_personalizado_config
                    (agente_id, nombre, ruta, tipo, activa, frecuencia_dias, updated_at)
                VALUES
                    (:agente_id, :nombre, :ruta, :tipo, true, :frecuencia_dias, NOW())
            ");

            $stmt->execute([
                ':agente_id' => $AGENTE_ID,
                ':nombre' => $nombre,
                ':ruta' => $ruta,
                ':tipo' => $tipo,
                ':frecuencia_dias' => $frecuencia
            ]);

            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=creado');
            exit;
        }

        if (isset($_POST['guardar_rutas'])) {
            $ids = $_POST['id'] ?? [];

            foreach ($ids as $id) {
                $id = (int)$id;
                $activa = isset($_POST['activa'][$id]);
                $frecuencia = (int)($_POST['frecuencia'][$id] ?? 7);

                if (!array_key_exists($frecuencia, $FRECUENCIAS)) {
                    $frecuencia = 7;
                }

                $stmt = $pdo->prepare("
                    UPDATE backup_personalizado_config
                    SET activa = :activa,
                        frecuencia_dias = :frecuencia_dias,
                        updated_at = NOW()
                    WHERE id = :id
                      AND agente_id = :agente_id
                ");

                $stmt->bindValue(':activa', $activa, PDO::PARAM_BOOL);
                $stmt->bindValue(':frecuencia_dias', $frecuencia, PDO::PARAM_INT);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->bindValue(':agente_id', $AGENTE_ID);
                $stmt->execute();
            }

            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=guardado');
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

        if (isset($_POST['backup_ahora'])) {
            $stmt = $pdo->prepare("
                SELECT id
                FROM backup_ordenes
                WHERE agente_id = :agente_id
                  AND estado IN ('pendiente', 'en_proceso', 'preparando', 'comprimiendo', 'cifrando', 'subiendo')
                LIMIT 1
            ");
            $stmt->execute([':agente_id' => $AGENTE_ID]);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                $stmt = $pdo->prepare("
                    INSERT INTO backup_ordenes (agente_id, accion, estado, mensaje)
                    VALUES (:agente_id, 'backup_ahora', 'pendiente', 'Backup personalizado solicitado')
                ");
                $stmt->execute([':agente_id' => $AGENTE_ID]);
            }

            header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=orden');
            exit;
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->prepare("
    SELECT id, nombre, ruta, tipo, activa, frecuencia_dias, ultimo_backup_ok, updated_at
    FROM backup_personalizado_config
    WHERE agente_id = :agente_id
    ORDER BY id DESC
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

        input, select, button {
            padding: 8px 12px;
            border-radius: 8px;
            border: 0;
        }

        input[type="text"] {
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 12px;
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

        .ok {
            color: #22c55e;
        }

        .error {
            color: #ef4444;
        }

        .muted {
            color: #9ca3af;
        }

        .acciones {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>Backup personalizado</h1>
    <p>Añade rutas concretas de archivos o carpetas para copiarlas automáticamente.</p>

    <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (isset($_GET['ok'])): ?>
        <p class="ok">Acción realizada correctamente.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Añadir ruta</h2>

    <form method="post">
        <label>Nombre visible</label>
        <input type="text" name="nombre" placeholder="Ej: Proyecto final, Fotos, Facturas..." required>

        <label>Ruta del equipo</label>
        <input type="text" name="ruta" placeholder="Ej: C:\Users\alex\Documents\Proyecto" required>

        <label>Tipo</label>
        <select name="tipo">
            <option value="carpeta">Carpeta</option>
            <option value="archivo">Archivo</option>
        </select>

        <label>Frecuencia</label>
        <select name="frecuencia_dias">
            <?php foreach ($FRECUENCIAS as $dias => $label): ?>
                <option value="<?php echo (int)$dias; ?>">
                    <?php echo htmlspecialchars($label); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" name="crear_ruta">Añadir ruta</button>
    </form>
</div>

<div class="card">
    <h2>Rutas configuradas</h2>

    <form method="post">
        <table>
            <thead>
                <tr>
                    <th>Activa</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Ruta</th>
                    <th>Frecuencia</th>
                    <th>Última copia correcta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rutas as $r): ?>
                <tr>
                    <td>
                        <input type="hidden" name="id[]" value="<?php echo (int)$r['id']; ?>">
                        <input type="checkbox" name="activa[<?php echo (int)$r['id']; ?>]" <?php echo $r['activa'] ? 'checked' : ''; ?>>
                    </td>
                    <td><?php echo htmlspecialchars((string)$r['nombre']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['tipo']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['ruta']); ?></td>
                    <td>
                        <select name="frecuencia[<?php echo (int)$r['id']; ?>]">
                            <?php foreach ($FRECUENCIAS as $dias => $label): ?>
                                <option value="<?php echo (int)$dias; ?>" <?php echo ((int)$r['frecuencia_dias'] === (int)$dias) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><?php echo htmlspecialchars((string)($r['ultimo_backup_ok'] ?? '-')); ?></td>
                    <td>
                        <button
                            type="submit"
                            name="eliminar_ruta"
                            value="1"
                            class="danger"
                            formaction=""
                            onclick="this.form.ruta_id.value='<?php echo (int)$r['id']; ?>'; return confirm('¿Eliminar esta ruta?');"
                        >
                            Eliminar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$rutas): ?>
                <tr>
                    <td colspan="7" class="muted">Todavía no hay rutas personalizadas.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <input type="hidden" name="ruta_id" value="">

        <br>

        <button type="submit" name="guardar_rutas">Guardar configuración</button>

        <?php if ($backup_en_proceso): ?>
            <button type="button" disabled>En proceso...</button>
        <?php else: ?>
            <button type="submit" name="backup_ahora">Ejecutar copia ahora</button>
        <?php endif; ?>
    </form>
</div>

</body>
</html>
