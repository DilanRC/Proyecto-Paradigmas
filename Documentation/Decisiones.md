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

# Decisiones - Frontend del Avance II

Namespace propio `DEC-FRONT-*`. La rama `DBA` ya ocupa `DEC-21` y `DEC-22`, de
modo que numerar correlativo provocaria un conflicto de fusion. Todo este bloque
es frontend, pruebas y documentacion: no toca `Database/`, `Application/Model/`,
`Application/Controller/`, migraciones, semillas ni el algoritmo de recomendacion.

## DEC-FRONT-01 - CSS en capas y purga por selector

**Necesidad.** `styles.css` concentraba 25 KB en un archivo con una linea de 3421
caracteres que mezclaba panel, tabla, badges y modales. Cualquier cambio obligaba
a leer todo y arriesgaba tocar reglas ajenas.

**Mecanismo.** Cinco capas con orden de cascada explicito: `tokens`, `base`,
`components`, `panel`, `red-ganadera`. La division se hizo con un script que
compara el conjunto de selectores antes y despues: 230 originales, 215 emitidos,
15 muertos.

**Beneficio.** Cada archivo tiene una responsabilidad y el diff de un cambio de
componente ya no toca el sistema de paneles.

**Costo.** Cinco peticiones de hoja en lugar de una. Sobre HTTP/2 y en local es
irrelevante; se evito `@import`, que serializa las descargas.

**Riesgo.** Reordenar reglas puede alterar la cascada entre selectores de igual
especificidad. Se mitigo conservando el orden original dentro de cada capa y
ordenando las capas de lo generico a lo especifico.

**Limite.** El borrado de muertos es por selector, nunca por linea: `.brand` y
`.brand strong` estaban muertos pero compartian la linea 4 con `.brand__icon`,
que es el logo de las cinco vistas. Borrar la linea habria eliminado el logo.

**Alternativa descartada.** Dejar el archivo unico y solo formatearlo: resuelve la
legibilidad pero no la mezcla de responsabilidades ni permite razonar sobre la
cascada.

## DEC-FRONT-02 - Contraste verificado por calculo, no por criterio

**Necesidad.** Nueve combinaciones de texto no alcanzaban el minimo AA de 4.5:1.
Las peores eran la cabecera de tabla (2.52), el pie del sidebar (2.25) y la nota
al pie del panel (2.05). Todas usaban `rgba()` sobre un fondo de color, que es
precisamente lo que hunde el contraste.

**Mecanismo.** Tokens opacos con el ratio calculado y anotado. Se eligieron los
valores mas atenuados que aun cumplen (4.53-4.70:1) en lugar de subir todo a
opacidad total. `Tests/frontend_contrast_test.mjs` recalcula cada par componiendo
el alpha igual que el navegador y falla por debajo de 4.5.

**Beneficio.** La accesibilidad deja de depender del ojo de quien revisa. La
afirmacion es verificable y se defiende con un numero.

**Costo.** El texto atenuado es algo menos tenue que antes; la jerarquia visual
se conserva pero es mas suave.

**Riesgo.** El gate conoce los fondos compuestos porque estan escritos en el
propio test. Si alguien cambia un fondo en el CSS sin actualizar el test, el
calculo se hace contra un fondo que ya no existe.

**Limite.** Cubre texto. El contraste de bordes y de componentes no textuales
(WCAG 1.4.11) no esta medido.

**Alternativa descartada.** Un test que comprobara `css.includes('--color-muted')`.
Verifica que el token existe, no que se lee.

## DEC-FRONT-03 - Modulos ES con extension .js, sin tocar el servidor

**Necesidad.** Cuatro de los cinco archivos de `Public/js` repetian los mismos
diez ayudantes, y ninguno era importable: al ser IIFE sin exportaciones, no habia
forma de probar su comportamiento.

**Mecanismo.** `<script type="module">` y modulos en `Public/js/shared/`. La
extension es `.js` mas un `Public/js/package.json` de una linea con
`{"type":"module"}`: el navegador recibe `text/javascript`, que es el MIME
garantizado para `.js`, y Node trata esos archivos como ESM.

**Beneficio.** El mismo archivo se ejecuta en el navegador y se importa en las
pruebas. La correccion no depende de la configuracion del servidor.

**Costo.** Un archivo de 25 bytes servido publicamente en `/js/package.json`, con
contenido inerte.

