<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT NOW() AS fecha");
    $row = $stmt->fetch();

    echo "OK BD conecta\n";
    echo $row['fecha'] . "\n";
} catch (Throwable $e) {
    echo "ERROR BD\n";
    echo $e->getMessage() . "\n";
}
