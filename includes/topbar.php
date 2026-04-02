<div class="topbar">
  <div class="topbar-left">
    <img src="/img/logo-zypher.png" class="top-logo">
    <div class="top-title">Zypher</div>
  </div>

  <div class="topbar-info">
    <div class="info-badge">Equipo: <?php echo htmlspecialchars($nombreEquipo); ?></div>
    <div class="info-badge">Usuario: <?php echo htmlspecialchars($nombreUsuario); ?></div>
    <div class="info-badge">Sesión activa</div>
  </div>

  <form action="/logout.php" method="POST">
    <button class="logout-btn">Cerrar sesión</button>
  </form>
</div>
