<?php
header('Content-Type: application/json; charset=utf-8');

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

function responder($ok, $data = []) {
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responder(false, ['error' => 'Método no permitido']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data['accion'])) {
    http_response_code(400);
    responder(false, ['error' => 'JSON inválido']);
}

try {
    $dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $accion = trim((string)$data['accion']);

    if ($accion === 'obtener_rutas') {
        $stmt = $pdo->query("
            SELECT id, ruta, tipo
            FROM fim_rutas
            WHERE activa = TRUE
            ORDER BY id ASC
        ");

        $rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        responder(true, ['rutas' => $rutas]);
    }

    if ($accion === 'agregar_ruta') {
        $ruta = trim((string)($data['ruta'] ?? ''));
        $tipo = trim((string)($data['tipo'] ?? ''));

        if ($ruta === '' || !in_array($tipo, ['carpeta', 'archivo'], true)) {
            http_response_code(400);
            responder(false, ['error' => 'Faltan datos']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO fim_rutas (ruta, tipo, activa)
            VALUES (:ruta, :tipo, TRUE)
            ON CONFLICT (ruta)
            DO UPDATE SET
                tipo = EXCLUDED.tipo,
                activa = TRUE
        ");
        $stmt->execute([
            ':ruta' => $ruta,
            ':tipo' => $tipo
        ]);

        responder(true, ['mensaje' => 'Ruta guardada']);
    }

    if ($accion === 'eliminar_ruta') {
        $id = isset($data['id']) ? (int)$data['id'] : 0;

        if ($id <= 0) {
            http_response_code(400);
            responder(false, ['error' => 'ID inválido']);
        }

        $stmt = $pdo->prepare("DELETE FROM fim_rutas WHERE id = :id");
        $stmt->execute([':id' => $id]);

        responder(true, ['mensaje' => 'Ruta eliminada']);
    }

    if ($accion === 'guardar_eventos') {
        $eventos = $data['eventos'] ?? null;

        if (!is_array($eventos)) {
            http_response_code(400);
            responder(false, ['error' => 'Eventos inválidos']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO fim_eventos (
                ruta,
                tipo_elemento,
                cambio,
                hash_anterior,
                hash_nuevo,
                fecha_evento
            )
            VALUES (
                :ruta,
                :tipo_elemento,
                :cambio,
                :hash_anterior,
                :hash_nuevo,
                CURRENT_TIMESTAMP
            )
        ");

        $guardados = 0;

        foreach ($eventos as $evento) {
            $ruta = trim((string)($evento['ruta'] ?? ''));
            $tipoElemento = trim((string)($evento['tipo_elemento'] ?? ''));
            $cambio = trim((string)($evento['cambio'] ?? ''));
            $hashAnterior = trim((string)($evento['hash_anterior'] ?? ''));
            $hashNuevo = trim((string)($evento['hash_nuevo'] ?? ''));

            if (
                $ruta === '' ||
                !in_array($tipoElemento, ['carpeta', 'archivo'], true) ||
                !in_array($cambio, ['Creado', 'Modificado', 'Eliminado'], true)
            ) {
                continue;
            }

            $stmt->execute([
                ':ruta' => $ruta,
                ':tipo_elemento' => $tipoElemento,
                ':cambio' => $cambio,
                ':hash_anterior' => $hashAnterior !== '' ? $hashAnterior : null,
                ':hash_nuevo' => $hashNuevo !== '' ? $hashNuevo : null
            ]);

            $guardados++;
        }

        responder(true, ['guardados' => $guardados]);
    }

    http_response_code(400);
    responder(false, ['error' => 'Acción no válida']);

} catch (Throwable $e) {
    http_response_code(500);
    responder(false, [
        'error' => 'Error interno',
        'detalle' => $e->getMessage()
    ]);
}
