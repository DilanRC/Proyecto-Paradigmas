-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: dbtindercows
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `tbbitacora`
--

DROP TABLE IF EXISTS `tbbitacora`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbbitacora` (
  `tbbitacoraId` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tbbitacoraEntidad` varchar(80) NOT NULL,
  `tbbitacoraRegistroId` bigint unsigned NOT NULL,
  `tbbitacoraAccion` varchar(30) NOT NULL,
  `tbbitacoraFecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tbbitacoraDatosAnteriores` json DEFAULT NULL,
  `tbbitacoraDatosNuevos` json DEFAULT NULL,
  `tbbitacoraActorTipo` varchar(30) NOT NULL,
  `tbusuarioId` bigint unsigned DEFAULT NULL,
  `tbbitacoraOrigen` varchar(100) NOT NULL,
  `tbbitacoraSolicitudId` varchar(100) NOT NULL,
  PRIMARY KEY (`tbbitacoraId`),
  KEY `idx_tbbitacora_entidad_registro_fecha` (`tbbitacoraEntidad`,`tbbitacoraRegistroId`,`tbbitacoraFecha`),
  KEY `idx_tbbitacora_solicitud` (`tbbitacoraSolicitudId`),
  KEY `idx_tbbitacora_fecha` (`tbbitacoraFecha`),
  CONSTRAINT `ck_tbbitacora_accion_no_vacia` CHECK ((char_length(trim(`tbbitacoraAccion`)) > 0)),
  CONSTRAINT `ck_tbbitacora_actor_no_autenticado` CHECK (((`tbbitacoraActorTipo` <> _utf8mb4'NO_AUTENTICADO') or (`tbusuarioId` is null))),
  CONSTRAINT `ck_tbbitacora_actor_no_vacio` CHECK ((char_length(trim(`tbbitacoraActorTipo`)) > 0)),
  CONSTRAINT `ck_tbbitacora_entidad_no_vacia` CHECK ((char_length(trim(`tbbitacoraEntidad`)) > 0)),
  CONSTRAINT `ck_tbbitacora_origen_no_vacio` CHECK ((char_length(trim(`tbbitacoraOrigen`)) > 0)),
  CONSTRAINT `ck_tbbitacora_solicitud_no_vacia` CHECK ((char_length(trim(`tbbitacoraSolicitudId`)) > 0))
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbfinca`
--

DROP TABLE IF EXISTS `tbfinca`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbfinca` (
  `tbfincaId` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tbfincaNombre` varchar(150) NOT NULL,
  `tbfincaEstado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`tbfincaId`),
  KEY `idx_tbfinca_nombre` (`tbfincaNombre`),
  KEY `idx_tbfinca_estado` (`tbfincaEstado`),
  CONSTRAINT `ck_tbfinca_estado` CHECK ((`tbfincaEstado` in (0,1))),
  CONSTRAINT `ck_tbfinca_nombre_no_vacio` CHECK ((char_length(trim(`tbfincaNombre`)) > 0))
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbidentificaciontipo`
--

DROP TABLE IF EXISTS `tbidentificaciontipo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbidentificaciontipo` (
  `tbidentificaciontipoId` smallint unsigned NOT NULL AUTO_INCREMENT,
  `tbidentificaciontipoCodigo` varchar(40) NOT NULL,
  `tbidentificaciontipoNombre` varchar(100) NOT NULL,
  `tbidentificaciontipoEstado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`tbidentificaciontipoId`),
  UNIQUE KEY `uq_tbidentificaciontipo_codigo` (`tbidentificaciontipoCodigo`),
  UNIQUE KEY `uq_tbidentificaciontipo_nombre` (`tbidentificaciontipoNombre`),
  KEY `idx_tbidentificaciontipo_estado` (`tbidentificaciontipoEstado`),
  CONSTRAINT `ck_tbidentificaciontipo_codigo_no_vacio` CHECK ((char_length(trim(`tbidentificaciontipoCodigo`)) > 0)),
  CONSTRAINT `ck_tbidentificaciontipo_estado` CHECK ((`tbidentificaciontipoEstado` in (0,1))),
  CONSTRAINT `ck_tbidentificaciontipo_nombre_no_vacio` CHECK ((char_length(trim(`tbidentificaciontipoNombre`)) > 0))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbparticipante`
--

DROP TABLE IF EXISTS `tbparticipante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbparticipante` (
  `tbparticipanteId` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tbparticipanteNombre` varchar(150) NOT NULL,
  `tbparticipanteTelefono` varchar(20) NOT NULL,
  `tbparticipanteCorreoElectronico` varchar(150) NOT NULL,
  `tbparticipanteEstado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`tbparticipanteId`),
  KEY `idx_tbparticipante_nombre` (`tbparticipanteNombre`),
  KEY `idx_tbparticipante_estado` (`tbparticipanteEstado`),
  KEY `idx_tbparticipante_correo` (`tbparticipanteCorreoElectronico`),
  CONSTRAINT `ck_tbparticipante_correo_minuscula` CHECK ((cast(`tbparticipanteCorreoElectronico` as char charset binary) = cast(lower(`tbparticipanteCorreoElectronico`) as char charset binary))),
  CONSTRAINT `ck_tbparticipante_correo_no_vacio` CHECK ((char_length(trim(`tbparticipanteCorreoElectronico`)) > 0)),
  CONSTRAINT `ck_tbparticipante_estado` CHECK ((`tbparticipanteEstado` in (0,1))),
  CONSTRAINT `ck_tbparticipante_nombre_longitud` CHECK ((char_length(trim(`tbparticipanteNombre`)) between 3 and 150)),
  CONSTRAINT `ck_tbparticipante_telefono_no_vacio` CHECK ((char_length(trim(`tbparticipanteTelefono`)) > 0))
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbparticipantedireccion`
--

