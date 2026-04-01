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
            --bg-main: #f3efff;
            --bg-soft: #fbf9ff;
            --panel: rgba(255, 255, 255, 0.88);
            --panel-strong: #ffffff;
            --panel-soft: #f7f3ff;
            --border: rgba(111, 78, 214, 0.16);
            --text: #2f2550;
            --text-soft: #6f6791;
            --purple: #6f4ed6;
            --purple-dark: #4b2cab;
            --purple-soft: #8f74ea;
            --purple-light: #ede7ff;
            --blue-soft: #e8f2ff;
            --blue-text: #225caa;
            --green-bg: #def8e8;
            --green-text: #1f8a4c;
            --yellow-bg: #fff4d6;
            --yellow-text: #a16b00;
            --red-bg: #ffe3e3;
            --red-text: #bf2d2d;
            --shadow-soft: 0 16px 34px rgba(76, 49, 168, 0.10);
            --shadow-strong: 0 20px 44px rgba(76, 49, 168, 0.16);
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(circle at 12% 18%, rgba(143, 116, 234, 0.18), transparent 18%),
                radial-gradient(circle at 88% 14%, rgba(111, 78, 214, 0.12), transparent 18%),
                linear-gradient(180deg, #fcfbff 0%, #f2edff 100%);
            color: var(--text);
            min-height: 100vh;
        }

        .layout {
            display: grid;
            grid-template-columns: 290px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
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
            width: 44px;
            height: 44px;
            object-fit: contain;
            filter: drop-shadow(0 8px 18px rgba(111, 78, 214, 0.18));
        }

        .brand-title {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--purple-dark);
        }

        .menu-section {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .menu-item {
            background: linear-gradient(180deg, rgba(255,255,255,0.94) 0%, rgba(247,243,255,0.95) 100%);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px 16px;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            text-align: center;
            box-shadow: var(--shadow-soft);
            transition: 0.2s ease;
        }

        .menu-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-strong);
        }

        .main {
            padding: 28px;
        }

        .topbar {
            background: rgba(255, 255, 255, 0.90);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-soft);
            margin-bottom: 28px;
            flex-wrap: wrap;
        }

        .topbar-info {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .info-badge {
            background: linear-gradient(180deg, #fbf9ff 0%, #f2edff 100%);
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
            background: linear-gradient(180deg, #fbf9ff 0%, #f2edff 100%);
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
            background: linear-gradient(180deg, #8a6df0 0%, #6f4ed6 100%);
            color: #ffffff;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 12px 24px rgba(111, 78, 214, 0.18);
            transition: 0.2s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
        }

        .content {
            display: grid;
            gap: 28px;
        }

        .card {
            background: rgba(255, 255, 255, 0.90);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 26px;
            box-shadow: var(--shadow-soft);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--purple-dark);
            margin-bottom: 12px;
        }

        .page-text {
            color: var(--text-soft);
            line-height: 1.75;
            font-size: 1rem;
            max-width: 900px;
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
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--purple-dark);
        }

        .section-btn {
            text-decoration: none;
            border: 1px solid rgba(111, 78, 214, 0.18);
            background: linear-gradient(180deg, #f8f4ff 0%, #efe8ff 100%);
            color: var(--purple-dark);
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

        .status-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 18px;
        }

        .status-box {
            background: linear-gradient(180deg, rgba(255,255,255,0.95) 0%, rgba(247,243,255,0.95) 100%);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px;
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
            color: var(--purple-dark);
            line-height: 1.45;
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

        .tag-info {
            background: var(--blue-soft);
            color: var(--blue-text);
        }

        .list {
            display: grid;
            gap: 14px;
        }

        .list-item {
            background: linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(247,243,255,0.96) 100%);
            border: 1px solid var(--border);
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
            color: var(--text);
        }

        .list-item-left span {
            color: var(--text-soft);
            font-size: 0.93rem;
            line-height: 1.55;
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
            background: linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(247,243,255,0.96) 100%);
            border: 1px solid var(--border);
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
            color: var(--purple-dark);
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .activity-text {
            color: var(--text-soft);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .mini-btn {
            display: inline-block;
            margin-top: 12px;
            text-decoration: none;
            border-radius: 12px;
            padding: 10px 12px;
            background: linear-gradient(180deg, #8a6df0 0%, #6f4ed6 100%);
            color: #ffffff;
            font-size: 0.88rem;
            font-weight: 700;
            box-shadow: 0 10px 20px rgba(111, 78, 214, 0.16);
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

            .card {
                padding: 20px;
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
                        La sección Inicio muestra de forma resumida el estado general del sistema, notificaciones recientes y la actividad más importante realizada recientemente dentro de Zypher.
                    </p>
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
                            <span class="status-tag tag-warn">En riesgo</span>
                        </div>

                        <div class="status-box">
                            <div class="status-label">Protecciones activas</div>
                            <div class="status-value">3 módulos</div>
                            <span class="status-tag tag-ok">Activas</span>
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
                        <div class="section-title">Notificaciones recientes (últimas 24 horas)</div>
                        <a href="#" class="section-btn">Ver todas las notificaciones</a>
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
                        <div class="section-title">Actividad reciente (últimas 24 horas)</div>
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
