<?php
$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

function color_accion($accion) {
    $accion = mb_strtolower(trim((string)$accion));
    if ($accion === 'creado') return '#d9f8d9';
    if ($accion === 'modificado') return '#fff4cc';
    if ($accion === 'borrado') return '#ffd6d6';
    return '#eeeeee';
}

try {
    $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmt = $pdo->query("
        SELECT
            id,
            agent_id,
            hostname,
            accion,
            ruta,
            tamano_anterior,
            tamano_actual,
            fecha_mod_anterior,
            fecha_mod_actual,
            fecha_evento
        FROM fim_eventos
        ORDER BY fecha_evento DESC, id DESC
        LIMIT 300
    ");

    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error de base de datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIM</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            color: #111;
        }
        .contenedor {
            width: 95%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 20px 0 40px;
        }
        h1 {
            text-align: center;
            margin: 0 0 20px;
            font-size: 34px;
            font-weight: normal;
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
            border: 6px solid #000;
            padding: 12px 10px;
            vertical-align: top;
        }
        th {
            background: #f8f8f8;
            font-size: 18px;
            font-weight: normal;
            text-align: center;
        }
        td {
            font-size: 15px;
            line-height: 1.4;
        }
        .col-fecha { width: 14%; text-align: center; }
        .col-equipo { width: 12%; text-align: center; }
        .col-accion { width: 10%; text-align: center; }
        .col-ruta { width: 42%; }
        .col-ant { width: 11%; text-align: center; }
        .col-act { width: 11%; text-align: center; }
        .sin-datos {
            text-align: center;
            font-size: 22px;
            background: #fff;
            border: 6px solid #000;
            padding: 40px 20px;
        }
        .badge {
            display: inline-block;
            border: 3px solid #000;
            padding: 8px 10px;
            min-width: 110px;
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>FIM</h1>

        <div class="tabla-wrap">
            <?php if (count($eventos) === 0): ?>
                <div class="sin-datos">No hay eventos FIM.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th class="col-fecha">Fecha</th>
                            <th class="col-equipo">Equipo</th>
                            <th class="col-accion">Acción</th>
                            <th class="col-ruta">Ruta</th>
                            <th class="col-ant">Tamaño anterior</th>
                            <th class="col-act">Tamaño actual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eventos as $e): ?>
                            <tr>
                                <td class="col-fecha">
                                    <?php echo htmlspecialchars($e['fecha_evento'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="col-equipo">
                                    <?php echo htmlspecialchars($e['hostname'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="col-accion">
                                    <span class="badge" style="background: <?php echo htmlspecialchars(color_accion($e['accion']), ENT_QUOTES, 'UTF-8'); ?>;">
                                        <?php echo htmlspecialchars($e['accion'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="col-ruta">
                                    <?php echo nl2br(htmlspecialchars($e['ruta'], ENT_QUOTES, 'UTF-8')); ?>
                                </td>
                                <td class="col-ant">
                                    <?php echo $e['tamano_anterior'] !== null ? htmlspecialchars($e['tamano_anterior'], ENT_QUOTES, 'UTF-8') : '-'; ?>
                                </td>
                                <td class="col-act">
                                    <?php echo $e['tamano_actual'] !== null ? htmlspecialchars($e['tamano_actual'], ENT_QUOTES, 'UTF-8') : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
