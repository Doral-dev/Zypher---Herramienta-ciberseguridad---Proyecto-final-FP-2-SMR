import os
import sys
import json
import time
import hmac
import hashlib
import zipfile
import secrets
import subprocess
import urllib.parse
import urllib.request
import urllib.error

from pathlib import Path
from datetime import datetime


AGENTE_ID = "windows-agent-001"

ZYPHER_GET_URL = "https://zypher-herramienta-ciberseguridad.onrender.com/agente_get_backup.php"
ZYPHER_RESULTADO_URL = "https://zypher-herramienta-ciberseguridad.onrender.com/agente_resultado_backup.php"

TOKEN = "ZYPHER_BACKUP_TOKEN_2026"

R2_ACCOUNT_ID = "TU_ACCOUNT_ID"
R2_ACCESS_KEY = "TU_R2_ACCESS_KEY"
R2_SECRET_KEY = "TU_R2_SECRET_KEY"
R2_BUCKET = "zypher-backups"

BASE_DIR = Path("C:/Zypher/Backups")
TEMP_DIR = BASE_DIR / "temp"
KEY_FILE = BASE_DIR / "backup.key"

POLL_SECONDS = 60


def asegurar_cryptography():
    try:
        from cryptography.hazmat.primitives.ciphers.aead import AESGCM
        return AESGCM
    except Exception:
        print("[BACKUP] Instalando dependencia necesaria: cryptography")
        subprocess.check_call([
            sys.executable,
            "-m",
            "pip",
            "install",
            "cryptography"
        ])
        from cryptography.hazmat.primitives.ciphers.aead import AESGCM
        return AESGCM


AESGCM = asegurar_cryptography()


def asegurar_directorios():
    BASE_DIR.mkdir(parents=True, exist_ok=True)
    TEMP_DIR.mkdir(parents=True, exist_ok=True)


def obtener_clave():
    asegurar_directorios()

    if KEY_FILE.exists():
        return KEY_FILE.read_bytes()

    key = AESGCM.generate_key(bit_length=256)
    KEY_FILE.write_bytes(key)

    try:
        os.system(f'icacls "{KEY_FILE}" /inheritance:r /grant:r "%USERNAME%:F" >nul 2>&1')
    except Exception:
        pass

    return key


def http_get_json(url, params):
    query = urllib.parse.urlencode(params)
    final_url = url + "?" + query

    req = urllib.request.Request(
        final_url,
        method="GET",
        headers={
            "User-Agent": "ZypherBackupAgent/1.0"
        }
    )

    with urllib.request.urlopen(req, timeout=30) as response:
        data = response.read().decode("utf-8", errors="replace")
        return json.loads(data)


def http_post_json(url, payload):
    data = json.dumps(payload).encode("utf-8")

    req = urllib.request.Request(
        url,
        data=data,
        method="POST",
        headers={
            "Content-Type": "application/json; charset=utf-8",
            "User-Agent": "ZypherBackupAgent/1.0"
        }
    )

    with urllib.request.urlopen(req, timeout=60) as response:
        texto = response.read().decode("utf-8", errors="replace")
        return json.loads(texto)


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
    texto = texto.split("+")[0]

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
    return http_get_json(
        ZYPHER_GET_URL,
        {
            "token": TOKEN,
            "agente_id": AGENTE_ID
        }
    )


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

    return http_post_json(ZYPHER_RESULTADO_URL, payload)


def crear_zip(carpetas_codigos):
    asegurar_directorios()

    fecha = datetime.now().strftime("%Y%m%d_%H%M%S")
    zip_path = TEMP_DIR / f"backup_{AGENTE_ID}_{fecha}.zip"

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED, allowZip64=True) as zf:
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
                    if archivo == zip_path:
                        continue

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


def firma_hmac(key, msg):
    return hmac.new(key, msg.encode("utf-8"), hashlib.sha256).digest()


