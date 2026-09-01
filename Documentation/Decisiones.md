# Decisiones - Corrección 04

## DEC-PER-001 - Persona única y capacidades independientes

Esta decisión sustituye cualquier descripción posterior que trate Productor,
Comprador y Transportista como identidades distintas. `tbpersona` concentra
identificación, tipo, nombre, teléfono, correo y estado global. La existencia
de una fila en `tbproductor`, `tbcomprador` o `tbtransportista` representa una
capacidad concreta. No se crean roles, catálogos, ENUM ni columnas de tipo de
rol.

Los IDs históricos de los tres perfiles y sus relaciones se conservan. Cada
perfil contiene `tbpersonaid` y su propio estado de participación. El estado
efectivo requiere persona y perfil activos.

## DEC-PER-002 - Conflictos y escritura compartida

Al crear una capacidad, PHP crea la persona o la reutiliza por identificación.
Devuelve 409 si la capacidad ya existe o si los datos personales recibidos no
coinciden, sin sobrescribir ni escoger datos automáticamente. Actualizar los
datos personales desde cualquier capacidad modifica `tbpersona` y se refleja
en las demás. PHP aplica unicidad, IDs manuales y coherencia mediante
transacciones, sentencias preparadas y bloqueos nombrados.

## DEC-PER-003 - Desactivación lógica

`DELETE` desactiva exclusivamente el perfil y nunca ejecuta `DELETE FROM`.
`PATCH` reactiva exclusivamente el perfil. Una persona globalmente inactiva no
puede operar ni reactivar capacidades por esos endpoints.

## DEC-PER-004 - Migración atómica y espejo PostgreSQL

La migración detecta primero identificaciones duplicadas y datos personales
incompatibles. Ante un conflicto aborta antes de retirar columnas. Si no hay
conflictos, crea y enlaza `tbpersona`, verifica conteos, IDs, relaciones y
huérfanos, y solo después elimina las columnas duplicadas. La misma
transformación existe para MySQL y Supabase/PostgreSQL. La migración remota no
se ejecuta ni se activa por push sin snapshot confirmado y autorización
expresa.

## DEC-PER-005 - Quince tablas sin objetos de integridad

El modelo final tiene exactamente 15 tablas y mantiene cero PK, FK, UNIQUE,
CHECK, índices, ENUM, defaults, triggers y objetos programables.

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

La identificación vive ahora en `tbpersonaidentificacionnumero`; no es PK y la
aplicación no permite cambiarla.
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

## DEC-TRAMO-7 - Retiro frontend de Comprador

En el tramo 7 se retira Comprador del frontend; Productor, Transportista,
Vehículo y Métodos de pago se mantienen como paneles activos, no se toca el
contrato `estado` y el retiro respeta el alcance secuencial del remodelado.

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
mediante migraciones versionadas: 15 tablas, `tbpersona` como identidad única,
`tbproductordireccion` normalizada y el mismo criterio de cero llaves,
restricciones, índices y valores automáticos. El espejo sigue a MySQL; nunca al
revés. Aplicar el cambio remoto requiere snapshot y autorización expresa.

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

# Decisiones - Modelos de históricos

## DEC-18 - Periodos con fechafin NULL como vigente; cierre inmutable

El estado y la residencia del productor se modelan como hechos históricos
(plan §7-8). `ProductorEstadoPeriodo` escribe en `tbproductorestadoperiodo` y
`ProductorDireccion` trabaja sobre `tbproductordireccion` con sus columnas de
vigencia: **el periodo vigente es la fila con fechafin NULL**. Un cambio cierra
el periodo abierto (UPDATE solo de fechafin, asignada por el reloj de PHP) e
inserta una fila nueva; ningún periodo cerrado se edita ni elimina. El motor no
puede garantizar "máximo un abierto por productor" (cero restricciones): PHP lo
garantiza ejecutando abrir/cerrar bajo el bloqueo nombrado por productor
(`tindercows_productor_estado_{id}`) dentro de la transacción completa, y los
métodos de escritura rechazan llamadas sin ese lock (`LogicException`). La
consulta `consultarVigenteEn(fecha)` resuelve el periodo cuya vigencia contiene
la fecha. La política anterior de "exactamente una dirección por productor"
se reescribe sobre el periodo abierto: tener varias direcciones históricas es
lo normal, y dos periodos abiertos simultáneos se detectan como integridad
rota.

# Decisiones - Códigos HTTP de ubicación

## DEC-17 - Consistencia de códigos con el resto de la API

