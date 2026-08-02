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

## DEC-C01-008 — Versionado de la corrección

La corrección se respalda en `Database/Backups/Avance01Correccion01/` y se
etiqueta como `avance-01-correccion-01`. No se sobrescribe `Avance01/` ni se
mueve la etiqueta `avance-01`.

## Pendientes

- Confirmar si en una entrega futura el profesor desea limitar cada productor a una sola finca.
- Confirmar si deben admitirse nuevos tipos de identificación.
