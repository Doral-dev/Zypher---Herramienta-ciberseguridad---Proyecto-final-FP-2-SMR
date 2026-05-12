<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

$AGENTE_ID = 'windows-agent-001';
$mensaje = '';
$error = '';

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $codigo = $_POST['codigo'] ?? '';

        $stmtEquipo = $pdo->prepare("
            SELECT id
            FROM respuesta_equipos
            WHERE agente_id = :agente_id
              AND activo = TRUE
            LIMIT 1
        ");
        $stmtEquipo->execute([':agente_id' => $AGENTE_ID]);
        $equipo = $stmtEquipo->fetch();

        if (!$equipo) {
            throw new Exception('No existe equipo activo para este agente.');
        }

        $stmtAccion = $pdo->prepare("
            SELECT id
            FROM respuesta_acciones
            WHERE codigo = :codigo
              AND activa = TRUE
            LIMIT 1
        ");
        $stmtAccion->execute([':codigo' => $codigo]);
        $accion = $stmtAccion->fetch();

        if (!$accion) {
            throw new Exception('Acción no válida.');
        }

        $stmtInsert = $pdo->prepare("
            INSERT INTO respuesta_ordenes
            (equipo_id, accion_id, parametros, estado, creado_en)
            VALUES
            (:equipo_id, :accion_id, '{}'::jsonb, 'pendiente', CURRENT_TIMESTAMP)
        ");

        $stmtInsert->execute([
            ':equipo_id' => $equipo['id'],
            ':accion_id' => $accion['id'],
        ]);

        $mensaje = 'Acción enviada al agente correctamente.';
    }

    $acciones = $pdo->query("
        SELECT codigo, nombre, descripcion, tipo
        FROM respuesta_acciones
        WHERE activa = TRUE
        ORDER BY tipo ASC, id ASC
    ")->fetchAll();

    $ordenes = $pdo->query("
        SELECT
            ro.id,
            ra.nombre AS accion,
            ra.codigo,
            ro.estado,
            ro.resultado,
            ro.error,
            ro.creado_en,
            ro.finalizado_en
        FROM respuesta_ordenes ro
        INNER JOIN respuesta_acciones ra ON ra.id = ro.accion_id
        ORDER BY ro.id DESC
        LIMIT 10
    ")->fetchAll();

} catch (Throwable $e) {
    $error = $e->getMessage();
    $acciones = [];
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

        .card form {
            margin: 0;
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
            max-width: 520px;
        }

        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #111827;
            color: #e5e7eb;
            padding: 12px;
            border-radius: 10px;
            max-height: 300px;
            overflow: auto;
        }
    </style>
</head>
<body>
<div class="contenedor">

    <div class="cabecera">
        <h1>Respuesta ante eventos</h1>
        <p>Ejecutamos acciones de respuesta usando Velociraptor desde el agente Zypher.</p>
    </div>

    <?php if ($mensaje): ?>
        <div class="alerta-ok"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid">
        <?php foreach ($acciones as $accion): ?>
            <div class="card">
                <h3><?= htmlspecialchars($accion['nombre']) ?></h3>
                <p><?= htmlspecialchars($accion['descripcion']) ?></p>
                <form method="post">
                    <input type="hidden" name="codigo" value="<?= htmlspecialchars($accion['codigo']) ?>">
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
                <tr>
                    <td><?= htmlspecialchars((string)$orden['creado_en']) ?></td>
                    <td><?= htmlspecialchars($orden['accion']) ?></td>
                    <td>
                        <span class="estado <?= htmlspecialchars($orden['estado']) ?>">
                            <?= htmlspecialchars($orden['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($orden['estado'] === 'completada' && $orden['resultado']): ?>
                            <details>
                                <summary>Ver resultado</summary>
                                <pre><?= htmlspecialchars(json_encode(json_decode($orden['resultado'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                            </details>
                        <?php elseif ($orden['estado'] === 'error'): ?>
                            <span><?= htmlspecialchars($orden['error'] ?: 'Error desconocido') ?></span>
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
