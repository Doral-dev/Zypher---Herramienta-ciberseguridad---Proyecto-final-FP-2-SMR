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
    <title>Dashboard Inicio - Zypher</title>
    <link rel="stylesheet" href="/css/dashboard.css">
</head>
<body>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <div class="dashboard-main" id="dashboardMain">
        <?php require_once __DIR__ . '/includes/topbar.php'; ?>

        <main class="main">
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
                            <a href="/vulnerabilidades.php" class="mini-btn">Ver todas las vulnerabilidades</a>
                        </div>

                        <div class="status-box">
                            <div class="status-label">Estado CIS</div>
                            <div class="status-value">87 / 120</div>
                            <span class="status-tag tag-ok">Cumplidas</span>
                            <a href="/cis.php" class="mini-btn">Ver todas las políticas</a>
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
</div>

<script src="/js/menu.js"></script>
</body>
</html>