Las validaciones de `/api/productores-ubicacion.php` responden **422**
(contenido no procesable) en lugar de 400: coordenadas fuera de rango o no
numéricas, precisión negativa o no numérica, origen fuera de
`{NAVEGADOR, MANUAL}`, campos desconocidos y rango de fechas inválido. Es el
mismo criterio que ya aplicaban `ProductorController` (422 en sus trece
validaciones) y `CompradorController` (422 en nueve). Se mantienen: **404**
productor inexistente, **409** productor inactivo, **405** métodos
destructivos sobre la tabla append-only y 400/415 del contrato de transporte
(JSON malformado / Content-Type incorrecto). El alta válida responde **201**
porque crea una fila nueva, igual que POST de productor.

# Decisiones - Estado como periodos

## DEC-19 - El estado es un hecho histórico; la columna muerta se retira en el mismo PR

`tbproductorestado` se retira de `tbproductor` (MySQL y espejo Supabase) en el
mismo PR que cambia `ProductorController` para derivar el estado del periodo
abierto, de modo que nadie pueda volver a usar la columna eliminada. El
esquema MySQL (`000instalacioncompleta.sql`), la migración
`004eliminaestadoproductor.sql` (con backfill y comprobación previa) y el
espejo PostgreSQL (`schema.sql` + `migrate.php` v5) quedan sincronizados. La
semilla `103exampleproductores.sql` crea periodos iniciales ACTIVO para los
productores ficticios en lugar de escribir la columna muerta. Un productor sin
periodos (solo puede ocurrir con datos heredados pre-migración) se considera
**INACTIVO** por defecto: no hay evidencia de que esté activo. El orden de
locks en `desactivar`/`reactivar` es: bloqueo de fila FOR UPDATE del
productor → lock nombrado del periodo por productor; idempotente: desactivar
dos veces seguidas no duplica periodos.

## DEC-20 - Periodos de estado sobre persona con capacidades

Al integrar este tramo con la unificación de personas (DEC de `tbpersona`),
la identidad y el contacto del productor vienen de `tbpersona` y su estado
del periodo abierto: son dos ejes independientes. Un productor cuenta como
**ACTIVO** solo si su periodo abierto está en 1 **y** `tbpersonaestado` es 1,
porque una identidad inactiva desactiva todas sus capacidades. El modelo
expone el estado derivado con el alias `tbproductorestado` en cada consulta,
de modo que `FincaController`, `ProductorUbicacionController` y
`ProductorController` siguen leyendo el estado por el mismo nombre sin
depender de una columna que ya no existe. La migración MySQL se renumeró a
`004` porque `003` lo ocupa `003personacapacidades.sql`, y debe ejecutarse
después de ella: primero se unifica la persona, luego se retira la columna.

# Decisiones - Respaldo previo al remodelado

## DEC-21 - Respaldo previo al remodelado (tramo 1)

El tramo 2 se ejecutó antes que el tramo 1 porque solo agregaba tablas y no
arriesgaba datos existentes; el tramo 6, que sí escribe sobre datos
existentes, no podía arrancar sin un punto de reversión. Por eso se generó
`Database/Backups/Avance02/`, etiquetado explícitamente como "previo al
remodelado por tramos (EIF400)" en su `MANIFEST.md`, con
`Tools/backup-database.sh Avance02` y verificado con `Tools/test-restore.sh
Avance02`: quince tablas, cero PK/FK/CHECK/índices/AUTO_INCREMENT y
restauración completa y por partes idénticas al origen (`APROBADO`). Los ocho
respaldos anteriores (`Avance01` hasta `LineaBase`) se conservan intactos, tal
como exige el plan.

# Decisiones - Migrar lo que ya existe (tramo 6)

## DEC-22 - Backfill de fecha de inicio en dirección histórica, y conteo de comprador

`tbproductordireccionfechainicio` quedó nullable en
`Migrations/002historicoproductor.sql` para no romper el `ALTER TABLE` sobre
filas ya existentes; a esas filas heredadas nunca nadie les asignó una fecha
de inicio porque no existía el concepto antes del tramo 2. La semilla
`103exampleproductores.sql` insertaba `tbproductordireccion` sin esa columna
y reproducía el mismo hueco en cada instalación limpia; se corrigió para
fijar `NOW()` igual que ya hacía el periodo de estado.

