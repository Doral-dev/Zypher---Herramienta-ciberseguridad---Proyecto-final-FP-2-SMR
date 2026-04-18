<?php
date_default_timezone_set('Europe/Madrid');

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

function h($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function formatear_fecha_fim($fecha)
{
    if (!$fecha) {
        return '-';
    }

    try {
        $dt = new DateTime((string)$fecha, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Madrid'));
        return $dt->format('Y-m-d H:i');
    } catch (Throwable $e) {
        return h($fecha);
    }
}

function resumir_hash($hash)
{
    $hash = trim((string)$hash);

    if ($hash === '') {
        return '-';
    }

    if (strlen($hash) <= 24) {
        return $hash;
    }

    return substr($hash, 0, 12) . '...' . substr($hash, -12);
}

function clase_cambio($cambio)
{
    return match ((string)$cambio) {
        'Creado' => 'badge-creado',
        'Modificado' => 'badge-modificado',
        'Eliminado' => 'badge-eliminado',
        default => 'badge-default'
    };
}

function obtener_limit()
{
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    return in_array($limit, [10, 25, 50], true) ? $limit : 10;
}

function obtener_filtros()
{
    return [
        'cambio' => trim((string)($_GET['cambio'] ?? '')),
        'ruta'   => trim((string)($_GET['ruta'] ?? '')),
        'q'      => trim((string)($_GET['q'] ?? '')),
        'limit'  => obtener_limit()
    ];
}

function construir_where_y_params(array $filtros)
{
    $where = [];
    $params = [];

    if ($filtros['cambio'] !== '' && in_array($filtros['cambio'], ['Creado', 'Modificado', 'Eliminado'], true)) {
        $where[] = 'cambio = :cambio';
        $params[':cambio'] = $filtros['cambio'];
    }

    if ($filtros['ruta'] !== '') {
        $where[] = 'ruta ILIKE :ruta';
        $params[':ruta'] = '%' . $filtros['ruta'] . '%';
    }

    if ($filtros['q'] !== '') {
        $where[] = '(
            CAST(id AS TEXT) ILIKE :q
            OR ruta ILIKE :q
            OR cambio ILIKE :q
            OR COALESCE(hash_anterior, \'\') ILIKE :q
            OR COALESCE(hash_nuevo, \'\') ILIKE :q
        )';
        $params[':q'] = '%' . $filtros['q'] . '%';
    }

    return [$where, $params];
}

function obtener_rutas(PDO $pdo)
{
    $stmt = $pdo->query("
        SELECT id, ruta, tipo
        FROM fim_rutas
        WHERE activa = TRUE
        ORDER BY id ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtener_eventos(PDO $pdo, array $filtros)
{
    [$where, $params] = construir_where_y_params($filtros);

    $sql = "
        SELECT id, ruta, cambio, fecha_evento, hash_anterior, hash_nuevo
        FROM fim_eventos
    ";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY fecha_evento DESC, id DESC LIMIT ' . (int)$filtros['limit'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function render_hash_linea($label, $valor, $rowId, $tipo)
{
    $valor = trim((string)$valor);

    if ($valor === '') {
        return '
            <div class="hash-linea">
                <span class="hash-etiqueta">' . h($label) . ':</span>
                <span class="hash-vacio">-</span>
            </div>
        ';
    }

    $targetId = 'hash-' . $rowId . '-' . $tipo;

    return '
        <div class="hash-linea">
            <div class="hash-fila">
                <span class="hash-etiqueta">' . h($label) . ':</span>
                <span class="hash-resumen" title="' . h($valor) . '">' . h(resumir_hash($valor)) . '</span>
                <button type="button" class="btn-hash" data-target="' . h($targetId) . '">Ver completo</button>
            </div>
            <div class="hash-completo" id="' . h($targetId) . '">' . h($valor) . '</div>
        </div>
    ';
}

function render_eventos_rows(array $eventos)
{
    if (!$eventos) {
        return '
            <tr>
                <td colspan="5" class="sin-datos">No hay eventos registrados.</td>
            </tr>
        ';
    }

    $html = '';

    foreach ($eventos as $evento) {
        $id = (int)$evento['id'];
        $ruta = h($evento['ruta']);
        $cambio = h($evento['cambio']);
        $fecha = formatear_fecha_fim($evento['fecha_evento']);
        $badgeClass = clase_cambio($evento['cambio']);

        $hashes = render_hash_linea('Hash anterior', $evento['hash_anterior'] ?? '', $id, 'anterior');
        $hashes .= render_hash_linea('Hash nuevo', $evento['hash_nuevo'] ?? '', $id, 'nuevo');

        $html .= '
            <tr>
                <td>' . $id . '</td>
                <td>' . $ruta . '</td>
                <td><span class="badge-cambio ' . h($badgeClass) . '">' . $cambio . '</span></td>
                <td>' . h($fecha) . '</td>
                <td>' . $hashes . '</td>
            </tr>
        ';
    }

    return $html;
}

try {
    $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $filtros = obtener_filtros();

    if (isset($_GET['ajax']) && $_GET['ajax'] === 'eventos') {
        header('Content-Type: application/json; charset=utf-8');

        $eventos = obtener_eventos($pdo, $filtros);

        echo json_encode([
            'ok' => true,
            'tbody' => render_eventos_rows($eventos),
            'total' => count($eventos)
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $rutas = obtener_rutas($pdo);
    $eventos = obtener_eventos($pdo, $filtros);

} catch (Throwable $e) {
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'eventos') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Error de base de datos'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(500);
    echo 'Error de base de datos: ' . h($e->getMessage());
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitorización de integridad de archivos</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #f3f0ff;
            background:
                radial-gradient(circle at top center, rgba(120, 84, 255, 0.18), transparent 30%),
                linear-gradient(90deg, #0c1028 0%, #1b1742 35%, #231d4f 50%, #1b1742 65%, #0c1028 100%);
            min-height: 100vh;
        }

        .contenedor {
            width: 92%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 34px 0 42px;
        }

        .titulo {
            display: flex;
            align-items: center;
            gap: 18px;
            margin: 0 0 28px;
            font-size: 34px;
            font-weight: 700;
            color: #f4f2ff;
        }

        .titulo-icono {
            font-size: 46px;
            line-height: 1;
        }

        .bloque {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            padding: 18px;
            margin-bottom: 26px;
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.22);
        }

        .bloque-interior {
            background: linear-gradient(180deg, #f2eefb 0%, #ebe7f5 100%);
            border: 1px solid #d6d0e6;
            border-radius: 22px;
            padding: 22px;
            color: #221d39;
        }

        .lista-rutas {
            border: 1px solid #d8d1e8;
            border-radius: 16px;
            overflow: hidden;
            background: #f4f1fa;
        }

        .fila-ruta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 68px;
            padding: 0 18px;
            border-bottom: 1px solid #ddd7ec;
        }

        .fila-ruta:last-child {
            border-bottom: none;
        }

        .ruta-info {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
            font-size: 17px;
            color: #3c3556;
        }

        .ruta-icono {
            font-size: 28px;
            flex-shrink: 0;
        }

        .ruta-texto {
            word-break: break-all;
        }

        .zona-add {
            display: flex;
            justify-content: center;
            margin-top: 18px;
        }

        .btn-add,
        .btn-guardar {
            border: none;
            border-radius: 16px;
            background: linear-gradient(90deg, #9f6aff 0%, #8d63f0 100%);
            color: #ffffff;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 22px rgba(120, 84, 255, 0.28);
        }

        .btn-add {
            min-width: 360px;
            height: 56px;
            font-size: 18px;
        }

        .btn-guardar {
            height: 54px;
            padding: 0 30px;
            font-size: 18px;
            white-space: nowrap;
        }

        .btn-eliminar {
            border: none;
            background: #e53935;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            padding: 10px 16px;
            border-radius: 12px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .btn-eliminar:hover {
            background: #c62828;
        }

        .subtitulo {
            margin: 0 0 18px;
            font-size: 28px;
            font-weight: 700;
            color: #231d39;
        }

        .filtros-barra {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: end;
            margin-bottom: 18px;
        }

        .campo-filtro {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 180px;
            flex: 1;
        }

        .campo-filtro label {
            font-size: 14px;
            font-weight: 700;
            color: #3b3457;
        }

        .campo-filtro input,
        .campo-filtro select {
            height: 46px;
            border: 1px solid #cfc8df;
            border-radius: 12px;
            background: #ffffff;
            font-size: 15px;
            color: #2d2642;
            padding: 0 14px;
            outline: none;
        }

        .estado-tabla {
            margin-bottom: 14px;
            font-size: 15px;
            color: #4b4465;
        }

        .tabla-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #f4f1fa;
            border: 1px solid #d8d1e8;
            border-radius: 16px;
            overflow: hidden;
        }

        th,
        td {
            padding: 15px 14px;
            border-right: 1px solid #ddd7ec;
            border-bottom: 1px solid #ddd7ec;
            text-align: left;
            vertical-align: top;
            color: #342d4d;
            font-size: 16px;
            background: #f4f1fa;
        }

        th:last-child,
        td:last-child {
            border-right: none;
        }

        tr:last-child td {
            border-bottom: none;
        }

        th {
            background: #ece7f6;
            font-size: 17px;
            font-weight: 700;
            color: #2a2341;
        }

        .badge-cambio {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 110px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
        }

        .badge-creado {
            background: #2e7d32;
        }

        .badge-modificado {
            background: #ef6c00;
        }

        .badge-eliminado {
            background: #c62828;
        }

        .badge-default {
            background: #6b7280;
        }

        .sin-datos {
            text-align: center;
            font-size: 17px;
            color: #4d4566;
            padding: 18px;
        }

        .hash-linea {
            margin-bottom: 10px;
        }

        .hash-linea:last-child {
            margin-bottom: 0;
        }

        .hash-fila {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .hash-etiqueta {
            font-weight: 700;
            color: #2f2947;
        }

        .hash-resumen {
            font-family: Consolas, monospace;
            font-size: 14px;
            color: #3d3559;
            background: #ece7f6;
            border-radius: 8px;
            padding: 5px 8px;
        }

        .hash-vacio {
            color: #6a627f;
        }

        .btn-hash {
            border: none;
            background: #8d63f0;
            color: #fff;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-hash:hover {
            background: #7448dd;
        }

        .hash-completo {
            display: none;
            margin-top: 8px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #ece7f6;
            border: 1px solid #ddd7ec;
            font-family: Consolas, monospace;
            font-size: 13px;
            color: #342d4d;
            word-break: break-all;
        }

        .hash-completo.abierto {
            display: block;
        }

        .modal-fondo {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(8, 10, 24, 0.65);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-fondo.activo {
            display: flex;
        }

        .modal {
            width: 100%;
            max-width: 760px;
            background: linear-gradient(180deg, #f2eefb 0%, #ebe7f5 100%);
            border: 1px solid #d6d0e6;
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.35);
            color: #221d39;
            position: relative;
        }

        .modal-cerrar {
            position: absolute;
            top: 14px;
            right: 16px;
            border: none;
            background: transparent;
            color: #df3b3b;
            font-size: 28px;
            font-weight: 700;
            cursor: pointer;
            line-height: 1;
        }

        .modal h2 {
            margin: 0 0 18px;
            font-size: 24px;
            font-weight: 700;
            color: #231d39;
        }

        .modal-form {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .input-ruta {
            flex: 1;
            min-width: 320px;
            height: 54px;
            border: 1px solid #cfc8df;
            border-radius: 14px;
            background: #ffffff;
            font-size: 17px;
            color: #2d2642;
            padding: 0 16px;
        }

        @media (max-width: 900px) {
            .titulo {
                font-size: 28px;
            }

            .subtitulo {
                font-size: 24px;
            }

            .btn-add {
                width: 100%;
                min-width: 0;
            }

            .modal-form {
                flex-direction: column;
            }

            .input-ruta,
            .btn-guardar {
                width: 100%;
            }

            .filtros-barra {
                flex-direction: column;
                align-items: stretch;
            }

            .campo-filtro {
                min-width: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1 class="titulo">
            <span class="titulo-icono">📁</span>
            <span>Monitorización de integridad de archivos</span>
        </h1>

        <div class="bloque">
            <div class="bloque-interior">
                <h2 class="subtitulo">Archivos y carpetas monitorizadas actualmente:</h2>

                <div class="lista-rutas">
                    <?php if (count($rutas) === 0): ?>
                        <div class="fila-ruta">
                            <div class="ruta-info">
                                <span class="ruta-texto">No hay rutas monitorizadas.</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rutas as $ruta): ?>
                            <div class="fila-ruta">
                                <div class="ruta-info">
                                    <span class="ruta-icono"><?php echo $ruta['tipo'] === 'carpeta' ? '📁' : '📄'; ?></span>
                                    <span class="ruta-texto"><?php echo h($ruta['ruta']); ?></span>
                                </div>

                                <button
                                    type="button"
                                    class="btn-eliminar"
                                    onclick="eliminarRuta(<?php echo (int)$ruta['id']; ?>)"
                                >
                                    Eliminar
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="zona-add">
                    <button type="button" class="btn-add" onclick="abrirModal()">+ Añadir nuevo archivo/carpeta</button>
                </div>
            </div>
        </div>

        <div class="bloque">
            <div class="bloque-interior">
                <h2 class="subtitulo">Últimos cambios registrados:</h2>

                <div class="filtros-barra">
                    <div class="campo-filtro">
                        <label for="filtroCambio">Cambio</label>
                        <select id="filtroCambio">
                            <option value="">Todos</option>
                            <option value="Creado">Creado</option>
                            <option value="Modificado">Modificado</option>
                            <option value="Eliminado">Eliminado</option>
                        </select>
                    </div>

                    <div class="campo-filtro">
                        <label for="filtroRuta">Filtrar por ruta</label>
                        <input type="text" id="filtroRuta" placeholder="Ejemplo: C:\FIM-Prueba">
                    </div>

                    <div class="campo-filtro">
                        <label for="filtroBusqueda">Buscar</label>
                        <input type="text" id="filtroBusqueda" placeholder="ID, elemento, hash...">
                    </div>

                    <div class="campo-filtro">
                        <label for="filtroLimit">Ver</label>
                        <select id="filtroLimit">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>

                <div class="estado-tabla" id="estadoTabla">
                    Mostrando <?php echo count($eventos); ?> eventos
                </div>

                <div class="tabla-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Elemento</th>
                                <th>Cambio</th>
                                <th>Fecha</th>
                                <th>Hashes</th>
                            </tr>
                        </thead>
                        <tbody id="eventosBody">
                            <?php echo render_eventos_rows($eventos); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-fondo" id="modalRuta">
        <div class="modal">
            <button type="button" class="modal-cerrar" onclick="cerrarModal()" title="Cerrar">✕</button>
            <h2>Añadir nuevo archivo/carpeta</h2>

            <div class="modal-form">
                <input
                    type="text"
                    id="ruta"
                    class="input-ruta"
                    placeholder="Ejemplo: C:\FIM-Prueba o C:\FIM-Prueba\archivo.txt"
                >
                <button type="button" class="btn-guardar" onclick="guardarRuta()">Guardar</button>
            </div>
        </div>
    </div>

    <script>
        const filtroCambio = document.getElementById('filtroCambio');
        const filtroRuta = document.getElementById('filtroRuta');
        const filtroBusqueda = document.getElementById('filtroBusqueda');
        const filtroLimit = document.getElementById('filtroLimit');
        const eventosBody = document.getElementById('eventosBody');
        const estadoTabla = document.getElementById('estadoTabla');

        filtroCambio.value = <?php echo json_encode($filtros['cambio'], JSON_UNESCAPED_UNICODE); ?>;
        filtroRuta.value = <?php echo json_encode($filtros['ruta'], JSON_UNESCAPED_UNICODE); ?>;
        filtroBusqueda.value = <?php echo json_encode($filtros['q'], JSON_UNESCAPED_UNICODE); ?>;
        filtroLimit.value = <?php echo json_encode((string)$filtros['limit'], JSON_UNESCAPED_UNICODE); ?>;

        let debounceTimer = null;

        function abrirModal() {
            document.getElementById('modalRuta').classList.add('activo');
        }

        function cerrarModal() {
            document.getElementById('modalRuta').classList.remove('activo');
        }

        async function guardarRuta() {
            const ruta = document.getElementById('ruta').value.trim();

            if (!ruta) {
                alert('Introduce una ruta');
                return;
            }

            const tipo = ruta.includes('.') ? 'archivo' : 'carpeta';

            const res = await fetch('guardar_fim.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    accion: 'agregar_ruta',
                    ruta: ruta,
                    tipo: tipo
                })
            });

            const data = await res.json();

            if (data.ok) {
                location.reload();
            } else {
                alert(data.error || 'Error al guardar');
            }
        }

        async function eliminarRuta(id) {
            const res = await fetch('guardar_fim.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    accion: 'eliminar_ruta',
                    id: id
                })
            });

            const data = await res.json();

            if (data.ok) {
                location.reload();
            } else {
                alert(data.error || 'Error al eliminar');
            }
        }

        async function cargarEventos() {
            try {
                const url = new URL(window.location.href);
                url.search = '';

                url.searchParams.set('ajax', 'eventos');
                url.searchParams.set('cambio', filtroCambio.value);
                url.searchParams.set('ruta', filtroRuta.value.trim());
                url.searchParams.set('q', filtroBusqueda.value.trim());
                url.searchParams.set('limit', filtroLimit.value);

                const res = await fetch(url.toString(), {
                    cache: 'no-store'
                });

                const data = await res.json();

                if (!data.ok) {
                    return;
                }

                eventosBody.innerHTML = data.tbody;
                estadoTabla.textContent = 'Mostrando ' + data.total + ' eventos';
            } catch (e) {
            }
        }

        function cargarEventosDebounce() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(cargarEventos, 300);
        }

        filtroCambio.addEventListener('change', cargarEventos);
        filtroLimit.addEventListener('change', cargarEventos);
        filtroRuta.addEventListener('input', cargarEventosDebounce);
        filtroBusqueda.addEventListener('input', cargarEventosDebounce);

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-hash');
            if (!btn) {
                return;
            }

            const target = document.getElementById(btn.dataset.target);
            if (!target) {
                return;
            }

            const abierto = target.classList.toggle('abierto');
            btn.textContent = abierto ? 'Ocultar' : 'Ver completo';
        });

        document.getElementById('modalRuta').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        setInterval(cargarEventos, 4000);
    </script>
</body>
</html>
