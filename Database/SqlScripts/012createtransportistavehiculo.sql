USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Enlace conceptual entre transportista y vehículo. La política del modelo
-- espera varios vehículos por transportista y un solo transportista por
-- vehículo; el motor no lo impide.
CREATE TABLE IF NOT EXISTS tbtransportistavehiculo (
    tbtransportistavehiculoid INT NOT NULL,
    tbtransportistaid INT NOT NULL,
    tbvehiculoid INT NOT NULL
) ENGINE=InnoDB;
