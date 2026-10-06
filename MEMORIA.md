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
| Me interesa (`/me-interesa`) | `Application/View/me-interesa/` + `Public/js/me-interesa.js` |
| Registro / ampliación | `Application/View/registro/` + `Public/js/registro.js` |
| Publicar | `Application/View/publicar/` + `Public/js/publicar.js` |
| Destino seguro `?next=` | `Public/js/shared/next.js` |
| Registro pendiente de confirmar correo | `Public/js/shared/registro-pendiente.js` |
| Subida de fotos a Supabase Storage | `Public/js/shared/storage.js` |
| Navegación pública, menú de cuenta | `Public/js/public-ui.js` |
| Cerrar sesión (único) | `signOutEverywhere()` en `Public/js/shared/auth-gate.js` |
| Estilos de formularios | `Public/css/onboarding.css` (`signup-*`, `publish-*`) |

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
  regla de identificación en PHP, cambia también `REGLAS_SERVIDOR`**. Ajustes → Perfil todavía no usa `telefono.js`
  (el servidor sí valida).
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
- `AnimalPublicacionController::imagenUrl()` solo acepta `https://` absoluto,
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
- `PATCH api/v1/mi-perfil` `{ alias?, telefono?, fotoUrl? }` (sesión): solo cambian las claves enviadas; `alias: ""` o
  `fotoUrl: null` las dejan en NULL. El nombre, la identificación y el correo **no** se editan (422). La foto exige
  `https://` (`AnimalPublicacionController::imagenUrl($v, 'fotoUrl')`) y el teléfono usa `ValidacionService`. Un teléfono
  nuevo deja histórico (`Persona::actualizarPerfil`); la bitácora (`PERSONA`, `API_MI_PERFIL`) **no guarda el teléfono**,
  solo `telefonoCambiado`.
- `api/v1/actividad` devuelve `persona.fotoUrl`; de ahí sale el perfil en caché del navegador y el avatar del encabezado
  (imagen solo si es https; si falla, vuelve la inicial).
- Ajustes → Perfil: foto (reutiliza `shared/storage.js`, mismo bucket `publicaciones`), "Quitar foto" y "Editar datos"
  (alias y teléfono). El avatar del encabezado se actualiza al recargar la página.

### Foto del vehículo
- Columna `tbvehiculofotourl VARCHAR(500) NULL` (migración `014vehiculofoto.sql`, en los 4 lugares + diccionario/DER/PDF).
  Una sola foto por vehículo; los vehículos anteriores quedan en NULL.
- `api/v1/mi-vehiculos` acepta `fotoUrl` en `POST` y `PUT` y lo devuelve en `vehiculo` y `vehiculos[]`. Misma validación que la
  foto de perfil (`AnimalPublicacionController::imagenUrl($v, 'fotoUrl')`: solo `https://`, hasta 500, 422 en `errors.fotoUrl`).
- Mi panel → Mis vehículos: el diálogo sube la foto (campo compartido `shared/foto-campo.js`, mismo bucket `publicaciones`) y la fila muestra la miniatura. El navegador **solo envía `fotoUrl` si la foto cambió**; así un PUT sin cambio de foto no la borra.
- En `PUT`, **sin la clave `fotoUrl` la foto se conserva** y `null` o `""` la quita (`Vehiculo::actualizar`). Así el PUT del
  admin (`api/v1/vehiculos`, que no conoce `fotoUrl`) no borra la foto. El admin ve `fotoUrl` en la lectura pero no la edita.

### Oferta de flete (P1-2, en construcción)
- Tabla nueva `tbtransportistaoferta` (DEC-FLETE-001, esquema de **36 tablas**): transportista, vehículo, zona base (`tbdireccion` con
  coordenadas), radio en km, capacidad en cabezas, precio base opcional (`NULL` = a convenir), descripción y estado `ACTIVA`/`PAUSADA`.
  Migración `017transportistaoferta.sql`.
- **No** reutiliza `tbtransportistaflete` (viaje realizado, con método de pago obligatorio y reseñas que apuntan a él) ni
  `tbtransportistahorario` (la disponibilidad va como texto libre). El precio no se calcula por km.
- **API `api/v1/mi-ofertas`** (sesión, Transportista ACTIVO; `MiOfertasController`):
  - `GET` lista las propias en todos los estados (con zona exacta y vehículo).
  - `POST { vehiculoId, direccion, radioKm, capacidad, precio?, descripcion? }` publica (201, queda `ACTIVA`). `PUT` lo mismo + `ofertaId`.
    `PATCH { ofertaId, estado: ACTIVA|PAUSADA }` pausa o reactiva (idempotente). No hay DELETE: se pausa.
  - Reglas: la `direccion` usa el mismo objeto que las fincas pero el **punto (latitud y longitud) es obligatorio**; `radioKm` 1–500,
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
  estado del flete (`NULL` si no pidió flete) y fechas/motivo de las respuestas. Migración `018comprasolicitud.sql`.
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
  - **Sin método de pago en la interfaz:** la API lo acepta (`pagoMetodoId`), pero no hay una lista pública de métodos (`api/v1/metodos-pago` es solo de admin).
    Si se quiere, hace falta un endpoint de lectura para clientes.
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
- Verificar o rechazar, ver el documento con enlace firmado y borrar las fotos 90 días después de verificadas es
  **P2-6** y necesita `SUPABASE_SECRET_KEY` en el servidor.

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

