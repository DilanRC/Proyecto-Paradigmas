USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Persona independiente responsable del transporte. Conserva el perfil de
-- persona vigente en tbproductor y tbcomprador; no reutiliza sus identificadores.
CREATE TABLE IF NOT EXISTS tbtransportista (
    tbtransportistaid INT NOT NULL,
    tbtransportistaidentificacionnumero VARCHAR(250) NOT NULL,
    tbtransportistaidentificaciontipo VARCHAR(40) NOT NULL,
    tbtransportistanombre VARCHAR(150) NOT NULL,
    tbtransportistatelefono VARCHAR(20) NOT NULL,
    tbtransportistacorreoelectronico VARCHAR(150) NOT NULL,
    tbtransportistaestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;
