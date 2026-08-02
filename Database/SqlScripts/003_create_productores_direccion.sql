USE dbtindercows;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbproductoresdireccion (
    tbproductoresIdentificacionNumero VARCHAR(250) NOT NULL,
    tbproductoresdireccionProvincia VARCHAR(100) NOT NULL,
    tbproductoresdireccionCanton VARCHAR(100) NOT NULL,
    tbproductoresdireccionDistrito VARCHAR(100) NOT NULL,
    tbproductoresdireccionPueblo VARCHAR(150) NULL,
    tbproductoresdireccionSenas VARCHAR(500) NULL,
    CONSTRAINT ck_tbproductoresdireccion_provincia CHECK (CHAR_LENGTH(TRIM(tbproductoresdireccionProvincia)) > 0),
    CONSTRAINT ck_tbproductoresdireccion_canton CHECK (CHAR_LENGTH(TRIM(tbproductoresdireccionCanton)) > 0),
    CONSTRAINT ck_tbproductoresdireccion_distrito CHECK (CHAR_LENGTH(TRIM(tbproductoresdireccionDistrito)) > 0),
    INDEX idx_tbproductoresdireccion_identificacion (tbproductoresIdentificacionNumero)
) ENGINE=InnoDB;
