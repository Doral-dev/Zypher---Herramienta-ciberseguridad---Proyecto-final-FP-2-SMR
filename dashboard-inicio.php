
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
    <title>Zypher Panel</title>
    <link rel="stylesheet" href="/css/dashboard.css">
</head>
<body>

<div class="app">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-area">
        <?php include __DIR__ . '/includes/topbar.php'; ?>

        <div class="content">
            <div class="card">
                <h1>Panel principal</h1>
                <p>Bienvenido a Zypher.</p>
            </div>
        </div>
    </div>
</div>

<script src="/js/menu.js"></script>
</body>
</html>
