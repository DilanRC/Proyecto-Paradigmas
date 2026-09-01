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

## DEC-FRONT-10 - Bloqueo detectado: el total no coincide con las filas listadas

**Necesidad.** Al verificar en navegador, el panel de productores muestra
"5 productores encontrados" pero pinta 3 filas. El frontend no puede corregirlo
sin tocar backend, que esta fuera de alcance.

**Mecanismo del defecto.** En `Application/Model/Productor.php`, `listar()` cuenta
y lista con criterios distintos:

- el `COUNT(*)` une solamente `tbproductor` con `tbpersona`, de modo que cuenta
  los 5 productores;
- la consulta de filas anade `INNER JOIN tbproductordireccion`, asi que descarta
  a los productores sin enlace de direccion.

Comprobado contra la base: 5 productores, 3 enlaces de direccion, 3 filas
devueltas por la API con `total: 5`.

**Consecuencia.** El total y la paginacion se calculan sobre una poblacion mayor
que la que se puede mostrar. Con mas registros aparecerian paginas que se ven
vacias aunque el total prometa resultados.

**Por que no se corrige aqui.** La correccion pertenece al modelo: o el `COUNT`
aplica el mismo `INNER JOIN`, o el listado usa `LEFT JOIN` y tolera productores
sin direccion. Ambas son decisiones de backend con efectos sobre el contrato, y
este bloque es exclusivamente frontend.

**Limite.** El frontend representa fielmente lo que la API devuelve: pinta las
filas de `data.productores` y el total de `data.total`. La incoherencia es del
contrato, no de la vista.

**Nota adicional.** El mismo `INNER JOIN` no filtra por periodo abierto
(`tbproductordireccionfechafin IS NULL`), de modo que un productor con historico
de direcciones podria aparecer repetido una vez por periodo. No se reprodujo con
los datos actuales, pero conviene revisarlo junto con lo anterior.

## DEC-FRONT-11 - Catalogo territorial: distritos oficiales, localidades bloqueadas

**Necesidad.** Los formularios de direccion pedian provincia, canton, distrito y
pueblo como cuatro campos de texto libre e independientes. Nada impedia escribir
un canton en la provincia equivocada, y cada usuario tecleaba el mismo nombre de
otra forma ("Perez Zeledon", "Pérez Zeledón"), lo que rompe cualquier filtro o
agrupacion posterior.

**Fuente.** Instituto Geografico Nacional / Registro Nacional, *Division
Territorial Administrativa, 2026* (DTA 2026), tabla por provincias, cantones y
distritos. Consultada el 2026-08-31. `Public/js/shared/territorio.js` se genero
desde ese PDF con un script; no se escribio a mano ni desde la memoria del
modelo, y no se uso Wikipedia, Google Maps ni datasets comunitarios.

**Reconciliacion de 493 frente a 494.** La tabla de distritos del PDF contiene
493 filas, una menos que las 494 que declara su propia portada. La diferencia no
es del parseo: la extraccion no produjo una sola anomalia. El distrito ausente es
`70605` (Guacimo, Limon), cuyo nombre, **Duacari**, aparece en el archivo *Centros
Poblados y Localidades 2026* del mismo IGN, que le atribuye nueve localidades. Se
tomo de ahi. Los otros tres huecos de la numeracion son legitimos: Rio Cuarto
(`20306`), Monteverde (`60109`) y Puerto Jimenez (`60702`) dejaron de ser
distritos al convertirse en canton.

**Bloqueo superado en DEC-FRONT-13.** Lo que sigue describe por que no se
cargaron desde el PDF; las localidades entraron despues desde el XLSX oficial.

**Bloqueo original: las localidades no se cargan.** El archivo *Centros Poblados y
Localidades 2026* no se puede extraer con fidelidad. Su fuente incrustada pierde
la secuencia `nd` al convertir a texto, con dos extractores independientes
(`pdftotext` y `pdftohtml`): "Condominio" sale "Coominio" en 983 filas e
"Indigena" sale "Iigena" en 892. La perdida alcanza a los nombres propios, no
solo a la tipologia: "Grande", "Segundo" y "Redonda" aparecen 8, 2 y 4 veces en la
DTA y **cero** veces en las 7465 filas extraidas de localidades. Cargar esos
nombres habria metido errores silenciosos en la base de direcciones, asi que no
se cargo ninguno.

