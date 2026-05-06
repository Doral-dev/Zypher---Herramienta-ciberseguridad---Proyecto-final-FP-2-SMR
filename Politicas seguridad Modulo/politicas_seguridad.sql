CREATE TABLE politicas_seguridad (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(80) UNIQUE NOT NULL,
    categoria VARCHAR(120) NOT NULL,
    subcategoria VARCHAR(160) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    descripcion TEXT NOT NULL,
    comando_aplicar TEXT NOT NULL,
    comando_verificar TEXT NOT NULL,
    valor_recomendado TEXT,
    activa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE politicas_ordenes (
    id SERIAL PRIMARY KEY,
    politica_id INT REFERENCES politicas_seguridad(id) ON DELETE CASCADE,
    agente_id VARCHAR(120) NOT NULL,
    accion VARCHAR(50) NOT NULL,
    estado VARCHAR(30) DEFAULT 'pendiente',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ejecutado_en TIMESTAMP NULL,
    resultado TEXT NULL,
    error TEXT NULL
);

CREATE TABLE politicas_estado_agente (
    id SERIAL PRIMARY KEY,
    politica_id INT REFERENCES politicas_seguridad(id) ON DELETE CASCADE,
    agente_id VARCHAR(120) NOT NULL,
    valor_actual TEXT,
    valor_recomendado TEXT,
    cumple BOOLEAN,
    ultima_revision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (politica_id, agente_id)
);

CREATE TABLE politicas_historial (
    id SERIAL PRIMARY KEY,
    politica_id INT REFERENCES politicas_seguridad(id) ON DELETE CASCADE,
    agente_id VARCHAR(120) NOT NULL,
    accion VARCHAR(50) NOT NULL,
    estado VARCHAR(30) NOT NULL,
    detalle TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
