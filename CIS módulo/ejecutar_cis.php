<?php
header('Content-Type: application/json; charset=utf-8');

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

    $sql = "INSERT INTO cis_orders (accion, estado) VALUES (:accion, :estado)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':accion' => 'reanalyze_cis',
        ':estado' => 'pendiente'
    ]);

    echo json_encode([
        'ok' => true
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'output' => [$e->getMessage()]
    ]);
}
