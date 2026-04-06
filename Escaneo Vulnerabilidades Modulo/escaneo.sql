CREATE TABLE IF NOT EXISTS vulns_resultados (
    id SERIAL PRIMARY KEY,
    agent_id VARCHAR(50) NOT NULL,
    agent_name VARCHAR(255) NOT NULL,
    cve VARCHAR(100) NOT NULL,
    severidad VARCHAR(50),
    cvss NUMERIC(4,1),
    paquete VARCHAR(255),
    version_paquete VARCHAR(100),
    descripcion TEXT,
    referencia TEXT,
    remediacion TEXT,
    estado VARCHAR(50) NOT NULL DEFAULT 'Pendiente',
    fecha_deteccion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
