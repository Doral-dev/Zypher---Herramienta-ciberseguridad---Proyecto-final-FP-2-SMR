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
            --bg: #f4f1ff;
            --panel: #ffffff;
            --panel-soft: #f8f6ff;
            --border: #d9d0f3;
            --text: #2f2550;
            --text-soft: #6e6492;
            --purple: #6f4ed6;
            --purple-dark: #4b2cab;
            --purple-light: #ece6ff;
            --shadow: 0 14px 30px rgba(75, 44, 171, 0.10);
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: linear-gradient(180deg, #f8f6ff 0%, #efeaff 100%);
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
            font-size: 1.25rem;
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

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 24px;
            box-shadow: var(--shadow);
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 800;
            margin-bottom: 16px;
            color: var(--purple-dark);
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 10px;
            color: var(--purple-dark);
        }

        .welcome-text {
            color: var(--text-soft);
            line-height: 1.7;
            font-size: 1rem;
        }

        .notifications-list {
            padding-left: 18px;
            color: var(--text);
            line-height: 1.9;
        }

        .modules-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .module-box {
            background: var(--panel-soft);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px;
            text-align: center;
            font-weight: 700;
            color: var(--text);
        }

        @media (max-width: 1050px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .modules-row {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .topbar-right {
                justify-content: space-between;
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
                <div class="menu-item active">Inicio</div>
                <div class="menu-item">Evaluación y refuerzo de la seguridad</div>
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
                <div class="card">
                    <div class="welcome-title">Inicio</div>
                    <p class="welcome-text">
                        Zypher es una plataforma de ciberseguridad pensada para analizar, reforzar y supervisar la seguridad básica del sistema de forma simple e intuitiva.
                    </p>
                </div>

                <div class="card">
                    <div class="card-title">Notificaciones</div>
                    <ul class="notifications-list">
                        <li>Aviso o evento reciente 1</li>
                        <li>Aviso o evento reciente 2</li>
                        <li>Aviso o evento reciente 3</li>
                    </ul>
                </div>

                <div class="card">
                    <div class="card-title">Módulos usados recientemente</div>
                    <div class="modules-row">
                        <div class="module-box">Módulo 1</div>
                        <div class="module-box">Módulo 2</div>
                        <div class="module-box">Módulo 3</div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
