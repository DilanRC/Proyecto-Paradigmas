USE bdmercadoganadero;

-- Límite de consultas de disponibilidad del registro (P2-1): cédula y correo.
-- Una fila por consulta, con el hash SHA-256 de la IP (nunca la IP). PHP borra
-- las filas fuera de la ventana en cada consulta. Sin PK ni índices, como el
-- resto del esquema: las reglas viven en PHP.
CREATE TABLE IF NOT EXISTS tbregistroconsulta (
    tbregistroconsultaid INT NOT NULL,
    tbregistroconsultaclave CHAR(64) NOT NULL,
    tbregistroconsultafecha DATETIME NOT NULL
) ENGINE=InnoDB;
