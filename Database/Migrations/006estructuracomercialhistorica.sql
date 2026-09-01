USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Estructura comercial e histórica confirmada por Calidad para preparar la
-- base de datos de Backend. Esta migración NO inventa hechos pasados: no crea
-- compras, ventas, clasificaciones, observaciones, interacciones, fletes ni
-- reseñas sobre datos existentes.

CREATE TABLE IF NOT EXISTS tbproductorclasificacionperiodo (
    tbproductorclasificacionperiodoid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductorclasificacionperiodotipo VARCHAR(30) NOT NULL,
    tbproductorclasificacionperiodofechainicio DATETIME NOT NULL,
    tbproductorclasificacionperiodofechafin DATETIME NULL,
    tbproductorclasificacionperiodomotivo VARCHAR(250) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbanimal (
    tbanimalid INT NOT NULL,
    tbanimalcodigo VARCHAR(100) NULL,
    tbanimalsexo VARCHAR(20) NULL,
    tbanimalraza VARCHAR(100) NULL,
    tbanimalfecharegistroensistema DATETIME NOT NULL,
    tbanimalorigenregistro VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbanimalobservacion (
    tbanimalobservacionid INT NOT NULL,
    tbanimalid INT NOT NULL,
    tbanimalobservacionfecha DATETIME NOT NULL,
    tbanimalobservacionorigen VARCHAR(100) NOT NULL,
    tbanimalobservacioncontexto VARCHAR(250) NULL,
    tbanimalobservacionedadmeses INT NULL,
    tbanimalobservacionpeso DECIMAL(10,2) NULL,
    tbanimalobservacionproposito VARCHAR(80) NULL,
    tbanimalobservacionestadoreproductivo VARCHAR(80) NULL,
    tbanimalobservacionpartos INT NULL,
    tbanimalobservacionlitrosleche DECIMAL(10,2) NULL,
    tbanimalobservacionproduccion JSON NULL,
    tbanimalobservacionsalud JSON NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbanimalpublicacion (
    tbanimalpublicacionid INT NOT NULL,
    tbanimalid INT NOT NULL,
    tbproductorvendedorid INT NOT NULL,
    tbfincaid INT NOT NULL,
    tbanimalpublicacionfecha DATETIME NOT NULL,
    tbanimalpublicacionprecio DECIMAL(12,2) NULL,
    tbanimalpublicaciontitulo VARCHAR(150) NULL,
    tbanimalpublicaciondescripcion VARCHAR(500) NULL,
    tbanimalpublicacionestado VARCHAR(30) NOT NULL,
    tbanimalpublicacionorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbcompra (
    tbcompraid INT NOT NULL,
    tbanimalid INT NOT NULL,
    tbproductorcompradorid INT NOT NULL,
    tbfincaorigenid INT NULL,
    tbcomprafecha DATE NOT NULL,
    tbcomprahora TIME NULL,
    tbcompralugar VARCHAR(250) NULL,
    tbcompraprecio DECIMAL(12,2) NOT NULL,
    tbpagometodoid INT NOT NULL,
    tbcompraorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbventa (
    tbventaid INT NOT NULL,
    tbanimalid INT NOT NULL,
    tbproductorvendedorid INT NOT NULL,
    tbproductorcompradorid INT NOT NULL,
    tbfincaid INT NULL,
    tbcompraid INT NULL,
    tbventafecha DATE NOT NULL,
    tbventahora TIME NULL,
    tbventalugar VARCHAR(250) NULL,
    tbventaprecio DECIMAL(12,2) NOT NULL,
    tbpagometodoid INT NOT NULL,
    tbventaedadmeses INT NULL,
    tbventapeso DECIMAL(10,2) NULL,
    tbventarazasnapshot VARCHAR(100) NULL,
    tbventaorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbanimalinteraccion (
    tbanimalinteraccionid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbanimalid INT NOT NULL,
    tbanimalinteracciontipo VARCHAR(30) NOT NULL,
    tbanimalinteraccionaccion VARCHAR(30) NOT NULL,
    tbanimalinteraccionfecha DATETIME NOT NULL,
    tbanimalinteraccionorigen VARCHAR(100) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbcarrito (
    tbcarritoid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbcarritofechacreacion DATETIME NOT NULL,
    tbcarritoestado VARCHAR(30) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbcarritoanimal (
    tbcarritoanimalid INT NOT NULL,
    tbcarritoid INT NOT NULL,
    tbanimalid INT NOT NULL,
    tbcarritoanimalaccion VARCHAR(30) NOT NULL,
    tbcarritoanimalfecha DATETIME NOT NULL,
    tbcarritoanimalorigen VARCHAR(100) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbtransportistaestadoperiodo (
    tbtransportistaestadoperiodoid INT NOT NULL,
    tbtransportistaid INT NOT NULL,
    tbtransportistaestadoperiodoestado TINYINT(1) NOT NULL,
    tbtransportistaestadoperiodofechainicio DATETIME NULL,
    tbtransportistaestadoperiodofechafin DATETIME NULL,
    tbtransportistaestadoperiodomotivo VARCHAR(250) NULL,
    tbtransportistaestadoperiodofecharegistroensistema DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbtransportistaflete (
    tbtransportistafleteid INT NOT NULL,
    tbtransportistaid INT NOT NULL,
    tbproductororigenid INT NULL,
    tbfincaorigenid INT NULL,
    tbdireccionorigenid INT NULL,
    tbdirecciondestinoid INT NULL,
    tbtransportistafletefecha DATE NOT NULL,
    tbtransportistafletehora TIME NULL,
    tbtransportistafletedescripcion VARCHAR(500) NULL,
    tbtransportistafleteprecio DECIMAL(12,2) NULL,
    tbpagometodoid INT NOT NULL,
    tbtransportistafleteorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbtransportistaresena (
    tbtransportistaresenaid INT NOT NULL,
    tbtransportistaid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbtransportistafleteid INT NULL,
    tbtransportistaresenafecha DATETIME NOT NULL,
    tbtransportistaresenacalificacion INT NOT NULL,
    tbtransportistaresenacomentario VARCHAR(500) NULL,
    tbtransportistaresenaorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;
