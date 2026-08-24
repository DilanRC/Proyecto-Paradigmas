# Decisiones - Corrección 04

## DEC-C04-001 - Instrucción docente vigente

La instrucción docente sustituye el modelo anterior. `dbtindervacas` conserva
cinco tablas: `tbproductor`, `tbproductordireccion`, `tbfinca`, `tbbitacora` y
`tbcomprador`.

## DEC-C04-002 - Cero restricciones de integridad

El esquema no define claves, restricciones, índices, valores `DEFAULT`,
`AUTO_INCREMENT`, triggers, rutinas ni eventos. Las
asociaciones y validaciones son una política de aplicación. SQL directo puede
crear duplicados, huérfanos o valores fuera del dominio.

## DEC-C04-003 - ID de productor calculado en PHP

`tbproductorid` es `INT NOT NULL`, no es clave y no usa `AUTO_INCREMENT`.
Durante POST, PHP mantiene un bloqueo nombrado hasta después del commit,
calcula `MAX(tbproductorid) + 1` mediante SQL preparado y asigna el resultado.
Dirección y finca guardan ese mismo valor como enlace lógico, sin FK.

## DEC-C04-004 - Identificación inmutable por contrato

`tbproductoridentificacionnumero` no es PK. La aplicación no permite cambiarla.
Si fue digitada incorrectamente se debe:

1. desactivar el registro incorrecto;
2. conservar su bitácora;
3. crear el registro correcto;
4. no modificar directamente la identificación existente.

## DEC-C04-005 - Sentencias preparadas

Los modelos usan `PDO::prepare()` y parámetros enlazados. Ningún valor recibido
por HTTP se concatena al SQL. PDO mantiene desactivada la emulación de
sentencias preparadas.

## DEC-C04-006 - Bitácora

La bitácora registra CREAR, ACTUALIZAR, DESACTIVAR y REACTIVAR dentro de la
misma transacción. Antes de autenticación usa `NO_AUTENTICADO` y
`tbbitacorausuarioid = NULL`. PHP calcula `tbbitacoraid` con
`MAX(tbbitacoraid) + 1` bajo un bloqueo nombrado. MySQL no genera ningún ID.
PHP también envía la fecha, el actor y el origen como parámetros de la
sentencia preparada; el motor no completa columnas automáticamente.

## DEC-C04-007 - Entregas históricas

Avance01 y Correcciones 01, 02 y 03 permanecen intactas. La nueva evidencia,
respaldo y etiqueta corresponden a Corrección 04.

## DEC-C04-008 - Compradores

`tbcomprador` conserva identificación, nombre, teléfono, correo y estado con
el mismo perfil de tipos de `tbproductor`. Es una tabla independiente: no se
agregan relaciones, claves, índices, defaults ni valores automáticos. La
migración v2 crea y valida la misma estructura en Supabase PostgreSQL.

# Decisiones - Avance de direcciones, pagos y transporte

Este bloque solamente toca base de datos y documentación. Ninguna decisión se
implementa con código de aplicación.

## DEC-01

El productor mantiene una residencia principal. `tbproductordireccion` conserva
una sola fila por `tbproductorid`.

## DEC-02

Cada finca posee una dirección, registrada en `tbfincadireccion`.

## DEC-03

Un productor puede poseer varias fincas. La cardinalidad vive en
`tbfinca.tbproductorid`.

## DEC-04

La residencia del productor puede coincidir con la dirección de una finca.

## DEC-05

Cuando ambos representan el mismo lugar físico, pueden utilizar el mismo
`tbdireccionid`. Por eso la ubicación se centraliza en `tbdireccion` y no se
duplica por tipo de entidad.

## DEC-06

El transportista se modela como persona independiente, con `tbtransportistaid`
propio. No se deriva de productor, comprador, usuario, finca ni empresa. Eso es
lo confirmado. Identificación, tipo, nombre, teléfono, correo y estado son una
propuesta de modelado para poder identificar y contactar a la persona, siguiendo
el patrón de personas ya registrado en el proyecto: no fueron solicitados y
pueden retirarse. Queda pendiente confirmar cuáles son obligatorios.

## DEC-07

Un transportista puede tener varios vehículos, mediante
`tbtransportistavehiculo`.

## DEC-08

Dentro del modelo conceptual actual, un vehículo corresponde a un solo
transportista. Es una política documental: el motor acepta lo contrario y la
consulta `D-05` de `Database/Tests/diagnostico.sql` lo detecta.

## DEC-09

