<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

$AGENTE_ID = 'windows-agent-001';
$mensaje = '';
$error = '';

if (isset($_GET['ok'])) {
    $mensaje = 'Orden creada correctamente.';
}

$artefactosTexto = <<<'TXT'
Windows.Analysis.EvidenceOfDownload
Windows.Applications.ChocolateyPackages
Windows.Applications.Chrome.Extensions
Windows.Applications.Chrome.History
Windows.Applications.Edge.History
Windows.Applications.Firefox.Downloads
Windows.Applications.Firefox.History
Windows.Applications.IISLogs
Windows.Applications.TeamViewer.Incoming
Windows.Attack.ParentProcess
Windows.Attack.Prefetch
Windows.Attack.UnexpectedImagePath
Windows.Detection.Amcache
Windows.Detection.BinaryRename
Windows.Detection.EnvironmentVariables
Windows.Detection.Impersonation
Windows.Detection.ProcessCreation
Windows.Detection.PsexecService
Windows.Detection.Registry
Windows.Detection.Thumbdrives.List
Windows.Detection.Usn
Windows.Detection.WMIProcessCreation
Windows.EventLogs.AlternateLogon
Windows.EventLogs.Cleared
Windows.EventLogs.DHCP
Windows.EventLogs.Evtx
Windows.EventLogs.ExplicitLogon
Windows.EventLogs.Modifications
Windows.EventLogs.PowershellModule
Windows.EventLogs.PowershellScriptblock
Windows.EventLogs.RDPAuth
Windows.EventLogs.ScheduledTasks
Windows.EventLogs.ServiceCreationComspec
Windows.Forensics.Amcache
Windows.Forensics.Bam
Windows.Forensics.CertUtil
Windows.Forensics.FilenameSearch
Windows.Forensics.JumpLists
Windows.Forensics.Lnk
Windows.Forensics.Prefetch
Windows.Forensics.RDPCache
Windows.Forensics.RecentApps
Windows.Forensics.RecycleBin
Windows.Forensics.Shellbags
Windows.Forensics.UserAccessLogs
Windows.Network.ArpCache
Windows.Network.InterfaceAddresses
Windows.Network.ListeningPorts
Windows.Network.Netstat
Windows.Network.NetstatEnriched
Windows.Persistence.PermanentWMIEvents
Windows.Persistence.PowershellProfile
Windows.Persistence.PowershellRegistry
Windows.Registry.AppCompatCache
Windows.Registry.MountPoints2
Windows.Registry.PortProxy
Windows.Registry.PuttyHostKeys
Windows.Registry.RDP
Windows.Registry.RecentDocs
Windows.Registry.UserAssist
Windows.Registry.WDigest
Windows.Search.FileFinder
Windows.Sys.AllUsers
Windows.Sys.AppcompatShims
Windows.Sys.CertificateAuthorities
Windows.Sys.DiskInfo
Windows.Sys.Drivers
Windows.Sys.FirewallRules
Windows.Sys.Interfaces
Windows.Sys.Programs
Windows.Sys.StartupItems
Windows.Sys.Users
Windows.System.AuditPolicy
Windows.System.CriticalServices
Windows.System.DLLs
Windows.System.DNSCache
Windows.System.DomainRole
Windows.System.Handles
Windows.System.HostsFile
Windows.System.LocalAdmins
Windows.System.Powershell.ModuleAnalysisCache
Windows.System.Powershell.PSReadline
Windows.System.Pslist
Windows.System.RootCAStore
Windows.System.SVCHost
Windows.System.Services
Windows.System.Shares
Windows.System.Signers
Windows.System.TaskScheduler
Windows.System.Threads
Windows.System.UntrustedBinaries
Windows.System.WMIQuery
Windows.Timeline.Prefetch
Windows.Timeline.Registry.RunMRU
TXT;

function obtenerCategoria(string $artefacto): string
{
    if (str_contains($artefacto, '.Network.')) return 'Red';
    if (str_contains($artefacto, '.EventLogs.') || str_contains($artefacto, '.Events.')) return 'Eventos Windows';
    if (str_contains($artefacto, '.Persistence.')) return 'Persistencia';
    if (str_contains($artefacto, '.Forensics.') || str_contains($artefacto, '.Timeline.')) return 'Forense básico';
    if (str_contains($artefacto, '.Detection.') || str_contains($artefacto, '.Attack.') || str_contains($artefacto, '.Analysis.')) return 'Detección';
    if (str_contains($artefacto, '.Applications.')) return 'Aplicaciones';
    if (str_contains($artefacto, '.Registry.')) return 'Registro';
    if (str_contains($artefacto, '.Search.')) return 'Búsqueda';
    if (str_contains($artefacto, '.Sys.') || str_contains($artefacto, '.System.')) return 'Sistema';

    return 'Otros';
}

