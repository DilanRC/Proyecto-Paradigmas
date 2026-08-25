USE dbmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Comprobación de relaciones con datos de prueba desechables.
-- Los identificadores negativos no compiten con los consecutivos que calcula la
-- aplicación. El script inserta, comprueba y borra sus propias filas.
-- Ejecutar:
--   docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" \
--     < Database/Tests/comprobacionrelaciones.sql

START TRANSACTION;

-- Ubicaciones físicas.
INSERT INTO tbdireccion (tbdireccionid, tbdireccionprovincia, tbdireccioncanton,
    tbdirecciondistrito, tbdireccionpueblo, tbdireccionsenas) VALUES
    (-9001, 'Alajuela', 'San Carlos', 'Quesada', 'Centro', 'Prueba: lugar compartido.'),
    (-9002, 'Guanacaste', 'Tilaran', 'Tilaran', NULL, 'Prueba: residencia del productor.'),
    (-9003, 'Guanacaste', 'Canas', 'Canas', NULL, 'Prueba: finca en otro lugar.');

-- Caso B: el productor -901 vive en la misma ubicación que su finca -801.
-- Caso A: el productor -902 vive en -9002 y su finca -802 está en -9003.
INSERT INTO tbproductordireccion (tbproductordireccionid, tbproductorid, tbdireccionid) VALUES
    (-901, -901, -9001),
    (-902, -902, -9002);

-- El productor -901 posee dos fincas: la relación 1 a varias vive en tbfinca.
INSERT INTO tbfinca (tbfincaid, tbproductorid, tbfincanombre, tbfincaestado) VALUES
    (-801, -901, 'Finca prueba compartida', 1),
    (-803, -901, 'Finca prueba segunda', 1),
    (-802, -902, 'Finca prueba separada', 1);

INSERT INTO tbfincadireccion (tbfincadireccionid, tbfincaid, tbdireccionid) VALUES
    (-801, -801, -9001),
    (-803, -803, -9003),
    (-802, -802, -9003);

INSERT INTO tbtransportista (tbtransportistaid, tbtransportistaidentificacionnumero,
    tbtransportistaidentificaciontipo, tbtransportistanombre, tbtransportistatelefono,
    tbtransportistacorreoelectronico, tbtransportistaestado) VALUES
    (-701, 'PRUEBA-701', 'CEDULA_FISICA', 'Transportista de prueba', '88880000',
     'prueba.transportista@example.test', 1);

INSERT INTO tbvehiculo (tbvehiculoid, tbvehiculoplaca, tbvehiculovin, tbvehiculomodelo,
    tbvehiculoestado) VALUES
    (-601, 'PRB-601', 'VINPRUEBA0000000601', 'Cabezal de prueba', 1),
    (-602, 'PRB-602', 'VINPRUEBA0000000602', 'Furgon de prueba', 1);

-- Un transportista con varios vehículos.
INSERT INTO tbtransportistavehiculo (tbtransportistavehiculoid, tbtransportistaid, tbvehiculoid) VALUES
    (-501, -701, -601),
    (-502, -701, -602);

-- Comprobación 1: productor y finca comparten la misma ubicación (-9001).
-- Esperado: una fila con direccion_compartida = -9001.
SELECT pd.tbproductorid, fd.tbfincaid, pd.tbdireccionid AS direccion_compartida
FROM tbproductordireccion pd
INNER JOIN tbfincadireccion fd ON fd.tbdireccionid = pd.tbdireccionid
WHERE pd.tbproductorid = -901 AND fd.tbfincaid = -801;

-- Comprobación 2: productor y finca en ubicaciones distintas.
-- Esperado: una fila con -9002 y -9003.
SELECT pd.tbproductorid, pd.tbdireccionid AS direccion_productor,
       fd.tbfincaid, fd.tbdireccionid AS direccion_finca
FROM tbproductordireccion pd
INNER JOIN tbfinca f ON f.tbproductorid = pd.tbproductorid
INNER JOIN tbfincadireccion fd ON fd.tbfincaid = f.tbfincaid
WHERE pd.tbproductorid = -902;

-- Comprobación 3: un productor con varias fincas. Esperado: fincas = 2.
SELECT tbproductorid, COUNT(*) AS fincas
FROM tbfinca
WHERE tbproductorid = -901
GROUP BY tbproductorid;

-- Comprobación 4: un transportista con varios vehículos. Esperado: vehiculos = 2.
SELECT tbtransportistaid, COUNT(*) AS vehiculos
FROM tbtransportistavehiculo
WHERE tbtransportistaid = -701
GROUP BY tbtransportistaid;

-- Comprobación 5: datos completos de la ubicación compartida.
-- Esperado: una fila de Alajuela / San Carlos / Quesada.
SELECT d.*
FROM tbdireccion d
WHERE d.tbdireccionid = -9001;

-- Limpieza: el script no deja rastro.
DELETE FROM tbtransportistavehiculo WHERE tbtransportistavehiculoid IN (-501, -502);
DELETE FROM tbvehiculo WHERE tbvehiculoid IN (-601, -602);
DELETE FROM tbtransportista WHERE tbtransportistaid = -701;
DELETE FROM tbfincadireccion WHERE tbfincadireccionid IN (-801, -802, -803);
DELETE FROM tbfinca WHERE tbfincaid IN (-801, -802, -803);
DELETE FROM tbproductordireccion WHERE tbproductordireccionid IN (-901, -902);
DELETE FROM tbdireccion WHERE tbdireccionid IN (-9001, -9002, -9003);

COMMIT;

-- Esperado tras la limpieza: 0 en las cuatro columnas.
SELECT
    (SELECT COUNT(*) FROM tbdireccion WHERE tbdireccionid < 0) AS direcciones_prueba,
    (SELECT COUNT(*) FROM tbfincadireccion WHERE tbfincadireccionid < 0) AS enlaces_finca_prueba,
    (SELECT COUNT(*) FROM tbtransportista WHERE tbtransportistaid < 0) AS transportistas_prueba,
    (SELECT COUNT(*) FROM tbvehiculo WHERE tbvehiculoid < 0) AS vehiculos_prueba;
