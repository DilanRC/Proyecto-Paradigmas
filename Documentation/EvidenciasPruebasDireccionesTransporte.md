# Evidencias - Avance de direcciones, pagos y transporte

Todas las comprobaciones se ejecutaron sobre una base MySQL 8.0 limpia,
inicializada únicamente con `Database/SqlScripts/*.sql` y
`Database/SeedData/*.sql` en orden alfabético. Cero errores en el arranque del
motor.

## 1. Estructura creada desde base limpia

`SHOW TABLES` sobre `dbtindervacas`:

```text
tbbitacora
tbcomprador
tbdireccion
tbfinca
tbfincadireccion
tbpagometodo
tbproductor
tbproductordireccion
tbtransportista
tbtransportistavehiculo
tbvehiculo
```

`Database/Tests/comprobacionestructura.sql`:

```text
tbdireccion
+----------------------+--------------+------+-----+---------+-------+
| Field                | Type         | Null | Key | Default | Extra |
+----------------------+--------------+------+-----+---------+-------+
| tbdireccionid        | int          | NO   |     | NULL    |       |
| tbdireccionprovincia | varchar(100) | NO   |     | NULL    |       |
| tbdireccioncanton    | varchar(100) | NO   |     | NULL    |       |
| tbdirecciondistrito  | varchar(100) | NO   |     | NULL    |       |
| tbdireccionpueblo    | varchar(150) | YES  |     | NULL    |       |
| tbdireccionsenas     | varchar(500) | YES  |     | NULL    |       |
+----------------------+--------------+------+-----+---------+-------+

tbproductordireccion
+------------------------+------+------+-----+---------+-------+
| tbproductordireccionid | int  | NO   |     | NULL    |       |
| tbproductorid          | int  | NO   |     | NULL    |       |
| tbdireccionid          | int  | NO   |     | NULL    |       |
+------------------------+------+------+-----+---------+-------+

tbtransportistavehiculo
+---------------------------+------+------+-----+---------+-------+
| tbtransportistavehiculoid | int  | NO   |     | NULL    |       |
| tbtransportistaid         | int  | NO   |     | NULL    |       |
| tbvehiculoid              | int  | NO   |     | NULL    |       |
+---------------------------+------+------+-----+---------+-------+
```

La columna `Key` quedó vacía en todas las tablas. Las consultas de metadatos del
mismo script devolvieron **cero filas** en restricciones declaradas, índices,
columnas con valor automático y objetos programables.

## 2. Datos iniciales

`Database/Tests/comprobaciondatosiniciales.sql`:

```text
+----------------+--------------------+----------------------------+--------------------+
| tbpagometodoid | tbpagometodonombre | tbpagometododescripcion    | tbpagometodoactivo |
+----------------+--------------------+----------------------------+--------------------+
|              1 | Efectivo           | Pago realizado en efectivo |                  1 |
+----------------+--------------------+----------------------------+--------------------+
```

La consulta de métodos distintos de `Efectivo` devolvió cero filas.

## 3. Relaciones

`Database/Tests/comprobacionrelaciones.sql`:

```text
Productor y finca en el mismo lugar físico
+---------------+-----------+----------------------+
| tbproductorid | tbfincaid | direccion_compartida |
+---------------+-----------+----------------------+
|          -901 |      -801 |                -9001 |
+---------------+-----------+----------------------+

Productor y finca en lugares distintos
+---------------+---------------------+-----------+-----------------+
| tbproductorid | direccion_productor | tbfincaid | direccion_finca |
+---------------+---------------------+-----------+-----------------+
|          -902 |               -9002 |      -802 |           -9003 |
+---------------+---------------------+-----------+-----------------+

Un productor con varias fincas
+---------------+--------+
| tbproductorid | fincas |
+---------------+--------+
|          -901 |      2 |
+---------------+--------+

Un transportista con varios vehículos
+-------------------+-----------+
| tbtransportistaid | vehiculos |
+-------------------+-----------+
|              -701 |         2 |
+-------------------+-----------+

Limpieza
+--------------------+----------------------+-----------------------+------------------+
| direcciones_prueba | enlaces_finca_prueba | transportistas_prueba | vehiculos_prueba |
+--------------------+----------------------+-----------------------+------------------+
|                  0 |                    0 |                     0 |                0 |
+--------------------+----------------------+-----------------------+------------------+
```

## 4. Diagnóstico

`Database/Tests/diagnostico.sql`:

| Consulta | Resultado |
|---|---|
| D-01 productores con más de una dirección | 0 filas |
| D-02 fincas con más de una dirección | 0 filas |
| D-03 placas repetidas | 0 filas |
| D-04 vin repetidos | 0 filas |
| D-05 vehículos con más de un transportista | 0 filas |
| D-06 identificadores repetidos | 0 filas |
| D-07 asociaciones huérfanas | 0 filas |
| D-08 direcciones sin uso | 0 filas |
| D-09 ubicaciones con los mismos datos | 0 filas |

## 5. Pruebas del repositorio

