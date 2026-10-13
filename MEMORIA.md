# MEMORIA.md — Memoria del proyecto para agentes y personas

> **Léelo completo antes de cambiar algo** (lo exige `AGENTS.md`).
> **Actualízalo después de cada cambio**, en el mismo commit: agrega una
> entrada al "Registro de cambios", corrige las decisiones que cambien y
> mueve los pendientes que resuelvas. Escribe para quien llega sin contexto.

---

## 1. Estado actual

- Rama de trabajo del frente: `jefersonbustamante` (sale de `origin/dev`).
- Sprint actual: Jeferson Bustamante lleva el **frontend**; otro compañero
  continúa el **backend** en esta misma línea.
- App local: `docker compose up --build -d` → http://localhost:8080
  (phpMyAdmin en :8081, MySQL en :3309, verificación JWT en :3001).
- Datos de prueba: `docker compose exec -T app php Tools/seed-publicaciones-demo.php`
  crea 6 publicaciones con foto (marcadas `DEMO_EXPLORAR`; se borran con
  `--limpiar`). La semilla aplica a la base **local**, nunca a producción.

## 2. Mapa rápido (dónde está cada cosa)

Detalle completo en `Documentation/Arquitectura.md`. Lo nuevo de esta línea:

| Qué | Dónde |
|---|---|
| Rutas bonitas | `Public/.htaccess` (`/ajustes` es nueva) |
| Inicio (portada) | `Application/View/home/index.php` + `Public/js/home.js` |
| Explorar | `Application/View/explorar/index.php` + `Public/js/explore.js` |
| Tarjeta de publicación (única) | `buildCard()` en `Public/js/explore.js` |
| Carrusel | `Public/js/shared/carousel.js` |
| Mi panel (`/mi-actividad`) | `Application/View/mi-actividad/` + `Public/js/mi-actividad.js` |
| Ajustes de cuenta (`/ajustes`) | `Application/View/ajustes/` + `Public/js/ajustes.js` |
| Foto, alias y teléfono propios (Ajustes → Perfil) | `Public/js/ajustes.js` + API `Public/api/mi-perfil.php` (`MiPerfilController`) |
| Admin: moderar publicaciones (`/admin/publicaciones`) | `Application/View/publicaciones/` + `Public/js/publicaciones.js` + API `Public/api/admin-publicaciones.php` |
| Admin: fletes (`/admin/fletes`) | `Application/View/adminfletes/` + `Public/js/adminfletes.js` + API `Public/api/admin-fletes.php` (`AdminFletesController`) |
| Me interesa (`/me-interesa`) | `Application/View/me-interesa/` + `Public/js/me-interesa.js` |
| Registro / ampliación | `Application/View/registro/` + `Public/js/registro.js` |
| Publicar | `Application/View/publicar/` + `Public/js/publicar.js` |
| Destino seguro `?next=` | `Public/js/shared/next.js` |
| Registro pendiente de confirmar correo | `Public/js/shared/registro-pendiente.js` |
| Subida de fotos a Supabase Storage | `Public/js/shared/storage.js` |
| Navegación pública, menú de cuenta | `Public/js/public-ui.js` |
| Cerrar sesión (único) | `signOutEverywhere()` en `Public/js/shared/auth-gate.js` |
| Estilos de formularios | `Public/css/onboarding.css` (`signup-*`, `publish-*`) |
| Subastas en vivo (`/subastas`, `?id=` el detalle) | `Application/View/subastas/` + `Public/js/subastas.js` + `Public/css/subastas.css` + API `Public/api/subastas.php` (`SubastasController`) |
| Banda "Subastas en vivo" de Explorar | `Public/js/subastas-vivo.js` (marcado `data-subastas-banda` en `explorar/index.php`) |
| Tiempo real (Supabase Realtime) | Navegador `Public/js/shared/tiempo-real.js`; servidor `Application/Service/TiempoReal.php` |
| Cierre de subastas vencidas | `Application/Service/SubastaCierreService.php` |
| Campana de avisos (encabezado) | `createBell()` en `Public/js/public-ui.js` + API `Public/api/notificaciones.php` |
| Admin: subastas (`/admin/subastas`, solo lectura) | `Application/View/adminsubastas/` + `Public/js/adminsubastas.js` + API `Public/api/admin-subastas.php` |

## 3. Decisiones vigentes

### Navegación y sesión
- **Sin sesión**, el header muestra solo logo, tema, Entrar y Crear cuenta
  (sin Inicio, Buscar, Explorar, Publicar ni Fletes). Lo hace `public-ui.js`
  en el navegador; las vistas PHP siguen trayendo los enlaces.
- `/explorar` y `/fletes` sin sesión redirigen a Inicio. Cualquier enlace o
  búsqueda hacia una página privada pasa por `entrar?next=…` y vuelve al
  mismo destino (con sus filtros) después de iniciar sesión.
- **Cerrar sesión** (menú público y panel admin) usa `signOutEverywhere()`:
  revoca la sesión de Supabase, borra el permiso admin y lleva a Inicio. Una
  sesión vencida (401) en Mi panel o Ajustes usa `endExpiredSession()` y
  también va a Inicio.
- Al entrar por `/entrar` sin Persona (409), `login.js` termina **primero** el registro pendiente de la pestaña
  y **después** revisa si la cuenta es admin. Antes iba al panel y un administrador nunca podía crear su perfil de
  usuario. Por `/admin/entrar` sigue mandando al panel.
- **Toda salida de sesión borra el perfil en caché** (`tindercows:profile`, con cédula, teléfono y correo):
  `clearAuthSession()` en `supabase-auth.js`, por donde pasan el menú, el panel admin, la sesión vencida y el login
  rechazado. Antes quedaba en el navegador y el registro lo usaba para rellenar el formulario de la siguiente
  persona. El borrador del registro se conserva a propósito (se necesita si Supabase pide confirmar el correo).
- Iniciar sesión lleva a **Explorar** salvo que `next` traiga un destino
  seguro (por ejemplo `explorar?publicacion=6`).
- **Con sesión no hay portada.** `public-ui.js` quita "Inicio" del menú y del pie, el logo lleva a Explorar y la portada (`<body data-portada>` en `home/index.php`) redirige a `explorar`. Sin sesión todo sigue igual. Si cambias la portada, conserva `data-portada` y no se la pongas a Explorar (bucle de redirección).

### Registro y actividades (comprador, vendedor, transportista)
- **Alta inicial = solo Comprador.** El formulario no pregunta actividades y
  el **backend** lo garantiza: `RegistroPublicoService::capacidadesDeAlta()`
  agrega `COMPRADOR` cuando la sesión todavía no tiene Persona vinculada. Si
  la Persona ya tenía Comprador (activo o inactivo) no se toca.
- Tras registrarse va a **Explorar**.
- **Confirmación de correo:** el proyecto de Supabase la tiene activada
  (`mailer_autoconfirm: false`). Si `signUp` no devuelve sesión, el registro
  lleva a `entrar?registro=confirmar` con un aviso neutro. Al confirmar e
  iniciar sesión, `login.js` termina el registro con el borrador guardado
  (`registro-pendiente.js`), sin pedir los datos otra vez. El borrador no
  guarda contraseñas y solo vive en la pestaña donde se registró.
- **Ampliación (después del alta):** se gestiona en Ajustes → Cómo participo.
  - Vendedor: "Configurar" abre `registro/productor?next=ajustes`, que muestra
    **solo** el formulario de fincas y envía solo `PRODUCTOR`.
  - Transportista y Comprador sin configurar: se activan con el interruptor;
    Transportista hace `POST api/v1/registro` con
    `{ capacidades: ['TRANSPORTISTA'], fincas: [] }` (misma ampliación de
    siempre, sin datos extra) y muestra una notificación.
  - Activar/desactivar algo ya configurado: `PATCH api/v1/actividad` con
    `{ contexto, activo }` (sin cambios de lógica).
- **Disponibilidad en tiempo real (P2-1):** `POST api/v1/registro/identificacion` acepta **o**
  `{ identificacionTipo, identificacionNumero }` **o** `{ correoElectronico }` (los dos juntos → 422) y responde
  `{ disponible }`. El correo se normaliza con `ValidacionService::validarCorreo` (trim + minúscula) y se busca sin
  distinguir mayúsculas (`Persona::existeCorreo`). Al enviar el registro se vuelve a validar todo bajo `NamedLock`
  (`RegistroPublicoService::resolverPersona` y `Persona::obtenerOCrear`); esto ya existía.
- **Límite por IP (DEC-REG-001):** el endpoint de disponibilidad permite 20 consultas por IP cada 60 s
  (`RegistroConsulta::LIMITE` y `VENTANA_SEGUNDOS`); la siguiente responde **429** con `Retry-After: 60`. Tabla
  `tbregistroconsulta` (hash SHA-256 de la IP, nunca la IP; se limpia sola). La IP sale de `X-Real-IP` (proxy de
  Vercel) o de `REMOTE_ADDR`. El esquema ahora tiene **35 tablas**. **Comprobado en Vercel (05/10, preview de
  `backend`):** con `X-Real-IP`/`X-Forwarded-For` inventadas todas las consultas cuentan como la IP real (no se puede
  saltar el límite), y una segunda IP (celular con datos) seguía consultando mientras la primera tenía 429: el límite
  es por IP, no global.
- **Correo en el formulario (P2-1):** `registro.js` consulta el correo 400 ms después de dejar de escribir
  (`scheduleEmailCheck` / `checkEmail`, mensaje en `[data-correo-status]`), solo en el alta **sin sesión**. Solo
  bloquea "Siguiente" y "Registrar" si el correo ya está registrado; si la consulta falla, deja seguir (el servidor
  revalida). Un 429 muestra el mensaje del servidor ("Espera un minuto…"), también en la cédula.
- **Máscaras iguales al servidor (P2-1):** `errorIdentificacion()` en `shared/identificacion.js` copia las reglas y
  mensajes de `ValidacionService::validarIdentificacion()` (`REGLAS_SERVIDOR`), y el registro usa `telefono.js`
  (`telefonoValido`, `PATRON_TELEFONO`, `aplicarRestriccionTelefono`), igual que los paneles admin. Ambas se aplican en
  `validatePersonaDraft`. `Tests/frontend/mascaras.test.mjs` compara las reglas JS con las del PHP: **si cambias una
  regla de identificación en PHP, cambia también `REGLAS_SERVIDOR`**. Ajustes → Perfil usa las mismas reglas
  (`errorIdentificacion` y `telefono.js`) desde el 11/10.
- **Ojo, decisión revertida:** el 29/09 Dilan había retirado esta consulta del correo para no revelar qué correos
  existen (`d7b5a88`). Se reabrió el 04/10 por decisión del equipo, con el límite por IP (DEC-REG-001). La prueba
  que lo prohibía ahora exige que la consulta pase por el endpoint con límite. El aviso neutro al crear la cuenta en
  Supabase sigue igual.
- `registro.js` lee la actividad pedida también de la ruta bonita
  (`/registro/productor`), porque Apache agrega `?capacidad=` solo por dentro.

### Mi panel y Ajustes
- "Mi actividad" se renombró a **Mi panel** (la URL sigue siendo `/mi-actividad`).
- Mi panel muestra solo lo operativo: Mis publicaciones, Mis fincas (solo con
  Vendedor activo), Mis vehículos (solo con Transportista activo) y una
  tarjeta de perfil. "Desactivar finca" vive dentro de "Editar finca".
- **Ajustes de cuenta** (`/ajustes`, en el menú del avatar): perfil con
  identificación y teléfono enmascarados, y "Cómo participo".
- "Mis publicaciones" sale de `api/v1/publicaciones` con `mias: true` (el servidor filtra por
  el vendedor autenticado). Cada tarjeta permite Editar (título, precio, descripción), Pausar/Reactivar y
  marcar como Vendida.

### Publicaciones e imágenes
- Columna `tbanimalpublicacionimagenurl VARCHAR(500) NULL` (migración
  `012imagenpublicacion.sql`). La API devuelve `imagenUrl` en cada publicación
  y la acepta al crear (`POST api/v1/publicaciones`).
- `CampoValidacion::imagenUrl()` (`Application/Service/`) solo acepta `https://` absoluto,
  sin usuario/contraseña, hasta 500 caracteres; rechaza `http`, `javascript:`
  y `data:` con 422. En JS, `safeImageUrl()` hace lo mismo antes de un `<img>`.
- **Fotos del dispositivo:** el navegador las sube directo a **Supabase
  Storage**, bucket público `publicaciones`, carpeta `<id del usuario>/` (JWT
  `sub`). PHP solo recibe la URL. Requiere el bucket y su política (ver
  Pendientes de configuración). Sin bucket, la subida falla con un mensaje
  amable y la opción de URL sigue funcionando.
- Las fotos de prueba vienen de Wikimedia Commons (licencias libres; créditos
  en `Tools/seed-publicaciones-demo.php`). No usar imágenes de Google.
- **Estados de una publicación** (periodo abierto en `tbanimalpublicacionestadoperiodo`):
  `ACTIVO`, `PAUSADO`, `VENDIDO`, `RETIRADO`. Solo ACTIVO se ve en Explorar. Desde ACTIVO o PAUSADO
  se puede ir a cualquiera; VENDIDO y RETIRADO son finales (409). El plan decía `CERRADO`: se
  usó `RETIRADO`, que ya existía en el filtro de la API. Cada cambio cierra el periodo vigente,
  abre otro (con `motivo` opcional) y deja bitácora (`ACTUALIZAR`, entidad `PUBLICACION`).
- `PATCH api/v1/publicaciones` `{ publicacionId, titulo?, descripcion?, precio?, imagenUrl?, estado?, motivo? }`:
  solo toca las claves presentes; una publicación ajena responde 404. `mias=true` (en GET o en
  `consulta` del POST) exige sesión (401) y por defecto trae todos los estados.

### Foto de perfil y datos propios
- Columna `tbpersonafotourl VARCHAR(500) NULL` (migración `013personafoto.sql`, en los 4 lugares + diccionario/DER/PDF).
  Las cuentas anteriores quedan en NULL y muestran el avatar con iniciales.
- `PATCH api/v1/mi-perfil` `{ nombre?, identificacion?, correoElectronico?, alias?, telefono?, fotoUrl? }` (sesión): solo
  cambian las claves enviadas; `alias: ""` o `fotoUrl: null` las dejan en NULL. Otras claves → 422. La foto exige
  `https://` (`CampoValidacion::imagenUrl($v, 'fotoUrl')`) y el teléfono usa `ValidacionService`. Un teléfono
  nuevo deja histórico (`Persona::actualizarPerfil`); la bitácora (`PERSONA`, `API_MI_PERFIL`) **no guarda el teléfono**,
  solo `telefonoCambiado`.
- `api/v1/actividad` devuelve `persona.fotoUrl`; de ahí sale el perfil en caché del navegador y el avatar del encabezado
  (imagen solo si es https; si falla, vuelve la inicial).
- Ajustes → Perfil: foto (reutiliza `shared/storage.js`, mismo bucket `publicaciones`), "Quitar foto" y "Editar datos"
  (nombre, identificación, correo, alias y teléfono). El avatar del encabezado se actualiza al recargar la página.
- **Nombre, identificación y correo (11/10)**, todo bajo el lock de alta de Persona (el mismo del registro):
  - `nombre`: 3 a 150 caracteres (`ValidacionService::textoCampo`).
  - `identificacion` `{ tipoCodigo, numero }`: mismas reglas que el registro (`validarIdentificacion`); la de otra Persona → 409
    en `identificacion.numero`. Si la persona ya subió un documento, **vuelve a `PENDIENTE`** (sin motivo) y la lectura
    automática se recalcula contra el número nuevo. La bitácora solo guarda `identificacionCambiada`, nunca el número.
  - `correoElectronico`: se normaliza en minúscula. El de otra Persona o el de un **administrador** → 409 (tomar el correo
    de un admin daría acceso al panel). Como la sesión se vincula con la Persona **por el correo**, el servidor cambia
    también el correo de la cuenta de Supabase (`Application/Service/SupabaseAuthAdmin.php`, `PUT /auth/v1/admin/users/{sub}`
    con `email_confirm: true` y `SUPABASE_SECRET_KEY`): si Supabase lo rechaza (correo ya usado → 409), no se guarda nada del
    PATCH. Los dos sistemas no comparten transacción: antes de llamar a Supabase el cambio se guarda `PENDIENTE` en
    `tbpersonacorreocambio` y, si la operación se interrumpe, `CorreoCambioService` lo termina o lo descarta (DEC-CORREO-001,
    ver "Cambio de correo consistente"). Después, `ajustes.js` renueva la sesión (`refreshAuthSession`) para que el
    token traiga el correo nuevo. Un admin que cambie su correo deja de ser admin (`tbadministrador` va por correo).

### Foto del vehículo
- Columna `tbvehiculofotourl VARCHAR(500) NULL` (migración `014vehiculofoto.sql`, en los 4 lugares + diccionario/DER/PDF).
  Una sola foto por vehículo; los vehículos anteriores quedan en NULL.
- `api/v1/mi-vehiculos` acepta `fotoUrl` en `POST` y `PUT` y lo devuelve en `vehiculo` y `vehiculos[]`. Misma validación que la
  foto de perfil (`CampoValidacion::imagenUrl($v, 'fotoUrl')`: solo `https://`, hasta 500, 422 en `errors.fotoUrl`).
- Mi panel → Mis vehículos: el diálogo sube la foto (campo compartido `shared/foto-campo.js`, mismo bucket `publicaciones`) y la fila muestra la miniatura. El navegador **solo envía `fotoUrl` si la foto cambió**; así un PUT sin cambio de foto no la borra.
- En `PUT`, **sin la clave `fotoUrl` la foto se conserva** y `null` o `""` la quita (`Vehiculo::actualizar`). Así el PUT del
  admin (`api/v1/vehiculos`, que no conoce `fotoUrl`) no borra la foto. El admin ve `fotoUrl` en la lectura pero no la edita.

### Oferta de flete (P1-2, en construcción)
- Tabla nueva `tbtransportistaoferta` (DEC-FLETE-001, esquema de **36 tablas**): transportista, vehículo, zona base (`tbdireccion` con
  coordenadas), radio en km, capacidad en cabezas, precio base opcional (`NULL` = a convenir), descripción y estado `ACTIVA`/`PAUSADA`.
  Migración `019transportistaoferta.sql`.
- **No** reutiliza `tbtransportistaflete` (viaje realizado, con método de pago obligatorio y reseñas que apuntan a él) ni
  `tbtransportistahorario` (la disponibilidad va como texto libre). El precio no se calcula por km.
- **API `api/v1/mi-ofertas`** (sesión, Transportista ACTIVO; `MiOfertasController`):
  - `GET` lista las propias en todos los estados (con zona exacta y vehículo).
  - `POST { vehiculoId, direccion, radioKm, capacidad, precio?, descripcion? }` publica (201, queda `ACTIVA`). `PUT` lo mismo + `ofertaId`.
    `PATCH { ofertaId, estado: ACTIVA|PAUSADA }` pausa o reactiva (idempotente). No hay DELETE: se pausa.
  - Reglas: la `direccion` usa el mismo objeto que las fincas; el **punto (latitud y longitud) es opcional desde el 11/10** (antes obligatorio); `radioKm` 1–500,
    `capacidad` 1–200, `precio` ≥ 0 o `null` (a convenir). El vehículo debe ser propio (404 si no) y estar activo (409). Una oferta
    ajena responde 404. Sin la actividad Transportista: 409.
  - Locks en orden oferta → dirección → bitácora, dentro de una transacción. Bitácora entidad `OFERTA_FLETE`, origen `API_MI_OFERTAS`
    (`CREAR`, `ACTUALIZAR`, `PAUSAR`, `REACTIVAR`).
