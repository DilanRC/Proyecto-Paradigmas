USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Comprobación 1: estructura declarada de las tablas del avance.
-- Ejecutar:
--   docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" \
--     < Database/Tests/comprobacionestructura.sql

SELECT '--- tbdireccion' AS comprobacion;
DESCRIBE tbdireccion;

SELECT '--- tbpersona' AS comprobacion;
DESCRIBE tbpersona;

SELECT '--- perfiles de capacidad' AS comprobacion;
DESCRIBE tbproductor;
DESCRIBE tbcomprador;
DESCRIBE tbtransportista;

SELECT '--- tbproductordireccion' AS comprobacion;
DESCRIBE tbproductordireccion;

SELECT '--- tbfincadireccion' AS comprobacion;
DESCRIBE tbfincadireccion;

SELECT '--- tbpagometodo' AS comprobacion;
DESCRIBE tbpagometodo;

SELECT '--- tbtransportista' AS comprobacion;
DESCRIBE tbtransportista;

SELECT '--- tbvehiculo' AS comprobacion;
DESCRIBE tbvehiculo;

SELECT '--- tbtransportistavehiculo' AS comprobacion;
DESCRIBE tbtransportistavehiculo;

SELECT '--- tbproductorestadoperiodo' AS comprobacion;
DESCRIBE tbproductorestadoperiodo;

SELECT '--- tbproductorubicacion' AS comprobacion;
DESCRIBE tbproductorubicacion;

SELECT '--- tbproductoractividad' AS comprobacion;
DESCRIBE tbproductoractividad;

SELECT '--- tbproductorclasificacionperiodo' AS comprobacion;
DESCRIBE tbproductorclasificacionperiodo;

SELECT '--- animales y publicaciones' AS comprobacion;
DESCRIBE tbanimal;
DESCRIBE tbanimalproduccionsalud;
DESCRIBE tbanimalpublicacion;
DESCRIBE tbanimalpublicacionestadoperiodo;

SELECT '--- compra y venta' AS comprobacion;
DESCRIBE tbcompra;
DESCRIBE tbventa;

SELECT '--- funnel y carrito' AS comprobacion;
DESCRIBE tbanimalinteraccion;
DESCRIBE tbcarrito;
DESCRIBE tbcarritoanimal;
DESCRIBE tbcarritoestadoperiodo;

SELECT '--- transporte historico' AS comprobacion;
DESCRIBE tbtransportistaestadoperiodo;
DESCRIBE tbtransportistahorario;
DESCRIBE tbtransportistaflete;
DESCRIBE tbtransportistaresena;

-- Comprobación 2: el avance no introdujo llaves, restricciones ni índices.
-- Resultado esperado: cero filas en las tres consultas.
SELECT '--- restricciones declaradas (esperado: 0 filas)' AS comprobacion;
SELECT table_name, constraint_name, constraint_type
FROM information_schema.table_constraints
WHERE constraint_schema = DATABASE();

SELECT '--- indices declarados (esperado: 0 filas)' AS comprobacion;
SELECT table_name, index_name
FROM information_schema.statistics
WHERE table_schema = DATABASE()
GROUP BY table_name, index_name;

SELECT '--- columnas automaticas (esperado: 0 filas)' AS comprobacion;
SELECT table_name, column_name, extra
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (column_default IS NOT NULL OR extra <> '' OR generation_expression <> '');

-- Comprobación 3: objetos programables. Resultado esperado: cero filas.
SELECT '--- objetos programables (esperado: 0 filas)' AS comprobacion;
SELECT trigger_name AS objeto FROM information_schema.triggers WHERE trigger_schema = DATABASE()
UNION ALL
SELECT routine_name FROM information_schema.routines WHERE routine_schema = DATABASE()
UNION ALL
SELECT event_name FROM information_schema.events WHERE event_schema = DATABASE();

-- Comprobación 4: exactamente 30 tablas base.
SELECT COUNT(*) AS tablas_esperadas_30
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE';
