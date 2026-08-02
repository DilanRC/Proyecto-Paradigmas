USE dbtindercows;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbproductores (
    tbproductoresIdentificacionNumero VARCHAR(250) NOT NULL,
    tbproductoresIdentificacionTipo VARCHAR(40) NOT NULL,
    tbproductoresNombre VARCHAR(150) NOT NULL,
    tbproductoresTelefono VARCHAR(20) NOT NULL,
    tbproductoresCorreoElectronico VARCHAR(150) NOT NULL,
    tbproductoresEstado TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT pk_tbproductores PRIMARY KEY (tbproductoresIdentificacionNumero),
    CONSTRAINT ck_tbproductores_identificacion_no_vacia CHECK (CHAR_LENGTH(TRIM(tbproductoresIdentificacionNumero)) > 0),
    CONSTRAINT ck_tbproductores_tipo CHECK (tbproductoresIdentificacionTipo IN ('CEDULA_FISICA', 'CEDULA_JURIDICA', 'DIMEX', 'NITE', 'PASAPORTE')),
    CONSTRAINT ck_tbproductores_nombre CHECK (CHAR_LENGTH(TRIM(tbproductoresNombre)) BETWEEN 3 AND 150),
    CONSTRAINT ck_tbproductores_estado CHECK (tbproductoresEstado IN (0, 1)),
    INDEX idx_tbproductores_nombre (tbproductoresNombre),
    INDEX idx_tbproductores_estado (tbproductoresEstado),
    INDEX idx_tbproductores_correo (tbproductoresCorreoElectronico)
) ENGINE=InnoDB;
