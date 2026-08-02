# Decisiones del modelo de Productor

## DEC-001 — Participante y roles

Los datos comunes de identificación y contacto se almacenarán en
`tbparticipante`. Productor y comprador se representarán mediante roles
asignados en `tbparticipanterol`.

El CRUD presentado al usuario se denominará CRUD de Productor, pero registrará
un participante con el rol `PRODUCTOR`.

Esta decisión evita duplicar a una persona que puede comprar y vender. Si más
adelante productor necesita atributos propios, una especialización
`tbproductor` podrá usar `tbparticipanteId` como PK/FK sin volver a copiar nombre,
identificación, teléfono o correo.

## DEC-002 — Direcciones

La estructura permitirá varias direcciones por participante mediante
`tbparticipantedireccion`.

Para este avance, todo participante activo deberá tener exactamente una
dirección principal activa. La interfaz inicial permitirá registrar únicamente
esa dirección principal. La regla se valida dentro de la transacción y se
refuerza mediante una columna generada con índice único que solo toma valor para
la fila principal activa.

La dirección del participante no se utilizará como sustituto de la ubicación de
la finca para futuros cálculos de transporte.

## DEC-003 — Correo de contacto

Cada participante tendrá un correo principal de contacto. El correo no será
único entre participantes porque varias personas o empresas pueden compartir
un mismo contacto administrativo.

El correo no se utilizará como credencial en este avance. Cuando se implemente
autenticación, el correo de acceso se almacenará en `tbusuario` y será único
dentro de esa entidad.

## DEC-004 — Identificación

La identificación se almacenará como texto y conservará letras, guiones y ceros
iniciales cuando correspondan.

La combinación del tipo de identificación y el número normalizado será única.
La identificación seguirá reservada aunque el participante esté inactivo. Un
participante que regrese deberá reactivarse y no registrarse nuevamente.

La normalización es conservadora: se validan los caracteres permitidos según el
tipo, se eliminan únicamente espacios y guiones del valor comparado y se pasan
letras a mayúsculas. El valor visible original se conserva. No se convierte el
documento a entero ni se eliminan indiscriminadamente caracteres no numéricos.

## DEC-005 — Desactivación

La eliminación del participante será lógica. El registro, sus relaciones y su
historial permanecerán en la base de datos.

Un participante inactivo no podrá intervenir en nuevas operaciones, pero podrá
consultarse históricamente y reactivarse. La desactivación de este avance afecta
a la persona completa, no solamente al rol `PRODUCTOR`.

## DEC-006 — Productor y finca

La asociación entre productor y finca se manejará mediante
`tbproductorfinca`. La estructura permitirá varias fincas por productor y
varios productores asociados a una finca.

Mientras no se confirme la naturaleza jurídica de la relación, la aplicación
la denominará asociación y no propiedad. La API recibe exclusivamente
`fincaId` de filas existentes y activas; el CRUD de Productor no crea fincas ni
acepta nombres como sustituto del identificador.

## DEC-007 — Bitácora sin autenticación

Mientras no exista autenticación, la bitácora registrará el actor como
`NO_AUTENTICADO` y dejará `tbusuarioId` en `NULL`.

Se registrarán la operación, fecha, entidad, identificador afectado, datos
anteriores, datos nuevos, origen técnico y un identificador de solicitud. No se
creará un usuario ficticio.

`tbbitacoraEntidad` y `tbbitacoraRegistroId` forman una referencia lógica
polimórfica: pueden describir registros de distintas entidades, por lo que no
existe una FK física única desde `tbbitacoraRegistroId`. En este avance el
modelo registra `PARTICIPANTE`. La integridad de esa referencia la coordina el
controlador dentro de la misma transacción que modifica el dominio. El DER
muestra la relación con participante como relación lógica y la anota como tal.

## DEC-008 — Respaldo versionado por entrega

Cada entrega oficial conservará un respaldo completo, un respaldo de estructura
y un respaldo de datos en `Database/Backups/AvanceNN/`.

El paquete incluirá un manifiesto, sumas SHA-256 y evidencia de restauración en
una base temporal. El manifiesto identificará el commit candidato cuya lógica
produjo el estado respaldado y la entrega se cerrará con una etiqueta Git
`avance-NN`.

Un archivo SQL que no haya sido restaurado y verificado no se considerará un
respaldo comprobado. Las entregas previas no se sobrescriben.

## Aspectos no confirmados

Estos puntos permanecen abiertos y no deben resolverse mediante supuestos:

1. Atributos exclusivos del rol comprador.
2. Naturaleza jurídica o comercial de la asociación productor-finca: propiedad,
   administración, uso o autorización.
3. Dirección o ubicación que servirá como origen para transporte.
4. Permisos de cada rol cuando exista autenticación.
5. Atributos adicionales de finca, como matrícula, plano, área, capacidad,
   ubicación o responsable.

