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
-- Dumping data for table `tbproductores`
--

LOCK TABLES `tbproductores` WRITE;
/*!40000 ALTER TABLE `tbproductores` DISABLE KEYS */;
INSERT INTO `tbproductores` VALUES ('101110111','CEDULA_FISICA','Maria Fernandez Solano','88881111','contacto.compartido@example.test',1),('3101111111','CEDULA_JURIDICA','Ganaderia Valle Verde S.A.','+50622221111','contacto.compartido@example.test',1);
/*!40000 ALTER TABLE `tbproductores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbproductoresdireccion`
--

LOCK TABLES `tbproductoresdireccion` WRITE;
/*!40000 ALTER TABLE `tbproductoresdireccion` DISABLE KEYS */;
INSERT INTO `tbproductoresdireccion` VALUES ('101110111','Alajuela','San Carlos','Quesada','Centro','Datos ficticios para demostracion.'),('3101111111','Guanacaste','Tilaran','Tilaran',NULL,'Datos ficticios para demostracion.');
/*!40000 ALTER TABLE `tbproductoresdireccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbproductoresfinca`
--

LOCK TABLES `tbproductoresfinca` WRITE;
/*!40000 ALTER TABLE `tbproductoresfinca` DISABLE KEYS */;
INSERT INTO `tbproductoresfinca` VALUES ('101110111','Finca El Roble',1),('101110111','Finca Valle Verde',1),('3101111111','Finca Valle Verde',1);
/*!40000 ALTER TABLE `tbproductoresfinca` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-02 18:34:00