- **API `api/v1/fletes`** (GET, **requiere sesión** como la página Fletes; `FletesController`): `latitud` y `longitud` obligatorias (422 sin
  ellas), `capacidadMinima`, `pagina`, `tamanoPagina` (≤ 50). Devuelve solo ofertas `ACTIVA` cuyo punto queda **dentro de su radio**
  (`distancia <= radioKm`), de la más cercana a la más lejana, con `distanciaKm`. Solo de transportistas, personas y **vehículos activos**.
  La vista de cliente **no trae placa, VIN, señas ni coordenadas**: solo modelo y foto del vehículo y provincia/cantón/distrito/pueblo.
  La distancia se calcula en PHP con `PublicacionCercaniaService::calcularDistanciaKm` (ver el `ponytail:` de `listarCercanas` si crece).
- `api/v1/fletes` también acepta **`publicacionId`** en vez de `latitud`/`longitud`: el servidor usa el punto de la finca del animal (sus coordenadas no se exponen) y devuelve los fletes que la cubren, con `distanciaKm` desde la finca; 422 si la finca no tiene punto en el mapa. Es lo que usará "Comprar con flete" y "Ver fletes cercanos".
- **Ofertas sin punto en el mapa (11/10):** se emparejan por **provincia** (sin tildes ni mayúsculas) y salen al final, con `distanciaKm: null`.
  `api/v1/fletes` acepta `provincia` opcional junto a `latitud`/`longitud` (Fletes la saca del punto del navegador con Nominatim); con
  `publicacionId` o `subastaId` usa la provincia de la finca, y una finca **sin punto** ya no da 422: busca solo por provincia
  (`CompraSolicitud::ubicacionDeFinca`). Las pantallas muestran "en tu provincia" / "en la misma provincia" en vez de los km.
- **Punto del mapa = opcional en todos lados, pero debe coincidir con la provincia escrita** (`shared/punto-direccion.js`:
  `validarPuntoConDireccion`, `desajusteProvincia`). Se revisa en el navegador al guardar (oferta de flete, finca de Mi panel, fincas del
  registro y del admin de productores) con la provincia que Nominatim da para el punto. Solo la provincia (el pueblo o cantón del mapa puede
  llamarse distinto). Si Nominatim no responde, no bloquea. **No hay validación en el servidor** (habría que llamar a Nominatim desde PHP).
- `api/v1/fletes` **no devuelve las ofertas de quien consulta** (viven en "Mis ofertas"), igual que Explorar con las publicaciones propias.
- **Pantalla `/fletes`** (`fletes.js`, `fletes-1`): "Fletes disponibles" (lista las ofertas cercanas a la ubicación del navegador, con filtro de
  capacidad mínima; sin ubicación pide "Usar mi ubicación") y, con Transportista activo, "Mis ofertas" (Publicar, Editar, Pausar/Reactivar).
  Sin vehículo activo no deja publicar y manda a Mi panel. La columna lateral conserva el estado de la actividad; activar Transportista se hace
  en Ajustes → Cómo participo (ya no se manda a `registro/transportista`).
- **Editor de dirección con mapa reutilizable:** `shared/editor-direccion.js` (`montarEditorDireccion`) monta cascada, señas y mapa sobre un bloque con
  los `data-farm-*` de las fincas; `crearSelectorPuntoFinca` ganó las opciones `titulo`, `opcional` y `lugar` (por defecto, los textos de finca).
  **Pendiente de limpieza:** `mi-actividad.js` (`openFarmModal`) todavía tiene su propia copia de esa lógica; migrarla a `montarEditorDireccion`
  exige ajustar `mi_actividad.test.mjs`, que busca esas cadenas en `mi-actividad.js`.
- Hecho: esquema, API y pantalla. Sigue P1-3 (fletes cerca de una publicación y solicitud de compra).

### Solicitud de compra (P1-3, en construcción)
- Decisiones (DEC-COMPRA-001, esquema de **37 tablas**): comprar es una **solicitud que el vendedor acepta o rechaza** (no hay pagos en línea).
  El **flete lo responde aparte el transportista**: la venta no depende de él (si lo rechaza, el comprador lo ve y puede pedir otro).
  El comprador puede proponer un método de pago, opcional.
- Tabla nueva `tbcomprasolicitud`: publicación, comprador (`tbcompradorid`), oferta de flete opcional, método de pago opcional, precio (copia del
  de la publicación; nulo si era "a convenir" y el vendedor lo fija al aceptar), mensaje, estado `PENDIENTE|ACEPTADA|RECHAZADA|CANCELADA`,
  estado del flete (`NULL` si no pidió flete) y fechas/motivo de las respuestas. Migración `020comprasolicitud.sql`.
- **`tbcompra` y `tbventa` se adaptaron (aditivo):** columna nueva `tbcompradorid`, `tbcomprasolicitudid` (solo `tbventa`), y
  `tbproductorcompradorid` y `tbpagometodoid` ahora aceptan `NULL`. Antes exigían un Productor comprador y un pago, que un Comprador normal no
  tiene. Las filas existentes no cambian. Al aceptar una solicitud se registran ambas con `tbcompradorid`.
- Comprobado contra Postgres 16: `migrate.php` actualiza una base con el esquema viejo (36 tablas, columnas NOT NULL) y se puede correr dos veces.
- **API `api/v1/solicitudes-compra`** (sesión; `SolicitudesCompraController`, modelo `CompraSolicitud`):
  - `GET` devuelve `{ hechas, recibidas, fletes }`: lo que la persona pidió como Comprador, lo que recibe como Vendedor y los fletes que le piden
    como Transportista. El transportista **solo ve el flete cuando el vendedor ya aceptó la venta**.
  - `POST { publicacionId, ofertaId?, pagoMetodoId?, mensaje? }` (201): exige Comprador activo (409), publicación ACTIVA (409), que no sea propia
    (409) y que no haya otra pendiente suya para esa publicación (409). Copia el precio de la publicación (nulo si era "a convenir"). El flete
    debe estar disponible (`TransportistaOferta::buscarDisponible`), no ser del propio comprador y, si la finca y la oferta tienen punto, cubrirla
    (422 en `errors.ofertaId`).
  - `PATCH { solicitudId, accion, motivo?, precio? }`, `accion` = `CANCELAR` (comprador, solo pendiente), `ACEPTAR` o `RECHAZAR` (vendedor, solo
    pendiente), `ACEPTAR_FLETE` o `RECHAZAR_FLETE` (transportista, solo con la venta aceptada y el flete pendiente). Una solicitud ajena responde 404.
  - **Aceptar** (todo en una transacción): registra `tbcompra` y `tbventa` con `tbcompradorid` y `tbcomprasolicitudid` (sin Productor comprador, pago
    el que propuso el comprador o `NULL`, snapshots de raza/edad/peso/propósito), pasa la publicación a `VENDIDO` y **rechaza solas las demás
    pendientes** de esa publicación. Si la publicación era "a convenir", el vendedor **debe mandar `precio`** (422 si no); si ya tenía precio, se
    ignora el que mande.
  - **Teléfonos:** se comparten solo cuando hay trato (venta aceptada entre comprador y vendedor; flete aceptado entre los tres). Antes, `telefono: null`.
  - Bitácora entidad `COMPRA_SOLICITUD`, origen `API_SOLICITUDES_COMPRA` (`CREAR`, `CANCELAR`, `ACEPTAR`, `RECHAZAR`, `ACEPTAR_FLETE`, `RECHAZAR_FLETE`).
- `AnimalComercial::registrarCompra` y `registrarVenta` aceptan ahora `?int $productorCompradorId` y, en `$datos`, `compradorId`, `solicitudId` y un
  `pagoMetodoId` opcional (los llamadores anteriores siguen igual).
- **Pantallas:**
  - Explorar: la tarjeta completa tiene un tercer botón, **"Solicitar compra"** (la acción principal, coral). `explore-interactions.js` lo atiende y carga
    por demanda `shared/solicitud-compra.js` (`abrirSolicitudCompra`), que arma su propio `<dialog>` con DOM (sin `innerHTML`) y su hoja `css/solicitud.css`.
    Ahí se elige "Solo el animal" o "El animal con flete" (la lista sale de `api/v1/fletes?publicacionId=`), con un mensaje opcional.
  - Me interesa: si la publicación sigue ACTIVA, la tarjeta suma "Solicitar compra" y **"Ver fletes cercanos"** (abre el mismo diálogo con el flete preseleccionado).
  - Mi panel: tres bandejas que solo aparecen si tienen filas: **Solicitudes recibidas** (Aceptar o Rechazar), **Fletes que me piden** (Aceptar flete o
    Rechazar) y **Mis solicitudes** (Cancelar). Al aceptar una publicación "a convenir" se pide el precio con `window.prompt`; al rechazar, un motivo opcional.
    Los teléfonos aparecen cuando la API los entrega.
  - **Método de pago (opcional) en el diálogo:** `GET api/v1/pago-metodos-disponibles` (sesión; `PagoMetodosDisponiblesController`, `PagoMetodo::listarActivos`)
    devuelve `{ metodos: [{ pagoMetodoId, nombre }] }` solo con los **activos**, sin descripción. `api/v1/metodos-pago` sigue siendo solo del administrador.
    `shared/solicitud-compra.js` (`solicitud-2`) llena el selector "Método de pago (opcional)" al abrir (si la carga falla o no hay métodos, el campo
    se oculta y el diálogo sigue) y envía `pagoMetodoId` solo si se eligió. `presentar()` devuelve `pagoMetodo: { id, nombre }` o `null` (además de
    `pagoMetodoId`) y las bandejas de Mi panel muestran "Método de pago: …". Un método inexistente o inactivo sigue dando 422.
  - **Carrito = contador (decisión del 06/10):** con sesión, `public-ui.js` (`public-14`) pone junto al menú de cuenta un ícono de carrito
    (`fa-cart-shopping`) con una insignia que cuenta las solicitudes **ACEPTADAS** del usuario como Comprador; la insignia se oculta en 0 y el ícono
    siempre enlaza a `mi-actividad#mis-solicitudes` (ancla en la cabecera de "Mis solicitudes"; `mi-actividad.js` hace `scrollIntoView` porque la bandeja
    nace oculta). Etiqueta accesible "Mis solicitudes aprobadas: N". Dato: `GET api/v1/solicitudes-compra?resumen=1` → `{ aprobadas: N }` con un `COUNT`
    (`CompraSolicitud::contarAceptadas`; 0 si no es Comprador). Se pide una vez por carga de página y se guarda unos segundos en `sessionStorage`
    (`tindercows:carrito`, por correo); si falla se queda en 0 y no rompe la navegación. No usa `tbcarrito*`. Estilo: `.public-cart*` en
    `public-product.css` (`product-9`), insignia con el acento `--tc-primary` y texto `#151a18` (igual que `.public-header__login`, AA en ambos temas).
    El `@import` de `base.css` sigue apuntando a `product-7` (las vistas enlazan `product-9` directo).
- Hecho: esquema, API y pantallas. **No se probó en el navegador con una sesión real** (solo pruebas estáticas y de API): revisar a mano el flujo completo
  con dos cuentas (comprador y vendedor) y una tercera con Transportista.

### Documento de identidad (P2-5)
- La persona sube la foto o el PDF de su documento en Ajustes → Perfil (opcional). El navegador lo sube directo al
  bucket **privado** `documentos` de Supabase Storage, en su carpeta `<id de usuario>/` (`subirDocumentoIdentidad` en
  `shared/storage.js`: JPG, PNG, WebP o PDF, hasta 5 MB). No hay URL pública.
- `PATCH api/v1/mi-perfil` `{ documentoRuta }`: PHP solo acepta `<sub del JWT>/<uuid>.(jpg|png|webp|pdf)` (otra carpeta,
  `..`, una URL o `null` → 422), guarda la ruta y deja el estado en `PENDIENTE` con la fecha UTC. Un documento nuevo
  **siempre** vuelve a `PENDIENTE`, aunque el anterior estuviera verificado. Columnas `tbpersonadocumentoruta`,
  `tbpersonadocumentoestado` y `tbpersonadocumentofecha` (migración `016personadocumento.sql`, en los 4 lugares).
- El tipo de documento es el de la identificación (`tbpersonaidentificaciontipo`); no hay columna aparte.
- `api/v1/actividad` y la respuesta del PATCH devuelven `persona.documento = { estado, fecha }` o `null`, **nunca la
  ruta** (`Persona::documentoPublico`). La bitácora guarda solo el estado anterior y el nuevo.
- **Aviso al crear la cuenta:** `registro.js` (alta con sesión) y `login.js` (alta al confirmar el correo) marcan la
  pestaña (`shared/aviso-documento.js`, `sessionStorage`) y Explorar muestra **una vez** un aviso con enlace a
  Ajustes → Perfil. No hay paso de documento en el registro: al registrarse no hay sesión mientras Supabase exija
  confirmar el correo. Si se quita la confirmación (P0-1) se puede agregar el paso, pero debe ocultarse solo si
  vuelve la confirmación y no debe impedir crear la cuenta si la subida falla.
- **Verificación (P2-6, opción c del plan):** `/admin/documentos` lista las personas con documento (por defecto las
  `PENDIENTE`, de la más antigua a la más nueva). "Ver documento" pide un **enlace firmado** que caduca en 5 minutos
  (`Application/Service/SupabaseStorage.php`, con `SUPABASE_SECRET_KEY`) y lo abre en una pestaña sin `opener`. Solo un
  documento `PENDIENTE` se verifica o rechaza (si no, 409); **rechazar exige motivo**, que se guarda en
  `tbpersonadocumentomotivo` (migración `017personadocumentomotivo.sql`, en los 4 lugares) y la persona lo ve en
  Ajustes. Un documento nuevo limpia el motivo y vuelve a `PENDIENTE`.
- API `api/v1/admin/documentos` (solo admin): `POST { consulta }` lista; `POST { personaId }` da el enlace (la ruta
  sale de la base, nunca del navegador); `PATCH { personaId, estado: VERIFICADO|RECHAZADO, motivo }` decide.
  `Cache-Control: no-store`. Bitácora `PERSONA` con `VER_DOCUMENTO` (cada vez que un admin abre un documento),
  `VERIFICAR_DOCUMENTO` y `RECHAZAR_DOCUMENTO`, con `realizadoPor`.
- `SUPABASE_SECRET_KEY` llega a la app PHP: en local por `compose.yaml`; en producción está en las variables de entorno
  de Vercel (configurada, confirmado el 11/10). Sin ella, "Ver documento" y el cambio de correo responden 503. Nunca se
  manda al navegador.
- **Lectura automática del número (opción a del plan, como ayuda):** con una **foto** (no PDF) de una identificación
  numérica (cédula física, jurídica, DIMEX, NITE), `shared/escaner-documento.js` lee el número **en el dispositivo**
  con Tesseract.js (CDN jsdelivr, se descarga solo al elegir una foto; es el primer script externo de la app). Prueba 4
  variantes de la imagen y busca números con las reglas de `REGLAS_SERVIDOR`. El navegador manda **solo**
  `documentoLectura: { numero }`; **PHP calcula** `tbpersonadocumentolectura` (`COINCIDE`, `NO_COINCIDE`,
  `OTRA_CUENTA`, `SIN_LECTURA`; NULL si no se intentó o la identificación es un pasaporte) y guarda el número en
  `tbpersonadocumentonumeroleido` (migración 018). **No aprueba nada:** el admin lo ve junto a la imagen y decide.
  Si el lector no carga o falla, el documento se sube igual, sin lectura.
- **Cámara:** "Tomar foto" abre la **app de cámara del teléfono** (`<input accept="image/*" capture="environment">`,
  resolución completa) y solo se muestra en celulares (`esCelular()`: puntero grueso y pantalla táctil). En la prueba,
  la webcam de una computadora no tuvo calidad suficiente; subir una foto bien tomada sí funcionó.
- **Registro:** paso opcional "Documento de identidad" en el alta (no al ampliar). El archivo queda en memoria y se
  envía **después** de crear la cuenta (hace falta sesión y Persona); si falla o no hay sesión (confirmación de correo
  activa), la cuenta se crea igual y Explorar muestra el aviso hacia Ajustes.
- **En pausa (decisión del equipo):** exigir identidad verificada para vender u ofrecer fletes, y si una lectura que
  coincide podría verificar sola. Están en las preguntas abiertas del plan.
- **Pendiente:** borrar las fotos 90 días después de verificadas y los archivos reemplazados (necesita una tarea
  programada; acordarla con Dilan).

### Administrador: gestionar administradores (P3-4)
- `/admin/administradores` lista los correos de `tbadministrador`, permite **agregar** uno (se normaliza con
  `ValidacionService::validarCorreo`) y **desactivar o reactivar**. Antes se agregaban a mano en la base.
- API `api/v1/admin/administradores` (solo administrador): `GET` lista (con `esUsted`), `POST { correoElectronico }`
  agrega (201) o, si el correo ya existió inactivo, reactiva la **misma fila** (200); si ya es admin activo, 409.
  `PATCH { administradorId, activo }` cambia el estado.
- Reglas: **nadie puede desactivarse a sí mismo** y **debe quedar al menos un administrador activo** (409). Todo pasa bajo
  `NamedLock` `tindercows_administrador_alta`, así dos admins no pueden desactivarse a la vez y dejar el panel vacío.
- Bitácora entidad `ADMINISTRADOR`, origen `API_ADMIN_ADMINISTRADORES`; los datos nuevos llevan `realizadoPor` (correo de
  quien hizo el cambio), porque un admin puede no tener Persona y `tbbitacorausuarioid` quedaría vacío.
- `migrate.php` siembra el admin inicial solo si su correo no existe, así que un admin desactivado desde el panel no se
  reactiva al redesplegar.

### Administrador: bitácora (P3-4)
- `/admin/bitacora` es un visor **de solo lectura** de `tbbitacora`, del evento más reciente al más antiguo, 25 por página,
  con "Ver detalle" (datos anteriores y nuevos en JSON, mostrados con `textContent`).
- API `api/v1/admin/bitacora` (solo administrador; `GET` o `POST { consulta }`, sin PATCH/DELETE). Filtros: `entidad` exacta
  (la lista sale de las entidades que ya existen), `desde`/`hasta` (`AAAA-MM-DD`, días UTC, ambos inclusive) y `q`, que busca
  sin distinguir mayúsculas en el nombre, identificación o correo de **quien hizo el cambio** y en el registro afectado.
  La consulta va por POST para que los nombres buscados no queden en la URL.
- "Hecho por": el nombre de la Persona; si no tiene (admin sin Persona), el `realizadoPor` de los datos; si no, el tipo de
  actor (`Sistema` = `NO_AUTENTICADO`). La pantalla muestra las fechas en la hora local del navegador.
- Límite conocido: `q` no busca dentro del JSON, así que los cambios de un admin **sin Persona** no se encuentran por su
  correo (sí por entidad, fecha o registro). Buscar en JSON se escribe distinto en MySQL y Postgres.
- `Bitacora::listar` usa `LOWER(...) LIKE` en los dos lados porque Postgres distingue mayúsculas en `LIKE`. Probado en Postgres 16.

### Administrador: moderar publicaciones
- `/admin/publicaciones` lista **todas** las publicaciones (buscador por título, raza, vendedor, finca o zona;
  filtro por estado) y permite **Pausar**, **Retirar** (ambos con motivo obligatorio) y **Reactivar**.
- API `api/v1/admin/publicaciones` (solo administrador, igual que Métodos de pago): lectura con `POST {consulta}`
  y `PATCH { publicacionId, estado: ACTIVO|PAUSADO|RETIRADO, motivo }`. El admin no marca VENDIDO. Solo se
  modera lo ACTIVO o PAUSADO (VENDIDO/RETIRADO son finales, 409). Reutiliza `cambiarEstadoPublicacion()`; el
  motivo queda en el periodo de estado y la bitácora registra `MODERAR` (origen `API_ADMIN_PUBLICACIONES`).
