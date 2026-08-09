USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbproductordireccion (
    tbproductordireccionid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductordireccionprovincia VARCHAR(100) NOT NULL,
    tbproductordireccioncanton VARCHAR(100) NOT NULL,
    tbproductordirecciondistrito VARCHAR(100) NOT NULL,
    tbproductordireccionpueblo VARCHAR(150) NULL,
    tbproductordireccionsenas VARCHAR(500) NULL
) ENGINE=InnoDB;
