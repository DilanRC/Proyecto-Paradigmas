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
  `tbbitacoraUsuarioId` bigint unsigned DEFAULT NULL,
  `tbbitacoraOrigen` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbbitacoraSolicitudId` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  KEY `idx_tbbitacora_id` (`tbbitacoraId`),
  KEY `idx_tbbitacora_entidad_registro_fecha` (`tbbitacoraEntidad`,`tbbitacoraRegistroIdentificacionNumero`,`tbbitacoraFecha`),
  KEY `idx_tbbitacora_solicitud` (`tbbitacoraSolicitudId`),
  KEY `idx_tbbitacora_fecha` (`tbbitacoraFecha`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductor`
--

DROP TABLE IF EXISTS `tbproductor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductor` (
  `tbproductorId` int NOT NULL,
  `tbproductorIdentificacionNumero` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductorIdentificacionTipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductorNombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductorTelefono` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductorCorreoElectronico` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductorEstado` tinyint(1) NOT NULL DEFAULT '1',
  KEY `idx_tbproductor_id` (`tbproductorId`),
  KEY `idx_tbproductor_identificacion` (`tbproductorIdentificacionNumero`),
  KEY `idx_tbproductor_nombre` (`tbproductorNombre`),
  KEY `idx_tbproductor_estado` (`tbproductorEstado`),
  KEY `idx_tbproductor_correo` (`tbproductorCorreoElectronico`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductordireccion`
--

DROP TABLE IF EXISTS `tbproductordireccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductordireccion` (
  `tbproductorId` int NOT NULL,
  `tbproductordireccionProvincia` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductordireccionCanton` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductordireccionDistrito` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductordireccionPueblo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tbproductordireccionSenas` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `idx_tbproductordireccion_productor_id` (`tbproductorId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductorfinca`
--

DROP TABLE IF EXISTS `tbproductorfinca`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductorfinca` (
  `tbproductorId` int NOT NULL,
  `tbproductorfincaNombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductorfincaEstado` tinyint(1) NOT NULL DEFAULT '1',
  KEY `idx_tbproductorfinca_productor_nombre` (`tbproductorId`,`tbproductorfincaNombre`),
  KEY `idx_tbproductorfinca_nombre` (`tbproductorfincaNombre`)
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

-- Dump completed on 2026-08-02 20:12:23
