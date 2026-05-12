<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

$conexionCargada = false;

$posiblesConexiones = [
    __DIR__ . '/conexion.php',
    dirname(__DIR__) . '/conexion.php',
    $_SERVER['DOCUMENT_ROOT'] . '/conexion.php',
];

foreach ($posiblesConexiones as $rutaConexion) {
    if (is_file($rutaConexion)) {
        require_once $rutaConexion;
        $conexionCargada = true;
        break;
    }
}

if (!$conexionCargada && !function_exists('getPDO')) {
    function getPDO(): PDO
    {
        $host = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
        $port = '5432';
        $dbname = 'zypher_db_g2sb';
        $user = 'zypher_db_g2sb_user';
        $password = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}

const AGENTE_ID = 'windows-agent-001';

$mensaje = '';
$error = '';

$accionesRapidas = [
    'listar_procesos' => [
        'titulo' => 'Listar procesos',
        'descripcion' => 'Muestra los procesos activos del equipo.',
        'parametros' => [],
    ],
    'listar_conexiones' => [
        'titulo' => 'Ver conexiones',
        'descripcion' => 'Muestra conexiones de red activas.',
        'parametros' => [],
    ],
    'listar_servicios' => [
        'titulo' => 'Ver servicios',
        'descripcion' => 'Muestra servicios instalados.',
        'parametros' => [],
    ],
    'listar_tareas' => [
        'titulo' => 'Ver tareas programadas',
        'descripcion' => 'Muestra tareas programadas.',
        'parametros' => [],
    ],
    'listar_firewall' => [
        'titulo' => 'Ver firewall',
        'descripcion' => 'Muestra reglas del firewall.',
        'parametros' => [],
    ],
    'listar_usuarios' => [
        'titulo' => 'Ver usuarios',
        'descripcion' => 'Muestra usuarios locales.',
        'parametros' => [],
    ],
    'monitor_cuarentena' => [
        'titulo' => 'Ver cuarentena',
        'descripcion' => 'Muestra archivos en cuarentena.',
        'parametros' => [],
    ],
    'matar_psexec' => [
        'titulo' => 'Eliminar PsExec',
        'descripcion' => 'Intenta eliminar el servicio PSEXESVC si existe.',
        'parametros' => [],
    ],
];

$accionesConDato = [
    'bloquear_dominio' => [
        'titulo' => 'Bloquear dominio',
        'descripcion' => 'Añade el dominio al archivo hosts.',
        'placeholder' => 'ejemplo.com',
        'campo' => 'dominio',
    ],
    'buscar_archivo' => [
        'titulo' => 'Buscar archivo',
        'descripcion' => 'Busca un archivo por nombre en C:\\.',
        'placeholder' => 'malware.exe',
        'campo' => 'nombre',
    ],
    'cuarentena_archivo' => [
        'titulo' => 'Enviar archivo a cuarentena',
        'descripcion' => 'Mueve un archivo sospechoso a cuarentena.',
        'placeholder' => 'C:\\Users\\Usuario\\Desktop\\archivo.exe',
        'campo' => 'ruta',
    ],
    'remediar_tareas' => [
        'titulo' => 'Desactivar tarea programada',
        'descripcion' => 'Desactiva una tarea programada concreta.',
        'placeholder' => '\\NombreTarea',
        'campo' => 'tarea',
    ],
    'recoger_archivo' => [
        'titulo' => 'Analizar archivo',
        'descripcion' => 'Obtiene tamaño y hash SHA256 del archivo.',
        'placeholder' => 'C:\\Users\\Usuario\\Desktop\\archivo.exe',
        'campo' => 'ruta',
    ],
];

function h(?string $txt): string
{
    return htmlspecialchars((string)$txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function obtenerAccionNombre(PDO $pdo, string $codigo): string
{
    $stmt = $pdo->prepare("SELECT nombre FROM respuesta_acciones WHERE codigo = :codigo LIMIT 1");
    $stmt->execute(['codigo' => $codigo]);
    $row = $stmt->fetch();

    return $row['nombre'] ?? $codigo;
}

function crearOrden(PDO $pdo, string $codigo, array $parametros): void
{
    $stmt = $pdo->prepare("
        INSERT INTO respuesta_ordenes
            (agente_id, codigo, parametros, estado, creado_en)
        VALUES
            (:agente_id, :codigo, :parametros::jsonb, 'pendiente', CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        'agente_id' => AGENTE_ID,
        'codigo' => $codigo,
        'parametros' => json_encode($parametros, JSON_UNESCAPED_UNICODE),
    ]);
}

function obtenerUltimaOrden(PDO $pdo): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            ro.id,
            ro.estado,
            ro.codigo,
            ro.resultado::text AS resultado,
            ro.error,
            ro.creado_en,
            ro.finalizado_en,
            ra.nombre AS nombre_accion
        FROM respuesta_ordenes ro
        LEFT JOIN respuesta_acciones ra ON ra.codigo = ro.codigo
        WHERE ro.agente_id = :agente_id
        ORDER BY ro.id DESC
        LIMIT 1
    ");

    $stmt->execute(['agente_id' => AGENTE_ID]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function obtenerHistorial(PDO $pdo): array
{
    $stmt = $pdo->prepare("
        SELECT
            ro.id,
            ro.estado,
            ro.codigo,
            ro.error,
            ro.creado_en,
            ro.finalizado_en,
            ra.nombre AS nombre_accion
        FROM respuesta_ordenes ro
        LEFT JOIN respuesta_acciones ra ON ra.codigo = ro.codigo
        WHERE ro.agente_id = :agente_id
        ORDER BY ro.id DESC
        LIMIT 8
    ");

    $stmt->execute(['agente_id' => AGENTE_ID]);
    return $stmt->fetchAll();
}

function limpiarResultado(?string $resultado): string
{
    if (!$resultado) {
        return '';
    }

    $resultado = trim($resultado);

    $json = json_decode($resultado, true);

    if (is_array($json)) {
        if (isset($json['salida'])) {
            return (string)$json['salida'];
        }

        if (isset($json['resultado'])) {
            return is_string($json['resultado'])
                ? $json['resultado']
                : json_encode($json['resultado'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    return $resultado;
}

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $codigo = trim($_POST['codigo'] ?? '');

        $accionesPermitidas = array_merge(
            array_keys($accionesRapidas),
            array_keys($accionesConDato)
        );

        if (!in_array($codigo, $accionesPermitidas, true)) {
            throw new RuntimeException('Acción no permitida.');
        }

        $parametros = [];

        if (isset($accionesConDato[$codigo])) {
            $campo = $accionesConDato[$codigo]['campo'];
            $valor = trim($_POST[$campo] ?? '');

            if ($valor === '') {
                throw new RuntimeException('Falta el dato necesario para ejecutar la acción.');
            }

            if ($codigo === 'buscar_archivo') {
                $parametros = [
                    'ruta' => 'C:\\',
                    'nombre' => $valor,
                ];
            } else {
                $parametros = [
                    $campo => $valor,
                ];
            }
        }

        crearOrden($pdo, $codigo, $parametros);

        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?ok=1');
        exit;
    }

    if (isset($_GET['ok'])) {
        $mensaje = 'Acción enviada al agente correctamente.';
    }

    $ultimaOrden = obtenerUltimaOrden($pdo);
    $historial = obtenerHistorial($pdo);

} catch (Throwable $e) {
    $error = $e->getMessage();
    $ultimaOrden = null;
    $historial = [];
}

$resultadoLimpio = $ultimaOrden ? limpiarResultado($ultimaOrden['resultado'] ?? '') : '';
$estadoUltima = $ultimaOrden['estado'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Respuesta ante eventos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php if ($ultimaOrden && in_array($estadoUltima, ['pendiente', 'en_proceso'], true)): ?>
        <meta http-equiv="refresh" content="3">
    <?php endif; ?>

    <style>
        body {
            margin: 0;
            background: #f3f4f6;
            color: #111827;
            font-family: Arial, sans-serif;
        }

        .page {
            padding: 24px;
        }

        .header {
            margin-bottom: 22px;
        }

        .header h1 {
            margin: 0 0 6px 0;
            font-size: 30px;
        }

        .header p {
            margin: 0;
            color: #4b5563;
            font-size: 15px;
        }

        .alert-ok {
            background: #dcfce7;
            color: #166534;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .layout {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 20px;
            align-items: start;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            margin-bottom: 20px;
        }

        .card h2 {
            margin: 0 0 18px 0;
            font-size: 22px;
        }

        .grid-quick {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .grid-data {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .action-box {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 12px;
            padding: 14px;
        }

        .action-box h3 {
            margin: 0 0 8px 0;
            font-size: 16px;
        }

        .action-box p {
            margin: 0 0 14px 0;
            color: #4b5563;
            font-size: 13px;
            min-height: 34px;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            font-size: 14px;
        }

        button {
            width: 100%;
            border: 0;
            background: #111827;
            color: #ffffff;
            padding: 11px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }

        button:hover {
            background: #1f2937;
        }

        .estado {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 13px;
            margin: 8px 0 14px 0;
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

        .result-box {
            background: #020617;
            color: #e5e7eb;
            padding: 16px;
            border-radius: 12px;
            white-space: pre-wrap;
            overflow: auto;
            max-height: 520px;
            font-size: 13px;
            line-height: 1.45;
        }

        .empty {
            color: #6b7280;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            text-align: left;
            background: #e5e7eb;
            padding: 10px;
        }

        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px;
            vertical-align: top;
        }

        .small {
            color: #6b7280;
            font-size: 12px;
        }

        @media (max-width: 1200px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .grid-quick {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .grid-data {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .grid-quick {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="page">

    <div class="header">
        <h1>Respuesta ante eventos</h1>
        <p>Ejecutamos acciones rápidas de respuesta sobre este equipo mediante el Agente Zypher.</p>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert-ok"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="layout">

        <div>
            <div class="card">
                <h2>Acciones rápidas</h2>

                <div class="grid-quick">
                    <?php foreach ($accionesRapidas as $codigo => $accion): ?>
                        <div class="action-box">
                            <h3><?= h($accion['titulo']) ?></h3>
                            <p><?= h($accion['descripcion']) ?></p>

                            <form method="post">
                                <input type="hidden" name="codigo" value="<?= h($codigo) ?>">
                                <button type="submit">Ejecutar</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <h2>Acciones con dato necesario</h2>

                <div class="grid-data">
                    <?php foreach ($accionesConDato as $codigo => $accion): ?>
                        <div class="action-box">
                            <h3><?= h($accion['titulo']) ?></h3>
                            <p><?= h($accion['descripcion']) ?></p>

                            <form method="post">
                                <input type="hidden" name="codigo" value="<?= h($codigo) ?>">
                                <input
                                    type="text"
                                    name="<?= h($accion['campo']) ?>"
                                    placeholder="<?= h($accion['placeholder']) ?>"
                                    autocomplete="off"
                                >
                                <button type="submit">Ejecutar</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <h2>Resultado de la última acción</h2>

                <?php if (!$ultimaOrden): ?>
                    <p class="empty">Todavía no hay acciones ejecutadas.</p>
                <?php else: ?>
                    <div>
                        Acción: <?= h($ultimaOrden['nombre_accion'] ?: $ultimaOrden['codigo']) ?><br>
                        Creado: <?= h($ultimaOrden['creado_en']) ?><br>
                        Finalizado: <?= h($ultimaOrden['finalizado_en'] ?: '-') ?>
                    </div>

                    <div class="estado estado-<?= h($estadoUltima) ?>">
                        <?= h($estadoUltima) ?>
                    </div>

                    <?php if ($estadoUltima === 'completada' && $resultadoLimpio !== ''): ?>
                        <div class="result-box"><?= h($resultadoLimpio) ?></div>
                    <?php elseif ($estadoUltima === 'error'): ?>
                        <div class="result-box"><?= h($ultimaOrden['error'] ?: $resultadoLimpio ?: 'Error sin detalle.') ?></div>
                    <?php else: ?>
                        <div class="result-box">Todavía no hay resultado. Espera unos segundos. La página se recarga sola.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Historial reciente</h2>

                <?php if (!$historial): ?>
                    <p class="empty">Sin historial.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr>
                            <th>Acción</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($historial as $item): ?>
                            <tr>
                                <td>
                                    <?= h($item['nombre_accion'] ?: $item['codigo']) ?>
                                    <?php if (!empty($item['error'])): ?>
                                        <div class="small"><?= h($item['error']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($item['estado']) ?></td>
                                <td>
                                    <?= h($item['creado_en']) ?>
                                    <div class="small">Fin: <?= h($item['finalizado_en'] ?: '-') ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
</body>
</html>
