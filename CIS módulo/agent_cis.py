import time
from datetime import datetime

import psycopg2


DB_HOST = "dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com"
DB_PORT = "5432"
DB_NAME = "zypher_db_g2sb"
DB_USER = "zypher_db_g2sb_user"
DB_PASSWORD = "MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86"


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


def ejecutar_analisis_basico(cur):
    fecha_actual = datetime.now()

    cur.execute("""
        UPDATE cis_policies
        SET estado = %s,
            fecha_ultimo_analisis = %s
    """, ("pendiente", fecha_actual))


def marcar_orden_en_proceso(cur, order_id):
    cur.execute("""
        UPDATE cis_orders
        SET estado = 'en_proceso'
        WHERE id = %s
    """, (order_id,))


def marcar_orden_completada(cur, order_id):
    cur.execute("""
        UPDATE cis_orders
        SET estado = 'completado'
        WHERE id = %s
    """, (order_id,))


def marcar_orden_error(cur, order_id):
    cur.execute("""
        UPDATE cis_orders
        SET estado = 'error'
        WHERE id = %s
    """, (order_id,))


def main():
    print("Agente CIS iniciado. Esperando órdenes...")

    while True:
        conn = None
        cur = None

        try:
            conn = conectar_bd()
            conn.autocommit = False
            cur = conn.cursor()

            orden = buscar_orden_pendiente(cur)

            if orden:
                order_id = orden[0]
                print(f"Orden pendiente encontrada: {order_id}")

                marcar_orden_en_proceso(cur, order_id)
                conn.commit()

                ejecutar_analisis_basico(cur)
                marcar_orden_completada(cur, order_id)
                conn.commit()

                print(f"Orden {order_id} completada correctamente.")

            else:
                print("Sin órdenes pendientes...")

        except Exception as e:
            print(f"Error en el agente: {e}")

            try:
                if conn:
                    conn.rollback()
                if cur and 'order_id' in locals():
                    marcar_orden_error(cur, order_id)
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
