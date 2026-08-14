USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Enlace entre productor y su residencia principal. tbdireccionid apunta a la
-- ubicación centralizada en tbdireccion y admite nulo mientras la aplicación no
-- lo asigne. Las columnas de provincia a senas son el detalle heredado que
-- todavía escribe el CRUD vigente; su traslado a tbdireccion pertenece a la capa
-- de aplicación y queda fuera de este avance de base de datos.
CREATE TABLE IF NOT EXISTS tbproductordireccion (
    tbproductordireccionid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbdireccionid INT NULL,
    tbproductordireccionprovincia VARCHAR(100) NOT NULL,
    tbproductordireccioncanton VARCHAR(100) NOT NULL,
    tbproductordirecciondistrito VARCHAR(100) NOT NULL,
    tbproductordireccionpueblo VARCHAR(150) NULL,
    tbproductordireccionsenas VARCHAR(500) NULL
) ENGINE=InnoDB;
