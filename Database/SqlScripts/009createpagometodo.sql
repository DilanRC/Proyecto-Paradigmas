USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Catálogo de métodos de pago. El alcance vigente solo contempla efectivo y
-- todavía no se relaciona con ninguna operación económica.
CREATE TABLE IF NOT EXISTS tbpagometodo (
    tbpagometodoid INT NOT NULL,
    tbpagometodonombre VARCHAR(100) NOT NULL,
    tbpagometododescripcion VARCHAR(250) NOT NULL,
    tbpagometodoactivo TINYINT(1) NOT NULL
) ENGINE=InnoDB;
