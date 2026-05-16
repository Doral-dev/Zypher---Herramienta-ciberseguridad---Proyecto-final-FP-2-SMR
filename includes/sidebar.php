<aside class="dashboard-sidebar" id="dashboardSidebar">
    <a href="/Dashboard/dashboard-inicio.php" class="menu-home">🏠 Inicio</a>

    <div class="menu-category">
        <button type="button" class="menu-title" onclick="toggleMenu(this)">🛡️ Evaluación y refuerzo</button>
        <div class="submenu">
            <a href="/Escaneo Vulnerabilidades Modulo/detector_vulnerabilidades.php">Análisis de vulnerabilidades</a>
            <a href="/CIS módulo/cis.php">CIS Benchmark</a>
            <a href="/Politicas seguridad Modulo/seguridad.php">Políticas de seguridad</a>
        </div>
    </div>

    <div class="menu-category">
        <button type="button" class="menu-title" onclick="toggleMenu(this)">🔍 Amenazas y supervisión</button>
        <div class="submenu">
            <a href="/Escaneo archivos Modulo/escaneo.php">Escaneo de archivos y reputación</a>
            <a href="/Monitorizacion modulo/monitorizacion_eventos.php">Monitorización de eventos</a>
            <a href="/FIM Modulo/detector_fim.php">Monitorización de integridad de archivos</a>
            <a href="/Analisis y Supervision/supervision_eventos.php">Supervisión de eventos</a>
        </div>
    </div>

    <div class="menu-category">
        <button type="button" class="menu-title" onclick="toggleMenu(this)">💾 Copias de seguridad</button>
        <div class="submenu">
            <a href="/Copias modulo/backup_carpetas_personales.php">Backup carpetas personales</a>
            <a href="/Copias modulo/backup_personalizado.php">Backup personalizado</a>
        </div>
    </div>

    <div class="menu-category">
        <button type="button" class="menu-title" onclick="toggleMenu(this)">📊 Documentación e informes</button>
        <div class="submenu">
            <a href="/guia.php">Guía Zypher</a>
            <a href="/informes.php">Informes</a>
        </div>
    </div>

    <div class="menu-category">
        <button type="button" class="menu-title" onclick="toggleMenu(this)">⚙️ Ajustes</button>
        <div class="submenu">
            <a href="/Ajustes/ajustes_contrasena_secundaria.php">Contraseña secundaria</a>
        </div>
    </div>
</aside>

<button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" aria-label="Mostrar u ocultar menú">▶</button>
