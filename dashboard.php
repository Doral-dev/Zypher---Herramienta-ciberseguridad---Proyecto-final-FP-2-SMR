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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-main: #f6f3ff;
            --panel: #ffffff;
            --panel-soft: #f8f5ff;
            --border: #ddd4f5;
            --text: #2f2550;
            --text-soft: #6f6791;
            --purple: #6f4ed6;
            --purple-dark: #4b2cab;
            --purple-light: #ede7ff;
            --green-bg: #dff6e8;
            --green-text: #247a46;
            --yellow-bg: #fff4d8;
            --yellow-text: #9a6a00;
            --red-bg: #ffe1e1;
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
            gap: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
            flex-wrap: wrap;
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

        .topbar-actions {
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
            font-size: 1.15rem;
        }

        .logout-form {
            margin: 0;
        }

        .logout-btn {
            border: none;
            border-radius: 14px;
            padding: 12px 18px;
            background: linear-gradient(180deg, #8a6df0 0%, #6f4ed6 100%);
            color: #ffffff;
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

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--purple-dark);
            margin-bottom: 10px;
        }

        .page-text {
            color: var(--text-soft);
            line-height: 1.7;
            font-size: 1rem;
        }

        .section-title {
            font-size: 1.08rem;
            font-weight: 800;
            color: var(--purple-dark);
            margin-bottom: 16px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 18px;
        }

        .status-box {
            background: var(--panel-soft);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px;
        }

        .status-label {
            color: var(--text-soft);
            font-size: 0.92rem;
            margin-bottom: 8px;
        }

        .status-value {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--purple-dark);
            line-height: 1.4;
        }

        .status-tag {
            display: inline-block;
            margin-top: 10px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 0.84rem;
            font-weight: 700;
        }

        .tag-ok {
            background: var(--green-bg);
            color: var(--green-text);
        }

        .tag-warn {
            background: var(--yellow-bg);
            color: var(--yellow-text);
        }

        .tag-alert {
            background: var(--red-bg);
            color: var(--red-text);
        }

        .list {
            display: grid;
            gap: 12px;
        }

        .list-item {
            background: var(--panel-soft);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .list-item-left strong {
            display: block;
            margin-bottom: 4px;
            color: var(--text);
        }

        .list-item-left span {
            color: var(--text-soft);
            font-size: 0.93rem;
            line-height: 1.5;
        }

        .severity-tag {
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 0.84rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .severity-low {
            background: var(--green-bg);
            color: var(--green-text);
        }

        .severity-medium {
            background: var(--yellow-bg);
            color: var(--yellow-text);
        }

        .severity-high {
            background: var(--red-bg);
            color: var(--red-text);
        }

        .activity-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .activity-box {
            background: var(--panel-soft);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px;
        }

        .activity-label {
            color: var(--text-soft);
            font-size: 0.92rem;
            margin-bottom: 8px;
        }

        .activity-value {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--purple-dark);
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .activity-text {
            color: var(--text-soft);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        @media (max-width: 1300px) {
            .status-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 1100px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .activity-grid,
            .status-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 750px) {
            .status-grid,
            .activity-grid {
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
                    <div class="page-title">Resumen general</div>
                    <p class="page-text">
                        La sección Inicio mostrará de forma resumida el estado general del sistema, notificaciones recientes y la actividad más importante realizada recientemente dentro de Zypher.
                    </p>
                </div>

                <div class="card">
                    <div class="section-title">1. Estado general</div>
                    <div class="status-grid">
                        <div class="status-box">
                            <div class="status-label">Nombre del equipo</div>
                            <div class="status-value"><?php echo htmlspecialchars($nombreEquipo, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>

                        <div class="status-box">
                            <div class="status-label">Estado del sistema</div>
                            <div class="status-value">En riesgo</div>
                            <span class="status-tag tag-warn">En riesgo</span>
                        </div>

                        <div class="status-box">
                            <div class="status-label">Protecciones activas</div>
                            <div class="status-value">3 módulos</div>
                        </div>

                        <div class="status-box">
                            <div class="status-label">Estado vulnerabilidades</div>
                            <div class="status-value">12 detectadas</div>
                            <span class="status-tag tag-alert">2 críticas</span>
                        </div>

                        <div class="status-box">
                            <div class="status-label">Estado CIS</div>
                            <div class="status-value">87 / 120</div>
                            <span class="status-tag tag-ok">Cumplidas</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-title">2. Notificaciones recientes (últimas 24 horas)</div>
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
                    <div class="section-title">3. Actividad reciente (últimas 24 horas)</div>
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
