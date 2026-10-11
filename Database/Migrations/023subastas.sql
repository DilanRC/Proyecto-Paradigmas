USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Subastas en tiempo real (DEC-SUBASTA-001). Un vendedor subasta un animal de su inventario por un tiempo fijo;
-- las personas pujan y comentan. Sin llaves: PHP valida dueño, montos, tiempos y estados. Fechas en UTC.
-- Estado: ACTIVA, FINALIZADA (con ganador), DESIERTA (sin pujas) o CANCELADA (por su dueño, sin pujas).
CREATE TABLE IF NOT EXISTS tbsubasta (
    tbsubastaid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbanimalid INT NOT NULL,
    tbfincaid INT NOT NULL,
    tbsubastatitulo VARCHAR(150) NOT NULL,
    tbsubastadescripcion VARCHAR(500) NULL,
    tbsubastaimagenurl VARCHAR(500) NULL,
    tbsubastapreciobase DECIMAL(12,2) NOT NULL,
    tbsubastaincrementominimo DECIMAL(12,2) NOT NULL,
    tbsubastafechainicio DATETIME NOT NULL,
    tbsubastafechafin DATETIME NOT NULL,
    tbsubastaestado VARCHAR(20) NOT NULL,
    tbsubastapujaganadoraid INT NULL,
    tbsubastafechacierre DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbsubastapuja (
    tbsubastapujaid INT NOT NULL,
    tbsubastaid INT NOT NULL,
    tbpersonaid INT NOT NULL,
    tbsubastapujamonto DECIMAL(12,2) NOT NULL,
    tbsubastapujafecha DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbsubastacomentario (
    tbsubastacomentarioid INT NOT NULL,
    tbsubastaid INT NOT NULL,
    tbpersonaid INT NOT NULL,
    tbsubastacomentariotexto VARCHAR(500) NOT NULL,
    tbsubastacomentariofecha DATETIME NOT NULL
) ENGINE=InnoDB;

-- Avisos para una persona (la campana del encabezado): subasta ganada, terminada o superada.
CREATE TABLE IF NOT EXISTS tbnotificacion (
    tbnotificacionid INT NOT NULL,
    tbpersonaid INT NOT NULL,
    tbnotificaciontipo VARCHAR(40) NOT NULL,
    tbnotificaciontitulo VARCHAR(150) NOT NULL,
    tbnotificacionmensaje VARCHAR(500) NOT NULL,
    tbnotificacionenlace VARCHAR(250) NULL,
    tbnotificacionfecha DATETIME NOT NULL,
    tbnotificacionleida TINYINT(1) NOT NULL
) ENGINE=InnoDB;

