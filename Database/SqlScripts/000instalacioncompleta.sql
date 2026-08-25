CREATE DATABASE IF NOT EXISTS dbtindervacas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

ALTER DATABASE dbtindervacas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbproductor (
    tbproductorid INT NOT NULL,
    tbproductoridentificacionnumero VARCHAR(250) NOT NULL,
    tbproductoridentificaciontipo VARCHAR(40) NOT NULL,
    tbproductornombre VARCHAR(150) NOT NULL,
    tbproductortelefono VARCHAR(20) NOT NULL,
    tbproductorcorreoelectronico VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Enlace entre el productor y su residencia principal. La tabla no almacena
-- datos de ubicación: provincia, cantón, distrito, pueblo y señas viven una sola
-- vez en tbdireccion y se alcanzan por tbdireccionid.
-- tbproductordireccionfechainicio/fechafin preparan el histórico de dirección
-- (plan §8): quedan NULL para el enlace vigente hasta que el flujo transaccional
-- de cierre+alta (todavía sin implementar) las asigne. La fecha la calcula PHP,
-- nunca el motor.
CREATE TABLE IF NOT EXISTS tbproductordireccion (
    tbproductordireccionid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbdireccionid INT NOT NULL,
    tbproductordireccionfechainicio DATETIME NULL,
    tbproductordireccionfechafin DATETIME NULL
) ENGINE=InnoDB;

USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbfinca (
    tbfincaid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbfincanombre VARCHAR(150) NOT NULL,
    tbfincaestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;

USE dbtindervacas;
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

USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbcomprador (
    tbcompradorid INT NOT NULL,
    tbcompradoridentificacionnumero VARCHAR(250) NOT NULL,
    tbcompradoridentificaciontipo VARCHAR(40) NOT NULL,
    tbcompradornombre VARCHAR(150) NOT NULL,
    tbcompradortelefono VARCHAR(20) NOT NULL,
    tbcompradorcorreoelectronico VARCHAR(150) NOT NULL,
    tbcompradorestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;

USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Ubicación física reutilizable. No pertenece a productor ni a finca: ambas
-- entidades la referencian por tbdireccionid mediante sus tablas de enlace.
CREATE TABLE IF NOT EXISTS tbdireccion (
    tbdireccionid INT NOT NULL,
    tbdireccionprovincia VARCHAR(100) NOT NULL,
    tbdireccioncanton VARCHAR(100) NOT NULL,
    tbdirecciondistrito VARCHAR(100) NOT NULL,
    tbdireccionpueblo VARCHAR(150) NULL,
    tbdireccionsenas VARCHAR(500) NULL
) ENGINE=InnoDB;

USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Enlace conceptual entre una finca y su ubicación. La política del modelo
-- espera una sola fila por tbfincaid; el motor no lo impide.
CREATE TABLE IF NOT EXISTS tbfincadireccion (
    tbfincadireccionid INT NOT NULL,
    tbfincaid INT NOT NULL,
    tbdireccionid INT NOT NULL
) ENGINE=InnoDB;

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

USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Persona independiente responsable del transporte. Conserva el perfil de
-- persona vigente en tbproductor y tbcomprador; no reutiliza sus identificadores.
CREATE TABLE IF NOT EXISTS tbtransportista (
    tbtransportistaid INT NOT NULL,
    tbtransportistaidentificacionnumero VARCHAR(250) NOT NULL,
    tbtransportistaidentificaciontipo VARCHAR(40) NOT NULL,
    tbtransportistanombre VARCHAR(150) NOT NULL,
    tbtransportistatelefono VARCHAR(20) NOT NULL,
    tbtransportistacorreoelectronico VARCHAR(150) NOT NULL,
    tbtransportistaestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;

USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Vehículo utilizado para el transporte. Placa y vin se almacenan tal cual: la
-- detección de repetidos es una consulta de diagnóstico, no una restricción.
CREATE TABLE IF NOT EXISTS tbvehiculo (
    tbvehiculoid INT NOT NULL,
    tbvehiculoplaca VARCHAR(20) NOT NULL,
    tbvehiculovin VARCHAR(50) NOT NULL,
    tbvehiculomodelo VARCHAR(100) NOT NULL,
    tbvehiculoestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;

USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Enlace conceptual entre transportista y vehículo. La política del modelo
-- espera varios vehículos por transportista y un solo transportista por
-- vehículo; el motor no lo impide.
CREATE TABLE IF NOT EXISTS tbtransportistavehiculo (
    tbtransportistavehiculoid INT NOT NULL,
    tbtransportistaid INT NOT NULL,
    tbvehiculoid INT NOT NULL
) ENGINE=InnoDB;

-- ==========================================================================
-- Tablas históricas del remodelado EIF400 (plan §7, §9, §17). Se agregan sin
-- USE/SET NAMES propios porque ya rigen desde el bloque anterior; el conteo de
-- "SET NAMES" que exige Tests/naming_gate.php sigue en 12.
-- ==========================================================================

-- Historial de estado del productor. Cada fila es un periodo; el periodo
-- abierto (tbproductorestadoperiodofechafin NULL) es el estado vigente. Debe
-- existir como máximo un periodo abierto por productor: esa regla la aplica
-- PHP bajo un lock nombrado (plan §21), no el motor.
CREATE TABLE IF NOT EXISTS tbproductorestadoperiodo (
    tbproductorestadoperiodoid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductorestadoperiodoestado TINYINT(1) NOT NULL,
    tbproductorestadoperiodofechainicio DATETIME NOT NULL,
    tbproductorestadoperiodofechafin DATETIME NULL,
    tbproductorestadoperiodomotivo VARCHAR(250) NULL
) ENGINE=InnoDB;

-- Ubicación observada del productor (plan §9, §14-16). Append-only: cada
-- lectura es una fila nueva; ninguna fila se actualiza ni se borra.
CREATE TABLE IF NOT EXISTS tbproductorubicacion (
    tbproductorubicacionid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductorubicacionlatitud DECIMAL(10,7) NOT NULL,
    tbproductorubicacionlongitud DECIMAL(10,7) NOT NULL,
    tbproductorubicacionprecision DECIMAL(10,2) NULL,
    tbproductorubicacionfecha DATETIME NOT NULL,
    tbproductorubicacionorigen VARCHAR(40) NOT NULL
) ENGINE=InnoDB;

-- Actividad del productor (plan §17-18), base para la futura desactivación
-- automática por inactividad (Tools/desactivar_productores_inactivos.php,
-- todavía sin implementar).
CREATE TABLE IF NOT EXISTS tbproductoractividad (
    tbproductoractividadid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductoractividadtipo VARCHAR(60) NOT NULL,
    tbproductoractividadfecha DATETIME NOT NULL,
    tbproductoractividadorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- fin del script de instalación completa