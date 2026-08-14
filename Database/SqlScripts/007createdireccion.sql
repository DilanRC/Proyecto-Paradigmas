USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Ubicación física reutilizable. No pertenece a productor ni a finca: ambas
-- entidades la referencian por tbdireccionid mediante sus tablas de enlace.
CREATE TABLE IF NOT EXISTS tbdireccion (
    tbdireccionid INT NOT NULL,
    tbdireccionprovincia VARCHAR(100) NOT NULL,
    tbdireccioncanton VARCHAR(100) NOT NULL,
    tbdirecciondistrito VARCHAR(100) NOT NULL,
    tbdireccionpueblo VARCHAR(150) NULL,
    tbdireccionsenas VARCHAR(500) NULL
) ENGINE=InnoDB;
