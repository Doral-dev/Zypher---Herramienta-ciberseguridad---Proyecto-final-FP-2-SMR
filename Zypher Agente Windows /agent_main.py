import os
import re
import sys
import time
import shutil
import ctypes
import tempfile
import subprocess
from datetime import datetime
import winreg
import socket
import ssl
import json
import base64
import urllib.request
import urllib.error
import urllib.parse
import xml.etree.ElementTree as ET
import hashlib
import threading
import psycopg2
import zipfile
import mimetypes





# 1.MODULO CIS


DB_HOST = "dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com"
DB_PORT = "5432"
DB_NAME = "zypher_db_g2sb"
DB_USER = "zypher_db_g2sb_user"
DB_PASSWORD = "MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86"

APP_BASE_DIR = os.path.join(
    os.environ.get("LOCALAPPDATA", os.path.expanduser("~")),
    "AgenteZypher"
)
INSTALLED_EXE = os.path.join(APP_BASE_DIR, "AgenteZypher.exe")
RUN_KEY_PATH = r"Software\Microsoft\Windows\CurrentVersion\Run"
RUN_VALUE_NAME = "Agente Zypher"
LOG_FILE = os.path.join(APP_BASE_DIR, "agent.log")

ROOT_KEYS = {
    "HKEY_LOCAL_MACHINE": winreg.HKEY_LOCAL_MACHINE,
    "HKLM": winreg.HKEY_LOCAL_MACHINE,
    "HKEY_CURRENT_USER": winreg.HKEY_CURRENT_USER,
    "HKCU": winreg.HKEY_CURRENT_USER,
    "HKEY_CLASSES_ROOT": winreg.HKEY_CLASSES_ROOT,
    "HKCR": winreg.HKEY_CLASSES_ROOT,
    "HKEY_USERS": winreg.HKEY_USERS,
    "HKU": winreg.HKEY_USERS,
    "HKEY_CURRENT_CONFIG": winreg.HKEY_CURRENT_CONFIG,
    "HKCC": winreg.HKEY_CURRENT_CONFIG,
}

_MUTEX_HANDLE = None


def log(msg: str) -> None:
    try:
        os.makedirs(APP_BASE_DIR, exist_ok=True)
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {msg}\n")
    except Exception:
        pass


def obtener_ruta_actual() -> str:
    if getattr(sys, "frozen", False):
        return os.path.abspath(sys.executable)
    return os.path.abspath(__file__)


def registrar_inicio_windows(ruta_exe: str) -> None:
    with winreg.OpenKey(
        winreg.HKEY_CURRENT_USER,
        RUN_KEY_PATH,
        0,
        winreg.KEY_SET_VALUE
    ) as key:
        winreg.SetValueEx(key, RUN_VALUE_NAME, 0, winreg.REG_SZ, f'"{ruta_exe}"')


def relanzar_instalado_y_salir() -> None:
    flags = 0
    if hasattr(subprocess, "DETACHED_PROCESS"):
        flags |= subprocess.DETACHED_PROCESS
    if hasattr(subprocess, "CREATE_NEW_PROCESS_GROUP"):
        flags |= subprocess.CREATE_NEW_PROCESS_GROUP
    if hasattr(subprocess, "CREATE_NO_WINDOW"):
        flags |= subprocess.CREATE_NO_WINDOW

    subprocess.Popen([INSTALLED_EXE], creationflags=flags)
    sys.exit(0)


def asegurar_instalacion() -> None:
    os.makedirs(APP_BASE_DIR, exist_ok=True)
    ruta_actual = obtener_ruta_actual()

    if getattr(sys, "frozen", False):
        if os.path.normcase(ruta_actual) != os.path.normcase(INSTALLED_EXE):
            shutil.copy2(ruta_actual, INSTALLED_EXE)
            registrar_inicio_windows(INSTALLED_EXE)
            log(f"Instalado/actualizado en {INSTALLED_EXE}")
            relanzar_instalado_y_salir()
        else:
            registrar_inicio_windows(INSTALLED_EXE)
            log("Autoarranque verificado")


def asegurar_instancia_unica() -> bool:
    global _MUTEX_HANDLE
    mutex_name = "Global\\AgenteZypherMutex"
    _MUTEX_HANDLE = ctypes.windll.kernel32.CreateMutexW(None, False, mutex_name)
    last_error = ctypes.windll.kernel32.GetLastError()
    return last_error != 183


def conectar_bd():
    return psycopg2.connect(
        host=DB_HOST,
        port=DB_PORT,
        dbname=DB_NAME,
        user=DB_USER,
        password=DB_PASSWORD
    )


def obtener_politicas(cur):
    cur.execute("""
        SELECT id_cis, check_comprobacion
        FROM cis_policies
        ORDER BY id_cis ASC
    """)
    return cur.fetchall()


def parsear_ruta_registro(path):
    path = path.strip()
    root_name, subkey = path.split("\\", 1)
    return ROOT_KEYS[root_name], subkey


def existe_clave(path):
    try:
        root, subkey = parsear_ruta_registro(path)
        with winreg.OpenKey(root, subkey, 0, winreg.KEY_READ):
            return True
    except Exception:
        return False


def existe_valor(path, value_name):
    try:
        root, subkey = parsear_ruta_registro(path)
        with winreg.OpenKey(root, subkey, 0, winreg.KEY_READ) as key:
            winreg.QueryValueEx(key, value_name)
            return True
    except Exception:
        return False


def leer_valor(path, value_name):
    try:
        root, subkey = parsear_ruta_registro(path)
        with winreg.OpenKey(root, subkey, 0, winreg.KEY_READ) as key:
            value, _ = winreg.QueryValueEx(key, value_name)
            return value
    except Exception:
        return None


def comparar_valor(actual, expected):
    expected = expected.strip()

    if actual is None:
        return False

    if expected.startswith("n:^") and expected.endswith("$"):
        esperado = expected[3:-1]
        return str(actual) == esperado

    if expected.startswith("^") and expected.endswith("$"):
        return re.fullmatch(expected, str(actual)) is not None

    if re.fullmatch(r"-?\d+", expected):
        try:
            return int(actual) == int(expected)
        except Exception:
            return str(actual) == expected

    return str(actual) == expected


def evaluar_linea_registro(line):
    negativa = False
    line = line.strip()

    if line.startswith("not r:"):
        negativa = True
        body = line[6:].strip()
    elif line.startswith("r:"):
        body = line[2:].strip()
    else:
        return None

    parts = [p.strip() for p in body.split(" -> ")]

    try:
        if len(parts) == 1:
            resultado = existe_clave(parts[0])
        elif len(parts) == 2:
            resultado = existe_valor(parts[0], parts[1])
        elif len(parts) >= 3:
            actual = leer_valor(parts[0], parts[1])
            resultado = comparar_valor(actual, parts[2])
        else:
            return None
    except Exception:
        resultado = False

    return (not resultado) if negativa else resultado


def leer_archivo_cfg_seguro(path):
    encodings = ["utf-16", "utf-16le", "utf-8-sig", "cp1252", "latin-1"]

    for encoding in encodings:
        try:
            with open(path, "r", encoding=encoding) as f:
                return f.readlines()
        except Exception:
            continue

    with open(path, "rb") as f:
        raw = f.read()

    return raw.decode("latin-1", errors="ignore").splitlines()


def exportar_secpol():
    fd, temp_path = tempfile.mkstemp(suffix=".cfg")
    os.close(fd)

    try:
        startupinfo = subprocess.STARTUPINFO()
        startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW

        creationflags = 0
        if hasattr(subprocess, "CREATE_NO_WINDOW"):
            creationflags |= subprocess.CREATE_NO_WINDOW

        result = subprocess.run(
            ["secedit", "/export", "/cfg", temp_path],
            capture_output=True,
            text=True,
            shell=False,
            startupinfo=startupinfo,
            creationflags=creationflags
        )

        if result.returncode != 0:
            raise Exception(result.stderr.strip() or result.stdout.strip() or "Error ejecutando secedit")

        values = {}
        lines = leer_archivo_cfg_seguro(temp_path)

        for line in lines:
            line = line.strip()
            if "=" in line:
                key, value = line.split("=", 1)
                values[key.strip()] = value.strip()

        return values

    finally:
        if os.path.exists(temp_path):
            os.remove(temp_path)


def evaluar_linea_secedit(line, sec_values):
    line = line.strip()

    m = re.search(r"n:([A-Za-z0-9_]+)\s*=.*compare\s*(>=|<=|>|<|==|=)\s*(-?\d+)", line)
    if not m:
        return None

    nombre = m.group(1)
    operador = m.group(2)
    objetivo = int(m.group(3))

    if nombre not in sec_values:
        return False

    try:
        actual = int(sec_values[nombre])
    except Exception:
        return False

    if operador in ("=", "=="):
        return actual == objetivo
    if operador == ">=":
        return actual >= objetivo
    if operador == "<=":
        return actual <= objetivo
    if operador == ">":
        return actual > objetivo
    if operador == "<":
        return actual < objetivo

    return False


def evaluar_legal_notice(line):
    line = line.strip()

    m = re.search(r"reg query ([A-Z_\\0-9a-z]+) /v ([A-Za-z0-9_]+)", line)
    if not m:
        return None

    raw_path = m.group(1).replace("HKLM", "HKEY_LOCAL_MACHINE", 1)
    value_name = m.group(2)

    valor = leer_valor(raw_path, value_name)
    if valor is None:
        return False

    return str(valor).strip() != ""


