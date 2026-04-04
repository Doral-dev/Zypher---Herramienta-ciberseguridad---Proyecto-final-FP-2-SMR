import time
import psycopg2

DB_HOST = "dpg-d6rar2vafjfc73f3u5u0-a.oregon-postgres.render.com"
DB_PORT = "5432"
DB_NAME = "zypher_db_g2sb"
DB_USER = "zypher_db_g2sb_user"
DB_PASSWORD = "MwoKyrgVtJaOKvqtd97QQ5yMxzvnyT86"

while True:
    try:
        conn = psycopg2.connect(
            host=DB_HOST,
            port=DB_PORT,
            dbname=DB_NAME,
            user=DB_USER,
            password=DB_PASSWORD
        )
        cur = conn.cursor()

        cur.execute("""
            SELECT id
            FROM cis_orders
            WHERE accion = 'reanalyze_cis' AND estado = 'pendiente'
            ORDER BY id ASC
            LIMIT 1
        """)
        order = cur.fetchone()

        if order:
            order_id = order[0]

            cur.execute("""
                UPDATE cis_orders
                SET estado = 'completado'
                WHERE id = %s
            """, (order_id,))
            conn.commit()
            print(f"Orden {order_id} completada")

        cur.close()
        conn.close()

    except Exception as e:
        print("Error:", e)

    time.sleep(5)
