<div class="zy-sidebar-wrapper" id="sidebarWrapper">
    <div class="zy-sidebar-content">
        <div class="zy-brand">ZYPHER</div>
        
        <nav class="zy-nav">
            <div class="zy-nav-section">
                <a href="/dashboard-inicio.php" class="zy-nav-btn">🏠 Inicio</a>
            </div>

            <div class="zy-nav-section">
                <button class="zy-nav-btn has-child" onclick="zyToggleMenu(this)">
                    🛡️ Evaluación y refuerzo <span class="zy-arrow">▼</span>
                </button>
                <div class="zy-sub-container">
                    <a href="/vulnerabilidades.php">Vulnerabilidades</a>
                    <a href="/cis.php">Políticas CIS</a>
                </div>
            </div>

            <div class="zy-nav-section">
                <button class="zy-nav-btn has-child" onclick="zyToggleMenu(this)">
                    🔍 Amenazas y supervisión <span class="zy-arrow">▼</span>
                </button>
                <div class="zy-sub-container">
                    <a href="/escaneo.php">Escaneo</a>
                    <a href="/monitorizacion.php">Eventos</a>
                    <a href="/respuesta.php">Respuesta</a>
                </div>
            </div>

            <div class="zy-nav-section">
                <a href="/copias.php" class="zy-nav-btn">💾 Continuidad</a>
            </div>
        </nav>
    </div>
</div>

<button class="zy-menu-trigger" onclick="zyToggleSidebar()">
    <span>☰</span>
</button>
