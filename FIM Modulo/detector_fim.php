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
            background: #f2f2f2;
            color: #111;
        }

        .contenedor {
            width: 92%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 0 40px;
        }

        .titulo {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 32px;
            margin: 0 0 24px;
            font-weight: bold;
        }

        .icono-titulo {
            font-size: 42px;
        }

        .bloque {
            background: #262040;
            padding: 16px;
            margin-bottom: 18px;
        }

        .bloque-interior {
            background: #efedf3;
            border-radius: 18px;
            padding: 18px;
        }

        .lista-rutas {
            border: 1px solid #d8d3df;
            border-radius: 14px;
            overflow: hidden;
            background: #f5f3f8;
        }

        .fila-ruta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 18px;
            border-bottom: 1px solid #ddd7e3;
        }

        .fila-ruta:last-child {
            border-bottom: none;
        }

        .ruta-info {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 17px;
            word-break: break-all;
        }

        .ruta-icono {
            font-size: 26px;
            min-width: 28px;
        }

        .btn-papelera {
            border: none;
            background: transparent;
            font-size: 22px;
            cursor: pointer;
        }

        .zona-add {
            text-align: center;
            margin-top: 18px;
        }

        .btn-add {
            border: none;
            background: linear-gradient(90deg, #9d62ef, #8c6ce9);
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            padding: 16px 34px;
            border-radius: 14px;
            cursor: pointer;
        }

        .formulario {
            display: none;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #d9d4df;
        }

        .formulario.activo {
            display: block;
        }

        .formulario h2 {
            margin: 0 0 18px;
            font-size: 20px;
        }

        .fila-form {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .input-ruta,
        .select-tipo {
            height: 52px;
            font-size: 18px;
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 0 14px;
            background: #fff;
        }

        .input-ruta {
            flex: 1;
            min-width: 320px;
        }

        .select-tipo {
            width: 160px;
        }

        .btn-guardar {
            border: none;
            background: linear-gradient(90deg, #9d62ef, #8c6ce9);
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            padding: 0 26px;
            height: 52px;
            border-radius: 12px;
            cursor: pointer;
        }

        .subtitulo {
            margin: 0 0 18px;
            font-size: 28px;
            font-weight: bold;
        }

        .tabla-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #f5f3f8;
            border-radius: 14px;
            overflow: hidden;
        }

        th, td {
            border: 1px solid #ddd7e3;
            padding: 14px;
            text-align: left;
            vertical-align: top;
            font-size: 16px;
        }

        th {
            background: #ece8f2;
        }

        .sin-datos {
            text-align: center;
            padding: 16px;
            font-size: 18px;
            color: #333;
        }

        @media (max-width: 900px) {
            .titulo {
                font-size: 26px;
            }

            .subtitulo {
                font-size: 22px;
            }

            .fila-form {
                flex-direction: column;
            }

            .input-ruta,
            .select-tipo,
            .btn-guardar {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1 class="titulo">
            <span class="icono-titulo">📁</span>
            <span>Monitorización de integridad de archivos</span>
        </h1>

        <div class="bloque">
            <div class="bloque-interior">
                <div class="lista-rutas">
                    <?php if (count($rutas) === 0): ?>
                        <div class="fila-ruta">
                            <div class="ruta-info">No hay rutas monitorizadas.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rutas as $ruta): ?>
                            <div class="fila-ruta">
                                <div class="ruta-info">
                                    <span class="ruta-icono"><?php echo $ruta['tipo'] === 'carpeta' ? '📁' : '📄'; ?></span>
                                    <span><?php echo htmlspecialchars($ruta['ruta'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <button
                                    class="btn-papelera"
                                    type="button"
                                    onclick="eliminarRuta(<?php echo (int)$ruta['id']; ?>)"
                                    title="Eliminar"
                                >🗑️</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="zona-add">
                    <button class="btn-add" type="button" onclick="toggleFormulario()">+ Añadir nuevo elemento</button>
                </div>

                <div class="formulario" id="formularioRuta">
                    <h2>Nuevo archivo o carpeta a monitorizar</h2>

                    <div class="fila-form">
                        <input
                            type="text"
                            id="ruta"
                            class="input-ruta"
                            placeholder="Ejemplo: C:\FIM-Prueba o C:\FIM-Prueba\archivo.txt"
                        >

                        <select id="tipo" class="select-tipo">
                            <option value="carpeta">Carpeta</option>
                            <option value="archivo">Archivo</option>
                        </select>

                        <button class="btn-guardar" type="button" onclick="guardarRuta()">Guardar</button>
                    </div>
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
                                                Hash anterior: <?php echo htmlspecialchars($evento['hash_anterior'] ?: '-', ENT_QUOTES, 'UTF-8'); ?><br>
                                                Hash nuevo: <?php echo htmlspecialchars($evento['hash_nuevo'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>
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

    <script>
        function toggleFormulario() {
            document.getElementById('formularioRuta').classList.toggle('activo');
        }

        async function guardarRuta() {
            const ruta = document.getElementById('ruta').value.trim();
            const tipo = document.getElementById('tipo').value;

            if (!ruta) {
                alert('Introduce una ruta');
                return;
            }

            const res = await fetch('guardar_fim.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
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
            if (!confirm('¿Eliminar esta ruta?')) {
                return;
            }

            const res = await fetch('guardar_fim.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
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
    </script>
</body>
</html>
