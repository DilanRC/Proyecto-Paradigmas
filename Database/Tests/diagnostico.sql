USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Consultas de diagnóstico del modelo sin restricciones.
--
-- IMPORTANTE: estas consultas DETECTAN inconsistencias. No las IMPIDEN. El
-- motor acepta duplicados, huérfanos y cardinalidades inválidas porque el
-- esquema no declara llaves ni restricciones. Impedirlas corresponde a la capa
-- de aplicación y queda fuera del alcance de este avance.
--
-- Resultado esperado sobre datos válidos: 0 filas en todas las consultas.
-- Ejecutar:
--   docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" \
--     < Database/Tests/diagnostico.sql

-- D-01: productores con más de una dirección. Política: una residencia principal.
SELECT 'D-01 productores con mas de una direccion' AS diagnostico;
SELECT tbproductorid, COUNT(*) AS direcciones
FROM tbproductordireccion
GROUP BY tbproductorid
HAVING COUNT(*) > 1;

-- D-02: fincas con más de una dirección. Política: una dirección por finca.
SELECT 'D-02 fincas con mas de una direccion' AS diagnostico;
SELECT tbfincaid, COUNT(*) AS direcciones
FROM tbfincadireccion
GROUP BY tbfincaid
HAVING COUNT(*) > 1;

-- D-03: placas repetidas.
SELECT 'D-03 placas repetidas' AS diagnostico;
SELECT tbvehiculoplaca, COUNT(*) AS repeticiones
FROM tbvehiculo
GROUP BY tbvehiculoplaca
HAVING COUNT(*) > 1;

-- D-04: vin repetidos.
SELECT 'D-04 vin repetidos' AS diagnostico;
SELECT tbvehiculovin, COUNT(*) AS repeticiones
FROM tbvehiculo
GROUP BY tbvehiculovin
HAVING COUNT(*) > 1;

-- D-05: vehículos relacionados con más de un transportista.
-- Política: un vehículo corresponde a un solo transportista.
SELECT 'D-05 vehiculos con mas de un transportista' AS diagnostico;
SELECT tbvehiculoid, COUNT(DISTINCT tbtransportistaid) AS transportistas
FROM tbtransportistavehiculo
GROUP BY tbvehiculoid
HAVING COUNT(DISTINCT tbtransportistaid) > 1;

-- D-06: identificadores repetidos dentro de cada tabla del avance.
SELECT 'D-06 identificadores repetidos' AS diagnostico;
SELECT 'tbdireccion' AS tabla, tbdireccionid AS identificador, COUNT(*) AS repeticiones
FROM tbdireccion GROUP BY tbdireccionid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbfincadireccion', tbfincadireccionid, COUNT(*)
FROM tbfincadireccion GROUP BY tbfincadireccionid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbpagometodo', tbpagometodoid, COUNT(*)
FROM tbpagometodo GROUP BY tbpagometodoid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbtransportista', tbtransportistaid, COUNT(*)
FROM tbtransportista GROUP BY tbtransportistaid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbvehiculo', tbvehiculoid, COUNT(*)
FROM tbvehiculo GROUP BY tbvehiculoid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbtransportistavehiculo', tbtransportistavehiculoid, COUNT(*)
FROM tbtransportistavehiculo GROUP BY tbtransportistavehiculoid HAVING COUNT(*) > 1;

-- D-07: asociaciones cuyo identificador lógico no corresponde con un registro
-- existente. Sin llaves foráneas el motor acepta estas filas huérfanas.
SELECT 'D-07 asociaciones huerfanas' AS diagnostico;
SELECT 'tbproductordireccion.tbproductorid' AS enlace, pd.tbproductordireccionid AS asociacion,
       pd.tbproductorid AS valor_sin_destino
FROM tbproductordireccion pd
LEFT JOIN tbproductor p ON p.tbproductorid = pd.tbproductorid
WHERE p.tbproductorid IS NULL
UNION ALL
SELECT 'tbproductordireccion.tbdireccionid', pd.tbproductordireccionid, pd.tbdireccionid
FROM tbproductordireccion pd
LEFT JOIN tbdireccion d ON d.tbdireccionid = pd.tbdireccionid
WHERE d.tbdireccionid IS NULL
UNION ALL
SELECT 'tbfincadireccion.tbfincaid', fd.tbfincadireccionid, fd.tbfincaid
FROM tbfincadireccion fd
LEFT JOIN tbfinca f ON f.tbfincaid = fd.tbfincaid
WHERE f.tbfincaid IS NULL
UNION ALL
SELECT 'tbfincadireccion.tbdireccionid', fd.tbfincadireccionid, fd.tbdireccionid
FROM tbfincadireccion fd
LEFT JOIN tbdireccion d ON d.tbdireccionid = fd.tbdireccionid
WHERE d.tbdireccionid IS NULL
UNION ALL
SELECT 'tbfinca.tbproductorid', f.tbfincaid, f.tbproductorid
FROM tbfinca f
LEFT JOIN tbproductor p ON p.tbproductorid = f.tbproductorid
WHERE p.tbproductorid IS NULL
UNION ALL
SELECT 'tbtransportistavehiculo.tbtransportistaid', tv.tbtransportistavehiculoid, tv.tbtransportistaid
FROM tbtransportistavehiculo tv
LEFT JOIN tbtransportista t ON t.tbtransportistaid = tv.tbtransportistaid
WHERE t.tbtransportistaid IS NULL
UNION ALL
SELECT 'tbtransportistavehiculo.tbvehiculoid', tv.tbtransportistavehiculoid, tv.tbvehiculoid
FROM tbtransportistavehiculo tv
LEFT JOIN tbvehiculo v ON v.tbvehiculoid = tv.tbvehiculoid
WHERE v.tbvehiculoid IS NULL;

-- D-08: direcciones sin uso. No es un error: una ubicación puede quedar
-- disponible mientras la aplicación no la asocie.
SELECT 'D-08 direcciones sin uso' AS diagnostico;
SELECT d.tbdireccionid
FROM tbdireccion d
LEFT JOIN tbproductordireccion pd ON pd.tbdireccionid = d.tbdireccionid
LEFT JOIN tbfincadireccion fd ON fd.tbdireccionid = d.tbdireccionid
WHERE pd.tbdireccionid IS NULL AND fd.tbdireccionid IS NULL;

-- D-09: ubicaciones repetidas fila por fila. No es un error: dos lugares
-- distintos pueden compartir provincia, cantón y distrito. Sirve para revisar si
-- la aplicación está creando una ubicación nueva en lugar de reutilizar la
-- existente cuando productor y finca están en el mismo sitio.
SELECT 'D-09 ubicaciones con los mismos datos' AS diagnostico;
SELECT tbdireccionprovincia, tbdireccioncanton, tbdirecciondistrito,
       COALESCE(tbdireccionpueblo, ''), COALESCE(tbdireccionsenas, ''), COUNT(*) AS repeticiones
FROM tbdireccion
GROUP BY tbdireccionprovincia, tbdireccioncanton, tbdirecciondistrito,
         COALESCE(tbdireccionpueblo, ''), COALESCE(tbdireccionsenas, '')
HAVING COUNT(*) > 1;
