CREATE TABLE IF NOT EXISTS eventos_monitorizacion (
    id SERIAL PRIMARY KEY,
    id_evento INTEGER NOT NULL,
    descripcion TEXT NOT NULL,
    tipo VARCHAR(100) NOT NULL,
    severidad VARCHAR(50) NOT NULL,
    host VARCHAR(255) NOT NULL,
    fecha_evento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario VARCHAR(255),
    ip_origen VARCHAR(100),
    origen VARCHAR(255),
    regla VARCHAR(255),
    detalles_raw TEXT,
    estado VARCHAR(50) DEFAULT 'Nuevo'
);


