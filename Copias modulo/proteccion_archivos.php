<?php
declare(strict_types=1);

$DB_HOST = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$DB_PORT = '5432';
$DB_NAME = 'zypher_db_g2sb';
$DB_USER = 'zypher_db_g2sb_user';
$DB_PASSWORD = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

$MAX_UPLOAD_MB = 50;
$RESULTADOS_DIR = __DIR__ . '/proteccion_resultados';

$METODOS = [
    'aes256' => 'Cifrado AES-256 con contraseña',
    'zip_password' => 'ZIP protegido con contraseña',
    'metadata_image' => 'Eliminar metadatos de imagen',
    'sha256' => 'Generar SHA256 de integridad',
    'double_protection' => 'Doble protección',
    'pdf_aes' => 'Cifrar PDF'
];

$METODOS_CON_PASSWORD = [
    'aes256',
    'zip_password',
    'double_protection',
    'pdf_aes'
];

function db(): PDO {
    global $DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASSWORD;

    return new PDO(
        "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME",
        $DB_USER,
        $DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function h($txt): string {
    return htmlspecialchars((string)$txt, ENT_QUOTES, 'UTF-8');
}

function bytes_humanos(int $bytes): string {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    }

    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }

    return $bytes . ' B';
}

function nombre_seguro(string $nombre): string {
    $nombre = basename($nombre);
    $nombre = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $nombre);
    $nombre = trim((string)$nombre, '._-');

    return $nombre !== '' ? $nombre : 'archivo';
}

function base_sin_extension(string $nombre): string {
    $base = pathinfo($nombre, PATHINFO_FILENAME);
    $base = nombre_seguro($base);

    return $base !== '' ? $base : 'archivo';
}

function extension_archivo(string $nombre): string {
    return strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
}

function asegurar_directorio_resultados(): void {
    global $RESULTADOS_DIR;

    if (!is_dir($RESULTADOS_DIR)) {
        mkdir($RESULTADOS_DIR, 0755, true);
    }
}

function crear_tabla_si_no_existe(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS proteccion_archivos_historial (
            id SERIAL PRIMARY KEY,
            metodo VARCHAR(80) NOT NULL,
            metodo_nombre VARCHAR(150) NOT NULL,
            archivo_original TEXT NOT NULL,
            archivo_resultado TEXT,
            ruta_resultado TEXT,
            mime_resultado VARCHAR(255),
            tamano_original BIGINT DEFAULT 0,
            tamano_resultado BIGINT DEFAULT 0,
            sha256_original TEXT,
            sha256_resultado TEXT,
            estado VARCHAR(30) DEFAULT 'completado',
            mensaje TEXT,
            created_at TIMESTAMP DEFAULT NOW()
        )
    ");
}

