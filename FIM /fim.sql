CREATE TABLE IF NOT EXISTS fim_eventos (
    id SERIAL PRIMARY KEY,
    agent_id VARCHAR(50) NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    accion VARCHAR(20) NOT NULL,
    ruta TEXT NOT NULL,
    tamano_anterior BIGINT,
    tamano_actual BIGINT,
    fecha_mod_anterior TIMESTAMP NULL,
    fecha_mod_actual TIMESTAMP NULL,
    fecha_evento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_fim_eventos_fecha
ON fim_eventos (fecha_evento DESC);

CREATE INDEX IF NOT EXISTS idx_fim_eventos_hostname
ON fim_eventos (hostname);
