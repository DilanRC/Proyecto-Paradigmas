USE dbtindercows;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbproductor (
    tbproductorId INT NOT NULL,
    tbproductorIdentificacionNumero VARCHAR(250) NOT NULL,
    tbproductorIdentificacionTipo VARCHAR(40) NOT NULL,
    tbproductorNombre VARCHAR(150) NOT NULL,
    tbproductorTelefono VARCHAR(20) NOT NULL,
    tbproductorCorreoElectronico VARCHAR(150) NOT NULL,
    tbproductorEstado TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_tbproductor_id (tbproductorId),
    INDEX idx_tbproductor_identificacion (tbproductorIdentificacionNumero),
    INDEX idx_tbproductor_nombre (tbproductorNombre),
    INDEX idx_tbproductor_estado (tbproductorEstado),
    INDEX idx_tbproductor_correo (tbproductorCorreoElectronico)
) ENGINE=InnoDB;
