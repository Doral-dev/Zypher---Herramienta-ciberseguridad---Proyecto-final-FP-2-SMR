<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

$AGENTE_ID = 'windows-agent-001';
$error = '';
$ordenAutoAbrir = isset($_GET['orden']) ? (int)$_GET['orden'] : 0;

$artefactosTexto = <<<'TXT'
Windows.Analysis.EvidenceOfDownload
Windows.Applications.ChocolateyPackages
Windows.Applications.Chrome.Extensions
Windows.Applications.Chrome.History
Windows.Applications.Edge.History
Windows.Applications.Firefox.Downloads
Windows.Applications.Firefox.History
Windows.Applications.IISLogs
Windows.Applications.TeamViewer.Incoming
Windows.Attack.ParentProcess
Windows.Attack.Prefetch
Windows.Attack.UnexpectedImagePath
Windows.Detection.Amcache
Windows.Detection.BinaryRename
Windows.Detection.EnvironmentVariables
Windows.Detection.Impersonation
Windows.Detection.ProcessCreation
Windows.Detection.PsexecService
Windows.Detection.Registry
Windows.Detection.Thumbdrives.List
Windows.Detection.Usn
Windows.Detection.WMIProcessCreation
Windows.EventLogs.AlternateLogon
Windows.EventLogs.Cleared
Windows.EventLogs.DHCP
Windows.EventLogs.Evtx
Windows.EventLogs.ExplicitLogon
Windows.EventLogs.Modifications
Windows.EventLogs.PowershellModule
Windows.EventLogs.PowershellScriptblock
Windows.EventLogs.RDPAuth
Windows.EventLogs.ScheduledTasks
Windows.EventLogs.ServiceCreationComspec
Windows.Forensics.Amcache
Windows.Forensics.Bam
Windows.Forensics.CertUtil
Windows.Forensics.FilenameSearch
Windows.Forensics.JumpLists
Windows.Forensics.Lnk
Windows.Forensics.Prefetch
Windows.Forensics.RDPCache
Windows.Forensics.RecentApps
Windows.Forensics.RecycleBin
Windows.Forensics.Shellbags
Windows.Forensics.UserAccessLogs
Windows.Network.ArpCache
Windows.Network.InterfaceAddresses
Windows.Network.ListeningPorts
Windows.Network.Netstat
Windows.Network.NetstatEnriched
Windows.Persistence.PermanentWMIEvents
Windows.Persistence.PowershellProfile
Windows.Persistence.PowershellRegistry
Windows.Registry.AppCompatCache
Windows.Registry.MountPoints2
Windows.Registry.PortProxy
Windows.Registry.PuttyHostKeys
Windows.Registry.RDP
Windows.Registry.RecentDocs
Windows.Registry.UserAssist
Windows.Registry.WDigest
Windows.Search.FileFinder
Windows.Sys.AllUsers
Windows.Sys.AppcompatShims
Windows.Sys.CertificateAuthorities
Windows.Sys.DiskInfo
Windows.Sys.Drivers
Windows.Sys.FirewallRules
Windows.Sys.Interfaces
Windows.Sys.Programs
Windows.Sys.StartupItems
Windows.Sys.Users
Windows.System.AuditPolicy
Windows.System.CriticalServices
Windows.System.DLLs
Windows.System.DNSCache
Windows.System.DomainRole
Windows.System.Handles
Windows.System.HostsFile
Windows.System.LocalAdmins
Windows.System.Powershell.ModuleAnalysisCache
Windows.System.Powershell.PSReadline
Windows.System.Pslist
Windows.System.RootCAStore
Windows.System.SVCHost
Windows.System.Services
Windows.System.Shares
Windows.System.Signers
Windows.System.TaskScheduler
Windows.System.Threads
Windows.System.UntrustedBinaries
Windows.System.WMIQuery
Windows.Timeline.Prefetch
Windows.Timeline.Registry.RunMRU
TXT;

function obtenerCategoria(string $artefacto): string
{
    if (str_contains($artefacto, '.Network.')) return 'Red';
    if (str_contains($artefacto, '.EventLogs.') || str_contains($artefacto, '.Events.')) return 'Eventos Windows';
    if (str_contains($artefacto, '.Persistence.')) return 'Persistencia';
    if (str_contains($artefacto, '.Forensics.') || str_contains($artefacto, '.Timeline.')) return 'Forense básico';
    if (str_contains($artefacto, '.Detection.') || str_contains($artefacto, '.Attack.') || str_contains($artefacto, '.Analysis.')) return 'Detección';
    if (str_contains($artefacto, '.Applications.')) return 'Aplicaciones';
    if (str_contains($artefacto, '.Registry.')) return 'Registro';
    if (str_contains($artefacto, '.Search.')) return 'Búsqueda';
    if (str_contains($artefacto, '.Sys.') || str_contains($artefacto, '.System.')) return 'Sistema';

    return 'Otros';
}

function separarCamelCase(string $texto): string
{
    $texto = preg_replace('/(?<!^)([A-Z])/', ' $1', $texto);
    $texto = str_replace(
        ['R D P', 'D N S', 'W M I', 'D L Ls', 'P S Readline', 'S V C Host', 'U S N', 'D H C P', 'E V T X'],
        ['RDP', 'DNS', 'WMI', 'DLLs', 'PSReadline', 'SVCHost', 'USN', 'DHCP', 'EVTX'],
        (string)$texto
    );

    return trim((string)$texto);
}

