<?php
declare(strict_types=1);

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

$AGENTE_ID = 'windows-agent-001';

$mensaje = '';
$error = '';
$politicas = [];

try {
    $pdo = new PDO(
        "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME",
        $DB_USER,
        $DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'aplicar') {
            $politicaId = (int)($_POST['politica_id'] ?? 0);

            if ($politicaId > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO politicas_ordenes (politica_id, agente_id, accion, estado)
                    SELECT :politica_id, CAST(:agente_id_insert AS VARCHAR(120)), 'aplicar', 'pendiente'
                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM politicas_ordenes
                        WHERE politica_id = :politica_id_check
                          AND agente_id = CAST(:agente_id_check AS VARCHAR(120))
                          AND estado IN ('pendiente', 'en_proceso')
                    )
                ");

                $stmt->execute([
                    ':politica_id' => $politicaId,
                    ':agente_id_insert' => $AGENTE_ID,
                    ':politica_id_check' => $politicaId,
                    ':agente_id_check' => $AGENTE_ID,
                ]);

                $mensaje = 'Orden de aplicación creada correctamente.';
            }
        }

        if ($accion === 'refrescar') {
            $stmt = $pdo->prepare("
                INSERT INTO politicas_ordenes (politica_id, agente_id, accion, estado)
                SELECT ps.id, CAST(:agente_id_insert AS VARCHAR(120)), 'verificar', 'pendiente'
                FROM politicas_seguridad ps
                WHERE ps.activa = TRUE
                  AND NOT EXISTS (
                      SELECT 1
                      FROM politicas_ordenes po
                      WHERE po.politica_id = ps.id
                        AND po.agente_id = CAST(:agente_id_check AS VARCHAR(120))
                        AND po.estado IN ('pendiente', 'en_proceso')
                  )
            ");

            $stmt->execute([
                ':agente_id_insert' => $AGENTE_ID,
                ':agente_id_check' => $AGENTE_ID,
            ]);

            $mensaje = 'Orden de verificación creada correctamente.';
        }
    }

    $stmt = $pdo->prepare("
        SELECT 
            ps.id,
            ps.codigo,
            ps.categoria,
            ps.subcategoria,
            ps.nombre,
            ps.descripcion,
            ps.activa,

            pea.cumple,
            pea.ultima_revision,

            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM politicas_ordenes po2
                    WHERE po2.politica_id = ps.id
                      AND po2.agente_id = CAST(:agente_id_pendiente AS VARCHAR(120))
                      AND po2.estado IN ('pendiente', 'en_proceso')
                ) THEN 'pendiente'
                WHEN pea.cumple = TRUE THEN 'corregido'
                WHEN pea.cumple = FALSE THEN 'incorrecto'
                ELSE 'sin_comprobar'
            END AS estado

        FROM politicas_seguridad ps
        LEFT JOIN politicas_estado_agente pea
            ON pea.politica_id = ps.id
           AND pea.agente_id = CAST(:agente_id_join AS VARCHAR(120))
        WHERE ps.activa = TRUE
        ORDER BY ps.categoria, ps.subcategoria, ps.id
    ");

    $stmt->execute([
        ':agente_id_pendiente' => $AGENTE_ID,
        ':agente_id_join' => $AGENTE_ID,
    ]);

    $politicas = $stmt->fetchAll();

} catch (Throwable $e) {
    $error = 'Error: ' . $e->getMessage();
    $politicas = [];
}

$grupos = [];

foreach ($politicas as $p) {
    $grupos[$p['categoria']][$p['subcategoria']][] = $p;
}

function estadoClase(string $estado): string {
    return match ($estado) {
        'corregido' => 'estado-ok',
        'incorrecto' => 'estado-error',
        'pendiente' => 'estado-pendiente',
        default => 'estado-neutral',
    };
}