**Riesgo.** Un navegador sin soporte de modulos deja el panel inerte, mientras
que antes `defer` degradaba a funcionando. El codigo ya usaba `?.`, `??=`,
`replaceChildren` y `dialog.showModal`, de modo que la linea base real ya era
moderna; esto solo la hace explicita.

**Limite.** No hay `nomodule` de reserva ni transpilacion.

**Alternativa descartada.** `.mjs` mas `AddType application/javascript .mjs` en
`docker/apache/000-default.conf`. Funciona, pero ata la correccion del frontend a
la configuracion de Apache y deja de funcionar bajo `php -S` o cualquier otro
host. Se prefirio la opcion cuya correccion no depende de nada externo.

## DEC-FRONT-04 - Fallo HTTP y fallo de transporte se distinguen por estructura

**Necesidad.** `request()` solo asignaba `status` cuando habia JSON. Al rechazarse
`fetch`, el texto crudo del navegador ("Failed to fetch") llegaba al usuario y
cualquier comprobacion tipo `error.status === 500` se evaluaba sobre un
`TypeError`.

**Mecanismo.** Dos formas distintas y no un campo suelto: `type:'http'` siempre
trae `status` numerico; `type:'network'` lo trae en `null`. Se anade `kind`
estable (`validation`, `not-found`, `conflict`, `server`, ...) y `retryable`.

**Beneficio.** La vista decide por categoria y no por numero. Reintentar solo se
ofrece cuando repetir puede cambiar el resultado: en 500 y en fallo de red, no en
422, 404 ni 409.

**Costo.** Una capa de traduccion entre `fetch` y el panel.

**Riesgo.** Si la taxonomia perdiera `data`, se romperia en silencio el flujo de
reactivacion, que lee `data.reactivacion.identificacionNumero` de un 409. Hay una
prueba dedicada a esa preservacion.

**Limite.** No hay reintento automatico ni retroceso exponencial: el reintento es
una accion explicita de la persona.

**Alternativa descartada.** Un unico campo `kind` sobre una forma comun. Distingue
en la lectura pero permite seguir escribiendo `error.status` sobre un fallo que no
tiene status.

## DEC-FRONT-05 - Una lista vacia solo puede afirmarse tras una respuesta correcta

**Necesidad.** El `catch` de los cuatro paneles llamaba a `render([], 0, size)`,
que encendia el estado vacio. Un 500 o una caida de red se presentaban como
"No se encontraron X": el sistema culpaba a la busqueda de un fallo propio.

**Mecanismo.** Una maquina de estados pura con la invariante
`showEmpty <=> phase === 'ready' && items.length === 0`, mas un estado de error
propio con mensaje real y boton Reintentar. Cancelar no cambia de fase.

**Beneficio.** El usuario distingue "no hay datos" de "no pudimos traerlos", que
son dos situaciones con acciones distintas.

**Costo.** Un estado mas que mantener en cada panel y su marcado.

**Riesgo.** El reintento debe cancelar lo que este en vuelo y subir la secuencia;
si no, una respuesta tardia pisaria a la reintentada.

**Limite.** La maquina no distingue "vacio por filtro" de "vacio sin registros":
ambos muestran el mismo mensaje.

**Alternativa descartada.** Mostrar solo un toast de error sobre la lista vacia.
Deja la pantalla afirmando algo falso cuando el toast desaparece.

## DEC-FRONT-06 - Dos regiones vivas permanentes para las notificaciones

**Necesidad.** El toast escribia el texto con el nodo en `hidden`, luego lo
mostraba y ademas alternaba `role` entre `status` y `alert` sobre el mismo nodo.
Un nodo `hidden` esta fuera del arbol de accesibilidad y cambiar el `role` en
caliente no reinicia la observacion: el anuncio no llegaba.

**Mecanismo.** Dos regiones siempre presentes y nunca ocultas con `hidden`: una
`role="status"` cortes y otra `role="alert"` asertiva. Se ocultan por `:empty`.
Los errores no se autodescartan; el resto si.

**Beneficio.** El mensaje se escribe sobre una region que el lector ya estaba
observando, y un error puede interrumpir sin convertir cada aviso en interrupcion.

**Costo.** Dos nodos por vista en lugar de uno.

**Riesgo.** Escribir en las dos regiones a la vez produciria un anuncio doble; el
toaster limpia ambas antes de escribir.

**Limite.** Los errores por campo no van al toast: viven junto a su control, como
exige el formulario.

**Alternativa descartada.** Un nodo unico con `role` dinamico, que es exactamente
el defecto corregido.

