CREATE DATABASE IF NOT EXISTS bdmercadoganadero
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

ALTER DATABASE bdmercadoganadero
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbpersona (
    tbpersonaid INT NOT NULL,
    tbpersonaidentificacionnumero VARCHAR(250) NOT NULL,
    tbpersonaidentificaciontipo VARCHAR(40) NOT NULL,
    tbpersonanombre VARCHAR(150) NOT NULL,
    tbpersonaalias VARCHAR(150) NULL,
    tbpersonatelefono VARCHAR(20) NOT NULL,
    tbpersonacorreoelectronico VARCHAR(150) NOT NULL,
    tbpersonaestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbproductor (
    tbproductorid INT NOT NULL,
    tbpersonaid INT NOT NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbproductordireccion (
    tbproductordireccionid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbdireccionid INT NOT NULL,
    tbproductordireccionfechainicio DATETIME NULL,
    tbproductordireccionfechafin DATETIME NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbfinca (
    tbfincaid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbfincanombre VARCHAR(150) NOT NULL,
    tbfincaestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbbitacora (
    tbbitacoraid BIGINT UNSIGNED NOT NULL,
    tbbitacoraentidad VARCHAR(80) NOT NULL,
    tbbitacoraregistroidentificacionnumero VARCHAR(250) NOT NULL,
    tbbitacoraaccion VARCHAR(30) NOT NULL,
    tbbitacorafecha DATETIME NOT NULL,
    tbbitacoradatosanteriores JSON NULL,
    tbbitacoradatosnuevos JSON NULL,
    tbbitacoraactortipo VARCHAR(30) NOT NULL,
    tbbitacorausuarioid BIGINT UNSIGNED NULL,
    tbbitacoraorigen VARCHAR(100) NOT NULL,
    tbbitacorasolicitudid VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbcomprador (
    tbcompradorid INT NOT NULL,
    tbpersonaid INT NOT NULL,
    tbcompradorestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbdireccion (
    tbdireccionid INT NOT NULL,
    tbdireccionprovincia VARCHAR(100) NOT NULL,
    tbdireccioncanton VARCHAR(100) NOT NULL,
    tbdirecciondistrito VARCHAR(100) NOT NULL,
    tbdireccionpueblo VARCHAR(150) NULL,
    tbdireccionsenas VARCHAR(500) NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbfincadireccion (
    tbfincadireccionid INT NOT NULL,
    tbfincaid INT NOT NULL,
    tbdireccionid INT NOT NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbpagometodo (
    tbpagometodoid INT NOT NULL,
    tbpagometodonombre VARCHAR(100) NOT NULL,
    tbpagometododescripcion VARCHAR(250) NOT NULL,
    tbpagometodoactivo TINYINT(1) NOT NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbtransportista (
    tbtransportistaid INT NOT NULL,
    tbpersonaid INT NOT NULL,
    tbtransportistaestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbvehiculo (
    tbvehiculoid INT NOT NULL,
    tbvehiculoplaca VARCHAR(20) NOT NULL,
    tbvehiculovin VARCHAR(50) NOT NULL,
    tbvehiculomodelo VARCHAR(100) NOT NULL,
    tbvehiculoestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbtransportistavehiculo (
    tbtransportistavehiculoid INT NOT NULL,
    tbtransportistaid INT NOT NULL,
    tbvehiculoid INT NOT NULL
) ENGINE=InnoDB;

-- Histórico confirmado por Calidad: Productor + Persona + Teléfono + Histórico.
-- Una fila por cambio relevante; solo ID, relación conceptual, valor nuevo y fecha.
CREATE TABLE IF NOT EXISTS tbproductorpersonatelefonohistorico (
    tbproductorpersonatelefonohistoricoid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductorpersonatelefonohistoriconuevo VARCHAR(20) NOT NULL,
    tbproductorpersonatelefonohistoricofecha DATETIME NOT NULL
) ENGINE=InnoDB;

-- Histórico confirmado por Calidad para Comprador con la misma estructura.
CREATE TABLE IF NOT EXISTS tbcompradorpersonatelefonohistorico (
    tbcompradorpersonatelefonohistoricoid INT NOT NULL,
    tbcompradorid INT NOT NULL,
    tbcompradorpersonatelefonohistoriconuevo VARCHAR(20) NOT NULL,
    tbcompradorpersonatelefonohistoricofecha DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbproductorestadoperiodo (
    tbproductorestadoperiodoid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductorestadoperiodoestado TINYINT(1) NOT NULL,
    tbproductorestadoperiodofechainicio DATETIME NOT NULL,
    tbproductorestadoperiodofechafin DATETIME NULL,
    tbproductorestadoperiodomotivo VARCHAR(250) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbproductorubicacion (
    tbproductorubicacionid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductorubicacionlatitud DECIMAL(10,7) NOT NULL,
    tbproductorubicacionlongitud DECIMAL(10,7) NOT NULL,
    tbproductorubicacionprecision DECIMAL(10,2) NULL,
    tbproductorubicacionfecha DATETIME NOT NULL,
    tbproductorubicacionorigen VARCHAR(40) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbproductoractividad (
    tbproductoractividadid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductoractividadtipo VARCHAR(60) NOT NULL,
    tbproductoractividadfecha DATETIME NOT NULL,
    tbproductoractividadorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Estructura transitoria: conserva señales analíticas ya consumidas por dev.
-- No sustituye a tbcomprador ni define la identidad de la Persona.
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
    tbanimalidentificacion VARCHAR(100) NULL,
    tbanimalsexo VARCHAR(20) NULL,
    tbanimalraza VARCHAR(100) NULL,
    tbanimalcaracteristicas VARCHAR(500) NULL,
    tbanimalfecharegistroensistema DATETIME NOT NULL,
    tbanimalorigenregistro VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbanimalproduccionsalud (
    tbanimalproduccionsaludid INT NOT NULL,
    tbanimalid INT NOT NULL,
    tbanimalproduccionsaludfecha DATETIME NOT NULL,
    tbanimalproduccionsaludorigen VARCHAR(100) NOT NULL,
    tbanimalproduccionsaludcontexto VARCHAR(250) NULL,
    tbanimalproduccionsaludedadmeses INT NULL,
    tbanimalproduccionsaludpeso DECIMAL(10,2) NULL,
    tbanimalproduccionsaludproposito VARCHAR(80) NULL,
    tbanimalproduccionsaludestadoreproductivo VARCHAR(80) NULL,
    tbanimalproduccionsaludpartos INT NULL,
    tbanimalproduccionsaludlitrosleche DECIMAL(10,2) NULL,
    tbanimalproduccionsaludproduccion JSON NULL,
    tbanimalproduccionsaludsalud JSON NULL
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
    tbanimalpublicacionorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbanimalpublicacionestadoperiodo (
    tbanimalpublicacionestadoperiodoid INT NOT NULL,
    tbanimalpublicacionid INT NOT NULL,
    tbanimalpublicacionestadoperiodoestado VARCHAR(30) NOT NULL,
    tbanimalpublicacionestadoperiodofechainicio DATETIME NOT NULL,
    tbanimalpublicacionestadoperiodofechafin DATETIME NULL,
    tbanimalpublicacionestadoperiodomotivo VARCHAR(250) NULL,
    tbanimalpublicacionestadoperiodoorigen VARCHAR(100) NOT NULL
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
    tbventadireccionid INT NULL,
    tbventaproposito VARCHAR(80) NULL,
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
    tbcarritofechacreacion DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbcarritoanimal (
    tbcarritoanimalid INT NOT NULL,
    tbcarritoid INT NOT NULL,
    tbanimalid INT NOT NULL,
    tbcarritoanimalaccion VARCHAR(30) NOT NULL,
    tbcarritoanimalfecha DATETIME NOT NULL,
    tbcarritoanimalorigen VARCHAR(100) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbcarritoestadoperiodo (
    tbcarritoestadoperiodoid INT NOT NULL,
    tbcarritoid INT NOT NULL,
    tbcarritoestadoperiodoestado VARCHAR(30) NOT NULL,
    tbcarritoestadoperiodofechainicio DATETIME NOT NULL,
    tbcarritoestadoperiodofechafin DATETIME NULL,
    tbcarritoestadoperiodomotivo VARCHAR(250) NULL,
    tbcarritoestadoperiodoorigen VARCHAR(100) NOT NULL
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

CREATE TABLE IF NOT EXISTS tbtransportistahorario (
    tbtransportistahorarioid INT NOT NULL,
    tbtransportistaid INT NOT NULL,
    tbtransportistahorariodiasemana VARCHAR(15) NOT NULL,
    tbtransportistahorariohorainicio TIME NOT NULL,
    tbtransportistahorariohorafin TIME NOT NULL,
    tbtransportistahorariofechainicio DATETIME NOT NULL,
    tbtransportistahorariofechafin DATETIME NULL,
    tbtransportistahorarioorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbtransportistaflete (
    tbtransportistafleteid INT NOT NULL,
    tbtransportistaid INT NOT NULL,
    tbproductororigenid INT NULL,
    tbfincaorigenid INT NULL,
    tbdireccionorigenid INT NULL,
    tbdirecciondestinoid INT NULL,
    tbvehiculoid INT NULL,
    tbtransportistafletefecha DATE NOT NULL,
    tbtransportistafletehora TIME NULL,
    tbtransportistafletedescripcion VARCHAR(500) NULL,
    tbtransportistafletecantidadcabezas INT NULL,
    tbtransportistafletedistanciakm DECIMAL(10,2) NULL,
    tbtransportistafleteprecio DECIMAL(12,2) NULL,
    tbpagometodoid INT NOT NULL,
    tbtransportistafleteorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbtransportistaresena (
    tbtransportistaresenaid INT NOT NULL,
    tbtransportistaid INT NOT NULL,
    tbpersonaid INT NOT NULL,
    tbtransportistafleteid INT NULL,
    tbtransportistaresenafecha DATETIME NOT NULL,
    tbtransportistaresenacalificacion INT NOT NULL,
    tbtransportistaresenacomentario VARCHAR(500) NULL,
    tbtransportistaresenaorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- fin del script de instalación completa
