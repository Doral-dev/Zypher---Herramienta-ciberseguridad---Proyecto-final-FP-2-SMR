<?php
session_start();

$DB_HOST = "dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com";
$DB_PORT = "5432";
$DB_NAME = "zypher_db_g2sb";
$DB_USER = "zypher_db_g2sb_user";
$DB_PASS = "TU_PASSWORD_AQUI";

try {
    $pdo = new PDO(
        "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Error BD: " . $e->getMessage());
}

$fechaDesde = trim($_GET['desde'] ?? '');
$fechaHasta = trim($_GET['hasta'] ?? '');
$porPagina  = (int)($_GET['per_page'] ?? 25);
$pagina     = max(1, (int)($_GET['page'] ?? 1));

$permitidos = [25, 50, 100];
if (!in_array($porPagina, $permitidos, true)) {
    $porPagina = 25;
}

$where = [];
$params = [];

if ($fechaDesde !== '') {
    $where[] = "fecha_evento >= :desde";
    $params[':desde'] = $fechaDesde . " 00:00:00";
}

if ($fechaHasta !== '') {
    $where[] = "fecha_evento <= :hasta";
    $params[':hasta'] = $fechaHasta . " 23:59:59";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sqlCount = "SELECT COUNT(*) FROM eventos_monitorizacion $whereSql";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalEventos = (int)$stmtCount->fetchColumn();

$totalPaginas = max(1, (int)ceil($totalEventos / $porPagina));
if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}

$offset = ($pagina - 1) * $porPagina;

