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
- "Mis publicaciones" se arma en el navegador (ver Pendientes de backend).

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
   `supabase-auth.js?v=session-2`, `auth-gate.js?v=auth-gate-4`).
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

### Backend (para el compañero de backend)
- **Endpoint de "mis publicaciones".** Hoy `ownPublications()` en
  `mi-actividad.js` busca por nombre de finca y cruza finca + vendedor: dos
  personas homónimas con fincas homónimas se confundirían. Agregar un filtro
  autenticado (p. ej. `consulta.mias = true`) a `api/v1/publicaciones` y
  reemplazar esa heurística.
- **Edición de identidad.** No existe endpoint para que la persona edite su
  nombre, alias o teléfono; Ajustes → Perfil es solo lectura.
- **Filtros de Explorar en el servidor.** Ubicación y precio filtran solo la
  página cargada (25) en el navegador; la API solo filtra por `q` y estado.
- Cerrar/editar/despublicar una publicación propia (hoy solo se crea).

### Configuración de Supabase (panel, no código)
- Bucket `publicaciones` público + política de subida:
  ```sql
  create policy "Subir imágenes propias" on storage.objects
    for insert to authenticated
    with check (bucket_id = 'publicaciones' and (storage.foldername(name))[1] = auth.uid()::text);
  ```
- Decidir "Confirm email": desactivarlo en desarrollo o configurar SMTP propio
  (Resend/Brevo/SendGrid) y el Site URL para producción.

### Frontend
- "Pasar" en Explorar solo guarda la acción (antes avanzaba el carrusel).
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