def evaluar_check(check_text, sec_values):
    if not check_text or not check_text.strip():
        return "pendiente"

    lines = [line.strip() for line in check_text.splitlines() if line.strip()]

    if not lines:
        return "pendiente"

    resultados = []

    for line in lines:
        if line.startswith("r:") or line.startswith("not r:"):
            r = evaluar_linea_registro(line)
            if r is not None:
                resultados.append(r)
            continue

        if line.startswith("c:powershell secedit"):
            r = evaluar_linea_secedit(line, sec_values)
            if r is not None:
                resultados.append(r)
            continue

        if line.startswith("not c:reg query"):
            r = evaluar_legal_notice(line)
            if r is not None:
                resultados.append(r)
            continue

    if not resultados:
        return "pendiente"

    tiene_negativos = any(line.startswith("not r:") for line in lines)

    if tiene_negativos:
        return "completado" if any(resultados) else "no completado"

    return "completado" if all(resultados) else "no completado"


def actualizar_politicas(cur, politicas, sec_values):
    fecha_actual = datetime.now()

    for id_cis, check_comprobacion in politicas:
        estado = evaluar_check(check_comprobacion, sec_values)

        cur.execute("""
            UPDATE cis_policies
            SET estado = %s,
                fecha_ultimo_analisis = %s
            WHERE id_cis = %s
        """, (estado, fecha_actual, id_cis))


def analizar_todo():
    conn = None
    cur = None

    try:
        conn = conectar_bd()
        conn.autocommit = False
        cur = conn.cursor()

        politicas = obtener_politicas(cur)
        sec_values = exportar_secpol()
        actualizar_politicas(cur, politicas, sec_values)

        conn.commit()
        log("Análisis completado")

    except Exception as e:
        log(f"Error analizando: {e}")
        if conn:
            conn.rollback()

    finally:
        if cur:
            cur.close()
        if conn:
            conn.close()











# 2.MODULO ANALISIS VULNERABILIDADES

WAZUH_MANAGER_IP = "192.168.1.171"
WAZUH_INDEXER_URL = "https://192.168.1.171:9200"
WAZUH_INDEXER_USER = "admin"
WAZUH_INDEXER_PASS = "?zt3C5Fg9jqMVxYA0szojYubPRvprdlN"

WAZUH_AGENT_MSI_URL = "https://packages.wazuh.com/4.x/windows/wazuh-agent-4.13.1-1.msi"
WAZUH_AGENT_SERVICE_NAME = "WazuhSvc"
WAZUH_AGENT_CONF_PATH = r"C:\Program Files (x86)\ossec-agent\ossec.conf"

ZYPHER_GUARDAR_VULNS_URL = "https://zypher-herramienta-ciberseguridad.onrender.com/Escaneo%20Vulnerabilidades%20Modulo/guardar_vulnerabilidades.php"

WAZUH_MSI_PATH = os.path.join(
    globals().get("APP_BASE_DIR", os.environ.get("TEMP", ".")),
    "wazuh-agent.msi"
)


def _creationflags():
    flags = 0
    if hasattr(subprocess, "CREATE_NO_WINDOW"):
        flags |= subprocess.CREATE_NO_WINDOW
    return flags


def ejecutar_oculto(cmd):
    return subprocess.run(
        cmd,
        capture_output=True,
        text=True,
        creationflags=_creationflags()
    )


def servicio_wazuh_instalado():
    try:
        result = ejecutar_oculto(["sc", "query", WAZUH_AGENT_SERVICE_NAME])
        return result.returncode == 0
    except Exception:
        return False


def servicio_wazuh_activo():
    try:
        result = ejecutar_oculto(["sc", "query", WAZUH_AGENT_SERVICE_NAME])
        salida = (result.stdout or "") + (result.stderr or "")
        return "RUNNING" in salida
    except Exception:
        return False


def descargar_wazuh_agent():
    os.makedirs(os.path.dirname(WAZUH_MSI_PATH), exist_ok=True)
    urllib.request.urlretrieve(WAZUH_AGENT_MSI_URL, WAZUH_MSI_PATH)
    log(f"MSI Wazuh descargado en {WAZUH_MSI_PATH}")


def instalar_wazuh_agent():
    if not os.path.exists(WAZUH_MSI_PATH):
        descargar_wazuh_agent()

    cmd = [
        "msiexec.exe",
        "/i", WAZUH_MSI_PATH,
        "/qn",
        f"WAZUH_MANAGER={WAZUH_MANAGER_IP}"
    ]

    result = ejecutar_oculto(cmd)

    if result.returncode != 0:
        raise Exception(result.stderr or result.stdout or "Error instalando Wazuh Agent")

    log("Wazuh Agent instalado en silencio")


def configurar_manager_ossec_conf():
    if not os.path.exists(WAZUH_AGENT_CONF_PATH):
        return False

    try:
        tree = ET.parse(WAZUH_AGENT_CONF_PATH)
        root = tree.getroot()

        client = root.find("client")
        if client is None:
            return False

        server = client.find("server")
        if server is None:
            server = ET.SubElement(client, "server")

        address = server.find("address")
        if address is None:
            address = ET.SubElement(server, "address")

        cambiado = (address.text or "").strip() != WAZUH_MANAGER_IP
        address.text = WAZUH_MANAGER_IP

        port = server.find("port")
        if port is None:
            port = ET.SubElement(server, "port")
        port.text = "1514"

        protocol = server.find("protocol")
        if protocol is None:
            protocol = ET.SubElement(server, "protocol")
        protocol.text = "tcp"

        if cambiado:
            tree.write(WAZUH_AGENT_CONF_PATH, encoding="utf-8", xml_declaration=False)
            log(f"ossec.conf actualizado con manager {WAZUH_MANAGER_IP}")

        return cambiado

    except Exception as e:
        log(f"Error configurando ossec.conf: {e}")
        return False


def iniciar_servicio_wazuh():
    result = ejecutar_oculto(["sc", "start", WAZUH_AGENT_SERVICE_NAME])
    salida = (result.stdout or "") + (result.stderr or "")
    if result.returncode == 0 or "SERVICE_ALREADY_RUNNING" in salida:
        log("Servicio Wazuh iniciado")
        return True
    log(f"Error iniciando servicio Wazuh: {salida.strip()}")
    return False


def reiniciar_servicio_wazuh():
    try:
        ejecutar_oculto(["sc", "stop", WAZUH_AGENT_SERVICE_NAME])
        time.sleep(5)
        return iniciar_servicio_wazuh()
    except Exception as e:
        log(f"Error reiniciando servicio Wazuh: {e}")
        return False


def asegurar_wazuh_agent():
    try:
        if not servicio_wazuh_instalado():
            print("Wazuh Agent no instalado. Instalando...")
            log("Wazuh Agent no instalado. Instalando...")
            instalar_wazuh_agent()
            time.sleep(10)
        else:
            print("Wazuh Agent ya estaba instalado")
            log("Wazuh Agent ya estaba instalado")

        conf_cambiada = configurar_manager_ossec_conf()

        if conf_cambiada:
            print("ossec.conf actualizado. Reiniciando Wazuh Agent...")
            log("ossec.conf actualizado. Reiniciando Wazuh Agent...")
            reiniciar_servicio_wazuh()
            time.sleep(10)
        elif not servicio_wazuh_activo():
            print("Wazuh Agent instalado pero parado. Iniciando...")
            log("Wazuh Agent instalado pero parado. Iniciando...")
            iniciar_servicio_wazuh()
            time.sleep(10)
        else:
            print("Wazuh Agent ya estaba activo y configurado")
            log("Wazuh Agent ya estaba activo y configurado")

    except Exception as e:
        print(f"Error asegurando Wazuh Agent: {e}")
        log(f"Error asegurando Wazuh Agent: {e}")


def obtener_hostname_vulns():
    try:
        return socket.gethostname()
    except Exception:
        return None


def normalizar_cvss(valor):
    try:
        numero = float(valor)
        if numero < 0:
            return ""
        return round(numero, 1)
    except Exception:
        return ""


def traducir_severidad(valor, score):
    texto = str(valor).strip()

    mapa = {
        "Critical": "Crítica",
        "High": "Alta",
        "Medium": "Media",
        "Low": "Leve",
        "None": "Leve",
        "-": ""
    }

    traducida = mapa.get(texto, texto)

    if traducida:
        return traducida

    try:
        score_num = float(score)
    except Exception:
        score_num = -1

    if score_num >= 9.0:
        return "Crítica"
    if score_num >= 7.0:
        return "Alta"
    if score_num >= 4.0:
        return "Media"

    return "Leve"


def traducir_texto_es(texto):
    if not texto:
        return ""

    try:
        url = (
            "https://translate.googleapis.com/translate_a/single"
            "?client=gtx&sl=en&tl=es&dt=t&q=" + urllib.parse.quote(texto)
        )

        req = urllib.request.Request(
            url,
            headers={"User-Agent": "Mozilla/5.0"}
        )

        with urllib.request.urlopen(req, timeout=20) as response:
            data = json.loads(response.read().decode("utf-8", errors="ignore"))

        return "".join(parte[0] for parte in data[0] if parte and parte[0])

    except Exception:
        return texto