**Consecuencia en el formulario.** Provincia y canton son `<select>` encadenados:
elegir provincia repuebla los cantones. Distrito es `<input list>` con `<datalist>`
de los 494 valores oficiales, no un `<select>`: sugiere sin rechazar, de modo que
una direccion ya guardada fuera del catalogo sobrevive a una edicion. Pueblo
queda como texto libre mientras su lista este vacia.

**Costo.** El catalogo pesa unos 20 KB y hay que regenerarlo cuando el IGN
publique una DTA nueva.

**Riesgo.** Que el archivo quede desactualizado tras una reforma territorial. Lo
acota la prueba de integridad `ningun nombre de distrito perdio caracteres en la
extraccion`: si alguien regenera el catalogo con un extractor defectuoso, el gate
falla en vez de aceptar nombres mutilados.

**Alternativa descartada.** Completar las localidades reinsertando la secuencia
perdida. No es reconstruible: no hay forma de saber donde iba el `nd` sin la
lista correcta, que es justamente lo que falta.

**Limite.** Solo catalogo de frontend. No se creo tabla, endpoint ni validacion
de servidor; el backend sigue aceptando las direcciones como texto.

## DEC-FRONT-12 - Compradores vuelve al frontend y las capacidades se hacen visibles

**Necesidad.** DEC-PER-001 fija que una persona no es un rol: `tbpersona`
concentra a la persona y la existencia de una fila en `tbproductor`,
`tbcomprador` o `tbtransportista` representa una capacidad. Esa lectura no era
navegable. Comprador no tenia interfaz desde el tramo 7, y los tres paneles que
si existian presentaban a cada capacidad como si fuera una identidad separada:
nada indicaba que una misma persona pudiera ser dos cosas, ni ninguna.

**Sustituye a DEC-TRAMO-7.** Aquel retiro fue de secuencia, no de defecto: "el
retiro respeta el alcance secuencial del remodelado". El backend nunca se
retiro. `CompradorController` conserva el CRUD completo y `Public/api/compradores.php`
siguio respondiendo todo este tiempo, de modo que recuperar la interfaz no toco
base de datos, modelo ni controlador.

**Mecanismo.** Se reconstruyo el panel en la arquitectura vigente, no se
revirtio el commit: modulos compartidos, CSS por capas, validacion por campo,
notificaciones, vacio distinto de error y reintento. El cuerpo que envia es el
mismo que el de transportista, y una prueba compara ambos porque las dos
capacidades son persona mas contacto.

`Public/js/shared/capacidades.js` responde "que es esta persona" sin backend
nuevo: el contrato ya lo permitia, porque `GET ?identificacionNumero=X` devuelve
200 con los datos si la capacidad existe y 404 si no existe. Las tres consultas
van en paralelo.

**Tres situaciones, no dos.** Una capacidad puede estar registrada, no
registrada, o no haberse podido comprobar. Un 404 es una afirmacion del
servidor; un fallo de red no afirma nada. Mostrar "No registrado" ante un corte
de red seria declarar falso algo que no se verifico, el mismo defecto que separa
"lista vacia" de "fallo al cargar". Comprobado en navegador: con la red caida
las tres capacidades dicen "No se pudo comprobar" y ninguna dice "No registrado".

**Vendedor.** No existe `tbvendedor` ni mencion alguna en el repositorio. En un
mercado ganadero quien vende es el productor, que posee las fincas y el ganado,
asi que la capacidad se rotula "Productor (vendedor)". No se creo tabla ni
endpoint: habria exigido tocar base de datos, modelo y controlador.

**Consecuencia.** Desde la ficha de un comprador se ve si esa misma persona es
productor o transportista, y se salta a su panel con `?q=<identificacion>`.
Verificado con Maria Fernandez Solano, productora activa: al registrarla como
compradora el backend reutilizo la persona y la ficha muestra las dos
capacidades activas y transportista no registrado.

**Costo.** Tres peticiones adicionales al abrir una ficha. Se abortan al cerrar
el detalle para que la respuesta lenta de una ficha no pinte sus capacidades
sobre la siguiente.

**Riesgo.** Que el enlace "Abrir panel" apunte a un panel que ignore `?q=`: la
visita llegaria y mostraria la lista sin filtrar, pareciendo que funciona. Lo
cubre el gate `enlace_profundo_*`, comprobado quitando la lectura en
transportistas y verificando que falla.

**Alternativa descartada.** Revertir el commit del retiro. Habria devuelto el
panel escrito contra la arquitectura anterior, sin modulos compartidos ni
distincion de vacio y error, y habria que rehacerlo entero.

