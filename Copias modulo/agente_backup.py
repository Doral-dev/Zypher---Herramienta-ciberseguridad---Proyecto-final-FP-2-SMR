import json
import time
import zipfile
import secrets
import requests
import boto3

from pathlib import Path
from datetime import datetime
from cryptography.hazmat.primitives.ciphers.aead import AESGCM


AGENTE_ID = "windows-agent-001"

ZYPHER_GET_URL = "https://zypher-herramienta-ciberseguridad.onrender.com/agente_get_backup.php"
ZYPHER_RESULTADO_URL = "https://zypher-herramienta-ciberseguridad.onrender.com/agente_resultado_backup.php"

TOKEN = "ZYPHER_BACKUP_TOKEN_2026"

R2_ENDPOINT = "https://TU_ACCOUNT_ID.r2.cloudflarestorage.com"
R2_ACCESS_KEY = "TU_R2_ACCESS_KEY"
R2_SECRET_KEY = "TU_R2_SECRET_KEY"
R2_BUCKET = "zypher-backups"

BASE_DIR = Path("C:/Zypher/Backups")
TEMP_DIR = BASE_DIR / "temp"
KEY_FILE = BASE_DIR / "backup.key"

POLL_SECONDS = 60


def asegurar_directorios():
    BASE_DIR.mkdir(parents=True, exist_ok=True)
    TEMP_DIR.mkdir(parents=True, exist_ok=True)


def obtener_clave():
    asegurar_directorios()

    if KEY_FILE.exists():
        return KEY_FILE.read_bytes()

    key = AESGCM.generate_key(bit_length=256)
    KEY_FILE.write_bytes(key)
    return key


def ruta_carpeta(codigo):
    usuario = Path.home()

    rutas = {
        "desktop": usuario / "Desktop",
        "documents": usuario / "Documents",
        "downloads": usuario / "Downloads",
        "pictures": usuario / "Pictures",
        "videos": usuario / "Videos",
        "music": usuario / "Music"
    }

    return rutas.get(codigo)


def parse_fecha(fecha):
    if not fecha:
        return None

    texto = str(fecha).replace("Z", "").strip()

    formatos = [
        "%Y-%m-%d %H:%M:%S.%f",
        "%Y-%m-%d %H:%M:%S",
        "%Y-%m-%dT%H:%M:%S.%f",
        "%Y-%m-%dT%H:%M:%S"
    ]

    for formato in formatos:
        try:
            return datetime.strptime(texto[:26], formato)
        except Exception:
            pass

    return None


def esta_activa(item):
    valor = item.get("activa")

    if valor is True:
        return True

    if str(valor).lower() in ["1", "true", "t", "yes", "si", "sí"]:
        return True

    return False


def toca_backup(item):
    if not esta_activa(item):
        return False

    ultimo = parse_fecha(item.get("ultimo_backup_ok"))
    frecuencia_dias = int(item.get("frecuencia_dias") or 7)

    if ultimo is None:
        return True

    ahora = datetime.now()
    diferencia = ahora - ultimo

    return diferencia.days >= frecuencia_dias


def consultar_zypher():
    respuesta = requests.get(
        ZYPHER_GET_URL,
        params={
            "token": TOKEN,
            "agente_id": AGENTE_ID
        },
        timeout=30
    )

    respuesta.raise_for_status()
    return respuesta.json()


def enviar_resultado(orden_id, estado, carpetas, archivo_r2=None, tamano_mb=None, mensaje=None):
    payload = {
        "token": TOKEN,
        "agente_id": AGENTE_ID,
        "orden_id": orden_id,
        "estado": estado,
        "carpetas": carpetas,
        "archivo_r2": archivo_r2,
        "tamano_mb": tamano_mb,
        "mensaje": mensaje
    }

    respuesta = requests.post(
        ZYPHER_RESULTADO_URL,
        json=payload,
        timeout=60
    )

    respuesta.raise_for_status()
    return respuesta.json()


