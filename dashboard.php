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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Zypher</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-main: #040714;
            --bg-deep: #02040d;
            --bg-soft: #091128;
            --panel: rgba(10, 18, 40, 0.82);
            --panel-strong: rgba(11, 20, 46, 0.94);
            --panel-soft: rgba(255, 255, 255, 0.04);
            --line: rgba(255, 255, 255, 0.08);
            --line-strong: rgba(52, 198, 255, 0.22);
            --text: #f5f7ff;
            --text-soft: #b9c3ea;
            --text-muted: #7f8ab4;
            --blue: #34c6ff;
            --blue-2: #4d7bff;
            --purple: #8b5dff;
            --pink: #ff38c7;
            --cyan: #32f3ff;
            --green-bg: rgba(57, 217, 138, 0.14);
            --green-text: #90f1be;
            --yellow-bg: rgba(255, 196, 58, 0.16);
            --yellow-text: #ffd978;
            --red-bg: rgba(255, 92, 92, 0.14);
            --red-text: #ff9e9e;
            --blue-bg: rgba(52, 198, 255, 0.14);
            --blue-text: #8fdcff;
            --shadow-soft: 0 18px 50px rgba(0, 0, 0, 0.32);
            --shadow-strong: 0 24px 70px rgba(0, 0, 0, 0.46);
            --radius-xl: 28px;
            --radius-lg: 22px;
            --radius-md: 18px;
        }

        body {
            font-family: "Space Grotesk", Arial, sans-serif;
            background:
                radial-gradient(circle at 12% 12%, rgba(139, 93, 255, 0.16), transparent 22%),
                radial-gradient(circle at 84% 18%, rgba(52, 198, 255, 0.12), transparent 24%),
                radial-gradient(circle at 50% 50%, rgba(255, 56, 199, 0.05), transparent 30%),
                linear-gradient(180deg, #030611 0%, #050918 35%, #030611 100%);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.018) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.018) 1px, transparent 1px);
            background-size: 80px 80px;
            mask-image: linear-gradient(180deg, rgba(255,255,255,0.65), transparent 92%);
            pointer-events: none;
        }

        body::after {
            content: "";
            position: fixed;
            width: 420px;
            height: 420px;
            top: -90px;
            right: -110px;
            border-radius: 50%;
            background: rgba(52, 198, 255, 0.10);
            filter: blur(120px);
            pointer-events: none;
        }

        .layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            min-height: 100vh;
            position: relative;
            z-index: 2;
        }

        .sidebar {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01)),
                linear-gradient(180deg, rgba(8, 13, 32, 0.96), rgba(5, 9, 24, 0.96));
            border-right: 1px solid var(--line);
            padding: 24px 18px;
            backdrop-filter: blur(14px);
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 14px 24px;
            border-bottom: 1px solid var(--line);
            margin-bottom: 24px;
        }

        .brand img {
            width: 46px;
            height: 46px;
            object-fit: contain;
            filter:
                drop-shadow(0 0 12px rgba(139, 93, 255, 0.32))
                drop-shadow(0 0 18px rgba(52, 198, 255, 0.18));
        }

        .brand-title {
            font-size: 1.9rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            background: linear-gradient(90deg, #ffffff, #7ad9ff, #b988ff, #ff73da, #ffffff);
            background-size: 260% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: glowShift 5s linear infinite;
        }

        .sidebar-subtitle {
            margin: -6px 0 20px;
            padding: 0 14px;
            color: var(--text-muted);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .menu-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .menu-item {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)),
                linear-gradient(180deg, rgba(12, 20, 46, 0.96), rgba(10, 16, 38, 0.98));
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            padding: 18px 16px;
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--text);
            text-align: left;
            box-shadow: var(--shadow-soft);
            transition: 0.22s ease;
            position: relative;
            overflow: hidden;
        }

        .menu-item::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(52,198,255,0.08), transparent 40%, rgba(255,56,199,0.06));
            pointer-events: none;
        }

        .menu-item:hover {
            transform: translateY(-3px);
            border-color: var(--line-strong);
            box-shadow: var(--shadow-strong);
        }

        .main {
            padding: 28px;
        }

        .topbar {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)),
                linear-gradient(180deg, var(--panel), var(--panel-strong));
            border: 1px solid var(--line);
            border-radius: var(--radius-xl);
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-soft);
            margin-bottom: 28px;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }

        .topbar::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(52,198,255,0.07), transparent 35%, rgba(255,56,199,0.05));
            pointer-events: none;
        }

        .topbar-info {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .info-badge {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--line);
            color: var(--text);
            padding: 11px 15px;
            border-radius: 14px;
            font-size: 0.94rem;
            font-weight: 600;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.02);
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .notify {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            box-shadow: var(--shadow-soft);
        }

        .logout-form {
            margin: 0;
        }

        .logout-btn {
            border: none;
            border-radius: 14px;
            padding: 12px 18px;
            background: linear-gradient(135deg, var(--blue-2), var(--pink));
            color: #ffffff;
            font-weight: 800;
            cursor: pointer;
            box-shadow:
                0 12px 30px rgba(139, 93, 255, 0.26),
                0 0 20px rgba(52, 198, 255, 0.10);
            transition: 0.22s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
        }

        .content {
            display: grid;
            gap: 28px;
        }

        .card {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)),
                linear-gradient(180deg, var(--panel), var(--panel-strong));
            border: 1px solid var(--line);
            border-radius: var(--radius-xl);
            padding: 28px;
            box-shadow: var(--shadow-soft);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(52,198,255,0.08), transparent 35%, rgba(255,56,199,0.05));
            pointer-events: none;
        }

        .page-title {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.04em;
            background: linear-gradient(90deg, #ffffff, #7ad9ff, #b988ff, #ff73da, #ffffff);
            background-size: 260% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: glowShift 5s linear infinite;
        }

        .page-text {
            color: var(--text-soft);
            line-height: 1.8;
            font-size: 1rem;
            max-width: 920px;
        }

        .hero-stats {
            margin-top: 24px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .hero-stat {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px;
        }

        .hero-stat strong {
            display: block;
            font-size: 1.45rem;
            color: #ffffff;
            margin-bottom: 6px;
        }

        .hero-stat span {
            color: var(--text-soft);
            font-size: 0.92rem;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.02em;
        }

        .section-btn {
            text-decoration: none;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.04);
            color: var(--text);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            transition: 0.2s ease;
            box-shadow: var(--shadow-soft);
        }

        .section-btn:hover {
            transform: translateY(-2px);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 18px;
        }

        .metric-box {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)),
                linear-gradient(180deg, rgba(12, 20, 46, 0.96), rgba(10, 16, 38, 0.98));
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--shadow-soft);
            transition: 0.22s ease;
        }

        .metric-box:hover {
            transform: translateY(-4px);
            border-color: var(--line-strong);
        }

        .metric-label {
            color: var(--text-soft);
            font-size: 0.92rem;
            margin-bottom: 8px;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.3;
            margin-bottom: 8px;
        }

        .metric-sub {
            color: var(--text-muted);
            font-size: 0.88rem;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            max-width: 980px;
        }

        .status-box {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)),
                linear-gradient(180deg, rgba(12, 20, 46, 0.96), rgba(10, 16, 38, 0.98));
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--shadow-soft);
        }

        .status-label {
            color: var(--text-soft);
            font-size: 0.92rem;
            margin-bottom: 8px;
        }

        .status-value {
            font-size: 1.12rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.45;
        }

        .status-tag,
        .severity-tag {
            display: inline-block;
            margin-top: 10px;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 0.84rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .tag-ok,
        .severity-low {
            background: var(--green-bg);
            color: var(--green-text);
        }

        .tag-warn,
        .severity-medium {
            background: var(--yellow-bg);
            color: var(--yellow-text);
        }

        .tag-alert,
        .severity-high {
            background: var(--red-bg);
            color: var(--red-text);
        }

        .tag-info {
            background: var(--blue-bg);
            color: var(--blue-text);
        }

        .mini-btn {
            display: inline-block;
            margin-top: 12px;
            text-decoration: none;
            border-radius: 12px;
            padding: 10px 12px;
            background: linear-gradient(135deg, var(--blue-2), var(--pink));
            color: #ffffff;
            font-size: 0.88rem;
            font-weight: 700;
            box-shadow: 0 10px 20px rgba(111, 78, 214, 0.16);
        }

        .double-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 28px;
        }

        .list {
            display: grid;
            gap: 14px;
        }

        .list-item {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)),
                linear-gradient(180deg, rgba(12, 20, 46, 0.96), rgba(10, 16, 38, 0.98));
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            box-shadow: var(--shadow-soft);
        }

        .list-item-left strong {
            display: block;
            margin-bottom: 5px;
            color: #ffffff;
        }

        .list-item-left span {
            color: var(--text-soft);
            font-size: 0.93rem;
            line-height: 1.55;
        }

        .activity-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .activity-box {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)),
                linear-gradient(180deg, rgba(12, 20, 46, 0.96), rgba(10, 16, 38, 0.98));
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px;
            box-shadow: var(--shadow-soft);
        }

        .activity-label {
            color: var(--text-soft);
            font-size: 0.92rem;
            margin-bottom: 8px;
        }

        .activity-value {
            font-size: 1.15rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .activity-text {
            color: var(--text-soft);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .progress-panel {
            display: grid;
            gap: 16px;
        }

        .progress-item {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)),
                linear-gradient(180deg, rgba(12, 20, 46, 0.96), rgba(10, 16, 38, 0.98));
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px;
        }

        .progress-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            color: #ffffff;
            font-weight: 700;
        }

        .progress-bar {
            height: 10px;
            background: rgba(255,255,255,0.06);
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--blue), var(--pink));
        }

        .progress-fill.cis {
            width: 72%;
        }

        .progress-fill.hardening {
            width: 61%;
        }

        .progress-fill.backup {
            width: 88%;
        }

        .progress-fill.monitoring {
            width: 79%;
        }

        @keyframes glowShift {
            0% { background-position: 0% center; }
            100% { background-position: 260% center; }
        }

        @media (max-width: 1280px) {
            .metrics-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .double-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1100px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                height: auto;
                position: relative;
                border-right: none;
                border-bottom: 1px solid var(--line);
            }
        }

        @media (max-width: 900px) {
            .hero-stats,
            .metrics-grid,
            .status-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 750px) {
            .hero-stats,
            .metrics-grid,
            .status-grid {
                grid-template-columns: 1fr;
            }

            .main {
                padding: 16px;
            }

            .card {
                padding: 20px;
            }

            .page-title {
                font-size: 1.8rem;
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

            <p class="sidebar-subtitle">
                Centro de control para seguridad, análisis, refuerzo y supervisión del equipo.
            </p>

            <div class="menu-section">
                <div class="menu-item">Evaluación y refuerzo de la seguridad</div>
                <div class="menu-item">Análisis de amenazas y supervisión</div>
                <div class="menu-item">Continuidad y recuperación</div>
                <div class="menu-item">Revisión, apoyo y documentación</div>
                <div class="menu-item">Gestión, acceso e informes</div>
            </div>
        </aside>

        <main class="main">
            <div class="topbar">
                <div class="topbar-info">
                    <div class="info-badge">Equipo: <?php echo htmlspecialchars($nombreEquipo, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="info-badge">Usuario: <?php echo htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="info-badge">Sesión activa</div>
                </div>

                <div class="topbar-actions">
                    <div class="notify">🔔</div>

                    <form action="/logout.php" method="POST" class="logout-form">
                        <button type="submit" class="logout-btn">Cerrar sesión</button>
                    </form>
                </div>
            </div>

            <section class="content">
                <div class="card">
                    <div class="page-title">Centro de control</div>
                    <p class="page-text">
                        Desde aquí puedes ver de un vistazo el estado general del sistema, vulnerabilidades detectadas,
                        cumplimiento de políticas, actividad reciente y avisos importantes del entorno.
                    </p>

                    <div class="hero-stats">
                        <div class="hero-stat">
                            <strong>12</strong>
                            <span>Vulnerabilidades detectadas</span>
                        </div>
                        <div class="hero-stat">
                            <strong>87/120</strong>
                            <span>Políticas CIS cumplidas</span>
                        </div>
                        <div class="hero-stat">
                            <strong>3</strong>
                            <span>Módulos activos</span>
                        </div>
                        <div class="hero-stat">
                            <strong>24h</strong>
                            <span>Ventana de actividad visible</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-head">
                        <div class="section-title">Estado general</div>
                    </div>

                    <div class="status-grid">
                        <div class="status-box">
                            <div class="status-label">Nombre del equipo</div>
                            <div class="status-value"><?php echo htmlspecialchars($nombreEquipo, ENT_QUOTES, 'UTF-8'); ?></div>
                            <span class="status-tag tag-info">Hostname</span>
                        </div>

                        <div class="status-box">
                            <div class="status-label">Estado del sistema</div>
                            <div class="status-value">En riesgo</div>
                            <span class="status-tag tag-warn">Requiere revisión</span>
                        </div>

                        <div class="status-box">
                            <div class="status-label">Protecciones activas</div>
                            <div class="status-value">3 módulos</div>
                            <span class="status-tag tag-ok">Operativas</span>
                        </div>

                        <div class="status-box">
                            <div class="status-label">Estado vulnerabilidades</div>
                            <div class="status-value">12 detectadas</div>
                            <span class="status-tag tag-alert">2 críticas</span>
                            <a href="#" class="mini-btn">Ver todas las vulnerabilidades</a>
                        </div>

                        <div class="status-box">
                            <div class="status-label">Estado CIS</div>
                            <div class="status-value">87 / 120</div>
                            <span class="status-tag tag-ok">Cumplidas</span>
                            <a href="#" class="mini-btn">Ver todas las políticas</a>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-head">
                        <div class="section-title">Indicadores rápidos</div>
                    </div>

                    <div class="metrics-grid">
                        <div class="metric-box">
                            <div class="metric-label">Alertas abiertas</div>
                            <div class="metric-value">7</div>
                            <div class="metric-sub">Pendientes de revisión</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Eventos hoy</div>
                            <div class="metric-value">128</div>
                            <div class="metric-sub">Actividad registrada</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Backups</div>
                            <div class="metric-value">OK</div>
                            <div class="metric-sub">Última copia correcta</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Cumplimiento</div>
                            <div class="metric-value">72%</div>
                            <div class="metric-sub">Nivel actual CIS</div>
                        </div>

                        <div class="metric-box">
                            <div class="metric-label">Riesgo global</div>
                            <div class="metric-value">Medio</div>
                            <div class="metric-sub">Estado general actual</div>
                        </div>
                    </div>
                </div>

                <div class="double-grid">
                    <div class="card">
                        <div class="section-head">
                            <div class="section-title">Notificaciones recientes</div>
                            <a href="#" class="section-btn">Ver todas</a>
                        </div>

                        <div class="list">
                            <div class="list-item">
                                <div class="list-item-left">
                                    <strong>Evento de seguridad detectado</strong>
                                    <span>Se ha registrado una notificación reciente dentro de las últimas 24 horas.</span>
                                </div>
                                <span class="severity-tag severity-low">Leve</span>
                            </div>

                            <div class="list-item">
                                <div class="list-item-left">
                                    <strong>Cambio relevante en el sistema</strong>
                                    <span>Se ha detectado una modificación o aviso que requiere revisión.</span>
                                </div>
                                <span class="severity-tag severity-medium">Crítico</span>
                            </div>

                            <div class="list-item">
                                <div class="list-item-left">
                                    <strong>Incidencia importante registrada</strong>
                                    <span>Se ha generado una notificación de alta prioridad en el entorno.</span>
                                </div>
                                <span class="severity-tag severity-high">Muy crítico</span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="section-head">
                            <div class="section-title">Progreso de módulos</div>
                        </div>

                        <div class="progress-panel">
                            <div class="progress-item">
                                <div class="progress-head">
                                    <span>Políticas CIS</span>
                                    <span>72%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill cis"></div>
                                </div>
                            </div>

                            <div class="progress-item">
                                <div class="progress-head">
                                    <span>Hardening</span>
                                    <span>61%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill hardening"></div>
                                </div>
                            </div>

                            <div class="progress-item">
                                <div class="progress-head">
                                    <span>Copias de seguridad</span>
                                    <span>88%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill backup"></div>
                                </div>
                            </div>

                            <div class="progress-item">
                                <div class="progress-head">
                                    <span>Monitorización</span>
                                    <span>79%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill monitoring"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-head">
                        <div class="section-title">Actividad reciente</div>
                    </div>

                    <div class="activity-grid">
                        <div class="activity-box">
                            <div class="activity-label">Último módulo visitado</div>
                            <div class="activity-value">Análisis de amenazas</div>
                            <div class="activity-text">Último acceso registrado dentro del panel.</div>
                        </div>

                        <div class="activity-box">
                            <div class="activity-label">Último análisis ejecutado</div>
                            <div class="activity-value">Revisión general</div>
                            <div class="activity-text">Último análisis realizado dentro de Zypher.</div>
                        </div>

                        <div class="activity-box">
                            <div class="activity-label">Última copia de seguridad</div>
                            <div class="activity-value">Hoy a las 10:30</div>
                            <div class="activity-text">Última copia de seguridad registrada en la plataforma.</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
