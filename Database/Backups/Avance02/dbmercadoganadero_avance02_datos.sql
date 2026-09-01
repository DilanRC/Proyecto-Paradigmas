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
-- Dumping data for table `tbbitacora`
--

LOCK TABLES `tbbitacora` WRITE;
/*!40000 ALTER TABLE `tbbitacora` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbbitacora` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbcomprador`
--

LOCK TABLES `tbcomprador` WRITE;
/*!40000 ALTER TABLE `tbcomprador` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbcomprador` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbdireccion`
--

LOCK TABLES `tbdireccion` WRITE;
/*!40000 ALTER TABLE `tbdireccion` DISABLE KEYS */;
INSERT INTO `tbdireccion` VALUES (1,'Alajuela','San Carlos','Quesada','Centro','Datos ficticios para demostracion.'),(2,'Guanacaste','Tilaran','Tilaran',NULL,'Datos ficticios para demostracion.');
/*!40000 ALTER TABLE `tbdireccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbfinca`
--

LOCK TABLES `tbfinca` WRITE;
/*!40000 ALTER TABLE `tbfinca` DISABLE KEYS */;
INSERT INTO `tbfinca` VALUES (1,1,'Finca El Roble',1),(2,1,'Finca Valle Verde',1),(3,2,'Finca Valle Verde',1);
/*!40000 ALTER TABLE `tbfinca` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbfincadireccion`
--

LOCK TABLES `tbfincadireccion` WRITE;
/*!40000 ALTER TABLE `tbfincadireccion` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbfincadireccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbpagometodo`
--

LOCK TABLES `tbpagometodo` WRITE;
/*!40000 ALTER TABLE `tbpagometodo` DISABLE KEYS */;
INSERT INTO `tbpagometodo` VALUES (1,'Efectivo','Pago realizado en efectivo',1);
/*!40000 ALTER TABLE `tbpagometodo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbpersona`
--

LOCK TABLES `tbpersona` WRITE;
/*!40000 ALTER TABLE `tbpersona` DISABLE KEYS */;
INSERT INTO `tbpersona` VALUES (1,'101110111','CEDULA_FISICA','Maria Fernandez Solano','88881111','contacto.compartido@example.test',1),(2,'3101111111','CEDULA_JURIDICA','Ganaderia Valle Verde S.A.','+50622221111','contacto.compartido@example.test',1);
/*!40000 ALTER TABLE `tbpersona` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbproductor`
--

LOCK TABLES `tbproductor` WRITE;
/*!40000 ALTER TABLE `tbproductor` DISABLE KEYS */;
INSERT INTO `tbproductor` VALUES (1,1),(2,2);
/*!40000 ALTER TABLE `tbproductor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbproductoractividad`
--

LOCK TABLES `tbproductoractividad` WRITE;
/*!40000 ALTER TABLE `tbproductoractividad` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbproductoractividad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbproductordireccion`
--

LOCK TABLES `tbproductordireccion` WRITE;
/*!40000 ALTER TABLE `tbproductordireccion` DISABLE KEYS */;
INSERT INTO `tbproductordireccion` VALUES (1,1,1,NULL,NULL),(2,2,2,NULL,NULL);
/*!40000 ALTER TABLE `tbproductordireccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbproductorestadoperiodo`
--

LOCK TABLES `tbproductorestadoperiodo` WRITE;
/*!40000 ALTER TABLE `tbproductorestadoperiodo` DISABLE KEYS */;
INSERT INTO `tbproductorestadoperiodo` VALUES (1,1,1,'2026-08-30 05:53:35',NULL,'Alta del productor'),(2,2,1,'2026-08-30 05:53:35',NULL,'Alta del productor');
/*!40000 ALTER TABLE `tbproductorestadoperiodo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbproductorubicacion`
--

LOCK TABLES `tbproductorubicacion` WRITE;
/*!40000 ALTER TABLE `tbproductorubicacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbproductorubicacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbtransportista`
--

LOCK TABLES `tbtransportista` WRITE;
/*!40000 ALTER TABLE `tbtransportista` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbtransportista` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbtransportistavehiculo`
--

LOCK TABLES `tbtransportistavehiculo` WRITE;
/*!40000 ALTER TABLE `tbtransportistavehiculo` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbtransportistavehiculo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `tbvehiculo`
--

LOCK TABLES `tbvehiculo` WRITE;
/*!40000 ALTER TABLE `tbvehiculo` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbvehiculo` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-30  5:54:49
