<?php
header('Content-Type: application/json; charset=utf-8');

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
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "conexion_bd"]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data["eventos"]) || !is_array($data["eventos"])) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "json_invalido"]);
    exit;
}

$sql = "
    INSERT INTO eventos_monitorizacion
    (
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
        estado
    )
    VALUES
    (
        :id_evento,
        :descripcion,
        :tipo,
        :severidad,
        :host,
        :fecha_evento,
        :usuario,
        :ip_origen,
        :origen,
        :regla,
        :detalles_raw,
        :estado
    )
";

$stmt = $pdo->prepare($sql);
$insertados = 0;

foreach ($data["eventos"] as $e) {
    $stmt->execute([
        ":id_evento"    => (int)($e["id_evento"] ?? 0),
        ":descripcion"  => (string)($e["descripcion"] ?? ""),
        ":tipo"         => (string)($e["tipo"] ?? ""),
        ":severidad"    => (string)($e["severidad"] ?? "Baja"),
        ":host"         => (string)($e["host"] ?? ""),
        ":fecha_evento" => (string)($e["fecha_evento"] ?? date("Y-m-d H:i:s")),
        ":usuario"      => (string)($e["usuario"] ?? ""),
        ":ip_origen"    => (string)($e["ip_origen"] ?? ""),
        ":origen"       => (string)($e["origen"] ?? ""),
        ":regla"        => (string)($e["regla"] ?? ""),
        ":detalles_raw" => (string)($e["detalles_raw"] ?? ""),
        ":estado"       => (string)($e["estado"] ?? "Nuevo"),
    ]);
    $insertados++;
}

echo json_encode([
    "ok" => true,
    "insertados" => $insertados
]);