- Si el admin retira una publicación, el vendedor ya no puede editarla (409). Si solo la pausa, la ve en Mi
  panel como "Pausada".

### Administrador: fletes
- `/admin/fletes` (vista `adminfletes`, `adminfletes.js`; el nombre evita chocar con `fletes.js` de la pantalla pública) tiene dos vistas
  en la misma pantalla, elegidas con el selector "Ver": **Ofertas** (todas las ofertas de flete con transportista, vehículo, zona
  general, radio, capacidad, precio y estado) y **Solicitudes** (solicitudes de compra de **solo lectura**: publicación, comprador,
  vendedor, estado y estado del flete). Ambas con buscador y filtro de estado. No se muestran teléfonos, señas ni coordenadas.
- API `api/v1/admin/fletes` (solo administrador; `AdminFletesController`): lectura con `POST { consulta: { vista: OFERTAS|SOLICITUDES,
  q, estado, pagina, tamanoPagina } }` y `PATCH { ofertaId, estado: RETIRADA|ACTIVA, motivo }`. Consultas: `TransportistaOferta::listarAdmin`
  / `buscarAdmin` y `CompraSolicitud::listarAdmin`.
- **Moderación sin esquema nuevo:** `tbtransportistaofertaestado` es VARCHAR(20), así que se agregó el estado **`RETIRADA`**
  (`TransportistaOferta::ESTADO_RETIRADA`). `TransportistaOferta::ESTADOS` sigue siendo `ACTIVA|PAUSADA` (lo que el transportista puede
  poner). El admin retira una oferta ACTIVA o PAUSADA (motivo obligatorio, hasta 250) y reactiva (`ACTIVA`, sin motivo) **solo** una
  RETIRADA; una PAUSADA por su dueño la reactiva su dueño (409 al admin). Repetir el mismo estado es idempotente (200).
- El transportista **no puede** editar, pausar ni reactivar una RETIRADA (409 en `PUT` y `PATCH` de `api/v1/mi-ofertas`); en "Mis ofertas"
  la ve como "Retirada por un administrador" y sin botones. Una RETIRADA no sale en `api/v1/fletes` ni se puede pedir (esas consultas ya
  filtran por `ACTIVA`).
- El **motivo solo vive en la bitácora** (acción `MODERAR`, entidad `OFERTA_FLETE`, origen `API_ADMIN_FLETES`, clave = id de la oferta);
  la pantalla del transportista no lo muestra (no hay columna). Si se quiere mostrar, hay que agregar una columna o leer la bitácora.

### Me interesa (guardados)
- No hay tabla nueva: se usa `tbanimalpublicacioninteraccion` (tipo `ME_INTERESA`). El historial solo
  crece; la **marca vigente** es la última acción del par persona/publicación: `REGISTRAR` la marca y
  `RETIRAR` la quita (`PublicacionInteraccion::estaMarcada()`).
- `POST api/v1/publicaciones/interacciones` acepta `accion` (`REGISTRAR` por defecto, `RETIRAR` solo
  con `ME_INTERESA`). Con `ME_INTERESA` ambas son idempotentes (200 con `cambiado:false` si ya estaba marcada o
  ya no estaba; la revisión va dentro del lock, así dos toques seguidos guardan una sola fila). `RETIRAR` se permite
  aunque la publicación ya no esté activa; marcar una no activa sigue dando 409.
- La lista de guardados se ordena por **cuándo se marcó** (la última interacción `ME_INTERESA`), no por la fecha de la
  publicación: volver a marcar algo lo sube al inicio.
- `GET api/v1/publicaciones/interacciones?tipo=ME_INTERESA&pagina&tamanoPagina` (sesión): trae las
  marcadas con los datos de la tarjeta y **en cualquier estado** (la vendida o pausada sigue ahí; la
  pantalla la muestra como "No disponible").
- Con sesión, el listado público `api/v1/publicaciones` agrega `meInteresa` (bool) a cada publicación; sin
  sesión (o con token vencido) la lista sigue pública y sin ese campo. El botón "Me interesa" de la
  tarjeta sale con `data-saved="true"` cuando corresponde.
- `/me-interesa` está en el menú del avatar y en Mi panel; usa la tarjeta **compacta** de la portada
  (`buildCard(..., { compacta: true })`, con "Ver más información") y le agrega "Quitar de Me interesa".
- En Explorar, al tocar "Me interesa" la tarjeta sale del deck, y lo ya marcado no vuelve a aparecer ahí
  (Explorar envía `excluirMarcadas: 'true'` y lo filtra el servidor): vive en `/me-interesa`.

