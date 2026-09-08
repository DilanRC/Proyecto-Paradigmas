USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Agrega el alias solicitado por Calidad sin inventar valores históricos.
ALTER TABLE tbpersona
    ADD COLUMN tbpersonaalias VARCHAR(150) NULL AFTER tbpersonanombre;

-- Histórico de teléfono del Productor. La relación es conceptual: PHP valida
-- existencia y consistencia; MySQL no declara FOREIGN KEY.
CREATE TABLE IF NOT EXISTS tbproductorpersonatelefonohistorico (
    tbproductorpersonatelefonohistoricoid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductorpersonatelefonohistoriconuevo VARCHAR(20) NOT NULL,
    tbproductorpersonatelefonohistoricofecha DATETIME NOT NULL
) ENGINE=InnoDB;

-- Histórico de teléfono del Comprador con la misma estructura definida en la reunión.
CREATE TABLE IF NOT EXISTS tbcompradorpersonatelefonohistorico (
    tbcompradorpersonatelefonohistoricoid INT NOT NULL,
    tbcompradorid INT NOT NULL,
    tbcompradorpersonatelefonohistoriconuevo VARCHAR(20) NOT NULL,
    tbcompradorpersonatelefonohistoricofecha DATETIME NOT NULL
) ENGINE=InnoDB;