function nombreBonito(string $artefacto): string
{
    $partes = explode('.', $artefacto);
    $nombre = end($partes) ?: $artefacto;

    $nombre = preg_replace('/(?<!^)([A-Z])/', ' $1', $nombre);
    $nombre = str_replace(
        ['R D P', 'D N S', 'W M I', 'D L Ls', 'P S Readline', 'S V C Host'],
        ['RDP', 'DNS', 'WMI', 'DLLs', 'PSReadline', 'SVCHost'],
        $nombre
    );

    return trim((string)$nombre);
}

function descripcionArtefacto(string $artefacto): string
{
    $categoria = obtenerCategoria($artefacto);

    return match ($categoria) {
        'Sistema' => 'Recoge información del sistema usando Velociraptor.',
        'Red' => 'Analiza información de red del equipo.',
        'Eventos Windows' => 'Consulta eventos relevantes de Windows.',
        'Persistencia' => 'Busca posibles mecanismos de persistencia.',
        'Forense básico' => 'Recoge evidencias forenses básicas.',
        'Detección' => 'Analiza indicadores o comportamientos sospechosos.',
        'Aplicaciones' => 'Revisa datos de aplicaciones instaladas o usadas.',
        'Registro' => 'Consulta claves del registro relevantes.',
        'Búsqueda' => 'Busca archivos o evidencias en el equipo.',
        default => 'Ejecuta análisis seguro con Velociraptor.',
    };
}

function construirAcciones(string $texto): array
{
    $lineas = preg_split('/\R/', trim($texto));
    $acciones = [];

    foreach ($lineas as $linea) {
        $artefacto = trim($linea);

        if ($artefacto === '') {
            continue;
        }

        $acciones[] = [
            'codigo' => $artefacto,
            'artefacto' => $artefacto,
            'nombre' => nombreBonito($artefacto),
            'categoria' => obtenerCategoria($artefacto),
            'descripcion' => descripcionArtefacto($artefacto),
        ];
    }

    usort($acciones, function ($a, $b) {
        return [$a['categoria'], $a['nombre']] <=> [$b['categoria'], $b['nombre']];
    });

    return $acciones;
}

