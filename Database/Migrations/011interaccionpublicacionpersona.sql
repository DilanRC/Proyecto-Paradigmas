USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Hechos append-only de la persona sobre una publicación pública.
-- No se agrega PK, FK, UNIQUE ni CHECK: las relaciones y la idempotencia
-- pertenecen a PHP, conforme al contrato de Calidad.
CREATE TABLE IF NOT EXISTS tbanimalpublicacioninteraccion (
    tbanimalpublicacioninteraccionid INT NOT NULL,
    tbpersonaid INT NOT NULL,
    tbanimalpublicacionid INT NOT NULL,
    tbanimalpublicacioninteracciontipo VARCHAR(30) NOT NULL,
    tbanimalpublicacioninteraccionaccion VARCHAR(30) NOT NULL,
    tbanimalpublicacioninteraccionfecha DATETIME NOT NULL,
    tbanimalpublicacioninteraccionorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;
