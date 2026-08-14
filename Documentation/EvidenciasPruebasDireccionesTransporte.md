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
+-------------------------------+--------------+------+-----+---------+-------+
| tbproductordireccionid        | int          | NO   |     | NULL    |       |
| tbproductorid                 | int          | NO   |     | NULL    |       |
| tbdireccionid                 | int          | YES  |     | NULL    |       |
| tbproductordireccionprovincia | varchar(100) | NO   |     | NULL    |       |
| tbproductordireccioncanton    | varchar(100) | NO   |     | NULL    |       |
| tbproductordirecciondistrito  | varchar(100) | NO   |     | NULL    |       |
| tbproductordireccionpueblo    | varchar(150) | YES  |     | NULL    |       |
| tbproductordireccionsenas     | varchar(500) | YES  |     | NULL    |       |
+-------------------------------+--------------+------+-----+---------+-------+

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
| D-09 residencias sin `tbdireccionid` | 2 filas: los productores 1 y 2 del ejemplo |

`D-09` es la lista de trabajo pendiente para la capa de aplicación, no un
defecto del modelo. Ver `DEC-13`.

## 5. Pruebas del repositorio

| Comando | Resultado |
|---|---|
| `php Tests/naming_gate.php` | APROBADA: once tablas, cero llaves ni restricciones, enlaces del avance presentes, `Efectivo` como único método |
| `php Tests/naming_eval.php` | APROBADA: score 100 sobre umbral 100 |
| `php Tests/schema_test.php` | APROBADA contra base viva: once tablas, columnas exactas, cero restricciones, cero índices, cero valores automáticos y `Efectivo` en `tbpagometodo` |
| `php Tests/transaction_test.php` | APROBADA |
| `php Tests/address_policy_test.php` | APROBADA: el CRUD vigente sigue escribiendo la dirección sin cambios |
| `php Tests/audit_test.php` | APROBADA |
| `php Tests/concurrency_test.php` | APROBADA |
| `python3 Tests/documentation_test.py` | APROBADA: PDF regenerados y alineados |
| `php Tests/deployment_test.php` | APROBADA |
| `php services/supabase-database/tests/schema_test.php` | APROBADA: el espejo PostgreSQL conserva sus cinco tablas |
| `php Tests/api_productores_test.php` | NO EJECUTADA: requiere el servidor HTTP del contenedor `app` |
