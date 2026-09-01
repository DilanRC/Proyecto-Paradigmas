-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: dbmercadoganadero
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
  `tbbitacoraid` bigint unsigned NOT NULL,
  `tbbitacoraentidad` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbbitacoraregistroidentificacionnumero` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbbitacoraaccion` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbbitacorafecha` datetime NOT NULL,
  `tbbitacoradatosanteriores` json DEFAULT NULL,
  `tbbitacoradatosnuevos` json DEFAULT NULL,
  `tbbitacoraactortipo` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbbitacorausuarioid` bigint unsigned DEFAULT NULL,
  `tbbitacoraorigen` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbbitacorasolicitudid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbcomprador`
--

DROP TABLE IF EXISTS `tbcomprador`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbcomprador` (
  `tbcompradorid` int NOT NULL,
  `tbpersonaid` int NOT NULL,
  `tbcompradorestado` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbdireccion`
--

DROP TABLE IF EXISTS `tbdireccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbdireccion` (
  `tbdireccionid` int NOT NULL,
  `tbdireccionprovincia` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbdireccioncanton` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbdirecciondistrito` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbdireccionpueblo` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tbdireccionsenas` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbfinca`
--

DROP TABLE IF EXISTS `tbfinca`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbfinca` (
  `tbfincaid` int NOT NULL,
  `tbproductorid` int NOT NULL,
  `tbfincanombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbfincaestado` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbfincadireccion`
--

DROP TABLE IF EXISTS `tbfincadireccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbfincadireccion` (
  `tbfincadireccionid` int NOT NULL,
  `tbfincaid` int NOT NULL,
  `tbdireccionid` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbpagometodo`
--

DROP TABLE IF EXISTS `tbpagometodo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbpagometodo` (
  `tbpagometodoid` int NOT NULL,
  `tbpagometodonombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbpagometododescripcion` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbpagometodoactivo` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbpersona`
--

DROP TABLE IF EXISTS `tbpersona`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbpersona` (
  `tbpersonaid` int NOT NULL,
  `tbpersonaidentificacionnumero` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbpersonaidentificaciontipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbpersonanombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbpersonatelefono` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbpersonacorreoelectronico` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbpersonaestado` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductor`
--

DROP TABLE IF EXISTS `tbproductor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductor` (
  `tbproductorid` int NOT NULL,
  `tbpersonaid` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductoractividad`
--

DROP TABLE IF EXISTS `tbproductoractividad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductoractividad` (
  `tbproductoractividadid` int NOT NULL,
  `tbproductorid` int NOT NULL,
  `tbproductoractividadtipo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbproductoractividadfecha` datetime NOT NULL,
  `tbproductoractividadorigen` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductordireccion`
--

DROP TABLE IF EXISTS `tbproductordireccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductordireccion` (
  `tbproductordireccionid` int NOT NULL,
  `tbproductorid` int NOT NULL,
  `tbdireccionid` int NOT NULL,
  `tbproductordireccionfechainicio` datetime DEFAULT NULL,
  `tbproductordireccionfechafin` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductorestadoperiodo`
--

DROP TABLE IF EXISTS `tbproductorestadoperiodo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductorestadoperiodo` (
  `tbproductorestadoperiodoid` int NOT NULL,
  `tbproductorid` int NOT NULL,
  `tbproductorestadoperiodoestado` tinyint(1) NOT NULL,
  `tbproductorestadoperiodofechainicio` datetime NOT NULL,
  `tbproductorestadoperiodofechafin` datetime DEFAULT NULL,
  `tbproductorestadoperiodomotivo` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbproductorubicacion`
--

DROP TABLE IF EXISTS `tbproductorubicacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbproductorubicacion` (
  `tbproductorubicacionid` int NOT NULL,
  `tbproductorid` int NOT NULL,
  `tbproductorubicacionlatitud` decimal(10,7) NOT NULL,
  `tbproductorubicacionlongitud` decimal(10,7) NOT NULL,
  `tbproductorubicacionprecision` decimal(10,2) DEFAULT NULL,
  `tbproductorubicacionfecha` datetime NOT NULL,
  `tbproductorubicacionorigen` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbtransportista`
--

DROP TABLE IF EXISTS `tbtransportista`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbtransportista` (
  `tbtransportistaid` int NOT NULL,
  `tbpersonaid` int NOT NULL,
  `tbtransportistaestado` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbtransportistavehiculo`
--

DROP TABLE IF EXISTS `tbtransportistavehiculo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbtransportistavehiculo` (
  `tbtransportistavehiculoid` int NOT NULL,
  `tbtransportistaid` int NOT NULL,
  `tbvehiculoid` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tbvehiculo`
--

DROP TABLE IF EXISTS `tbvehiculo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbvehiculo` (
  `tbvehiculoid` int NOT NULL,
  `tbvehiculoplaca` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbvehiculovin` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbvehiculomodelo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tbvehiculoestado` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'dbmercadoganadero'
--

--
-- Dumping routines for database 'dbmercadoganadero'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-01  9:21:53
