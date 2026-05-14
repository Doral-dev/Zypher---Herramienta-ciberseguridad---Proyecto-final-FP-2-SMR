<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

$AGENTE_ID = 'windows-agent-001';
$mensaje = '';
$error = '';

$accionesDisponibles = [
    'listar_procesos' => [
        'nombre' => 'Listar procesos',
        'descripcion' => 'Ejecuta Windows.System.Pslist con Velociraptor.',
    ],
    'listar_conexiones' => [
        'nombre' => 'Listar conexiones',
        'descripcion' => 'Ejecuta Windows.Network.Netstat con Velociraptor.',
    ],
    'listar_servicios' => [
        'nombre' => 'Listar servicios',
        'descripcion' => 'Ejecuta Windows.System.Services con Velociraptor.',
    ],
    'listar_tareas' => [
        'nombre' => 'Listar tareas programadas',
        'descripcion' => 'Ejecuta Windows.System.TaskScheduler con Velociraptor.',
    ],
    'listar_usuarios' => [
        'nombre' => 'Listar usuarios',
        'descripcion' => 'Ejecuta Windows.Sys.Users con Velociraptor.',
    ],
];

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $codigo = $_POST['codigo'] ?? '';

        if (!isset($accionesDisponibles[$codigo])) {
            throw new Exception('Acción no válida.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO respuesta_ordenes
            (agente_id, codigo, parametros, estado, creado_en, actualizado_en)
            VALUES
            (:agente_id, :codigo, '{}'::jsonb, 'pendiente', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            ':agente_id' => $AGENTE_ID,
            ':codigo' => $codigo,
        ]);

        $mensaje = 'Orden creada correctamente.';
    }

    $stmtOrdenes = $pdo->prepare("
        SELECT
            id,
            agente_id,
            codigo,
            estado,
            flow_id,
            resultado,
            error,
            creado_en,
            ejecutado_en,
            actualizado_en
        FROM respuesta_ordenes
        WHERE agente_id = :agente_id
        ORDER BY id DESC
        LIMIT 20
    ");

    $stmtOrdenes->execute([':agente_id' => $AGENTE_ID]);
    $ordenes = $stmtOrdenes->fetchAll();

} catch (Throwable $e) {
    $error = $e->getMessage();
    $ordenes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Respuesta ante eventos - Zypher</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }

        .contenedor {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }

        .cabecera {
            background: #111827;
            color: white;
            padding: 24px;
            border-radius: 14px;
            margin-bottom: 24px;
        }

        .cabecera h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .cabecera p {
            margin: 0;
            color: #d1d5db;
        }

        .alerta-ok {
            background: #dcfce7;
            color: #166534;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
        }

        .alerta-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
        }

        .card h3 {
            margin: 0 0 10px;
            font-size: 18px;
        }

        .card p {
            min-height: 48px;
            font-size: 14px;
            color: #4b5563;
        }

        button {
            width: 100%;
            border: 0;
            background: #2563eb;
            color: white;
            padding: 11px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .bloque {
            background: white;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            background: #f9fafb;
            padding: 12px;
            font-size: 14px;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 14px;
        }

        .estado {
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .pendiente { background: #fef3c7; color: #92400e; }
        .en_proceso { background: #dbeafe; color: #1e40af; }
        .completada { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }

        details {
            max-width: 620px;
        }

        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #111827;
            color: #e5e7eb;
            padding: 12px;
            border-radius: 10px;
            max-height: 360px;
            overflow: auto;
        }
    </style>
</head>
<body>
<div class="contenedor">

    <div class="cabecera">
        <h1>Respuesta ante eventos</h1>
        <p>Ejecutamos acciones con Velociraptor desde el agente Windows.</p>
    </div>

    <?php if ($mensaje): ?>
        <div class="alerta-ok"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid">
        <?php foreach ($accionesDisponibles as $codigo => $accion): ?>
            <div class="card">
                <h3><?= htmlspecialchars($accion['nombre']) ?></h3>
                <p><?= htmlspecialchars($accion['descripcion']) ?></p>
                <form method="post">
                    <input type="hidden" name="codigo" value="<?= htmlspecialchars($codigo) ?>">
                    <button type="submit">Ejecutar</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="bloque">
        <h2>Últimas acciones</h2>

        <table>
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Acción</th>
                <th>Estado</th>
                <th>Resultado</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$ordenes): ?>
                <tr>
                    <td colspan="4">No hay acciones todavía.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($ordenes as $orden): ?>
                <?php
                $codigo = $orden['codigo'];
                $nombreAccion = $accionesDisponibles[$codigo]['nombre'] ?? $codigo;
                ?>
                <tr>
                    <td><?= htmlspecialchars((string)$orden['creado_en']) ?></td>
                    <td><?= htmlspecialchars($nombreAccion) ?></td>
                    <td>
                        <span class="estado <?= htmlspecialchars($orden['estado']) ?>">
                            <?= htmlspecialchars($orden['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($orden['estado'] === 'completada' && $orden['resultado']): ?>
                            <details>
                                <summary>Ver resultado</summary>
                                <pre><?= htmlspecialchars((string)$orden['resultado']) ?></pre>
                            </details>
                        <?php elseif ($orden['estado'] === 'error'): ?>
                            <pre><?= htmlspecialchars($orden['error'] ?: 'Error desconocido') ?></pre>
                        <?php else: ?>
                            <span>Esperando al agente...</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
