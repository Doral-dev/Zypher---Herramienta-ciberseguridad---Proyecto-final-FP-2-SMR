<aside class="zy-sidebar" id="dashboardSidebar">
    <div class="sidebar-header">
        <a href="/dashboard-inicio.php" class="sidebar-brand">
            <span class="brand-icon">🏠</span>
            <span class="brand-text">Zypher</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group">
            <button class="nav-toggle" onclick="toggleSubmenu(this)">
                <span>🛡️ Evaluación y refuerzo</span>
                <span class="arrow">▾</span>
            </button>
            <div class="nav-submenu">
                <a href="/vulnerabilidades.php">Vulnerabilidades</a>
                <a href="/cis.php">Políticas CIS</a>
            </div>
        </div>

        <div class="nav-group">
            <button class="nav-toggle" onclick="toggleSubmenu(this)">
                <span>🔍 Amenazas y supervisión</span>
                <span class="arrow">▾</span>
            </button>
            <div class="nav-submenu">
                <a href="/escaneo.php">Escaneo</a>
                <a href="/monitorizacion.php">Eventos</a>
                <a href="/respuesta.php">Respuesta</a>
            </div>
        </div>

        <div class="nav-group">
            <a href="/copias.php" class="nav-link">💾 Continuidad</a>
        </div>

        <div class="nav-group">
            <button class="nav-toggle" onclick="toggleSubmenu(this)">
                <span>📊 Informes</span>
                <span class="arrow">▾</span>
            </button>
            <div class="nav-submenu">
                <a href="/informes.php">Generación</a>
                <a href="/acceso-remoto.php">Acceso Nube</a>
            </div>
        </div>
    </nav>
</aside>

<button class="sidebar-trigger" id="sidebarToggleBtn" onclick="toggleSidebar()">
    <span class="trigger-icon">▶</span>
</button>