function estadoTexto(string $estado): string {
    return match ($estado) {
        'corregido' => 'Corregido',
        'incorrecto' => 'Incorrecto',
        'pendiente' => 'Pendiente',
        default => 'Sin comprobar',
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Políticas de seguridad - Zypher</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #070d1c;
            color: #e8eefc;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .dashboard-sidebar {
            width: 285px;
            background: #0b1021;
            border-right: 1px solid rgba(255,255,255,0.08);
            padding: 18px;
        }

        .menu-home,
        .submenu a {
            display: block;
            color: #cbd5f5;
            text-decoration: none;
            padding: 11px 14px;
            border-radius: 10px;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .menu-home:hover,
        .submenu a:hover {
            background: rgba(124, 92, 255, 0.16);
        }

        .menu-category {
            margin-top: 14px;
        }

        .menu-title {
            width: 100%;
            background: transparent;
            color: #ffffff;
            border: 0;
            text-align: left;
            padding: 12px 10px;
            font-size: 15px;
            cursor: pointer;
            font-weight: 700;
        }

        .submenu {
            padding-left: 8px;
        }

        .content {
            flex: 1;
            padding: 28px 34px;
        }

        .top {
            position: relative;
            margin-bottom: 28px;
        }

        h1 {
            margin: 0;
            font-size: 32px;
        }

        .subtitle {
            margin-top: 8px;
            color: #b9c5e8;
            font-size: 15px;
        }

        .refresh-wrap {
            display: flex;
            justify-content: center;
            margin: 18px 0 12px;
        }

        .refresh-btn {
            width: 82px;
            height: 52px;
            border: 0;
            border-radius: 13px;
            background: #1267e8;
            color: white;
            font-size: 30px;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(18, 103, 232, 0.35);
        }

        .refresh-btn:hover {
            background: #1b74ff;
        }

        .alert-ok {
            background: rgba(50, 185, 95, 0.15);
            border: 1px solid rgba(50, 185, 95, 0.35);
            color: #93f0ae;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
        }

        .alert-error {
            background: rgba(255, 75, 92, 0.15);
            border: 1px solid rgba(255, 75, 92, 0.35);
            color: #ff9aa6;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
        }

        .categoria {
            background: rgba(16, 28, 52, 0.92);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            margin-bottom: 18px;
            overflow: hidden;
        }

        .categoria-header {
            padding: 16px 20px;
            font-size: 20px;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .contador {
            background: #123e82;
            color: #bcd4ff;
            font-size: 13px;
            padding: 3px 9px;
            border-radius: 10px;
        }

        .subcategoria {
            padding: 16px 20px 20px;
        }

        .subcategoria h3 {
            margin: 0 0 12px;
            color: #ffffff;
            font-size: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
        }

        th {
            text-align: left;
            color: #dce6ff;
            font-size: 14px;
            padding: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        td {
            padding: 10px;
            color: #cdd8f6;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            font-size: 14px;
            vertical-align: middle;
        }

        .col-politica {
            width: 27%;
            color: #ffffff;
        }

        .col-desc {
            width: 43%;
        }

        .col-accion {
            width: 15%;
        }

        .col-estado {
            width: 15%;
        }

        .apply-btn {
            background: #1267e8;
            border: 0;
            color: white;
            padding: 7px 18px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 13px;
        }

        .apply-btn:hover {
            background: #1b74ff;
        }

        .estado {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
        }

        .estado-ok {
            color: #7ee06d;
        }

        .estado-error {
            color: #ff5f6d;
        }

        .estado-pendiente {
            color: #ffd166;
        }

        .estado-neutral {
            color: #9eadcc;
        }

        .empty {
            padding: 30px;
            text-align: center;
            color: #9eadcc;
        }

        @media (max-width: 900px) {
            .layout {
                display: block;
            }

            .dashboard-sidebar {
                width: 100%;
            }

            .content {
                padding: 20px;
            }

            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

<div class="layout">

    <aside class="dashboard-sidebar" id="dashboardSidebar">
        <a href="/dashboard-inicio.php" class="menu-home">🏠 Inicio</a>

        <div class="menu-category">
            <button type="button" class="menu-title">🛡️ Evaluación y refuerzo</button>
            <div class="submenu">
                <a href="/Escaneo Vulnerabilidades Modulo/detector_vulnerabilidades.php">Análisis de vulnerabilidades</a>
                <a href="/CIS módulo/cis.php">CIS Benchmark</a>
                <a href="/Politicas seguridad Modulo/seguridad.php">Políticas de seguridad</a>
            </div>
        </div>

        <div class="menu-category">
            <button type="button" class="menu-title">🔍 Amenazas y supervisión</button>
            <div class="submenu">
                <a href="/Escaneo archivos Modulo/escaneo.php">Escaneo de archivos y reputación</a>
                <a href="/Monitorizacion modulo/monitorizacion_eventos.php">Monitorización de eventos</a>
                <a href="/FIM Modulo/detector_fim.php">Monitorización de integridad de archivos</a>
                <a href="/respuesta.php">Respuesta ante eventos</a>
            </div>
        </div>

        <div class="menu-category">
            <button type="button" class="menu-title">💾 Continuidad</button>
            <div class="submenu">
                <a href="/copias.php">Copias de seguridad</a>
                <a href="/acceso-remoto.php">Acceso remoto desde la nube</a>
            </div>
        </div>

        <div class="menu-category">
            <button type="button" class="menu-title">📊 Documentación e informes</button>
            <div class="submenu">
                <a href="/guia.php">Guía Zypher</a>
                <a href="/informes.php">Informes</a>
            </div>
        </div>
    </aside>

    <main class="content">
        <div class="top">
            <h1>Políticas de seguridad</h1>
            <div class="subtitle">Gestiona y aplica las políticas de seguridad recomendadas para el sistema.</div>

            <form method="POST" class="refresh-wrap">
                <input type="hidden" name="accion" value="refrescar">
                <button type="submit" class="refresh-btn" title="Refrescar estado">↻</button>
            </form>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert-ok"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$grupos): ?>
            <div class="empty">No hay políticas registradas.</div>
        <?php endif; ?>

        <?php foreach ($grupos as $categoria => $subcategorias): ?>
            <?php
            $totalCategoria = 0;
            foreach ($subcategorias as $items) {
                $totalCategoria += count($items);
            }
            ?>

            <section class="categoria">
                <div class="categoria-header">
                    <?= htmlspecialchars($categoria) ?>
                    <span class="contador"><?= $totalCategoria ?></span>
                </div>

                <?php foreach ($subcategorias as $subcategoria => $items): ?>
                    <div class="subcategoria">
                        <h3><?= htmlspecialchars($subcategoria) ?></h3>

                        <table>
                            <thead>
                                <tr>
                                    <th class="col-politica">Política</th>
                                    <th class="col-desc">Descripción</th>
                                    <th class="col-accion">Acción</th>
                                    <th class="col-estado">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $p): ?>
                                    <tr>
                                        <td class="col-politica"><?= htmlspecialchars($p['nombre']) ?></td>
                                        <td class="col-desc"><?= htmlspecialchars($p['descripcion']) ?></td>
                                        <td class="col-accion">
                                            <form method="POST">
                                                <input type="hidden" name="accion" value="aplicar">
                                                <input type="hidden" name="politica_id" value="<?= (int)$p['id'] ?>">
                                                <button type="submit" class="apply-btn">Aplicar</button>
                                            </form>
                                        </td>
                                        <td class="col-estado">
                                            <span class="estado <?= estadoClase($p['estado']) ?>">
                                                ● <?= htmlspecialchars(estadoTexto($p['estado'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </main>
</div>

</body>
</html>
