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

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .summary-box {
            background: var(--panel-soft);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px;
        }

        .summary-label {
            color: var(--text-soft);
            font-size: 0.92rem;
            margin-bottom: 8px;
        }

        .summary-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--purple-dark);
            margin-bottom: 10px;
        }

        .summary-status {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 0.84rem;
            font-weight: 700;
        }

        .ok {
            background: var(--green);
            color: var(--green-text);
        }

        .warn {
            background: var(--yellow);
            color: var(--yellow-text);
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
        }

        .list-item strong {
            display: block;
            margin-bottom: 4px;
            color: var(--text);
        }

        .list-item span {
            color: var(--text-soft);
            font-size: 0.93rem;
            line-height: 1.5;
        }

        @media (max-width: 1000px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .summary-grid {
                grid-template-columns: 1fr;
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
                        Aquí puedes ver el estado general del entorno, la actividad más reciente y los avisos principales de Zypher en una sola vista.
                    </p>
                </div>

                <div class="card">
                    <div class="section-title">Estado general</div>
                    <div class="summary-grid">
                        <div class="summary-box">
                            <div class="summary-label">Estado del sistema</div>
                            <div class="summary-value">Estable</div>
                            <span class="summary-status ok">Correcto</span>
                        </div>

                        <div class="summary-box">
                            <div class="summary-label">Alertas recientes</div>
                            <div class="summary-value">3</div>
                            <span class="summary-status warn">Revisar</span>
                        </div>

                        <div class="summary-box">
                            <div class="summary-label">Última revisión</div>
                            <div class="summary-value">Hoy</div>
                            <span class="summary-status ok">Actualizado</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-title">Actividad reciente</div>
                    <div class="list">
                        <div class="list-item">
                            <strong>Análisis completado</strong>
                            <span>Se ha terminado una revisión general del sistema.</span>
                        </div>

                        <div class="list-item">
                            <strong>Nueva notificación registrada</strong>
                            <span>Se ha añadido un nuevo aviso pendiente de revisión.</span>
                        </div>

                        <div class="list-item">
                            <strong>Acceso al panel</strong>
                            <span>Se ha iniciado sesión correctamente en Zypher.</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-title">Avisos principales</div>
                    <div class="list">
                        <div class="list-item">
                            <strong>Revisión recomendada</strong>
                            <span>Hay elementos pendientes de comprobar en el entorno.</span>
                        </div>

                        <div class="list-item">
                            <strong>Estado operativo</strong>
                            <span>La plataforma está disponible y funcionando con normalidad.</span>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