def crear_zip(carpetas_codigos):
    asegurar_directorios()

    fecha = datetime.now().strftime("%Y%m%d_%H%M%S")
    zip_path = TEMP_DIR / f"backup_{AGENTE_ID}_{fecha}.zip"

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        info = {
            "agente_id": AGENTE_ID,
            "fecha": datetime.now().isoformat(),
            "carpetas": carpetas_codigos
        }

        zf.writestr(
            "info_backup.json",
            json.dumps(info, ensure_ascii=False, indent=2)
        )

        for codigo in carpetas_codigos:
            base = ruta_carpeta(codigo)

            if base is None or not base.exists():
                continue

            for archivo in base.rglob("*"):
                if not archivo.is_file():
                    continue

                try:
                    rel = archivo.relative_to(base)
                    nombre_zip = f"{codigo}/{rel}".replace("\\", "/")
                    zf.write(archivo, nombre_zip)
                except Exception:
                    continue

    return zip_path


def cifrar_archivo(zip_path):
    key = obtener_clave()
    aesgcm = AESGCM(key)

    nonce = secrets.token_bytes(12)
    data = zip_path.read_bytes()
    encrypted = aesgcm.encrypt(nonce, data, None)

    enc_path = zip_path.with_suffix(".zip.zypher.enc")
    enc_path.write_bytes(nonce + encrypted)

    return enc_path


def subir_r2(enc_path):
    s3 = boto3.client(
        "s3",
        endpoint_url=R2_ENDPOINT,
        aws_access_key_id=R2_ACCESS_KEY,
        aws_secret_access_key=R2_SECRET_KEY,
        region_name="auto"
    )

    clave_r2 = f"{AGENTE_ID}/{enc_path.name}"

    s3.upload_file(str(enc_path), R2_BUCKET, clave_r2)

    return clave_r2


def ejecutar_backup(carpetas, orden_id=None):
    zip_path = None
    enc_path = None

    try:
        carpetas = list(dict.fromkeys(carpetas))

        if not carpetas:
            enviar_resultado(
                orden_id=orden_id,
                estado="error",
                carpetas=[],
                mensaje="No hay carpetas activas para copiar"
            )
            return

        zip_path = crear_zip(carpetas)
        enc_path = cifrar_archivo(zip_path)
        archivo_r2 = subir_r2(enc_path)

        tamano_mb = round(enc_path.stat().st_size / 1024 / 1024, 2)

        enviar_resultado(
            orden_id=orden_id,
            estado="completada",
            carpetas=carpetas,
            archivo_r2=archivo_r2,
            tamano_mb=tamano_mb,
            mensaje="Backup cifrado y subido correctamente"
        )

    except Exception as e:
        try:
            enviar_resultado(
                orden_id=orden_id,
                estado="error",
                carpetas=carpetas,
                mensaje=str(e)
            )
        except Exception:
            pass

    finally:
        for archivo in [zip_path, enc_path]:
            try:
                if archivo and archivo.exists():
                    archivo.unlink()
            except Exception:
                pass


def main():
    asegurar_directorios()

    print("[BACKUP] Agente de copias iniciado")

    while True:
        try:
            data = consultar_zypher()

            if not data.get("ok"):
                print("[BACKUP] Respuesta no válida de Zypher")
                time.sleep(POLL_SECONDS)
                continue

            orden = data.get("orden")
            configuracion = data.get("configuracion", [])

            carpetas_activas = [
                item["carpeta_codigo"]
                for item in configuracion
                if esta_activa(item)
            ]

            carpetas_debidas = [
                item["carpeta_codigo"]
                for item in configuracion
                if toca_backup(item)
            ]

            if orden:
                print("[BACKUP] Orden manual recibida")
                ejecutar_backup(carpetas_activas, orden_id=orden["id"])

            elif carpetas_debidas:
                print("[BACKUP] Ejecutando backup automático")
                ejecutar_backup(carpetas_debidas, orden_id=None)

        except Exception as e:
            print(f"[BACKUP] Error: {e}")

        time.sleep(POLL_SECONDS)


if __name__ == "__main__":
    main()
