<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

$pdo = getPDO();

$AGENTE_ID = 'windows-agent-001';

$mensaje = '';
$error = '';

function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function crearOrden(PDO $pdo, string $agenteId, string $codigoAccion, array $parametros = []): void
{
    $stmtEquipo = $pdo->prepare("
        SELECT id
        FROM respuesta_equipos
        WHERE agente_id = :agente_id
          AND activo = TRUE
        LIMIT 1
    ");
    $stmtEquipo->execute([':agente_id' => $agenteId]);
    $equipo = $stmtEquipo->fetch();

    if (!$equipo) {
        throw new RuntimeException('No existe el equipo del agente en la base de datos.');
    }

    $stmtAccion = $pdo->prepare("
        SELECT id
        FROM respuesta_acciones
        WHERE codigo = :codigo
          AND activa = TRUE
        LIMIT 1
    ");
    $stmtAccion->execute([':codigo' => $codigoAccion]);
    $accion = $stmtAccion->fetch();

    if (!$accion) {
        throw new RuntimeException('Acción no disponible.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO respuesta_ordenes
        (equipo_id, accion_id, parametros, estado)
        VALUES
        (:equipo_id, :accion_id, CAST(:parametros AS jsonb), 'pendiente')
    ");

    $stmt->execute([
        ':equipo_id' => (int)$equipo['id'],
        ':accion_id' => (int)$accion['id'],
        ':parametros' => json_encode($parametros, JSON_UNESCAPED_UNICODE),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $accion = trim($_POST['accion'] ?? '');

        if ($accion === '') {
            throw new RuntimeException('No se ha seleccionado ninguna acción.');
        }

        $parametros = [];

        if ($accion === 'bloquear_dominio') {
            $dominio = trim($_POST['dominio'] ?? '');
            if ($dominio === '') {
                throw new RuntimeException('Introduce un dominio.');
            }
            $parametros = ['dominio' => $dominio];
        }

        if ($accion === 'buscar_archivo') {
            $nombre = trim($_POST['nombre_archivo'] ?? '');
            if ($nombre === '') {
                throw new RuntimeException('Introduce el nombre del archivo.');
            }
            $parametros = [
                'ruta' => 'C:\\',
                'nombre' => $nombre
            ];
        }

        if ($accion === 'cuarentena_archivo') {
            $ruta = trim($_POST['ruta_archivo'] ?? '');
            if ($ruta === '') {
                throw new RuntimeException('Introduce la ruta del archivo.');
            }
            $parametros = ['ruta' => $ruta];
        }

        if ($accion === 'remediar_tareas') {
            $tarea = trim($_POST['nombre_tarea'] ?? '');
            if ($tarea === '') {
                throw new RuntimeException('Introduce el nombre de la tarea.');
            }
            $parametros = ['tarea' => $tarea];
        }

        crearOrden($pdo, $AGENTE_ID, $accion, $parametros);

        $mensaje = 'Acción enviada al agente correctamente.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$accionesRapidas = [
    [
        'codigo' => 'listar_procesos',
        'nombre' => 'Listar procesos',
        'desc' => 'Muestra los procesos activos del equipo.'
    ],
    [
        'codigo' => 'listar_conexiones',
        'nombre' => 'Ver conexiones',
        'desc' => 'Muestra conexiones de red activas.'
    ],
    [
        'codigo' => 'listar_servicios',
        'nombre' => 'Ver servicios',
        'desc' => 'Muestra servicios instalados.'
    ],
    [
        'codigo' => 'listar_tareas',
        'nombre' => 'Ver tareas programadas',
        'desc' => 'Muestra tareas programadas.'
    ],
    [
        'codigo' => 'listar_firewall',
        'nombre' => 'Ver firewall',
        'desc' => 'Muestra reglas del firewall.'
    ],
    [
        'codigo' => 'listar_usuarios',
        'nombre' => 'Ver usuarios',
        'desc' => 'Muestra usuarios locales.'
    ],
    [
        'codigo' => 'monitor_cuarentena',
        'nombre' => 'Ver cuarentena',
        'desc' => 'Muestra archivos en cuarentena.'
    ],
    [
        'codigo' => 'matar_psexec',
        'nombre' => 'Eliminar PsExec',
        'desc' => 'Intenta eliminar el servicio PsExec si existe.'
    ],
];

$accionesConCampo = [
    [
        'codigo' => 'bloquear_dominio',
        'nombre' => 'Bloquear dominio',
        'desc' => 'Bloquea un dominio en el archivo hosts.',
        'campo' => 'dominio',
        'placeholder' => 'ejemplo.com'
    ],
    [
        'codigo' => 'buscar_archivo',
        'nombre' => 'Buscar archivo',
        'desc' => 'Busca un archivo por nombre en el disco C.',
        'campo' => 'nombre_archivo',
        'placeholder' => 'malware.exe'
    ],
    [
        'codigo' => 'cuarentena_archivo',
        'nombre' => 'Enviar archivo a cuarentena',
        'desc' => 'Mueve un archivo sospechoso a la cuarentena del agente.',
        'campo' => 'ruta_archivo',
        'placeholder' => 'C:\\Users\\Usuario\\Desktop\\archivo.exe'
    ],
    [
        'codigo' => 'remediar_tareas',
        'nombre' => 'Desactivar tarea programada',
        'desc' => 'Desactiva una tarea programada concreta.',
        'campo' => 'nombre_tarea',
        'placeholder' => '\\NombreTarea'
    ],
];

$ultimaOrden = $pdo->query("
    SELECT 
        ro.estado,
        ro.resultado,
        ro.error,
        ro.creado_en,
        ro.finalizado_en,
        ra.nombre AS accion_nombre
    FROM respuesta_ordenes ro
    JOIN respuesta_acciones ra ON ra.id = ro.accion_id
    ORDER BY ro.id DESC
    LIMIT 1
")->fetch();

$resultadoTexto = '';

if ($ultimaOrden) {
    $resultado = $ultimaOrden['resultado'];

    if (is_string($resultado)) {
        $json = json_decode($resultado, true);
    } else {
        $json = $resultado;
    }

    if (is_array($json) && isset($json['salida'])) {
        $resultadoTexto = (string)$json['salida'];
    } elseif (is_array($json)) {
        $resultadoTexto = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } elseif ($resultado !== null) {
        $resultadoTexto = (string)$resultado;
    } else {
        $resultadoTexto = 'Todavía no hay resultado. Espera unos segundos y recarga la página.';
    }
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
            color: #111827;
        }

        h1 {
            margin-top: 0;
            margin-bottom: 8px;
        }

        .intro {
            margin-bottom: 24px;
            color: #4b5563;
        }

        .alert-ok {
            background: #dcfce7;
            color: #166534;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .section {
            background: #ffffff;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }

        .action-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
            background: #f9fafb;
        }

        .action-card h3 {
            margin: 0 0 8px 0;
            font-size: 17px;
        }

        .action-card p {
            min-height: 40px;
            margin: 0 0 14px 0;
            color: #4b5563;
            font-size: 14px;
        }

        button {
            width: 100%;
            border: none;
            background: #111827;
            color: white;
            padding: 11px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }

        button:hover {
            background: #374151;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .resultado-box {
            background: #111827;
            color: #e5e7eb;
            padding: 16px;
            border-radius: 10px;
            overflow: auto;
            max-height: 420px;
            white-space: pre-wrap;
            font-family: Consolas, monospace;
            font-size: 13px;
        }

        .estado {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 13px;
            background: #e5e7eb;
            margin-bottom: 12px;
        }

        .estado-pendiente {
            background: #fef3c7;
            color: #92400e;
        }

        .estado-en_proceso {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .estado-completada {
            background: #dcfce7;
            color: #166534;
        }

        .estado-error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>

<h1>Respuesta ante eventos</h1>
<p class="intro">Ejecutamos acciones rápidas de respuesta sobre este equipo mediante el Agente Zypher.</p>

<?php if ($mensaje): ?>
    <div class="alert-ok"><?= h($mensaje) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="section">
    <h2>Acciones rápidas</h2>

    <div class="grid">
        <?php foreach ($accionesRapidas as $accion): ?>
            <div class="action-card">
                <h3><?= h($accion['nombre']) ?></h3>
                <p><?= h($accion['desc']) ?></p>

                <form method="POST">
                    <input type="hidden" name="accion" value="<?= h($accion['codigo']) ?>">
                    <button type="submit">Ejecutar</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="section">
    <h2>Acciones con dato necesario</h2>

    <div class="grid">
        <?php foreach ($accionesConCampo as $accion): ?>
            <div class="action-card">
                <h3><?= h($accion['nombre']) ?></h3>
                <p><?= h($accion['desc']) ?></p>

                <form method="POST">
                    <input type="hidden" name="accion" value="<?= h($accion['codigo']) ?>">
                    <input
                        type="text"
                        name="<?= h($accion['campo']) ?>"
                        placeholder="<?= h($accion['placeholder']) ?>"
                        required
                    >
                    <button type="submit">Ejecutar</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($ultimaOrden): ?>
    <?php
    $estado = (string)$ultimaOrden['estado'];
    $estadoClass = 'estado-' . $estado;
    ?>
    <div class="section">
        <h2>Resultado de la última acción</h2>

        <p>
            Acción: <?= h($ultimaOrden['accion_nombre']) ?><br>
            Fecha: <?= h($ultimaOrden['creado_en']) ?>
        </p>

        <span class="estado <?= h($estadoClass) ?>"><?= h($estado) ?></span>

        <?php if (!empty($ultimaOrden['error'])): ?>
            <div class="alert-error"><?= h($ultimaOrden['error']) ?></div>
        <?php endif; ?>

        <div class="resultado-box"><?= h($resultadoTexto) ?></div>
    </div>
<?php endif; ?>

</body>
</html>