### Me interesa (guardados)
- No hay tabla nueva: se usa `tbanimalpublicacioninteraccion` (tipo `ME_INTERESA`). El historial solo
  crece; la **marca vigente** es la última acción del par persona/publicación: `REGISTRAR` la marca y
  `RETIRAR` la quita (`PublicacionInteraccion::estaMarcada()`).
- `POST api/v1/publicaciones/interacciones` acepta `accion` (`REGISTRAR` por defecto, `RETIRAR` solo
  con `ME_INTERESA`). `RETIRAR` es idempotente (200 con `cambiado:false` si ya no estaba) y se permite
  aunque la publicación ya no esté activa; marcar una no activa sigue dando 409.
- `GET api/v1/publicaciones/interacciones?tipo=ME_INTERESA&pagina&tamanoPagina` (sesión): trae las
  marcadas con los datos de la tarjeta y **en cualquier estado** (la vendida o pausada sigue ahí; la
  pantalla la muestra como "No disponible").
- Con sesión, el listado público `api/v1/publicaciones` agrega `meInteresa` (bool) a cada publicación; sin
  sesión (o con token vencido) la lista sigue pública y sin ese campo. El botón "Me interesa" de la
  tarjeta sale con `data-saved="true"` cuando corresponde.
- `/me-interesa` está en el menú del avatar y en Mi panel; usa la tarjeta **compacta** de la portada
  (`buildCard(..., { compacta: true })`, con "Ver más información") y le agrega "Quitar de Me interesa".
- En Explorar, al tocar "Me interesa" la tarjeta sale del deck, y lo ya marcado no vuelve a aparecer ahí
  (`meInteresa !== true` al cargar): vive en `/me-interesa`.

