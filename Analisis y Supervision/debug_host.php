<?php
header('Content-Type: text/plain');
echo gethostname() . "\n";
echo $_SERVER['SERVER_SOFTWARE'] ?? 'sin server software';
