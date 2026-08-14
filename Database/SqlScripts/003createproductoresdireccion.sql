USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Enlace entre el productor y su residencia principal. La tabla no almacena
-- datos de ubicación: provincia, cantón, distrito, pueblo y señas viven una sola
-- vez en tbdireccion y se alcanzan por tbdireccionid.
CREATE TABLE IF NOT EXISTS tbproductordireccion (
    tbproductordireccionid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbdireccionid INT NOT NULL
) ENGINE=InnoDB;