### Portada, Explorar y tarjetas
- Inicio: hero con buscador, carrusel de publicaciones (4/2/1 por vista,
  autoplay 4 s con pausa, flechas y puntos; fijas con ≤4) y "Cómo funciona".
  En la portada la tarjeta es compacta (foto, nombre, precio y "Ver más
  información"), sin botones de acción y sin navegar al hacer clic.
- Explorar: hero con foto, filtros de tipo/ubicación/precio y fila de
  tarjetas completas con scroll inferior (más de 4).
- **Explorar no muestra tus propias publicaciones** (viven en Mi panel). `explore.js` envía `excluirPropias: 'true'` y el servidor (`AnimalPublicacionController::consultar` → `listarPublicaciones(..., $excluirVendedorId)`) excluye las del vendedor autenticado; así el total y la paginación salen bien. Sin sesión no excluye nada. Es opcional: Inicio, Mi panel y los demás no lo envían. Una publicación propia ya no abre con `explorar?publicacion=<id>`.

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

## 4. Cuidados (lo que ya rompió o puede romper)

1. **Columna nueva = 4 lugares** (SQL canónico, migración MySQL, `schema.sql`
   de Postgres y `migrate.php`). Producción corre `migrate.php` al arrancar el
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
4. **Finales de línea:** `git stash` puede reescribir archivos con CRLF en
   Windows. Las pruebas que buscan `\n` en `Public/css/base.css` y
   `Public/js/shared/api.js` fallan con CRLF; normalizar con
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

## 5. Pendientes

Plan completo, priorizado y repartido entre Carlos, Jeremi y Jeferson:
`Documentation/Sprints/Plan-Cliente-Admin-2026-10.md`. Los puntos de abajo
están incluidos ahí.

### Backend (para el compañero de backend)
- Editar **nombre, identificación o correo**: sigue sin existir (solo alias, teléfono y foto, ver "Foto de perfil y datos propios").
- **Filtros de Explorar en el servidor.** Ubicación y precio filtran solo la
  página cargada (25) en el navegador; la API solo filtra por `q` y estado.

### Pendiente de P2-5 (va con P2-6)
- ~~Crear el bucket privado `documentos`~~: **resuelto** (06/10).
- Al reemplazar el documento, el archivo anterior queda en el bucket (no hay política de borrado para la persona). La
  limpieza de 90 días de P2-6 debe borrar también los archivos que ya no están en `tbpersonadocumentoruta`.

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

Pasan todas salvo **4 pruebas de frontend que ya fallaban antes de esta línea
de trabajo** (no son regresiones):
- `el shell existente se mejora desde un bootstrap compartido, no con cinco implementaciones`
- `el rail compacto reduce ancho y centra iconos sin alterar el móvil`
- `pueblo diferencia buscar de una sugerencia de ubicacion`
- `los estados vacios heredan la identidad del modulo`

Cualquier otro fallo es una regresión.

**Ojo: dos pruebas vacían la bitácora local.** `Tests/transaction_test.php` y `Tests/pagometodo_test.php` achican
por un momento `tbbitacorasolicitudid` a `VARCHAR(5)` para forzar un fallo, y antes borran **toda fila con
solicitud de más de 5 caracteres**, que son casi todas. Después de correr toda la batería, `/admin/bitacora` queda casi
vacía en local. No pasa en producción (ahí no se corren las pruebas). Arreglarlo exige otra forma de forzar el fallo.

`Tests/registro_publico_test.php` falló una vez (201 esperado, 200 recibido) y
pasó al repetirla: parece intermitente, no relacionada con los alias.

## 7. Registro de cambios

Agrega entradas nuevas **arriba**. Formato: fecha · rama · resumen · archivos clave · cuidados.

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
- Tabla nueva en los 4 lugares (`000instalacioncompleta.sql`, `018comprasolicitud.sql`, `schema.sql` con RLS, `migrate.php`) y cambios en `tbcompra` y `tbventa` (ver "Solicitud de compra (P1-3)"); diccionario, DER, `Decisiones.md` (DEC-COMPRA-001) y PDF.
- El esquema pasa de 36 a **37 tablas**: se actualizaron los mismos README, docs y pruebas que en el cambio anterior.
- `migrate.php`: `EXPECTED_COLUMNS` de `tbcompra`, `tbventa` y la tabla nueva, y `ensureCurrentColumns()` con `ADD COLUMN IF NOT EXISTS` y `DROP NOT NULL`.
- Cuidado: una base MySQL existente necesita `Database/Migrations/018comprasolicitud.sql` (y la 017 si falta).
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
- Tabla nueva en los 4 lugares (`000instalacioncompleta.sql`, `017transportistaoferta.sql`, `schema.sql` con RLS, `migrate.php`), diccionario, DER (con relaciones), `Decisiones.md` (DEC-FLETE-001) y los 3 PDF regenerados. Sin código de aplicación todavía.
- El esquema pasa de 35 a **36 tablas**: se actualizaron README, GuiaDefensa, Respaldos, `Database/Tests`, `schema_manifest_test`, `db_ready_test`, `instalacion_limpia_test`, `naming_eval` y los tres archivos de `services/supabase-database`.
- Cuidado: una base MySQL existente necesita `Database/Migrations/017transportistaoferta.sql`.

### 2026-10-05 · jefersonbustamante · Explorar sin publicaciones propias y sin portada con sesión
- API: `excluirPropias` en `api/v1/publicaciones` (sin cambio de contrato para quien no lo envía). Frontend: Explorar lo envía; `public-ui.js` (`public-13`) quita Inicio y redirige la portada a Explorar con sesión.
- Archivos: `AnimalPublicacionController.php`, `AnimalComercial.php` (`listarPublicaciones`), `explore.js` (`explore-10`), `public-ui.js`, `home/index.php` (`data-portada`); `?v=` de `public-ui.js` y `explore.js` subidos en todas las vistas.
- Pruebas: ampliada `Tests/api_publicaciones_test.php`; nueva `Tests/frontend/explorar_sin_propias.test.mjs`.

### 2026-10-05 · jefersonbustamante · P1-5 pantalla de foto de vehículo y foto en el diálogo de editar publicación (P0-2)
- Mi panel: los diálogos de **vehículo** y de **publicación** tienen campo de foto (elegir, vista previa, quitar). La fila del vehículo muestra miniatura (`fa-truck` si no hay). Sin cambios de API ni de esquema.
- Archivos: nuevo `Public/js/shared/foto-campo.js` (`montarCampoFoto`; `resolver()` devuelve `undefined` sin cambio, `null` al quitar, o la URL subida), `mi-actividad.js` (`panel-8`, import `foto-campo-1`), vista de Mi panel. Reutiliza `.publish-dropzone` y `.publish-preview` de `onboarding.css` (sin CSS nuevo).
- Pruebas: nueva `Tests/frontend/panel_fotos.test.mjs`. Quedan solo las 4 pruebas de frontend que ya fallaban.
- Cuidado: no enviar `fotoUrl`/`imagenUrl` cuando no cambió la foto (el PUT del vehículo borra la foto con `null`). Tras un merge, `Public/js/shared/api.js` puede quedar con CRLF y fallar `public_identity_auth` (Cuidados #4).

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
