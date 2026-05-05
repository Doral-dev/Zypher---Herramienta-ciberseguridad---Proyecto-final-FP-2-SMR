<?php
header('Content-Type: text/plain; charset=utf-8');

echo "PHP OK\n\n";

echo "Archivo guardar_escaneo.php: ";
echo file_exists(__DIR__ . '/guardar_escaneo.php') ? "EXISTE\n" : "NO EXISTE\n";

echo "Extensión pgsql: ";
echo extension_loaded('pgsql') ? "ACTIVA\n" : "NO ACTIVA\n";

echo "Extensión curl: ";
echo extension_loaded('curl') ? "ACTIVA\n" : "NO ACTIVA\n";

echo "DATABASE_URL: ";
echo getenv('DATABASE_URL') ? "CONFIGURADA\n" : "VACÍA\n";

echo "VT_API_KEY: ";
echo getenv('VT_API_KEY') ? "CONFIGURADA\n" : "VACÍA\n";

echo "\nProbando GET historial...\n";

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['accion'] = 'historial';

include __DIR__ . '/guardar_escaneo.php';