function guardar_historial(PDO $pdo, array $data): int {
    $stmt = $pdo->prepare("
        INSERT INTO proteccion_archivos_historial
            (
                metodo,
                metodo_nombre,
                archivo_original,
                archivo_resultado,
                ruta_resultado,
                mime_resultado,
                tamano_original,
                tamano_resultado,
                sha256_original,
                sha256_resultado,
                estado,
                mensaje,
                created_at
            )
        VALUES
            (
                :metodo,
                :metodo_nombre,
                :archivo_original,
                :archivo_resultado,
                :ruta_resultado,
                :mime_resultado,
                :tamano_original,
                :tamano_resultado,
                :sha256_original,
                :sha256_resultado,
                :estado,
                :mensaje,
                NOW()
            )
        RETURNING id
    ");

    $stmt->execute([
        ':metodo' => $data['metodo'] ?? '',
        ':metodo_nombre' => $data['metodo_nombre'] ?? '',
        ':archivo_original' => $data['archivo_original'] ?? '',
        ':archivo_resultado' => $data['archivo_resultado'] ?? '',
        ':ruta_resultado' => $data['ruta_resultado'] ?? '',
        ':mime_resultado' => $data['mime_resultado'] ?? 'application/octet-stream',
        ':tamano_original' => $data['tamano_original'] ?? 0,
        ':tamano_resultado' => $data['tamano_resultado'] ?? 0,
        ':sha256_original' => $data['sha256_original'] ?? '',
        ':sha256_resultado' => $data['sha256_resultado'] ?? '',
        ':estado' => $data['estado'] ?? 'completado',
        ':mensaje' => $data['mensaje'] ?? ''
    ]);

    return (int)$stmt->fetchColumn();
}

function cifrar_aes256(string $inputPath, string $outputPath, string $password, string $nombreOriginal): void {
    if ($password === '') {
        throw new Exception('Este método necesita contraseña.');
    }

    if (!function_exists('openssl_encrypt')) {
        throw new Exception('OpenSSL no está disponible en PHP.');
    }

    $data = file_get_contents($inputPath);

    if ($data === false) {
        throw new Exception('No se pudo leer el archivo.');
    }

    $salt = random_bytes(16);
    $iv = random_bytes(16);
    $iterations = 200000;

    $keyMaterial = hash_pbkdf2('sha256', $password, $salt, $iterations, 64, true);
    $encKey = substr($keyMaterial, 0, 32);
    $macKey = substr($keyMaterial, 32, 32);

    $ciphertext = openssl_encrypt(
        $data,
        'aes-256-cbc',
        $encKey,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($ciphertext === false) {
        throw new Exception('No se pudo cifrar el archivo.');
    }

    $header = json_encode([
        'format' => 'zypher-aes-v1',
        'cipher' => 'AES-256-CBC',
        'kdf' => 'PBKDF2-SHA256',
        'iterations' => $iterations,
        'salt' => base64_encode($salt),
        'iv' => base64_encode($iv),
        'original_name' => $nombreOriginal
    ], JSON_UNESCAPED_UNICODE);

    if ($header === false) {
        throw new Exception('No se pudo generar cabecera de cifrado.');
    }

    $mac = hash_hmac('sha256', $header . "\n" . $ciphertext, $macKey, true);

    $final = "ZYPHER-AES-256-CBC\n";
    $final .= base64_encode($header) . "\n";
    $final .= base64_encode($mac) . "\n";
    $final .= $ciphertext;

    if (file_put_contents($outputPath, $final) === false) {
        throw new Exception('No se pudo guardar el archivo cifrado.');
    }
}

function limpiar_metadatos_imagen(string $inputPath, string $outputPath, string $extension): void {
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
        throw new Exception('La extensión GD de PHP no está disponible.');
    }

    if (in_array($extension, ['jpg', 'jpeg'], true)) {
        $img = imagecreatefromjpeg($inputPath);

        if (!$img) {
            throw new Exception('No se pudo abrir la imagen JPG.');
        }

        imagejpeg($img, $outputPath, 95);
        imagedestroy($img);
        return;
    }

    if ($extension === 'png') {
        $img = imagecreatefrompng($inputPath);

        if (!$img) {
            throw new Exception('No se pudo abrir la imagen PNG.');
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagepng($img, $outputPath, 6);
        imagedestroy($img);
        return;
    }

    throw new Exception('Solo se soportan imágenes JPG, JPEG y PNG.');
}

function crear_zip_protegido_o_fallback_aes(string $inputPath, string $outputBase, string $password, string $nombreOriginal): array {
    if ($password === '') {
        throw new Exception('Este método necesita contraseña.');
    }

    if (!class_exists('ZipArchive')) {
        throw new Exception('ZipArchive no está disponible en PHP.');
    }

    $zipPath = $outputBase . '.zip';
    $insideName = nombre_seguro($nombreOriginal);

    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('No se pudo crear el ZIP.');
    }

    $zip->addFile($inputPath, $insideName);
    $zip->setPassword($password);

    $zipCifradoReal = false;

    if (method_exists($zip, 'setEncryptionName') && defined('ZipArchive::EM_AES_256')) {
        $zipCifradoReal = $zip->setEncryptionName($insideName, ZipArchive::EM_AES_256);
    }

    $zip->close();

    if ($zipCifradoReal) {
        return [
            'path' => $zipPath,
            'name' => basename($zipPath),
            'mime' => 'application/zip',
            'mensaje' => 'ZIP protegido con contraseña creado correctamente.'
        ];
    }

    $encPath = $zipPath . '.zypher.enc';
    cifrar_aes256($zipPath, $encPath, $password, basename($zipPath));
    @unlink($zipPath);

    return [
        'path' => $encPath,
        'name' => basename($encPath),
        'mime' => 'application/octet-stream',
        'mensaje' => 'El servidor no soporta ZIP cifrado real. Se creó ZIP y se protegió con AES-256.'
    ];
}

$pdo = db();
crear_tabla_si_no_existe($pdo);
asegurar_directorio_resultados();

$error = '';
$resultado = null;

if (isset($_GET['download'])) {
    $id = (int)$_GET['download'];

    $stmt = $pdo->prepare("
        SELECT archivo_resultado, ruta_resultado, mime_resultado
        FROM proteccion_archivos_historial
        WHERE id = :id
          AND estado = 'completado'
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['ruta_resultado']) || !is_file($row['ruta_resultado'])) {
        exit('Archivo no encontrado.');
    }

    header('Content-Type: ' . ($row['mime_resultado'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($row['archivo_resultado']) . '"');
    header('Content-Length: ' . filesize($row['ruta_resultado']));

    readfile($row['ruta_resultado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ejecutar'])) {
    try {
        global $METODOS, $METODOS_CON_PASSWORD, $MAX_UPLOAD_MB, $RESULTADOS_DIR;

        $metodo = (string)($_POST['metodo'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (!isset($METODOS[$metodo])) {
            throw new Exception('Método no válido.');
        }

        if (in_array($metodo, $METODOS_CON_PASSWORD, true) && $password === '') {
            throw new Exception('Este método necesita contraseña.');
        }

        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No se ha subido ningún archivo válido.');
        }

        $archivo = $_FILES['archivo'];
        $tamanoOriginal = (int)$archivo['size'];

        if ($tamanoOriginal <= 0) {
            throw new Exception('El archivo está vacío.');
        }

        if ($tamanoOriginal > ($MAX_UPLOAD_MB * 1024 * 1024)) {
            throw new Exception('El archivo supera el límite de ' . $MAX_UPLOAD_MB . ' MB.');
        }

        $nombreOriginal = nombre_seguro((string)$archivo['name']);
        $base = base_sin_extension($nombreOriginal);
        $extension = extension_archivo($nombreOriginal);
        $tmpInput = (string)$archivo['tmp_name'];

        $shaOriginal = hash_file('sha256', $tmpInput);
        $random = date('Ymd_His') . '_' . bin2hex(random_bytes(5));
        $outputBase = $RESULTADOS_DIR . '/' . $base . '_' . $random;

        $outputPath = '';
        $outputName = '';
        $mime = 'application/octet-stream';
        $mensaje = '';

        if ($metodo === 'aes256') {
            $outputName = $base . '_' . $random . '.zypher.enc';
            $outputPath = $RESULTADOS_DIR . '/' . $outputName;

            cifrar_aes256($tmpInput, $outputPath, $password, $nombreOriginal);
            $mensaje = 'Archivo cifrado con AES-256 correctamente.';
        }

        if ($metodo === 'zip_password') {
            $zipResultado = crear_zip_protegido_o_fallback_aes(
                $tmpInput,
                $outputBase,
                $password,
                $nombreOriginal
            );

            $outputPath = $zipResultado['path'];
            $outputName = $zipResultado['name'];
            $mime = $zipResultado['mime'];
            $mensaje = $zipResultado['mensaje'];
        }

        if ($metodo === 'metadata_image') {
            if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                throw new Exception('Este método solo funciona con JPG, JPEG y PNG.');
            }

            $outputName = $base . '_' . $random . '_sin_metadatos.' . $extension;
            $outputPath = $RESULTADOS_DIR . '/' . $outputName;

            limpiar_metadatos_imagen($tmpInput, $outputPath, $extension);

            $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
            $mensaje = 'Imagen regenerada sin metadatos.';
        }

        if ($metodo === 'sha256') {
            $outputName = $base . '_' . $random . '_sha256.txt';
            $outputPath = $RESULTADOS_DIR . '/' . $outputName;

            $contenido = "Archivo: " . $nombreOriginal . PHP_EOL;
            $contenido .= "SHA256: " . $shaOriginal . PHP_EOL;
            $contenido .= "Fecha: " . date('Y-m-d H:i:s') . PHP_EOL;

            file_put_contents($outputPath, $contenido);

            $mime = 'text/plain';
            $mensaje = 'Hash SHA256 generado correctamente.';
        }

        if ($metodo === 'double_protection') {
            $inputParaCifrar = $tmpInput;
            $tempLimpio = null;

            if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                $tempLimpio = $RESULTADOS_DIR . '/' . $base . '_' . $random . '_limpio.' . $extension;
                limpiar_metadatos_imagen($tmpInput, $tempLimpio, $extension);
                $inputParaCifrar = $tempLimpio;
                $mensaje = 'Metadatos eliminados y archivo cifrado con AES-256.';
            } else {
                $mensaje = 'El archivo no era imagen compatible. Se aplicó cifrado AES-256.';
            }

            $outputName = $base . '_' . $random . '_doble.zypher.enc';
            $outputPath = $RESULTADOS_DIR . '/' . $outputName;

            cifrar_aes256($inputParaCifrar, $outputPath, $password, $nombreOriginal);

            if ($tempLimpio && is_file($tempLimpio)) {
                @unlink($tempLimpio);
            }
        }

        if ($metodo === 'pdf_aes') {
            if ($extension !== 'pdf') {
                throw new Exception('Este método solo acepta archivos PDF.');
            }

            $outputName = $base . '_' . $random . '.pdf.zypher.enc';
            $outputPath = $RESULTADOS_DIR . '/' . $outputName;

            cifrar_aes256($tmpInput, $outputPath, $password, $nombreOriginal);
            $mensaje = 'PDF cifrado con AES-256 correctamente.';
        }

        if ($outputPath === '' || !is_file($outputPath)) {
            throw new Exception('No se generó archivo de resultado.');
        }

        $shaResultado = hash_file('sha256', $outputPath);
        $tamanoResultado = filesize($outputPath);

        $idHistorial = guardar_historial($pdo, [
            'metodo' => $metodo,
            'metodo_nombre' => $METODOS[$metodo],
            'archivo_original' => $nombreOriginal,
            'archivo_resultado' => $outputName,
            'ruta_resultado' => $outputPath,
            'mime_resultado' => $mime,
            'tamano_original' => $tamanoOriginal,
            'tamano_resultado' => $tamanoResultado,
            'sha256_original' => $shaOriginal,
            'sha256_resultado' => $shaResultado,
            'estado' => 'completado',
            'mensaje' => $mensaje
        ]);

        $resultado = [
            'id' => $idHistorial,
            'mensaje' => $mensaje,
            'archivo' => $outputName,
            'sha_original' => $shaOriginal,
            'sha_resultado' => $shaResultado,
            'tamano' => bytes_humanos((int)$tamanoResultado)
        ];

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->query("
    SELECT id, metodo_nombre, archivo_original, archivo_resultado, tamano_original, tamano_resultado, sha256_original, sha256_resultado, estado, mensaje, created_at
    FROM proteccion_archivos_historial
    ORDER BY created_at DESC
    LIMIT 30
");
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Protección de archivos - Zypher</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: #e5e7eb;
            margin: 0;
            padding: 30px;
        }

        .card {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 24px;
        }

        h1, h2 {
            margin-top: 0;
        }

        p {
            color: #9ca3af;
        }

        .dropzone {
            border: 2px dashed #3b82f6;
            border-radius: 16px;
            padding: 35px;
            text-align: center;
            background: #0b1220;
            cursor: pointer;
        }

        .dropzone.dragover {
            background: #172554;
        }

        .file-name {
            margin-top: 12px;
            color: #93c5fd;
        }

        .metodos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .metodo {
            background: #0b1220;
            border: 1px solid #273449;
            border-radius: 12px;
            padding: 14px;
            cursor: pointer;
        }

        .metodo:hover {
            border-color: #3b82f6;
        }

        .metodo input {
            margin-right: 8px;
        }

        input[type="password"],
        button {
            padding: 10px 12px;
            border-radius: 8px;
            border: 0;
        }

        input[type="password"] {
            min-width: 280px;
        }

        button {
            background: #2563eb;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        button:disabled {
            background: #6b7280;
            cursor: not-allowed;
        }

        .ok { color: #22c55e; }
        .error { color: #ef4444; }
        .muted { color: #9ca3af; }

        .resultado {
            background: #0b1220;
            border: 1px solid #1f2937;
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
        }

        .fila-botones {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-download {
            display: inline-block;
            padding: 10px 12px;
            border-radius: 8px;
            background: #16a34a;
            color: white;
            text-decoration: none;
        }

        .btn-download:hover {
            background: #15803d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #374151;
            text-align: left;
            vertical-align: top;
        }

        th {
            color: #93c5fd;
        }

        code {
            word-break: break-all;
            color: #bfdbfe;
        }
    </style>
</head>
<body>

<div class="card">
    <h1>Protección de archivos</h1>
    <p>Arrastra un archivo, elige un método de protección y descarga el resultado protegido.</p>

    <?php if ($error): ?>
        <p class="error"><?php echo h($error); ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="formProteccion">
        <div class="dropzone" id="dropzone">
            <div>Arrastra aquí el archivo o haz clic para seleccionarlo</div>
            <div class="muted">Máximo <?php echo (int)$MAX_UPLOAD_MB; ?> MB</div>
            <div class="file-name" id="fileName"></div>
            <input type="file" name="archivo" id="archivo" hidden required>
        </div>

        <h2>Método de protección</h2>

        <div class="metodos">
            <?php foreach ($METODOS as $codigo => $nombre): ?>
                <label class="metodo">
                    <input type="radio" name="metodo" value="<?php echo h($codigo); ?>" required>
                    <?php echo h($nombre); ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="fila-botones" id="bloquePassword" style="display:none;">
            <input type="password" name="password" id="password" placeholder="Contraseña para proteger el archivo">
        </div>

        <div class="fila-botones">
            <button type="submit" name="ejecutar" id="btnEjecutar">Ejecutar</button>
        </div>
    </form>

    <?php if ($resultado): ?>
        <div class="resultado">
            <h2>Resultado</h2>
            <p class="ok"><?php echo h($resultado['mensaje']); ?></p>
            <p>Archivo generado: <?php echo h($resultado['archivo']); ?></p>
            <p>Tamaño: <?php echo h($resultado['tamano']); ?></p>
            <p>SHA256 original:</p>
            <code><?php echo h($resultado['sha_original']); ?></code>
            <p>SHA256 resultado:</p>
            <code><?php echo h($resultado['sha_resultado']); ?></code>
            <br><br>
            <a class="btn-download" href="?download=<?php echo (int)$resultado['id']; ?>">Descargar archivo protegido</a>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Historial</h2>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Método</th>
                <th>Archivo original</th>
                <th>Resultado</th>
                <th>Tamaño</th>
                <th>Mensaje</th>
                <th>Descarga</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$historial): ?>
            <tr>
                <td colspan="7" class="muted">Todavía no hay archivos protegidos.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($historial as $item): ?>
                <tr>
                    <td><?php echo h($item['created_at']); ?></td>
                    <td><?php echo h($item['metodo_nombre']); ?></td>
                    <td><?php echo h($item['archivo_original']); ?></td>
                    <td><?php echo h($item['archivo_resultado']); ?></td>
                    <td><?php echo h(bytes_humanos((int)$item['tamano_resultado'])); ?></td>
                    <td><?php echo h($item['mensaje']); ?></td>
                    <td>
                        <?php if ($item['estado'] === 'completado' && $item['archivo_resultado']): ?>
                            <a class="btn-download" href="?download=<?php echo (int)$item['id']; ?>">Descargar</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
const dropzone = document.getElementById('dropzone');
const archivo = document.getElementById('archivo');
const fileName = document.getElementById('fileName');
const form = document.getElementById('formProteccion');
const btn = document.getElementById('btnEjecutar');
const bloquePassword = document.getElementById('bloquePassword');
const password = document.getElementById('password');

const metodosConPassword = ['aes256', 'zip_password', 'double_protection', 'pdf_aes'];

dropzone.addEventListener('click', () => archivo.click());

dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('dragover');
});

dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('dragover');
});

dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');

    if (e.dataTransfer.files.length > 0) {
        archivo.files = e.dataTransfer.files;
        fileName.textContent = e.dataTransfer.files[0].name;
    }
});

archivo.addEventListener('change', () => {
    if (archivo.files.length > 0) {
        fileName.textContent = archivo.files[0].name;
    }
});

document.querySelectorAll('input[name="metodo"]').forEach((radio) => {
    radio.addEventListener('change', () => {
        if (metodosConPassword.includes(radio.value)) {
            bloquePassword.style.display = 'flex';
            password.required = true;
        } else {
            bloquePassword.style.display = 'none';
            password.required = false;
            password.value = '';
        }
    });
});

form.addEventListener('submit', () => {
    btn.disabled = true;
    btn.textContent = 'Procesando...';
});
</script>

</body>
</html>
