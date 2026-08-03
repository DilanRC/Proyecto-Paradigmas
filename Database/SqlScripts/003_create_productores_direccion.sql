USE dbtindercows;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbproductordireccion (
    tbproductordireccionId INT NOT NULL,
    tbproductorId INT NOT NULL,
    tbproductordireccionProvincia VARCHAR(100) NOT NULL,
    tbproductordireccionCanton VARCHAR(100) NOT NULL,
    tbproductordireccionDistrito VARCHAR(100) NOT NULL,
    tbproductordireccionPueblo VARCHAR(150) NULL,
    tbproductordireccionSenas VARCHAR(500) NULL,
    INDEX idx_tbproductordireccion_productor_id (tbproductorId)
) ENGINE=InnoDB;