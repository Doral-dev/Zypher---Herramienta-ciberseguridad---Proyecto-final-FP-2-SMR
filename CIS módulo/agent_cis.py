import os
import re
import time
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


def conectar_bd():
    return psycopg2.connect(
        host=DB_HOST,
        port=DB_PORT,
        dbname=DB_NAME,
        user=DB_USER,
        password=DB_PASSWORD
    )


def buscar_orden_pendiente(cur):
    cur.execute("""
        SELECT id
        FROM cis_orders
        WHERE accion = 'reanalyze_cis' AND estado = 'pendiente'
        ORDER BY id ASC
        LIMIT 1
    """)
    return cur.fetchone()


def marcar_orden(cur, order_id, estado):
    cur.execute("""
        UPDATE cis_orders
        SET estado = %s
        WHERE id = %s
    """, (estado, order_id))


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


def exportar_secpol():
    fd, temp_path = tempfile.mkstemp(suffix=".cfg")
    os.close(fd)

    try:
        result = subprocess.run(
            ["secedit", "/export", "/cfg", temp_path],
            capture_output=True,
            text=True,
            shell=False
        )

        if result.returncode != 0:
            raise Exception(result.stderr.strip() or result.stdout.strip() or "Error ejecutando secedit")

        values = {}
        with open(temp_path, "r", encoding="utf-16", errors="ignore") as f:
            for line in f:
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


def main():
    print("Agente CIS iniciado. Esperando órdenes...")

    while True:
        conn = None
        cur = None
        order_id = None

        try:
            conn = conectar_bd()
            conn.autocommit = False
            cur = conn.cursor()

            orden = buscar_orden_pendiente(cur)

            if orden:
                order_id = orden[0]
                print(f"Orden pendiente encontrada: {order_id}")

                marcar_orden(cur, order_id, "en_proceso")
                conn.commit()

                politicas = obtener_politicas(cur)
                sec_values = exportar_secpol()
                actualizar_politicas(cur, politicas, sec_values)

                marcar_orden(cur, order_id, "completado")
                conn.commit()

                print(f"Orden {order_id} completada correctamente.")

            else:
                print("Sin órdenes pendientes...")

        except Exception as e:
            print(f"Error en el agente: {e}")

            try:
                if conn:
                    conn.rollback()
                if cur and order_id is not None:
                    marcar_orden(cur, order_id, "error")
                    conn.commit()
            except Exception:
                pass

        finally:
            if cur:
                cur.close()
            if conn:
                conn.close()

        time.sleep(5)


if __name__ == "__main__":
    main()
