USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Enlace conceptual entre una finca y su ubicación. La política del modelo
-- espera una sola fila por tbfincaid; el motor no lo impide.
CREATE TABLE IF NOT EXISTS tbfincadireccion (
    tbfincadireccionid INT NOT NULL,
    tbfincaid INT NOT NULL,
    tbdireccionid INT NOT NULL
) ENGINE=InnoDB;
