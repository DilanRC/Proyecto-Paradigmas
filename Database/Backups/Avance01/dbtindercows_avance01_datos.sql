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
-- Dumping data for table `tbbitacora`
--

LOCK TABLES `tbbitacora` WRITE;
/*!40000 ALTER TABLE `tbbitacora` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbbitacora` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbfinca`
--

LOCK TABLES `tbfinca` WRITE;
/*!40000 ALTER TABLE `tbfinca` DISABLE KEYS */;
INSERT INTO `tbfinca` VALUES (1,'Finca El Roble',1),(2,'Finca Valle Verde',1);
/*!40000 ALTER TABLE `tbfinca` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbidentificaciontipo`
--

LOCK TABLES `tbidentificaciontipo` WRITE;
/*!40000 ALTER TABLE `tbidentificaciontipo` DISABLE KEYS */;
INSERT INTO `tbidentificaciontipo` VALUES (1,'CEDULA_FISICA','Cédula física',1),(2,'CEDULA_JURIDICA','Cédula jurídica',1),(3,'DIMEX','DIMEX',1),(4,'NITE','NITE',1),(5,'PASAPORTE','Pasaporte',1);
/*!40000 ALTER TABLE `tbidentificaciontipo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbparticipante`
--

LOCK TABLES `tbparticipante` WRITE;
/*!40000 ALTER TABLE `tbparticipante` DISABLE KEYS */;
INSERT INTO `tbparticipante` VALUES (1,'Maria Fernandez Solano','88881111','contacto.compartido@example.test',1),(2,'Ganaderia Valle Verde S.A.','+50622221111','contacto.compartido@example.test',1);
/*!40000 ALTER TABLE `tbparticipante` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbparticipantedireccion`
--

LOCK TABLES `tbparticipantedireccion` WRITE;
/*!40000 ALTER TABLE `tbparticipantedireccion` DISABLE KEYS */;
INSERT INTO `tbparticipantedireccion` (`tbparticipantedireccionId`, `tbparticipanteId`, `tbparticipantedireccionProvincia`, `tbparticipantedireccionCanton`, `tbparticipantedireccionDistrito`, `tbparticipantedireccionPueblo`, `tbparticipantedireccionSenas`, `tbparticipantedireccionEsPrincipal`, `tbparticipantedireccionEstado`) VALUES (1,1,'Alajuela','San Carlos','Quesada','Centro','Datos ficticios para demostracion.',1,1),(2,2,'Guanacaste','Tilaran','Tilaran',NULL,'Datos ficticios para demostracion.',1,1);
/*!40000 ALTER TABLE `tbparticipantedireccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbparticipanteidentificacion`
--

LOCK TABLES `tbparticipanteidentificacion` WRITE;
/*!40000 ALTER TABLE `tbparticipanteidentificacion` DISABLE KEYS */;
INSERT INTO `tbparticipanteidentificacion` (`tbparticipanteidentificacionId`, `tbparticipanteId`, `tbidentificaciontipoId`, `tbparticipanteidentificacionNumero`, `tbparticipanteidentificacionNumeroNormalizado`, `tbparticipanteidentificacionEsPrincipal`, `tbparticipanteidentificacionEstado`) VALUES (1,1,1,'1-0111-0111','101110111',1,1),(2,2,2,'3-101-111111','3101111111',1,1);
/*!40000 ALTER TABLE `tbparticipanteidentificacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbparticipanterol`
--

LOCK TABLES `tbparticipanterol` WRITE;
/*!40000 ALTER TABLE `tbparticipanterol` DISABLE KEYS */;
INSERT INTO `tbparticipanterol` VALUES (1,1,1),(2,1,1),(1,2,1);
/*!40000 ALTER TABLE `tbparticipanterol` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbproductorfinca`
--

LOCK TABLES `tbproductorfinca` WRITE;
/*!40000 ALTER TABLE `tbproductorfinca` DISABLE KEYS */;
INSERT INTO `tbproductorfinca` VALUES (1,1,1),(1,2,1),(2,2,1);
/*!40000 ALTER TABLE `tbproductorfinca` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbrol`
--

LOCK TABLES `tbrol` WRITE;
/*!40000 ALTER TABLE `tbrol` DISABLE KEYS */;
INSERT INTO `tbrol` VALUES (1,'PRODUCTOR','Productor',1),(2,'COMPRADOR','Comprador',1),(3,'ADMINISTRADOR','Administrador',1);
/*!40000 ALTER TABLE `tbrol` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-02  1:09:44
