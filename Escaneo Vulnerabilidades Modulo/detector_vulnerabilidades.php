<?php
$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

function clasificar_grupo($severidad) {
    $sev = trim((string)$severidad);

    if ($sev === 'Crítica') {
        return 'Muy críticas';
    }

    if ($sev === 'Alta') {
        return 'Críticas';
    }

    if ($sev === 'Media') {
        return 'Normales';
    }

    return 'Leves';
}

function color_estado($estado) {
    $valor = mb_strtolower(trim((string)$estado));

    if ($valor === 'solucionado' || $valor === 'completado') {
        return '#c8f7c5';
    }

    if ($valor === 'en progreso') {
        return '#fff1b8';
    }

    return '#ffd6d6';
}

$grupoSeleccionado = isset($_GET['grupo']) ? trim($_GET['grupo']) : 'Leves';

try {
    $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmt = $pdo->query("
        SELECT
            id,
            agent_id,
            agent_name,
            cve,
            severidad,
            cvss,
            paquete,
            version_paquete,
            descripcion,
            referencia,
            remediacion,
            estado,
            fecha_deteccion,
            actualizado_en
        FROM vulns_resultados
        ORDER BY actualizado_en DESC, id DESC
    ");

    $todas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error de base de datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

$contadores = [
    'Muy críticas' => 0,
    'Críticas' => 0,
    'Normales' => 0,
    'Leves' => 0
];

foreach ($todas as $fila) {
    $grupo = clasificar_grupo($fila['severidad']);
    if (!isset($contadores[$grupo])) {
        $contadores[$grupo] = 0;
    }
    $contadores[$grupo]++;
}

if (!array_key_exists($grupoSeleccionado, $contadores)) {
    $grupoSeleccionado = 'Leves';
}

$filtradas = array_values(array_filter($todas, function ($fila) use ($grupoSeleccionado) {
    return clasificar_grupo($fila['severidad']) === $grupoSeleccionado;
}));

$chartData = [
    $contadores['Muy críticas'],
    $contadores['Críticas'],
    $contadores['Normales'],
    $contadores['Leves']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detector de vulnerabilidades</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f3f3;
            color: #111;
        }

        .contenedor {
            max-width: 1280px;
            margin: 0 auto;
            padding: 20px 20px 40px;
        }

        .titulo {
            text-align: center;
            font-size: 38px;
            margin: 10px 0 30px;
        }

        .bloque-superior {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 50px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .grafico-wrap {
            display: flex;
            align-items: center;
            gap: 35px;
        }

        #graficoPie {
            width: 220px;
            height: 220px;
            background: #fff;
            border-radius: 50%;
        }

        .leyenda {
            display: flex;
            flex-direction: column;
            gap: 14px;
            font-size: 18px;
        }

        .leyenda-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cuadro {
            width: 16px;
            height: 16px;
            border: 2px solid #000;
            display: inline-block;
        }

        .c1 { background: #111; }
        .c2 { background: #444; }
        .c3 { background: #888; }
        .c4 { background: #d9d9d9; }

        .filtro-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
            margin: 10px 0 35px;
            flex-wrap: wrap;
        }

        .filtro-wrap label {
            font-size: 22px;
        }

        .filtro-wrap select {
            min-width: 220px;
            height: 52px;
            border: 4px solid #000;
            background: #fff;
            font-size: 20px;
            text-align: center;
            padding: 0 10px;
            appearance: none;
        }

        .tabla-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th, td {
            border: 4px solid #000;
            padding: 14px 12px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #f8f8f8;
            font-size: 20px;
            font-weight: normal;
            text-align: center;
        }

        td {
            font-size: 18px;
            line-height: 1.4;
            min-height: 110px;
        }

        .col-id {
            width: 22%;
        }

        .col-desc {
            width: 46%;
        }

        .col-rem {
            width: 22%;
        }

        .col-est {
            width: 10%;
            text-align: center;
        }

        .cve {
            font-size: 22px;
            margin-bottom: 8px;
        }

        .mini {
            font-size: 15px;
            color: #333;
            word-break: break-word;
        }

        .estado-badge {
            display: inline-block;
            min-width: 120px;
            padding: 10px 12px;
            border: 3px solid #000;
            font-size: 16px;
            background: #eee;
        }

        .sin-datos {
            text-align: center;
            padding: 40px;
            font-size: 22px;
            background: #fff;
            border: 4px solid #000;
        }

        @media (max-width: 900px) {
            .titulo {
                font-size: 30px;
            }

            th, td {
                font-size: 16px;
            }

            .cve {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1 class="titulo">Detector de vulnerabilidades</h1>

        <div class="bloque-superior">
            <div class="grafico-wrap">
                <canvas id="graficoPie" width="220" height="220"></canvas>

                <div class="leyenda">
                    <div class="leyenda-item">
                        <span class="cuadro c1"></span>
                        <span>Muy críticas (<?php echo (int)$contadores['Muy críticas']; ?>)</span>
                    </div>
                    <div class="leyenda-item">
                        <span class="cuadro c2"></span>
                        <span>Críticas (<?php echo (int)$contadores['Críticas']; ?>)</span>
                    </div>
                    <div class="leyenda-item">
                        <span class="cuadro c3"></span>
                        <span>Normales (<?php echo (int)$contadores['Normales']; ?>)</span>
                    </div>
                    <div class="leyenda-item">
                        <span class="cuadro c4"></span>
                        <span>Leves (<?php echo (int)$contadores['Leves']; ?>)</span>
                    </div>
                </div>
            </div>
        </div>

        <form method="GET" class="filtro-wrap">
            <label for="grupo">Vulnerabilidades</label>
            <select name="grupo" id="grupo" onchange="this.form.submit()">
                <option value="Muy críticas" <?php echo $grupoSeleccionado === 'Muy críticas' ? 'selected' : ''; ?>>Muy críticas</option>
                <option value="Críticas" <?php echo $grupoSeleccionado === 'Críticas' ? 'selected' : ''; ?>>Críticas</option>
                <option value="Normales" <?php echo $grupoSeleccionado === 'Normales' ? 'selected' : ''; ?>>Normales</option>
                <option value="Leves" <?php echo $grupoSeleccionado === 'Leves' ? 'selected' : ''; ?>>Leves</option>
            </select>
        </form>

        <div class="tabla-wrap">
            <?php if (count($filtradas) === 0): ?>
                <div class="sin-datos">No hay vulnerabilidades en esta categoría.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th class="col-id">Identificador vulnerabilidad</th>
                            <th class="col-desc">Descripción vulnerabilidad</th>
                            <th class="col-rem">Remediación</th>
                            <th class="col-est">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtradas as $fila): ?>
                            <tr>
                                <td class="col-id">
                                    <div class="cve"><?php echo htmlspecialchars($fila['cve'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="mini">
                                        <?php echo htmlspecialchars($fila['paquete'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (!empty($fila['version_paquete'])): ?>
                                            <br>Versión: <?php echo htmlspecialchars($fila['version_paquete'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($fila['cvss'])): ?>
                                            <br>CVSS: <?php echo htmlspecialchars($fila['cvss'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="col-desc">
                                    <?php echo nl2br(htmlspecialchars($fila['descripcion'], ENT_QUOTES, 'UTF-8')); ?>
                                </td>
                                <td class="col-rem">
                                    <?php echo nl2br(htmlspecialchars($fila['remediacion'], ENT_QUOTES, 'UTF-8')); ?>
                                </td>
                                <td class="col-est">
                                    <span class="estado-badge" style="background: <?php echo htmlspecialchars(color_estado($fila['estado']), ENT_QUOTES, 'UTF-8'); ?>;">
                                        <?php echo htmlspecialchars($fila['estado'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const datos = <?php echo json_encode($chartData, JSON_UNESCAPED_UNICODE); ?>;
        const colores = ['#111111', '#444444', '#888888', '#d9d9d9'];

        const canvas = document.getElementById('graficoPie');
        const ctx = canvas.getContext('2d');

        const total = datos.reduce((a, b) => a + b, 0);
        const centroX = canvas.width / 2;
        const centroY = canvas.height / 2;
        const radio = 95;

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (total > 0) {
            let anguloInicial = -Math.PI / 2;

            for (let i = 0; i < datos.length; i++) {
                const valor = datos[i];
                const angulo = (valor / total) * Math.PI * 2;

                ctx.beginPath();
                ctx.moveTo(centroX, centroY);
                ctx.arc(centroX, centroY, radio, anguloInicial, anguloInicial + angulo);
                ctx.closePath();
                ctx.fillStyle = colores[i];
                ctx.fill();
                ctx.lineWidth = 4;
                ctx.strokeStyle = '#000';
                ctx.stroke();

                anguloInicial += angulo;
            }

            ctx.beginPath();
            ctx.arc(centroX, centroY, radio, 0, Math.PI * 2);
            ctx.lineWidth = 4;
            ctx.strokeStyle = '#000';
            ctx.stroke();
        } else {
            ctx.beginPath();
            ctx.arc(centroX, centroY, radio, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.lineWidth = 4;
            ctx.strokeStyle = '#000';
            ctx.stroke();
        }
    </script>
</body>
</html>
