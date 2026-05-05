<?php
header('Content-Type: application/json; charset=utf-8');

function responder($ok, $data = []) {
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
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

    $motores = array_slice($motores, 0, 10);

    $clasificacion = $attr['popular_threat_classification']['suggested_threat_label'] ?? '';
    $categoria = $clasificacion ?: '';

    if (!empty($attr['first_submission_date'])) {
        $primeraVezVisto = date('Y-m-d H:i:s', $attr['first_submission_date']);
    }

    if (!empty($attr['last_analysis_date'])) {
        $ultimoAnalisis = date('Y-m-d H:i:s', $attr['last_analysis_date']);
    }
}

$accion = 'Permitir';

if ($estado === 'Peligroso') {
    $accion = 'Cuarentena';
} elseif ($estado === 'Sospechoso') {
    $accion = 'Revisar';
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
        'fecha_escaneo_zypher' => date('Y-m-d H:i:s')
    ],
    'relaciones' => [
        'dominios_contactados' => [],
        'ips_contactadas' => [],
        'archivos_relacionados' => [],
        'archivos_soltados' => []
    ]
];

responder(true, ['resultado' => $resultado]);