Placa, VIN y modelo son los datos confirmados actualmente para vehículo.
`tbvehiculoestado` es una propuesta de modelado por coherencia con el patrón de
estado lógico del resto de tablas, no un requisito recibido. Cualquier otro
atributo queda como PENDIENTE DE CONFIRMACIÓN en el diccionario de datos.

## DEC-10

Todas las operaciones económicas del alcance actual utilizan efectivo.
`tbpagometodo` se crea con una sola fila y sin relación con otras tablas.

## DEC-11

Las tablas del avance no utilizan llaves primarias, llaves foráneas, unicidad,
verificaciones ni consecutivos del motor. Tampoco se sustituyen por triggers,
procedimientos, funciones ni eventos.

## DEC-12

Las reglas que SQL no garantiza quedan documentadas como políticas del modelo y
deberán ser atendidas por la capa correspondiente del proyecto. Este avance no
implementa esa capa.

## DEC-13 - Una sola fuente de verdad para la dirección

`tbproductordireccion` queda con tres columnas:
`tbproductordireccionid`, `tbproductorid` y `tbdireccionid INT NOT NULL`. La
ubicación completa vive únicamente en `tbdireccion`.

La respuesta a "¿dónde está almacenada la dirección del productor?" es una sola:
en `tbdireccion`. Conservar además las columnas heredadas habría contradicho la
DEC-05 y dejado el dato en dos lugares.

`NOT NULL` no entra en las construcciones prohibidas: lo prohibido es
`PRIMARY KEY`, `FOREIGN KEY`, `UNIQUE`, `CHECK` y `AUTO_INCREMENT`.

La migración `Database/Migrations/001normalizadireccionproductor.sql` traslada
las direcciones existentes, comprueba que cada productor quedó enlazado y
después elimina las cinco columnas.

Consecuencia: **el contrato de base cambió**. La aplicación ya no debe escribir
provincia, cantón, distrito, pueblo ni señas en `tbproductordireccion`; debe
hacerlo en `tbdireccion` y guardar el enlace. Adaptar
`Application/Model/ProductorDireccion.php` es **FUERA DEL ALCANCE DE BASE DE
DATOS**: la base se normalizó según el modelo, y la aplicación que la consume se
adapta al contrato actualizado.

## DEC-14 - Sin tabla puente productor-finca

No existe `tbproductorfinca` en la base ni se crea. La única cardinalidad
confirmada es 1 productor a N fincas, que `tbfinca.tbproductorid` ya representa.
Introducir una tabla puente supondría una cardinalidad N a N todavía no
definida.

## DEC-15 - MySQL es la entrega, Supabase es el espejo

`dbtindervacas` en MySQL es la base del curso y la que debe estar correcta. El
espejo PostgreSQL de `services/supabase-database` se actualizó al mismo modelo
en la migración `v3`: once tablas, `tbproductordireccion` normalizada y el mismo
criterio de cero llaves, restricciones, índices y valores automáticos. El espejo
sigue a MySQL; nunca al revés.

## DEC-16 - Ubicaciones GPS append-only

`tbproductorubicacion` es una serie temporal: cada lectura GPS del productor
inserta una fila nueva y ninguna fila se actualiza ni se elimina. Las
consecuencias vigentes son:

1. **Solo INSERT**: `Application/Model/ProductorUbicacion.php` no expone
   `actualizar()` ni `eliminar()`, y el endpoint
   `/api/productores-ubicacion.php` rechaza PUT, PATCH y DELETE con 405.
2. **Fecha del servidor**: PHP asigna `tbproductorubicacionfecha` con su reloj;
   el campo `fecha` que pudiera enviar el cliente se descarta.
3. **Origen conjunto controlado**: `tbproductorubicacionorigen` solo acepta
   `NAVEGADOR` o `MANUAL`; cualquier otro valor se rechaza con error por campo.
4. **Lock dedicado**: el consecutivo usa `MAX(tbproductorubicacionid) + 1`
   bajo el bloqueo nombrado `tindercows_productor_ubicacion_alta`, retenido
   hasta después del COMMIT para garantizar IDs únicos bajo ráfagas
   simultáneas.
5. **Bitácora en la misma transacción**: cada inserción registra
   `REGISTRAR_UBICACION` en `tbbitacora` antes del commit.
6. **Coordenadas exactas**: latitud y longitud se validan por rango (-90..90,
   -180..180) y se guardan como texto hacia `DECIMAL(10,7)`, sin redondeos de
   punto flotante.
