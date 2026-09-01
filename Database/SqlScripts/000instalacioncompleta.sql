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

-- Enlace entre el productor y su residencia principal. La tabla no almacena
-- datos de ubicación: provincia, cantón, distrito, pueblo y señas viven una sola
-- vez en tbdireccion y se alcanzan por tbdireccionid.
-- tbproductordireccionfechainicio/fechafin sostienen el histórico de dirección:
-- el flujo PHP vigente cierra el periodo abierto e inserta uno nuevo dentro de
-- una transacción. La fecha la calcula PHP, nunca el motor.
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

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Enlace conceptual entre una finca y su ubicación. La política del modelo
-- espera una sola fila por tbfincaid; el motor no lo impide.
CREATE TABLE IF NOT EXISTS tbfincadireccion (
    tbfincadireccionid INT NOT NULL,
    tbfincaid INT NOT NULL,
    tbdireccionid INT NOT NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Catálogo de métodos de pago. El alcance vigente solo contempla efectivo y
-- todavía no se relaciona con ninguna operación económica.
CREATE TABLE IF NOT EXISTS tbpagometodo (
    tbpagometodoid INT NOT NULL,
    tbpagometodonombre VARCHAR(100) NOT NULL,
    tbpagometododescripcion VARCHAR(250) NOT NULL,
    tbpagometodoactivo TINYINT(1) NOT NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Capacidad logística de una persona. La identidad vive solamente en tbpersona.
CREATE TABLE IF NOT EXISTS tbtransportista (
    tbtransportistaid INT NOT NULL,
    tbpersonaid INT NOT NULL,
    tbtransportistaestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;

USE bdmercadoganadero;
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

USE bdmercadoganadero;
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
--
-- Los estados de perfil se conservan y tbpersonaestado controla la identidad global.
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

-- Clasificaciones comerciales del productor. COMPRADOR y VENDEDOR se validan
-- en PHP; el motor no usa CHECK. Un productor puede tener periodos abiertos
-- simultáneos si son de tipo distinto.
CREATE TABLE IF NOT EXISTS tbproductorclasificacionperiodo (
    tbproductorclasificacionperiodoid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductorclasificacionperiodotipo VARCHAR(30) NOT NULL,
    tbproductorclasificacionperiodofechainicio DATETIME NOT NULL,
    tbproductorclasificacionperiodofechafin DATETIME NULL,
    tbproductorclasificacionperiodomotivo VARCHAR(250) NULL
) ENGINE=InnoDB;

-- Identidad estable del animal. No guarda peso, edad actual ni dueño actual:
-- esos datos cambian y viven como observaciones o hechos.
CREATE TABLE IF NOT EXISTS tbanimal (
    tbanimalid INT NOT NULL,
    tbanimalcodigo VARCHAR(100) NULL,
    tbanimalsexo VARCHAR(20) NULL,
    tbanimalraza VARCHAR(100) NULL,
    tbanimalfecharegistroensistema DATETIME NOT NULL,
    tbanimalorigenregistro VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Observaciones históricas del animal. Cada fila guarda lo observado, cuándo,
-- desde dónde y en qué contexto; no inventa fecha de nacimiento.
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

-- Publicación del animal. Congela vendedor y finca al momento de publicar para
-- no depender de relaciones futuras al reconstruir quién vendía.
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

-- Hecho económico de compra. No define ciclo de estados porque Calidad no
-- aprobó una semántica suficiente; el estado se decidirá en Backend.
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

-- Hecho económico de venta. tbcompraid es opcional: el animal pudo nacer en la
-- finca o existir antes del sistema.
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

-- Interacciones comerciales especializadas sobre animales: ME_GUSTA, SEGUIR y
-- eventos equivalentes se validan en PHP junto con AGREGAR/RETIRAR.
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

-- Historial de agregar/retirar animales del carrito. No se borra pasado.
CREATE TABLE IF NOT EXISTS tbcarritoanimal (
    tbcarritoanimalid INT NOT NULL,
    tbcarritoid INT NOT NULL,
    tbanimalid INT NOT NULL,
    tbcarritoanimalaccion VARCHAR(30) NOT NULL,
    tbcarritoanimalfecha DATETIME NOT NULL,
    tbcarritoanimalorigen VARCHAR(100) NULL
) ENGINE=InnoDB;

-- Periodos de estado del transportista. La fecha real de inicio puede quedar
-- NULL si no existe evidencia; no se usa fecha de migración como fecha real.
CREATE TABLE IF NOT EXISTS tbtransportistaestadoperiodo (
    tbtransportistaestadoperiodoid INT NOT NULL,
    tbtransportistaid INT NOT NULL,
    tbtransportistaestadoperiodoestado TINYINT(1) NOT NULL,
    tbtransportistaestadoperiodofechainicio DATETIME NULL,
    tbtransportistaestadoperiodofechafin DATETIME NULL,
    tbtransportistaestadoperiodomotivo VARCHAR(250) NULL,
    tbtransportistaestadoperiodofecharegistroensistema DATETIME NOT NULL
) ENGINE=InnoDB;

-- Flete realizado por un transportista. El método de pago usado se conserva
-- aquí; cantidad semanal y método frecuente se derivan por consultas.
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

-- Reseñas históricas del transportista. El promedio se deriva con AVG.
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

-- fin del script de instalación completa
