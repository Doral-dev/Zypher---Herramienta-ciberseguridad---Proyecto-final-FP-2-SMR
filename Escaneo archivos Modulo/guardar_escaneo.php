<?php
header('Content-Type: application/json; charset=utf-8');

function responder($ok, $data = []) {
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function conectar_bd() {
    $url = getenv('DATABASE_URL');

    if (!$url) {
        return null;
    }

    $db = parse_url($url);

    $host = $db['host'] ?? '';
    $port = $db['port'] ?? 5432;
    $user = $db['user'] ?? '';
    $pass = $db['pass'] ?? '';
    $name = isset($db['path']) ? ltrim($db['path'], '/') : '';

    $conn = pg_connect(
        "host={$host} port={$port} dbname={$name} user={$user} password={$pass} sslmode=require"
    );

    return $conn ?: null;
}

function asegurar_tabla($conn) {
    pg_query($conn, "
        CREATE TABLE IF NOT EXISTS escaneos_archivos (
            id SERIAL PRIMARY KEY,
            fecha TIMESTAMP NOT NULL DEFAULT NOW(),
            nombre TEXT,
            estado TEXT,
            detecciones TEXT,
            sha256 TEXT,
            accion TEXT,
            resultado_json JSONB
        )
    ");
}

function guardar_historial($resultado) {
    $conn = conectar_bd();

    if (!$conn) {
        return null;
    }

    asegurar_tabla($conn);

    $deteccion = $resultado['deteccion'] ?? [];
    $detalles = $resultado['detalles'] ?? [];

    $nombre = $detalles['nombre'] ?? '';
    $estado = $deteccion['estado_general'] ?? '';
    $detecciones = $deteccion['detecciones'] ?? '';
    $sha256 = $detalles['sha256'] ?? '';
    $accion = $deteccion['accion_recomendada'] ?? '';
    $json = json_encode($resultado, JSON_UNESCAPED_UNICODE);

    $res = pg_query_params($conn, "
        INSERT INTO escaneos_archivos
        (nombre, estado, detecciones, sha256, accion, resultado_json)
        VALUES ($1, $2, $3, $4, $5, $6)
        RETURNING id
    ", [$nombre, $estado, $detecciones, $sha256, $accion, $json]);

    if (!$res) {
        return null;
    }

    $row = pg_fetch_assoc($res);
    return $row['id'] ?? null;
}

function obtener_historial() {
    $conn = conectar_bd();

    if (!$conn) {
        responder(false, ['error' => 'No hay conexión con PostgreSQL']);
    }

    asegurar_tabla($conn);

    $res = pg_query($conn, "
        SELECT id, fecha, nombre, estado, detecciones, sha256, accion
        FROM escaneos_archivos
        ORDER BY fecha DESC
        LIMIT 20
    ");

    $items = [];

    while ($row = pg_fetch_assoc($res)) {
        $items[] = $row;
    }

    responder(true, ['historial' => $items]);
}

function obtener_detalle($id) {
    $conn = conectar_bd();

    if (!$conn) {
        responder(false, ['error' => 'No hay conexión con PostgreSQL']);
    }

    asegurar_tabla($conn);

    $res = pg_query_params($conn, "
        SELECT resultado_json
        FROM escaneos_archivos
        WHERE id = $1
        LIMIT 1
    ", [$id]);

    $row = pg_fetch_assoc($res);

    if (!$row) {
        responder(false, ['error' => 'Escaneo no encontrado']);
    }

    responder(true, ['resultado' => json_decode($row['resultado_json'], true)]);
}

function formato_tamano($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 2) . ' KB';
    return round($bytes / 1024 / 1024, 2) . ' MB';
}

function vt_get($endpoint) {
    $apiKey = getenv('VT_API_KEY');

    if (!$apiKey) {
        return null;
    }

    $url = 'https://www.virustotal.com/api/v3' . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'x-apikey: ' . $apiKey
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 404) {
        return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'error' => 'VirusTotal HTTP ' . $httpCode
        ];
    }

    return json_decode($response, true);
}

function fecha_vt($timestamp) {
    if (empty($timestamp)) {
        return '';
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function extraer_categorias($attr) {
    $clasificacion = $attr['popular_threat_classification'] ?? [];

    if (!empty($clasificacion['popular_threat_category'])) {
        $categorias = [];

        foreach ($clasificacion['popular_threat_category'] as $item) {
            if (!empty($item['value'])) {
                $categorias[] = $item['value'];
            }
        }

        $categorias = array_values(array_unique($categorias));

        if ($categorias) {
            return implode(', ', array_slice($categorias, 0, 3));
        }
    }

    return $clasificacion['suggested_threat_label'] ?? '';
}

function es_hash_largo($valor) {
    return preg_match('/^[a-fA-F0-9]{32,128}$/', $valor);
}

function valor_archivo_relacionado($item) {
    $attr = $item['attributes'] ?? [];

    if (!empty($attr['meaningful_name'])) {
        return $attr['meaningful_name'];
    }

    if (!empty($attr['names']) && is_array($attr['names'])) {
        foreach ($attr['names'] as $nombre) {
            if (!empty($nombre) && !es_hash_largo($nombre)) {
                return $nombre;
            }
        }
    }

    if (!empty($attr['type_description'])) {
        return $attr['type_description'];
    }

    return $item['id'] ?? '';
}

function valor_relacion($item, $relacion) {
    $id = $item['id'] ?? '';
    $attr = $item['attributes'] ?? [];

    if ($relacion === 'contacted_domains') {
        return $id;
    }

    if ($relacion === 'contacted_ips') {
        return $id;
    }

    if ($relacion === 'execution_parents' || $relacion === 'dropped_files') {
        return valor_archivo_relacionado($item);
    }

    return $attr['meaningful_name'] ?? $id;
}

function relacion_valida($valor, $relacion) {
    $valor = trim($valor);

    if ($valor === '') {
        return false;
    }

    if ($relacion === 'contacted_domains') {
        if (filter_var($valor, FILTER_VALIDATE_IP)) {
            return false;
        }

        return strpos($valor, '.') !== false && !es_hash_largo($valor);
    }

    if ($relacion === 'contacted_ips') {
        return filter_var($valor, FILTER_VALIDATE_IP) !== false;
    }

    return true;
}

function consultar_relacion($sha256, $relacion, $limite = 30) {
    $data = vt_get('/files/' . $sha256 . '/' . $relacion . '?limit=' . $limite);

    if (!$data || isset($data['error'])) {
        return [];
    }

    $items = [];
    $vistos = [];

    foreach (($data['data'] ?? []) as $item) {
        $valor = valor_relacion($item, $relacion);

        if (!relacion_valida($valor, $relacion)) {
            continue;
        }

        $clave = strtolower($valor);

        if (isset($vistos[$clave])) {
            continue;
        }

        $vistos[$clave] = true;

        $attr = $item['attributes'] ?? [];
        $stats = $attr['last_analysis_stats'] ?? [];

        $maliciosos = intval($stats['malicious'] ?? 0);
        $sospechosos = intval($stats['suspicious'] ?? 0);

        $items[] = [
            'valor' => $valor,
            'tipo' => $item['type'] ?? '',
            'detecciones' => $maliciosos + $sospechosos
        ];

        if (count($items) >= 10) {
            break;
        }
    }

    return $items;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accionGet = $_GET['accion'] ?? '';

    if ($accionGet === 'historial') {
        obtener_historial();
    }

    if ($accionGet === 'detalle') {
        obtener_detalle(intval($_GET['id'] ?? 0));
    }

    responder(false, ['error' => 'Acción GET no válida']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, ['error' => 'Método no permitido']);
}

if (!isset($_FILES['archivo'])) {
    responder(false, ['error' => 'No se ha recibido ningún archivo']);
}

$archivo = $_FILES['archivo'];

if ($archivo['error'] !== UPLOAD_ERR_OK) {
    responder(false, ['error' => 'Error al subir el archivo']);
}

$maxSize = 20 * 1024 * 1024;

if ($archivo['size'] > $maxSize) {
    responder(false, ['error' => 'El archivo supera 20 MB']);
}

$tmp = $archivo['tmp_name'];
$nombre = basename($archivo['name']);
$tamano = filesize($tmp);
$tipo = mime_content_type($tmp) ?: 'Desconocido';

$md5 = hash_file('md5', $tmp);
$sha1 = hash_file('sha1', $tmp);
$sha256 = hash_file('sha256', $tmp);

$vt = vt_get('/files/' . $sha256);

$estado = 'No encontrado';
$detecciones = '0/0';
$etiquetaAmenaza = '';
$categoria = '';
$motores = [];
$nombres = [];
$primeraVezVisto = '';
$ultimoAnalisis = '';

if ($vt && !isset($vt['error'])) {
    $attr = $vt['data']['attributes'] ?? [];

    $stats = $attr['last_analysis_stats'] ?? [];
    $results = $attr['last_analysis_results'] ?? [];

    $malicious = intval($stats['malicious'] ?? 0);
    $suspicious = intval($stats['suspicious'] ?? 0);
    $total = array_sum(array_map('intval', $stats));

    $detecciones = ($malicious + $suspicious) . '/' . $total;

    if ($malicious > 0) {
        $estado = 'Peligroso';
    } elseif ($suspicious > 0) {
        $estado = 'Sospechoso';
    } else {
        $estado = 'Limpio';
    }

    foreach ($results as $motor => $info) {
        $cat = $info['category'] ?? '';
        $res = $info['result'] ?? '';

        if (($cat === 'malicious' || $cat === 'suspicious') && $res) {
            $motores[] = [
                'motor' => $motor,
                'deteccion' => $res
            ];

            if (!$etiquetaAmenaza) {
                $etiquetaAmenaza = $res;
            }
        }
    }

    $motores = array_slice($motores, 0, 20);
    $categoria = extraer_categorias($attr);

    if (!empty($attr['names']) && is_array($attr['names'])) {
        $nombres = array_values(array_unique($attr['names']));
        $nombres = array_slice($nombres, 0, 15);
    }

    $primeraVezVisto = fecha_vt($attr['first_submission_date'] ?? null);
    $ultimoAnalisis = fecha_vt($attr['last_analysis_date'] ?? null);
}

$accion = 'Permitir';

if ($estado === 'Peligroso') {
    $accion = 'Cuarentena';
} elseif ($estado === 'Sospechoso') {
    $accion = 'Revisar';
}

$relaciones = [
    'dominios_contactados' => [],
    'ips_contactadas' => [],
    'archivos_relacionados' => [],
    'archivos_soltados' => []
];

if ($vt && !isset($vt['error'])) {
    $relaciones = [
        'dominios_contactados' => consultar_relacion($sha256, 'contacted_domains', 30),
        'ips_contactadas' => consultar_relacion($sha256, 'contacted_ips', 30),
        'archivos_relacionados' => consultar_relacion($sha256, 'execution_parents', 30),
        'archivos_soltados' => consultar_relacion($sha256, 'dropped_files', 30)
    ];
}

$resultado = [
    'deteccion' => [
        'estado_general' => $estado,
        'detecciones' => $detecciones,
        'etiqueta_amenaza' => $etiquetaAmenaza,
        'categoria' => $categoria,
        'motores_que_detectan' => $motores,
        'accion_recomendada' => $accion,
        'origen_resultado' => getenv('VT_API_KEY') ? 'VirusTotal' : 'Local'
    ],
    'detalles' => [
        'nombre' => $nombre,
        'tamano' => formato_tamano($tamano),
        'tipo' => $tipo,
        'md5' => $md5,
        'sha1' => $sha1,
        'sha256' => $sha256,
        'primera_vez_visto' => $primeraVezVisto,
        'ultimo_analisis' => $ultimoAnalisis,
        'fecha_escaneo_zypher' => date('Y-m-d H:i:s'),
        'mas_nombres_archivo' => $nombres
    ],
    'relaciones' => $relaciones
];

$id = guardar_historial($resultado);

responder(true, [
    'id' => $id,
    'resultado' => $resultado
]);
