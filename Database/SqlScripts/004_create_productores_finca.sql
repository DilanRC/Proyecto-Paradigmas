USE dbtindercows;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbproductorfinca (
    tbproductorfincaid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductorfincanombre VARCHAR(150) NOT NULL,
    tbproductorfincaestado TINYINT(1) NOT NULL DEFAULT 1,
) ENGINE=InnoDB;
