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
- Iniciar sesión lleva a **Explorar** salvo que `next` traiga un destino
  seguro (por ejemplo `explorar?publicacion=6`).

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
- En `PUT`, **sin la clave `fotoUrl` la foto se conserva** y `null` o `""` la quita (`Vehiculo::actualizar`). Así el PUT del
  admin (`api/v1/vehiculos`, que no conoce `fotoUrl`) no borra la foto. El admin ve `fotoUrl` en la lectura pero no la edita.

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

## 4. Cuidados (lo que ya rompió o puede romper)

1. **Columna nueva = 4 lugares** (SQL canónico, migración MySQL, `schema.sql`
   de Postgres y `migrate.php`). Producción corre `migrate.php` al arrancar el
   contenedor; si falta ahí, el SELECT falla en producción.
2. **Versiones de caché `?v=`**: súbelas al cambiar CSS/JS. Si un módulo
   compartido gana un `export`, versiona su `import` donde se usa (ya pasa con
   `explore.js?v=foto-1`, `business-rules.js?v=panel-2`,
   `supabase-auth.js?v=session-2`, `auth-gate.js?v=auth-gate-5`).
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

## 5. Pendientes

Plan completo, priorizado y repartido entre Carlos, Jeremi y Jeferson:
`Documentation/Sprints/Plan-Cliente-Admin-2026-10.md`. Los puntos de abajo
están incluidos ahí.

### Backend (para el compañero de backend)
- Editar **nombre, identificación o correo**: sigue sin existir (solo alias, teléfono y foto, ver "Foto de perfil y datos propios").
- **Filtros de Explorar en el servidor.** Ubicación y precio filtran solo la
  página cargada (25) en el navegador; la API solo filtra por `q` y estado.
- Editar la **foto** de una publicación desde Mi panel (el API ya acepta `imagenUrl` en el PATCH; falta el campo en el diálogo).
- "Ver fletes cercanos" en `/me-interesa` (depende de P1-3; no hay botón hasta que exista).

### Frontend (pendiente de P1-5)
- Mi panel → Mis vehículos: subir la foto con vista previa (mismo componente que Publicar, `shared/storage.js`) y enviarla como
  `fotoUrl`. El API ya está listo (ver "Foto del vehículo"). Mostrarla en las tarjetas de fletes cuando exista P1-2.

### Configuración de Supabase (panel, no código)
- ~~Bucket `publicaciones` público + política de subida~~: **resuelto** (el bucket ya existe en Supabase; lo usan las fotos de publicaciones y de perfil). Si hubiera que recrearlo:
  ```sql
  create policy "Subir imágenes propias" on storage.objects
    for insert to authenticated
    with check (bucket_id = 'publicaciones' and (storage.foldername(name))[1] = auth.uid()::text);
  ```
- Decidir "Confirm email": desactivarlo en desarrollo o configurar SMTP propio
  (Resend/Brevo/SendGrid) y el Site URL para producción.

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

`Tests/registro_publico_test.php` falló una vez (201 esperado, 200 recibido) y
pasó al repetirla: parece intermitente, no relacionada con los alias.

## 7. Registro de cambios

Agrega entradas nuevas **arriba**. Formato: fecha · rama · resumen · archivos clave · cuidados.

### 2026-10-04 · backend · P1-5 Fotos de vehículos (API)
- Columna nueva `tbvehiculofotourl` en los 4 lugares (`000instalacioncompleta.sql`, `014vehiculofoto.sql`, `schema.sql`, `migrate.php`), diccionario, DER y PDF regenerados.
- `fotoUrl` en `api/v1/mi-vehiculos` (crear, editar y lectura) con validación https (ver "Foto del vehículo"). Sin cambios de pantalla: la parte de Mi panel queda para frontend.
- Archivos: `MiVehiculosController.php`, `Vehiculo.php` (`crear`, `actualizar`, `mapear`), `TransportistaVehiculo.php` (`listarVehiculosPorTransportista`).
- Pruebas: ampliada `Tests/mi_vehiculos_test.php` (foto en alta y edición, conservación sin la clave, quitar con null, rechazo de http/javascript:/data:).
- Cuidado: en una base MySQL ya creada hay que aplicar `Database/Migrations/014vehiculofoto.sql` (producción lo hace `migrate.php` al arrancar).

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
