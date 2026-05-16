<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id'])) {
    exit('No hay sesión activa.');
}

require_once __DIR__ . '/../db.php';

$usuario_id = (int)$_SESSION['user_id'];
$agente_id = 'windows-agent-001';

function h(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function texto_frecuencia(int $dias): string {
    if ($dias <= 1) return 'Cada día';
    if ($dias <= 7) return 'Cada semana';
    if ($dias <= 30) return 'Cada mes';
    return 'Personalizado';
}

$pdo = getPDO();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS backup_rutas_personalizadas (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        agente_id VARCHAR(100) NOT NULL,
        ruta TEXT NOT NULL,
        activa BOOLEAN NOT NULL DEFAULT FALSE,
        frecuencia_dias INTEGER NOT NULL DEFAULT 7,
        ultima_copia_ok TIMESTAMP NULL,
        created_at TIMESTAMP NOT NULL DEFAULT NOW()
    )
");

$mensaje_ok = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_configuracion') {
        $ids = $_POST['ruta_id'] ?? [];
        $rutas = $_POST['ruta'] ?? [];
        $activas = $_POST['activa'] ?? [];
        $frecuencias = $_POST['frecuencia_dias'] ?? [];

        foreach ($ids as $i => $id) {
            $id = (int)$id;
            $ruta = trim($rutas[$i] ?? '');
            $activa = isset($activas[$i]) ? 1 : 0;
            $frecuencia = (int)($frecuencias[$i] ?? 7);

            if ($ruta === '') {
                continue;
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE backup_rutas_personalizadas
                    SET ruta = :ruta,
                        activa = :activa,
                        frecuencia_dias = :frecuencia_dias
                    WHERE id = :id
                      AND user_id = :user_id
                      AND agente_id = :agente_id
                ");
                $stmt->execute([
                    ':ruta' => $ruta,
                    ':activa' => $activa,
                    ':frecuencia_dias' => $frecuencia,
                    ':id' => $id,
                    ':user_id' => $usuario_id,
                    ':agente_id' => $agente_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO backup_rutas_personalizadas
                    (user_id, agente_id, ruta, activa, frecuencia_dias)
                    VALUES
                    (:user_id, :agente_id, :ruta, :activa, :frecuencia_dias)
                ");
                $stmt->execute([
                    ':user_id' => $usuario_id,
                    ':agente_id' => $agente_id,
                    ':ruta' => $ruta,
                    ':activa' => $activa,
                    ':frecuencia_dias' => $frecuencia
                ]);
            }
        }

        $mensaje_ok = 'Configuración guardada correctamente.';
    }

    if ($accion === 'ejecutar_copia_ahora') {
        $stmt = $pdo->prepare("
            INSERT INTO backup_ordenes
            (agente_id, accion, estado, created_at)
            VALUES
            (:agente_id, 'backup_personalizado_ahora', 'pendiente', NOW())
        ");
        $stmt->execute([
            ':agente_id' => $agente_id
        ]);

        $mensaje_ok = 'Orden de backup personalizada enviada.';
    }
}

$stmt = $pdo->prepare("
    SELECT id, ruta, activa, frecuencia_dias, ultima_copia_ok
    FROM backup_rutas_personalizadas
    WHERE user_id = :user_id
      AND agente_id = :agente_id
    ORDER BY id ASC
");
$stmt->execute([
    ':user_id' => $usuario_id,
    ':agente_id' => $agente_id
]);
$rutas_personalizadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT fecha, estado, carpetas, tamano_mb, archivo_r2, mensaje
    FROM backup_historial
    WHERE agente_id = :agente_id
    ORDER BY fecha DESC
    LIMIT 20
");
$stmt->execute([
    ':agente_id' => $agente_id
]);
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup personalizado - Zypher</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #07152b;
            color: #ffffff;
        }

        .page {
            padding: 24px;
        }

        .card {
            background: #091a36;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 24px;
        }

        h1, h2 {
            margin-top: 0;
        }

        .muted {
            color: #b8c7e0;
            margin-bottom: 18px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.12);
            text-align: left;
            vertical-align: middle;
        }

        th {
            color: #6cb2ff;
            font-size: 15px;
        }

        input[type="text"], select, input[type="password"] {
            width: 100%;
            max-width: 320px;
            padding: 9px 12px;
            border-radius: 10px;
            border: none;
            outline: none;
            font-size: 14px;
        }

        input[type="checkbox"] {
            transform: scale(1.2);
        }

        .actions-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .btn {
            background: #2f6df6;
            color: #fff;
            border: none;
            padding: 10px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            opacity: 0.92;
        }

        .btn-danger {
            background: #e53935;
        }

        .top-inline {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .msg-ok {
            background: #12361f;
            color: #8df0a3;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 14px;
        }

        .msg-error {
            background: #3a1515;
            color: #ff9d9d;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 14px;
        }

        .small-input {
            width: 220px;
            max-width: 220px;
        }
    </style>
</head>
<body>
<div class="page">

    <div class="card">
        <h1>Backup personalizado</h1>
        <div class="muted">Equipo: <?= h($agente_id) ?></div>

        <?php if ($mensaje_ok !== ''): ?>
            <div class="msg-ok"><?= h($mensaje_ok) ?></div>
        <?php endif; ?>

        <?php if ($mensaje_error !== ''): ?>
            <div class="msg-error"><?= h($mensaje_error) ?></div>
        <?php endif; ?>

        <h2>Configuración automática</h2>

        <form method="POST" id="formBackupPersonalizado">
            <input type="hidden" name="accion" id="accionFormulario" value="guardar_configuracion">

            <div class="table-wrap">
                <table id="tablaRutas">
                    <thead>
                        <tr>
                            <th>Ruta</th>
                            <th>Activar</th>
                            <th>Frecuencia</th>
                            <th>Última copia correcta</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rutas_personalizadas): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="ruta_id[]" value="0">
                                <input type="text" name="ruta[]" placeholder="Ej: C:\Users\alex\Documents">
                            </td>
                            <td>
                                <input type="checkbox" name="activa[0]" value="1">
                            </td>
                            <td>
                                <select name="frecuencia_dias[]">
                                    <option value="1">Cada día</option>
                                    <option value="7" selected>Cada semana</option>
                                    <option value="30">Cada mes</option>
                                </select>
                            </td>
                            <td>-</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rutas_personalizadas as $i => $ruta): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="ruta_id[]" value="<?= (int)$ruta['id'] ?>">
                                    <input type="text" name="ruta[]" value="<?= h($ruta['ruta']) ?>">
                                </td>
                                <td>
                                    <input
                                        type="checkbox"
                                        name="activa[<?= (int)$i ?>]"
                                        value="1"
                                        <?= !empty($ruta['activa']) ? 'checked' : '' ?>
                                    >
                                </td>
                                <td>
                                    <select name="frecuencia_dias[]">
                                        <option value="1" <?= (int)$ruta['frecuencia_dias'] === 1 ? 'selected' : '' ?>>Cada día</option>
                                        <option value="7" <?= (int)$ruta['frecuencia_dias'] === 7 ? 'selected' : '' ?>>Cada semana</option>
                                        <option value="30" <?= (int)$ruta['frecuencia_dias'] === 30 ? 'selected' : '' ?>>Cada mes</option>
                                    </select>
                                </td>
                                <td><?= $ruta['ultima_copia_ok'] ? h($ruta['ultima_copia_ok']) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="actions-row">
                <button type="button" class="btn" onclick="anadirRuta()">Añadir ruta</button>
                <button type="submit" class="btn" onclick="document.getElementById('accionFormulario').value='guardar_configuracion'">
                    Guardar configuración
                </button>
                <button type="submit" class="btn" onclick="document.getElementById('accionFormulario').value='ejecutar_copia_ahora'">
                    Ejecutar copia ahora
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Historial</h2>

        <div class="top-inline">
            <input type="password" class="small-input" placeholder="Contraseña secundaria">
            <button type="button" class="btn btn-danger">Limpiar historial</button>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Carpetas</th>
                        <th>Tamaño</th>
                        <th>Archivo R2</th>
                        <th>Mensaje</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$historial): ?>
                    <tr>
                        <td colspan="7">Todavía no hay copias registradas.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($historial as $item): ?>
                        <tr>
                            <td><?= h($item['fecha']) ?></td>
                            <td><?= h($item['estado']) ?></td>
                            <td><?= h($item['carpetas']) ?></td>
                            <td><?= h((string)$item['tamano_mb']) ?> MB</td>
                            <td><?= h($item['archivo_r2']) ?></td>
                            <td><?= h($item['mensaje']) ?></td>
                            <td>-</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function anadirRuta() {
    const tbody = document.querySelector('#tablaRutas tbody');
    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td>
            <input type="hidden" name="ruta_id[]" value="0">
            <input type="text" name="ruta[]" placeholder="Ej: C:\\Users\\alex\\Desktop">
        </td>
        <td>
            <input type="checkbox" name="activa[]" value="1">
        </td>
        <td>
            <select name="frecuencia_dias[]">
                <option value="1">Cada día</option>
                <option value="7" selected>Cada semana</option>
                <option value="30">Cada mes</option>
            </select>
        </td>
        <td>-</td>
    `;

    tbody.appendChild(tr);
}
</script>
</body>
</html>
