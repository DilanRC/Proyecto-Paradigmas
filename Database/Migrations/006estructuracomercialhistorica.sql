USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Estructura comercial e histórica confirmada por Calidad para preparar la
-- base de datos de Backend. Esta migración NO inventa hechos pasados: no crea
-- compras, ventas, clasificaciones, producción/salud, interacciones, fletes ni
-- reseñas sobre datos existentes.
--
-- Pasada de concordancia contra la evidencia directa de Calidad: el animal
-- guarda identificación, raza, sexo y características (edad y peso siguen
-- fuera de la identidad y viven en tbanimalproduccionsalud); el flete recupera
-- vehículo, cantidad de cabezas y distancia; el transportista gana horario
-- histórico; la reseña la firma la persona que contrató, no un productor; la
-- venta recupera dirección y propósito; y ningún estado mutable sin histórico
-- sobrevive: tbcarritoestado y tbanimalpublicacionestado pasan a periodos.
--
-- La migración sigue siendo solo CREATE TABLE IF NOT EXISTS. Un entorno que ya
-- había ejecutado la versión anterior de este archivo debe reinstalarse limpio
-- (docker compose down -v) porque la corrección renombra y retira columnas.

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
-- Transiciones de estado de la publicación. Reemplaza la columna mutable
-- tbanimalpublicacionestado: el estado vigente es el periodo abierto
-- (fechafin NULL) y el pasado nunca se sobrescribe.
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
-- Transiciones de estado del carrito. Reemplaza la columna mutable
-- tbcarritoestado con la misma regla de periodo abierto único por carrito,
-- aplicada por PHP.
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
-- Horario declarado del transportista. Cada fila es un periodo de vigencia por
-- día de la semana: cambiar el horario cierra el periodo y abre otro.
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
