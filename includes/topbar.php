<header class="topbar">
    <div class="topbar-left">
        <img src="/img/logo.png" alt="Zypher Logo" class="top-logo">
        <h1 class="top-title">ZYPHER</h1>
    </div>

    <div class="topbar-info">
        <div class="info-badge">
            <span class="text-muted">Equipo:</span> 
            <strong><?php echo htmlspecialchars($nombreEquipo); ?></strong>
        </div>
        <div class="info-badge">
            <span class="text-muted">Usuario:</span> 
            <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong>
        </div>
        <form action="/logout.php" method="POST" class="logout-form">
            <button type="submit" class="logout-btn">Cerrar Sesión</button>
        </form>
    </div>
</header>
