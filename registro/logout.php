<?php
declare(strict_types=1);

session_start();
$_SESSION = [];
session_destroy();

header('Location: /inicio_sesion.html');
exit;
