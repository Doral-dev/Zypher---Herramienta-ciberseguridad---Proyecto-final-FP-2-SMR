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

$grupoSeleccionado = isset($_GET['grupo']) ? trim($_GET['grupo']) : 'Leves';

try {
    $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmt = $pdo->query("
        SELECT
            id,
            cve,
            severidad,
            cvss,
            paquete,
            descripcion,
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
            background: #f2f2f2;
            color: #111;
        }

        .contenedor {
            width: 94%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 10px 0 40px;
        }

        .titulo {
            text-align: center;
            font-size: 34px;
            margin: 0 0 10px;
            font-weight: normal;
        }

        .bloque-superior {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 40px;
            margin: 10px 0 25px;
            flex-wrap: wrap;
        }

        #graficoPie {
            width: 250px;
            height: 250px;
        }

        .leyenda {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            gap: 24px;
            font-size: 18px;
        }

        .leyenda-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cuadro {
            width: 16px;
            height: 16px;
            border: 2px solid #000;
            display: inline-block;
        }

        .c1 { background: #111111; }
        .c2 { background: #444444; }
        .c3 { background: #888888; }
        .c4 { background: #d9d9d9; }

        .filtro-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 18px;
            margin: 18px 0 35px;
            flex-wrap: wrap;
        }

        .filtro-wrap label {
            font-size: 18px;
        }

        .filtro-wrap select {
            width: 210px;
            height: 52px;
            border: 6px solid #000;
            background: #fff;
            font-size: 18px;
            text-align: center;
            appearance: none;
            padding: 0 10px;
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
            border: 8px solid #000;
            padding: 14px 12px;
            vertical-align: top;
        }

        th {
            font-size: 20px;
            text-align: center;
            font-weight: normal;
            background: #f8f8f8;
        }

        td {
            font-size: 17px;
            line-height: 1.45;
            height: 118px;
        }

        .col-cve {
            width: 19%;
            text-align: center;
        }

        .col-desc {
            width: 33%;
        }

        .col-sev {
            width: 17%;
            text-align: center;
        }

        .col-paq {
            width: 16%;
            text-align: center;
        }

        .col-cvss {
            width: 15%;
            text-align: center;
        }

        .sin-datos {
            text-align: center;
            font-size: 22px;
            background: #fff;
            border: 8px solid #000;
            padding: 40px 20px;
        }

        @media (max-width: 900px) {
            .titulo {
                font-size: 28px;
            }

            th {
                font-size: 16px;
            }

            td {
                font-size: 15px;
            }

            th, td {
                border-width: 5px;
            }

            .filtro-wrap select {
                border-width: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1 class="titulo">Detector de vulnerabilidades</h1>

        <div class="bloque-superior">
            <canvas id="graficoPie" width="250" height="250"></canvas>

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
                            <th class="col-cve">CVE</th>
                            <th class="col-desc">Descripción</th>
                            <th class="col-sev">Severidad</th>
                            <th class="col-paq">Paquete</th>
                            <th class="col-cvss">CVSS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtradas as $fila): ?>
                            <tr>
                                <td class="col-cve"><?php echo htmlspecialchars($fila['cve'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="col-desc"><?php echo nl2br(htmlspecialchars($fila['descripcion'], ENT_QUOTES, 'UTF-8')); ?></td>
                                <td class="col-sev"><?php echo htmlspecialchars($fila['severidad'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="col-paq"><?php echo htmlspecialchars($fila['paquete'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="col-cvss">
                                    <?php
                                    echo $fila['cvss'] !== null && $fila['cvss'] !== ''
                                        ? htmlspecialchars($fila['cvss'], ENT_QUOTES, 'UTF-8')
                                        : '-';
                                    ?>
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
        const radio = 105;

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
                ctx.lineWidth = 6;
                ctx.strokeStyle = '#000';
                ctx.stroke();

                anguloInicial += angulo;
            }

            ctx.beginPath();
            ctx.arc(centroX, centroY, radio, 0, Math.PI * 2);
            ctx.lineWidth = 6;
            ctx.strokeStyle = '#000';
            ctx.stroke();
        } else {
            ctx.beginPath();
            ctx.arc(centroX, centroY, radio, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.lineWidth = 6;
            ctx.strokeStyle = '#000';
            ctx.stroke();
        }
    </script>
</body>
</html>
