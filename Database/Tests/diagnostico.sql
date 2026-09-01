USE bdmercadoganadero;
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

-- D-00: perfiles sin identidad y capacidades repetidas para una persona.
SELECT 'D-00 perfiles de persona invalidos' AS diagnostico;
SELECT 'tbproductor persona inexistente' AS problema, p.tbproductorid AS registro
FROM tbproductor p LEFT JOIN tbpersona x ON x.tbpersonaid = p.tbpersonaid
WHERE x.tbpersonaid IS NULL
UNION ALL
SELECT 'tbcomprador persona inexistente', c.tbcompradorid
FROM tbcomprador c LEFT JOIN tbpersona x ON x.tbpersonaid = c.tbpersonaid
WHERE x.tbpersonaid IS NULL
UNION ALL
SELECT 'tbtransportista persona inexistente', t.tbtransportistaid
FROM tbtransportista t LEFT JOIN tbpersona x ON x.tbpersonaid = t.tbpersonaid
WHERE x.tbpersonaid IS NULL;

SELECT 'D-00 capacidades duplicadas' AS diagnostico;
SELECT 'tbproductor' AS perfil, tbpersonaid, COUNT(*) AS repeticiones
FROM tbproductor GROUP BY tbpersonaid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbcomprador', tbpersonaid, COUNT(*) FROM tbcomprador GROUP BY tbpersonaid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbtransportista', tbpersonaid, COUNT(*) FROM tbtransportista
GROUP BY tbpersonaid HAVING COUNT(*) > 1;

-- D-01: dirección del productor como histórico (DEC-18). Tener varias
-- direcciones cerradas es normal; lo que rompe integridad es un productor
-- con más de un periodo ABIERTO a la vez, o sin ninguno.
SELECT 'D-01 productores con mas de un periodo de direccion abierto' AS diagnostico;
SELECT tbproductorid, COUNT(*) AS periodos_abiertos
FROM tbproductordireccion
WHERE tbproductordireccionfechafin IS NULL
GROUP BY tbproductorid
HAVING COUNT(*) > 1;

SELECT 'D-01b productores sin periodo de direccion abierto' AS diagnostico;
SELECT p.tbproductorid
FROM tbproductor p
LEFT JOIN tbproductordireccion pd
    ON pd.tbproductorid = p.tbproductorid AND pd.tbproductordireccionfechafin IS NULL
WHERE pd.tbproductordireccionid IS NULL;

SELECT 'D-01c periodos de direccion abiertos sin fecha de inicio' AS diagnostico;
SELECT tbproductordireccionid, tbproductorid
FROM tbproductordireccion
WHERE tbproductordireccionfechafin IS NULL
  AND tbproductordireccionfechainicio IS NULL;

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
SELECT 'tbproductoractividad.tbproductorid', a.tbproductoractividadid, a.tbproductorid
FROM tbproductoractividad a
LEFT JOIN tbproductor p ON p.tbproductorid = a.tbproductorid
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

-- D-10: estado del productor como histórico (DEC-18/DEC-19). Un productor no
-- puede tener dos periodos de estado abiertos a la vez.
SELECT 'D-10 productores con mas de un periodo de estado abierto' AS diagnostico;
SELECT tbproductorid, COUNT(*) AS periodos_abiertos
FROM tbproductorestadoperiodo
WHERE tbproductorestadoperiodofechafin IS NULL
GROUP BY tbproductorid
HAVING COUNT(*) > 1;

-- D-11: productor sin ningún periodo de estado abierto. Solo puede ocurrir
-- con datos heredados sin migrar (Migrations/004eliminaestadoproductor.sql
-- ya hace ese backfill); DEC-19 lo trata como INACTIVO por defecto.
SELECT 'D-11 productores sin periodo de estado abierto' AS diagnostico;
SELECT p.tbproductorid
FROM tbproductor p
LEFT JOIN tbproductorestadoperiodo ep
    ON ep.tbproductorid = p.tbproductorid AND ep.tbproductorestadoperiodofechafin IS NULL
WHERE ep.tbproductorestadoperiodoid IS NULL;

