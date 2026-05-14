<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$host = 'dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com';
$port = 5432;
$db = 'zypher_db_g2sb';
$user = 'zypher_db_g2sb_user';
$pass = 'MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86';

echo "PHP: " . PHP_VERSION . "\n";
echo "PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? OPENSSL_VERSION_TEXT : 'NO') . "\n\n";

echo "DNS:\n";
var_dump(gethostbynamel($host));

echo "\nTCP test:\n";
$errno = 0;
$errstr = '';
$fp = @fsockopen($host, $port, $errno, $errstr, 10);
if ($fp) {
    echo "TCP OK\n";
    fclose($fp);
} else {
    echo "TCP ERROR $errno $errstr\n";
}

echo "\nPDO test sslmode=require:\n";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 15,
    ]);

    echo "PDO OK\n";
    echo $pdo->query("SELECT version()")->fetchColumn() . "\n";
} catch (Throwable $e) {
    echo "PDO ERROR\n";
    echo "Clase: " . get_class($e) . "\n";
    echo "Código: " . $e->getCode() . "\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
}