function nombreBonito(string $artefacto): string
{
    $nombres = [
        'Windows.Analysis.EvidenceOfDownload' => 'Evidencias de descarga',
        'Windows.Applications.ChocolateyPackages' => 'Paquetes Chocolatey',
        'Windows.Applications.Chrome.Extensions' => 'Extensiones Chrome',
        'Windows.Applications.Chrome.History' => 'Historial Chrome',
        'Windows.Applications.Edge.History' => 'Historial Edge',
        'Windows.Applications.Firefox.Downloads' => 'Descargas Firefox',
        'Windows.Applications.Firefox.History' => 'Historial Firefox',
        'Windows.Applications.IISLogs' => 'Logs IIS',
        'Windows.Applications.TeamViewer.Incoming' => 'Conexiones TeamViewer',
        'Windows.Attack.ParentProcess' => 'Procesos con padre sospechoso',
        'Windows.Attack.Prefetch' => 'Prefetch sospechoso',
        'Windows.Attack.UnexpectedImagePath' => 'Ruta de imagen inesperada',
        'Windows.Detection.Amcache' => 'Detección Amcache',
        'Windows.Detection.BinaryRename' => 'Binarios renombrados',
        'Windows.Detection.EnvironmentVariables' => 'Variables de entorno',
        'Windows.Detection.Impersonation' => 'Impersonación',
        'Windows.Detection.ProcessCreation' => 'Creación de procesos',
        'Windows.Detection.PsexecService' => 'Servicio PsExec',
        'Windows.Detection.Registry' => 'Registro sospechoso',
        'Windows.Detection.Thumbdrives.List' => 'USB conectados',
        'Windows.Detection.Usn' => 'Actividad USN',
        'Windows.Detection.WMIProcessCreation' => 'Procesos creados por WMI',
        'Windows.EventLogs.AlternateLogon' => 'Inicios de sesión alternativos',
        'Windows.EventLogs.Cleared' => 'Logs borrados',
        'Windows.EventLogs.DHCP' => 'Eventos DHCP',
        'Windows.EventLogs.Evtx' => 'Eventos EVTX',
        'Windows.EventLogs.ExplicitLogon' => 'Uso de credenciales explícitas',
        'Windows.EventLogs.Modifications' => 'Cambios en logs',
        'Windows.EventLogs.PowershellModule' => 'PowerShell Module Logs',
        'Windows.EventLogs.PowershellScriptblock' => 'PowerShell ScriptBlock',
        'Windows.EventLogs.RDPAuth' => 'Autenticaciones RDP',
        'Windows.EventLogs.ScheduledTasks' => 'Eventos de tareas programadas',
        'Windows.EventLogs.ServiceCreationComspec' => 'Servicios creados con ComSpec',
        'Windows.Forensics.Amcache' => 'Amcache forense',
        'Windows.Forensics.Bam' => 'BAM',
        'Windows.Forensics.CertUtil' => 'Uso de CertUtil',
        'Windows.Forensics.FilenameSearch' => 'Búsqueda por nombre de archivo',
        'Windows.Forensics.JumpLists' => 'Jump Lists',
        'Windows.Forensics.Lnk' => 'Archivos LNK',
        'Windows.Forensics.Prefetch' => 'Prefetch forense',
        'Windows.Forensics.RDPCache' => 'Caché RDP',
        'Windows.Forensics.RecentApps' => 'Aplicaciones recientes',
        'Windows.Forensics.RecycleBin' => 'Papelera de reciclaje',
        'Windows.Forensics.Shellbags' => 'Shellbags',
        'Windows.Forensics.UserAccessLogs' => 'User Access Logs',
        'Windows.Network.ArpCache' => 'Caché ARP',
        'Windows.Network.InterfaceAddresses' => 'Interfaces de red',
        'Windows.Network.ListeningPorts' => 'Puertos en escucha',
        'Windows.Network.Netstat' => 'Conexiones activas',
        'Windows.Network.NetstatEnriched' => 'Conexiones activas enriquecidas',
        'Windows.Persistence.PermanentWMIEvents' => 'Persistencia WMI',
        'Windows.Persistence.PowershellProfile' => 'Perfil PowerShell',
        'Windows.Persistence.PowershellRegistry' => 'PowerShell en registro',
        'Windows.Registry.AppCompatCache' => 'AppCompatCache',
        'Windows.Registry.MountPoints2' => 'Dispositivos montados',
        'Windows.Registry.PortProxy' => 'PortProxy',
        'Windows.Registry.PuttyHostKeys' => 'Claves PuTTY',
        'Windows.Registry.RDP' => 'Registro RDP',
        'Windows.Registry.RecentDocs' => 'Documentos recientes',
        'Windows.Registry.UserAssist' => 'UserAssist',
        'Windows.Registry.WDigest' => 'WDigest',
        'Windows.Search.FileFinder' => 'Buscar archivos',
        'Windows.Sys.AllUsers' => 'Todos los usuarios',
        'Windows.Sys.AppcompatShims' => 'AppCompat Shims',
        'Windows.Sys.CertificateAuthorities' => 'Autoridades certificadoras',
        'Windows.Sys.DiskInfo' => 'Información de discos',
        'Windows.Sys.Drivers' => 'Drivers',
        'Windows.Sys.FirewallRules' => 'Reglas firewall',
        'Windows.Sys.Interfaces' => 'Interfaces del sistema',
        'Windows.Sys.Programs' => 'Programas instalados',
        'Windows.Sys.StartupItems' => 'Elementos de inicio',
        'Windows.Sys.Users' => 'Usuarios locales',
        'Windows.System.AuditPolicy' => 'Política de auditoría',
        'Windows.System.CriticalServices' => 'Servicios críticos',
        'Windows.System.DLLs' => 'DLLs cargadas',
        'Windows.System.DNSCache' => 'Caché DNS',
        'Windows.System.DomainRole' => 'Rol de dominio',
        'Windows.System.Handles' => 'Handles abiertos',
        'Windows.System.HostsFile' => 'Archivo hosts',
        'Windows.System.LocalAdmins' => 'Administradores locales',
        'Windows.System.Powershell.ModuleAnalysisCache' => 'Caché de módulos PowerShell',
        'Windows.System.Powershell.PSReadline' => 'Historial PSReadline',
        'Windows.System.Pslist' => 'Procesos activos',
        'Windows.System.RootCAStore' => 'Certificados raíz',
        'Windows.System.SVCHost' => 'Servicios SVCHost',
        'Windows.System.Services' => 'Servicios',
        'Windows.System.Shares' => 'Recursos compartidos',
        'Windows.System.Signers' => 'Firmantes del sistema',
        'Windows.System.TaskScheduler' => 'Tareas programadas',
        'Windows.System.Threads' => 'Hilos del sistema',
        'Windows.System.UntrustedBinaries' => 'Binarios no confiables',
        'Windows.System.WMIQuery' => 'Consultas WMI',
        'Windows.Timeline.Prefetch' => 'Línea temporal Prefetch',
        'Windows.Timeline.Registry.RunMRU' => 'RunMRU',
    ];

    if (isset($nombres[$artefacto])) {
        return $nombres[$artefacto];
    }

    $partes = explode('.', $artefacto);
    $ultimas = array_slice($partes, -2);

    return separarCamelCase(implode(' ', $ultimas));
}