$sql = "
    SELECT
        id,
        id_evento,
        descripcion,
        tipo,
        severidad,
        host,
        fecha_evento,
        usuario,
        ip_origen,
        origen,
        regla,
        detalles_raw,
        estado,
        ruta_acceso
    FROM eventos_monitorizacion
    $whereSql
    ORDER BY fecha_evento DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$eventos = $stmt->fetchAll();

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function sevBadgeClass(string $sev): string {
    $sev = mb_strtolower(trim($sev));

    if (str_starts_with($sev, 'muy crítica') || str_starts_with($sev, 'muy critica')) {
        return 'sev-muy-critica';
    }
    if (str_starts_with($sev, 'crítica') || str_starts_with($sev, 'critica')) {
        return 'sev-critica';
    }
    if (str_starts_with($sev, 'moderada')) {
        return 'sev-moderada';
    }
    if (str_starts_with($sev, 'leve')) {
        return 'sev-leve';
    }

    return 'sev-default';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitorización de eventos</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #0b1220;
            color: #e5e7eb;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1450px;
            margin: 30px auto;
            padding: 0 20px;
        }

        h1 {
            margin: 0 0 20px;
            font-size: 28px;
        }

        .filtros {
            display: grid;
            grid-template-columns: 180px 180px 200px 140px;
            gap: 12px;
            margin-bottom: 20px;
            align-items: end;
        }

        .filtro-campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filtro-campo label {
            font-size: 13px;
            color: #cbd5e1;
        }

        .filtros input,
        .filtros select,
        .filtros button {
            width: 100%;
            height: 44px;
            border-radius: 10px;
            border: 1px solid #2b3548;
            background: #121a2b;
            color: #e5e7eb;
            padding: 0 14px;
        }

        .filtros button {
            cursor: pointer;
            font-weight: 700;
            background: #15803d;
            border-color: #15803d;
            color: #fff;
        }

        .tabla-layout {
            display: grid;
            grid-template-columns: 46px 1fr;
            gap: 0;
            align-items: start;
        }

        .acciones-columna {
            display: flex;
            flex-direction: column;
            gap: 0;
            padding-top: 58px;
        }

        .accion-celda {
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #253047;
        }

        .tabla-wrap {
            background: #121a2b;
            border: 1px solid #253047;
            border-radius: 14px;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #172033;
        }

        th, td {
            padding: 16px 12px;
            border-bottom: 1px solid #253047;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
        }

        th {
            color: #cbd5e1;
            font-size: 17px;
            font-weight: 800;
        }

        tr:hover td {
            background: #162033;
        }

        .btn-plus {
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            font-size: 20px;
            line-height: 30px;
            cursor: pointer;
            font-weight: 700;
        }

        .badge {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .sev-leve        { background: #15803d; color: #dcfce7; }   /* verde */
        .sev-moderada    { background: #ca8a04; color: #fefce8; }   /* amarillo */
        .sev-critica     { background: #ea580c; color: #fff7ed; }   /* naranja */
        .sev-muy-critica { background: #b91c1c; color: #fee2e2; }   /* rojo */
        .sev-default     { background: #374151; color: #e5e7eb; }

        .paginacion {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .paginacion a,
        .paginacion span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 12px;
            border-radius: 8px;
            text-decoration: none;
            border: 1px solid #2b3548;
            background: #121a2b;
            color: #e5e7eb;
        }

        .paginacion .actual {
            background: #2563eb;
            border-color: #2563eb;
        }

        .modal-bg {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.65);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal {
            width: min(860px, 100%);
            min-height: 540px;
            max-height: 90vh;
            overflow: auto;
            background: #111827;
            border: 1px solid #2b3548;
            border-radius: 16px;
            padding: 22px;
            position: relative;
        }

        .cerrar {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 10px;
            background: #1f2937;
            color: #fff;
            font-size: 22px;
            cursor: pointer;
        }

        .detalle-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 24px;
            margin-top: 18px;
        }

        .detalle-box {
            background: #0f172a;
            border: 1px solid #253047;
            border-radius: 12px;
            padding: 14px;
        }

        .detalle-box h3 {
            margin: 0 0 12px;
            font-size: 16px;
        }

        .detalle-box p {
            margin: 8px 0;
            font-size: 14px;
            line-height: 1.4;
            word-break: break-word;
        }

        .raw {
            white-space: pre-wrap;
            background: #020617;
            border: 1px solid #253047;
            border-radius: 10px;
            padding: 12px;
            font-size: 13px;
            line-height: 1.4;
            max-height: 250px;
            overflow: auto;
        }

        .sin-datos {
            padding: 24px;
            text-align: center;
            color: #94a3b8;
        }

        @media (max-width: 1100px) {
            .filtros {
                grid-template-columns: 1fr 1fr;
            }

            .detalle-grid {
                grid-template-columns: 1fr;
            }

            .tabla-wrap {
                overflow-x: auto;
            }

            table {
                min-width: 900px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Monitorización de eventos</h1>

    <form method="GET" class="filtros">
        <div class="filtro-campo">
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= h($fechaDesde) ?>">
        </div>

        <div class="filtro-campo">
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= h($fechaHasta) ?>">
        </div>

        <div class="filtro-campo">
            <label for="per_page">Nº eventos a mostrar</label>
            <select id="per_page" name="per_page">
                <option value="25" <?= $porPagina === 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $porPagina === 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $porPagina === 100 ? 'selected' : '' ?>>100</option>
            </select>
        </div>

        <button type="submit">Aplicar</button>
    </form>

    <div class="tabla-layout">
        <div class="acciones-columna">
            <?php if ($eventos): ?>
                <?php foreach ($eventos as $e): ?>
                    <div class="accion-celda">
                        <button
                            type="button"
                            class="btn-plus"
                            onclick="abrirModal(this)"
                            data-id_evento="<?= h($e['id_evento']) ?>"
                            data-descripcion="<?= h($e['descripcion']) ?>"
                            data-tipo="<?= h($e['tipo']) ?>"
                            data-severidad="<?= h($e['severidad']) ?>"
                            data-host="<?= h($e['host']) ?>"
                            data-fecha="<?= h($e['fecha_evento']) ?>"
                            data-usuario="<?= h($e['usuario'] ?? '') ?>"
                            data-ip="<?= h($e['ip_origen'] ?? '') ?>"
                            data-origen="<?= h($e['origen'] ?? '') ?>"
                            data-regla="<?= h($e['regla'] ?? '') ?>"
                            data-estado="<?= h($e['estado'] ?? '') ?>"
                            data-ruta="<?= h($e['ruta_acceso'] ?? '') ?>"
                            data-raw="<?= h($e['detalles_raw'] ?? '') ?>"
                        >+</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="tabla-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID evento</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Severidad</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$eventos): ?>
                        <tr>
                            <td colspan="5" class="sin-datos">No hay eventos.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($eventos as $e): ?>
                            <tr>
                                <td><?= h($e['id_evento']) ?></td>
                                <td><?= h($e['descripcion']) ?></td>
                                <td><?= h($e['tipo']) ?></td>
                                <td>
                                    <span class="badge <?= sevBadgeClass($e['severidad'] ?? '') ?>">
                                        <?= h($e['severidad']) ?>
                                    </span>
                                </td>
                                <td><?= h($e['fecha_evento']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="paginacion">
        <?php
        $queryBase = $_GET;
        for ($i = 1; $i <= $totalPaginas; $i++):
            $queryBase['page'] = $i;
            $url = '?' . http_build_query($queryBase);
        ?>
            <?php if ($i === $pagina): ?>
                <span class="actual"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= h($url) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>

<div class="modal-bg" id="modalBg">
    <div class="modal">
        <button class="cerrar" onclick="cerrarModal()">×</button>
        <h2 id="m-id-evento">Detalle del evento</h2>

        <div class="detalle-grid">
            <div class="detalle-box">
                <h3>Información general</h3>
                <p>ID evento: <span id="m-id"></span></p>
                <p>Descripción: <span id="m-descripcion"></span></p>
                <p>Categoría: <span id="m-tipo"></span></p>
                <p>Severidad: <span id="m-severidad"></span></p>
                <p>Fecha: <span id="m-fecha"></span></p>
                <p>Estado: <span id="m-estado"></span></p>
            </div>

            <div class="detalle-box">
                <h3>Detalles</h3>
                <p>Equipo / Host: <span id="m-host"></span></p>
                <p>Usuario: <span id="m-usuario"></span></p>
                <p>IP origen: <span id="m-ip"></span></p>
                <p>Origen: <span id="m-origen"></span></p>
                <p>Regla disparada: <span id="m-regla"></span></p>
                <p>Ruta de acceso: <span id="m-ruta"></span></p>
            </div>

            <div class="detalle-box" style="grid-column: 1 / -1;">
                <h3>Detalles completos</h3>
                <div class="raw" id="m-raw"></div>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModal(btn) {
    document.getElementById('m-id-evento').textContent = 'Detalle del evento ' + (btn.dataset.id_evento || '');
    document.getElementById('m-id').textContent = btn.dataset.id_evento || '-';
    document.getElementById('m-descripcion').textContent = btn.dataset.descripcion || '-';
    document.getElementById('m-tipo').textContent = btn.dataset.tipo || '-';
    document.getElementById('m-severidad').textContent = btn.dataset.severidad || '-';
    document.getElementById('m-fecha').textContent = btn.dataset.fecha || '-';
    document.getElementById('m-estado').textContent = btn.dataset.estado || '-';
    document.getElementById('m-host').textContent = btn.dataset.host || '-';
    document.getElementById('m-usuario').textContent = btn.dataset.usuario || '-';
    document.getElementById('m-ip').textContent = btn.dataset.ip || '-';
    document.getElementById('m-origen').textContent = btn.dataset.origen || '-';
    document.getElementById('m-regla').textContent = btn.dataset.regla || '-';
    document.getElementById('m-ruta').textContent = btn.dataset.ruta || '-';
    document.getElementById('m-raw').textContent = btn.dataset.raw || '-';
    document.getElementById('modalBg').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalBg').style.display = 'none';
}

document.getElementById('modalBg').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});
</script>
</body>
</html>
