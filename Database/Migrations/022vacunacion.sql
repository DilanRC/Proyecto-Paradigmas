USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Historial de vacunación del animal (P2-3). tbvacuna es el catálogo (con activo, para el panel de administración).
-- Sin llaves: PHP valida que el animal sea del vendedor y que la vacuna exista y esté activa.
CREATE TABLE IF NOT EXISTS tbvacuna (
    tbvacunaid INT NOT NULL,
    tbvacunanombre VARCHAR(100) NOT NULL,
    tbvacunaactivo TINYINT(1) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbanimalvacunacion (
    tbanimalvacunacionid INT NOT NULL,
    tbanimalid INT NOT NULL,
    tbvacunaid INT NOT NULL,
    tbanimalvacunacionfecha DATE NOT NULL,
    tbanimalvacunaciondosis VARCHAR(50) NULL,
    tbanimalvacunacionlote VARCHAR(50) NULL,
    tbanimalvacunacionaplicadapor VARCHAR(150) NULL,
    tbanimalvacunacionproximadosis DATE NULL,
    tbanimalvacunacionobservaciones VARCHAR(500) NULL,
    tbanimalvacunacionfecharegistro DATETIME NOT NULL
) ENGINE=InnoDB;

-- Vacunas comunes del ganado. Solo se siembra si la tabla está vacía (lo que el administrador cambie no se reinserta).
START TRANSACTION;

INSERT INTO tbvacuna (tbvacunaid, tbvacunanombre, tbvacunaactivo)
SELECT v.* FROM (
    SELECT 1 AS tbvacunaid, 'Fiebre aftosa' AS tbvacunanombre, 1 AS tbvacunaactivo
    UNION ALL
    SELECT 2, 'Brucelosis', 1
    UNION ALL
    SELECT 3, 'Rabia paralítica bovina', 1
    UNION ALL
    SELECT 4, 'Carbunco sintomático', 1
    UNION ALL
    SELECT 5, 'Carbunco bacteridiano', 1
    UNION ALL
    SELECT 6, 'Clostridiales (multiclostridial)', 1
    UNION ALL
    SELECT 7, 'IBR / DVB (rinotraqueítis y diarrea viral)', 1
    UNION ALL
    SELECT 8, 'Leptospirosis', 1
    UNION ALL
    SELECT 9, 'Complejo respiratorio bovino', 1
    UNION ALL
    SELECT 10, 'Pasteurelosis', 1
) v
WHERE NOT EXISTS (SELECT 1 FROM tbvacuna);

COMMIT;
