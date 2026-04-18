CREATE TABLE IF NOT EXISTS fim_rutas (
    id SERIAL PRIMARY KEY,
    ruta TEXT NOT NULL UNIQUE,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('carpeta', 'archivo')),
    activa BOOLEAN NOT NULL DEFAULT TRUE,
    creada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fim_eventos (
    id SERIAL PRIMARY KEY,
    ruta TEXT NOT NULL,
    tipo_elemento VARCHAR(20) NOT NULL CHECK (tipo_elemento IN ('carpeta', 'archivo')),
    cambio VARCHAR(20) NOT NULL CHECK (cambio IN ('Creado', 'Modificado', 'Eliminado')),
    hash_anterior TEXT,
    hash_nuevo TEXT,
    fecha_evento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
