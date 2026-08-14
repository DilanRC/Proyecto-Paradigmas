USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Vehículo utilizado para el transporte. Placa y vin se almacenan tal cual: la
-- detección de repetidos es una consulta de diagnóstico, no una restricción.
CREATE TABLE IF NOT EXISTS tbvehiculo (
    tbvehiculoid INT NOT NULL,
    tbvehiculoplaca VARCHAR(20) NOT NULL,
    tbvehiculovin VARCHAR(50) NOT NULL,
    tbvehiculomodelo VARCHAR(100) NOT NULL,
    tbvehiculoestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;