def consultar_vulnerabilidades_wazuh():
    hostname = obtener_hostname_vulns()

    if not hostname:
        print("No se pudo obtener el hostname")
        return []

    body = {
        "size": 1000,
        "query": {
            "match_phrase": {
                "agent.name": hostname
            }
        }
    }

    url = f"{WAZUH_INDEXER_URL}/wazuh-states-vulnerabilities*/_search"
    data = json.dumps(body).encode("utf-8")

    token = base64.b64encode(
        f"{WAZUH_INDEXER_USER}:{WAZUH_INDEXER_PASS}".encode("utf-8")
    ).decode("utf-8")

    req = urllib.request.Request(
        url,
        data=data,
        method="POST",
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Basic {token}"
        }
    )

    context = ssl._create_unverified_context()

    try:
        with urllib.request.urlopen(req, context=context, timeout=20) as response:
            raw = response.read().decode("utf-8", errors="ignore")
            parsed = json.loads(raw)

        hits = parsed.get("hits", {}).get("hits", [])
        vulns = []

        for item in hits:
            src = item.get("_source", {})
            agent = src.get("agent", {})
            package = src.get("package", {})
            vulnerability = src.get("vulnerability", {})

            score = normalizar_cvss(vulnerability.get("score", {}).get("base", ""))
            severity = traducir_severidad(vulnerability.get("severity", ""), score)

            vulns.append({
                "agent_id": agent.get("id", ""),
                "agent_name": agent.get("name", ""),
                "paquete": package.get("name", ""),
                "version_paquete": package.get("version", ""),
                "cve": vulnerability.get("id", ""),
                "severity": severity,
                "score": score,
                "descripcion": traducir_texto_es(vulnerability.get("description", "")),
                "referencia": vulnerability.get("reference", "")
            })

        return vulns

    except urllib.error.HTTPError as e:
        detalle = e.read().decode("utf-8", errors="ignore")
        print(f"ERROR HTTP WAZUH {e.code}: {detalle}")
        log(f"ERROR HTTP WAZUH {e.code}: {detalle}")
        return []

    except Exception as e:
        print(f"ERROR WAZUH: {e}")
        log(f"ERROR WAZUH: {e}")
        return []


def mostrar_vulnerabilidades(vulns):
    print()
    print("=" * 70)
    print(f"VULNERABILIDADES ENCONTRADAS: {len(vulns)}")
    print("=" * 70)

    if not vulns:
        print("No se encontraron vulnerabilidades")
        return

    for i, v in enumerate(vulns, 1):
        print()
        print("-" * 70)
        print(f"[{i}] {v['cve']}")
        print(f"Severidad  : {v['severity']}")
        print(f"CVSS       : {v['score'] if v['score'] != '' else '-'}")
        print(f"Paquete    : {v['paquete']}")
        print(f"Versión    : {v['version_paquete']}")
        print(f"Referencia : {v['referencia']}")
        print(f"Descripción: {v['descripcion']}")
        print("-" * 70)
        print()


def enviar_vulnerabilidades_a_zypher(vulns):
    try:
        body = {
            "vulnerabilidades": vulns
        }

        data = json.dumps(body).encode("utf-8")

        req = urllib.request.Request(
            ZYPHER_GUARDAR_VULNS_URL,
            data=data,
            method="POST",
            headers={
                "Content-Type": "application/json"
            }
        )

        with urllib.request.urlopen(req, timeout=30) as response:
            raw = response.read().decode("utf-8", errors="ignore")
            log(f"Vulnerabilidades enviadas a Zypher: {raw}")
            return True

    except urllib.error.HTTPError as e:
        detalle = e.read().decode("utf-8", errors="ignore")
        print(f"ERROR HTTP ZYPHER {e.code}: {detalle}")
        log(f"ERROR HTTP ZYPHER {e.code}: {detalle}")
        return False

    except Exception as e:
        print(f"ERROR ZYPHER: {e}")
        log(f"ERROR ZYPHER: {e}")
        return False







# 3.MODULO FIM

ZYPHER_FIM_URL = "https://zypher-herramienta-ciberseguridad.onrender.com/FIM%20Modulo/guardar_fim.php"

fim_estado_anterior = {}
fim_rutas_monitorizadas_anteriores = set()


def hash_archivo_fim(ruta):
    try:
        h = hashlib.sha256()
        with open(ruta, "rb") as f:
            while True:
                bloque = f.read(8192)
                if not bloque:
                    break
                h.update(bloque)
        return h.hexdigest()
    except Exception as e:
        log(f"FIM: error hash {ruta}: {e}")
        return None


def leer_archivo_fim(ruta):
    if not os.path.isfile(ruta):
        return {}

    h = hash_archivo_fim(ruta)
    if h is None:
        return {}

    return {
        ruta: {
            "tipo_elemento": "archivo",
            "hash": h
        }
    }


def leer_carpeta_fim(ruta_base):
    if not os.path.isdir(ruta_base):
        return {}

    estado = {}

    for raiz, _, archivos in os.walk(ruta_base):
        for nombre in archivos:
            ruta = os.path.join(raiz, nombre)
            h = hash_archivo_fim(ruta)
            if h is None:
                continue

            estado[ruta] = {
                "tipo_elemento": "archivo",
                "hash": h
            }

    return estado


def leer_ruta_fim(ruta, tipo):
    if tipo == "archivo":
        return leer_archivo_fim(ruta)

    if tipo == "carpeta":
        return leer_carpeta_fim(ruta)

    return {}


def obtener_rutas_fim():
    try:
        data = json.dumps({
            "accion": "obtener_rutas"
        }).encode("utf-8")

        req = urllib.request.Request(
            ZYPHER_FIM_URL,
            data=data,
            method="POST",
            headers={"Content-Type": "application/json"}
        )

        with urllib.request.urlopen(req, timeout=5) as response:
            raw = response.read().decode("utf-8", errors="ignore")
            parsed = json.loads(raw)

        if not parsed.get("ok"):
            log(f"FIM: respuesta no OK al obtener rutas: {raw}")
            return []

        rutas = parsed.get("rutas", [])
        log(f"FIM: rutas recibidas {len(rutas)}")
        return rutas

    except Exception as e:
        log(f"FIM: error obteniendo rutas: {e}")
        return []




def comparar_estados_fim(estado_anterior, estado_actual):
    eventos = []

    rutas_anteriores = set(estado_anterior.keys())
    rutas_actuales = set(estado_actual.keys())

    for ruta in rutas_actuales - rutas_anteriores:
        info = estado_actual[ruta]
        eventos.append({
            "ruta": ruta,
            "tipo_elemento": info["tipo_elemento"],
            "cambio": "Creado",
            "hash_anterior": "",
            "hash_nuevo": info["hash"]
        })

    for ruta in rutas_anteriores - rutas_actuales:
        info = estado_anterior[ruta]
        eventos.append({
            "ruta": ruta,
            "tipo_elemento": info["tipo_elemento"],
            "cambio": "Eliminado",
            "hash_anterior": info["hash"],
            "hash_nuevo": ""
        })

    for ruta in rutas_anteriores & rutas_actuales:
        anterior = estado_anterior[ruta]
        actual = estado_actual[ruta]

        if anterior["hash"] != actual["hash"]:
            eventos.append({
                "ruta": ruta,
                "tipo_elemento": actual["tipo_elemento"],
                "cambio": "Modificado",
                "hash_anterior": anterior["hash"],
                "hash_nuevo": actual["hash"]
            })

    return eventos


def enviar_eventos_fim(eventos):
    if not eventos:
        log("FIM: sin eventos")
        return True

    try:
        data = json.dumps({
            "accion": "guardar_eventos",
            "eventos": eventos
        }).encode("utf-8")

        req = urllib.request.Request(
            ZYPHER_FIM_URL,
            data=data,
            method="POST",
            headers={"Content-Type": "application/json"}
        )

        with urllib.request.urlopen(req, timeout=8) as response:
            raw = response.read().decode("utf-8", errors="ignore")
            log(f"FIM: eventos enviados: {raw}")

        return True

    except urllib.error.HTTPError as e:
        detalle = e.read().decode("utf-8", errors="ignore")
        log(f"FIM: error HTTP {e.code}: {detalle}")
        return False

    except Exception as e:
        log(f"FIM: error enviando eventos: {e}")
        return False


def analizar_fim():
    global fim_estado_anterior, fim_rutas_monitorizadas_anteriores

    try:
        log("FIM: inicio analisis")

        rutas = obtener_rutas_fim()
        if not rutas:
            log("FIM: no hay rutas para monitorizar")
            fim_estado_anterior = {}
            fim_rutas_monitorizadas_anteriores = set()
            return

        estado_actual_total = {}
        rutas_monitorizadas_actuales = set()

        for item in rutas:
            ruta = (item.get("ruta") or "").strip()
            tipo = (item.get("tipo") or "").strip()

            if not ruta or tipo not in ("archivo", "carpeta"):
                continue

            rutas_monitorizadas_actuales.add(f"{tipo}|{ruta}")

            estado = leer_ruta_fim(ruta, tipo)
            estado_actual_total.update(estado)

        log(f"FIM: elementos actuales {len(estado_actual_total)}")

        if not fim_estado_anterior:
            fim_estado_anterior = dict(estado_actual_total)
            fim_rutas_monitorizadas_anteriores = set(rutas_monitorizadas_actuales)
            log(f"FIM: estado inicial cargado ({len(fim_estado_anterior)} elementos)")
            return

        if rutas_monitorizadas_actuales != fim_rutas_monitorizadas_anteriores:
            fim_estado_anterior = dict(estado_actual_total)
            fim_rutas_monitorizadas_anteriores = set(rutas_monitorizadas_actuales)
            log("FIM: rutas monitorizadas cambiadas, nueva linea base aplicada sin generar eventos")
            return

        eventos = comparar_estados_fim(fim_estado_anterior, estado_actual_total)
        log(f"FIM: eventos detectados {len(eventos)}")

        if eventos:
            enviar_eventos_fim(eventos)
            log(f"FIM: cambios detectados: {len(eventos)}")
        else:
            log("FIM: sin cambios")

        fim_estado_anterior = dict(estado_actual_total)
        fim_rutas_monitorizadas_anteriores = set(rutas_monitorizadas_actuales)

    except Exception as e:
        log(f"FIM: error analizando: {e}")





















