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
-- Dumping data for table `tbbitacora`
--

LOCK TABLES `tbbitacora` WRITE;
/*!40000 ALTER TABLE `tbbitacora` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbbitacora` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbcomprador`
--

LOCK TABLES `tbcomprador` WRITE;
/*!40000 ALTER TABLE `tbcomprador` DISABLE KEYS */;
INSERT INTO `tbcomprador` VALUES (1,5,0),(2,1,1),(3,25,1);
/*!40000 ALTER TABLE `tbcomprador` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbdireccion`
--

LOCK TABLES `tbdireccion` WRITE;
/*!40000 ALTER TABLE `tbdireccion` DISABLE KEYS */;
INSERT INTO `tbdireccion` VALUES (1,'Alajuela','San Carlos','Quesada','Centro','Datos ficticios para demostracion.'),(2,'Guanacaste','Tilaran','Tilaran',NULL,'Datos ficticios para demostracion.'),(3,'Heredia','Cantón Prueba','Distrito Prueba',NULL,'Registro ficticio generado por Tests.'),(4,'','','',NULL,NULL),(5,'Limón','Cantón Prueba','Distrito Prueba',NULL,'Registro ficticio generado por Tests.'),(6,'','','',NULL,NULL),(8,'','','',NULL,NULL),(9,'','','',NULL,NULL),(10,'Limón','Cantón Prueba','Distrito Prueba',NULL,'Registro ficticio generado por Tests.'),(11,'','','',NULL,NULL),(13,'','','',NULL,NULL),(14,'','','',NULL,NULL),(15,'Limón','Cantón Prueba','Distrito Prueba',NULL,'Registro ficticio generado por Tests.'),(16,'','','',NULL,NULL),(18,'','','',NULL,NULL),(19,'','','',NULL,NULL),(20,'Limón','Cantón Prueba','Distrito Prueba',NULL,'Registro ficticio generado por Tests.'),(21,'','','',NULL,NULL),(23,'','','',NULL,NULL),(24,'Guanacaste','Santa Cruz','Tamarindo','Villarreal','200 metros norte de la plaza'),(25,'','','',NULL,NULL),(26,'','','',NULL,NULL),(27,'Limón','Cantón Prueba','Distrito Prueba',NULL,'Registro ficticio generado por Tests.'),(28,'Limón','Cantón Prueba','Distrito Prueba',NULL,'Registro ficticio generado por Tests.'),(29,'','','',NULL,NULL),(31,'','','',NULL,NULL);
/*!40000 ALTER TABLE `tbdireccion` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbfinca`
--

LOCK TABLES `tbfinca` WRITE;
/*!40000 ALTER TABLE `tbfinca` DISABLE KEYS */;
INSERT INTO `tbfinca` VALUES (1,1,'Finca El Roble',1),(2,1,'Finca Valle Verde',1),(3,2,'Finca Valle Verde',1),(4,5,'Finca Uno',0),(5,5,'Finca Dos',0),(6,5,'Finca Tres',1);
/*!40000 ALTER TABLE `tbfinca` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbfincadireccion`
--

LOCK TABLES `tbfincadireccion` WRITE;
/*!40000 ALTER TABLE `tbfincadireccion` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbfincadireccion` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbpagometodo`
--

LOCK TABLES `tbpagometodo` WRITE;
/*!40000 ALTER TABLE `tbpagometodo` DISABLE KEYS */;
INSERT INTO `tbpagometodo` VALUES (1,'Efectivo','Pago realizado en efectivo',1);
/*!40000 ALTER TABLE `tbpagometodo` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbpersona`
--

LOCK TABLES `tbpersona` WRITE;
/*!40000 ALTER TABLE `tbpersona` DISABLE KEYS */;
INSERT INTO `tbpersona` VALUES (1,'101110111','CEDULA_FISICA','Maria Fernandez Solano','88881111','contacto.compartido@example.test',1),(2,'3101111111','CEDULA_JURIDICA','Ganaderia Valle Verde S.A.','+50622221111','contacto.compartido@example.test',1),(3,'TST65FEBA03BFFDC3A4','PASAPORTE','Concurrente A','88887777','a@example.test',1),(4,'TST83B7BD855E58B501','PASAPORTE','Concurrente B','88887777','b@example.test',1),(5,'CO001ABA0E5F','PASAPORTE','Comprador Ficticio de Prueba','+50622223333','actualizado.comprador@example.test',1),(6,'TR00A8179591','PASAPORTE','Transportista Ficticio de Prueba','+50622223333','actualizado.transportista@example.test',1),(7,'AB0011E850CA','PASAPORTE','Productor Ficticio de Prueba','+50622223333','actualizado@example.test',1),(-222732,'TST936ABEF00F3E0686','SIN_CATALOGO','','','directo@example.test',9),(-1055846,'TST936ABEF00F3E0686','SIN_CATALOGO','','','directo@example.test',9),(8,'CONCT1D88BC642','PASAPORTE','Transportista Concurrencia 1','88888888','conc1@test.com',1),(9,'CONCT253F317C4','PASAPORTE','Transportista Concurrencia 2','87777777','conc2@test.com',1),(10,'FIXTVA790D9F','PASAPORTE','Transportista HTTP A','88888888','fix-tva@test.com',1),(11,'FIXTVB660BBE','PASAPORTE','Transportista HTTP B','87777777','fix-tvb@test.com',1),(12,'CONCT1B925B37A','PASAPORTE','Transportista Concurrencia 1','88888888','conc1@test.com',1),(13,'CONCT233EE45FD','PASAPORTE','Transportista Concurrencia 2','87777777','conc2@test.com',1),(-153226,'TSTF008324D22A59791','SIN_CATALOGO','','','directo@example.test',9),(-1776057,'TSTF008324D22A59791','SIN_CATALOGO','','','directo@example.test',9),(-909521,'TST0B1B1BD63580E2BE','SIN_CATALOGO','','','directo@example.test',9),(-1518543,'TST0B1B1BD63580E2BE','SIN_CATALOGO','','','directo@example.test',9),(14,'CONCT17B93BD0F','PASAPORTE','Transportista Concurrencia 1','88888888','conc1@test.com',1),(15,'CONCT261697611','PASAPORTE','Transportista Concurrencia 2','87777777','conc2@test.com',1),(16,'FIXTVA968E45','PASAPORTE','Transportista HTTP A','88888888','fix-tva@test.com',1),(17,'FIXTVB6999CF','PASAPORTE','Transportista HTTP B','87777777','fix-tvb@test.com',1),(18,'CONCT10658B5D8','PASAPORTE','Transportista Concurrencia 1','88888888','conc1@test.com',1),(19,'CONCT2D33F97FC','PASAPORTE','Transportista Concurrencia 2','87777777','conc2@test.com',1),(-483422,'TST4171933F3810BB6B','SIN_CATALOGO','','','directo@example.test',9),(-1095806,'TST4171933F3810BB6B','SIN_CATALOGO','','','directo@example.test',9),(-571785,'TST9FA57D458E611AEA','SIN_CATALOGO','','','directo@example.test',9),(-1147192,'TST9FA57D458E611AEA','SIN_CATALOGO','','','directo@example.test',9),(-806957,'TST135BD3F0FC1F3DB7','SIN_CATALOGO','','','directo@example.test',9),(-1614246,'TST135BD3F0FC1F3DB7','SIN_CATALOGO','','','directo@example.test',9),(20,'CONCT12CF3182D','PASAPORTE','Transportista Concurrencia 1','88888888','conc1@test.com',1),(21,'CONCT2FE0AA108','PASAPORTE','Transportista Concurrencia 2','87777777','conc2@test.com',1),(22,'FIXTVA55BADA','PASAPORTE','Transportista HTTP A','88888888','fix-tva@test.com',1),(23,'FIXTVB45BB48','PASAPORTE','Transportista HTTP B','87777777','fix-tvb@test.com',1),(24,'539925840','CEDULA_FISICA','Finca Prueba Territorio','88887777','territorio.prueba@example.test',1),(25,'101110222','CEDULA_FISICA','Prueba Telefono','88887777','p@example.test',1),(-502742,'TST25D4C28E9EC609D4','SIN_CATALOGO','','','directo@example.test',9),(-1394308,'TST25D4C28E9EC609D4','SIN_CATALOGO','','','directo@example.test',9),(-606941,'TSTAF3BA1B2147E95AD','SIN_CATALOGO','','','directo@example.test',9),(-1001164,'TSTAF3BA1B2147E95AD','SIN_CATALOGO','','','directo@example.test',9),(26,'CONCT1701BC3AC','PASAPORTE','Transportista Concurrencia 1','88888888','conc1@test.com',1),(27,'CONCT2E7ECA049','PASAPORTE','Transportista Concurrencia 2','87777777','conc2@test.com',1),(28,'FIXTVA935027','PASAPORTE','Transportista HTTP A','88888888','fix-tva@test.com',1),(29,'FIXTVB1ED96E','PASAPORTE','Transportista HTTP B','87777777','fix-tvb@test.com',1),(30,'CONCT1FEB3F735','PASAPORTE','Transportista Concurrencia 1','88888888','conc1@test.com',1),(31,'CONCT2E11D9FAE','PASAPORTE','Transportista Concurrencia 2','87777777','conc2@test.com',1),(-735078,'TSTFAD5FB2247728A57','SIN_CATALOGO','','','directo@example.test',9),(-1315365,'TSTFAD5FB2247728A57','SIN_CATALOGO','','','directo@example.test',9);
/*!40000 ALTER TABLE `tbpersona` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbproductor`
--

LOCK TABLES `tbproductor` WRITE;
/*!40000 ALTER TABLE `tbproductor` DISABLE KEYS */;
INSERT INTO `tbproductor` VALUES (1,1),(2,2),(3,3),(4,4),(5,7),(6,24);
/*!40000 ALTER TABLE `tbproductor` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbproductoractividad`
--

LOCK TABLES `tbproductoractividad` WRITE;
/*!40000 ALTER TABLE `tbproductoractividad` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbproductoractividad` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbproductordireccion`
--

LOCK TABLES `tbproductordireccion` WRITE;
/*!40000 ALTER TABLE `tbproductordireccion` DISABLE KEYS */;
INSERT INTO `tbproductordireccion` VALUES (1,1,1,NULL,NULL),(2,2,2,NULL,NULL),(3,5,3,NULL,NULL),(4,6,24,'2026-09-01 05:18:46',NULL);
/*!40000 ALTER TABLE `tbproductordireccion` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbproductorestadoperiodo`
--

LOCK TABLES `tbproductorestadoperiodo` WRITE;
/*!40000 ALTER TABLE `tbproductorestadoperiodo` DISABLE KEYS */;
INSERT INTO `tbproductorestadoperiodo` VALUES (1,1,1,'2026-08-25 08:32:07',NULL,'Reactivación'),(2,2,1,'2026-08-25 08:32:07',NULL,'Reactivación'),(3,3,1,'2026-08-25 08:53:21',NULL,'Migración 004: estado heredado'),(4,4,1,'2026-08-25 08:53:21',NULL,'Migración 004: estado heredado'),(5,5,1,'2026-08-25 08:53:21',NULL,'Migración 004: estado heredado'),(6,6,1,'2026-09-01 05:18:46',NULL,'Alta del productor');
/*!40000 ALTER TABLE `tbproductorestadoperiodo` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbproductorubicacion`
--

LOCK TABLES `tbproductorubicacion` WRITE;
/*!40000 ALTER TABLE `tbproductorubicacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbproductorubicacion` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbtransportista`
--

LOCK TABLES `tbtransportista` WRITE;
/*!40000 ALTER TABLE `tbtransportista` DISABLE KEYS */;
INSERT INTO `tbtransportista` VALUES (1,6,0);
/*!40000 ALTER TABLE `tbtransportista` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbtransportistavehiculo`
--

LOCK TABLES `tbtransportistavehiculo` WRITE;
/*!40000 ALTER TABLE `tbtransportistavehiculo` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbtransportistavehiculo` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tbvehiculo`
--

LOCK TABLES `tbvehiculo` WRITE;
/*!40000 ALTER TABLE `tbvehiculo` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbvehiculo` ENABLE KEYS */;
UNLOCK TABLES;

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