Para bases con datos heredados de antes del tramo 2,
`Migrations/005backfilldireccionfechainicio.sql` asigna una única marca de
tiempo (capturada una sola vez con `SET @fechaMigracion := NOW()`) a toda fila
con `tbproductordireccionfechainicio IS NULL`; es la frase que hay que poder
decir en la defensa: **"el histórico confiable empieza en la fecha de
migración; los datos anteriores no existían en el modelo previo"**. Es
idempotente (solo toca filas con la columna en `NULL`) y no inventa fechas
distintas por productor porque no hay evidencia de cuándo empezó cada una.

`Database/Tests/diagnostico.sql` se actualizó porque su D-01 verificaba una
política ya reemplazada por DEC-18 (una sola dirección por productor): con
histórico, tener varias filas cerradas es normal. D-01 ahora detecta
productores con más de un periodo de dirección **abierto**, D-01b detecta
productores sin ninguno abierto, y D-01c detecta periodos abiertos sin fecha
de inicio (el síntoma exacto que corrige esta migración). Se agregaron
también D-10 y D-11, equivalentes para `tbproductorestadoperiodo`, que no
tenía ninguna consulta de diagnóstico propia.

Conteo de `tbcomprador` en la instalación limpia de referencia: **0 filas**
(sin datos heredados en este entorno). No es criterio para decidir si se
borra la tabla — DEC-TRAMO-7 ya la conserva — sirve para confirmar que la
corrección del tramo 7 no perdió compradores existentes. Si el equipo tiene
una base de desarrollo compartida con compradores reales cargados, debe
correrse `SELECT COUNT(*) FROM tbcomprador;` ahí antes de cerrar el tramo con
el equipo, porque este conteo es local.

Verificado: instalación limpia (`docker compose down -v && up`) levanta sin
errores, `Tests/naming_gate.php` pasa, `Database/Tests/diagnostico.sql`
devuelve cero filas en todas las consultas, y la suite PHP relevante
(`schema_test`, `direccion_test`, `address_policy_test`,
`productor_estado_periodo_test`, `productor_estado_flujo_test`,
`comprador_test`, `productor_ubicacion_test`, `finca_direccion_test`) pasa
completa. Ningún productor quedó sin periodo de dirección o estado abierto,
ninguno con dos a la vez.

# Decisiones - Esquema histórico transversal (tramo 13)

## DEC-23 - La matriz del tramo 12 confirma el esquema existente; no se crean tablas nuevas

El tramo 13 recibe del arquitecto `matriz-historica-tramo12.pdf` (resumen en
Decisiones.md del arquitecto) y su condición de entrega es "cada
entidad/perfil confirmado tiene un mecanismo histórico coherente, sin
histórico genérico que mezcle semánticas". Contrastando la matriz contra
`Database/SqlScripts/000instalacioncompleta.sql` no aparece ninguna tabla ni
columna faltante:

1. **Productor - Estado**: `tbproductorestadoperiodo` (creada en el tramo 2,
   poblada en el tramo 4/6) ya modela apertura/cierre de periodo con
   `fechainicio`/`fechafin` de servidor, exactamente como pide la matriz.
2. **Productor - Ubicación**: `tbproductorubicacion` (tramo 2/8/9) ya es
   append-only con `latitud`, `longitud`, `precision`, `origen` y fecha de
   servidor (DEC-16), igual que la matriz.
3. **Productor - Actividad**: `tbproductoractividad` (tramo 2) ya tiene
   `tipo`, `fecha` de servidor y `origen`. Se documenta ahora en
   `Documentation/DiccionarioDatos.md` el catálogo cerrado de
   `tbproductoractividadtipo` que la matriz exige (`login`,
   `actualizacion_ubicacion`, `actualizacion_perfil`,
   `registro_actividad_productiva`, `contacto_comprador`); validarlo es
   trabajo de PHP (tramo 15), fuera del alcance de esta base.
4. **Comprador - Perfil**: la matriz marca explícitamente el histórico
   propio de comprador como "fuera del alcance profundo de esta fase".
   `tbcompradorestado` (DEC-C04-008/DEC-PER-003) sigue siendo su único
   registro de alta/baja lógica; no se crea tabla de periodos para
   comprador.
5. **Persona - Vínculo productor/comprador**: la matriz confirma
   explícitamente que no lleva tabla histórica propia, para no repetir el
   error de tratar dos capacidades simultáneas como un solo estado; se
   deriva de la existencia de filas en `tbproductor` y `tbcomprador`.

`tbbitacora` se mantiene exclusivamente como auditoría técnica (DEC-C04-006)
y no se convierte en histórico universal, como exige la regla de base del
tramo 13.