DROP TABLE IF EXISTS `tbparticipantedireccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbparticipantedireccion` (
  `tbparticipantedireccionId` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tbparticipanteId` bigint unsigned NOT NULL,
  `tbparticipantedireccionProvincia` varchar(100) NOT NULL,
  `tbparticipantedireccionCanton` varchar(100) NOT NULL,
  `tbparticipantedireccionDistrito` varchar(100) NOT NULL,
  `tbparticipantedireccionPueblo` varchar(150) DEFAULT NULL,
  `tbparticipantedireccionSenas` varchar(500) DEFAULT NULL,
  `tbparticipantedireccionEsPrincipal` tinyint(1) NOT NULL DEFAULT '1',
  `tbparticipantedireccionEstado` tinyint(1) NOT NULL DEFAULT '1',
  `tbparticipantedireccionPrincipalActivaParticipanteId` bigint unsigned GENERATED ALWAYS AS ((case when ((`tbparticipantedireccionEsPrincipal` = 1) and (`tbparticipantedireccionEstado` = 1)) then `tbparticipanteId` else NULL end)) STORED,
  PRIMARY KEY (`tbparticipantedireccionId`),
  UNIQUE KEY `uq_tbparticipantedireccion_principal_activa` (`tbparticipantedireccionPrincipalActivaParticipanteId`),
  KEY `idx_tbparticipantedireccion_participante_estado` (`tbparticipanteId`,`tbparticipantedireccionEstado`),
  CONSTRAINT `fk_tbparticipantedireccion_participante` FOREIGN KEY (`tbparticipanteId`) REFERENCES `tbparticipante` (`tbparticipanteId`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `ck_tbparticipantedireccion_canton_no_vacio` CHECK ((char_length(trim(`tbparticipantedireccionCanton`)) > 0)),
  CONSTRAINT `ck_tbparticipantedireccion_distrito_no_vacio` CHECK ((char_length(trim(`tbparticipantedireccionDistrito`)) > 0)),
  CONSTRAINT `ck_tbparticipantedireccion_estado` CHECK ((`tbparticipantedireccionEstado` in (0,1))),
  CONSTRAINT `ck_tbparticipantedireccion_principal` CHECK ((`tbparticipantedireccionEsPrincipal` in (0,1))),
  CONSTRAINT `ck_tbparticipantedireccion_provincia_no_vacia` CHECK ((char_length(trim(`tbparticipantedireccionProvincia`)) > 0))
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbparticipanteidentificacion`
--

DROP TABLE IF EXISTS `tbparticipanteidentificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbparticipanteidentificacion` (
  `tbparticipanteidentificacionId` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tbparticipanteId` bigint unsigned NOT NULL,
  `tbidentificaciontipoId` smallint unsigned NOT NULL,
  `tbparticipanteidentificacionNumero` varchar(250) NOT NULL,
  `tbparticipanteidentificacionNumeroNormalizado` varchar(250) NOT NULL,
  `tbparticipanteidentificacionEsPrincipal` tinyint(1) NOT NULL DEFAULT '1',
  `tbparticipanteidentificacionEstado` tinyint(1) NOT NULL DEFAULT '1',
  `tbparticipanteidentificacionPrincipalActivaParticipanteId` bigint unsigned GENERATED ALWAYS AS ((case when ((`tbparticipanteidentificacionEsPrincipal` = 1) and (`tbparticipanteidentificacionEstado` = 1)) then `tbparticipanteId` else NULL end)) STORED,
  PRIMARY KEY (`tbparticipanteidentificacionId`),
  UNIQUE KEY `uq_tbparticipanteidentificacion_tipo_numero_normalizado` (`tbidentificaciontipoId`,`tbparticipanteidentificacionNumeroNormalizado`),
  UNIQUE KEY `uq_tbparticipanteidentificacion_principal_activa` (`tbparticipanteidentificacionPrincipalActivaParticipanteId`),
  KEY `idx_tbparticipanteidentificacion_participante_estado` (`tbparticipanteId`,`tbparticipanteidentificacionEstado`),
  CONSTRAINT `fk_tbparticipanteidentificacion_participante` FOREIGN KEY (`tbparticipanteId`) REFERENCES `tbparticipante` (`tbparticipanteId`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_tbparticipanteidentificacion_tipo` FOREIGN KEY (`tbidentificaciontipoId`) REFERENCES `tbidentificaciontipo` (`tbidentificaciontipoId`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `ck_tbparticipanteidentificacion_estado` CHECK ((`tbparticipanteidentificacionEstado` in (0,1))),
  CONSTRAINT `ck_tbparticipanteidentificacion_normalizado_no_vacio` CHECK ((char_length(trim(`tbparticipanteidentificacionNumeroNormalizado`)) > 0)),
  CONSTRAINT `ck_tbparticipanteidentificacion_numero_no_vacio` CHECK ((char_length(trim(`tbparticipanteidentificacionNumero`)) > 0)),
  CONSTRAINT `ck_tbparticipanteidentificacion_principal` CHECK ((`tbparticipanteidentificacionEsPrincipal` in (0,1)))
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbparticipanterol`
--

DROP TABLE IF EXISTS `tbparticipanterol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbparticipanterol` (
  `tbparticipanteId` bigint unsigned NOT NULL,
  `tbrolId` smallint unsigned NOT NULL,
  `tbparticipanterolEstado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`tbparticipanteId`,`tbrolId`),
  KEY `idx_tbparticipanterol_rol_estado` (`tbrolId`,`tbparticipanterolEstado`),
  CONSTRAINT `fk_tbparticipanterol_participante` FOREIGN KEY (`tbparticipanteId`) REFERENCES `tbparticipante` (`tbparticipanteId`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_tbparticipanterol_rol` FOREIGN KEY (`tbrolId`) REFERENCES `tbrol` (`tbrolId`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `ck_tbparticipanterol_estado` CHECK ((`tbparticipanterolEstado` in (0,1)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductorfinca`
--

DROP TABLE IF EXISTS `tbproductorfinca`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductorfinca` (
  `tbparticipanteId` bigint unsigned NOT NULL,
  `tbfincaId` bigint unsigned NOT NULL,
  `tbproductorfincaEstado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`tbparticipanteId`,`tbfincaId`),
  KEY `idx_tbproductorfinca_finca_estado` (`tbfincaId`,`tbproductorfincaEstado`),
  CONSTRAINT `fk_tbproductorfinca_finca` FOREIGN KEY (`tbfincaId`) REFERENCES `tbfinca` (`tbfincaId`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_tbproductorfinca_participante` FOREIGN KEY (`tbparticipanteId`) REFERENCES `tbparticipante` (`tbparticipanteId`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `ck_tbproductorfinca_estado` CHECK ((`tbproductorfincaEstado` in (0,1)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbrol`
--

DROP TABLE IF EXISTS `tbrol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbrol` (
  `tbrolId` smallint unsigned NOT NULL AUTO_INCREMENT,
  `tbrolCodigo` varchar(40) NOT NULL,
  `tbrolNombre` varchar(100) NOT NULL,
  `tbrolEstado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`tbrolId`),
  UNIQUE KEY `uq_tbrol_codigo` (`tbrolCodigo`),
  UNIQUE KEY `uq_tbrol_nombre` (`tbrolNombre`),
  KEY `idx_tbrol_estado` (`tbrolEstado`),
  CONSTRAINT `ck_tbrol_codigo_no_vacio` CHECK ((char_length(trim(`tbrolCodigo`)) > 0)),
  CONSTRAINT `ck_tbrol_estado` CHECK ((`tbrolEstado` in (0,1))),
  CONSTRAINT `ck_tbrol_nombre_no_vacio` CHECK ((char_length(trim(`tbrolNombre`)) > 0))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'dbtindercows'
--

--
-- Dumping routines for database 'dbtindercows'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-02  1:09:44
