<header class="topbar">
    <div class="topbar-left">
        <img src="/img/logo.png" alt="Zypher Logo" class="top-logo">
        <div class="top-title">ZYPHER</div>
    </div>

    <div class="topbar-info">
        <div class="info-badge">Equipo: <?php echo htmlspecialchars($nombreEquipo, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="info-badge">Usuario: <?php echo htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="info-badge">Sesión activa</div>
    </div>

    <form action="/logout.php" method="POST" class="logout-form">
        <button type="submit" class="logout-btn">Cerrar sesión</button>
    </form>
</header>