Como no se crean tablas, `Database/Tests/comprobacionestructura.sql` sigue
esperando exactamente 15 tablas y `Tests/naming_gate.php` sigue exigiendo
las mismas tablas que ya validaba (incluida `tbproductoractividad` desde el
tramo 2). Lo que sí cambia:

- `Database/Tests/diagnostico.sql` gana **D-12** (actividad con
  `tbproductoractividadtipo` fuera del catálogo cerrado) y **D-13**
  (`tbcompradorestado` fuera de `{0,1}`), y el bloque **D-07** de huérfanos
  gana el enlace `tbproductoractividad.tbproductorid`.
- `Documentation/DiccionarioDatos.md` documenta el catálogo cerrado de
  actividad y por qué comprador y el vínculo persona-productor/comprador no
  tienen tabla nueva.

Verificado: instalación limpia (`docker compose down -v && up`) levanta sin
errores, `Tests/naming_gate.php` pasa, `Database/Tests/diagnostico.sql`
devuelve cero filas en D-00 a D-13 sobre datos válidos (conteo de tablas
antes/después del tramo: 15/15, sin cambio), y ningún productor ni
comprador queda con dos estados actuales incompatibles.
## DEC-21 - Fin del UPDATE destructivo de dirección

`ProductorDireccion::actualizar()` deja de hacer `UPDATE` sobre la fila vigente de `tbdireccion`. Un cambio de
residencia ahora ejecuta **cierre + alta** transaccional bajo el bloqueo nombrado **por productor**
(`tindercows_productor_direccion_{productorId}`), que a su vez delega al lock global de alta de dirección
(requisito de `Direccion::crearConBloqueoExistente`). El flujo cierra el periodo abierto (solo `fechafin` = reloj
de PHP), inserta una `tbdireccion` nueva y su enlace con `fechainicio`, y registra **CAMBIO_DIRECCION** en la
bitácora dentro de la misma transacción. El lock se libera SIEMPRE en `finally`, incluso ante excepción, y
cualquier fallo entre el cierre y el INSERT provoca `ROLLBACK` que deja el estado original. La primera residencia
permanece consultable para siempre mediante `consultarVigenteEn(fecha)`; nunca hay más de un periodo abierto.
`obtenerDireccionAbiertaId()` y la rama de `UPDATE` se eliminan.

**Fincas:** `tbfincadireccion` no tiene columnas de vigencia. No se inventan columnas ni se hace histórico de
dirección de finca por ahora: se pide a DBA; las direcciones de finca quedan fuera del histórico de residencia.

## DEC-23 - Estado e histórico de dirección son operaciones atómicas y reutilizables

La transición del estado operativo del productor (desactivar/reactivar) y el cierre+alta de residencia dejan de
vivir dentro del controlador y pasan a `Application/Service/`:

- `ProductorEstadoService::transicionar()` cierra el periodo abierto y abre el nuevo bajo el lock nombrado por
  productor, dentro de la transacción que posee el llamador. Es **idempotente**: transicionar al mismo estado
  vigente es un no-op que NO abre un periodo duplicado. Registra `DESACTIVAR`/`REACTIVAR` en la bitácora solo
  cuando hubo una transición real.
- `ProductorDireccionService::cambiar()`/`vaciar()` orquestan cierre+alta y registran
  `CAMBIO_DIRECCION`/`VACIAR_DIRECCION` bajo el lock por productor.

El controlador conserva el contrato sobre JSON (códigos de estado, mensajes y respuestas) y la resolución de
bloqueos 409, pero ya no duplica la máquina de estados ni el histórico de residencia. Si una de las escrituras
falla a mitad, el COMMIT/ROLLBACK exterior deja o todas o ninguna.

## DEC-25 - Contrato de validación unificado reutilizable

La validación de productor (identificación, teléfono, correo, dirección y fincas) se extrae del controlador a
`Application/Service/ProductorValidacionService`, ejecutable sin el controlador gigante. Mantiene el mismo
contrato de errores por campo que consume toda la API (HTTP 422 + `errors`). El controlador delega y traduce
`ProductorValidacionException` a `ProductorHttpException` para no cambiar la respuesta hacia el frontend.
`ProductorController` deja de reimplementar `TIPOS_IDENTIFICACION`, `validarIdentificacion`, `validarDireccion`,
`textoCampo`, `rechazarCamposDesconocidos`, etc.; la única fuente es el servicio.
