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

import psycopg2


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


def main():
    asegurar_instalacion()

    if not asegurar_instancia_unica():
        sys.exit(0)

    log("Agente iniciado")

    while True:
        analizar_todo()
        time.sleep(15)


if __name__ == "__main__":
    main()