# 4.MODULO MONITORIZACION DE EVENTOS

ZYPHER_EVENTOS_URL = "https://zypher-herramienta-ciberseguridad.onrender.com/Monitorizacion%20modulo/guardar_eventos.php"
EVENTOS_STATE_FILE = os.path.join(APP_BASE_DIR, "eventos_state.json")
SYSMON_EXE_PATH = os.path.join(APP_BASE_DIR, "Sysmon64.exe")

EVENTOS_CONFIG = [
    {
        "canal": "Security",
        "ids": [1102, 4625, 4648, 4688, 4698, 4719, 4720, 4724, 4726, 4732, 4739, 5140, 5142, 5143, 5144],
        "tipo": "Security"
    },
    {
        "canal": "Windows PowerShell",
        "ids": [400, 403],
        "tipo": "PowerShell"
    },
    {
        "canal": "Microsoft-Windows-PowerShell/Operational",
        "ids": [4103, 4104, 4105, 4106],
        "tipo": "PowerShell"
    },
    {
        "canal": "Microsoft-Windows-Sysmon/Operational",
        "ids": [1],
        "tipo": "CMD"
    },
    {
        "canal": "Microsoft-Windows-TaskScheduler/Operational",
        "ids": [106, 140, 141],
        "tipo": "TaskScheduler"
    },
    {
        "canal": "Microsoft-Windows-Windows Defender/Operational",
        "ids": [1116, 1117, 1118, 1119, 5001, 5007],
        "tipo": "Windows Defender"
    },
    {
        "canal": "Microsoft-Windows-Windows Firewall With Advanced Security/Firewall",
        "ids": [2004, 2005, 2006, 2009, 2033],
        "tipo": "Windows Firewall"
    },
    {
        "canal": "Microsoft-Windows-AppLocker/EXE and DLL",
        "ids": [8003, 8004],
        "tipo": "AppLocker"
    },
    {
        "canal": "Microsoft-Windows-AppLocker/MSI and Script",
        "ids": [8006, 8007],
        "tipo": "AppLocker"
    },
    {
        "canal": "Microsoft-Windows-TerminalServices-RemoteConnectionManager/Operational",
        "ids": [1149],
        "tipo": "RDP"
    },
    {
        "canal": "Microsoft-Windows-TerminalServices-LocalSessionManager/Operational",
        "ids": [21, 24, 25],
        "tipo": "RDP"
    },
    {
        "canal": "System",
        "ids": [7036, 7040, 7045],
        "tipo": "Servicios"
    },
]


