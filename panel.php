<?php
declare(strict_types=1);

session_start();

if (empty($_SESSION['logged_in'])) {
    header('Location: /inicio_sesion.html');
    exit;
}

$username = $_SESSION['username'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel - Zypher</title>
  <link rel="stylesheet" href="registro.css">
</head>
<body>

  <main class="register-page">
    <div class="register-wrapper">
      <div class="register-card">
        <div class="logo-wrap">
          <img src="img/logo-zypher.png" alt="Logo Zypher">
          <div class="logo-text">Zypher</div>
        </div>

        <h1>Panel</h1>
        <p class="subtitle">Has iniciado sesión correctamente como <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></p>

        <form action="/logout.php" method="POST" class="register-form">
          <button type="submit" class="register-btn">Cerrar sesión</button>
        </form>
      </div>
    </div>
  </main>

</body>
</html>
