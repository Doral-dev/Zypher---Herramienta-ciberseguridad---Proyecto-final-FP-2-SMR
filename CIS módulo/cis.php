<?php
date_default_timezone_set('Europe/Madrid');

$host = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$port = '5432';
$dbname = 'zypher_db_g2sb';
$user = 'zypher_db_g2sb_user';
$password = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $sql = "SELECT id_cis, titulo, descripcion, comando_remediacion, estado, fecha_ultimo_analisis
            FROM cis_policies
            ORDER BY id_cis ASC";
    $stmt = $pdo->query($sql);
    $policies = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error de conexión o consulta: " . $e->getMessage());
}

function mostrarEstado(string $estado): string
{
    $estado = strtolower(trim($estado));

    if ($estado === 'cumple' || $estado === 'completado') {
        return '✅';
    }

    if ($estado === 'no cumple' || $estado === 'no completado') {
        return '❌';
    }

    return '⏳';
}

function formatearFecha(?string $fecha): string
{
    if (empty($fecha)) {
        return '-';
    }

    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return $fecha;
    }

    return date('d/m/Y H:i:s', $timestamp);
}

function calcularResumen(array $policies): array
{
    $total = count($policies);
    $completadas = 0;
    $noCompletadas = 0;

    foreach ($policies as $policy) {
        $estado = strtolower(trim($policy['estado'] ?? ''));

        if ($estado === 'cumple' || $estado === 'completado') {
            $completadas++;
        } elseif ($estado === 'no cumple' || $estado === 'no completado') {
            $noCompletadas++;
        }
    }

    $analizadas = $completadas + $noCompletadas;

    if ($analizadas > 0) {
        $porcentajeCompletadas = round(($completadas / $analizadas) * 100, 1);
        $porcentajeNoCompletadas = round(($noCompletadas / $analizadas) * 100, 1);
    } else {
        $porcentajeCompletadas = 0;
        $porcentajeNoCompletadas = 0;
    }

    return [
        'total' => $total,
        'completadas' => $completadas,
        'no_completadas' => $noCompletadas,
        'porcentaje_completadas' => $porcentajeCompletadas,
        'porcentaje_no_completadas' => $porcentajeNoCompletadas
    ];
}

function renderizarResumen(array $resumen): void
{
    $verde = $resumen['porcentaje_completadas'];
    $rojo = $resumen['porcentaje_no_completadas'];
    $gradosVerde = ($verde + $rojo) > 0 ? ($verde / 100) * 360 : 0;
    ?>
    <div class="cis-resumen">
        <div class="cis-grafica-box">
            <div
                class="cis-grafica"
                style="background: conic-gradient(#22c55e 0deg <?php echo $gradosVerde; ?>deg, #ef4444 <?php echo $gradosVerde; ?>deg 360deg);"
            ></div>

            <div class="cis-leyenda">
                <div class="leyenda-item">
                    <span class="leyenda-color verde"></span>
                    <span>Políticas aplicadas: <?php echo $resumen['completadas']; ?> (<?php echo $resumen['porcentaje_completadas']; ?>%)</span>
                </div>

                <div class="leyenda-item">
                    <span class="leyenda-color rojo"></span>
                    <span>Políticas NO aplicadas: <?php echo $resumen['no_completadas']; ?> (<?php echo $resumen['porcentaje_no_completadas']; ?>%)</span>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function renderizarTabla(array $policies): void
{
    ?>
    <div class="table-box">
        <table>
            <thead>
                <tr>
                    <th class="col-id">ID Benchmark</th>
                    <th class="col-titulo">Nombre política</th>
                    <th class="col-descripcion">Descripción política</th>
                    <th class="col-remediacion">Comando remediación</th>
                    <th class="col-estado">Estado</th>
                    <th class="col-fecha">Último análisis</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($policies as $policy): ?>
                    <tr>
                        <td class="col-id"><?php echo htmlspecialchars($policy['id_cis']); ?></td>
                        <td class="col-titulo"><?php echo htmlspecialchars($policy['titulo']); ?></td>
                        <td class="col-descripcion"><?php echo htmlspecialchars($policy['descripcion']); ?></td>
                        <td class="col-remediacion"><pre><?php echo htmlspecialchars($policy['comando_remediacion']); ?></pre></td>
                        <td class="col-estado"><?php echo mostrarEstado($policy['estado']); ?></td>
                        <td class="col-fecha"><?php echo htmlspecialchars(formatearFecha($policy['fecha_ultimo_analisis'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function renderizarContenido(array $policies): void
{
    $resumen = calcularResumen($policies);
    ?>
    <div id="contenido-cis">
        <?php renderizarResumen($resumen); ?>

        <div class="top-actions">
            <button class="btn-reset" id="btn-reanalizar" type="button">🔄</button>
        </div>

        <?php renderizarTabla($policies); ?>
    </div>
    <?php
}

if (isset($_GET['contenido']) && $_GET['contenido'] === '1') {
    renderizarContenido($policies);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Benchmark - Zypher</title>
    <link rel="stylesheet" href="cis.css">
</head>
<body>
    <div class="wrap">
        <h1>CIS Benchmark Políticas de cumplimiento</h1>
        <?php renderizarContenido($policies); ?>
    </div>

    <script>
        function bindRefreshButton() {
            const btn = document.getElementById('btn-reanalizar');
            if (!btn) return;

            btn.onclick = async function () {
                btn.disabled = true;
                btn.textContent = '⏳';

                try {
                    const contenido = await fetch('cis.php?contenido=1');
                    const htmlContenido = await contenido.text();
                    document.getElementById('contenido-cis').outerHTML = htmlContenido;
                    bindRefreshButton();
                } catch (error) {
                    console.error(error);
                } finally {
                    const nuevoBtn = document.getElementById('btn-reanalizar');
                    if (nuevoBtn) {
                        nuevoBtn.disabled = false;
                        nuevoBtn.textContent = '🔄';
                    }
                }
            };
        }

        bindRefreshButton();
    </script>
</body>
</html>