def cargar_json_archivo(ruta, por_defecto):
    try:
        if not os.path.exists(ruta):
            return por_defecto
        with open(ruta, "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception:
        return por_defecto


def guardar_json_archivo(ruta, data):
    try:
        os.makedirs(os.path.dirname(ruta), exist_ok=True)
        with open(ruta, "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
    except Exception as e:
        log(f"EVENTOS: error guardando JSON {ruta}: {e}")


def hostname_local():
    try:
        return socket.gethostname()
    except Exception:
        return "Equipo"


def canal_existe(nombre_canal):
    try:
        r = ejecutar_oculto(["wevtutil", "gl", nombre_canal])
        return r.returncode == 0
    except Exception:
        return False


def habilitar_canal(nombre_canal):
    try:
        if canal_existe(nombre_canal):
            ejecutar_oculto(["wevtutil", "sl", nombre_canal, "/e:true"])
            log(f"EVENTOS: canal habilitado/verificado: {nombre_canal}")
    except Exception as e:
        log(f"EVENTOS: error habilitando canal {nombre_canal}: {e}")


def servicio_existe(nombre):
    try:
        r = ejecutar_oculto(["sc", "query", nombre])
        return r.returncode == 0
    except Exception:
        return False


def configurar_servicio_auto(nombre):
    try:
        if servicio_existe(nombre):
            ejecutar_oculto(["sc", "config", nombre, "start=", "auto"])
            ejecutar_oculto(["sc", "start", nombre])
            log(f"EVENTOS: servicio verificado/iniciado: {nombre}")
    except Exception as e:
        log(f"EVENTOS: error servicio {nombre}: {e}")


def activar_powershell_logging():
    comandos = [
        [
            "reg", "add",
            r"HKLM\SOFTWARE\Policies\Microsoft\Windows\PowerShell\ScriptBlockLogging",
            "/v", "EnableScriptBlockLogging",
            "/t", "REG_DWORD",
            "/d", "1",
            "/f"
        ],
        [
            "reg", "add",
            r"HKLM\SOFTWARE\Policies\Microsoft\Windows\PowerShell\ModuleLogging",
            "/v", "EnableModuleLogging",
            "/t", "REG_DWORD",
            "/d", "1",
            "/f"
        ],
        [
            "reg", "add",
            r"HKLM\SOFTWARE\Policies\Microsoft\Windows\PowerShell\ModuleLogging\ModuleNames",
            "/v", "*",
            "/t", "REG_SZ",
            "/d", "*",
            "/f"
        ]
    ]

    for cmd in comandos:
        try:
            ejecutar_oculto(cmd)
        except Exception as e:
            log(f"EVENTOS: error activando PowerShell logging: {e}")


def sysmon_instalado():
    try:
        r = ejecutar_oculto(["sc", "query", "Sysmon64"])
        if r.returncode == 0:
            return True
        r = ejecutar_oculto(["sc", "query", "Sysmon"])
        return r.returncode == 0
    except Exception:
        return False


def descargar_sysmon():
    url = "https://live.sysinternals.com/Sysmon64.exe"
    urllib.request.urlretrieve(url, SYSMON_EXE_PATH)
    log(f"EVENTOS: Sysmon descargado en {SYSMON_EXE_PATH}")


def asegurar_sysmon():
    try:
        if sysmon_instalado():
            log("EVENTOS: Sysmon ya instalado")
            return

        if not os.path.exists(SYSMON_EXE_PATH):
            descargar_sysmon()

        r = ejecutar_oculto([SYSMON_EXE_PATH, "-accepteula", "-i"])
        if r.returncode == 0:
            log("EVENTOS: Sysmon instalado")
        else:
            log(f"EVENTOS: error instalando Sysmon: {(r.stdout or '')} {(r.stderr or '')}")
    except Exception as e:
        log(f"EVENTOS: error asegurando Sysmon: {e}")


def asegurar_eventos_windows():
    try:
        log("EVENTOS: asegurar_eventos_windows() inicio")

        activar_powershell_logging()

        canales = [
            "Security",
            "System",
            "Windows PowerShell",
            "Microsoft-Windows-PowerShell/Operational",
            "Microsoft-Windows-Sysmon/Operational",
            "Microsoft-Windows-TaskScheduler/Operational",
            "Microsoft-Windows-Windows Defender/Operational",
            "Microsoft-Windows-Windows Firewall With Advanced Security/Firewall",
            "Microsoft-Windows-AppLocker/EXE and DLL",
            "Microsoft-Windows-AppLocker/MSI and Script",
            "Microsoft-Windows-TerminalServices-RemoteConnectionManager/Operational",
            "Microsoft-Windows-TerminalServices-LocalSessionManager/Operational",
        ]

        for canal in canales:
            habilitar_canal(canal)

        configurar_servicio_auto("Schedule")
        configurar_servicio_auto("AppIDSvc")
        configurar_servicio_auto("MpsSvc")
        configurar_servicio_auto("WinDefend")

        asegurar_sysmon()
        log("EVENTOS: asegurar_eventos_windows() fin")

    except Exception as e:
        log(f"EVENTOS: error preparando entorno: {e}")


def ejecutar_powershell_json(script):
    try:
        cmd = [
            "powershell",
            "-NoProfile",
            "-ExecutionPolicy", "Bypass",
            "-Command", script
        ]
        r = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="ignore",
            creationflags=_creationflags()
        )

        salida = (r.stdout or "").strip()

        if r.returncode != 0:
            log(f"EVENTOS: PowerShell error: {(r.stderr or '').strip()}")
            return []

        if not salida:
            return []

        data = json.loads(salida)

        if isinstance(data, dict):
            return [data]

        if isinstance(data, list):
            return data

        return []

    except Exception as e:
        log(f"EVENTOS: error ejecutando PowerShell JSON: {e}")
        return []


def obtener_eventos_canal(canal, ids, ultimo_record_id):
    ids_txt = ",".join(str(x) for x in ids)

    script = f"""
$ErrorActionPreference = 'SilentlyContinue'
$log = '{canal}'
$ids = @({ids_txt})
$last = {int(ultimo_record_id)}

if (-not (Get-WinEvent -ListLog $log -ErrorAction SilentlyContinue)) {{
    @() | ConvertTo-Json -Depth 5
    exit
}}

$items = @(
    Get-WinEvent -FilterHashtable @{{ LogName = $log; Id = $ids }} -MaxEvents 200 |
    Where-Object {{ $_.RecordId -gt $last }} |
    Sort-Object RecordId |
    Select-Object `
        RecordId,
        Id,
        LogName,
        ProviderName,
        MachineName,
        @{{Name='TimeCreated';Expression={{ if ($_.TimeCreated) {{ $_.TimeCreated.ToString('yyyy-MM-dd HH:mm:ss') }} else {{ '' }} }}}},
        @{{Name='Message';Expression={{ $_.Message }}}},
        @{{Name='Xml';Expression={{ $_.ToXml() }}}}
)

$items | ConvertTo-Json -Depth 5
"""
    return ejecutar_powershell_json(script)


def extraer_datos_xml(xml_texto):
    datos = {}

    try:
        root = ET.fromstring(xml_texto)

        for data in root.findall(".//EventData/Data"):
            nombre = data.attrib.get("Name", "").strip()
            valor = (data.text or "").strip()
            if nombre:
                datos[nombre] = valor

        for data in root.findall(".//UserData//*"):
            tag = data.tag.split("}")[-1]
            valor = (data.text or "").strip()
            if tag and valor and tag not in datos:
                datos[tag] = valor

        patrones = {
            "ScriptBlockText": r"<Data Name=['\"]ScriptBlockText['\"]>(.*?)</Data>",
            "CommandLine": r"<Data Name=['\"]CommandLine['\"]>(.*?)</Data>",
            "Image": r"<Data Name=['\"]Image['\"]>(.*?)</Data>",
            "NewProcessName": r"<Data Name=['\"]NewProcessName['\"]>(.*?)</Data>",
            "ParentProcessName": r"<Data Name=['\"]ParentProcessName['\"]>(.*?)</Data>",
            "ShareName": r"<Data Name=['\"]ShareName['\"]>(.*?)</Data>",
            "RelativeTargetName": r"<Data Name=['\"]RelativeTargetName['\"]>(.*?)</Data>",
            "IpAddress": r"<Data Name=['\"]IpAddress['\"]>(.*?)</Data>",
            "SourceNetworkAddress": r"<Data Name=['\"]SourceNetworkAddress['\"]>(.*?)</Data>",
            "NetworkAddress": r"<Data Name=['\"]NetworkAddress['\"]>(.*?)</Data>",
            "SubjectUserName": r"<Data Name=['\"]SubjectUserName['\"]>(.*?)</Data>",
            "TargetUserName": r"<Data Name=['\"]TargetUserName['\"]>(.*?)</Data>",
            "User": r"<Data Name=['\"]User['\"]>(.*?)</Data>",
            "TaskName": r"<Data Name=['\"]TaskName['\"]>(.*?)</Data>",
            "ServiceFileName": r"<Data Name=['\"]ServiceFileName['\"]>(.*?)</Data>",
        }

        for clave, patron in patrones.items():
            if clave not in datos:
                m = re.search(patron, xml_texto, re.DOTALL | re.IGNORECASE)
                if m:
                    datos[clave] = m.group(1).strip()

    except Exception:
        pass

    return datos


def coger_primer_valor(dic, claves):
    for clave in claves:
        v = str(dic.get(clave, "")).strip()
        if v and v not in ["-", "::1", "N/A"]:
            return v
    return ""


def coger_usuario_evento(event_id, datos):
    mapa = {
        4625: ["TargetUserName", "SubjectUserName", "AccountName"],
        4648: ["SubjectUserName", "TargetUserName", "AccountName"],
        4688: ["SubjectUserName", "CreatorSubjectUserName", "AccountName"],
        4698: ["SubjectUserName", "Author", "TaskContent"],
        4719: ["SubjectUserName"],
        4720: ["TargetUserName", "SubjectUserName"],
        4724: ["TargetUserName", "SubjectUserName"],
        4726: ["TargetUserName", "SubjectUserName"],
        4732: ["TargetUserName", "MemberName", "SubjectUserName"],
        4739: ["SubjectUserName"],
        5140: ["SubjectUserName"],
        5142: ["SubjectUserName"],
        5143: ["SubjectUserName"],
        5144: ["SubjectUserName"],
        1149: ["Param1", "User", "UserName"],
        21: ["User"],
        24: ["User"],
        25: ["User"],
        1: ["User"],
    }
    return coger_primer_valor(
        datos,
        mapa.get(event_id, ["SubjectUserName", "TargetUserName", "User", "UserName", "AccountName"])
    )


def coger_ip_evento(event_id, datos):
    mapa = {
        4625: ["IpAddress", "SourceNetworkAddress"],
        4648: ["NetworkAddress", "IpAddress"],
        5140: ["IpAddress", "SourceAddress", "ClientAddress"],
        5142: ["IpAddress", "SourceAddress", "ClientAddress"],
        5143: ["IpAddress", "SourceAddress", "ClientAddress"],
        5144: ["IpAddress", "SourceAddress", "ClientAddress"],
        1149: ["Param3", "IpAddress", "Address"],
        21: ["Address", "ClientAddress", "IpAddress"],
        24: ["Address", "ClientAddress", "IpAddress"],
        25: ["Address", "ClientAddress", "IpAddress"],
    }
    return coger_primer_valor(
        datos,
        mapa.get(event_id, ["IpAddress", "ClientAddress", "SourceNetworkAddress", "Address", "SourceAddress"])
    )


def coger_ruta_evento(event_id, datos):
    mapa = {
        4688: ["NewProcessName", "ProcessName"],
        4698: ["TaskName"],
        5140: ["ShareName", "RelativeTargetName"],
        5142: ["ShareName"],
        5143: ["ShareName"],
        5144: ["ShareName"],
        7045: ["ServiceFileName", "ImagePath"],
        1: ["CommandLine", "Image", "ProcessName"],
        4104: ["ScriptBlockText", "Path"],
        4105: ["CommandName", "Path"],
        4106: ["CommandName", "Path"],
    }

    ruta = coger_primer_valor(
        datos,
        mapa.get(event_id, [
            "ShareName", "RelativeTargetName", "ObjectName", "ObjectPath",
            "ProcessName", "Image", "TargetFilename", "TaskName",
            "ApplicationPath", "FilePath", "Path", "NewProcessName",
            "CommandLine", "ScriptBlockText", "ServiceFileName", "ImagePath"
        ])
    )

    if ruta and len(ruta) > 500:
        ruta = ruta[:500]

    return ruta


def descripcion_evento(tipo, event_id, mensaje):
    mapa = {
        1102: "Registro de auditoría borrado",
        4625: "Error de inicio de sesión",
        4648: "Uso de credenciales explícitas",
        4688: "Proceso creado",
        4698: "Tarea programada creada",
        4719: "Auditoría modificada",
        4720: "Usuario creado",
        4724: "Intento de cambio de contraseña",
        4726: "Usuario eliminado",
        4732: "Usuario añadido a grupo",
        4739: "Política de dominio modificada",
        400: "PowerShell iniciado",
        403: "PowerShell finalizado",
        4103: "Módulo PowerShell registrado",
        4104: "Bloque de script ejecutado",
        4105: "Comando PowerShell iniciado",
        4106: "Comando PowerShell finalizado",
        1: "cmd.exe ejecutado",
        106: "Tarea registrada",
        140: "Tarea actualizada",
        141: "Tarea eliminada o deshabilitada",
        1116: "Malware detectado",
        1117: "Acción aplicada",
        1118: "Acción fallida",
        1119: "Amenaza crítica detectada",
        5001: "Protección en tiempo real desactivada",
        5007: "Configuración modificada",
        2004: "Regla añadida",
        2005: "Regla modificada",
        2006: "Regla eliminada",
        2009: "Configuración modificada",
        2033: "Perfil modificado",
        8003: "EXE/DLL en auditoría",
        8004: "EXE/DLL bloqueado",
        8006: "Script/MSI en auditoría",
        8007: "Script/MSI bloqueado",
        1149: "Conexión RDP autenticada",
        21: "Sesión RDP iniciada",
        24: "Sesión RDP desconectada",
        25: "Sesión RDP reconectada",
        5140: "Acceso a recurso compartido",
        5142: "Recurso compartido creado",
        5143: "Recurso compartido modificado",
        5144: "Recurso compartido eliminado",
        7036: "Servicio cambia de estado",
        7040: "Inicio de servicio modificado",
        7045: "Servicio instalado",
    }

    if event_id in mapa:
        return mapa[event_id]

    primera = (mensaje or "").splitlines()[0].strip()
    return primera[:180] if primera else f"Evento {event_id}"


def severidad_evento(tipo, event_id):
    mapa = {
        1102: 10,
        4625: 5,
        4648: 7,
        4698: 7,
        4719: 9,
        4720: 8,
        4724: 7,
        4726: 8,
        4732: 8,
        4739: 9,

        400: 7,
        403: 7,
        4103: 6,
        4104: 8,
        4105: 5,
        4106: 4,

        1: 7,

        106: 7,
        140: 6,
        141: 5,

        1116: 9,
        1117: 7,
        1118: 8,
        1119: 10,
        5001: 10,
        5007: 8,

        2004: 6,
        2005: 7,
        2006: 8,
        2009: 8,
        2033: 7,

        8003: 5,
        8004: 8,
        8006: 5,
        8007: 8,

        1149: 6,
        21: 5,
        24: 3,
        25: 4,

        5140: 4,
        5142: 8,
        5143: 7,
        5144: 6,

        7036: 3,
        7040: 7,
        7045: 9,
    }

    n = mapa.get(event_id, 3)

    if n <= 4:
        etiqueta = "Leve"
    elif n <= 6:
        etiqueta = "Moderada"
    elif n <= 9:
        etiqueta = "Crítica"
    else:
        etiqueta = "Muy crítica"

    return f"{etiqueta} - {n}/10"


def evento_valido_cfg(cfg, item):
    event_id = int(item.get("Id", 0))

    if cfg["tipo"] == "CMD" and event_id == 1:
        xml_txt = item.get("Xml", "") or ""
        datos = extraer_datos_xml(xml_txt)
        image = coger_primer_valor(datos, ["Image", "ProcessName"])
        image_low = image.lower()
        return image_low.endswith("\\cmd.exe") or image_low == "cmd.exe"

    return True


def convertir_evento(cfg, item):
    event_id = int(item.get("Id", 0))
    xml_txt = item.get("Xml", "") or ""
    datos = extraer_datos_xml(xml_txt)

    usuario = coger_usuario_evento(event_id, datos)
    ip_origen = coger_ip_evento(event_id, datos)
    ruta_acceso = coger_ruta_evento(event_id, datos)

    mensaje = (item.get("Message") or "").strip()
    tipo = cfg["tipo"]

    if not ruta_acceso:
        primera_linea = mensaje.splitlines()[0].strip() if mensaje else ""
        if event_id in [4104, 4105, 4106] and primera_linea:
            ruta_acceso = primera_linea[:500]

    return {
        "id_evento": event_id,
        "descripcion": descripcion_evento(tipo, event_id, mensaje),
        "tipo": tipo,
        "severidad": severidad_evento(tipo, event_id),
        "host": (item.get("MachineName") or hostname_local()).strip(),
        "fecha_evento": (item.get("TimeCreated") or datetime.now().strftime("%Y-%m-%d %H:%M:%S")).strip(),
        "usuario": usuario,
        "ip_origen": ip_origen,
        "origen": (item.get("LogName") or cfg["canal"]).strip(),
        "regla": f"{tipo}-{event_id}",
        "detalles_raw": mensaje if mensaje else xml_txt,
        "estado": "Nuevo",
        "ruta_acceso": ruta_acceso
    }


def enviar_eventos_monitorizacion(eventos):
    if not eventos:
        return True

    try:
        data = json.dumps({"eventos": eventos}).encode("utf-8")

        req = urllib.request.Request(
            ZYPHER_EVENTOS_URL,
            data=data,
            method="POST",
            headers={"Content-Type": "application/json"}
        )

        with urllib.request.urlopen(req, timeout=20) as response:
            raw = response.read().decode("utf-8", errors="ignore")
            log(f"EVENTOS: enviados {len(eventos)} -> {raw}")

        return True

    except urllib.error.HTTPError as e:
        detalle = e.read().decode("utf-8", errors="ignore")
        log(f"EVENTOS: HTTP {e.code}: {detalle}")
        return False

    except Exception as e:
        log(f"EVENTOS: error enviando eventos: {e}")
        return False


def analizar_eventos():
    log("EVENTOS: analizar_eventos() arrancó")
    estado = cargar_json_archivo(EVENTOS_STATE_FILE, {})
    eventos_detectados = []

    for cfg in EVENTOS_CONFIG:
        canal = cfg["canal"]
        ultimo = int(estado.get(canal, 0))
        log(f"EVENTOS: canal={canal} ultimo_record={ultimo}")

        try:
            items = obtener_eventos_canal(canal, cfg["ids"], ultimo)
            log(f"EVENTOS: canal={canal} items_recibidos={len(items)}")
            max_record = ultimo

            for item in items:
                record_id = int(item.get("RecordId", 0))
                if record_id > max_record:
                    max_record = record_id

                if not evento_valido_cfg(cfg, item):
                    continue

                eventos_detectados.append(convertir_evento(cfg, item))

            estado[canal] = max_record

        except Exception as e:
            log(f"EVENTOS: error analizando canal {canal}: {e}")

    log(f"EVENTOS: total_detectados={len(eventos_detectados)}")

    if eventos_detectados:
        ok = enviar_eventos_monitorizacion(eventos_detectados)
        log(f"EVENTOS: envio_ok={ok}")
        if ok:
            guardar_json_archivo(EVENTOS_STATE_FILE, estado)
    else:
        guardar_json_archivo(EVENTOS_STATE_FILE, estado)
        log("EVENTOS: sin eventos nuevos")


def ciclo_fim():
    while True:
        try:
            print("\n[FIM] inicio")
            analizar_fim()
            print("[FIM] fin")
        except Exception as e:
            log(f"FIM hilo error: {e}")

        time.sleep(3)


def ciclo_secundario():
    while True:
        try:
            print("\n[CIS] inicio")
            analizar_todo()
            print("[CIS] fin")
        except Exception as e:
            log(f"CIS hilo error: {e}")

        try:
            print("\n[VULNERABILIDADES] inicio")
            vulnerabilidades = consultar_vulnerabilidades_wazuh()
            enviar_vulnerabilidades_a_zypher(vulnerabilidades)
            print(f"[VULNERABILIDADES] encontradas: {len(vulnerabilidades)}")
        except Exception as e:
            log(f"VULNS hilo error: {e}")

        time.sleep(60)


def ciclo_eventos():
    log("EVENTOS: hilo ciclo_eventos arrancado")
    asegurar_eventos_windows()

    while True:
        try:
            print("\n[EVENTOS] inicio")
            analizar_eventos()
            print("[EVENTOS] fin")
        except Exception as e:
            log(f"EVENTOS hilo error: {e}")

        time.sleep(5)















# 5.MODULO POLITICAS DE SEGURIDAD

ZYPHER_POLITICAS_BASE_URL = "https://zypher-herramienta-ciberseguridad.onrender.com/Politicas%20seguridad%20Modulo"

ZYPHER_POLITICAS_GET_URL = f"{ZYPHER_POLITICAS_BASE_URL}/agente_get_orden.php"
ZYPHER_POLITICAS_RESULTADO_URL = f"{ZYPHER_POLITICAS_BASE_URL}/agente_resultado_orden.php"
ZYPHER_POLITICAS_LISTAR_URL = f"{ZYPHER_POLITICAS_BASE_URL}/agente_listar_politicas.php"
ZYPHER_POLITICAS_GUARDAR_ESTADO_URL = f"{ZYPHER_POLITICAS_BASE_URL}/agente_guardar_estado_politica.php"

ZYPHER_POLITICAS_TOKEN = "ZYHPER_POLITICAS_TOKEN_2026"
ZYPHER_POLITICAS_AGENTE_ID = "windows-agent-001"


AUDITPOL_ES = {
    'subcategory:"File System"': 'subcategory:"Sistema de archivos"',
    'subcategory:"Registry"': 'subcategory:"Registro"',
    'subcategory:"User Account Management"': 'subcategory:"Administración de cuentas de usuario"',
    'subcategory:"Security Group Management"': 'subcategory:"Administración de grupos de seguridad"',
    'subcategory:"Audit Policy Change"': 'subcategory:"Cambio en la directiva de auditoría"',
    'subcategory:"Authentication Policy Change"': 'subcategory:"Cambio de la directiva de autenticación"',
    'subcategory:"Credential Validation"': 'subcategory:"Validación de credenciales"',
    'subcategory:"Logon"': 'subcategory:"Inicio de sesión"',
    'subcategory:"Logoff"': 'subcategory:"Cerrar sesión"',
    'subcategory:"Account Lockout"': 'subcategory:"Bloqueo de cuenta"',
    'subcategory:"Process Creation"': 'subcategory:"Creación del proceso"',
    'subcategory:"Process Termination"': 'subcategory:"Finalización del proceso"',
    'subcategory:"Security State Change"': 'subcategory:"Cambio de estado de seguridad"',
    'subcategory:"Security System Extension"': 'subcategory:"Extensión del sistema de seguridad"',
    'subcategory:"System Integrity"': 'subcategory:"Integridad del sistema"',
    'subcategory:"Sensitive Privilege Use"': 'subcategory:"Uso de privilegio confidencial"',

    'category:"System"': 'category:"Sistema"',
    'category:"Logon/Logoff"': 'category:"Inicio/cierre de sesión"',
    'category:"Account Management"': 'category:"Administración de cuentas"',
    'category:"Policy Change"': 'category:"Cambio de plan"',
    'category:"Detailed Tracking"': 'category:"Seguimiento detallado"',
}


def politicas_http_get(url, params):
    query = urllib.parse.urlencode(params)
    full_url = f"{url}?{query}"

    with urllib.request.urlopen(full_url, timeout=20) as response:
        raw = response.read().decode("utf-8", errors="ignore")
        return json.loads(raw)


def politicas_http_post(url, data):
    payload = json.dumps(data).encode("utf-8")

    req = urllib.request.Request(
        url,
        data=payload,
        method="POST",
        headers={"Content-Type": "application/json"}
    )

    with urllib.request.urlopen(req, timeout=20) as response:
        raw = response.read().decode("utf-8", errors="ignore")
        return json.loads(raw)


def obtener_orden_politicas():
    data = politicas_http_get(
        ZYPHER_POLITICAS_GET_URL,
        {
            "token": ZYPHER_POLITICAS_TOKEN,
            "agente_id": ZYPHER_POLITICAS_AGENTE_ID
        }
    )

    if not data.get("ok"):
        raise Exception(data.get("error", "Error obteniendo orden"))

    return data.get("orden")


def listar_politicas_seguridad():
    data = politicas_http_get(
        ZYPHER_POLITICAS_LISTAR_URL,
        {
            "token": ZYPHER_POLITICAS_TOKEN,
            "agente_id": ZYPHER_POLITICAS_AGENTE_ID
        }
    )

    if not data.get("ok"):
        raise Exception(data.get("error", "Error listando políticas"))

    return data.get("politicas", [])


def enviar_resultado_politicas(orden_id, estado, resultado="", error=""):
    data = {
        "token": ZYPHER_POLITICAS_TOKEN,
        "orden_id": orden_id,
        "estado": estado,
        "resultado": resultado,
        "error": error
    }

    resp = politicas_http_post(ZYPHER_POLITICAS_RESULTADO_URL, data)

    if not resp.get("ok"):
        raise Exception(resp.get("error", "Error enviando resultado"))


def guardar_estado_politica(politica_id, cumple, valor_actual="", valor_recomendado="", detalle=""):
    data = {
        "token": ZYPHER_POLITICAS_TOKEN,
        "agente_id": ZYPHER_POLITICAS_AGENTE_ID,
        "politica_id": int(politica_id),
        "cumple": bool(cumple),
        "valor_actual": valor_actual or "",
        "valor_recomendado": valor_recomendado or "",
        "detalle": detalle or ""
    }

    resp = politicas_http_post(ZYPHER_POLITICAS_GUARDAR_ESTADO_URL, data)

    if not resp.get("ok"):
        raise Exception(resp.get("error", "Error guardando estado de política"))


def comando_politica_permitido(comando):
    comando = (comando or "").strip().lower()

    especiales = [
        "secedit_password_complexity",
        "secedit_clear_text_password",
        "secedit_deny_interactive_guest",
        "secedit_deny_network_guest",
        "secedit_remote_interactive_logon",
    ]

    permitidos = [
        "net accounts",
        "net user",
        "auditpol",
        "reg add",
        "reg query",
        "netsh advfirewall",
        "fsutil behavior",
        "certutil",
        "manage-bde",
        "secedit /export",
        "powershell",
        "cmd /c",
    ]

    if comando in especiales:
        return True

    return any(comando.startswith(p) for p in permitidos)


def traducir_auditpol_es(comando):
    nuevo = comando

    if not nuevo.lower().startswith("auditpol"):
        return nuevo

    for en, es in AUDITPOL_ES.items():
        nuevo = nuevo.replace(en, es)

    return nuevo


def ejecutar_comando_politica(comando):
    comando = (comando or "").strip()

    log(f"POLITICAS: ejecutando comando: {comando}")

    r = subprocess.run(
        comando,
        shell=True,
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="ignore",
        creationflags=_creationflags()
    )

    salida = ((r.stdout or "") + "\n" + (r.stderr or "")).strip()

    if r.returncode != 0 and comando.lower().startswith("auditpol"):
        comando_es = traducir_auditpol_es(comando)

        if comando_es != comando:
            log(f"POLITICAS: reintentando auditpol en español: {comando_es}")

            r = subprocess.run(
                comando_es,
                shell=True,
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="ignore",
                creationflags=_creationflags()
            )

            salida = ((r.stdout or "") + "\n" + (r.stderr or "")).strip()

    if comando.lower().startswith("secedit /export"):
        cfg_path = os.path.join(tempfile.gettempdir(), "zypher_check.cfg")

        if os.path.exists(cfg_path):
            try:
                with open(cfg_path, "r", encoding="utf-16", errors="ignore") as f:
                    contenido_cfg = f.read()

                salida = salida + "\n\nCONTENIDO_CFG:\n" + contenido_cfg

            except Exception as e:
                salida = salida + f"\n\nERROR_LEYENDO_CFG: {e}"

    return r.returncode, salida


    salida = ((r.stdout or "") + "\n" + (r.stderr or "")).strip()

    if r.returncode != 0 and comando.lower().startswith("auditpol"):
        comando_es = traducir_auditpol_es(comando)

        if comando_es != comando:
            log(f"POLITICAS: reintentando auditpol en español: {comando_es}")

            r = subprocess.run(
                comando_es,
                shell=True,
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="ignore",
                creationflags=_creationflags()
            )

            salida = ((r.stdout or "") + "\n" + (r.stderr or "")).strip()

    return r.returncode, salida


def aplicar_secedit_politica(seccion, lineas, area):
    temp_dir = tempfile.gettempdir()
    inf_path = os.path.join(temp_dir, "zypher_politicas.inf")
    sdb_path = os.path.join(temp_dir, "zypher_politicas.sdb")

    contenido = [
        "[Unicode]",
        "Unicode=yes",
        f"[{seccion}]",
        *lineas,
        "[Version]",
        'signature="$CHICAGO$"',
        "Revision=1"
    ]

    try:
        with open(inf_path, "w", encoding="utf-16") as f:
            f.write("\n".join(contenido))

        comando = f'secedit /configure /db "{sdb_path}" /cfg "{inf_path}" /areas {area}'
        return ejecutar_comando_politica(comando)

    finally:
        try:
            if os.path.exists(inf_path):
                os.remove(inf_path)
            if os.path.exists(sdb_path):
                os.remove(sdb_path)
        except Exception as e:
            log(f"POLITICAS: no se pudieron borrar temporales: {e}")


def ejecutar_politica_especial(codigo):
    codigo = (codigo or "").strip()

    if codigo == "secedit_password_complexity":
        return aplicar_secedit_politica(
            "System Access",
            ["PasswordComplexity = 1"],
            "SECURITYPOLICY"
        )

    if codigo == "secedit_clear_text_password":
        return aplicar_secedit_politica(
            "System Access",
            ["ClearTextPassword = 0"],
            "SECURITYPOLICY"
        )

    if codigo == "secedit_deny_interactive_guest":
        return aplicar_secedit_politica(
            "Privilege Rights",
            ["SeDenyInteractiveLogonRight = Invitado"],
            "USER_RIGHTS"
        )

    if codigo == "secedit_deny_network_guest":
        return aplicar_secedit_politica(
            "Privilege Rights",
            ["SeDenyNetworkLogonRight = Invitado"],
            "USER_RIGHTS"
        )

    if codigo == "secedit_remote_interactive_logon":
        return aplicar_secedit_politica(
            "Privilege Rights",
            ["SeRemoteInteractiveLogonRight = *S-1-5-32-544,*S-1-5-32-555"],
            "USER_RIGHTS"
        )

    return 1, f"Política especial no reconocida: {codigo}"


def normalizar_texto_politicas(txt):
    txt = (txt or "").lower()
    txt = txt.replace("\r", "\n")
    txt = txt.replace("\t", " ")
    txt = txt.replace("á", "a").replace("é", "e").replace("í", "i").replace("ó", "o").replace("ú", "u")
    txt = txt.replace("ñ", "n")
    while "  " in txt:
        txt = txt.replace("  ", " ")
    return txt


def salida_contiene_numero_linea(salida, claves, esperado):
    salida_n = normalizar_texto_politicas(salida)

    for linea in salida_n.splitlines():
        linea = linea.strip()

        for clave in claves:
            if clave in linea:
                return str(esperado).lower() in linea

    return False


def salida_contiene_valor(salida, valores):
    salida_n = normalizar_texto_politicas(salida)

    for valor in valores:
        if normalizar_texto_politicas(valor) in salida_n:
            return True

    return False


def comprobar_auditpol(salida, esperado):
    salida_n = normalizar_texto_politicas(salida)
    esperado_n = normalizar_texto_politicas(esperado)

    if "sin auditoria" in salida_n:
        return False

    if esperado_n == "success and failure":
        return "aciertos y errores" in salida_n or "success and failure" in salida_n

    if esperado_n == "success":
        return ("aciertos" in salida_n or "success" in salida_n) and "errores" not in salida_n

    return salida_contiene_valor(salida, [esperado])


def comprobar_politica_por_codigo(codigo, salida, valor_recomendado):
    codigo = (codigo or "").strip().upper()
    valor = (valor_recomendado or "").strip()
    salida_n = normalizar_texto_politicas(salida)

    if codigo == "MIN_PASSWORD_LENGTH":
        return salida_contiene_numero_linea(salida, ["longitud minima de contrasena"], valor)

    if codigo == "MAX_PASSWORD_AGE":
        return salida_contiene_numero_linea(salida, ["duracion max. de contrasena", "duracion max"], valor)

    if codigo == "PASSWORD_HISTORY_SIZE":
        return salida_contiene_numero_linea(salida, ["historial de contrasenas"], valor)

    if codigo == "LOCKOUT_BAD_COUNT":
        return salida_contiene_numero_linea(salida, ["umbral de bloqueo"], valor)

    if codigo == "LOCKOUT_DURATION":
        return salida_contiene_numero_linea(salida, ["duracion de bloqueo"], valor)

    if codigo == "RESET_LOCKOUT_COUNT":
        return salida_contiene_numero_linea(salida, ["ventana de obs. de bloqueo"], valor)

    if codigo == "PASSWORD_COMPLEXITY":
        return "passwordcomplexity = 1" in salida_n

    if codigo == "CLEAR_TEXT_PASSWORD":
        return "cleartextpassword = 0" in salida_n

    if codigo in [
        "ADV_FILE_SYSTEM",
        "ADV_REGISTRY",
        "ADV_USER_ACCOUNT_MANAGEMENT",
        "ADV_SECURITY_GROUP_MANAGEMENT",
        "ADV_AUDIT_POLICY_CHANGE",
        "ADV_AUTH_POLICY_CHANGE",
        "ADV_CREDENTIAL_VALIDATION",
        "ADV_LOGON",
        "ADV_LOGOFF",
        "ADV_ACCOUNT_LOCKOUT",
        "ADV_PROCESS_CREATION",
        "ADV_PROCESS_TERMINATION",
        "ADV_SECURITY_STATE_CHANGE",
        "ADV_SECURITY_SYSTEM_EXTENSION",
        "ADV_SYSTEM_INTEGRITY",
        "ADV_SENSITIVE_PRIVILEGE_USE",
        "AUDIT_SYSTEM_EVENTS",
        "AUDIT_LOGON_EVENTS",
        "AUDIT_ACCOUNT_MANAGE",
        "AUDIT_POLICY_CHANGE",
        "AUDIT_PROCESS_TRACKING",
    ]:
        return comprobar_auditpol(salida, valor)

    if codigo == "DISABLE_GUEST_ACCOUNT":
        return "cuenta activa" in salida_n and "no" in salida_n

    if codigo == "DISABLE_ADMIN_ACCOUNT":
        return "cuenta activa" in salida_n and "no" in salida_n

    if codigo in [
        "DONT_DISPLAY_LAST_USER",
        "ENABLE_LUA",
        "CONSENT_PROMPT_ADMIN",
        "CONSENT_PROMPT_USER",
        "LIMIT_BLANK_PASSWORD_USE",
        "NO_LM_HASH",
        "RESTRICT_ANONYMOUS",
        "RESTRICT_ANONYMOUS_SAM",
        "SRP_ENABLE",
        "SRP_TRANSPARENT_ENABLED",
    ]:
        try:
            valor_int = int(valor)
            return (
                f"0x{valor_int:x}" in salida_n
                or f"reg_dword {valor}" in salida_n
                or f" {valor}" in salida_n
            )
        except Exception:
            return salida_contiene_valor(salida, [valor])

    if codigo in [
        "DENY_INTERACTIVE_LOGON_GUEST",
        "DENY_NETWORK_LOGON_GUEST",
        "REMOTE_INTERACTIVE_LOGON",
    ]:
        return normalizar_texto_politicas(valor) in salida_n

    if codigo in [
        "FIREWALL_PUBLIC_ON",
        "FIREWALL_PRIVATE_ON",
        "FIREWALL_DOMAIN_ON",
    ]:
        return "estado activar" in salida_n or "state on" in salida_n

    if codigo in [
        "BLOCK_SMB_IN",
        "BLOCK_RDP_IN",
        "BLOCK_SMB_OUT",
        "BLOCK_RDP_OUT",
    ]:
        return "bloquear" in salida_n or "block" in salida_n

    if codigo == "EFS_ENABLED":
        return "disableencryption = 0" in salida_n

    if codigo == "BITLOCKER_STATUS":
        return "proteccion activada" in salida_n or "protection on" in salida_n

    if codigo == "DPAPI_CERT_CHECK":
        return "certificado" in salida_n or "certificate" in salida_n

    return salida_contiene_valor(salida, [valor])


def verificar_politica(politica):
    politica_id = int(politica.get("politica_id"))
    codigo_politica = politica.get("codigo", "")
    nombre = politica.get("nombre", "")
    comando_verificar = politica.get("comando_verificar", "")
    valor_recomendado = politica.get("valor_recomendado", "")

    if not comando_verificar:
        guardar_estado_politica(
            politica_id,
            False,
            "",
            valor_recomendado,
            "La política no tiene comando_verificar"
        )
        return False

    if not comando_politica_permitido(comando_verificar):
        guardar_estado_politica(
            politica_id,
            False,
            "",
            valor_recomendado,
            f"Comando de verificación bloqueado: {comando_verificar}"
        )
        return False

    codigo_salida, salida = ejecutar_comando_politica(comando_verificar)

    if codigo_salida != 0:
        cumple = False
    else:
        cumple = comprobar_politica_por_codigo(
            codigo_politica,
            salida,
            valor_recomendado
        )

    detalle = (
        f"POLITICA: {nombre}\n"
        f"CODIGO: {codigo_politica}\n"
        f"COMANDO_VERIFICAR: {comando_verificar}\n"
        f"VALOR_RECOMENDADO: {valor_recomendado}\n"
        f"CODIGO_SALIDA: {codigo_salida}\n"
        f"CUMPLE: {cumple}\n\n"
        f"SALIDA:\n{salida}"
    )

    guardar_estado_politica(
        politica_id,
        cumple,
        salida,
        valor_recomendado,
        detalle
    )

    if cumple:
        log(f"POLITICAS: verificación correcta politica_id={politica_id}")
    else:
        log(f"POLITICAS: verificación incorrecta politica_id={politica_id}")

    return cumple


def verificar_todas_las_politicas():
    politicas = listar_politicas_seguridad()

    log(f"POLITICAS: verificando políticas activas total={len(politicas)}")

    for politica in politicas:
        try:
            verificar_politica(politica)
            time.sleep(0.2)
        except Exception as e:
            log(f"POLITICAS: error verificando politica_id={politica.get('politica_id')}: {e}")


def procesar_orden_politicas(orden):
    orden_id = int(orden.get("orden_id"))
    accion = orden.get("accion")
    politica_id = int(orden.get("politica_id"))
    nombre = orden.get("nombre", "")
    comando_aplicar = orden.get("comando_aplicar", "")
    comando_verificar = orden.get("comando_verificar", "")
    valor_recomendado = orden.get("valor_recomendado", "")

    log(f"POLITICAS: orden recibida id={orden_id} accion={accion} politica={nombre}")

    try:
        if accion != "aplicar":
            enviar_resultado_politicas(
                orden_id,
                "error",
                "",
                f"Acción no permitida: {accion}"
            )
            return

        if not comando_politica_permitido(comando_aplicar):
            enviar_resultado_politicas(
                orden_id,
                "error",
                "",
                f"Comando bloqueado: {comando_aplicar}"
            )
            return

        if comando_aplicar.startswith("secedit_"):
            codigo_aplicar, salida_aplicar = ejecutar_politica_especial(comando_aplicar)
        else:
            codigo_aplicar, salida_aplicar = ejecutar_comando_politica(comando_aplicar)

        if codigo_aplicar != 0:
            enviar_resultado_politicas(
                orden_id,
                "error",
                salida_aplicar,
                f"Error aplicando política. Código salida: {codigo_aplicar}"
            )

            guardar_estado_politica(
                politica_id,
                False,
                salida_aplicar,
                valor_recomendado,
                f"Error aplicando política. Código salida: {codigo_aplicar}"
            )

            log(f"POLITICAS: error aplicando id={orden_id} codigo={codigo_aplicar}")
            return

        politica = {
            "politica_id": politica_id,
            "codigo": orden.get("codigo", ""),
            "nombre": nombre,
            "comando_verificar": comando_verificar,
            "valor_recomendado": valor_recomendado,
        }

        cumple = verificar_politica(politica)

        resultado_final = (
            f"APLICACION:\n{salida_aplicar}\n\n"
            f"VERIFICACION_POSTERIOR:\n"
            f"{'CORRECTA' if cumple else 'INCORRECTA'}"
        )

        if cumple:
            enviar_resultado_politicas(orden_id, "completada", resultado_final, "")
            log(f"POLITICAS: orden aplicada y verificada id={orden_id}")
        else:
            enviar_resultado_politicas(
                orden_id,
                "error",
                resultado_final,
                "La política se aplicó, pero la verificación posterior no cumple"
            )
            log(f"POLITICAS: aplicada pero sigue incorrecta id={orden_id}")

    except Exception as e:
        enviar_resultado_politicas(orden_id, "error", "", str(e))
        log(f"POLITICAS: error procesando orden {orden_id}: {e}")


def analizar_orden_politicas():
    try:
        orden = obtener_orden_politicas()

        if not orden:
            return

        procesar_orden_politicas(orden)

    except Exception as e:
        log(f"POLITICAS: error general en orden: {e}")


def ciclo_politicas():
    log("POLITICAS: hilo ciclo_politicas arrancado")

    ultima_verificacion = 0
    intervalo_verificacion = 30

    while True:
        try:
            analizar_orden_politicas()

            ahora = time.time()

            if ahora - ultima_verificacion >= intervalo_verificacion:
                print("\n[POLITICAS] verificación automática inicio")
                verificar_todas_las_politicas()
                print("[POLITICAS] verificación automática fin")
                ultima_verificacion = ahora

        except Exception as e:
            log(f"POLITICAS hilo error: {e}")

        time.sleep(1)


def ciclo_principal():
    asegurar_wazuh_agent()
    log("MAIN: ciclo_principal arrancado")

    hilo_fim = threading.Thread(target=ciclo_fim, daemon=True)
    hilo_secundario = threading.Thread(target=ciclo_secundario, daemon=True)
    hilo_eventos = threading.Thread(target=ciclo_eventos, daemon=True)
    hilo_politicas = threading.Thread(target=ciclo_politicas, daemon=True)

    hilo_fim.start()
    hilo_secundario.start()
    hilo_eventos.start()
    hilo_politicas.start()

    log("MAIN: hilos lanzados")

    while True:
        time.sleep(60)


if __name__ == "__main__":
    ciclo_principal()