## DEC-FRONT-07 - Se extrae lo transversal; lo propio de un panel se queda

**Necesidad.** Diez ayudantes repetidos en cuatro archivos. Pero siete eran
identicos y seis divergian de verdad, y aplanar esas diferencias rompia la
aplicacion.

**Mecanismo.** Regla: un modulo compartido debe tener al menos dos consumidores
reales o una responsabilidad transversal aislable. Las divergencias se vuelven
parametros:

| ID | Divergencia | Tratamiento |
|---|---|---|
| D1 | banderas de ocupado distintas por panel | parametro `isBusy` |
| D2 | claves `fincas.N` solo en productores | parametro `collapsePrefixes` |
| D3 | productores tiene dos formularios | un enlace de errores por formulario |
| D4 | letra de reserva de las iniciales | se queda local |
| D5 | formateadores de dominio | se quedan locales |
| D6 | variable de registro pendiente | se queda local |

**Beneficio.** Un arreglo se aplica una vez y no cuatro, sin perder el
comportamiento particular de cada panel.

**Costo.** Una indireccion mas entre el panel y el DOM.

**Riesgo.** Aplanar D3 habria sido el error caro: el dialogo de direccion de finca
habria pintado sus errores sobre el formulario principal.

**Limite.** D4, D5 y D6 se dejan duplicados a proposito. Extraer una funcion de
una linea usada una sola vez por panel anade mas indireccion que la que elimina.

**Alternativa descartada.** Un ayudante universal con opciones para todo. Habria
sido peor que la duplicacion original y mas dificil de leer.

## DEC-FRONT-08 - Guardar no persiste: el bloqueo se documenta en vez de inventarse

**Necesidad.** La vista principal ofrece guardar y pasar, y se pedia un contador.

**Mecanismo.** Se busco la semantica en el repositorio antes de implementarla:
no hay endpoint, ni tabla, ni una sola mencion de guardar, favorito o interes en
`Documentation/`. Al no poder confirmar que sea estado meramente presentacional,
los contadores viven en memoria de la pestana y la vista lo dice en texto visible.

**Beneficio.** No se afirma una persistencia que el sistema no tiene. Si guardar
representa una preferencia real de la persona usuaria, almacenarla solo en un
navegador seria incorrecto y dificil de defender.

**Costo.** El contador se pierde al recargar.

**Riesgo.** Puede leerse como una funcion incompleta. Por eso el limite esta
escrito en la interfaz y no solo aqui.

**Limite.** Para que guardar signifique algo se necesita, como minimo: un actor
autenticado, una tabla de interes por persona y productor, y endpoints de alta y
baja. Nada de eso pertenece a este alcance.

**Alternativa descartada.** `localStorage`. Sobrevive a la recarga, pero convierte
una posible decision de negocio en un dato atrapado en un navegador, sin
sincronizacion ni trazabilidad.

## DEC-FRONT-09 - Los gates comprueban propiedades, no nombres de variables

**Necesidad.** `naming_gate.php` y `ui_test.js` exigian que `fetch(`,
`AbortController`, `listSequence` y `changingStatus` aparecieran literalmente
dentro de `productores.js`. Repartir ese codigo los rompia aunque el
comportamiento fuera identico: comprobaban el archivo, no la propiedad.

**Mecanismo.** Los gates resuelven el grafo de modulos (entrada mas
`shared/*.js`) y las aserciones pasan a describir la propiedad: cancelacion mas
descarte por secuencia para las carreras, guarda sincrona para el doble envio, y
que vacio y error sean distinguibles. Ademas, cada panel exporta su constructor de
payload y se compara contra un cuerpo de referencia.

**Beneficio.** El mayor riesgo de esta refactorizacion no era visual sino que la
interfaz se viera bien y el backend recibiera otra cosa. La paridad de payload lo
cubre por panel.

**Costo.** Los gates son algo mas largos y hay que mantener los payloads de
referencia.

**Riesgo.** Un payload de referencia equivocado fijaria el error en vez del
contrato. Se escribieron leyendo el codigo anterior, no el nuevo.

**Limite.** `markFirstInvalid` depende del selector `:invalid`, que ningun sustituto
de DOM puede honrar con honestidad. Se separo la parte pura, que si se prueba, y
la linea que consulta al navegador queda para la verificacion manual.

**Alternativa descartada.** Anadir jsdom. Daria fidelidad real de DOM, pero
introduce la primera dependencia en la raiz del repositorio para un beneficio que
la separacion de logica pura ya consigue.