function pintarResultado(?string $resultado): string
{
    if (!$resultado) {
        return '<span>Sin resultado.</span>';
    }

    $json = json_decode($resultado, true);

    if (!is_array($json)) {
        return '<pre>' . htmlspecialchars($resultado) . '</pre>';
    }

    if (!$json) {
        return '<span>Velociraptor ejecutó el análisis, pero no devolvió filas.</span>';
    }

    $primerasFilas = array_slice($json, 0, 50);

    $columnasPreferidas = [
        'Name',
        'Pid',
        'Ppid',
        'Username',
        'Exe',
        'CommandLine',
        'LocalAddr',
        'LocalPort',
        'RemoteAddr',
        'RemotePort',
        'State',
        'ServiceName',
        'DisplayName',
        'StartMode',
        'PathName',
        'FileName',
        'FullPath',
        'Key',
        'Value',
        'Timestamp',
        'EventID',
        'Message'
    ];

    $columnas = [];

    foreach ($columnasPreferidas as $columna) {
        foreach ($primerasFilas as $fila) {
            if (is_array($fila) && array_key_exists($columna, $fila)) {
                $columnas[] = $columna;
                break;
            }
        }
    }

    if (!$columnas) {
        $columnas = array_keys($primerasFilas[0] ?? []);
        $columnas = array_slice($columnas, 0, 8);
    }

    if (!$columnas) {
        return '<pre>' . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
    }

    $html = '<div class="resultado-info">Mostrando ' . count($primerasFilas) . ' filas. Los detalles completos quedan ocultos debajo.</div>';
    $html .= '<div class="tabla-scroll"><table class="tabla-resultado"><thead><tr>';

    foreach ($columnas as $columna) {
        $html .= '<th>' . htmlspecialchars($columna) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    foreach ($primerasFilas as $fila) {
        $html .= '<tr>';

        foreach ($columnas as $columna) {
            $valor = $fila[$columna] ?? '';

            if (is_array($valor)) {
                $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
            }

            $html .= '<td>' . htmlspecialchars((string)$valor) . '</td>';
        }

        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';
    $html .= '<details class="detalle-json"><summary>Ver detalles</summary><pre>' . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></details>';

    return $html;
}

function pintarUltimasAcciones(array $ordenes): void
{
    ?>
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
                <td>
                    <div><?= htmlspecialchars(nombreBonito((string)$orden['codigo'])) ?></div>
                    <div class="artefacto"><?= htmlspecialchars((string)$orden['codigo']) ?></div>
                </td>
                <td>
                    <span class="estado <?= htmlspecialchars((string)$orden['estado']) ?>">
                        <?= htmlspecialchars((string)$orden['estado']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($orden['estado'] === 'completada' && $orden['resultado']): ?>
                        <details>
                            <summary>Ver resultado</summary>
                            <?= pintarResultado((string)$orden['resultado']) ?>
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
    <?php
}

$acciones = construirAcciones($artefactosTexto);
$ordenes = [];

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $codigo = $_POST['codigo'] ?? '';
        $codigosValidos = array_column($acciones, 'codigo');

        if (!in_array($codigo, $codigosValidos, true)) {
            throw new Exception('Acción no válida.');
        }

        $stmtExiste = $pdo->prepare("
            SELECT id
            FROM respuesta_ordenes
            WHERE agente_id = :agente_id
              AND codigo = :codigo
              AND estado IN ('pendiente', 'en_proceso')
            LIMIT 1
        ");

        $stmtExiste->execute([
            ':agente_id' => $AGENTE_ID,
            ':codigo' => $codigo,
        ]);

        $ordenExistente = $stmtExiste->fetch();

        if (!$ordenExistente) {
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
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?ok=1');
        exit;
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

if (isset($_GET['ajax']) && $_GET['ajax'] === 'ultimas') {
    pintarUltimasAcciones($ordenes);
    exit;
}

$accionesPorCategoria = [];

foreach ($acciones as $accion) {
    $accionesPorCategoria[$accion['categoria']][] = $accion;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Análisis y supervisión - Zypher</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }

        .contenedor {
            max-width: 1300px;
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

        details.categoria {
            background: white;
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 14px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 14px rgba(0,0,0,0.05);
        }

        details.categoria summary {
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
        }

        .acciones-lista {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 12px;
        }

        .accion {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 18px;
            background: #f9fafb;
            min-height: 160px;
        }

        .accion h3 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .accion p {
            margin: 0 0 10px;
            font-size: 14px;
            color: #4b5563;
            min-height: 36px;
        }

        .artefacto {
            font-family: Consolas, monospace;
            font-size: 12px;
            background: #e5e7eb;
            padding: 5px 7px;
            border-radius: 7px;
            display: inline-block;
            margin-bottom: 10px;
            word-break: break-all;
        }

        button {
            width: 100%;
            border: 0;
            background: #2563eb;
            color: white;
            padding: 13px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }

        button:hover {
            background: #1d4ed8;
        }

        button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .bloque {
            background: white;
            border-radius: 14px;
            padding: 18px;
            margin-top: 28px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
        }

        .bloque h2 {
            margin-top: 0;
        }

        .actualizando {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 10px;
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

        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #111827;
            color: #e5e7eb;
            padding: 12px;
            border-radius: 10px;
            max-height: 420px;
            overflow: auto;
        }

        .tabla-scroll {
            max-width: 100%;
            overflow: auto;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-top: 10px;
        }

        .tabla-resultado th,
        .tabla-resultado td {
            font-size: 12px;
            white-space: nowrap;
        }

        .resultado-info {
            font-size: 13px;
            color: #4b5563;
            margin: 8px 0;
        }

        .detalle-json {
            margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="contenedor">

    <div class="cabecera">
        <h1>Análisis y supervisión</h1>
        <p>Ejecutamos análisis seguros sobre equipos Windows usando Velociraptor.</p>
    </div>

    <?php if ($mensaje): ?>
        <div class="alerta-ok"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php foreach ($accionesPorCategoria as $categoria => $lista): ?>
        <details class="categoria">
            <summary><?= htmlspecialchars($categoria) ?> (<?= count($lista) ?>)</summary>

            <div class="acciones-lista">
                <?php foreach ($lista as $accion): ?>
                    <div class="accion">
                        <h3><?= htmlspecialchars($accion['nombre']) ?></h3>
                        <p><?= htmlspecialchars($accion['descripcion']) ?></p>
                        <span class="artefacto"><?= htmlspecialchars($accion['artefacto']) ?></span>

                        <form method="post">
                            <input type="hidden" name="codigo" value="<?= htmlspecialchars($accion['codigo']) ?>">
                            <button type="submit">Ejecutar</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endforeach; ?>

    <div class="bloque">
        <div class="actualizando">Últimas acciones se actualiza automáticamente cada 5 segundos.</div>
        <div id="ultimasAcciones">
            <?php pintarUltimasAcciones($ordenes); ?>
        </div>
    </div>

</div>

<script>
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const boton = form.querySelector('button');

            if (boton) {
                boton.disabled = true;
                boton.innerText = 'Enviado...';
            }
        });
    });

    function actualizarUltimasAcciones() {
        fetch(window.location.pathname + '?ajax=ultimas', {
            cache: 'no-store'
        })
            .then(function (respuesta) {
                return respuesta.text();
            })
            .then(function (html) {
                const bloque = document.getElementById('ultimasAcciones');

                if (bloque) {
                    bloque.innerHTML = html;
                }
            })
            .catch(function () {
                console.log('No se pudo actualizar Últimas acciones');
            });
    }

    setInterval(actualizarUltimasAcciones, 5000);
</script>
</body>
</html>
