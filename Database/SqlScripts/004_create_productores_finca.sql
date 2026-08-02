USE dbtindercows;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbproductorfinca (
    tbproductorfincaId INT NOT NULL,
    tbproductorfincaNombre VARCHAR(150) NOT NULL,
    tbproductorfincaEstado TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_tbproductorfinca_productor_nombre (
        tbproductorId,
        tbproductorfincaNombre
    ),
    INDEX idx_tbproductorfinca_nombre (tbproductorfincaNombre)
) ENGINE=InnoDB;
