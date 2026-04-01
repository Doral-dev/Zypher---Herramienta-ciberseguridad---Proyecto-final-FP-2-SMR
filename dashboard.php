<?php
declare(strict_types=1);

session_start();

if (empty($_SESSION['logged_in'])) {
    header('Location: /inicio-sesion.html');
    exit;
}

$email = $_SESSION['email'] ?? 'usuario@zypher.local';
$nombreUsuario = explode('@', $email)[0];
$nombreEquipo = php_uname('n');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Zypher</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg: #f6f3ff;
            --panel: #ffffff;
            --panel-soft: #f8f5ff;
            --border: #ddd4f5;
            --text: #2f2550;
            --text-soft: #6f6791;
            --purple: #6f4ed6;
            --purple-dark: #4b2cab;
            --purple-light: #ede7ff;
            --green: #dff6e8;
            --green-text: #247a46;
            --yellow: #fff4d8;
            --yellow-text: #9a6a00;
            --red: #ffe1e1;
            --red-text: #b42323;
            --shadow: 0 14px 32px rgba(75, 44, 171, 0.10);
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: linear-gradient(180deg, #faf8ff 0%, #f1ebff 100%);
            color: var(--text);
            min-height: 100vh;
        }

        .layout {
            display: grid;
            grid-template-columns: 290px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: #ffffff;
            border-right: 1px solid var(--border);
            padding: 24px 18px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 22px;
        }

        .brand img {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .brand-title {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--purple-dark);
        }

        .menu-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .menu-item {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px 16px;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            text-align: center;
            box-shadow: var(--shadow);
        }

        .menu-item.active {
            background: linear-gradient(180deg, #7d60e4 0%, #6f4ed6 100%);
            color: #ffffff;
            border-color: #6f4ed6;
        }

        .main {
            padding: 24px;
        }

        .topbar {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .topbar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: var(--purple-dark);
            font-size: 1.2rem;
        }

        .topbar-logo img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .topbar-info {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .info-badge {
            background: var(--panel-soft);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .notify {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: var(--panel-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .logout-form {
            margin: 0;
        }

        .logout-btn {
            border: none;
            border-radius: 14px;
            padding: 12px 18px;
            background: linear-gradient(180deg, #8a6df0 0%, #6f4ed6 100%);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .content {
            display: grid;
            gap: 20px;
        }

        .hero-card,
        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 24px;
            box-shadow: var(--shadow);
        }

        .hero-card {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 20px;
            align-items: center;
        }

        .hero-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--purple-dark);
            margin-bottom: 10px;
        }

        .hero-text {
            color: var(--text-soft);
            line-height: 1.7;
            font-size: 1rem;
            margin-bottom: 18px;
        }

        .hero-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .hero-tag {
            background: var(--purple-light);
            color: var(--purple-dark);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 9px 14px;
            font-size: 0.92rem;
            font-weight: 700;
        }

        .hero-side {
            background: linear-gradient(180deg, #f8f5ff 0%, #efe8ff 100%);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 20px;
        }

        .hero-side-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--purple-dark);
            margin-bottom: 14px;
        }

        .hero-side-list {
            display: grid;
            gap: 12px;
        }

        .hero-side-item {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 14px;
            color: var(--text);
            font-size: 0.95rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }

        .stat-card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .stat-label {
            color: var(--text-soft);
            font-size: 0.92rem;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 1.9rem;
            font-weight: 800;
            color: var(--purple-dark);
            margin-bottom: 10px;
        }

        .stat-status {
            display: inline-block;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 7px 10px;
            border-radius: 999px;
        }

        .ok {
            background: var(--green);
            color: var(--green-text);
        }

        .warn {
            background: var(--yellow);
            color: var(--yellow-text);
        }

        .alert {
            background: var(--red);
            color: var(--red-text);
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 20px;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--purple-dark);
            margin-bottom: 16px;
        }

        .activity-list {
            display: grid;
            gap: 12px;
        }

        .activity-item {
            border: 1px solid var(--border);
            background: var(--panel-soft);
            border-radius: 16px;
            padding: 14px 16px;
        }

        .activity-item strong {
            display: block;
            margin-bottom: 4px;
            color: var(--text);
        }

        .activity-item span {
            color: var(--text-soft);
            font-size: 0.93rem;
        }

        .quick-grid {
            display: grid;
            gap: 12px;
        }

        .quick-item {
            border: 1px solid var(--border);
            background: var(--panel-soft);
            border-radius: 16px;
            padding: 16px;
        }

        .quick-item-title {
            font-weight: 800;
            margin-bottom: 6px;
            color: var(--text);
        }

        .quick-item-text {
            color: var(--text-soft);
            font-size: 0.93rem;
            line-height: 1.5;
        }

        @media (max-width: 1150px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .bottom-grid,
            .hero-card {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 980px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .topbar-right {
                justify-content: space-between;
            }
        }

        @media (max-width: 650px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .main {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="brand">
                <img src="img/logo-zypher.png" alt="Logo Zypher">
                <div class="brand-title">Zypher</div>
            </div>

            <div class="menu-section">
                <div class="menu-item active">Evaluación y refuerzo de la seguridad</div>
                <div class="menu-item">Análisis de amenazas y supervisión</div>
                <div class="menu-item">Continuidad y recuperación</div>
                <div class="menu-item">Revisión, apoyo y documentación</div>
                <div class="menu-item">Gestión, acceso e informes</div>
            </div>
        </aside>

        <main class="main">
            <div class="topbar">
                <div class="topbar-left">
                    <div class="topbar-logo">
                        <img src="img/logo-zypher.png" alt="Logo Zypher">
                        <span>Zypher</span>
                    </div>

                    <div class="topbar-info">
                        <div class="info-badge">Equipo: <?php echo htmlspecialchars($nombreEquipo, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="info-badge">Usuario: <?php echo htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>

                <div class="topbar-right">
                    <div class="notify">🔔</div>

                    <form action="/logout.php" method="POST" class="logout-form">
                        <button type="submit" class="logout-btn">Cerrar sesión</button>
                    </form>
                </div>
            </div>

            <section class="content">
                <div class="hero-card">
                    <div>
                        <div class="hero-title">Resumen general del entorno</div>
                        <p class="hero-text">
                            Aquí puedes ver de un vistazo el estado general del sistema, la actividad reciente y los accesos rápidos a las áreas principales de Zypher.
                        </p>

                        <div class="hero-tags">
                            <div class="hero-tag">Vista principal</div>
                            <div class="hero-tag">Estado general</div>
                            <div class="hero-tag">Actividad reciente</div>
                            <div class="hero-tag">Acceso rápido</div>
                        </div>
                    </div>

                    <div class="hero-side">
                        <div class="hero-side-title">Qué puedes hacer desde aquí</div>
                        <div class="hero-side-list">
                            <div class="hero-side-item">Revisar el estado general del sistema</div>
                            <div class="hero-side-item">Ver alertas y avisos recientes</div>
                            <div class="hero-side-item">Acceder rápidamente a módulos clave</div>
                        </div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Estado del sistema</div>
                        <div class="stat-value">Estable</div>
                        <span class="stat-status ok">Correcto</span>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Alertas recientes</div>
                        <div class="stat-value">3</div>
                        <span class="stat-status warn">Revisar</span>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Último análisis</div>
                        <div class="stat-value">Hoy</div>
                        <span class="stat-status ok">Actualizado</span>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Copias de seguridad</div>
                        <div class="stat-value">1</div>
                        <span class="stat-status ok">Disponible</span>
                    </div>
                </div>

                <div class="bottom-grid">
                    <div class="card">
                        <div class="card-title">Actividad reciente</div>
                        <div class="activity-list">
                            <div class="activity-item">
                                <strong>Análisis completado</strong>
                                <span>Se ha terminado una revisión general del sistema.</span>
                            </div>

                            <div class="activity-item">
                                <strong>Nueva notificación registrada</strong>
                                <span>Se ha añadido un nuevo aviso pendiente de revisión.</span>
                            </div>

                            <div class="activity-item">
                                <strong>Acceso al panel</strong>
                                <span>Has iniciado sesión correctamente en Zypher.</span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-title">Accesos rápidos</div>
                        <div class="quick-grid">
                            <div class="quick-item">
                                <div class="quick-item-title">Ver alertas</div>
                                <div class="quick-item-text">Consulta los últimos avisos y eventos importantes.</div>
                            </div>

                            <div class="quick-item">
                                <div class="quick-item-title">Revisar estado</div>
                                <div class="quick-item-text">Comprueba el estado general del entorno protegido.</div>
                            </div>

                            <div class="quick-item">
                                <div class="quick-item-title">Abrir módulos</div>
                                <div class="quick-item-text">Accede a las áreas principales del sistema desde un punto central.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
