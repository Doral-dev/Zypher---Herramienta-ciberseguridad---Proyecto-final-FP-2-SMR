<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['logged_in'])) {
    header('Location: /inicio-sesion.html');
    exit;
}

$email = $_SESSION['email'] ?? 'usuario@zypher.local';
$nombreUsuario = explode('@', $email)[0] ?: 'usuario';
$nombreEquipo = php_uname('n');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Zypher</title>
    <link rel="stylesheet" href="/css/dashboard.css">
</head>
<body>

<div class="layout">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main">
        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <section class="content">
            <div class="card">
                <h1>Panel principal</h1>
                <p>Bienvenido a Zypher.</p>
            </div>
        </section>

    </main>
</div>

<script src="/js/menu.js"></script>
</body>
</html>
