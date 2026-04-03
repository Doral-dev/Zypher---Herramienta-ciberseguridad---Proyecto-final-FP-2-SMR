<aside class="dashboard-sidebar" id="dashboardSidebar">
    <a href="/dashboard-inicio.php" class="menu-home">🏠 Inicio</a>

    <div class="menu-category">
        <button type="button" class="menu-title" onclick="toggleMenu(this)">🛡️ Evaluación y refuerzo</button>
        <div class="submenu">
            <a href="/vulnerabilidades.php">Análisis de vulnerabilidades</a>
            <a href="/CIS módulo/cis.php">Aplicación de políticas CIS</a>
        </div>
    </div>

    <div class="menu-category">
        <button type="button" class="menu-title" onclick="toggleMenu(this)">🔍 Amenazas y supervisión</button>
        <div class="submenu">
            <a href="/escaneo.php">Escaneo de archivos y reputación</a>
            <a href="/monitorizacion.php">Monitorización de eventos</a>
            <a href="/respuesta.php">Respuesta ante eventos</a>
        </div>
    </div>

    <div class="menu-category">
        <button type="button" class="menu-title" onclick="toggleMenu(this)">💾 Continuidad</button>
        <div class="submenu">
            <a href="/copias.php">Copias de seguridad</a>
        </div>
    </div>

    <div class="menu-category">
        <button type="button" class="menu-title" onclick="toggleMenu(this)">📋 Revisión</button>
        <div class="submenu">
            <a href="/recordatorios.php">Recordatorio y guía de revisión</a>
            <a href="/guia.php">Guía de uso de Zypher</a>
        </div>
    </div>

    <div class="menu-category">
        <button type="button" class="menu-title" onclick="toggleMenu(this)">📊 Informes</button>
        <div class="submenu">
            <a href="/informes.php">Generación de informes</a>
            <a href="/acceso-remoto.php">Acceso remoto desde la nube</a>
        </div>
    </div>
</aside>

<button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" aria-label="Mostrar u ocultar menú">▶</button>
