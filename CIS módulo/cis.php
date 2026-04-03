<?php
// cis.php

$host = 'TU_HOST';
$port = '5432';
$dbname = 'zypher_db_g2sb';
$user = 'TU_USUARIO';
$pass = 'TU_PASSWORD';

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $sql = "SELECT id_cis, titulo, descripcion, comando_remediacion, estado
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

    if ($estado === 'cumple') {
        return '✅';
    }

    if ($estado === 'no cumple') {
        return '❌';
    }

    return '⏳';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Benchmark - Zypher</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f3f3;
            color: #111;
        }

        .wrap {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 44px;
            font-weight: 500;
        }

        .top-actions {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .btn-reset {
            padding: 12px 20px;
            font-size: 18px;
            border: 3px solid #000;
            background: #fff;
            cursor: not-allowed;
        }

        .table-box {
            overflow-x: auto;
            background: #fff;
            border: 4px solid #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 3px solid #000;
            padding: 14px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        th {
            background: #fff;
            font-size: 18px;
            font-weight: 500;
            text-align: center;
        }

        td {
            font-size: 15px;
        }

        .col-id {
            width: 10%;
            text-align: center;
        }

        .col-titulo {
            width: 18%;
        }

        .col-descripcion {
            width: 32%;
        }

        .col-remediacion {
            width: 30%;
        }

        .col-estado {
            width: 10%;
            text-align: center;
            font-size: 26px;
        }

        .empty {
            text-align: center;
            padding: 30px;
            font-size: 18px;
        }

        pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: Consolas, monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>CIS Benchmark Políticas de cumplimiento</h1>

        <div class="top-actions">
            <button class="btn-reset" type="button">🔄 Volver a analizar</button>
        </div>

        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th class="col-id">ID Benchmark</th>
                        <th class="col-titulo">Nombre política</th>
                        <th class="col-descripcion">Descripción política</th>
                        <th class="col-remediacion">Comando remediación</th>
                        <th class="col-estado">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($policies)): ?>
                        <tr>
                            <td colspan="5" class="empty">No hay políticas cargadas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($policies as $policy): ?>
                            <tr>
                                <td class="col-id"><?php echo htmlspecialchars($policy['id_cis']); ?></td>
                                <td class="col-titulo"><?php echo htmlspecialchars($policy['titulo']); ?></td>
                                <td class="col-descripcion"><?php echo htmlspecialchars($policy['descripcion']); ?></td>
                                <td class="col-remediacion">
                                    <pre><?php echo htmlspecialchars($policy['comando_remediacion']); ?></pre>
                                </td>
                                <td class="col-estado"><?php echo mostrarEstado($policy['estado']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
