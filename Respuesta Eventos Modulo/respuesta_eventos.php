<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

$pdo = getPDO();

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $equipoId = (int)($_POST['equipo_id'] ?? 0);
        $accionId = (int)($_POST['accion_id'] ?? 0);
        $parametrosTexto = trim($_POST['parametros'] ?? '');

        if ($equipoId <= 0 || $accionId <= 0) {
            throw new RuntimeException('Faltan equipo o acción.');
        }

        $parametros = [];

        if ($parametrosTexto !== '') {
            $parametros = json_decode($parametrosTexto, true);
            if (!is_array($parametros)) {
                throw new RuntimeException('Los parámetros deben ser JSON válido.');
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO respuesta_ordenes
            (equipo_id, accion_id, parametros, estado)
            VALUES
            (:equipo_id, :accion_id, :parametros::jsonb, 'pendiente')
        ");

        $stmt->execute([
            ':equipo_id' => $equipoId,
            ':accion_id' => $accionId,
            ':parametros' => json_encode($parametros, JSON_UNESCAPED_UNICODE),
        ]);

        $mensaje = 'Orden creada correctamente.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$equipos = $pdo->query("
    SELECT id, hostname, sistema, velociraptor_client_id, activo
    FROM respuesta_equipos
    WHERE activo = TRUE
    ORDER BY hostname
")->fetchAll();

$acciones = $pdo->query("
    SELECT id, codigo, nombre, artefacto_velociraptor, descripcion, tipo, requiere_parametros
    FROM respuesta_acciones
    WHERE activa = TRUE
    ORDER BY tipo DESC, nombre
")->fetchAll();

$ordenes = $pdo->query("
    SELECT 
        ro.id,
        ro.estado,
        ro.parametros,
        ro.flow_id,
        ro.error,
        ro.creado_en,
        ro.ejecutado_en,
        ro.finalizado_en,
        re.hostname,
        ra.nombre AS accion_nombre,
        ra.codigo AS accion_codigo
    FROM respuesta_ordenes ro
    JOIN respuesta_equipos re ON re.id = ro.equipo_id
    JOIN respuesta_acciones ra ON ra.id = ro.accion_id
    ORDER BY ro.id DESC
    LIMIT 50
")->fetchAll();

function h(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Zypher - Respuesta ante eventos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 24px;
            color: #1f2937;
        }

        h1 {
            margin-top: 0;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
        }

        select, textarea, button {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
        }

        textarea {
            min-height: 90px;
            font-family: monospace;
        }

        button {
            background: #111827;
            color: white;
            cursor: pointer;
            border: none;
            margin-top: 16px;
        }

        button:hover {
            background: #374151;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #e5e7eb;
        }

        .ok {
            background: #dcfce7;
            color: #166534;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 16px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 16px;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #e5e7eb;
        }

        .pendiente { background: #fef3c7; }
        .en_proceso { background: #dbeafe; }
        .completada { background: #dcfce7; }
        .error_estado { background: #fee2e2; }
    </style>
</head>
<body>

<h1>Respuesta ante eventos</h1>

<p>Módulo para lanzar acciones de respuesta sobre equipos gestionados por Velociraptor.</p>

<?php if ($mensaje): ?>
    <div class="ok"><?= h($mensaje) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= h($error) ?></div>
<?php endif; ?>

<div class="grid">
    <div class="card">
        <h2>Lanzar acción</h2>

        <form method="POST">
            <label>Equipo</label>
            <select name="equipo_id" required>
                <option value="">Seleccionar equipo</option>
                <?php foreach ($equipos as $equipo): ?>
                    <option value="<?= (int)$equipo['id'] ?>">
                        <?= h($equipo['hostname']) ?> - <?= h($equipo['velociraptor_client_id']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Acción</label>
            <select name="accion_id" required>
                <option value="">Seleccionar acción</option>
                <?php foreach ($acciones as $accion): ?>
                    <option value="<?= (int)$accion['id'] ?>">
                        [<?= h($accion['tipo']) ?>] <?= h($accion['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Parámetros JSON</label>
            <textarea name="parametros" placeholder='Ejemplo: {"comando":"whoami"}'></textarea>

            <button type="submit">Crear orden</button>
        </form>
    </div>

    <div class="card">
        <h2>Equipos disponibles</h2>

        <table>
            <thead>
            <tr>
                <th>Equipo</th>
                <th>Sistema</th>
                <th>Client ID</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($equipos as $equipo): ?>
                <tr>
                    <td><?= h($equipo['hostname']) ?></td>
                    <td><?= h($equipo['sistema']) ?></td>
                    <td><?= h($equipo['velociraptor_client_id']) ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$equipos): ?>
                <tr>
                    <td colspan="3">No hay equipos sincronizados.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Últimas órdenes</h2>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Equipo</th>
            <th>Acción</th>
            <th>Estado</th>
            <th>Parámetros</th>
            <th>Flow ID</th>
            <th>Error</th>
            <th>Creado</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($ordenes as $orden): ?>
            <?php
            $estado = (string)$orden['estado'];
            $class = match ($estado) {
                'pendiente' => 'pendiente',
                'en_proceso' => 'en_proceso',
                'completada' => 'completada',
                'error' => 'error_estado',
                default => ''
            };
            ?>
            <tr>
                <td><?= (int)$orden['id'] ?></td>
                <td><?= h($orden['hostname']) ?></td>
                <td><?= h($orden['accion_nombre']) ?></td>
                <td><span class="badge <?= h($class) ?>"><?= h($estado) ?></span></td>
                <td><code><?= h(json_encode($orden['parametros'], JSON_UNESCAPED_UNICODE)) ?></code></td>
                <td><?= h($orden['flow_id']) ?></td>
                <td><?= h($orden['error']) ?></td>
                <td><?= h($orden['creado_en']) ?></td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$ordenes): ?>
            <tr>
                <td colspan="8">Todavía no hay órdenes.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