function descripcionArtefacto(string $artefacto): string
{
    $descripciones = [
        'Windows.Analysis.EvidenceOfDownload' => 'Busca archivos que pudieron descargarse desde Internet.',
        'Windows.Applications.ChocolateyPackages' => 'Muestra paquetes instalados con Chocolatey, si existe en el equipo.',
        'Windows.Applications.Chrome.Extensions' => 'Lista extensiones de Chrome para revisar complementos sospechosos.',
        'Windows.Applications.Chrome.History' => 'Muestra páginas visitadas en Chrome.',
        'Windows.Applications.Edge.History' => 'Muestra páginas visitadas en Microsoft Edge.',
        'Windows.Applications.Firefox.Downloads' => 'Muestra archivos descargados desde Firefox.',
        'Windows.Applications.Firefox.History' => 'Muestra páginas visitadas en Firefox.',
        'Windows.Applications.IISLogs' => 'Revisa actividad web si el equipo usa IIS.',
        'Windows.Applications.TeamViewer.Incoming' => 'Muestra accesos remotos entrantes por TeamViewer.',

        'Windows.Attack.ParentProcess' => 'Busca programas lanzados desde sitios poco habituales.',
        'Windows.Attack.Prefetch' => 'Ayuda a ver programas ejecutados recientemente.',
        'Windows.Attack.UnexpectedImagePath' => 'Detecta programas ejecutándose desde rutas raras.',

        'Windows.Detection.Amcache' => 'Busca programas ejecutados anteriormente en Windows.',
        'Windows.Detection.BinaryRename' => 'Detecta herramientas conocidas usando nombres cambiados.',
        'Windows.Detection.EnvironmentVariables' => 'Revisa variables del sistema que puedan afectar ejecuciones.',
        'Windows.Detection.Impersonation' => 'Busca señales de procesos intentando hacerse pasar por otros.',
        'Windows.Detection.ProcessCreation' => 'Revisa procesos creados para detectar ejecuciones raras.',
        'Windows.Detection.PsexecService' => 'Busca señales de ejecución remota con PsExec.',
        'Windows.Detection.Registry' => 'Revisa claves del registro usadas para ocultarse o persistir.',
        'Windows.Detection.Thumbdrives.List' => 'Muestra memorias USB conectadas al equipo.',
        'Windows.Detection.Usn' => 'Muestra cambios recientes en archivos del sistema.',
        'Windows.Detection.WMIProcessCreation' => 'Busca procesos lanzados mediante WMI.',

        'Windows.EventLogs.AlternateLogon' => 'Busca accesos iniciados de forma poco común.',
        'Windows.EventLogs.Cleared' => 'Detecta si alguien ha borrado registros de Windows.',
        'Windows.EventLogs.DHCP' => 'Muestra cambios o eventos relacionados con red DHCP.',
        'Windows.EventLogs.Evtx' => 'Consulta registros de eventos de Windows.',
        'Windows.EventLogs.ExplicitLogon' => 'Detecta uso directo de usuario y contraseña.',
        'Windows.EventLogs.Modifications' => 'Busca cambios en la configuración de registros.',
        'Windows.EventLogs.PowershellModule' => 'Muestra actividad registrada de módulos PowerShell.',
        'Windows.EventLogs.PowershellScriptblock' => 'Muestra comandos PowerShell ejecutados.',
        'Windows.EventLogs.RDPAuth' => 'Muestra intentos y accesos por Escritorio Remoto.',
        'Windows.EventLogs.ScheduledTasks' => 'Muestra actividad relacionada con tareas programadas.',
        'Windows.EventLogs.ServiceCreationComspec' => 'Busca servicios creados usando consola de comandos.',

        'Windows.Forensics.Amcache' => 'Ayuda a saber qué programas se han ejecutado.',
        'Windows.Forensics.Bam' => 'Muestra actividad reciente de aplicaciones.',
        'Windows.Forensics.CertUtil' => 'Busca uso de CertUtil, usado a veces para descargar archivos.',
        'Windows.Forensics.FilenameSearch' => 'Busca archivos por nombre dentro del equipo.',
        'Windows.Forensics.JumpLists' => 'Muestra archivos o carpetas abiertas recientemente.',
        'Windows.Forensics.Lnk' => 'Revisa accesos directos usados recientemente.',
        'Windows.Forensics.Prefetch' => 'Muestra programas ejecutados y cuándo se usaron.',
        'Windows.Forensics.RDPCache' => 'Busca rastros de sesiones de Escritorio Remoto.',
        'Windows.Forensics.RecentApps' => 'Muestra aplicaciones utilizadas recientemente.',
        'Windows.Forensics.RecycleBin' => 'Revisa archivos enviados a la papelera.',
        'Windows.Forensics.Shellbags' => 'Muestra carpetas que el usuario ha abierto.',
        'Windows.Forensics.UserAccessLogs' => 'Muestra rastros de acceso de usuarios si existen.',

        'Windows.Network.ArpCache' => 'Muestra equipos vistos recientemente en la red local.',
        'Windows.Network.InterfaceAddresses' => 'Muestra IPs y tarjetas de red del equipo.',
        'Windows.Network.ListeningPorts' => 'Muestra servicios abiertos esperando conexiones.',
        'Windows.Network.Netstat' => 'Muestra conexiones de red activas.',
        'Windows.Network.NetstatEnriched' => 'Muestra conexiones activas con más detalle.',

        'Windows.Persistence.PermanentWMIEvents' => 'Busca arranques ocultos configurados mediante WMI.',
        'Windows.Persistence.PowershellProfile' => 'Revisa comandos que se cargan al abrir PowerShell.',
        'Windows.Persistence.PowershellRegistry' => 'Busca configuraciones PowerShell guardadas en registro.',

        'Windows.Registry.AppCompatCache' => 'Muestra rastros de programas ejecutados.',
        'Windows.Registry.MountPoints2' => 'Muestra discos y USB usados por el usuario.',
        'Windows.Registry.PortProxy' => 'Detecta redirecciones de puertos configuradas en Windows.',
        'Windows.Registry.PuttyHostKeys' => 'Muestra servidores guardados en PuTTY.',
        'Windows.Registry.RDP' => 'Revisa rastros y configuración de Escritorio Remoto.',
        'Windows.Registry.RecentDocs' => 'Muestra documentos abiertos recientemente.',
        'Windows.Registry.UserAssist' => 'Muestra programas abiertos por el usuario.',
        'Windows.Registry.WDigest' => 'Comprueba una opción peligrosa relacionada con credenciales.',

        'Windows.Search.FileFinder' => 'Busca archivos concretos en el equipo.',

        'Windows.Sys.AllUsers' => 'Muestra usuarios encontrados en el sistema.',
        'Windows.Sys.AppcompatShims' => 'Busca reglas que puedan alterar cómo arrancan programas.',
        'Windows.Sys.CertificateAuthorities' => 'Muestra certificados raíz confiables instalados.',
        'Windows.Sys.DiskInfo' => 'Muestra discos y particiones del equipo.',
        'Windows.Sys.Drivers' => 'Lista controladores instalados.',
        'Windows.Sys.FirewallRules' => 'Muestra qué permite o bloquea el firewall.',
        'Windows.Sys.Interfaces' => 'Lista interfaces de red y sistema.',
        'Windows.Sys.Programs' => 'Lista software instalado para detectar herramientas raras.',
        'Windows.Sys.StartupItems' => 'Muestra programas que arrancan automáticamente.',
        'Windows.Sys.Users' => 'Muestra usuarios locales del equipo.',

        'Windows.System.AuditPolicy' => 'Muestra qué acciones de seguridad se están registrando.',
        'Windows.System.CriticalServices' => 'Muestra servicios importantes de Windows.',
        'Windows.System.DLLs' => 'Muestra librerías cargadas por procesos.',
        'Windows.System.DNSCache' => 'Muestra dominios consultados recientemente.',
        'Windows.System.DomainRole' => 'Indica si el equipo pertenece a dominio o grupo.',
        'Windows.System.Handles' => 'Muestra archivos o recursos abiertos por procesos.',
        'Windows.System.HostsFile' => 'Revisa redirecciones manuales en el archivo hosts.',
        'Windows.System.LocalAdmins' => 'Muestra quién tiene permisos de administrador.',
        'Windows.System.Powershell.ModuleAnalysisCache' => 'Muestra módulos PowerShell usados recientemente.',
        'Windows.System.Powershell.PSReadline' => 'Muestra comandos escritos en PowerShell.',
        'Windows.System.Pslist' => 'Muestra procesos activos del equipo.',
        'Windows.System.RootCAStore' => 'Lista certificados raíz confiables.',
        'Windows.System.SVCHost' => 'Muestra servicios agrupados bajo SVCHost.',
        'Windows.System.Services' => 'Lista servicios instalados y su estado.',
        'Windows.System.Shares' => 'Muestra carpetas compartidas en red.',
        'Windows.System.Signers' => 'Revisa firmas digitales de componentes.',
        'Windows.System.TaskScheduler' => 'Muestra tareas programadas del equipo.',
        'Windows.System.Threads' => 'Muestra hilos internos de procesos.',
        'Windows.System.UntrustedBinaries' => 'Busca ejecutables sin firma confiable.',
        'Windows.System.WMIQuery' => 'Consulta información del sistema mediante WMI.',

        'Windows.Timeline.Prefetch' => 'Ordena ejecuciones recientes usando Prefetch.',
        'Windows.Timeline.Registry.RunMRU' => 'Muestra comandos ejecutados desde la ventana Ejecutar.',
    ];

    return $descripciones[$artefacto] ?? 'Ejecuta análisis seguro con Velociraptor.';
}