def firma_aws_v4(secret_key, date_stamp, region, service):
    k_date = firma_hmac(("AWS4" + secret_key).encode("utf-8"), date_stamp)
    k_region = firma_hmac(k_date, region)
    k_service = firma_hmac(k_region, service)
    k_signing = firma_hmac(k_service, "aws4_request")
    return k_signing


def comprobar_r2_config():
    faltan = []

    if R2_ACCOUNT_ID == "TU_ACCOUNT_ID" or not R2_ACCOUNT_ID:
        faltan.append("R2_ACCOUNT_ID")

    if R2_ACCESS_KEY == "TU_R2_ACCESS_KEY" or not R2_ACCESS_KEY:
        faltan.append("R2_ACCESS_KEY")

    if R2_SECRET_KEY == "TU_R2_SECRET_KEY" or not R2_SECRET_KEY:
        faltan.append("R2_SECRET_KEY")

    if R2_BUCKET == "zypher-backups" or not R2_BUCKET:
        pass

    if faltan:
        raise Exception("Faltan datos de Cloudflare R2: " + ", ".join(faltan))


def subir_r2(enc_path):
    comprobar_r2_config()

    region = "auto"
    service = "s3"

    host = f"{R2_ACCOUNT_ID}.r2.cloudflarestorage.com"
    object_key = f"{AGENTE_ID}/{enc_path.name}"

    canonical_uri = "/" + urllib.parse.quote(f"{R2_BUCKET}/{object_key}", safe="/~")
    url = f"https://{host}{canonical_uri}"

    payload = enc_path.read_bytes()
    payload_hash = hashlib.sha256(payload).hexdigest()

    ahora = datetime.utcnow()
    amz_date = ahora.strftime("%Y%m%dT%H%M%SZ")
    date_stamp = ahora.strftime("%Y%m%d")

    canonical_headers = (
        f"host:{host}\n"
        f"x-amz-content-sha256:{payload_hash}\n"
        f"x-amz-date:{amz_date}\n"
    )

    signed_headers = "host;x-amz-content-sha256;x-amz-date"

    canonical_request = "\n".join([
        "PUT",
        canonical_uri,
        "",
        canonical_headers,
        signed_headers,
        payload_hash
    ])

    credential_scope = f"{date_stamp}/{region}/{service}/aws4_request"

    string_to_sign = "\n".join([
        "AWS4-HMAC-SHA256",
        amz_date,
        credential_scope,
        hashlib.sha256(canonical_request.encode("utf-8")).hexdigest()
    ])

    signing_key = firma_aws_v4(R2_SECRET_KEY, date_stamp, region, service)
    signature = hmac.new(
        signing_key,
        string_to_sign.encode("utf-8"),
        hashlib.sha256
    ).hexdigest()

    authorization = (
        "AWS4-HMAC-SHA256 "
        f"Credential={R2_ACCESS_KEY}/{credential_scope}, "
        f"SignedHeaders={signed_headers}, "
        f"Signature={signature}"
    )

    req = urllib.request.Request(
        url,
        data=payload,
        method="PUT",
        headers={
            "Host": host,
            "x-amz-date": amz_date,
            "x-amz-content-sha256": payload_hash,
            "Authorization": authorization,
            "Content-Type": "application/octet-stream",
            "User-Agent": "ZypherBackupAgent/1.0"
        }
    )

    try:
        with urllib.request.urlopen(req, timeout=300) as response:
            if response.status not in [200, 201]:
                raise Exception(f"R2 respondió estado HTTP {response.status}")
    except urllib.error.HTTPError as e:
        detalle = e.read().decode("utf-8", errors="replace")
        raise Exception(f"Error subiendo a R2 HTTP {e.code}: {detalle}")

    return object_key


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

        print("[BACKUP] Copia completada correctamente")

    except Exception as e:
        print(f"[BACKUP] Error ejecutando copia: {e}")

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
                print("[BACKUP] Ejecutando copia automática")
                ejecutar_backup(carpetas_debidas, orden_id=None)

        except Exception as e:
            print(f"[BACKUP] Error general: {e}")

        time.sleep(POLL_SECONDS)


if __name__ == "__main__":
    main()
