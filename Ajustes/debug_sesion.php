<?php
declare(strict_types=1);

session_start();

echo '<pre>';
echo 'SESSION:' . PHP_EOL;
print_r($_SESSION);

echo PHP_EOL . 'COOKIE:' . PHP_EOL;
print_r($_COOKIE);
echo '</pre>';