function construirAcciones(string $texto): array
{
    $lineas = preg_split('/\R/', trim($texto));
    $acciones = [];

    foreach ($lineas as $linea) {
        $artefacto = trim($linea);

        if ($artefacto === '') {
            continue;
        }

        $acciones[] = [
            'codigo' => $artefacto,
            'artefacto' => $artefacto,
            'nombre' => nombreBonito($artefacto),
            'categoria' => obtenerCategoria($artefacto),
            'descripcion' => descripcionArtefacto($artefacto),
        ];
    }

    usort($acciones, function ($a, $b) {
        return [$a['categoria'], $a['nombre']] <=> [$b['categoria'], $b['nombre']];
    });

    return $acciones;
}

function pintarResultado(?string $resultado): string
{
    if (!$resultado) {
        return '<span>No se encontraron resultados.</span>';
    }

    if (
        str_contains($resultado, 'filas detectadas=0') ||
        str_contains($resultado, 'source devolvió vacío') ||
        trim($resultado) === '[]'
    ) {
        return '<span>No se encontraron resultados.</span>';
    }

    $json = json_decode($resultado, true);

    if (!is_array($json)) {
        return '<pre>' . htmlspecialchars($resultado) . '</pre>';
    }

    if (!$json) {
        return '<span>No se encontraron resultados.</span>';
    }

    $primerasFilas = array_slice($json, 0, 50);

    $columnasPreferidas = [
        'Name', 'Pid', 'Ppid', 'Username', 'Exe', 'CommandLine',
        'LocalAddr', 'LocalPort', 'RemoteAddr', 'RemotePort', 'State',
        'ServiceName', 'DisplayName', 'StartMode', 'PathName',
        'FileName', 'FullPath', 'Key', 'Value', 'Timestamp',
        'EventID', 'Message', 'url', 'name', 'id', 'User',
        'startTime', 'endTime', 'last_modified'
    ];

    $columnas = [];

    foreach ($columnasPreferidas as $columna) {
        foreach ($primerasFilas as $fila) {
            if (is_array($fila) && array_key_exists($columna, $fila)) {
                $columnas[] = $columna;
                break;
            }
        }
    }

    if (!$columnas) {
        $columnas = array_keys($primerasFilas[0] ?? []);
        $columnas = array_slice($columnas, 0, 8);
    }

    if (!$columnas) {
        return '<pre>' . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
    }

    $html = '<div class="resultado-info">Mostrando ' . count($primerasFilas) . ' filas. Los detalles completos quedan ocultos debajo.</div>';
    $html .= '<div class="tabla-scroll"><table class="tabla-resultado"><thead><tr>';

    foreach ($columnas as $columna) {
        $html .= '<th>' . htmlspecialchars($columna) . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    foreach ($primerasFilas as $fila) {
        $html .= '<tr>';

        foreach ($columnas as $columna) {
            $valor = $fila[$columna] ?? '';

            if (is_array($valor)) {
                $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
            }

            $html .= '<td>' . htmlspecialchars((string)$valor) . '</td>';
        }

        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';
    $html .= '<details class="detalle-json"><summary>Ver detalles</summary><pre>' . htmlspecialchars(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></details>';

    return $html;
}

function pintarHistorialAcciones(array $ordenes): void
{
    ?>
    <div class="historial-cabecera">
        <h2>Historial Últimas acciones</h2>

        <form method="post" class="form-borrar-historial" onsubmit="return confirm('¿Seguro que quieres borrar el historial?');">
            <input type="hidden" name="borrar_historial" value="1">
            <button type="submit" class="btn-borrar-historial">Borrar historial</button>
        </form>
    </div>

    <table class="tabla-principal">
        <thead>
        <tr>
            <th>Fecha</th>
            <th>Acción</th>
            <th>Estado</th>
            <th>Resultado</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$ordenes): ?>
            <tr>
                <td colspan="4">No hay acciones todavía.</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($ordenes as $orden): ?>
            <?php $resultadoId = 'resultado_' . (int)$orden['id']; ?>
            <tr>
                <td><?= htmlspecialchars((string)$orden['creado_en']) ?></td>
                <td>
                    <div><?= htmlspecialchars(nombreBonito((string)$orden['codigo'])) ?></div>
                    <div class="artefacto"><?= htmlspecialchars((string)$orden['codigo']) ?></div>
                </td>
                <td>
                    <span class="estado <?= htmlspecialchars((string)$orden['estado']) ?>">
                        <?= htmlspecialchars((string)$orden['estado']) ?>
                    </span>
                </td>
                <td>
                    <?php if (in_array($orden['estado'], ['pendiente', 'en_proceso'], true)): ?>
                        <form method="post" class="form-cancelar">
                            <input type="hidden" name="cancelar_orden" value="1">
                            <input type="hidden" name="orden_id" value="<?= (int)$orden['id'] ?>">
                            <button type="submit" class="btn-cancelar-orden">Cancelar</button>
                        </form>
                        <span>Esperando al agente...</span>
                    <?php elseif ($orden['estado'] === 'completada' && $orden['resultado']): ?>
                        <button type="button" class="btn-ver-resultado" data-target="<?= htmlspecialchars($resultadoId) ?>">
                            Ver resultado
                        </button>

                        <div id="<?= htmlspecialchars($resultadoId) ?>" class="resultado-oculto">
                            <?= pintarResultado((string)$orden['resultado']) ?>
                        </div>
                    <?php elseif ($orden['estado'] === 'cancelada'): ?>
                        <span>Orden cancelada.</span>
                    <?php elseif ($orden['estado'] === 'error'): ?>
                        <pre><?= htmlspecialchars($orden['error'] ?: 'Error desconocido') ?></pre>
                    <?php else: ?>
                        <span>Sin resultado.</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

$acciones = construirAcciones($artefactosTexto);
$ordenes = [];

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_orden'])) {
        $ordenIdCancelar = (int)($_POST['orden_id'] ?? 0);

        if ($ordenIdCancelar > 0) {
            $stmtCancelar = $pdo->prepare("
                UPDATE respuesta_ordenes
                SET estado = 'cancelada',
                    error = 'Orden cancelada por el usuario',
                    actualizado_en = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND agente_id = :agente_id
                  AND estado IN ('pendiente', 'en_proceso')
            ");

            $stmtCancelar->execute([
                ':id' => $ordenIdCancelar,
                ':agente_id' => $AGENTE_ID,
            ]);
        }

        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar_historial'])) {
        $stmtBorrar = $pdo->prepare("
            DELETE FROM respuesta_ordenes
            WHERE agente_id = :agente_id
        ");

        $stmtBorrar->execute([':agente_id' => $AGENTE_ID]);

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $codigo = $_POST['codigo'] ?? '';
        $codigosValidos = array_column($acciones, 'codigo');

        if (!in_array($codigo, $codigosValidos, true)) {
            throw new Exception('Acción no válida.');
        }

        $stmtExiste = $pdo->prepare("
            SELECT id
            FROM respuesta_ordenes
            WHERE agente_id = :agente_id
              AND codigo = :codigo
              AND estado IN ('pendiente', 'en_proceso')
            LIMIT 1
        ");

        $stmtExiste->execute([
            ':agente_id' => $AGENTE_ID,
            ':codigo' => $codigo,
        ]);

        $ordenExistente = $stmtExiste->fetch();

        if ($ordenExistente) {
            $ordenId = (int)$ordenExistente['id'];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO respuesta_ordenes
                (agente_id, codigo, parametros, estado, creado_en, actualizado_en)
                VALUES
                (:agente_id, :codigo, '{}'::jsonb, 'pendiente', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                RETURNING id
            ");

            $stmt->execute([
                ':agente_id' => $AGENTE_ID,
                ':codigo' => $codigo,
            ]);

            $ordenId = (int)$stmt->fetchColumn();
        }

        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'orden_id' => $ordenId], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?orden=' . $ordenId);
        exit;
    }

    $stmtOrdenes = $pdo->prepare("
        SELECT
            id,
            agente_id,
            codigo,
            estado,
            flow_id,
            resultado,
            error,
            creado_en,
            ejecutado_en,
            actualizado_en
        FROM respuesta_ordenes
        WHERE agente_id = :agente_id
        ORDER BY id DESC
        LIMIT 20
    ");

    $stmtOrdenes->execute([':agente_id' => $AGENTE_ID]);
    $ordenes = $stmtOrdenes->fetchAll();

} catch (Throwable $e) {
    $error = $e->getMessage();
    $ordenes = [];
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'ultimas') {
    pintarHistorialAcciones($ordenes);
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'orden') {
    $ordenId = (int)($_GET['id'] ?? 0);

    $stmtOrden = $pdo->prepare("
        SELECT id, codigo, estado, resultado, error
        FROM respuesta_ordenes
        WHERE id = :id
          AND agente_id = :agente_id
        LIMIT 1
    ");

    $stmtOrden->execute([
        ':id' => $ordenId,
        ':agente_id' => $AGENTE_ID,
    ]);

    $orden = $stmtOrden->fetch();

    if (!$orden) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Orden no encontrada'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $html = '';

    if ($orden['estado'] === 'completada') {
        $html = pintarResultado((string)$orden['resultado']);
    } elseif ($orden['estado'] === 'error') {
        $html = '<pre>' . htmlspecialchars($orden['error'] ?: 'Error desconocido') . '</pre>';
    } elseif ($orden['estado'] === 'cancelada') {
        $html = '<span>Orden cancelada.</span>';
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'estado' => $orden['estado'],
        'titulo' => nombreBonito((string)$orden['codigo']),
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$accionesPorCategoria = [];

foreach ($acciones as $accion) {
    $accionesPorCategoria[$accion['categoria']][] = $accion;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Análisis y supervisión - Zypher</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
        }

        .contenedor {
            max-width: 1300px;
            margin: 30px auto;
            padding: 20px;
        }

        .cabecera {
            background: #111827;
            color: white;
            padding: 24px;
            border-radius: 14px;
            margin-bottom: 24px;
        }

        .cabecera h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .cabecera p {
            margin: 0;
            color: #d1d5db;
        }

        .alerta-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
        }

        details.categoria {
            background: white;
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 14px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 14px rgba(0,0,0,0.05);
        }

        details.categoria summary {
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
        }

        .acciones-lista {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 12px;
        }

        .accion {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 18px;
            background: #f9fafb;
            min-height: 160px;
        }

        .accion h3 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .accion p {
            margin: 0 0 10px;
            font-size: 14px;
            color: #4b5563;
            min-height: 36px;
        }

        .artefacto {
            font-family: Consolas, monospace;
            font-size: 12px;
            background: #e5e7eb;
            padding: 5px 7px;
            border-radius: 7px;
            display: inline-block;
            margin-bottom: 10px;
            max-width: 100%;
            box-sizing: border-box;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        button {
            width: 100%;
            border: 0;
            background: #2563eb;
            color: white;
            padding: 13px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }

        button:hover {
            background: #1d4ed8;
        }

        button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .btn-ver-resultado {
            width: auto;
            min-width: 130px;
            padding: 9px 14px;
            font-size: 13px;
        }

        .btn-cancelar-orden {
            width: auto;
            background: #f97316;
            padding: 8px 12px;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .btn-cancelar-orden:hover {
            background: #ea580c;
        }

        .bloque {
            background: white;
            border-radius: 14px;
            padding: 18px;
            margin-top: 28px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .actualizando {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .historial-cabecera {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 12px;
        }

        .historial-cabecera h2 {
            margin: 0;
        }

        .form-borrar-historial,
        .form-cancelar {
            margin: 0;
        }

        .btn-borrar-historial {
            width: auto;
            background: #dc2626;
            padding: 10px 14px;
            font-size: 13px;
        }

        .btn-borrar-historial:hover {
            background: #b91c1c;
        }

        .tabla-principal {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .tabla-principal th {
            text-align: left;
            background: #f9fafb;
            padding: 12px;
            font-size: 14px;
            border-bottom: 1px solid #e5e7eb;
        }

        .tabla-principal td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .tabla-principal th:nth-child(1),
        .tabla-principal td:nth-child(1) {
            width: 130px;
        }

        .tabla-principal th:nth-child(2),
        .tabla-principal td:nth-child(2) {
            width: 210px;
        }

        .tabla-principal th:nth-child(3),
        .tabla-principal td:nth-child(3) {
            width: 110px;
        }

        .estado {
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }

        .pendiente { background: #fef3c7; color: #92400e; }
        .en_proceso { background: #dbeafe; color: #1e40af; }
        .completada { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .cancelada { background: #e5e7eb; color: #374151; }

        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #111827;
            color: #e5e7eb;
            padding: 12px;
            border-radius: 10px;
            max-height: 420px;
            overflow: auto;
        }

        .tabla-scroll {
            width: 100%;
            max-width: 100%;
            overflow: auto;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-top: 10px;
        }

        .tabla-resultado {
            width: max-content;
            min-width: 100%;
            border-collapse: collapse;
        }

        .tabla-resultado th,
        .tabla-resultado td {
            font-size: 12px;
            white-space: normal;
            overflow-wrap: anywhere;
            max-width: 360px;
            padding: 9px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .tabla-resultado th {
            background: #f9fafb;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .resultado-info {
            font-size: 13px;
            color: #4b5563;
            margin: 8px 0;
        }

        .detalle-json {
            margin-top: 10px;
        }

        .resultado-oculto {
            display: none;
        }

        .modal-fondo {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.75);
            z-index: 9999;
            padding: 30px;
            box-sizing: border-box;
        }

        .modal-fondo.activo {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-caja {
            background: white;
            width: min(1200px, 96vw);
            max-height: 90vh;
            border-radius: 14px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.35);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .modal-cabecera {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .modal-cabecera h3 {
            margin: 0;
            font-size: 18px;
        }

        .modal-cerrar {
            width: auto;
            background: #ef4444;
            padding: 8px 12px;
            border-radius: 8px;
        }

        .modal-cerrar:hover {
            background: #dc2626;
        }

        .modal-contenido {
            padding: 18px;
            overflow: auto;
        }

        .modal-cargando {
            padding: 20px;
            font-size: 15px;
            color: #374151;
        }
    </style>
</head>
<body>
<div class="contenedor">

    <div class="cabecera">
        <h1>Análisis y supervisión</h1>
        <p>Ejecutamos análisis seguros sobre equipos Windows usando Velociraptor.</p>
    </div>

    <?php if ($error): ?>
        <div class="alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php foreach ($accionesPorCategoria as $categoria => $lista): ?>
        <details class="categoria" data-categoria="<?= htmlspecialchars($categoria) ?>">
            <summary><?= htmlspecialchars($categoria) ?> (<?= count($lista) ?>)</summary>

            <div class="acciones-lista">
                <?php foreach ($lista as $accion): ?>
                    <div class="accion">
                        <h3><?= htmlspecialchars($accion['nombre']) ?></h3>
                        <p><?= htmlspecialchars($accion['descripcion']) ?></p>
                        <span class="artefacto"><?= htmlspecialchars($accion['artefacto']) ?></span>

                        <form method="post" class="form-ejecutar">
                            <input type="hidden" name="codigo" value="<?= htmlspecialchars($accion['codigo']) ?>">
                            <button type="submit">Ejecutar</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endforeach; ?>

    <div class="bloque">
        <div class="actualizando">Historial Últimas acciones se actualiza automáticamente cada 5 segundos.</div>
        <div id="ultimasAcciones">
            <?php pintarHistorialAcciones($ordenes); ?>
        </div>
    </div>

</div>

<div class="modal-fondo" id="modalResultado">
    <div class="modal-caja">
        <div class="modal-cabecera">
            <h3 id="modalTitulo">Resultado del análisis</h3>
            <button type="button" class="modal-cerrar" id="cerrarModal">X</button>
        </div>
        <div class="modal-contenido" id="modalContenido"></div>
    </div>
</div>

<script>
    const ordenAutoAbrir = <?= (int)$ordenAutoAbrir ?>;
    let modalAbierto = false;
    let intervaloOrden = null;

    function guardarCategoriasAbiertas() {
        const abiertas = [];

        document.querySelectorAll('details.categoria').forEach(function (item) {
            if (item.open) {
                abiertas.push(item.getAttribute('data-categoria'));
            }
        });

        localStorage.setItem('zypher_categorias_abiertas', JSON.stringify(abiertas));
    }

    function restaurarCategoriasAbiertas() {
        let abiertas = [];

        try {
            abiertas = JSON.parse(localStorage.getItem('zypher_categorias_abiertas') || '[]');
        } catch (e) {
            abiertas = [];
        }

        document.querySelectorAll('details.categoria').forEach(function (item) {
            const categoria = item.getAttribute('data-categoria');

            if (abiertas.includes(categoria)) {
                item.open = true;
            }

            item.addEventListener('toggle', guardarCategoriasAbiertas);
        });
    }

    restaurarCategoriasAbiertas();

    document.addEventListener('submit', function (e) {
        const formEjecutar = e.target.closest('.form-ejecutar');

        if (!formEjecutar) {
            return;
        }

        e.preventDefault();
        guardarCategoriasAbiertas();

        const boton = formEjecutar.querySelector('button');
        const formData = new FormData(formEjecutar);
        formData.append('ajax', '1');

        if (boton) {
            boton.disabled = true;
            boton.innerText = 'Enviado...';
        }

        abrirModal('Resultado del análisis', '<div class="modal-cargando">Ejecutando análisis, esperando resultado...</div>');

        fetch(window.location.pathname, {
            method: 'POST',
            body: formData,
            cache: 'no-store'
        })
            .then(function (respuesta) {
                return respuesta.json();
            })
            .then(function (data) {
                if (!data.ok || !data.orden_id) {
                    abrirModal('Resultado del análisis', '<span>No se pudo crear la orden.</span>');
                    return;
                }

                actualizarUltimasAcciones();
                vigilarOrden(data.orden_id);
            })
            .catch(function () {
                abrirModal('Resultado del análisis', '<span>No se pudo ejecutar la acción.</span>');
            })
            .finally(function () {
                if (boton) {
                    boton.disabled = false;
                    boton.innerText = 'Ejecutar';
                }
            });
    });

    document.addEventListener('submit', function (e) {
        const form = e.target.closest('.form-cancelar');

        if (!form) {
            return;
        }

        e.preventDefault();

        if (!confirm('¿Cancelar esta orden?')) {
            return;
        }

        const boton = form.querySelector('button');
        const formData = new FormData(form);
        formData.append('ajax', '1');

        if (boton) {
            boton.disabled = true;
            boton.innerText = 'Cancelando...';
        }

        fetch(window.location.pathname, {
            method: 'POST',
            body: formData,
            cache: 'no-store'
        })
            .then(function () {
                actualizarUltimasAcciones();
            })
            .catch(function () {
                alert('No se pudo cancelar la orden.');
            });
    });

    function abrirModal(titulo, html) {
        document.getElementById('modalTitulo').innerText = titulo || 'Resultado del análisis';
        document.getElementById('modalContenido').innerHTML = html;
        document.getElementById('modalResultado').classList.add('activo');
        modalAbierto = true;
    }

    function cerrarVentanaResultado() {
        document.getElementById('modalResultado').classList.remove('activo');
        document.getElementById('modalContenido').innerHTML = '';
        modalAbierto = false;
    }

    function actualizarUltimasAcciones() {
        if (modalAbierto) {
            return;
        }

        fetch(window.location.pathname + '?ajax=ultimas', {
            cache: 'no-store'
        })
            .then(function (respuesta) {
                return respuesta.text();
            })
            .then(function (html) {
                const bloque = document.getElementById('ultimasAcciones');

                if (bloque) {
                    bloque.innerHTML = html;
                }
            })
            .catch(function () {
                console.log('No se pudo actualizar Historial Últimas acciones');
            });
    }

    setInterval(actualizarUltimasAcciones, 5000);

    document.addEventListener('click', function (e) {
        const boton = e.target.closest('.btn-ver-resultado');

        if (!boton) {
            return;
        }

        const targetId = boton.getAttribute('data-target');
        const contenido = document.getElementById(targetId);

        if (!contenido) {
            return;
        }

        abrirModal('Resultado del análisis', contenido.innerHTML);
    });

    document.getElementById('cerrarModal').addEventListener('click', cerrarVentanaResultado);

    document.getElementById('modalResultado').addEventListener('click', function (e) {
        if (e.target === this) {
            cerrarVentanaResultado();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            cerrarVentanaResultado();
        }
    });

    function vigilarOrden(id) {
        if (intervaloOrden) {
            clearInterval(intervaloOrden);
        }

        intervaloOrden = setInterval(function () {
            fetch(window.location.pathname + '?ajax=orden&id=' + encodeURIComponent(id), {
                cache: 'no-store'
            })
                .then(function (respuesta) {
                    return respuesta.json();
                })
                .then(function (data) {
                    if (!data.ok) {
                        return;
                    }

                    if (data.estado === 'completada' || data.estado === 'error' || data.estado === 'cancelada') {
                        clearInterval(intervaloOrden);
                        intervaloOrden = null;

                        abrirModal(data.titulo || 'Resultado del análisis', data.html || '<span>No se encontraron resultados.</span>');
                        actualizarUltimasAcciones();

                        if (window.history.replaceState) {
                            window.history.replaceState({}, document.title, window.location.pathname);
                        }
                    }
                })
                .catch(function () {
                    console.log('No se pudo consultar la orden');
                });
        }, 3000);
    }

    if (ordenAutoAbrir > 0) {
        abrirModal('Resultado del análisis', '<div class="modal-cargando">Ejecutando análisis, esperando resultado...</div>');
        vigilarOrden(ordenAutoAbrir);
    }
</script>
</body>
</html>