| Comando | Resultado |
|---|---|
| `php Tests/naming_gate.php` | APROBADA: once tablas, cero llaves ni restricciones, enlaces del avance presentes, `Efectivo` como único método |
| `php Tests/naming_eval.php` | APROBADA: score 100 sobre umbral 100 |
| `php Tests/schema_test.php` | APROBADA la parte de base de datos contra base viva: once tablas, columnas exactas, cero restricciones, cero índices, cero valores automáticos y `Efectivo` en `tbpagometodo`. La parte que ejercita el CRUD falla: ver sección 6 |
| `php Tests/comprador_test.php` | APROBADA |
| `python3 Tests/documentation_test.py` | APROBADA: PDF regenerados y alineados |
| `php Tests/deployment_test.php` | APROBADA |
| `php services/supabase-database/tests/schema_test.php` | APROBADA: el espejo PostgreSQL declara las once tablas y la normalización |
| `php services/supabase-database/evals/schema_eval.php` | APROBADA: score 100 |
| `php Tests/api_productores_test.php` | NO EJECUTADA: requiere el servidor HTTP del contenedor `app` |

## 6. Migración de una base existente

Comprobada sobre un contenedor MySQL inicializado con el esquema anterior al
avance, aplicando `Database/SqlScripts/007` a `012` y después
`Database/Migrations/001normalizadireccionproductor.sql`.

Antes:

```text
+------------------------+---------------+-------------------------------+----------------------------+------------------------------+----------------------------+------------------------------------+
| tbproductordireccionid | tbproductorid | tbproductordireccionprovincia | tbproductordireccioncanton | tbproductordirecciondistrito | tbproductordireccionpueblo | tbproductordireccionsenas          |
+------------------------+---------------+-------------------------------+----------------------------+------------------------------+----------------------------+------------------------------------+
|                      1 |             1 | Alajuela                      | San Carlos                 | Quesada                      | Centro                     | Datos ficticios para demostracion. |
|                      2 |             2 | Guanacaste                    | Tilaran                    | Tilaran                      | NULL                       | Datos ficticios para demostracion. |
+------------------------+---------------+-------------------------------+----------------------------+------------------------------+----------------------------+------------------------------------+
```

Después:

```text
+------------------------+---------------+---------------+----------------------+-------------------+---------------------+-------------------+------------------------------------+
| tbproductordireccionid | tbproductorid | tbdireccionid | tbdireccionprovincia | tbdireccioncanton | tbdirecciondistrito | tbdireccionpueblo | tbdireccionsenas                   |
+------------------------+---------------+---------------+----------------------+-------------------+---------------------+-------------------+------------------------------------+
|                      1 |             1 |             1 | Alajuela             | San Carlos        | Quesada             | Centro            | Datos ficticios para demostracion. |
|                      2 |             2 |             2 | Guanacaste           | Tilaran           | Tilaran             | NULL              | Datos ficticios para demostracion. |
+------------------------+---------------+---------------+----------------------+-------------------+---------------------+-------------------+------------------------------------+
```

Las tres comprobaciones obligatorias de la migración devolvieron 0:
`residencias_sin_enlace`, `enlaces_sin_ubicacion` y
`ubicaciones_con_datos_distintos`. El diagnóstico posterior devolvió cero filas
en D-01 a D-09.

## 7. Contrato roto a propósito

La base se normalizó según el modelo; la aplicación que la consume todavía no.
Estas pruebas fallan hasta que se adapte
`Application/Model/ProductorDireccion.php` y la consulta de
`Application/Model/Productor.php:55`:

| Prueba | Error |
|---|---|
| `Tests/schema_test.php` (parte CRUD) | `Unknown column 'd.tbproductordireccionprovincia'` |
| `Tests/transaction_test.php` | mismo origen |
| `Tests/address_policy_test.php` | mismo origen |
| `Tests/audit_test.php` | mismo origen |
| `Tests/concurrency_test.php` | `Unknown column 'tbproductordireccionprovincia'` |
| `Tests/direccion_test.php` | mismo origen |

Es el efecto esperado del cambio de contrato descrito en `DEC-13`, no un defecto
de la base. Corresponde al backend: **FUERA DEL ALCANCE DE BASE DE DATOS**.

## 8. Supabase

`services/supabase-database/` quedó alineado con el mismo modelo: once tablas,
`tbproductordireccion` normalizada, RLS en todas y `Efectivo` como único dato
inicial. La migración `v3` normaliza también el espejo PostgreSQL antes de
validar y deja la traza `supabase_schema_status=ready tables=11 migration=v3`.

La migración se aplicó al proyecto `mkxugbjvvlcyxosjjzit` con el nombre
`v3_direcciones_pagos_transporte`. Estado comprobado después de aplicarla:

| Elemento | Antes | Después |
|---|---|---|
| Tablas en `public` | 5 | 11 |
| `tbproductordireccion` | 7 columnas, con la ubicación adentro | 3 columnas, `tbdireccionid NOT NULL` |
| Filas afectadas | las cinco tablas estaban vacías | ningún dato descartado |
| RLS | 5 de 5 | 11 de 11 |
| Llaves, unicidad y verificaciones reales en `pg_constraint` | 0 | 0 |
| Índices | 0 | 0 |
| Columnas con valor automático | 0 | 0 |
| Triggers | 0 | 0 |
| `tbpagometodo` | no existía | 1 fila: `Efectivo` |

PostgREST recargó su caché: las seis tablas nuevas responden por la API REST.
Repetir los pasos idempotentes de `migrate.php` sobre el esquema ya migrado no
duplica ni falla, así que el próximo despliegue en Vercel lo encontrará
conforme. Reporte completo en `/tmp/supabase-v3/reporte.md`.
