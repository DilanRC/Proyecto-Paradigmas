USE dbtindercows;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbproductoresfinca (
    tbproductoresIdentificacionNumero VARCHAR(250) NOT NULL,
    tbproductoresfincaNombre VARCHAR(150) NOT NULL,
    tbproductoresfincaEstado TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT pk_tbproductoresfinca PRIMARY KEY (
        tbproductoresIdentificacionNumero,
        tbproductoresfincaNombre
    ),
    CONSTRAINT fk_tbproductoresfinca_productor FOREIGN KEY (tbproductoresIdentificacionNumero)
        REFERENCES tbproductores (tbproductoresIdentificacionNumero)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT ck_tbproductoresfinca_nombre CHECK (CHAR_LENGTH(TRIM(tbproductoresfincaNombre)) > 0),
    CONSTRAINT ck_tbproductoresfinca_estado CHECK (tbproductoresfincaEstado IN (0, 1)),
    INDEX idx_tbproductoresfinca_nombre (tbproductoresfincaNombre)
) ENGINE=InnoDB;
