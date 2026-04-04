#!/usr/bin/env python3

from datetime import datetime
from zoneinfo import ZoneInfo
import sys

try:
    import psycopg2
except Exception as e:
    print(f"Error importando psycopg2: {e}")
    sys.exit(1)

DB_HOST = "dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com"
DB_PORT = "5432"
DB_NAME = "zypher_db_g2sb"
DB_USER = "zypher_db_g2sb_user"
DB_PASSWORD = "MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86"


def main() -> int:
    conn = None
    cur = None

    try:
        conn = psycopg2.connect(
            host=DB_HOST,
            port=DB_PORT,
            dbname=DB_NAME,
            user=DB_USER,
            password=DB_PASSWORD,
        )

        cur = conn.cursor()

        fecha_madrid = datetime.now(ZoneInfo("Europe/Madrid"))

        cur.execute(
            """
            UPDATE cis_policies
            SET estado = %s,
                fecha_ultimo_analisis = %s
            """,
            ("pendiente", fecha_madrid),
        )

        conn.commit()
        print("Análisis reiniciado correctamente.")
        return 0

    except Exception as e:
        print(f"Error al reiniciar el análisis CIS: {e}")
        return 1

    finally:
        if cur is not None:
            cur.close()
        if conn is not None:
            conn.close()


if __name__ == "__main__":
    sys.exit(main())
