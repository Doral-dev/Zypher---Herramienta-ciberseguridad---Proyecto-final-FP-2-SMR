<?php
header('Content-Type: text/plain; charset=utf-8');

$key = getenv('VT_API_KEY');

if (!$key) {
    echo "VT_API_KEY NO CARGADA";
} else {
    echo "VT_API_KEY CARGADA: " . substr($key, 0, 6) . "..." . substr($key, -4);
}
