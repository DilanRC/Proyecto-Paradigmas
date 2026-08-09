USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbfinca (
    tbfincaid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbfincanombre VARCHAR(150) NOT NULL,
    tbfincaestado TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
