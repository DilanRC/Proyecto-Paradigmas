USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbproductor (
    tbproductorid INT NOT NULL,
    tbproductoridentificacionnumero VARCHAR(250) NOT NULL,
    tbproductoridentificaciontipo VARCHAR(40) NOT NULL,
    tbproductornombre VARCHAR(150) NOT NULL,
    tbproductortelefono VARCHAR(20) NOT NULL,
    tbproductorcorreoelectronico VARCHAR(150) NOT NULL,
    tbproductorestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;
