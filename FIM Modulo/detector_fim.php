<?php
$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

try {
    $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmtRutas = $pdo->query("
        SELECT id, ruta, tipo
        FROM fim_rutas
        WHERE activa = TRUE
        ORDER BY id ASC
    ");
    $rutas = $stmtRutas->fetchAll(PDO::FETCH_ASSOC);

    $stmtEventos = $pdo->query("
        SELECT id, ruta, cambio, fecha_evento, hash_anterior, hash_nuevo
        FROM fim_eventos
        ORDER BY fecha_evento DESC, id DESC
        LIMIT 10
    ");
    $eventos = $stmtEventos->fetchAll(PDO::FETCH_ASSOC);

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

        .sin-datos {
            text-align: center;
            font-size: 17px;
            color: #4d4566;
            padding: 18px;
        }

        .hash-linea {
            margin-bottom: 6px;
            word-break: break-all;
        }

        .hash-linea:last-child {
            margin-bottom: 0;
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
                                    <span class="ruta-texto"><?php echo htmlspecialchars($ruta['ruta'], ENT_QUOTES, 'UTF-8'); ?></span>
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

                <div class="tabla-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Ruta</th>
                                <th>Cambio</th>
                                <th>Fecha</th>
                                <th>Hashes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($eventos) === 0): ?>
                                <tr>
                                    <td colspan="5" class="sin-datos">No hay más eventos registrados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($eventos as $evento): ?>
                                    <tr>
                                        <td><?php echo (int)$evento['id']; ?></td>
                                        <td><?php echo htmlspecialchars($evento['ruta'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($evento['cambio'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($evento['fecha_evento'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if ($evento['hash_anterior'] || $evento['hash_nuevo']): ?>
                                                <div class="hash-linea">Hash anterior: <?php echo htmlspecialchars($evento['hash_anterior'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="hash-linea">Hash nuevo: <?php echo htmlspecialchars($evento['hash_nuevo'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td colspan="5" class="sin-datos">No hay más eventos registrados.</td>
                                </tr>
                            <?php endif; ?>
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

        document.getElementById('modalRuta').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
    </script>
</body>
</html>
