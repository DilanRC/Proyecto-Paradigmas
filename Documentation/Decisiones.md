# Decisiones de la corrección del Avance 01

## DEC-C03-001 - Alcance docente

La instrucción docente vigente reemplaza las decisiones anteriores sobre PK/FK.
El modelo activo contiene únicamente `tbproductores`,
`tbproductoresdireccion`, `tbproductoresfinca` y `tbbitacora`.

## DEC-C03-002 - Única PRIMARY KEY

`tbproductoresIdentificacionNumero` es la única PRIMARY KEY de todo el esquema.
Es una llave natural `VARCHAR`, se normaliza antes de persistir y PUT no permite
modificarla.

Si una identificación fue digitada incorrectamente, se debe:

1. desactivar el registro incorrecto;
2. conservar su bitácora;
3. crear el registro correcto;
4. no modificar directamente la PRIMARY KEY.

## DEC-C03-003 - Cero FOREIGN KEY

Dirección, finca y bitácora conservan la identificación solo como columna de
referencia lógica. No existe ninguna FOREIGN KEY ni reglas `ON UPDATE` o
`ON DELETE`. MySQL acepta huérfanos; la aplicación controla el flujo normal.

## DEC-C03-004 - Dirección por aplicación

`tbproductoresdireccion` no tiene PRIMARY KEY, FOREIGN KEY ni identificador
artificial. POST comprueba que no exista una dirección previa y PUT exige que
exista exactamente una. La relación 1:1 es una política de aplicación.

## DEC-C03-005 - Fincas sin llave compuesta

`tbproductoresfinca` no tiene PRIMARY KEY ni FOREIGN KEY. Puede almacenar varias
fincas por productor. La aplicación evita nombres repetidos y sincroniza estados
con `SELECT`, `UPDATE` e `INSERT`, sin depender de `ON DUPLICATE KEY`.

## DEC-C03-006 - Bitácora sin PRIMARY KEY

`tbbitacoraId` conserva `AUTO_INCREMENT` para ordenar eventos, pero no es PRIMARY
KEY. MySQL requiere indexarlo, por lo que usa un índice ordinario no único. La
bitácora registra CREAR, ACTUALIZAR, DESACTIVAR y REACTIVAR dentro de la misma
transacción, con actor `NO_AUTENTICADO` y `tbusuarioId = NULL`.

## DEC-C03-007 - Tipo e intercalación

El tipo de identificación permanece como columna controlada, sin catálogo. La
base y las cuatro tablas usan `utf8mb4` con `utf8mb4_unicode_ci`.

## DEC-C03-008 - Versionado

La nueva entrega usa `Database/Backups/Avance01Correccion03/` y la etiqueta
`avance-01-correccion-03`. No se modifican respaldos ni etiquetas anteriores.

## Limitaciones

- SQL directo puede insertar huérfanos o duplicados en tablas sin llave.
- No hay autenticación ni autorización.
- El cambio de identificación no está implementado.
