CREATE TABLE IF NOT EXISTS respuesta_equipos (
    id SERIAL PRIMARY KEY,
    agente_id VARCHAR(100) UNIQUE NOT NULL,
    velociraptor_client_id VARCHAR(100) UNIQUE NOT NULL,
    hostname VARCHAR(150) NOT NULL,
    sistema VARCHAR(100) DEFAULT 'Windows',
    activo BOOLEAN DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS respuesta_acciones (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(100) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    artefacto_velociraptor VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'respuesta',
    requiere_parametros BOOLEAN DEFAULT FALSE,
    activa BOOLEAN DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS respuesta_ordenes (
    id SERIAL PRIMARY KEY,
    equipo_id INTEGER NOT NULL REFERENCES respuesta_equipos(id) ON DELETE CASCADE,
    accion_id INTEGER NOT NULL REFERENCES respuesta_acciones(id) ON DELETE RESTRICT,
    parametros JSONB DEFAULT '{}'::jsonb,
    estado VARCHAR(50) NOT NULL DEFAULT 'pendiente',
    flow_id VARCHAR(100),
    resultado JSONB,
    error TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ejecutado_en TIMESTAMP,
    finalizado_en TIMESTAMP
);
