# Decisiones de la corrección del Avance 01

## DEC-C01-001 — Instrucción docente prevalece

La instrucción docente recibida después de `avance-01` sustituye el modelo de
participante, roles y catálogos. El modelo activo tendrá únicamente
`tbproductores`, `tbproductoresdireccion`, `tbproductoresfinca` y
`tbbitacora`. El diseño anterior permanece verificable en la etiqueta
`avance-01`; sus respaldos no se modifican.

## DEC-C01-002 — Identificación como clave primaria

`tbproductoresIdentificacionNumero` es la PK natural. La aplicación elimina
espacios y guiones y convierte letras a mayúsculas antes de persistirla. La PK
no se puede modificar mediante PUT porque identifica al registro y a sus hijos.

Si una identificación fue digitada incorrectamente, se debe:

1. desactivar el registro incorrecto;
2. conservar su bitácora;
3. crear el registro correcto;
4. no modificar directamente la PRIMARY KEY.

No se implementa una función para cambiar la identificación en este avance.

## DEC-C01-003 — Tipo de identificación sin catálogo

El tipo se almacena directamente en `tbproductoresIdentificacionTipo`. El
controlador acepta `CEDULA_FISICA`, `CEDULA_JURIDICA`, `DIMEX`, `NITE` y
`PASAPORTE`; la base aplica el mismo `CHECK`. Agregar un tipo exige cambiar
ambas reglas.

## DEC-C01-004 — Dirección 1:1

`tbproductoresdireccion` usa la identificación simultáneamente como PK y FK.
Cada productor tiene exactamente una dirección y no existen identificadores de
dirección artificiales.

## DEC-C01-005 — Finca dentro de productoresFinca

No existe `tbfinca`. `tbproductoresfinca` almacena la identificación del
productor y el nombre de la finca. Su PK compuesta permite varias fincas por
productor sin crear otra entidad ni un ID artificial de finca.

## DEC-C01-006 — Estado y bitácora

La desactivación sigue siendo lógica. `tbbitacora` conserva eventos con actor
`NO_AUTENTICADO`, usuario nulo y la identificación textual del productor. La
bitácora forma parte de la misma transacción que el cambio.

## DEC-C01-007 — Integridad relacional

Las tablas de dirección y finca conservan FOREIGN KEY hacia productores. Una FK
no es una tabla adicional ni un identificador artificial; impide registros
huérfanos. MySQL mostrará correctamente PRIMARY KEY y FOREIGN KEY en esas tablas.
La identificación es la llave natural e inmutable y las tablas dependientes
usan esa misma identificación como referencia. Por ello MySQL aplica
`ON UPDATE RESTRICT` y `ON DELETE RESTRICT`, en concordancia con PUT.

## DEC-C01-008 — Intercalación explícita

La base y las cuatro tablas usan `utf8mb4` con `utf8mb4_unicode_ci`. Compose
fija los valores del servidor y `001_create_database.sql` ejecuta tanto
`CREATE DATABASE IF NOT EXISTS` como `ALTER DATABASE`, porque `MYSQL_DATABASE`
puede crear la base antes de los scripts de inicialización.

## DEC-C01-009 — Versionado de la corrección

La nueva corrección se respalda en `Database/Backups/Avance01Correccion02/` y se
etiqueta como `avance-01-correccion-02`. No se sobrescriben `Avance01/` ni
`Avance01Correccion01/`, y no se mueven las etiquetas históricas.

## Pendientes

- Confirmar si deben admitirse nuevos tipos de identificación.
- Definir autenticación y autorización en un avance posterior.
