# Decisiones - Corrección 04

## DEC-C04-001 - Instrucción docente vigente

La instrucción docente sustituye el modelo anterior. `dbtindervacas` conserva
cuatro tablas: `tbproductor`, `tbproductordireccion`, `tbfinca` y
`tbbitacora`.

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
