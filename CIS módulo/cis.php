<?php
// cis.php

$host = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$port = '5432';
$dbname = 'zypher_db_g2sb';
$user = 'zypher_db_g2sb_user';
$password = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
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
    <link rel="stylesheet" href="cis.css">
</head>
<body>
    <div class="wrap">
        <h1>CIS Benchmark Políticas de cumplimiento</h1>

        <form class="top-actions" method="POST" action="ejecutar_cis.php">
            <button class="btn-reset" type="submit">🔄 Volver a analizar</button>
        </form>

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