**Limite.** Solo frontend. No se creo tabla de roles, ni endpoint que devuelva
las capacidades de una persona en una sola llamada; la ficha las compone desde
los tres endpoints que ya existian.

## DEC-FRONT-13 - Localidades oficiales y busqueda que compensa un defecto de la fuente

**Necesidad.** DEC-FRONT-11 dejo el catalogo a medias: 494 distritos cargados y
cero localidades, porque el PDF de Centros Poblados no se podia extraer. El
distrito quedo como texto libre y el pueblo sin ninguna ayuda, de modo que dos
personas escribian el mismo lugar de dos formas distintas.

**Fuente.** Instituto Geografico Nacional / Registro Nacional / SNIT, *Centros
Poblados y Localidades (2026).xlsx*. Se descargo el archivo y se verifico su
SHA-256 contra el publicado: `ab4b1bf9a753e49c423398f6f746edad577ac8121bec76f2648e14281b0ca6cf`.
Coincide. Se parseo el XLSX directamente, no su version en PDF. Consulta:
2026-08-31. Resultado: 13309 etiquetas utiles en 493 distritos; uno no tiene
ninguna localidad publicada.

**El defecto esta en el archivo oficial, no en la extraccion.** El XLSX pierde
la secuencia `nd` en sus cadenas de texto: cero de sus 8575 cadenas la
contienen, y trae `Tamario` por Tamarindo, `Llano Grae` por Llano Grande,
`Coominio` por Condominio e `Iigena` por Indigena. Se comprobo que no es un
problema del lector: la tabla DTA del mismo IGN si conserva las 16 apariciones
de `nd` en nombres de distrito. La prueba mas clara esta dentro del propio
dato: el distrito `10108 Mata Redonda` de la DTA tiene como cabecera, en el
XLSX, la localidad `Mata Redoa`. Afecta a cerca del 4% de los nombres.

**Que se hizo.** Tres cosas, en orden de solidez:

1. Los 70 registros cuyo nombre coincide con la forma mutilada de un toponimo
   que la DTA si trae limpio se corrigieron a la forma de la DTA. Es
   demostrable, no conjetura: `Mata Redoa` en el distrito 10108 solo puede ser
   el `Mata Redonda` que la DTA declara para ese mismo distrito.
2. Los demas se conservan tal como los publica la fuente. No se adivina donde
   iba un `nd` que la fuente ya perdio, ni se completa parcialmente.
3. La busqueda compensa el defecto en vez de taparlo. `normalizar` aplica a lo
   que teclea el usuario la misma perdida que sufrio la fuente, ademas de
   quitar acentos y mayusculas. Como se aplica a los dos lados de la
   comparacion el resultado es coherente, y quien escribe "Tamarindo"
   correctamente encuentra el registro guardado como "Tamario".

**Consecuencia en el formulario.** Provincia, canton y distrito son `<select>`
encadenados: la DTA es un catalogo cerrado y no tiene sentido teclearla. Pueblo
sigue siendo texto libre con sugerencias, porque no es una unidad administrativa
sino lenguaje comun y el catalogo no pretende ser exhaustivo; se escriben las
primeras letras y aparecen las localidades de ese distrito. Cada campo se
habilita al elegir el anterior.

**Costo.** Las 13309 localidades pesan 200 KB frente a las 22 KB de la DTA. No
se cargan con la pagina: `direccion.js` las pide con `import()` la primera vez
que se elige un distrito, y una sola vez por pagina. Comprobado en navegador:
`poblados.js` no aparece entre los recursos descargados hasta ese momento.

**Riesgo.** Que alguien regenere el catalogo con un extractor o una fuente
distinta y reintroduzca nombres mutilados. Lo acota la prueba que exige
`Mata Redonda`, `Rio Segundo`, `San Andres`, `Rancho Redondo` y `Llano Grande`
entre los distritos, y la que exige que los 70 nombres reparados sigan escritos
bien.

**Alternativa descartada.** Reconstruir los nombres afectados insertando la
secuencia perdida. No es reconstruible: sin la lista correcta no hay forma de
saber donde iba el `nd`, y esa lista es justamente lo que falta.
`Tamario` podria ser Tamarindo, pero `Caelaria` solo se resuelve porque la DTA
lo dice, no porque se pueda deducir.

**Limite.** Solo catalogo de frontend. No se creo tabla, endpoint ni validacion
de servidor; el backend sigue recibiendo la direccion como texto, con el mismo
cuerpo que antes. La correccion de nombres vive en el catalogo generado, no en
los datos ya guardados en la base.