-- D-12: catálogo cerrado de actividad (Tramo 12, matriz punto 3; Decisiones.md
-- decisión #2). Sin CHECK por regla del profesor: esta consulta detecta, no
-- impide, un tipo_evento fuera de {login, actualizacion_ubicacion,
-- actualizacion_perfil, registro_actividad_productiva, contacto_comprador}
-- que PHP debió rechazar antes de insertar (Tramo 15).
SELECT 'D-12 actividad fuera del catalogo cerrado' AS diagnostico;
SELECT tbproductoractividadid, tbproductorid, tbproductoractividadtipo
FROM tbproductoractividad
WHERE tbproductoractividadtipo NOT IN (
    'login', 'actualizacion_ubicacion', 'actualizacion_perfil',
    'registro_actividad_productiva', 'contacto_comprador'
);

-- D-13: estado de comprador fuera de su dominio lógico {0,1}. Comprador no
-- tiene tabla de periodos propia (matriz Tramo 12 punto 4: alta/baja lógica
-- en tbcompradorestado queda fuera del alcance profundo de esta fase); esta
-- consulta es el único resguardo de coherencia disponible sin CHECK.
SELECT 'D-13 tbcompradorestado fuera de dominio' AS diagnostico;
SELECT tbcompradorid, tbcompradorestado
FROM tbcomprador
WHERE tbcompradorestado NOT IN (0, 1);

-- D-14: identificadores repetidos en estructuras comerciales e históricas.
SELECT 'D-14 identificadores comerciales repetidos' AS diagnostico;
SELECT 'tbproductorclasificacionperiodo' AS tabla, tbproductorclasificacionperiodoid AS identificador, COUNT(*) AS repeticiones
FROM tbproductorclasificacionperiodo GROUP BY tbproductorclasificacionperiodoid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbanimal', tbanimalid, COUNT(*) FROM tbanimal GROUP BY tbanimalid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbanimalproduccionsalud', tbanimalproduccionsaludid, COUNT(*) FROM tbanimalproduccionsalud GROUP BY tbanimalproduccionsaludid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbanimalpublicacion', tbanimalpublicacionid, COUNT(*) FROM tbanimalpublicacion GROUP BY tbanimalpublicacionid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbcompra', tbcompraid, COUNT(*) FROM tbcompra GROUP BY tbcompraid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbventa', tbventaid, COUNT(*) FROM tbventa GROUP BY tbventaid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbanimalinteraccion', tbanimalinteraccionid, COUNT(*) FROM tbanimalinteraccion GROUP BY tbanimalinteraccionid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbcarrito', tbcarritoid, COUNT(*) FROM tbcarrito GROUP BY tbcarritoid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbcarritoanimal', tbcarritoanimalid, COUNT(*) FROM tbcarritoanimal GROUP BY tbcarritoanimalid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbtransportistaestadoperiodo', tbtransportistaestadoperiodoid, COUNT(*) FROM tbtransportistaestadoperiodo GROUP BY tbtransportistaestadoperiodoid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbtransportistaflete', tbtransportistafleteid, COUNT(*) FROM tbtransportistaflete GROUP BY tbtransportistafleteid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbtransportistaresena', tbtransportistaresenaid, COUNT(*) FROM tbtransportistaresena GROUP BY tbtransportistaresenaid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbanimalpublicacionestadoperiodo', tbanimalpublicacionestadoperiodoid, COUNT(*) FROM tbanimalpublicacionestadoperiodo GROUP BY tbanimalpublicacionestadoperiodoid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbcarritoestadoperiodo', tbcarritoestadoperiodoid, COUNT(*) FROM tbcarritoestadoperiodo GROUP BY tbcarritoestadoperiodoid HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbtransportistahorario', tbtransportistahorarioid, COUNT(*) FROM tbtransportistahorario GROUP BY tbtransportistahorarioid HAVING COUNT(*) > 1;

-- D-15: enlaces lógicos huérfanos de la capa comercial. Sin FK, el motor los
-- acepta; esta consulta los deja visibles para Backend.
SELECT 'D-15 enlaces comerciales huerfanos' AS diagnostico;
SELECT 'tbproductorclasificacionperiodo.tbproductorid' AS enlace, tbproductorclasificacionperiodoid AS asociacion, cp.tbproductorid AS valor_sin_destino
FROM tbproductorclasificacionperiodo cp LEFT JOIN tbproductor p ON p.tbproductorid = cp.tbproductorid
WHERE p.tbproductorid IS NULL
UNION ALL
SELECT 'tbanimalproduccionsalud.tbanimalid', o.tbanimalproduccionsaludid, o.tbanimalid
FROM tbanimalproduccionsalud o LEFT JOIN tbanimal a ON a.tbanimalid = o.tbanimalid
WHERE a.tbanimalid IS NULL
UNION ALL
SELECT 'tbanimalpublicacion.tbanimalid', ap.tbanimalpublicacionid, ap.tbanimalid
FROM tbanimalpublicacion ap LEFT JOIN tbanimal a ON a.tbanimalid = ap.tbanimalid
WHERE a.tbanimalid IS NULL
UNION ALL
SELECT 'tbanimalpublicacion.tbproductorvendedorid', ap.tbanimalpublicacionid, ap.tbproductorvendedorid
FROM tbanimalpublicacion ap LEFT JOIN tbproductor p ON p.tbproductorid = ap.tbproductorvendedorid
WHERE p.tbproductorid IS NULL
UNION ALL
SELECT 'tbanimalpublicacion.tbfincaid', ap.tbanimalpublicacionid, ap.tbfincaid
FROM tbanimalpublicacion ap LEFT JOIN tbfinca f ON f.tbfincaid = ap.tbfincaid
WHERE f.tbfincaid IS NULL
UNION ALL
SELECT 'tbcompra.tbanimalid', c.tbcompraid, c.tbanimalid
FROM tbcompra c LEFT JOIN tbanimal a ON a.tbanimalid = c.tbanimalid
WHERE a.tbanimalid IS NULL
UNION ALL
SELECT 'tbcompra.tbproductorcompradorid', c.tbcompraid, c.tbproductorcompradorid
FROM tbcompra c LEFT JOIN tbproductor p ON p.tbproductorid = c.tbproductorcompradorid
WHERE p.tbproductorid IS NULL
UNION ALL
SELECT 'tbcompra.tbfincaorigenid', c.tbcompraid, c.tbfincaorigenid
FROM tbcompra c LEFT JOIN tbfinca f ON f.tbfincaid = c.tbfincaorigenid
WHERE c.tbfincaorigenid IS NOT NULL AND f.tbfincaid IS NULL
UNION ALL
SELECT 'tbcompra.tbpagometodoid', c.tbcompraid, c.tbpagometodoid
FROM tbcompra c LEFT JOIN tbpagometodo p ON p.tbpagometodoid = c.tbpagometodoid
WHERE p.tbpagometodoid IS NULL
UNION ALL
SELECT 'tbventa.tbanimalid', v.tbventaid, v.tbanimalid
FROM tbventa v LEFT JOIN tbanimal a ON a.tbanimalid = v.tbanimalid
WHERE a.tbanimalid IS NULL
UNION ALL
SELECT 'tbventa.tbproductorvendedorid', v.tbventaid, v.tbproductorvendedorid
FROM tbventa v LEFT JOIN tbproductor p ON p.tbproductorid = v.tbproductorvendedorid
WHERE p.tbproductorid IS NULL
UNION ALL
SELECT 'tbventa.tbproductorcompradorid', v.tbventaid, v.tbproductorcompradorid
FROM tbventa v LEFT JOIN tbproductor p ON p.tbproductorid = v.tbproductorcompradorid
WHERE p.tbproductorid IS NULL
UNION ALL
SELECT 'tbventa.tbfincaid', v.tbventaid, v.tbfincaid
FROM tbventa v LEFT JOIN tbfinca f ON f.tbfincaid = v.tbfincaid
WHERE v.tbfincaid IS NOT NULL AND f.tbfincaid IS NULL
UNION ALL
SELECT 'tbventa.tbcompraid', v.tbventaid, v.tbcompraid
FROM tbventa v LEFT JOIN tbcompra c ON c.tbcompraid = v.tbcompraid
WHERE v.tbcompraid IS NOT NULL AND c.tbcompraid IS NULL
UNION ALL
SELECT 'tbventa.tbpagometodoid', v.tbventaid, v.tbpagometodoid
FROM tbventa v LEFT JOIN tbpagometodo p ON p.tbpagometodoid = v.tbpagometodoid
WHERE p.tbpagometodoid IS NULL
UNION ALL
SELECT 'tbventa.tbventadireccionid', v.tbventaid, v.tbventadireccionid
FROM tbventa v LEFT JOIN tbdireccion d ON d.tbdireccionid = v.tbventadireccionid
WHERE v.tbventadireccionid IS NOT NULL AND d.tbdireccionid IS NULL
UNION ALL
SELECT 'tbanimalpublicacionestadoperiodo.tbanimalpublicacionid', ep.tbanimalpublicacionestadoperiodoid, ep.tbanimalpublicacionid
FROM tbanimalpublicacionestadoperiodo ep LEFT JOIN tbanimalpublicacion ap ON ap.tbanimalpublicacionid = ep.tbanimalpublicacionid
WHERE ap.tbanimalpublicacionid IS NULL;

SELECT 'D-16 enlaces de funnel y transporte huerfanos' AS diagnostico;
SELECT 'tbanimalinteraccion.tbproductorid' AS enlace, i.tbanimalinteraccionid AS asociacion, i.tbproductorid AS valor_sin_destino
FROM tbanimalinteraccion i LEFT JOIN tbproductor p ON p.tbproductorid = i.tbproductorid
WHERE p.tbproductorid IS NULL
UNION ALL
SELECT 'tbanimalinteraccion.tbanimalid', i.tbanimalinteraccionid, i.tbanimalid
FROM tbanimalinteraccion i LEFT JOIN tbanimal a ON a.tbanimalid = i.tbanimalid
WHERE a.tbanimalid IS NULL
UNION ALL
SELECT 'tbcarrito.tbproductorid', c.tbcarritoid, c.tbproductorid
FROM tbcarrito c LEFT JOIN tbproductor p ON p.tbproductorid = c.tbproductorid
WHERE p.tbproductorid IS NULL
UNION ALL
SELECT 'tbcarritoanimal.tbcarritoid', ca.tbcarritoanimalid, ca.tbcarritoid
FROM tbcarritoanimal ca LEFT JOIN tbcarrito c ON c.tbcarritoid = ca.tbcarritoid
WHERE c.tbcarritoid IS NULL
UNION ALL
SELECT 'tbcarritoanimal.tbanimalid', ca.tbcarritoanimalid, ca.tbanimalid
FROM tbcarritoanimal ca LEFT JOIN tbanimal a ON a.tbanimalid = ca.tbanimalid
WHERE a.tbanimalid IS NULL
UNION ALL
SELECT 'tbtransportistaestadoperiodo.tbtransportistaid', ep.tbtransportistaestadoperiodoid, ep.tbtransportistaid
FROM tbtransportistaestadoperiodo ep LEFT JOIN tbtransportista t ON t.tbtransportistaid = ep.tbtransportistaid
WHERE t.tbtransportistaid IS NULL
UNION ALL
SELECT 'tbtransportistaflete.tbtransportistaid', f.tbtransportistafleteid, f.tbtransportistaid
FROM tbtransportistaflete f LEFT JOIN tbtransportista t ON t.tbtransportistaid = f.tbtransportistaid
WHERE t.tbtransportistaid IS NULL
UNION ALL
SELECT 'tbtransportistaflete.tbproductororigenid', f.tbtransportistafleteid, f.tbproductororigenid
FROM tbtransportistaflete f LEFT JOIN tbproductor p ON p.tbproductorid = f.tbproductororigenid
WHERE f.tbproductororigenid IS NOT NULL AND p.tbproductorid IS NULL
UNION ALL
SELECT 'tbtransportistaflete.tbfincaorigenid', f.tbtransportistafleteid, f.tbfincaorigenid
FROM tbtransportistaflete f LEFT JOIN tbfinca x ON x.tbfincaid = f.tbfincaorigenid
WHERE f.tbfincaorigenid IS NOT NULL AND x.tbfincaid IS NULL
UNION ALL
SELECT 'tbtransportistaflete.tbdireccionorigenid', f.tbtransportistafleteid, f.tbdireccionorigenid
FROM tbtransportistaflete f LEFT JOIN tbdireccion d ON d.tbdireccionid = f.tbdireccionorigenid
WHERE f.tbdireccionorigenid IS NOT NULL AND d.tbdireccionid IS NULL
UNION ALL
SELECT 'tbtransportistaflete.tbdirecciondestinoid', f.tbtransportistafleteid, f.tbdirecciondestinoid
FROM tbtransportistaflete f LEFT JOIN tbdireccion d ON d.tbdireccionid = f.tbdirecciondestinoid
WHERE f.tbdirecciondestinoid IS NOT NULL AND d.tbdireccionid IS NULL
UNION ALL
SELECT 'tbtransportistaflete.tbpagometodoid', f.tbtransportistafleteid, f.tbpagometodoid
FROM tbtransportistaflete f LEFT JOIN tbpagometodo p ON p.tbpagometodoid = f.tbpagometodoid
WHERE p.tbpagometodoid IS NULL
UNION ALL
SELECT 'tbtransportistaresena.tbtransportistaid', r.tbtransportistaresenaid, r.tbtransportistaid
FROM tbtransportistaresena r LEFT JOIN tbtransportista t ON t.tbtransportistaid = r.tbtransportistaid
WHERE t.tbtransportistaid IS NULL
UNION ALL
SELECT 'tbtransportistaresena.tbpersonaid', r.tbtransportistaresenaid, r.tbpersonaid
FROM tbtransportistaresena r LEFT JOIN tbpersona p ON p.tbpersonaid = r.tbpersonaid
WHERE p.tbpersonaid IS NULL
UNION ALL
SELECT 'tbtransportistaresena.tbtransportistafleteid', r.tbtransportistaresenaid, r.tbtransportistafleteid
FROM tbtransportistaresena r LEFT JOIN tbtransportistaflete f ON f.tbtransportistafleteid = r.tbtransportistafleteid
WHERE r.tbtransportistafleteid IS NOT NULL AND f.tbtransportistafleteid IS NULL
UNION ALL
SELECT 'tbtransportistaflete.tbvehiculoid', f.tbtransportistafleteid, f.tbvehiculoid
FROM tbtransportistaflete f LEFT JOIN tbvehiculo v ON v.tbvehiculoid = f.tbvehiculoid
WHERE f.tbvehiculoid IS NOT NULL AND v.tbvehiculoid IS NULL
UNION ALL
SELECT 'tbtransportistahorario.tbtransportistaid', h.tbtransportistahorarioid, h.tbtransportistaid
FROM tbtransportistahorario h LEFT JOIN tbtransportista t ON t.tbtransportistaid = h.tbtransportistaid
WHERE t.tbtransportistaid IS NULL
UNION ALL
SELECT 'tbcarritoestadoperiodo.tbcarritoid', cep.tbcarritoestadoperiodoid, cep.tbcarritoid
FROM tbcarritoestadoperiodo cep LEFT JOIN tbcarrito c ON c.tbcarritoid = cep.tbcarritoid
WHERE c.tbcarritoid IS NULL;

-- D-17: dominios lógicos confirmados pero validados por PHP, no por CHECK.
SELECT 'D-17 dominios comerciales fuera de rango' AS diagnostico;
SELECT 'tbproductorclasificacionperiodo.tipo' AS campo, tbproductorclasificacionperiodoid AS registro, tbproductorclasificacionperiodotipo AS valor
FROM tbproductorclasificacionperiodo
WHERE tbproductorclasificacionperiodotipo NOT IN ('COMPRADOR', 'VENDEDOR')
UNION ALL
SELECT 'tbanimalinteraccion.tipo', tbanimalinteraccionid, tbanimalinteracciontipo
FROM tbanimalinteraccion
WHERE tbanimalinteracciontipo NOT IN ('ME_GUSTA', 'SEGUIR', 'CARRITO', 'COMPRA')
UNION ALL
SELECT 'tbanimalinteraccion.accion', tbanimalinteraccionid, tbanimalinteraccionaccion
FROM tbanimalinteraccion
WHERE tbanimalinteraccionaccion NOT IN ('AGREGAR', 'RETIRAR')
UNION ALL
SELECT 'tbcarritoanimal.accion', tbcarritoanimalid, tbcarritoanimalaccion
FROM tbcarritoanimal
WHERE tbcarritoanimalaccion NOT IN ('AGREGAR', 'RETIRAR');

-- D-18: periodos abiertos duplicados por entidad y tipo.
SELECT 'D-18 periodos abiertos duplicados' AS diagnostico;
SELECT 'tbproductorclasificacionperiodo' AS tabla, tbproductorid AS entidad, tbproductorclasificacionperiodotipo AS tipo, COUNT(*) AS abiertos
FROM tbproductorclasificacionperiodo
WHERE tbproductorclasificacionperiodofechafin IS NULL
GROUP BY tbproductorid, tbproductorclasificacionperiodotipo
HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbtransportistaestadoperiodo', tbtransportistaid, CAST(tbtransportistaestadoperiodoestado AS CHAR), COUNT(*)
FROM tbtransportistaestadoperiodo
WHERE tbtransportistaestadoperiodofechafin IS NULL
GROUP BY tbtransportistaid, tbtransportistaestadoperiodoestado
HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbcarritoestadoperiodo', tbcarritoid, 'estado', COUNT(*)
FROM tbcarritoestadoperiodo
WHERE tbcarritoestadoperiodofechafin IS NULL
GROUP BY tbcarritoid
HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbanimalpublicacionestadoperiodo', tbanimalpublicacionid, 'estado', COUNT(*)
FROM tbanimalpublicacionestadoperiodo
WHERE tbanimalpublicacionestadoperiodofechafin IS NULL
GROUP BY tbanimalpublicacionid
HAVING COUNT(*) > 1
UNION ALL
SELECT 'tbtransportistahorario', tbtransportistaid, tbtransportistahorariodiasemana, COUNT(*)
FROM tbtransportistahorario
WHERE tbtransportistahorariofechafin IS NULL
GROUP BY tbtransportistaid, tbtransportistahorariodiasemana
HAVING COUNT(*) > 1;

-- D-19: periodos solapados por productor/tipo o transportista.
SELECT 'D-19 periodos solapados' AS diagnostico;
SELECT 'tbproductorclasificacionperiodo' AS tabla, a.tbproductorclasificacionperiodoid AS periodo_a,
       b.tbproductorclasificacionperiodoid AS periodo_b
FROM tbproductorclasificacionperiodo a
JOIN tbproductorclasificacionperiodo b
  ON a.tbproductorid = b.tbproductorid
 AND a.tbproductorclasificacionperiodotipo = b.tbproductorclasificacionperiodotipo
 AND a.tbproductorclasificacionperiodoid < b.tbproductorclasificacionperiodoid
 AND a.tbproductorclasificacionperiodofechainicio < COALESCE(b.tbproductorclasificacionperiodofechafin, '9999-12-31 23:59:59')
 AND b.tbproductorclasificacionperiodofechainicio < COALESCE(a.tbproductorclasificacionperiodofechafin, '9999-12-31 23:59:59')
UNION ALL
SELECT 'tbtransportistaestadoperiodo', a.tbtransportistaestadoperiodoid, b.tbtransportistaestadoperiodoid
FROM tbtransportistaestadoperiodo a
JOIN tbtransportistaestadoperiodo b
  ON a.tbtransportistaid = b.tbtransportistaid
 AND a.tbtransportistaestadoperiodoid < b.tbtransportistaestadoperiodoid
 AND a.tbtransportistaestadoperiodofechainicio IS NOT NULL
 AND b.tbtransportistaestadoperiodofechainicio IS NOT NULL
 AND a.tbtransportistaestadoperiodofechainicio < COALESCE(b.tbtransportistaestadoperiodofechafin, '9999-12-31 23:59:59')
 AND b.tbtransportistaestadoperiodofechainicio < COALESCE(a.tbtransportistaestadoperiodofechafin, '9999-12-31 23:59:59')
UNION ALL
SELECT 'tbcarritoestadoperiodo', a.tbcarritoestadoperiodoid, b.tbcarritoestadoperiodoid
FROM tbcarritoestadoperiodo a
JOIN tbcarritoestadoperiodo b
  ON a.tbcarritoid = b.tbcarritoid
 AND a.tbcarritoestadoperiodoid < b.tbcarritoestadoperiodoid
 AND a.tbcarritoestadoperiodofechainicio < COALESCE(b.tbcarritoestadoperiodofechafin, '9999-12-31 23:59:59')
 AND b.tbcarritoestadoperiodofechainicio < COALESCE(a.tbcarritoestadoperiodofechafin, '9999-12-31 23:59:59')
UNION ALL
SELECT 'tbanimalpublicacionestadoperiodo', a.tbanimalpublicacionestadoperiodoid, b.tbanimalpublicacionestadoperiodoid
FROM tbanimalpublicacionestadoperiodo a
JOIN tbanimalpublicacionestadoperiodo b
  ON a.tbanimalpublicacionid = b.tbanimalpublicacionid
 AND a.tbanimalpublicacionestadoperiodoid < b.tbanimalpublicacionestadoperiodoid
 AND a.tbanimalpublicacionestadoperiodofechainicio < COALESCE(b.tbanimalpublicacionestadoperiodofechafin, '9999-12-31 23:59:59')
 AND b.tbanimalpublicacionestadoperiodofechainicio < COALESCE(a.tbanimalpublicacionestadoperiodofechafin, '9999-12-31 23:59:59')
UNION ALL
SELECT 'tbtransportistahorario', a.tbtransportistahorarioid, b.tbtransportistahorarioid
FROM tbtransportistahorario a
JOIN tbtransportistahorario b
  ON a.tbtransportistaid = b.tbtransportistaid
 AND a.tbtransportistahorariodiasemana = b.tbtransportistahorariodiasemana
 AND a.tbtransportistahorarioid < b.tbtransportistahorarioid
 AND a.tbtransportistahorariofechainicio < COALESCE(b.tbtransportistahorariofechafin, '9999-12-31 23:59:59')
 AND b.tbtransportistahorariofechainicio < COALESCE(a.tbtransportistahorariofechafin, '9999-12-31 23:59:59');

-- D-20: valores numéricos fuera de dominio lógico. PHP debe impedirlos; SQL
-- directo puede insertarlos por ausencia deliberada de CHECK.
SELECT 'D-20 valores comerciales fuera de dominio numerico' AS diagnostico;
SELECT 'tbanimalproduccionsalud.edadmeses' AS campo, tbanimalproduccionsaludid AS registro, tbanimalproduccionsaludedadmeses AS valor
FROM tbanimalproduccionsalud
WHERE tbanimalproduccionsaludedadmeses IS NOT NULL AND tbanimalproduccionsaludedadmeses < 0
UNION ALL
SELECT 'tbanimalproduccionsalud.peso', tbanimalproduccionsaludid, tbanimalproduccionsaludpeso
FROM tbanimalproduccionsalud
WHERE tbanimalproduccionsaludpeso IS NOT NULL AND tbanimalproduccionsaludpeso < 0
UNION ALL
SELECT 'tbanimalproduccionsalud.partos', tbanimalproduccionsaludid, tbanimalproduccionsaludpartos
FROM tbanimalproduccionsalud
WHERE tbanimalproduccionsaludpartos IS NOT NULL AND tbanimalproduccionsaludpartos < 0
UNION ALL
SELECT 'tbanimalproduccionsalud.litrosleche', tbanimalproduccionsaludid, tbanimalproduccionsaludlitrosleche
FROM tbanimalproduccionsalud
WHERE tbanimalproduccionsaludlitrosleche IS NOT NULL AND tbanimalproduccionsaludlitrosleche < 0
UNION ALL
SELECT 'tbcompra.precio', tbcompraid, tbcompraprecio
FROM tbcompra
WHERE tbcompraprecio < 0
UNION ALL
SELECT 'tbventa.precio', tbventaid, tbventaprecio
FROM tbventa
WHERE tbventaprecio < 0
UNION ALL
SELECT 'tbventa.edadmeses', tbventaid, tbventaedadmeses
FROM tbventa
WHERE tbventaedadmeses IS NOT NULL AND tbventaedadmeses < 0
UNION ALL
SELECT 'tbventa.peso', tbventaid, tbventapeso
FROM tbventa
WHERE tbventapeso IS NOT NULL AND tbventapeso < 0
UNION ALL
SELECT 'tbtransportistaflete.precio', tbtransportistafleteid, tbtransportistafleteprecio
FROM tbtransportistaflete
WHERE tbtransportistafleteprecio IS NOT NULL AND tbtransportistafleteprecio < 0
UNION ALL
SELECT 'tbtransportistaresena.calificacion', tbtransportistaresenaid, tbtransportistaresenacalificacion
FROM tbtransportistaresena
WHERE tbtransportistaresenacalificacion < 1 OR tbtransportistaresenacalificacion > 5
UNION ALL
SELECT 'tbtransportistaflete.cantidadcabezas', tbtransportistafleteid, tbtransportistafletecantidadcabezas
FROM tbtransportistaflete
WHERE tbtransportistafletecantidadcabezas IS NOT NULL AND tbtransportistafletecantidadcabezas < 1
UNION ALL
SELECT 'tbtransportistaflete.distanciakm', tbtransportistafleteid, tbtransportistafletedistanciakm
FROM tbtransportistaflete
WHERE tbtransportistafletedistanciakm IS NOT NULL AND tbtransportistafletedistanciakm < 0;

-- D-21: horarios de transportista con hora de fin anterior o igual a la de
-- inicio. PHP debe rechazarlos; sin CHECK el motor los acepta.
SELECT 'D-21 horario de transportista invertido' AS diagnostico;
SELECT tbtransportistahorarioid, tbtransportistaid, tbtransportistahorariodiasemana,
       tbtransportistahorariohorainicio, tbtransportistahorariohorafin
FROM tbtransportistahorario
WHERE tbtransportistahorariohorafin <= tbtransportistahorariohorainicio;

-- D-22: compradores legacy sin Productor. Comprador es una clasificacion del
-- Productor (DEC-DBREADY-005/006), asi que una fila de tbcomprador cuya persona
-- no es productora NO puede migrarse: no se inventa un Productor. Debe
-- resolverse a mano antes de retirar la tabla legacy.
SELECT 'D-22 comprador legacy sin productor' AS diagnostico;
SELECT c.tbcompradorid, c.tbpersonaid, c.tbcompradorestado,
       pe.tbpersonaidentificacionnumero, pe.tbpersonanombre
FROM tbcomprador c
INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
LEFT JOIN tbproductor p ON p.tbpersonaid = c.tbpersonaid
WHERE p.tbproductorid IS NULL;

-- D-23: comprador legacy activo, con Productor, sin periodo COMPRADOR abierto.
-- Despues del backfill (Tools/backfill-clasificacion-comprador.php) y del
-- cambio de escrituras esto debe ser cero: si aparece, la clasificacion quedo
-- desincronizada del bit legacy y el panel mostraria un comprador como falso.
SELECT 'D-23 comprador legacy activo sin clasificacion abierta' AS diagnostico;
SELECT c.tbcompradorid, p.tbproductorid, pe.tbpersonaidentificacionnumero
FROM tbcomprador c
INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
INNER JOIN tbproductor p ON p.tbpersonaid = c.tbpersonaid
LEFT JOIN tbproductorclasificacionperiodo cp
       ON cp.tbproductorid = p.tbproductorid
      AND cp.tbproductorclasificacionperiodotipo = 'COMPRADOR'
      AND cp.tbproductorclasificacionperiodofechafin IS NULL
WHERE c.tbcompradorestado = 1 AND pe.tbpersonaestado = 1
  AND cp.tbproductorclasificacionperiodoid IS NULL;

-- D-24: el reverso. Comprador legacy dado de baja que conserva la
-- clasificacion abierta: desactivar debe cerrar el periodo, no dejarlo vivo.
SELECT 'D-24 comprador legacy inactivo con clasificacion abierta' AS diagnostico;
SELECT c.tbcompradorid, p.tbproductorid, cp.tbproductorclasificacionperiodoid
FROM tbcomprador c
INNER JOIN tbpersona pe ON pe.tbpersonaid = c.tbpersonaid
INNER JOIN tbproductor p ON p.tbpersonaid = c.tbpersonaid
INNER JOIN tbproductorclasificacionperiodo cp
        ON cp.tbproductorid = p.tbproductorid
       AND cp.tbproductorclasificacionperiodotipo = 'COMPRADOR'
       AND cp.tbproductorclasificacionperiodofechafin IS NULL
WHERE c.tbcompradorestado = 0 OR pe.tbpersonaestado = 0;
