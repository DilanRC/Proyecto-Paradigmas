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
  `tbbitacoraEntidad` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbbitacoraRegistroIdentificacionNumero` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbbitacoraAccion` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbbitacoraFecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tbbitacoraDatosAnteriores` json DEFAULT NULL,
  `tbbitacoraDatosNuevos` json DEFAULT NULL,
  `tbbitacoraActorTipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbusuarioId` bigint unsigned DEFAULT NULL,
  `tbbitacoraOrigen` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbbitacoraSolicitudId` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `idx_tbbitacora_id` (`tbbitacoraId`),
  KEY `idx_tbbitacora_entidad_registro_fecha` (`tbbitacoraEntidad`,`tbbitacoraRegistroIdentificacionNumero`,`tbbitacoraFecha`),
  KEY `idx_tbbitacora_solicitud` (`tbbitacoraSolicitudId`),
  KEY `idx_tbbitacora_fecha` (`tbbitacoraFecha`),
  CONSTRAINT `ck_tbbitacora_accion_no_vacia` CHECK ((char_length(trim(`tbbitacoraAccion`)) > 0)),
  CONSTRAINT `ck_tbbitacora_actor_no_autenticado` CHECK (((`tbbitacoraActorTipo` <> _utf8mb4'NO_AUTENTICADO') or (`tbusuarioId` is null))),
  CONSTRAINT `ck_tbbitacora_actor_no_vacio` CHECK ((char_length(trim(`tbbitacoraActorTipo`)) > 0)),
  CONSTRAINT `ck_tbbitacora_entidad_no_vacia` CHECK ((char_length(trim(`tbbitacoraEntidad`)) > 0)),
  CONSTRAINT `ck_tbbitacora_origen_no_vacio` CHECK ((char_length(trim(`tbbitacoraOrigen`)) > 0)),
  CONSTRAINT `ck_tbbitacora_solicitud_no_vacia` CHECK ((char_length(trim(`tbbitacoraSolicitudId`)) > 0))
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductores`
--

DROP TABLE IF EXISTS `tbproductores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductores` (
  `tbproductoresIdentificacionNumero` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoresIdentificacionTipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoresNombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoresTelefono` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoresCorreoElectronico` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoresEstado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`tbproductoresIdentificacionNumero`),
  KEY `idx_tbproductores_nombre` (`tbproductoresNombre`),
  KEY `idx_tbproductores_estado` (`tbproductoresEstado`),
  KEY `idx_tbproductores_correo` (`tbproductoresCorreoElectronico`),
  CONSTRAINT `ck_tbproductores_estado` CHECK ((`tbproductoresEstado` in (0,1))),
  CONSTRAINT `ck_tbproductores_identificacion_no_vacia` CHECK ((char_length(trim(`tbproductoresIdentificacionNumero`)) > 0)),
  CONSTRAINT `ck_tbproductores_nombre` CHECK ((char_length(trim(`tbproductoresNombre`)) between 3 and 150)),
  CONSTRAINT `ck_tbproductores_tipo` CHECK ((`tbproductoresIdentificacionTipo` in (_utf8mb4'CEDULA_FISICA',_utf8mb4'CEDULA_JURIDICA',_utf8mb4'DIMEX',_utf8mb4'NITE',_utf8mb4'PASAPORTE')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductoresdireccion`
--

DROP TABLE IF EXISTS `tbproductoresdireccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductoresdireccion` (
  `tbproductoresIdentificacionNumero` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoresdireccionProvincia` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoresdireccionCanton` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoresdireccionDistrito` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoresdireccionPueblo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tbproductoresdireccionSenas` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_tbproductoresdireccion_identificacion` (`tbproductoresIdentificacionNumero`),
  CONSTRAINT `ck_tbproductoresdireccion_canton` CHECK ((char_length(trim(`tbproductoresdireccionCanton`)) > 0)),
  CONSTRAINT `ck_tbproductoresdireccion_distrito` CHECK ((char_length(trim(`tbproductoresdireccionDistrito`)) > 0)),
  CONSTRAINT `ck_tbproductoresdireccion_provincia` CHECK ((char_length(trim(`tbproductoresdireccionProvincia`)) > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductoresfinca`
--

DROP TABLE IF EXISTS `tbproductoresfinca`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductoresfinca` (
  `tbproductoresIdentificacionNumero` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoresfincaNombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoresfincaEstado` tinyint(1) NOT NULL DEFAULT '1',
  KEY `idx_tbproductoresfinca_productor_nombre` (`tbproductoresIdentificacionNumero`,`tbproductoresfincaNombre`),
  KEY `idx_tbproductoresfinca_nombre` (`tbproductoresfincaNombre`),
  CONSTRAINT `ck_tbproductoresfinca_estado` CHECK ((`tbproductoresfincaEstado` in (0,1))),
  CONSTRAINT `ck_tbproductoresfinca_nombre` CHECK ((char_length(trim(`tbproductoresfincaNombre`)) > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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

-- Dump completed on 2026-08-02 19:50:30