### Portada, Explorar y tarjetas
- Inicio: hero con buscador, carrusel de publicaciones (4/2/1 por vista,
  autoplay 4 s con pausa, flechas y puntos; fijas con ≤4) y "Cómo funciona".
  En la portada la tarjeta es compacta (foto, nombre, precio y "Ver más
  información"), sin botones de acción y sin navegar al hacer clic.
- Explorar: hero con foto, filtros de tipo/ubicación/precio y fila de
  tarjetas completas con scroll inferior (más de 4).
- **Explorar no muestra tus propias publicaciones** (viven en Mi panel). `explore.js` envía `excluirPropias: 'true'` y el servidor (`AnimalPublicacionController::consultar` → `listarPublicaciones(..., $excluirVendedorId)`) excluye las del vendedor autenticado; así el total y la paginación salen bien. Sin sesión no excluye nada. Es opcional: Inicio, Mi panel y los demás no lo envían. Una publicación propia ya no abre con `explorar?publicacion=<id>`.
- **Filtros de Explorar en el servidor (11/10):** `api/v1/publicaciones` acepta `ubicacion` (texto de hasta 100; busca en pueblo, distrito, cantón y provincia juntos, sin distinguir mayúsculas **ni tildes** en MySQL y Postgres: `AnimalComercial::sinTildesSql` con `REPLACE`), `precioMin` y `precioMax` (inclusivos; dejan fuera las "a convenir"; mínimo > máximo → 422) y `excluirMarcadas` (con sesión). Se aplican antes de contar y paginar, también al ordenar por cercanía. `explore.js` (`explore-13`) los envía 400 ms después de dejar de escribir y solo acepta la última respuesta. El filtro de **propósito** (chips) sigue siendo del navegador, sobre la página cargada.

### Decisiones del equipo (06/10) que acotan el trabajo
- **Respuestas del cliente/equipo:**
  - "Categorización" es lo mismo que **tipo** del animal.
  - **Formato del arete SENASA** (DIIO): `188` (código ISO de Costa Rica) + `0` (dígito de control o separación) + `NN` (código de provincia de
    procedencia: 01 San José, 02 Alajuela…) + `NNNNNNN` (correlativo único de 7 dígitos del animal en la base nacional). Son **13 dígitos**, por ejemplo
    `1880010002345` (188 · 0 · 01 · 0002345).
  - Una **publicación puede ser de un animal o de un lote** (ambas).
  - **Comerciante = Vendedor.** No se crea una actividad nueva (cierra P3-1).
  - **El carrito se mantiene, pero como un contador:** muestra cuántas **solicitudes de compra aprobadas** tiene la persona y enlaza a Mi panel
    (Mis solicitudes). No usa las tablas `tbcarrito*`.
- **Acceso en producción (P0-1): resuelto por decisión.** "Confirm email" está desactivado y el equipo **acepta el riesgo** de vincular la sesión
  por correo; no se hará la unión por `sub` por ahora. El bucket privado `documentos` ya existe.
- **Pospuesto (no tocar hasta nuevo aviso):** chat (P3-2), limpieza de tablas sin uso (P3-3: `tbcarrito*`, `tbanimalinteraccion`,
  `tbproductoractividad`, `tbtransportistaestadoperiodo` se quedan como están), política de sesión de administrador (P3-4) y la limpieza menor de frontend.
- **Lo trabaja Jeremi (no tocar):** verificación de identidad y catálogos en el panel de administración (P2-6).

### Modelo de animal (P2-2; DEC-ANIMAL-001)
- Esquema de **41 tablas** (43 con el historial de vacunación): catálogos `tbespecie`, `tbanimaltipo` (con `sexo` M/H o NULL) y `tbraza` (todos con `activo`; se gestionan en `/admin/catalogos`, ver "Mis animales y catálogos") y `tbanimalpublicacionanimal` (enlace de lote). Migración `021modeloanimal.sql`; semilla `104catalogosanimal.sql` (en `compose.yaml`, `instalar-local.*` y `Tools/db-aislada.sh`) y `seedCatalogs()` en `migrate.php`. **Solo se siembra una tabla vacía**: lo que el admin cambie o desactive no se reinserta.
- Columnas nuevas en `tbanimal` (todas NULL): `tbespecieid`, `tbanimaltipoid`, `tbrazaid`, `tbanimalfechanacimiento` (+ `…estimada`), `tbanimalpartos`, `tbanimalestado` (`ACTIVO|PUBLICADO|VENDIDO|INACTIVO`) y `tbproductorid` (dueño explícito). `tbanimalraza` (texto) se conserva y se llena con el nombre de la raza de catálogo.
- **API `GET api/v1/catalogos?especieId=`** (sesión; `CatalogosController`): `{ especies, tipos, razas }` activos (con `especieId` filtra tipos y razas). Los formularios la leen una sola vez sin filtro.
- **`POST api/v1/publicaciones` acepta, todo opcional:** `especieId`, `tipoId`, `razaId`, `fechaNacimiento` (AAAA-MM-DD) + `fechaNacimientoEstimada`, `partos`, `arete` y `loteCantidad` (2-100). Validaciones en `AnimalValidacionService` (422 por campo): tipo y raza activos y de la especie (el tipo/raza exige `especieId`); el **sexo se deriva del tipo** y si se envía debe coincidir; `partos` entero 0-30 solo si el sexo es HEMBRA; fecha válida y no futura (hora de Costa Rica), "estimada" exige fecha; un lote no lleva `arete` ni `partos`. Sin ninguno de estos campos todo funciona como antes (`animalIdentificacion` y `raza` libres siguen igual).
- **Arete SENASA (supuestos):** 13 dígitos = `188` + 1 dígito de control + provincia `01`-`07` + 7 de correlativo (`1880010002345`). Se valida **solo** cuando llega en el campo nuevo `arete` (PHP: `AnimalValidacionService::arete`; JS: `shared/arete.js`); se aceptan espacios y guiones y se guardan **solo dígitos** en `tbanimalidentificacion`. Supuesto anotado: la provincia es 01-07 y el 4.º dígito se acepta tal cual (el cliente no lo definió). Es opcional: los animales con identificación libre (`SOL-xxxx`) siguen existiendo. **Unicidad en PHP** (bajo el lock de `tbanimal`): 409 si hay otro animal vigente (no `VENDIDO` ni `INACTIVO`) con el mismo arete; un animal vendido libera el suyo.
- **Lote:** una publicación apunta a su animal principal (`tbanimalpublicacion.tbanimalid`, todo lo anterior funciona igual). Un lote de N crea N animales iguales (cada uno con su observación de edad/peso) y N filas en `tbanimalpublicacionanimal` (incluido el principal); un animal solo no tiene filas. La API devuelve `loteCantidad` (1 = un animal) y, al crear, `animalIds` si es lote. `AnimalComercial::animalesDePublicacion($id)` da los ids.
- **Venta de un lote:** `SolicitudesCompraController::aceptar` registra `tbcompra` y `tbventa` **por animal** (snapshot propio de raza/edad/peso/propósito); el precio se reparte en partes iguales en centavos (el primero absorbe el resto) y los animales quedan `VENDIDO`. `CompraSolicitud::datosParaVenta` devuelve además `animales`. Una publicación de un animal registra exactamente lo mismo que antes.
- **Lectura:** cada publicación trae `loteCantidad` y `animal.{especieId, especie, tipoId, tipo, razaId, fechaNacimiento, fechaNacimientoEstimada, partos, estado}` (null si el animal es anterior a P2-2).
- **Publicar:** listas encadenadas especie → tipo y raza (con "Otra (la escribo)"), sexo fijado por el tipo, fecha de nacimiento (con "estimada"), partos solo si es hembra, arete con máscara y "Publicar un lote". Si el catálogo no carga, queda la raza en texto. Tarjetas (`buildCard`): Tipo (especie · tipo), edad calculada desde la fecha ("(aprox.)" si es estimada), partos y, en lotes, "Lote de N" junto al precio y una fila "Lote".
- Cambió una decisión vieja: antes tres pruebas **prohibían** `tbanimalfechanacimiento` ("sin pasado inventado"). La columna es opcional y nunca se llena sola; se ajustaron `db_ready_test`, `naming_eval` y `schema_eval`.

### Historial de vacunación (P2-3; DEC-ANIMAL-002)
- Esquema de **43 tablas**: `tbvacuna` (catálogo con `activo`; 10 vacunas comunes del ganado) y `tbanimalvacunacion` (animal, vacuna, fecha, dosis, lote, aplicada por, próxima dosis, observaciones). Migración `022vacunacion.sql`; las vacunas también están en `104catalogosanimal.sql` y en `seedCatalogs()` (solo se siembran si la tabla está vacía). `GET api/v1/catalogos` devuelve además `vacunas`.
- **API `api/v1/mi-animales/vacunas`** (sesión; `MiAnimalesVacunasController`, modelo `AnimalVacunacion`): `GET ?animalId=` lista (más reciente primero), `POST { animalId, vacunaId, fecha, dosis?, lote?, aplicadaPor?, proximaDosis?, observaciones? }` registra (201) y `PATCH { vacunacionId, ...campos }` corrige solo lo enviado (`null` borra un opcional). No hay DELETE ni cambio de animal.
- Reglas: animal **propio** = dueño explícito (`tbanimal.tbproductorid`) o animal de una publicación suya (principal o del lote); ajeno o inexistente → 404; sin la actividad Vendedor → 409; animal `VENDIDO`/`INACTIVO` → 409. Fecha no futura, próxima dosis no anterior, vacuna existente y activa (422 por campo). Bitácora `ANIMAL_VACUNACION` / `API_MI_ANIMALES_VACUNAS` (`CREAR`, `ACTUALIZAR`).
- **Lectura pública acotada:** `GET api/v1/publicaciones` agrega `vacunas: [{ vacuna, fecha, proximaDosis }]` a cada publicación de la página (nunca lote del biológico, quién la aplicó ni observaciones; máx. 8; en un lote, una fila por vacuna sumando sus animales). `AnimalComercial::adjuntarVacunas`. No se agrega en `api/v1/publicaciones/interacciones` (Me interesa).
- La tarjeta (`buildCard`, completa y compacta) las muestra en un bloque desplegable "Vacunas (N)" con la fecha y la próxima dosis.
- La pantalla para registrar vacunas está en Mi panel → "Mis animales" (P2-4).

### Mis animales (P2-4) y catálogos admin (P2-6)
- **API `api/v1/mi-animales`** (sesión; `MiAnimalesController`): `GET` inventario (`AnimalComercial::inventarioPropio`: dueño explícito o animal principal de una publicación suya; los demás animales de un lote no se listan sueltos; con la última observación, la publicación más reciente y cuántas vacunas tiene). `POST` registra un animal sin publicar (`tbanimalestado = ACTIVO`, `tbproductorid` = el vendedor, valida con `AnimalValidacionService::validar`; un lote → 422; arete repetido → 409). `PATCH { animalId, accion: 'PUBLICAR', fincaNombre, titulo, precio?, descripcion?, imagenUrl? }` publica un animal `ACTIVO` propio (ajeno → 404; ya publicado → 409) y lo marca `PUBLICADO`. Bitácora `ANIMAL` y `PUBLICACION` con origen `API_MI_ANIMALES`.
- **El estado del animal sigue a su publicación** (`cambiarEstadoPublicacion`): `ACTIVO`/`PAUSADO` → `PUBLICADO`, `VENDIDO` → `VENDIDO`, `RETIRADO` → vuelve a `ACTIVO` (al inventario, se puede publicar de nuevo).
- **Editar y pesos (11/10):** `PATCH { animalId, accion: 'EDITAR', ...campos del alta }` reemplaza los datos del animal con los del formulario (lo que no llega queda vacío), salvo dos excepciones: **sin la clave `arete` se conserva la identificación** (los animales viejos tienen una libre, `SOL-…`, que el formulario no muestra) y sin `raza` ni `razaId` se conserva la raza escrita a mano. Arete de otro animal vigente → 409. Peso, edad o propósito distintos de los vigentes **agregan una observación** (`tbanimalproduccionsalud`); el historial nunca se reescribe. `PATCH { animalId, accion: 'PESAR', peso, fecha? }` agrega un pesaje (fecha no futura; hoy por omisión, y entonces queda como vigente) copiando el propósito y estimando la edad desde la observación vigente. `GET ?animalId=` devuelve `observaciones` (la más reciente primero). Solo animales propios y no `VENDIDO`/`INACTIVO` (409). Bitácora `ANIMAL` con `ACTUALIZAR` y `PESAR`. El inventario devuelve también `razaId`. Pantalla: botones "Editar" (mismo diálogo del alta, relleno) y "Pesos" (historial + alta rápida) en Mis animales (`mis-animales.js?v=animales-5`, `panel-15`).
- **En subasta:** un animal en una subasta abierta (`tbanimalestado = SUBASTA`) no se edita, pesa ni publica (409) y en Mis animales muestra "En subasta" con "Ver subasta"; uno `ACTIVO` muestra "Subastar" (`subastas?nueva=<id>`). `mis-animales.js?v=animales-6`, `panel-16`.
- **Ojo:** el peso vigente es la **última observación por fecha**, y de ella salen también la edad y el propósito de las tarjetas. Toda observación nueva debe copiar los datos vigentes que no cambian (como hacen `EDITAR` y `PESAR`); una con solo el peso borraría la edad y el propósito de la tarjeta.
- **Pantalla:** sección "Mis animales" en Mi panel (módulo `Public/js/mis-animales.js`, importado desde `mi-actividad.js`): registrar (especie → tipo y raza, sexo fijado por el tipo, partos solo hembras, arete con máscara), vacunas (historial + alta rápida con `api/v1/mi-animales/vacunas`) y Publicar (finca, título, precio, descripción y foto).
- **Catálogos en el panel:** `/admin/catalogos` y API `api/v1/admin/catalogos` (solo administrador; `AdminCatalogoController`): `GET` las cuatro listas con inactivos, `POST { catalogo: ESPECIE|TIPO|RAZA|VACUNA, nombre, especieId (tipo y raza), sexo: M|H|null (tipo) }`, `PATCH { catalogo, id, nombre?, activo? }`. **Sin DELETE** (los animales los referencian) y **sin cambiar la especie ni el sexo** después de crear. Nombre único por especie sin distinguir mayúsculas (409). No se reactiva un tipo o raza de una especie inactiva (409). Bitácora `CATALOGO` (`CREAR`, `ACTUALIZAR`, `DESACTIVAR`, `REACTIVAR`), registro `TIPO:5`.

### Subastas en tiempo real (DEC-SUBASTA-001)
- **Qué hay:** un vendedor abre una subasta con un animal `ACTIVO` de su inventario (finca, título, descripción, foto, precio base,
  incremento mínimo y duración de 5 minutos a 30 días). Se ve en Explorar (banda "Subastas en vivo"), en `/subastas` (Activas,
  Historial, Mis subastas, Donde pujé) y en `/admin/subastas`. Varias subastas abiertas y varias pujas a la vez, sin límite.
- **Esquema de 47 tablas:** `tbsubasta`, `tbsubastapuja`, `tbsubastacomentario` y `tbnotificacion`, en los 4 lugares (migración
  `023subastas.sql`; las siguientes empiezan en **024**). Estado del animal nuevo: `SUBASTA` (`AnimalComercial::ESTADOS_ANIMAL`).
- **Tiempo real = Supabase Realtime (Broadcast), sin librerías ni servidor propio.** El navegador abre un WebSocket nativo con la
  clave pública (`shared/tiempo-real.js`: `escuchar(tópico, fn)`, `alCambiarConexion`, `agrupar`); PHP avisa **después del COMMIT**
  por la API REST de Realtime con `SUPABASE_SECRET_KEY` (`TiempoReal::avisar`). Tópicos `subastas`, `subasta-<id>` y
  `notificaciones-<personaId>`. **El aviso no lleva datos**: quien lo recibe vuelve a pedir el estado a la API (los canales son
  públicos; un mensaje falso solo provoca una recarga). Además las pantallas consultan cada 15-30 s. Si Realtime falla, la petición
  no falla (se anota en el log). Probado contra el proyecto real de Supabase (cliente Node con `tiempo-real.js` + aviso desde PHP).
- **API `api/v1/subastas`** (sesión de Persona; `SubastasController`): `GET ?vista=ACTIVAS|HISTORIAL|MIAS|PARTICIPO&q&pagina&tamanoPagina`,
  `GET ?id=` (detalle con `pujas`, `comentarios`, `minimoSiguiente`, `puedoPujar`, `motivoNoPuja`, `puedoCancelar` y `trato`),
  `POST` abre, `PATCH { subastaId, accion: PUJAR (monto) | COMENTAR (texto) | CANCELAR }`. Todas las respuestas traen `ahora` (hora
  del servidor, para los contadores). Errores por campo como el resto (Cuidados #14).
- **Reglas:** abrir exige Vendedor activo, finca activa propia y animal propio `ACTIVO` (409 si no); pujar exige Comprador activo; ni
  el vendedor, ni un **administrador** (correo activo en `tbadministrador`, 403 en abrir/pujar/comentar), ni quien ya va ganando
  pueden pujar. Primera puja ≥ precio base; siguientes ≥ más alta + incremento (422 en `monto` con el mínimo). Cancelar: solo su
  dueño, activa y sin pujas. Quien queda superado recibe un aviso `SUBASTA_SUPERADA`.
- **Cierre sin tarea programada:** cada petición a subastas, a la campana o al admin llama primero a
  `SubastaCierreService::cerrarVencidas()`; el contador del navegador consulta al llegar a cero. Con pujas → `FINALIZADA`, se
  registran `tbcompra` y `tbventa` (lugar "Subasta en línea", con el Comprador del ganador) y el animal queda `VENDIDO`; avisos
  `SUBASTA_GANADA` (ganador) y `SUBASTA_FINALIZADA` (vendedor). Sin pujas → `DESIERTA`, el animal vuelve a `ACTIVO`, aviso
  `SUBASTA_DESIERTA`. La bitácora del cierre no lleva actor (la hace el sistema).
- **Trato:** solo el ganador y el vendedor de una `FINALIZADA` reciben `trato` (precio final, nombre/teléfono/correo del otro y la
  dirección completa de la finca). El ganador además pide `api/v1/fletes?subastaId=` y ve los fletes que cubren la finca **con el
  teléfono del transportista** (excepción consciente a "teléfonos solo con trato": aquí ya hay trato). Las pujas y comentarios
  muestran alias o "Nombre I." (`SubastasController::nombrePublico`); el admin ve nombres completos.
- **Campana:** `GET api/v1/notificaciones` (últimas 20 y `sinLeer`), `PATCH { accion: 'LEER' }`. `public-ui.js` (`public-16`) la pone
  junto al carrito y escucha `notificaciones-<personaId>` (el `personaId` sale del perfil en caché; sin él, consulta cada 60 s).
  Estilos `.public-bell*` en `public-product.css` (`product-10`, en todas las vistas públicas).
- **Bloqueos:** un solo `NamedLock` `tindercows_subastas` para las tres tablas de subasta; orden subastas → `tbanimal` (o
  `tbcompra`/`tbventa` al cerrar) → notificación → bitácora → transacción (Cuidados #12).
- **Admin:** cadena de caché subida a **`auth-gate-11`, `admin-13`, `login.js?v=front-15`** y la versión del script de las 13 vistas admin.

### Cambio de correo consistente (DEC-CORREO-001)
- Tabla `tbpersonacorreocambio` (esquema de **48 tablas**, migración `026personacorreocambio.sql`; **las siguientes empiezan en
  `027`**): persona, `sub` de la cuenta de Supabase, correo anterior y nuevo, estado `PENDIENTE|APLICADO|CANCELADO` y fechas.
- `MiPerfilController` la escribe `PENDIENTE` **antes** de llamar a Supabase y **fuera** de la transacción del perfil (tiene que
  sobrevivir al ROLLBACK). Pasa a `APLICADO` dentro de la transacción que actualiza `tbpersona`; a `CANCELADO` si Supabase no
  cambió o si la restauración se comprobó. Si no se sabe qué pasó, queda `PENDIENTE`.
- **Fuente de verdad: Supabase.** `SupabaseActorResolver`, cuando el correo de la sesión no tiene Persona, llama a
  `CorreoCambioService::conciliar($sub, $correo, $rol)`: si esa cuenta tiene un `PENDIENTE` hacia ese correo, `tbpersona` avanza,
  se anota en la bitácora (origen `CONCILIACION_CORREO`) y queda `APLICADO`. Otra cuenta, otro correo, una Persona cambiada por
  otra vía o un correo que ya es de otra Persona **no** concilian (queda `PENDIENTE`, revisión manual).
- El resolvedor carga el servicio con `require_once` solo en ese caso (sesión sin Persona): las peticiones normales no pagan nada.
- Orden de bloqueos: alta de Persona → (histórico de teléfono) → bitácora → transacción. El de `tbpersonacorreocambio`
  (`tindercows_persona_correo_cambio`) solo lo toma `PersonaCorreoCambio::crear`, siempre el último y sin pedir otro dentro.
- `test_cleanup_productores()` borra también las filas de `tbpersonacorreocambio` de la Persona de prueba.

## 4. Cuidados (lo que ya rompió o puede romper)

1. **Columna nueva = 4 lugares** (SQL canónico, migración MySQL, `schema.sql`
   de Postgres y `migrate.php`). Una **tabla** nueva lleva además su `PRIMARY KEY (<tabla>id)`. Producción corre `migrate.php` al arrancar el
   contenedor; si falta ahí, el SELECT falla en producción.
2. **Versiones de caché `?v=`**: súbelas al cambiar CSS/JS. Si un módulo
   compartido gana un `export`, versiona su `import` donde se usa (ya pasa con
   `explore.js?v=foto-1`, `business-rules.js?v=panel-2`,
   `supabase-auth.js?v=session-2`, `auth-gate.js?v=auth-gate-6`; `public-ui.js` sigue con `auth-gate-5` porque
   no usa la lista de rutas privadas).
3. **Alias SQL siempre en minúscula** (`AS publicacionid`, nunca
   `AS publicacionId`). Postgres (producción) pasa a minúscula los alias sin
   comillas; MySQL (local) no, así que el error solo aparece en producción:
   PHP imprime `Warning: Undefined array key` antes del JSON y el navegador
   muestra "El servidor no devolvió una respuesta válida". La clave pública
   camelCase se arma en PHP al mapear la fila. `Tests/sql_alias_minuscula_test.php`
   falla si vuelve a aparecer un alias con mayúsculas.
4. **Finales de línea:** `git stash` (o un `git pull` en una copia con `core.autocrlf=true`) puede dejar archivos con
   CRLF en Windows. Las pruebas que buscan `\n` en `Public/css/base.css`, `Public/js/shared/api.js`,
   `Public/js/public-ui.js` y `Public/js/documentos.js` fallan con CRLF; normalizar con
   `sed -i 's/\r$//' <archivo>` (para git el contenido es idéntico).
5. **Supabase por defecto** solo envía correos a miembros del equipo y con
   límite por hora: un correo de confirmación puede no llegar nunca.
6. **Wikimedia** responde 429 si se piden muchas imágenes seguidas desde la
   misma IP (al verificar URLs con curl, espaciar las peticiones).
7. `node --test Tests/frontend/` (con carpeta) falla en Windows; usar el glob
   `Tests/frontend/*.test.mjs`.
8. Si cambias `Documentation/DER.md`, `DiccionarioDatos.md` o `Decisiones.md`,
   regenera los PDF con `python Tools/generate-documentation-pdfs.py`
   (`Tests/documentation_test.py` falla si quedan desactualizados).
9. La regla de colores es estricta: nada de colores nuevos; coral solo para la
   acción principal; texto secundario con `--tc-text-soft` (con `--tc-muted`
   no llega a AA en tema claro sobre los paneles).

10. **Vercel: ramas y registro de imágenes.** Solo `dev` y `main` despliegan
    (`git.deploymentEnabled` con `"**": false` en `vercel.json`); no quites esa
    regla ni vuelvas a `ignoreCommand`, que Vercel no ejecuta con `services`.
    Vercel lee el `vercel.json` de la rama que recibe el push: una rama vieja
    sigue desplegando hasta que incorpore `dev`. El registro admite 50
    imágenes; `.github/workflows/vercel-prune-registry.yml` lo poda en cada
    push a `dev` o `main`. Si ese workflow queda en rojo, el registro se vuelve
    a llenar y los despliegues fallan al publicar la imagen. El workflow usa
    el secreto `VERCEL_TOKEN` del entorno `vercel-registry` de GitHub; el
    entorno, su límite a `dev` y `main` y el secreto se configuran en GitHub,
    no en el repo.
11. **Ruta admin nueva = subir versiones en cadena.** El menú, sus íconos y la guarda de sesión llegan por
    `api.js` → `auth-gate.js` y `admin-ui.js` → `admin-refinements.css`. Al agregar una ruta admin: sube la versión de
    `auth-gate.js` y de `admin-ui.js`/`admin-refinements.css`, el import de `shared/api.js?v=…` en **todos** los módulos
    admin y el `?v=` de sus `<script>`. Si falta alguno, esa pantalla queda en blanco o sin el ícono nuevo.
12. **Bitácora y consecutivos: el bloqueo dura hasta el COMMIT.** Los ids salen de MAX()+1 (o del último +1)
    bajo `NamedLock`. Si un controlador escribe la bitácora dentro de una transacción, debe envolver **toda** la
    transacción con `Bitacora::ejecutarConBloqueoAlta` (y con el bloqueo de cada tabla cuyo id calcule); si no,
    el bloqueo se suelta antes del COMMIT y otra conexión repite el id (demostrado: dos eventos con id 56). El
    `FOR UPDATE` del último id no lo evita en Postgres. `NamedLock` es reentrante, así que anidar es seguro.
    `Tests/bitacora_bloqueo_test.php` falla si aparece un controlador nuevo sin envolver. **Orden fijo:** locks de las
    tablas (p. ej. `tbanimal` → `tbanimalproduccionsalud` → `tbanimalpublicacion` → `…estadoperiodo`) → bitácora →
    transacción; tomar la bitácora antes que una tabla puede dejar dos peticiones esperándose en cruz.

13. **Probar en un `git worktree` o con una base aparte** (dos personas o sesiones en paralelo): `docker compose exec app` solo ve el
    repositorio principal y la base `bdmercadoganadero`. Usa `sh Tools/php-test.sh [-d base] Tests/x_test.php` (corre el código de TU árbol) y,
    para un cambio de esquema, `sh Tools/db-aislada.sh <nombre>` (crea una base propia desde TU `000instalacioncompleta.sql`) y pásala con `-d`.
    Así un esquema a medias no rompe `instalacion_limpia_test` ni la app de quien trabaja al lado. Limitación: las pruebas que llaman por HTTP
    (`instalacion_limpia_test`, `*_http_test`) necesitan Apache y solo corren en el repositorio principal.

14. **Validar cada dato que entra (lo que ya dio 500 o guardó basura).** `Tests/validacion_entradas_test.php` manda a cada
    campo de los formularios una lista, un objeto, `true`, un texto de 5000 caracteres y números fuera de rango, y exige 4xx
    con un mensaje por campo. Al agregar un campo o un endpoint, súmalo ahí. Reglas:
    - **Nunca `(string) $cuerpo[...]` sin revisar el tipo**: una lista se guarda como el texto "Array" (y el aviso de PHP rompía el JSON).
    - **Enteros con `max_range => 2147483647`** (los ids son `INTEGER` en Postgres: uno mayor hace fallar la consulta) y
      **rechazar booleanos** (`filter_var(true, FILTER_VALIDATE_INT)` devuelve 1).
    - **Números con tope**: precio ≤ 9.999.999.999,99 (`NUMERIC(12,2)`), peso ≤ 5000 kg, edad ≤ 600 meses (`AnimalPublicacionController::MAXIMOS`).
    - **Todo texto con largo máximo** igual o menor al de su columna (si no, Postgres/MySQL estricto responden 500).
    - El sexo del animal solo acepta `HEMBRA`/`MACHO` (o `H`/`M`).
    - `readJsonBody()` rechaza (400) cuerpos de más de 1 MB y caracteres de control (Postgres no guarda el carácter nulo).
    - Las imágenes Docker cargan `docker/php-errores.ini` (`display_errors = Off`, errores al log): un aviso nunca sale en la respuesta.
    - Mensajes: dicen qué falta y cuánto (`ValidacionService::mensajeLongitud`: "Este campo es obligatorio.", "No puede superar N
      caracteres."); un 422 siempre trae `errors` por campo. En el navegador, `erroresSinLugar()` / `mostrarErroresSinLugar()`
      (`shared/field-errors.js?v=errores-1`) muestran los errores de campos que el formulario no tiene en pantalla.
    - Las pruebas que usan "hoy" deben usar `AnimalValidacionService::hoy()` (Costa Rica), no `gmdate`: de 6 p. m. a
      medianoche UTC ya es mañana y la fecha cuenta como futura.

15. **Tiempo real: avisar después del COMMIT y nunca confiar en el mensaje.** `TiempoReal::avisar()` va fuera de la transacción
    (si se avisa antes, otra pantalla recarga y todavía no ve el cambio). El navegador trata cada aviso como "algo cambió" y vuelve a
    pedir el estado: no agregues datos al aviso ni los uses para pintar (los canales de Broadcast son públicos). Una pantalla nueva que
    quiera tiempo real: `escuchar('<tópico>', agrupar(recargar))` + una consulta periódica de respaldo.

## 5. Pendientes

Plan completo, priorizado y repartido entre Carlos, Jeremi y Jeferson:
`Documentation/Sprints/Plan-Cliente-Admin-2026-10.md`. Los puntos de abajo
están incluidos ahí.

### Backend (para el compañero de backend)
- ~~Editar nombre, identificación o correo~~, ~~historial de pesos y editar animal~~ y ~~filtros de Explorar en el
  servidor~~: **resueltos** el 11/10.
- El filtro de **propósito** de Explorar (chips) sigue filtrando solo la página cargada (25).
- **Subastas, descartado por ahora (DEC-SUBASTA-001):** extender el cierre si alguien puja en los últimos segundos (anti-sniping),
  subastar lotes, que el admin cancele o modere una subasta y canales privados de Realtime con políticas (necesitan configurar
  "Realtime authorization" en Supabase). **Sin probar en el navegador con sesiones reales:** revisar a mano con 2-3 cuentas.

### Pendiente de P2-5 (va con P2-6)
- ~~Crear el bucket privado `documentos`~~: **resuelto** (06/10).
- Al reemplazar el documento, el archivo anterior queda en el bucket (no hay política de borrado para la persona). La
  limpieza de 90 días de P2-6 debe borrar también los archivos que ya no están en `tbpersonadocumentoruta`.
  **Sigue sin hacerse** (06/10): borra archivos del bucket de producción y necesita una tarea programada; se deja para
  acordarla con Dilan.
- ~~`SUPABASE_SECRET_KEY` en Vercel~~: **resuelto** (confirmado el 11/10).
- Los detalles menores de la revisión de P1-1 (marcar dos veces guardaba dos filas; la lista de guardados ordenada por
  fecha de publicación) se corrigieron el 11/10.

### Frontend (pendiente de P1-5)
- (Resuelto con P1-2: la foto del vehículo ya se muestra en las filas de fletes.)

### Configuración de Supabase (panel, no código)
- ~~Bucket `publicaciones` público + política de subida~~: **resuelto** (el bucket ya existe en Supabase; lo usan las fotos de publicaciones y de perfil). Si hubiera que recrearlo:
  ```sql
  create policy "Subir imágenes propias" on storage.objects
    for insert to authenticated
    with check (bucket_id = 'publicaciones' and (storage.foldername(name))[1] = auth.uid()::text);
  ```
- **Bucket privado `documentos` (P2-5): ya creado (06/10).** Si hubiera que recrearlo, en el SQL Editor:
  ```sql
  insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
  values ('documentos', 'documentos', false, 5242880,
          array['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
  on conflict (id) do nothing;
  create policy "Subir documento propio" on storage.objects
    for insert to authenticated
    with check (bucket_id = 'documentos' and (storage.foldername(name))[1] = auth.uid()::text);
  ```
  Sin políticas de lectura, cambio ni borrado para las personas: solo el servidor, con la clave secreta, lo lee (P2-6).
- **"Confirm email" está DESACTIVADO** (comprobado el 05/10: `mailer_autoconfirm = true`). ⚠️ **Riesgo abierto:** la app
  vincula la sesión con la Persona **solo por el correo** (`SupabaseActorResolver::personaIdPorCorreo`), así que
  cualquiera puede registrarse en Supabase con un correo ajeno que aún no tenga cuenta y entrar como esa Persona. Lo
  mismo con `tbadministrador`: un correo agregado como admin **sin cuenta** lo reclama quien se registre primero.
  Cerrarlo: reactivar la confirmación (con SMTP propio, Resend/Brevo/SendGrid, y el Site URL de producción) o hacer
  P0-1 (vincular por el `sub` del JWT). Mientras tanto, agregar como admin solo correos que ya tengan cuenta.
  **Decisión del 06/10: el equipo acepta este riesgo**; no se hará P0-1 por ahora.

### Frontend
- Se quitó el botón **Pasar** de las tarjetas (un toque accidental ocultaría la publicación para siempre; el scroll ya cumple esa función). El API sigue aceptando el tipo `PASAR`, pero ninguna pantalla lo envía.
- `renderSummary()` en `registro.js` es código muerto anterior.

## 6. Estado de pruebas

**Frontend: pasan todas (436/436 el 12/10/2026, rama `fix/xampp-linux-local`).** Las 4 que fallaban desde antes ya
no fallan. **PHP: 80/80 archivos `Tests/*_test.php` en XAMPP real** (PHP 8.0.30, MariaDB 10.4.32) y 92/92 contando los
`*_eval.php`, `*_gate.php`, `Database/Tests/*.php` y `services/supabase-database`. Cualquier fallo es una regresión, salvo las
excepciones de entorno de abajo.

**Pruebas PHP que fallan por el entorno o a veces** (no son regresiones):
- `Tests/vercel_prune_registry_test.php`: falla también en commits anteriores (la poda termina con código 2 en este entorno Windows).
- `Tests/registro_publico_test.php` y `Tests/solicitudes_compra_test.php` daban a veces 200 en vez de 201. **Causa encontrada (12/10):** `mi_vehiculos_test` dejaba filas de `tbcomprador` sin Persona y el siguiente id de Persona las heredaba. Ya limpia sus compradores; si vuelve a pasar, busca otra prueba que deje huérfanos.
- `Tests/api_identidad_test.php` imprime "Todos los tests de api_identidad pasaron" en vez de "OK ...": es un éxito.
- `Tests/api_productores_ubicacion_http_test.php` termina sin salida a propósito (tiene un `return;`).

**Ojo: dos pruebas vacían la bitácora local.** `Tests/transaction_test.php` y `Tests/pagometodo_test.php` achican
por un momento `tbbitacorasolicitudid` a `VARCHAR(5)` para forzar un fallo, y antes borran **toda fila con
solicitud de más de 5 caracteres**, que son casi todas. Después de correr toda la batería, `/admin/bitacora` queda casi
vacía en local. No pasa en producción (ahí no se corren las pruebas). Arreglarlo exige otra forma de forzar el fallo.

`Tests/registro_publico_test.php` falló una vez (201 esperado, 200 recibido) y
pasó al repetirla: parece intermitente, no relacionada con los alias.

## 7. Registro de cambios

Agrega entradas nuevas **arriba**. Formato: fecha · rama · resumen · archivos clave · cuidados.

### 2026-10-12 · fix/xampp-linux-local · Cierre para Calidad: correo consistente, controladores divididos y migraciones repetibles
- **Cambio de correo (DEC-CORREO-001):** tabla `tbpersonacorreocambio` en los 4 lugares (48 tablas), modelo `PersonaCorreoCambio`,
  `CorreoCambioService` y conciliación en `SupabaseActorResolver` (ver "Cambio de correo consistente"). `SupabaseAuthAdmin` ganó
  `correoConfirmado()`. Pruebas: nueva `Tests/perfil_correo_conciliacion_test.php` (10 puntos de fallo; sin la conciliación falla con
  409). Comprobado además contra Apache de XAMPP y el Supabase de pruebas con la cuenta sintética: 16/16 pasos, incluida una
  interrupción real (Supabase con el correo nuevo y `tbpersona` con el anterior) que la primera petición concilió.
- **Controladores (observación de Calidad):** `ProductorController` pasó de 582 a 396 líneas: la dirección principal
  (`api/v1/productores/direccion`) tiene su controlador, `ProductorDireccionController` (`procesar()`; ya no existen
  `procesarDireccion()` ni `crearDireccion()` en `ProductorController`). `AnimalPublicacionController` pasó de 587 a 492: los
  validadores de campo (`texto`, `numero`, `validarTodos`, `imagenUrl`) viven en `Application/Service/CampoValidacion.php`.
  **Ningún controlador carga ya a otro controlador.** Sin cambios en rutas ni en respuestas JSON.
- **Migraciones:** la `009` fallaba (error 1146) en toda base ya normalizada: MySQL resuelve la tabla de una subconsulta aunque
  el `IF` no tome esa rama; ahora usa `PREPARE`. `tbadministrador` nunca tuvo migración: la `025` la crea si falta. Y la `025`
  **no era todo-o-nada en phpMyAdmin**: su importación sigue después del error de la comprobación y dejó 11 llaves declaradas con
  un identificador repetido; ahora cada `ALTER` exige `@repetidos = 0`. Prueba nueva `Tests/migraciones_cadena_test.php`.
- **Respaldos antiguos:** `Avance03` (15 tablas, sin llaves) restaurado en MySQL 8.0 y migrado de la `006` a la `026`: 48 tablas,
  48 llaves, mismas filas y misma estructura que una instalación limpia. Procedimiento en `Documentation/Respaldos.md`.
- **phpMyAdmin:** la 025 y la 026 importadas por su formulario sobre MariaDB 10.4.32 (base anterior con datos, repetición y caso
  con un identificador repetido). **PostgreSQL 16:** `migrate.php` sobre una base con el esquema de `origin/dev` (47 tablas, 0
  llaves) dos veces → 48 tablas, 48 llaves, RLS en las 48; con un id repetido deja esa tabla sin llave, avisa y no tumba el
  arranque; conciliación de correo probada también ahí.
- `Database/Tests/personacapacidades_eval.php` fijaba 15 tablas desde hace meses (fallaba sin que nadie lo corriera): ahora 48.
- Cuidados: una tabla nueva también va en `test_cleanup_productores()` si cuelga de la Persona. Si agregas un `ALTER` a una
  migración con comprobación previa, condiciona el `ALTER` mismo: phpMyAdmin no se detiene en el primer error.
- **Seguridad:** la clave secreta del Supabase de **pruebas** (`ganado-cerca-xampp-tests`, no producción) quedó escrita en un
  registro local de sesión de Codex y en archivos temporales; nunca entró a Git. Se redactó del registro. **Dilan decidió no
  rotarla**; si ese proyecto se sigue usando, rotarla en Settings → API Keys.
- **Navegador con sesión (cuenta sintética, Supabase de pruebas):** Chromium 152 y Firefox 153 automatizados, a 1366 px y 390 px:
  Explorar, Ajustes, Mi panel, Publicar, Fletes, Subastas y Me interesa cargan con 200, sin errores de JavaScript, sin scroll
  horizontal y con cada campo visible etiquetado (el buscador del encabezado usa una etiqueta `screen-reader-only`). El único
  aviso de consola es el 403 esperado de `api/v1/admin/status` para una cuenta que no es administradora. **No se probó a mano**
  el llenado de formularios, los diálogos ni el panel de administración en el navegador.
- Revisión independiente (dos pasadas): un `PENDIENTE` de otra cuenta de Supabase o uno que no se puede completar ya no se
  cancela ni se anuncia como completado (409 "pida a un administrador que lo revise"); la conciliación tampoco avanza hacia el
  correo de un administrador. **Cuidado:** una base MySQL anterior sin la `026` responde 503 con el nombre de la migración a toda
  sesión sin Persona (registro y administradores sin Persona): aplíquela antes de usar la app. Si falta `SUPABASE_SECRET_KEY`, un
  intento de cambio de correo queda `PENDIENTE` y los siguientes responden 503 hasta configurarla.

### 2026-10-12 · fix/xampp-linux-local · Sesión vencida en XAMPP y SQL fuera de los controladores
- **Sesión vencida o cerrada = 401 también en XAMPP.** Sin el servicio Node, `SupabaseActorResolver` valida el JWT directo contra
  `SUPABASE_URL/auth/v1/user`, y Supabase responde **403** a un token inválido, vencido o de una sesión cerrada. PHP lo reenviaba
  y el navegador (que solo trata el 401 como sesión terminada) mostraba un error en vez de volver a Entrar. Ahora, en la
  verificación directa, 401 y 403 salen como **401**. Con `SUPABASE_AUTH_VERIFY_URL` (servicio Node) el contrato no cambia.
  Prueba en `Tests/auth_actor_test.php`. Se encontró probando el flujo real contra XAMPP, no en las pruebas con dobles.
- **Ningún controlador prepara ni ejecuta SQL** (observación de Calidad sobre MVC). Las 9 consultas que quedaban en 6
  controladores pasaron a sus modelos, sin cambiar respuestas: `Administrador::existeCorreo`,
  `ProductorFinca::listarActivasPorNombre` y `::nombresUsadosPorOtroProductor`, `AnimalComercial::publicacionActiva`,
  `Persona::buscarIdentidad` y `::capacidadIds`. Gate nuevo `Tests/controladores_sin_sql_test.php`: falla si vuelve a
  aparecer `->prepare(`, `->query(` o `->exec(` en `Application/Controller/`.
- Cuidado: un controlador que empieza a usar un modelo debe agregarlo a la lista `foreach ([...] as $modelo)` de su endpoint
  en `Public/api/` (lo exige `api_requires_test`); se agregó `Administrador` en `mi-perfil.php` y `subastas.php`.
- Pendiente en producción: `ensurePrimaryKeys()` no fija `lock_timeout` (47 `ALTER` con bloqueo exclusivo, una sola vez) y una
  tabla con ids repetidos queda sin llave hasta limpiarla. Si la bitácora de producción tiene los ids repetidos de Cuidados
  #12, hay que corregirlos a mano para que tenga su llave.
- ~~`ProductorController` y `AnimalPublicacionController` de ~580 líneas~~: divididos el 12/10 (ver el registro "Cierre para
  Calidad").
- Comprobado contra XAMPP real y el Supabase de pruebas: registro, reintento, correo distinto, datos inválidos, otra cuenta
  con la misma cédula, edición de perfil con histórico de teléfono, cierre de sesión y nuevo login, mirando las filas en MySQL
  (24 casos). Storage con dos usuarios: 19 casos (carpeta ajena, sobrescritura, borrado, anónimo, documento privado).

### 2026-10-12 · fix/xampp-linux-local · Una llave primaria por tabla (DEC-CALIDAD-PK-001)
- **Regla corregida.** Calidad pide: "Cada tabla debe tener su llave primaria. No trabajamos con claves foráneas y
  restricciones de negocio en la BD". El proyecto lo había leído como "cero claves". Ahora las **47 tablas** declaran
  `PRIMARY KEY (<tabla>id)` sobre el identificador que ya tenían; no hay columnas nuevas. Siguen prohibidos FK, UNIQUE, CHECK,
  DEFAULT, AUTO_INCREMENT, índices adicionales, triggers y rutinas, y **PHP sigue generando los ids** con `MAX+1` bajo `NamedLock`.
- Lugares: `000instalacioncompleta.sql`, migración `025llavesprimarias.sql` (MySQL/MariaDB), `schema.sql` de Postgres y
  `ensurePrimaryKeys()` en `migrate.php`. **Las migraciones siguientes empiezan en `026`.**
- **Tabla nueva = trae su `PRIMARY KEY (<tabla>id)`** en el SQL canónico y en `schema.sql`; si no, fallan `schema_test`,
  `naming_gate` y las pruebas de `services/supabase-database`.
- La migración 025 cuenta primero los ids repetidos y se detiene antes del primer `ALTER` si hay alguno
  (`MIGRACION_ABORTADA_IDENTIFICADORES_REPETIDOS`); se puede repetir. En Postgres, una tabla con ids repetidos o nulos se
  deja sin llave y `migrate.php` escribe `supabase_primary_key_pendiente tablas=...` en la salida de error **sin tumbar el
  despliegue**: revisar ese aviso en los logs de Vercel después del primer despliegue con este cambio.
- Gates que cambiaron de "cero restricciones" a "solo la llave primaria": `Tests/schema_test.php`, `naming_gate.php`,
  `naming_eval.php`, `pagometodo_test.php`, `Database/Tests/personacapacidades_gate.php`, `comprobacionestructura.sql`,
  las pruebas y la evaluación de `services/supabase-database`, `Tools/diagnostico-local.php` (`llaves_primarias`) y
  `Tools/test-restore.sh`. **Los respaldos anteriores (`Database/Backups/`) no tienen llaves:** `test-restore.sh` los rechaza
  hasta que se genere un respaldo nuevo.
- Documentación: `AGENTS.md`, README, `Decisiones.md` (DEC-CALIDAD-PK-001; DEC-C04-002 queda sustituida en parte),
  `GuiaDefensa.md`, `Respaldos.md`, guía de XAMPP, DER (marca `PK`) y diccionario. De paso se agregaron al diccionario las
  fichas de `tbadministrador`, los dos históricos de teléfono y `tbanimalpublicacioninteraccion`, y al DER
  `tbadministrador` y `tbanimalpublicacioninteraccion`, que nunca estuvieron.
- Comprobado: instalación limpia (instalador y SQL) y migración de una base con datos en MariaDB 10.4.32 y MySQL 5.7.44;
  base nueva, esquema anterior e id repetido en PostgreSQL 16. **No se aplicó a producción.**

### 2026-10-12 · fix/xampp-linux-local · La app corre en XAMPP real (PHP 8.0, MariaDB 10.4, MySQL 5.7)
- **Mínimo real: PHP 8.0.** Los Dockerfiles siguen en PHP 8.3 (despliegue), pero ya no es el mínimo. Se quitaron las clases y
  propiedades `readonly` (8.1/8.2), `fn (): null`, las referencias `f(...)` y la expansión de arreglos con claves de texto.
  `ActorContext` y `HttpException` siguen siendo inmutables con el trait `Application/ReadOnlyProperties.php`.
  `Configuration/Compatibility.php` corta con un mensaje claro en PHP < 8.0 y define `array_is_list()` si falta.
  **No uses sintaxis de PHP 8.1+**: `Tests/runtime_compatibility_test.php` y el lint con `/opt/lampp/bin/php -l` lo detectan.
- **UTC fijo:** `Configuration/Configuration.php` llama `date_default_timezone_set('UTC')`. El `php.ini` de XAMPP trae
  Europe/Berlin y mezclaba fechas del negocio.
- **JSON pasa a `LONGTEXT`** en las 4 columnas (`tbbitacoradatosanteriores`, `tbbitacoradatosnuevos`,
  `tbanimalproduccionsaludproduccion`, `tbanimalproduccionsaludsalud`): en MariaDB el alias `JSON` crea un
  `CHECK(json_valid(...))` implícito. PHP sigue validando y codificando. Migración `024jsontextportable.sql` (solo MySQL/MariaDB;
  Postgres no cambia). Las migraciones siguientes empiezan en `025`.
- **SQL portable:** la última observación del animal ya no usa `ROW_NUMBER() OVER` (no existe en MySQL 5.7) sino
  `NOT EXISTS` de una posterior por fecha e id (`AnimalComercial`, dos consultas; prueba `latest_observation_portability_test.php`).
  `CompraSolicitud::buscar` usa `FOR UPDATE OF s` solo en Postgres; MySQL/MariaDB usan `FOR UPDATE`.
- **Instalador:** `instalar-local.sh` usa siempre TCP con `DB_HOST`/`DB_PORT`, acepta `MYSQL_CLIENT` o `XAMPP_ROOT`, no ejecuta
  `sudo` ni cambia la contraseña de una cuenta existente. `Tools/diagnostico-local.php` (CLI, solo lectura) informa versiones,
  extensiones, UTC, conexión y estructura. XAMPP **no necesita Node ni Composer**.
- **Cambio de correo (Mi perfil):** `SupabaseAuthAdmin::cambiarCorreo` ya no acepta cualquier HTTP 200: exige el mismo `id`, el
  correo pedido y `email_confirmed_at`; ante un timeout consulta la cuenta. Si MySQL falla después de cambiar el correo en
  Supabase, `MiPerfilController` restaura el correo remoto (`recuperarCorreo`) solo cuando el ROLLBACK quedó confirmado; con el
  resultado local desconocido responde 503 y no compensa a ciegas. Prueba `perfil_correo_recuperacion_test.php`.
  **No está cerrado del todo:** si el `COMMIT` de MySQL falla después de que Supabase ya cambió el correo, el resultado local
  es desconocido, se responde 503 y **la cuenta puede quedar con el correo nuevo y la Persona con el viejo** (al entrar daría
  409 "sin vínculo"). Es raro, pero no hay herramienta de reconciliación: se arregla a mano alineando los dos correos.
  Además el cambio hace hasta 4 llamadas a Supabase dentro de la transacción y de los locks; con Supabase lento frena las
  demás escrituras.
- **Frontend portable:** sin `Array.at()` ni `Object.hasOwn()`; `shared/dialog.js` exporta `openDialog`/`closeDialog` con un modal
  de reserva si el navegador no tiene `<dialog>` nativo; Font Awesome 7.3.1 va **local** en `Public/vendor/` (agregado con
  `git add -f` porque `vendor/` está ignorado); los 13 paneles privados muestran un aviso con "Recargar" y "Volver a entrar" si la
  guarda de sesión no responde en 3,5 s (`shared/panel-runtime.js`, parcial `Application/View/shared/panel-runtime.php`).
  Se subieron los `?v=` de las 24 vistas y de los imports compartidos (`form-2`, `draft-2`, `pendiente-1`, `animales-8`).
- **Pruebas sin estado compartido:** `mi_vehiculos_test` dejaba `tbcomprador` huérfanos y un id de Persona reutilizado los
  heredaba (causa de los 200 en vez de 201 en `registro_publico_test`); `publicacion_interaccion_test` crea su propia
  publicación y ya no necesita la semilla demo; `admin_shell_ux` compara la cadena en vez de fijar `auth-gate-2`.
- **Comprobado (no en Docker):** XAMPP 8.0.30 en Linux (PHP 8.0.30 por `apache2handler`, Apache 2.4.58, MariaDB 10.4.32,
  phpMyAdmin 5.2.1): 78/78 pruebas PHP (77 al momento de este cambio), instalación desde cero por phpMyAdmin, 436/436 frontend. MySQL 5.7.44: importación y
  tres pruebas de publicaciones e inventario. Lint y `runtime_compatibility_test` en PHP 8.1, 8.2 y 8.3. El binario de XAMPP
  arranca en userspace de Ubuntu 18.04 y 20.04. **La versión del equipo del profesor sigue sin conocerse.**
- Supabase de pruebas `ganado-cerca-xampp-tests` (no es producción): buckets `publicaciones` (público) y `documentos` (privado)
  con las dos políticas INSERT por carpeta propia. Sus claves viven fuera del repositorio.
- Cuidado: Auth, Storage, Realtime y mapas siguen necesitando Internet; la portada local no demuestra que funcionen.

### 2026-10-12 · fix/xampp-linux-local · Guía reproducible para XAMPP Linux
- `Documentation/Instalacion-XAMPP.md` mantiene el repositorio fuera de `htdocs` y publica solo `Public/` con VirtualHost, importa
  desde cero el esquema canónico y las semillas, crea un usuario PDO local y verifica que `.env` responda 404. `README.md` enlaza
  la guía y elimina la alternativa insegura de servir la raíz como subcarpeta. Gate: `Tests/documentation_test.py`.

### 2026-10-12 · jefersonbustamante · Validaciones juntas en los formularios, teclas numéricas y login más claro
- **Todos los campos a la vez:** nuevo `Public/js/shared/validacion-nativa.js` (`nativa-1`): `erroresNativos(form)` devuelve cada campo inválido
  (required, min, max, step, type) con su motivo en español, y `mensajeCampo` dice qué corregir ("Escribe solo números.", "No puede ser negativo.",
  "No puede ser mayor que 5000.", "La fecha no puede ser futura.", "Elige una opción."). Lo usan **Publicar** (antes `reportValidity()`, una burbuja
  por intento), **Mis animales** (animal, publicar, vacuna y peso), **Mi panel** (editar publicación y vehículo), **Fletes** (oferta) y **Subastas**
  (abrir y pujar). Antes esos diálogos esperaban al servidor (un viaje por error) y un número con letras llegaba como "" y se leía como "a convenir".
- **Teclas:** el mismo módulo registra un `keydown` global (al importarlo) que impide escribir `e`, `+` y `-` en `<input type="number">` y el punto
  o la coma cuando el `step` es entero (el peso, con `step="0.1"`, sí admite decimales). La cédula y el teléfono ya filtraban sus caracteres.
- **Login:** un 400/401 de Supabase muestra "El correo o la contraseña no coinciden. Revisa que estén bien escritos e inténtalo de nuevo." y enfoca la
  contraseña. **A propósito no dice cuál de los dos falló** (decir "el correo no existe" permitiría enumerar cuentas; mismo criterio de DEC-REG-001).
- **Punto del mapa:** sin cambios; ya era opcional y debe caer en la provincia escrita (`validarPuntoConDireccion`) en oferta de flete, finca de Mi panel,
  fincas del registro y admin de productores. Revisado que los cuatro lugares lo llaman.
- Caché: `publicar.js` `publish-6`, `mi-actividad.js` `panel-18` (import `mis-animales.js?v=animales-7`), `fletes.js` `fletes-6`, `subastas.js`
  `subastas-4`, `login.js` `front-16`. `field-errors.js` **no** cambió (sigue `errores-1`): el módulo nuevo lo envuelve en vez de modificarlo.
- Pruebas: nueva `Tests/frontend/validacion_nativa.test.mjs`. Frontend 426 pasan y fallan solo las 4 conocidas; PHP 71 de 74 (las 3 son las conocidas:
  `vercel_prune_registry_test` y las dos intermitentes, que pasan al repetir).
- Cuidado: los formularios del **panel admin** siguen con `describeValidity` (mensajes genéricos de rango); si se quiere el mismo texto, importar
  `mensajeCampo` en `shared/form.js` y subir `form.js` y sus importadores. **Sin probar en el navegador con una sesión real.**

### 2026-10-11 · jefersonbustamante · Punto del mapa opcional en los fletes y validado contra la provincia
- Ver "Oferta de flete (P1-2)". Archivos: `MiOfertasController` (sin el punto obligatorio), `TransportistaOferta::listarCercanas` (latitud/longitud
  opcionales y `$provincia`), `FletesController`, `CompraSolicitud::ubicacionDeFinca`, nuevo `Public/js/shared/punto-direccion.js`, `fletes.js`
  (`fletes-5`), `mi-actividad.js` (`panel-17`), `registro.js` (`signup-12`), `productores.js` (`sections-9`), `subastas.js` (`subastas-3`) y
  `solicitud-compra.js` (`solicitud-3`; `interactions-5`, `interesa-6`).
- Revisado el mapa: MapLibre 6.9.0 (unpkg), estilo de OpenFreeMap, límites de Costa Rica y Nominatim responden 200.
- Pruebas: `mi_ofertas_test` (oferta sin punto por provincia) y nueva `Tests/frontend/punto_direccion.test.mjs`.

### 2026-10-11 · jefersonbustamante · Subastas en tiempo real (DEC-SUBASTA-001)
- Subastas con pujas y comentarios en vivo, cierre automático con ganador, avisos en una campana y vista de solo lectura para el admin
  (ver "Subastas en tiempo real"). Tiempo real con Supabase Realtime (Broadcast) y WebSocket nativo; sin dependencias nuevas.
- Esquema: 4 tablas nuevas en los 4 lugares + `Database/Migrations/023subastas.sql` (aplicada en la base local), diccionario, DER,
  `Decisiones.md` y los PDF regenerados (con un contenedor `python:3.12-slim`, no hay Python en la máquina). Conteo de 43 → 47 en
  README, GuiaDefensa, Respaldos, `Database/Tests`, `services/supabase-database` y las pruebas de esquema. `diagnostico.sql` revisa
  las referencias nuevas.
- Backend: `Subasta`, `Notificacion`, `TiempoReal`, `SubastaCierreService`, `SubastasController`, `NotificacionesController`,
  `AdminSubastasController`, endpoints `subastas.php`, `notificaciones.php` y `admin-subastas.php`; `FletesController` acepta
  `subastaId` (carga `Model/Subasta.php` él mismo) y `TransportistaOferta::listarCercanas` puede incluir el teléfono.
- Frontend: página `/subastas` (`subastas-1`), banda de Explorar (`subastas-vivo-1`), campana y enlace "Subastas" en
  `public-ui.js` (`public-16`, `product-10` en todas las vistas públicas), "Subastar" en Mis animales, `/admin/subastas` y la cadena
  admin (`auth-gate-11`, `admin-13`, `front-15`).
- De paso: `AnimalCatalogo::crear` usaba `->query()` (regla de sentencias preparadas, `naming_eval` en rojo): ahora `prepare()`.
  `publicacion_interaccion_test` ya no da por hecho que existe la publicación 1.
- Pruebas: nuevas `Tests/subastas_test.php` y `Tests/frontend/subastas.test.mjs`; ampliadas `validacion_entradas_test`,
  `api_auth_admin_http_test`, `admin_cache_chain`, `carrito`, `navegacion_publica` y `mis_animales`. SQL probado en Postgres 16 y
  `migrate.php` dos veces sobre una base limpia (47 tablas). Batería PHP en verde salvo las conocidas; frontend con solo las 4 conocidas.
- Cuidado: una base MySQL existente necesita `Database/Migrations/023subastas.sql`. **No se probó en el navegador con sesiones reales.**

### 2026-10-11 · jefersonbustamante · Filtros de Explorar en el servidor, editar animal e historial de pesos, editar identidad y arreglos de Me interesa
- Se trajo `origin/dev` (PR #44 y #45 de `backend`) por fast-forward antes de empezar.
- **Explorar:** ubicación, precio y lo ya marcado se filtran en la API (ver "Portada, Explorar y tarjetas"). Archivos: `AnimalPublicacionController::filtrosConsulta`, `AnimalComercial::listarPublicaciones` (parámetro `$filtros`), `explore.js` (`explore-13`; se quitaron `filterByLocation` y `filterByPrice`).
- **Me interesa:** marcar dos veces ya no guarda dos filas y la lista se ordena por cuándo se marcó (ver "Me interesa"). Archivos: `PublicacionInteraccionController`, `AnimalComercial::listarPublicaciones`.
- **Mis animales:** acciones `EDITAR` y `PESAR` y `GET ?animalId=` (ver "Mis animales (P2-4)"). Archivos: `MiAnimalesController`, `AnimalComercial` (`datosAnimal`, `actualizarAnimal`, `observaciones`, `existeAreteVigente` con exclusión, `razaId` en el inventario), `Public/api/mi-animales.php` (el GET recibe `$_GET`), `mis-animales.js` (`animales-5`), `mi-actividad.js` (`panel-15`), vista de Mi panel (diálogo `animal-weights-modal`).
- **Identidad:** nombre, identificación y correo editables desde Ajustes (ver "Foto de perfil y datos propios"). Archivos: nuevo `Application/Service/SupabaseAuthAdmin.php`, `MiPerfilController`, `Persona::actualizarPerfil`, `ajustes.js` (`ajustes-12`), vista de Ajustes.
- Sin cambios de esquema ni de PDF. Contrato de API ampliado (claves nuevas opcionales), documentado en `RutasPublicas.md`.
- Pruebas: ampliadas `api_publicaciones_test`, `mi_animales_test`, `api_mi_perfil_test` (con un transporte falso para Supabase), `validacion_entradas_test` (los campos nuevos), `perfil.test.mjs`, `mis_animales.test.mjs`, `explore_card_content.test.mjs` y `me_interesa.test.mjs`; `publicacion_interaccion_test` ahora lee el evento más reciente de la bitácora (fallaba con filas de corridas anteriores). Las consultas nuevas se ejecutaron contra Postgres 16 con `schema.sql`. Batería PHP completa en verde salvo las conocidas; frontend: 412 pasan y fallan solo las 4 conocidas.
- Cuidado: tras el pull, `public-ui.js`, `api.js` y `documentos.js` quedaron con CRLF y 3 pruebas fallaban; se normalizaron (Cuidados #4, ampliado).
- **Sin probar en el navegador con una sesión real.** El cambio de correo modifica la cuenta **real** de Supabase también desde local (la app local usa el mismo proyecto).

### 2026-10-10 · backend · Revisión de validaciones y mensajes de error
- Prueba nueva `Tests/validacion_entradas_test.php`: datos hostiles en publicar, editar publicación, Mis animales, vacunas,
  perfil, fincas, vehículos, ofertas de flete, solicitud de compra, Me interesa, búsqueda de fletes y de Explorar, registro y
  moderación del admin. Encontró y se corrigieron:
  - **Errores 500:** ids sin tope (Postgres `INTEGER`), precio/peso/edad sin máximo, sexo sin límite de largo, carácter nulo.
  - **Datos basura:** listas guardadas como "Array" (títulos, motivos, alias, nombre de finca), `true` tomado como el id 1 o
    como cantidad 1, una próxima dosis inválida guardada vacía, una acción de Me interesa inválida tomada como REGISTRAR.
  - **Mensajes poco claros:** 422 sin decir el campo (Explorar, editar o moderar publicación, búsquedas del admin),
    "El campo es obligatorio." cuando el dato sí venía pero con el tipo equivocado, "Debe contener entre 0 y N caracteres",
    "El precio debe ser mayor que cero." para un precio demasiado alto, "Valor no válido." en fletes.
- `Configuration::readJsonBody` (1 MB y caracteres de control), `docker/php-errores.ini` en `Dockerfile` y `Dockerfile.vercel`.
- `api.js`: tildes en los mensajes y textos para 403, 413, 429 y 5xx. Los formularios de cliente y los del admin (vía
  `bindFormErrors`) muestran los errores sin campo en pantalla. Versiones: `ajustes-10`, `fletes-3`, `panel-12`, `signup-11`,
  `mis-animales.js?v=animales-2`.
- Pruebas nuevas: `api_entrada_http_test.php` (por HTTP) y `Tests/frontend/errores_sin_lugar.test.mjs`. Se corrigió
  `mi_animales_vacunas_test` (usaba la fecha UTC).
- Cuidado nuevo: Cuidados #14.
- Publicar (tras probarlo a mano): el servidor devuelve **todos** los errores juntos (`AnimalPublicacionController::validarTodos`,
  también en Mis animales); el formulario marca cada campo con su mensaje debajo y el resumen lleva el nombre del campo
  ("Peso aproximado (kg): No puede ser mayor que 5.000."). Precio, peso y edad tienen `max` en el HTML (el navegador avisa
  antes de enviar). El aviso de éxito enlaza a **Mi panel → Mis publicaciones**: Explorar no muestra las propias. `publish-5`.
- `readJsonBody`: `{}` (formulario sin datos) ya no se confunde con una lista (antes respondía "El cuerpo debe ser un objeto
  JSON."); sus mensajes ya no usan "cuerpo" ni "JSON". Registrar un animal sin ningún dato → 422 "No se puede registrar un
  animal sin datos…" (y campos desconocidos → 422). La rueda del ratón ya no cambia un campo numérico enfocado
  (`public-ui.js` → `soltarNumeroConRueda`, `public-15`). Versiones: `panel-13`, `mis-animales.js?v=animales-3`.
- Línea de estado `.auth-status`: es **verde por defecto**, así que un error escrito con `textContent` salía verde. Los
  formularios de Mi panel, Mis animales, Ajustes y Fletes usan `mostrarEstado(nodo, texto, 'error'|'info'|'success')`
  (`shared/estado.js?v=estado-1`). Al escribir un error en una línea de estado, usa `'error'`. Versiones: `ajustes-11`,
  `fletes-4`, `panel-14`, `animales-4`.

### 2026-10-06 · backend · P2-4 Mis animales, P2-6 catálogos admin y arreglos para producción
- **P2-4:** API `api/v1/mi-animales` (`MiAnimalesController`, `AnimalComercial::inventarioPropio`) y sección "Mis
  animales" en Mi panel (`Public/js/mis-animales.js`, `mi-actividad.js?v=panel-11`). El estado del animal ahora sigue al
  de su publicación (retirada → vuelve al inventario). `AnimalPublicacionController::texto` y `::numero` pasaron a
  públicos para reutilizarlos. Ver "Mis animales (P2-4) y catálogos admin (P2-6)".
- **P2-6 catálogos:** `/admin/catalogos` (`catalogos.js`, vista `Application/View/catalogos`), API `api/v1/admin/catalogos`
  (`AdminCatalogoController`) y métodos admin en `AnimalCatalogo` (`TABLAS`, `listarAdmin`, `crear`, `actualizar`,
  `existeNombre`; un solo lock `tindercows_catalogo_alta`). Cadena de caché admin: **`auth-gate-10`, `admin-12`,
  `login.js?v=front-14`** y la versión del script de las 11 vistas admin; menú "Catálogos" (ícono `tags`) en todas.
- **Bitácora hasta el COMMIT en los 9 controladores antiguos** (Cuidados #12): su `transaccion()` ahora envuelve con
  `Bitacora::ejecutarConBloqueoAlta`; `AnimalPublicacionController` usa `conBloqueos()` (locks de tablas en orden fijo →
  bitácora → transacción). `PENDIENTES` de `bitacora_bloqueo_test` quedó vacía.
- **Búsquedas sin distinguir mayúsculas en Postgres:** todos los `LIKE` de los modelos usan `LOWER(columna) LIKE LOWER(:valor)`.
- "Quitar de Me interesa" queda en la bitácora como `RETIRAR` (antes `CREAR`).
- Base local: se aplicaron las migraciones `019` a `022` y la semilla `104` sin recrearla.
- Pruebas nuevas: `mi_animales_test.php`, `admin_catalogos_test.php`, `Tests/frontend/mis_animales.test.mjs` y
  `catalogos_admin.test.mjs`; `publicacion_interaccion_test` comprueba `RETIRAR`.
- Rutas en `RutasPublicas.md` y `RutasFrontend.md`; plan actualizado (P1-1, P1-3, P2-4, P2-5 y P2-6).

### 2026-10-06 · jefersonbustamante · Integración de `backend` (Jeremi) en la rama del frente
- Se fusionó `origin/backend` (documentos de identidad, lectura automática del número, bitácora con ids repetidos, cierre de sesión). Conflictos resueltos en: el menú de las 9 vistas admin (ahora **Fletes y Documentos**), `.htaccess`, `PRIVATE_ROUTES`, `ADMIN_DESTINATIONS`, `MODULES` de `admin-ui.js`, la prueba `admin_cache_chain`, versiones de las vistas públicas, `me-interesa.js` y esta memoria y el plan. Los dos PDF en conflicto se regeneraron con `Tools/generate-documentation-pdfs.py`.
- Cuidado: **dos ramas subieron `auth-gate-8` y `admin-10` con contenido distinto**; tras la fusión la cadena pasó a `auth-gate-9` y `admin-11` para que ningún navegador conserve una copia vieja.
- `AdminFletesController::moderar` ahora envuelve toda su transacción con `Bitacora::ejecutarConBloqueoAlta` (la prueba nueva `bitacora_bloqueo_test` de Jeremi lo exigió; ver Cuidados #12).
- Migraciones: `017` y `018` (backend) y `019` a `022` (frente) conviven sin choque de números; las siguientes empiezan en `023`.

### 2026-10-06 · jefersonbustamante · Integración de las sesiones paralelas (admin de fletes, pago, carrito, modelo de animal y vacunas)
- Se fusionaron `sesion-a-fletes-admin` (admin de fletes, método de pago visible, carrito como contador) y `modelo-animal` (P2-2 y P2-3). Conflictos resueltos solo en versiones de las vistas (`public-14`, `explore-12`, `home-8`, `publish-4`, `interactions-4`), rutas documentadas, esta memoria y una prueba.
- `me-interesa.js` sube su import a `explore.js?v=foto-4` (`interesa-5`) para que Me interesa muestre "Lote de N" y las vacunas.
- `Database/Tests/diagnostico.sql`: con `tbproductorcompradorid` y `tbpagometodoid` opcionales (DEC-COMPRA-001), el diagnóstico de referencias contaba el `NULL` como "sin destino"; ahora ignora los `NULL` y verifica también `tbcompradorid` y `tbcomprasolicitudid`. **Regla:** toda columna enlace que pase a aceptar `NULL` necesita `IS NOT NULL AND …` en `diagnostico.sql`.
- **Esquema: 43 tablas.** Una base MySQL existente necesita, en orden, las migraciones `019`, `020`, `021` y `022` (más la `017` y `018` de `backend`, que son de otra persona). Producción las cubre con `migrate.php`: comprobado contra Postgres 16 partiendo del esquema de `origin/dev` (35 tablas), dos corridas seguidas, 43 tablas, catálogos sembrados una vez y RLS en todas.
- Pruebas: batería PHP completa en el repositorio principal (67 archivos): pasan todas salvo las ya conocidas (ver "Estado de pruebas"). Frontend: 386 pasan y 4 fallan, las conocidas.
- **Sin probar en navegador:** nada de lo hecho hoy (fletes, solicitudes de compra, admin de fletes, carrito, Publicar con modelo de animal, vacunas) se vio con una sesión real.

### 2026-10-06 · sesion-a-fletes-admin · Método de pago en la solicitud y carrito como contador
- Endpoint de lectura `api/v1/pago-metodos-disponibles`; selector opcional en el diálogo de solicitud; `pagoMetodo` en la presentación y en las bandejas de Mi panel. Carrito del encabezado con el contador de solicitudes aprobadas y `?resumen=1` (ver "Solicitud de compra"). Sin esquema nuevo.
- Archivos: `PagoMetodosDisponiblesController.php`, `Public/api/pago-metodos-disponibles.php`, `PagoMetodo.php`, `CompraSolicitud.php` (`contarAceptadas`, join con el método), `SolicitudesCompraController.php` (`procesar` recibe también `$_GET`; `resumen()`), `solicitud-compra.js`, `mi-actividad.js`, `public-ui.js`, `public-product.css`, `solicitud.css`, `Public/.htaccess`.
- Caché: `public-ui.js` `public-14` y `public-product.css` `product-9` en las 8 vistas públicas; `solicitud-compra.js`/`solicitud.css` `solicitud-2`; `explore-interactions.js` `interactions-4`, `me-interesa.js` `interesa-4`, `mi-actividad.js` `panel-10`.
- Pruebas: ampliada `Tests/solicitudes_compra_test.php` (método válido/inactivo/inexistente, presentación, lista de disponibles, resumen y 401); nueva `Tests/frontend/carrito.test.mjs`; ampliada `solicitudes_ui.test.mjs`.

### 2026-10-06 · sesion-a-fletes-admin · Admin: pantalla /admin/fletes (moderación de ofertas)
- Pantalla y API nuevas (ver "Administrador: fletes"); estado `RETIRADA` de oferta sin cambiar el esquema; el transportista no puede tocar una retirada.
- Archivos: `AdminFletesController.php`, `Public/api/admin-fletes.php`, `TransportistaOferta.php` (`listarAdmin`, `buscarAdmin`), `CompraSolicitud.php` (`listarAdmin`), `MiOfertasController.php` (409 si RETIRADA), vista `adminfletes`, `adminfletes.js`, `fletes.js` (`fletes-2`), `Public/.htaccess`, enlace "Fletes" en el menú de las 9 vistas admin.
- Cadena de caché (Cuidados #11): `auth-gate-8` (api.js, login, admin-ui y los 8 módulos admin), `admin-10` (admin-ui y `admin-refinements.css`).
- Pruebas: nueva `Tests/api_admin_fletes_test.php` (y en la lista de `api_auth_admin_http_test.php`) y `Tests/frontend/admin_fletes.test.mjs`; `admin_cache_chain` y `fletes` ajustadas.

### 2026-10-06 · modelo-animal · P2-3 Historial de vacunación
- Tablas `tbvacuna` y `tbanimalvacunacion` (43 en total), `api/v1/mi-animales/vacunas`, vacunas en `api/v1/catalogos` y lectura pública acotada en la lista de publicaciones (ver "Historial de vacunación (P2-3)" y `DEC-ANIMAL-002`).
- Archivos: `AnimalVacunacion.php`, `MiAnimalesVacunasController.php`, `Public/api/mi-animales-vacunas.php`, `AnimalCatalogo.php`, `CatalogosController.php`, `AnimalComercial.php` (`adjuntarVacunas`), `AnimalPublicacionController.php`, `022vacunacion.sql`, `104catalogosanimal.sql`, `schema.sql`, `migrate.php`, `explore.js` (bloque de vacunas), `Public/.htaccess`, diccionario, DER, `Decisiones.md` y PDF.
- Pruebas: nuevas `Tests/mi_animales_vacunas_test.php` y `Tests/frontend/vacunas.test.mjs`; conteos de 41 a 43 tablas en README, `Database/Tests`, `schema_manifest_test`, `db_ready_test`, `instalacion_limpia_test`, `naming_eval`, `schema_eval` y `schema_test`.
- Cuidado: una base MySQL existente necesita `Database/Migrations/022vacunacion.sql` (después de la 021). Probado `migrate.php` en Postgres 16 (dos corridas; no reinserta lo que el administrador borró o desactivó).

### 2026-10-06 · modelo-animal · P2-2 Modelo de animal: catálogos, validaciones, arete, lote y Publicar
- Esquema, API y pantalla del modelo de animal (ver "Modelo de animal (P2-2)" y `DEC-ANIMAL-001`): 4 tablas nuevas (41 en total), 8 columnas NULL en `tbanimal`, `api/v1/catalogos`, validaciones, arete SENASA, lote y venta de lote por animal.
- Archivos: `AnimalCatalogo.php`, `AnimalValidacionService.php`, `CatalogosController.php`, `Public/api/catalogos.php`, `AnimalComercial.php`, `AnimalPublicacionController.php`, `CompraSolicitud.php` (`datosParaVenta`) y `SolicitudesCompraController.php` (`aceptar`), `021modeloanimal.sql`, `104catalogosanimal.sql`, `schema.sql`, `migrate.php`, `publicar.js` (`publish-4`), `shared/arete.js`, `explore.js` (`explore-12`; import `foto-4` en `home.js` y `publicar.js`), vistas de Publicar, Explorar e Inicio, `Public/.htaccess`, diccionario, DER, `Decisiones.md` y PDF.
- Pruebas: nuevas `Tests/api_modelo_animal_test.php` y `Tests/frontend/modelo_animal.test.mjs`; actualizadas `db_ready_test`, `naming_eval` y `schema_eval` (la fecha de nacimiento ya no está prohibida) y todos los conteos de 37 a 41 tablas. `solicitudes_compra_test` y `api_publicaciones_test` pasan sin cambios.
- Cuidado: **`me-interesa.js` debe subir su import a `explore.js?v=foto-4`** (no se tocó aquí porque lo edita otra rama); hasta entonces un navegador con la copia vieja de `explore.js` no muestra la marca de lote en Me interesa. Una base MySQL existente necesita `Database/Migrations/021modeloanimal.sql`. Migraciones nuevas empiezan en 022.
- Probado `migrate.php` contra Postgres 16 partiendo del esquema anterior (37 tablas), dos veces seguidas (idempotente, 41 tablas, catálogos sembrados una vez).

### 2026-10-06 · jefersonbustamante · Migraciones renumeradas (colisión con `backend`)
- La rama `backend` (Jeremi) ya usa las migraciones `017personadocumentomotivo.sql` y `018personadocumentolectura.sql`. Las mías pasan a `019transportistaoferta.sql` y `020comprasolicitud.sql`; **las siguientes empiezan en 021**. Solo cambió el nombre de los archivos (el contenido y lo aplicado en las bases no cambian).
- Cuidado: antes de crear una migración, mira también `git ls-tree origin/backend Database/Migrations/` para no repetir número.

### 2026-10-06 · jefersonbustamante · P1-3 paso 3: pantallas de solicitudes de compra
- "Solicitar compra" en Explorar, "Solicitar compra" y "Ver fletes cercanos" en Me interesa, y las tres bandejas de Mi panel (ver "Solicitud de compra (P1-3)").
- Archivos: nuevos `Public/js/shared/solicitud-compra.js` y `Public/css/solicitud.css`; `explore.js` (`explore-11`), `explore-interactions.js` (`interactions-3`), `me-interesa.js` (`interesa-3`), `mi-actividad.js` (`panel-9`), vistas de Explorar, Me interesa y Mi panel.
- Pruebas: nueva `Tests/frontend/solicitudes_ui.test.mjs` (ids de la vista vs. el script, acciones de la API, escape de HTML, estilos con `--tc-*`). Quedan solo las 4 fallas de frontend conocidas; pasa `frontend_contrast_test` (20 hojas).
- Cuidado: `shared/solicitud-compra.js` **no importa `explore.js`** a propósito: Explorar lo carga con otro `?v=` y se ejecutaría dos veces. Si cambias el diálogo, sube su `?v=solicitud-` en `explore-interactions.js` y en `me-interesa.js`, y el de `solicitud.css` en las dos vistas.

### 2026-10-06 · jefersonbustamante · P1-3 paso 2: API de solicitudes de compra
- `api/v1/solicitudes-compra` (listar, solicitar, cancelar, aceptar, rechazar y responder el flete) con las reglas de "Solicitud de compra (P1-3)". Sin columnas nuevas (el esquema es del paso 1).
- Archivos: nuevos `Application/Model/CompraSolicitud.php`, `Application/Controller/SolicitudesCompraController.php`, `Public/api/solicitudes-compra.php`; `TransportistaOferta::buscarDisponible`; `AnimalComercial` (compra y venta con Comprador); ruta en `Public/.htaccess`; `RutasFrontend.md` y `RutasPublicas.md`.
- Pruebas: nueva `Tests/solicitudes_compra_test.php` (validación, anti-IDOR, cancelar, rechazar, aceptar con venta y compra, precio a convenir con rollback, flete aparte, contactos, bitácora); pasan también `api_requires_test`, `sql_alias_minuscula_test`, `backend_db_ready_test`, `instalacion_limpia_test`, `api_publicaciones_test`, `mi_ofertas_test` y `mi_vehiculos_test`.
- Cuidado: los transportistas y vendedores registrados por `RegistroPublicoController` **también son Compradores** (alta inicial); un vendedor creado con `test_create_completo` no lo es. Los alias SQL de `CompraSolicitud` van en minúscula (Cuidados #3).

### 2026-10-06 · jefersonbustamante · P1-3 paso 1: tabla `tbcomprasolicitud` y adaptación de compra/venta (esquema)
- Tabla nueva en los 4 lugares (`000instalacioncompleta.sql`, `020comprasolicitud.sql`, `schema.sql` con RLS, `migrate.php`) y cambios en `tbcompra` y `tbventa` (ver "Solicitud de compra (P1-3)"); diccionario, DER, `Decisiones.md` (DEC-COMPRA-001) y PDF.
- El esquema pasa de 36 a **37 tablas**: se actualizaron los mismos README, docs y pruebas que en el cambio anterior.
- `migrate.php`: `EXPECTED_COLUMNS` de `tbcompra`, `tbventa` y la tabla nueva, y `ensureCurrentColumns()` con `ADD COLUMN IF NOT EXISTS` y `DROP NOT NULL`.
- Cuidado: una base MySQL existente necesita `Database/Migrations/020comprasolicitud.sql` (y la 019 si falta).
- Limpieza: `mi_ofertas_test` dejó filas huérfanas de `tbcomprador` en una corrida fallida; `instalacion_limpia_test` las detecta ("Ningún tbcomprador apunta a una persona inexistente").

### 2026-10-06 · jefersonbustamante · P1-2 paso 3: pantalla de Fletes
- `/fletes` pasa de explicación a funcional: fletes disponibles cerca de ti y Mis ofertas (ver "Oferta de flete (P1-2)"). `api/v1/fletes` excluye las ofertas propias.
- Archivos: `fletes.js` (reescrito, `fletes-1`), vista `fletes/index.php` (diálogo de oferta; ahora carga `components.css` y `mi-actividad.css`), nuevo `shared/editor-direccion.js`, `shared/finca-mapa.js` (opciones de texto), `TransportistaOferta::listarCercanas` y `FletesController` (parámetro de persona).
- Pruebas: nueva `Tests/frontend/fletes.test.mjs` (ids de la vista vs. el script, campos del formulario, versiones, escape de HTML); ampliada `Tests/mi_ofertas_test.php`.
- Cuidado: `finca-mapa.js` se importa con versiones distintas en cada lugar; el cambio de opciones solo lo ve quien importe `?v=oferta-1` (por eso `editor-direccion.js` lo versiona). No se probó en el navegador con una sesión real: revisar a mano (publicar, editar, pausar, ver desde otra cuenta).

### 2026-10-06 · jefersonbustamante · P1-2 paso 2: API de ofertas de flete
- `api/v1/mi-ofertas` (crear, editar, pausar) y `api/v1/fletes` (cercanas por radio) con sus reglas en "Oferta de flete (P1-2)". Sin columnas nuevas (la tabla es del paso 1).
- Archivos: nuevos `Application/Model/TransportistaOferta.php`, `Application/Controller/MiOfertasController.php` y `FletesController.php`, `Public/api/mi-ofertas.php` y `fletes.php`; rutas en `Public/.htaccess`; `RutasFrontend.md` y `RutasPublicas.md`.
- Pruebas: nueva `Tests/mi_ofertas_test.php` (validación, anti-IDOR, cercanía y radio, capacidad, pausa, vehículo inactivo, bitácora); pasan también `mi_vehiculos_test`, `api_requires_test`, `sql_alias_minuscula_test`, `api_publicaciones_test`, `api_mi_perfil_test` y `api_auth_admin_http_test`.
- Cuidado: `FletesController` es **privado** (401 sin sesión), a diferencia de `api/v1/publicaciones`. Los alias SQL de `TransportistaOferta` van en minúscula (Cuidados #3).

### 2026-10-06 · jefersonbustamante · P1-2 paso 1: tabla `tbtransportistaoferta` (esquema)
- Tabla nueva en los 4 lugares (`000instalacioncompleta.sql`, `019transportistaoferta.sql`, `schema.sql` con RLS, `migrate.php`), diccionario, DER (con relaciones), `Decisiones.md` (DEC-FLETE-001) y los 3 PDF regenerados. Sin código de aplicación todavía.
- El esquema pasa de 35 a **36 tablas**: se actualizaron README, GuiaDefensa, Respaldos, `Database/Tests`, `schema_manifest_test`, `db_ready_test`, `instalacion_limpia_test`, `naming_eval` y los tres archivos de `services/supabase-database`.
- Cuidado: una base MySQL existente necesita `Database/Migrations/019transportistaoferta.sql`.

### 2026-10-05 · jefersonbustamante · Explorar sin publicaciones propias y sin portada con sesión
- API: `excluirPropias` en `api/v1/publicaciones` (sin cambio de contrato para quien no lo envía). Frontend: Explorar lo envía; `public-ui.js` (`public-13`) quita Inicio y redirige la portada a Explorar con sesión.
- Archivos: `AnimalPublicacionController.php`, `AnimalComercial.php` (`listarPublicaciones`), `explore.js` (`explore-10`), `public-ui.js`, `home/index.php` (`data-portada`); `?v=` de `public-ui.js` y `explore.js` subidos en todas las vistas.
- Pruebas: ampliada `Tests/api_publicaciones_test.php`; nueva `Tests/frontend/explorar_sin_propias.test.mjs`.

### 2026-10-05 · jefersonbustamante · P1-5 pantalla de foto de vehículo y foto en el diálogo de editar publicación (P0-2)
- Mi panel: los diálogos de **vehículo** y de **publicación** tienen campo de foto (elegir, vista previa, quitar). La fila del vehículo muestra miniatura (`fa-truck` si no hay). Sin cambios de API ni de esquema.
- Archivos: nuevo `Public/js/shared/foto-campo.js` (`montarCampoFoto`; `resolver()` devuelve `undefined` sin cambio, `null` al quitar, o la URL subida), `mi-actividad.js` (`panel-8`, import `foto-campo-1`), vista de Mi panel. Reutiliza `.publish-dropzone` y `.publish-preview` de `onboarding.css` (sin CSS nuevo).
- Pruebas: nueva `Tests/frontend/panel_fotos.test.mjs`. Quedan solo las 4 pruebas de frontend que ya fallaban.
- Cuidado: no enviar `fotoUrl`/`imagenUrl` cuando no cambió la foto (el PUT del vehículo borra la foto con `null`). Tras un merge, `Public/js/shared/api.js` puede quedar con CRLF y fallar `public_identity_auth` (Cuidados #4).

### 2026-10-06 · backend · Revisión de P1-1 y P1-4: bitácora con ids repetidos
- Revisión del API de P1-1 (Me interesa) y P1-4 (perfil). Error encontrado y demostrado: `PublicacionInteraccionController`, `MiPerfilController` y `AdminPublicacionController` (P1-6) escribían la bitácora en una transacción sin mantener su bloqueo hasta el COMMIT, y dos peticiones simultáneas podían repetir el id. Ahora envuelven toda la transacción con el bloqueo de la bitácora y con el de su otro consecutivo (interacción, periodo de estado, o histórico de teléfono vía el nuevo `Persona::ejecutarConBloqueoTelefono`).
- Comprobado con 2 procesos en paralelo × 25 ediciones del perfil: 50 eventos, 50 ids distintos.
- Nueva `Tests/bitacora_bloqueo_test.php` (Cuidados #12). Quedan 9 controladores antiguos con el mismo problema (ver Pendientes).

### 2026-10-06 · backend · Cerrar sesión borra el perfil en caché
- Al cerrar sesión y pulsar "Crear cuenta", el registro aparecía con los datos de la cuenta anterior (menos contraseña y documento). Causa: `clearAuthSession()` no borraba `tindercows:profile` y `registro.js` usa ese perfil cuando no hay sesión. Ahora se borra en toda salida de sesión (ver "Navegación y sesión").
- Caché: imports `supabase-auth.js?v=session-3` en `ajustes.js` (`ajustes-9`), `me-interesa.js` (`interesa-3`) y `mi-actividad.js` (`panel-8`). Prueba nueva `Tests/frontend/cerrar_sesion.test.mjs`.

### 2026-10-06 · backend · Lectura automática del número, cámara en celulares y documento en el registro (P2-5/P2-6)
- Ver "Documento de identidad (P2-5)": lectura en el dispositivo con Tesseract.js, resultado calculado en PHP, "Tomar foto" solo en celulares, paso opcional en el registro y la lectura visible para el admin en `/admin/documentos`.
- Columnas `tbpersonadocumentonumeroleido` y `tbpersonadocumentolectura` en los 4 lugares + migración 018, diccionario, DER y PDF.
- Archivos: nuevo `shared/escaner-documento.js?v=escaner-2`; `storage.js` `documento-2` (`nuevoUuid`: `crypto.randomUUID` no existe en páginas sin HTTPS, como el celular entrando por `http://IP-local`; se arma con `getRandomValues`); `MiPerfilController.php` (`documentoLectura`, `numeroLeido`, `resultadoLectura`), `Persona.php`; `ajustes.js` (`ajustes-8`), `registro.js` (`signup-10`), `documentos.js` (`documentos-2`), vistas de Ajustes y Registro.
- Antes se probó la lectura con una página temporal fuera del repositorio: una foto subida se leyó bien; la webcam de la computadora, no.
- Pruebas: nueva `Tests/frontend/escaner_documento.test.mjs`; `api_mi_perfil_test.php` cubre los 5 resultados, que el navegador no puede mandar el resultado y que con pasaporte no aplica.

### 2026-10-05 · backend · P2-6 Verificación de documentos de identidad
- Pantalla `/admin/documentos` y API `api/v1/admin/documentos` (ver "Documento de identidad (P2-5)"); columna `tbpersonadocumentomotivo` en los 4 lugares + migración 017, diccionario, DER y PDF; Ajustes muestra el motivo del rechazo (`ajustes-6`).
- Archivos: nuevos `Application/Service/SupabaseStorage.php`, `AdminDocumentoController.php`, `Public/api/admin-documentos.php`, `Public/documentos.php`, vista `documentos/`, `Public/js/documentos.js`; `Persona.php` (`listarDocumentos`, `bloquearPorId`, `decidirDocumento`, motivo en `documentoPublico`); `MiPerfilController` limpia el motivo; `compose.yaml` pasa `SUPABASE_SECRET_KEY` a la app.
- Cadena de caché (Cuidados #11): `auth-gate-8`, `admin-ui.js` y `admin-refinements.css` `admin-10`, `login.js` `front-13`, los 10 módulos admin con `shared/api.js?v=auth-gate-8`.
- Pruebas: nueva `Tests/api_admin_documentos_test.php` (transporte falso para el enlace; borra sus eventos al terminar porque el id de la persona de prueba se reutiliza); `api_auth_admin_http_test.php` y `admin_cache_chain.test.mjs` incluyen la ruta; nueva `admin_documentos.test.mjs`. Enlace firmado probado contra el Supabase real (abre el archivo; la ruta pública da 400). Probado en Postgres 16.

### 2026-10-05 · backend · P3-1 Comerciante (investigación, sin código)
- Nuevo `Documentation/Sprints/P3-1-Comerciante.md`: según la Ley 8799 y el Decreto 44336, "comerciante" no es un actor distinto (comprar y vender tiene las mismas obligaciones de guía y trazabilidad); lo distinto son los establecimientos mercantiles (subastas, ferias). Se recomienda tratarlo como Vendedor hasta que el cliente responda las 3 preguntas del documento.
- De paso responde parte de P2-2: el arete oficial (DIIO) lleva el 188 de Costa Rica.

### 2026-10-05 · backend · P3-4 Visor de la bitácora
- Pantalla `/admin/bitacora` y API `api/v1/admin/bitacora` de solo lectura (ver "Administrador: bitácora (P3-4)"). Sin columnas nuevas.
- Archivos: `Bitacora.php` (`listar`, `entidades`), nuevos `AdminBitacoraController.php`, `Public/api/admin-bitacora.php`, `Public/bitacora.php`, vista `bitacora/`, `Public/js/bitacora.js`; estilo `.detail-grid pre` en `admin-refinements.css` (sin colores).
- Cadena de caché (Cuidados #11): `auth-gate-7`, `admin-ui.js` `admin-9`, `admin-refinements.css` `admin-9`, `login.js` `front-12`; los 9 módulos admin importan `shared/api.js?v=auth-gate-7` y sus `<script>` suben (`admin-menu-2`, `sections-5`, `moderacion-4`, `administradores-2`, `bitacora-1`).
- Pruebas: nueva `Tests/api_admin_bitacora_test.php`; `api_auth_admin_http_test.php` incluye el endpoint (encontró que un POST mal formado sin sesión respondía 422 en vez de 401: ahora la autorización va primero); nuevas `admin_bitacora.test.mjs` y `admin_cache_chain.test.mjs`, que exige que **toda la cadena use la misma versión** en vez de fijar números (ya no hay que editar las pruebas al subir versiones).
- La prueba que ya fallaba ("el shell existente se mejora desde un bootstrap compartido…") es `admin_shell_ux.test.mjs`, que todavía fija `auth-gate-2`.
- Los filtros de fecha usan el aspecto de los `select` de filtro y `color-scheme` según el tema del panel (`admin-refinements.css` `admin-9`; el panel es oscuro por defecto y el navegador lo pintaba claro).

### 2026-10-05 · backend · P3-4 Gestionar administradores desde el panel
- Pantalla `/admin/administradores` y API `api/v1/admin/administradores` (ver "Administrador: gestionar administradores"). Sin columnas nuevas.
- Archivos: nuevos `Application/Model/Administrador.php`, `AdminAdministradorController.php`, `Public/api/admin-administradores.php`, `Public/administradores.php`, vista `administradores/`, `Public/js/administradores.js`.
- Cableado de la ruta: `Public/.htaccess`, `PRIVATE_ROUTES` en `auth-gate.js`, `MODULES` en `admin-ui.js`, `ADMIN_DESTINATIONS` en `login.js`, enlace en el menú de las 8 vistas admin e ícono en `admin-refinements.css`.
- Caché: `auth-gate.js` `auth-gate-6` (en `login.js`, `admin-ui.js`, `api.js`), `admin-ui.js` `admin-7` (import en `api.js`), `admin-refinements.css` `admin-7`, `login.js` `front-11`; `administradores.js` importa `shared/api.js?v=auth-gate-6`.
- Ícono del menú que no aparecía en las otras pantallas admin: el ícono vive en `admin-refinements.css`, que inyecta `admin-ui.js`, que carga `api.js`; las demás pantallas importaban `api.js` **sin versión** y el navegador usaba la copia vieja. Ahora los 7 módulos admin importan `shared/api.js?v=auth-gate-6` y sus `<script>` llevan versión (`admin-menu-1`, `productores` `sections-4`, `publicaciones` `moderacion-3`).
- Pruebas: nueva `Tests/api_admin_administradores_test.php` (desactiva a los demás admins para probar "último activo" y los restaura); `api_auth_admin_http_test.php` incluye el endpoint; nueva `Tests/frontend/admin_administradores.test.mjs`; `public_identity_auth.test.mjs` con las versiones nuevas de `api.js`.

### 2026-10-05 · backend · Aviso para subir el documento al crear la cuenta (P2-5)
- Nuevo `Public/js/shared/aviso-documento.js?v=aviso-1`; lo marcan `registro.js` (`signup-8`) y `login.js` (`front-10`) y lo muestra Explorar (aviso dentro de la página, no flotante, para no taparse con el aviso de "Me interesa"). Estilo `.aviso-documento` en `explore.css` (`explore-9` en Explorar, Inicio y Me interesa), solo con variables `--tc-*`.
- Prueba nueva `Tests/frontend/aviso_documento.test.mjs`.

### 2026-10-05 · backend · Un admin que se registra como usuario termina su registro
- `login.js` (`front-9`): con 409 en `/entrar`, se completa el registro pendiente antes del acceso admin (ver "Navegación y sesión"). Se encontró al probar: una cuenta admin sin Persona siempre terminaba en el panel.
- Prueba nueva en `Tests/frontend/registro_comprador.test.mjs`.

### 2026-10-05 · backend · P2-5 Foto del documento de identidad (subida y estado)
- Ajustes → Perfil permite subir la foto o el PDF del documento al bucket privado `documentos`; queda `PENDIENTE` (ver "Documento de identidad (P2-5)"). La verificación por el admin es P2-6.
- Columnas `tbpersonadocumentoruta`, `tbpersonadocumentoestado` y `tbpersonadocumentofecha` en los 4 lugares (`000instalacioncompleta.sql`, `016personadocumento.sql`, `schema.sql`, `migrate.php`), diccionario, DER y PDF.
- Archivos: `MiPerfilController.php` (`documentoRuta`), `Persona.php` (`documentoPublico`, columnas en `actualizarPerfil`), `MiActividadController.php`, `shared/storage.js` (`subirDocumentoIdentidad`, `validarDocumento`; la subida pasa a `subirArchivo` común), `ajustes.js` (`ajustes-5`, import `storage.js?v=documento-1`), vista de Ajustes.
- Pruebas: ampliadas `Tests/api_mi_perfil_test.php` (rutas ajenas, `..`, URL, extensión, null; estado PENDIENTE; nunca expone la ruta) y `Tests/frontend/perfil.test.mjs`; `Tests/schema_test.php` con las columnas.
- Cuidado: una base MySQL existente necesita `Database/Migrations/016personadocumento.sql`. El bucket `documentos` todavía no existe en Supabase (SQL en "Configuración de Supabase").

### 2026-10-05 · backend · P2-1 Máscaras de cédula y teléfono iguales al servidor; límite por IP comprobado en Vercel
- El registro aceptaba cédulas que el servidor rechaza (`0-1234-5678`, 10 dígitos en una física) y mostraba "No se pudo verificar"; y teléfonos con letras, que fallaban recién después de crear la cuenta en Supabase. Ahora `validatePersonaDraft` aplica las reglas del servidor (ver "Máscaras iguales al servidor") y un 422 del servidor muestra su mensaje.
- Archivos: `shared/identificacion.js` (`errorIdentificacion`, `REGLAS_SERVIDOR`), `shared/business-rules.js`, `registro.js`. Caché: `registro.js?v=signup-7`; imports `business-rules.js`, `identificacion.js` y `telefono.js` con `?v=mascaras-1`.
- Pruebas: nueva `Tests/frontend/mascaras.test.mjs`.
- `X-Real-IP` comprobado en el preview de Vercel (ver "Límite por IP"): no se puede falsificar y el límite es por IP.

### 2026-10-04 · backend · P2-1 Correo en tiempo real en el registro (frontend)
- `registro.js` consulta el correo como ya lo hacía con la cédula y muestra el 429 con el mensaje del servidor (ver "Correo en el formulario"). Vista con `[data-correo-status]`; caché `registro.js?v=signup-6`.
- Revierte, por decisión del equipo, el retiro de `d7b5a88` (Dilan, 29/09). Se ajustó su prueba en `supabase_auth_registration.test.mjs`: ahora exige que no vuelva `api/v1/registro/correo` y que el endpoint tenga límite y 429. Nueva prueba en `public_identity_auth.test.mjs`. DEC-REG-001 ampliada.
- Cuidado: sin captcha, el límite por IP solo frena la enumeración de correos, no la impide.

### 2026-10-04 · backend · P2-1 Disponibilidad de cédula y correo con límite por IP (API)
- `api/v1/registro/identificacion` ahora también consulta el correo (ver "Disponibilidad en tiempo real"). Sin cambio para quien ya lo usa con la cédula.
- Tabla nueva `tbregistroconsulta` (DEC-REG-001) en `000instalacioncompleta.sql`, `015registroconsulta.sql`, `schema.sql` (con RLS) y `migrate.php`; diccionario, DER, Decisiones y PDF regenerados. El esquema pasa de 34 a 35 tablas: se actualizaron las pruebas y documentos que fijaban el número (`schema_manifest_test`, `naming_eval`, `db_ready_test`, `instalacion_limpia_test`, `supabase .../schema_test` y `schema_eval`, `personacapacidades_gate`, `comprobacionestructura.sql`, README, GuiaDefensa, Respaldos). La lista de tablas del README tenía 32; se completó.
- Archivos: `RegistroIdentificacionController.php`, `Persona.php` (`existeCorreo`), nuevo `Application/Model/RegistroConsulta.php`, `Public/api/registro-validar-identificacion.php`.
- Pruebas: nueva `Tests/registro_consulta_test.php`; ampliada `Tests/registro_publico_test.php`. `migrate.php` probado dos veces contra Postgres 16 (35 tablas, RLS) y el límite también en Postgres.
- Cuidado: una base MySQL existente necesita `Database/Migrations/015registroconsulta.sql`. Producción la crea con `schema.sql` al arrancar. Una tabla nueva cambia el conteo de tablas en todas las pruebas y documentos de arriba.

### 2026-10-04 · backend · Arreglo de carga en mi-perfil.php (P1-4)
- `Tests/api_requires_test.php` fallaba: `mi-perfil.php` cargaba `AnimalPublicacionController` antes que su controlador, y la prueba revisa el primer controlador del endpoint (le exigía `AnimalComercial`).
- Ahora `MiPerfilController.php` hace `require_once` de `AnimalPublicacionController.php` (solo usa su `imagenUrl()` estático) y el endpoint ya no lo carga. Igual que `MiVehiculosController`.
- Cuidado: si un controlador solo usa un método estático de otro controlador, que lo cargue el propio controlador, no el endpoint.

### 2026-10-04 · backend · P1-5 Fotos de vehículos (API)
- Columna nueva `tbvehiculofotourl` en los 4 lugares (`000instalacioncompleta.sql`, `014vehiculofoto.sql`, `schema.sql`, `migrate.php`), diccionario, DER y PDF regenerados.
- `fotoUrl` en `api/v1/mi-vehiculos` (crear, editar y lectura) con validación https (ver "Foto del vehículo"). Sin cambios de pantalla: la parte de Mi panel queda para frontend.
- Archivos: `MiVehiculosController.php`, `Vehiculo.php` (`crear`, `actualizar`, `mapear`), `TransportistaVehiculo.php` (`listarVehiculosPorTransportista`).
- Pruebas: ampliada `Tests/mi_vehiculos_test.php` (foto en alta y edición, conservación sin la clave, quitar con null, rechazo de http/javascript:/data:).
- Cuidado: en una base MySQL ya creada hay que aplicar `Database/Migrations/014vehiculofoto.sql` (producción lo hace `migrate.php` al arrancar).

### 2026-10-04 · fix/vercel-ramas-y-poda · Política de ramas de Vercel y poda automática del registro
- El registro de imágenes llegó a 50 y los despliegues de `dev` y `backend` fallaron al publicar. Causa: `services.app.ignoreCommand` nunca se ejecutó en Vercel (el log de build no lo muestra y `backend` y `jefersonbustamante` construían con el mismo script que debía omitirlas), así que ni la guarda de ramas ni la poda que vivía dentro corrieron.
- `vercel.json`: `git.deploymentEnabled` con `"**": false`, `dev` y `main`; se quita `ignoreCommand`. Comprobado con dos ramas de prueba (una con `/` y otra sin): ninguna creó despliegue.
- Se borra `Tools/vercel-ignore-build.sh`. La poda pasa a `.github/workflows/vercel-prune-registry.yml` (push a `dev`/`main`, conserva 15 más las 3 últimas producciones listas).
- `Tools/vercel-prune-registry.sh`: ya no oculta los errores de la CLI no borra nada si no encuentra una producción lista (antes una respuesta vacía dejaba sin protección la imagen de producción) y protege solo las 3 producciones más recientes (protegerlas todas habría vuelto a llenar el registro tras unos 35 pushes a `main`).
- Pruebas: `Tests/deployment_test.php` y `Tests/deployment_eval.php` ajustadas; `Tests/vercel_prune_registry_test.php` ejecuta el envoltorio contra una CLI falsa.
- Cuidado: ver "Cuidados" punto 10. La imagen de producción se protege por etiqueta (sha del commit); si `dev` y `main` despliegan el mismo commit, la etiqueta queda en una sola imagen y la otra cuenta como una más entre las recientes.

### 2026-10-04 · jefersonbustamante · P1-4 Foto de perfil y edición de datos personales
- Columna nueva `tbpersonafotourl` en los 4 lugares (`000instalacioncompleta.sql`, `013personafoto.sql`, `schema.sql`, `migrate.php`), diccionario, DER y PDF regenerados. Las bases MySQL existentes necesitan la migración 013.
- API `PATCH api/v1/mi-perfil` y `fotoUrl` en `api/v1/actividad` (ver "Foto de perfil y datos propios"). Ajustes → Perfil con foto, quitar foto y edición de alias y teléfono; avatar con foto en el encabezado.
- Archivos: `MiPerfilController.php`, `Persona.php` (`actualizarPerfil`), `Public/api/mi-perfil.php`, `ajustes.js` (`ajustes-4`), `public-ui.js` (`public-12`), `mi-actividad.css` (`panel-4`), `public-product.css` (`product-8`), vista de Ajustes, `Public/.htaccess`.
- Pruebas: nueva `Tests/api_mi_perfil_test.php` y `Tests/frontend/perfil.test.mjs`; `schema_test.php` y `api_auth_admin_http_test.php` ajustadas.
- Cuidado: en una base MySQL ya creada hay que aplicar `Database/Migrations/013personafoto.sql` (producción lo hace `migrate.php` al arrancar).
- Bucket `publicaciones` de Supabase: ya existe y tiene política (resuelto); sin él, la subida de foto falla con mensaje amable.

### 2026-10-04 · jefersonbustamante · P1-6 Admin: moderar publicaciones
- Pantalla `/admin/publicaciones` (lista, buscador, filtro de estado, Pausar/Retirar con motivo, Reactivar) y API `api/v1/admin/publicaciones` (ver "Administrador: moderar publicaciones"). Sin columnas nuevas: no toca esquema ni PDF.
- Cableado de una ruta admin nueva: `Public/.htaccess` (página y API), `PRIVATE_ROUTES` en `auth-gate.js`, `MODULES` en `admin-ui.js`, destinos de `login.js`, enlace en el menú de las 7 vistas admin e ícono en `admin-refinements.css`.
- Caché: `auth-gate.js` `auth-gate-5` (en `login.js`, `public-ui.js`, `admin-ui.js`, `api.js`), `admin-refinements.css` `admin-6`, `login.js` `front-8`, `publicaciones.js` `moderacion-1`.
- Pruebas: ampliada `Tests/api_publicaciones_test.php`; `Tests/api_auth_admin_http_test.php` incluye el endpoint nuevo; nueva `Tests/frontend/admin_publicaciones.test.mjs`.
- Cuidado: toda ruta admin nueva debe agregarse en `PRIVATE_ROUTES` y exigir `AdminAuthorization::require` en su endpoint.
- Cuidado (página en blanco): los paneles admin son `visibility:hidden` hasta que `auth-gate.js` los revela. Un `api.js` viejo en caché del navegador carga el `auth-gate` anterior, que no conoce una ruta nueva, y la página queda en blanco aunque la API responda. Por eso `publicaciones.js` importa `shared/api.js?v=auth-gate-5` (`moderacion-2`). Una ruta admin nueva debe versionar ese import.

### 2026-10-04 · jefersonbustamante · Ajustes de P1-1: tarjeta compacta, ocultar marcadas y sin Pasar
- `/me-interesa` usa la tarjeta compacta de la portada; Explorar oculta la tarjeta al marcar "Me interesa" y lo ya marcado; se eliminó el botón Pasar de `buildCard()`.
- Caché: `explore.js` `explore-9` / import `foto-3`, `explore-interactions.js` `interactions-2`, `me-interesa.js` `interesa-2`, `home.js` `home-7`, `publicar.js` `publish-3`, `mi-actividad.js` `panel-7`.
- Pruebas: `public_identity_auth.test.mjs` ya no exige Pasar; `me_interesa.test.mjs` cubre la tarjeta compacta y el ocultado.

### 2026-10-04 · jefersonbustamante · P1-1 Página Me interesa (guardados)
- API: `GET` de guardados, acción `RETIRAR` idempotente y `meInteresa` en el listado (ver "Me interesa (guardados)"). Sin columnas nuevas: no toca esquema ni PDF.
- Página nueva `/me-interesa` (ruta en `Public/.htaccess`, vista, `me-interesa.js`), enlazada desde el menú del avatar y Mi panel.
- Archivos: `PublicacionInteraccionController.php`, `PublicacionInteraccion.php`, `AnimalComercial.php` (`listarPublicaciones` con `personaId`/`soloMarcadas`), `Public/api/publicacion-interacciones.php` y `publicaciones.php`, `explore.js` (estado guardado en la tarjeta).
- Caché: `explore.js` pasa a `explore-8` / import `foto-2` (home, Mi panel, Publicar y la página nueva); `public-ui.js` a `public-11` en todas las vistas; `mi-actividad.js` `panel-6`, `home.js` `home-6`, `publicar.js` `publish-2`.
- Pruebas: ampliada `Tests/api_publicaciones_test.php`; nueva `Tests/frontend/me_interesa.test.mjs`.
- Cuidado: `publicaciones.php` ahora resuelve la sesión también en la lectura pública si llega un token, pero un token inválido no la rompe.
- Pendiente: botón "Ver fletes cercanos" (P1-3).

### 2026-10-04 · jefersonbustamante · P0-2 Mis publicaciones: listar, editar, pausar y vender
- API: `mias=true` en `api/v1/publicaciones`; `PATCH` para editar y cambiar de estado (ver "Estados de una publicación"). Sin columnas nuevas: no toca esquema ni PDF.
- Mi panel: usa `mias: true` (se eliminó `ownPublications()`), con botones Editar (diálogo), Pausar/Reactivar y Vendida.
- Archivos: `AnimalPublicacionController.php`, `AnimalComercial.php` (`buscarPublicacionPropia`, `actualizarPublicacion`, `cambiarEstadoPublicacion`; `abrirEstadoPeriodo` ahora delega en `insertarEstadoPeriodo`), `Public/api/publicaciones.php`, `mi-actividad.js` (`?v=panel-5`), vista `mi-actividad`.
- Pruebas: ampliada `Tests/api_publicaciones_test.php`; `mi_actividad.test.mjs` y `public_identity_auth.test.mjs` ajustadas al nuevo contrato (`['GET', 'POST', 'PATCH']`).
- Cuidado: el endpoint resuelve la sesión solo cuando hace falta (crear, PATCH o `mias`); la lectura pública sigue sin sesión.
- Plan: P0-2 marcado en `Documentation/Sprints/Plan-Cliente-Admin-2026-10.md` (queda pendiente la foto en el diálogo).

### 2026-10-04 · jefersonbustamante · Plan de trabajo cliente y administrador
- Nuevo `Documentation/Sprints/Plan-Cliente-Admin-2026-10.md`: lo que falta para el cliente y el administrador (Me interesa, fletes cercanos, solicitud de compra, fotos de perfil y vehículo, modelo de animal, verificación de identidad, moderación) más el issue de la reunión del 29/09, ordenado P0–P3 y repartido entre Carlos, Jeremi y Jeferson.
- Sección 0 del plan: lo que el issue pide y choca con las reglas del proyecto (UNIQUE/FK, nombres de tablas, WebSockets en Vercel, tabla de guardados duplicada).

### 2026-10-04 · jefersonbustamante · Alias SQL en minúscula (fallo en producción)
- Síntoma en producción (Postgres): Explorar e Inicio sin publicaciones ("El servidor no devolvió una respuesta válida"), Mi panel sin publicaciones ni fincas.
- Causa: alias camelCase (`AS publicacionId`, `AS fincaId`…) que Postgres pliega a minúscula; PHP no encontraba la clave e imprimía avisos antes del JSON. Reproducido con Postgres 16 + `schema.sql`.
- Arreglo: alias en minúscula y lectura en minúscula; la API mantiene las mismas claves camelCase (sin cambio de contrato).
- Archivos: `AnimalComercial.php`, `ProductorFinca.php`, `TransportistaVehiculo.php`, `Transportista.php`, `PublicacionCercaniaService.php`, nueva prueba `Tests/sql_alias_minuscula_test.php`.

### 2026-10-04 · jefersonbustamante · Sincronización con dev (PR #33)
- `dev` ya incluye todo el trabajo de esta rama (merge `a1ac1e3`, PR #33); la rama se actualizó con `dev` por fast-forward, sin cambios de código.
- Se quitó el pendiente "Llevar `jefersonbustamante` a `dev`". Los próximos cambios van en nuevos PR desde esta rama.

### 2026-10-04 · jefersonbustamante · Fotos en publicaciones y página Publicar
- Columna `tbanimalpublicacionimagenurl` en MySQL y Postgres; `imagenUrl` en la API con validación https.
- Publicar rehecho con el sistema de formulario del registro; foto por archivo (Supabase Storage) o URL, con vista previa.
- Foto en la tarjeta (Inicio y Explorar) y miniatura en Mi panel; 6 fotos de prueba de Wikimedia.
- Se eliminó `Public/css/front2-flow.css` (usaba variables inexistentes y colores verdes).
- Archivos: `AnimalComercial.php`, `AnimalPublicacionController.php`, `012imagenpublicacion.sql`, `schema.sql`, `migrate.php`, `publicar.js`, `shared/storage.js`, `explore.js`, `onboarding.css`.

### 2026-10-04 · jefersonbustamante · Configurar actividades desde Ajustes
- Vendedor: solo formulario de fincas, con dirección siempre visible y botón "Activar vendedor".
- Transportista: se activa con el interruptor y una notificación.
- Se quitó "Completar/Agregar actividades" del menú del avatar.

### 2026-10-03/04 · jefersonbustamante · Registro como comprador y confirmación de correo (commit 1e5abf6)
- Alta inicial solo Comprador (backend); formulario de una página que termina en "Registrar".
- Registro con confirmación pendiente → Entrar con aviso; se completa al iniciar sesión.
- Navegación sin sesión, cierre de sesión unificado (también admin), `next` seguro compartido.
- Mi panel y Ajustes de cuenta; carrusel y tarjeta compacta en Inicio.

### 2026-10-03 · jefersonbustamante · Portada y Explorar (commit 6d343c0)
- Nueva portada (hero con buscador, destacadas, cómo funciona), Explorar con filtros y fila de tarjetas, jerarquía de botones del header.

### 2026-10-03 · jefersonbustamante · Orden del repositorio (commit fd32685)
- Documentos sueltos a `Documentation/`, plantilla de PR a `.github/`, mapa en `Documentation/Arquitectura.md`.
