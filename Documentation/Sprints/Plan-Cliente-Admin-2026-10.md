# Plan de trabajo: cliente y administrador (octubre 2026)

Fecha: 2026-10-04 · Base: rama `dev` después del PR #34.

Este documento junta tres fuentes:
1. Lo que hoy **falta** en la aplicación, según una revisión de las 34 tablas
   contra el código y las pantallas.
2. Lo nuevo que se pidió: fletes cercanos, página de "Me interesa", fletes cerca
   de una publicación, fotos de vehículos y foto de perfil.
3. El issue de backend de la reunión del 29/09/26 (registro, animales,
   catálogos, guardados y chat).

Las tareas están **ordenadas por prioridad** (P0 primero) y repartidas entre
**Carlos, Jeremi y Jeferson**. El reparto es una propuesta: Jeferson lleva el
frontend (así está en `MEMORIA.md`); Carlos y Jeremi se reparten el backend.
Cada tarea indica quién la lidera y quién la apoya.

---

## 0. Antes de empezar: choques con las reglas del proyecto

El issue de la reunión propone cosas que chocan con `AGENTS.md`. Hay que
decidirlas primero:

| Lo que pide el issue | Regla actual del proyecto | Propuesta |
|---|---|---|
| Índices `UNIQUE` y llaves foráneas (`FK`) | El esquema **no usa** PK, FK, UNIQUE ni DEFAULT; las reglas viven en PHP | Validar la unicidad en PHP con `NamedLock`, como ya se hace en las altas |
| Nombres `tbAnimal`, `tbRaza`, `tbVacunacion` | Minúscula `tb<tabla><campo>` | `tbespecie`, `tbanimaltipo`, `tbraza`, `tbanimalvacunacion`… |
| Rutas `/api/usuarios/...`, `/api/publicaciones/{id}/guardar` | Rutas `api/v1/<recurso>` declaradas en `Public/.htaccess` | Usar `api/v1/...` con el cuerpo JSON, como las demás |
| Tabla nueva `tbPublicacionGuardada` ("Me encanta") | Ya existe `tbanimalpublicacioninteraccion` con el tipo `ME_INTERESA` | **Reutilizarla**; no crear otra tabla (ver P1-1) |
| WebSockets con SignalR o Socket.IO | El backend es PHP en contenedores de Vercel, que no mantienen conexiones abiertas | Usar **Supabase Realtime** (ya usamos Supabase); ver P3-2 |
| Precio en la publicación | Ya está en `tbanimalpublicacion` | Nada que hacer |

Recordatorio: **cada columna nueva va en 4 lugares** (SQL completo, migración
MySQL, `schema.sql` de Postgres y `migrate.php`), o producción se rompe. Los
alias SQL van **en minúscula** (ver `MEMORIA.md`, Cuidados #3).

---

## 1. Resumen por persona

| Prioridad | Tarea | Carlos | Jeremi | Jeferson |
|---|---|---|---|---|
| P0-1 | Acceso en producción (confirmación de correo y unión por ID de Supabase) | **Lidera** | | Apoya |
| P0-2 | Mis publicaciones: listar, editar, pausar y cerrar — **HECHO** (falta foto en el diálogo) | **Lidera** (API) | | **Lidera** (pantalla) |
| P1-1 | Página "Me interesa" (guardados) — **HECHO** (falta "Ver fletes cercanos") | | **Lidera** (API) | **Lidera** (pantalla) |
| P1-2 | Fletes cercanos (los fletes funcionan como publicaciones) | **Lidera** (API) | | **Lidera** (pantalla) |
| P1-3 | Fletes cerca de una publicación y solicitud de compra (animal, o animal + flete) | **Lidera** (API) | Apoya | **Lidera** (pantalla) |
| P1-4 | Foto de perfil y edición de datos personales — **HECHO** | | **Lidera** (API) | **Lidera** (pantalla) |
| P1-5 | Fotos de vehículos — **API HECHA** (falta pantalla) | | **Lidera** (API) | **Lidera** (pantalla) |
| P1-6 | Administrador: moderar publicaciones — **HECHO** | Apoya | | **Lidera** |
| P2-1 | Validación en tiempo real de cédula y correo — **HECHO** | | **Lidera** | Apoya |
| P2-2 | Modelo de animal: especie, tipo, raza, nacimiento, partos y estado | **Lidera** | | Apoya |
| P2-3 | Historial de vacunación | **Lidera** | | Apoya |
| P2-4 | Mis animales (inventario) | Apoya | | **Lidera** |
| P2-5 | Foto del documento de identidad y verificación — **SUBIDA HECHA** (falta bucket en Supabase; verificación en P2-6) | | **Lidera** | Apoya |
| P2-6 | Administrador: catálogos, verificación de identidad y fletes | | **Lidera** | Apoya |
| P3-1 | Comerciante (solo investigación) | | **Lidera** | |
| P3-2 | Chat y comentarios en tiempo real (solo planificación) | **Lidera** | | |
| P3-3 | Limpieza de tablas sin uso y decisión sobre el carrito | **Lidera** | | |
| P3-4 | Administrador: gestionar administradores y ver la bitácora | | **Lidera** | Apoya |

---

## 2. Parte del cliente (comprador, vendedor, transportista)

### Qué puede hacer hoy

- Registrarse (empieza como Comprador), iniciar y cerrar sesión.
- Activar Vendedor (con sus fincas) y Transportista en Ajustes → Cómo participo.
- Ver publicaciones en Inicio y Explorar (ordenadas por cercanía si da su
  ubicación) y marcar "Me interesa" o "Pasar".
- Publicar un animal con foto.
- En Mi panel: ver sus publicaciones (de forma aproximada), sus fincas y sus vehículos.

### Qué no puede hacer todavía

- Ver las publicaciones que marcó con "Me interesa".
- Editar, pausar o cerrar sus publicaciones.
- Ver fletes: la página Fletes solo muestra el enlace para configurar la actividad.
- Pedir un flete o comprar un animal.
- Poner foto de perfil o editar su nombre, alias o teléfono.
- Poner fotos de sus vehículos.

---

### P0-1 · Acceso en producción · Carlos (+ Jeferson)

**Problema:** en producción, quien se registra no puede entrar porque Supabase
exige confirmar el correo y los correos no llegan (no hay servidor de correo propio).

- [ ] Que el dueño del proyecto de Supabase **desactive "Confirm email"** y
      confirme a mano las cuentas que ya existen (Authentication → Users).
- [ ] Que el dueño agregue al equipo como miembros del proyecto de Supabase.
- [ ] **Unir la cuenta con la persona por el ID de usuario de Supabase**
      (`sub` del JWT) en vez de por el correo. Columna nueva
      `tbpersonaauthid VARCHAR(64) NULL` en los 4 lugares. Cambiar la búsqueda en
      `SupabaseActorResolver.php`. Así nadie se puede adueñar de una persona
      creada por un administrador registrándose con su correo.
- [ ] Más adelante: servidor de correo propio (Resend o Brevo) y volver a
      activar la confirmación.

### P0-2 · Mis publicaciones: listar, editar, pausar y cerrar · Carlos (API) + Jeferson (pantalla)

**Estado (2026-10-04): hecho por Jeferson en la rama `jefersonbustamante`**, API y pantalla (detalle en `MEMORIA.md`). Carlos: solo falta tu revisión. El estado "cerrado" quedó como `RETIRADO`.

Hoy una publicación se crea y nunca se cierra: `tbanimalpublicacionestadoperiodo`
se abre al publicar y nada lo cierra. Y "Mis publicaciones" se arma en el
navegador buscando por nombre de finca, lo que confunde a homónimos.

- [x] `GET api/v1/publicaciones` con `mias=true` (solo con sesión): devuelve
      las publicaciones del vendedor autenticado en todos sus estados.
- [x] `PATCH api/v1/publicaciones`: editar precio, título, descripción y foto
      de una publicación propia (el API acepta la foto; el diálogo de Mi panel aún no la muestra).
- [x] Cambio de estado (`ACTIVO`, `PAUSADO`, `VENDIDO`, `RETIRADO`): cerrar el
      periodo vigente y abrir otro; dejar registro en `tbbitacora`.
- [x] Mi panel usa `mias=true` en lugar de la búsqueda por nombre de finca
      (`ownPublications()` en `mi-actividad.js`).
- [x] Botones Editar, Pausar y Marcar como vendida en cada tarjeta de Mi panel.

### P1-1 · Página "Me interesa" (guardados) · Jeremi (API) + Jeferson (pantalla)

**Estado (2026-10-04): hecho por Jeferson en la rama `jefersonbustamante`**, API y pantalla (detalle en `MEMORIA.md`). Jeremi: solo falta tu revisión. Queda pendiente el botón "Ver fletes cercanos" (depende de P1-3).

Cubre el punto 8 del issue ("Me encanta"). **Ya existe** la tabla
`tbanimalpublicacioninteraccion` con el tipo `ME_INTERESA`; hoy solo se escribe
(`POST api/v1/publicaciones/interacciones`) y nadie la lee.

- [x] `GET api/v1/publicaciones/interacciones?tipo=ME_INTERESA` paginado: las
      publicaciones que la persona autenticada marcó, con todos los datos de la tarjeta.
- [x] Quitar de "Me interesa": registrar la acción `RETIRAR` (hoy solo existe
      `REGISTRAR`), así se conserva el historial. Debe ser idempotente.
- [x] Agregar `meInteresa: true|false` a cada publicación del listado cuando hay sesión.
- [x] Una publicación vendida o cerrada aparece como **"No disponible"** (no desaparece).
- [x] Página nueva `/me-interesa`, enlazada desde el menú del avatar y desde
      Mi panel. Usa `buildCard()` de `explore.js`.
- [ ] Botón para quitar la publicación de la lista y botón "Ver fletes cercanos"
      (lleva a P1-3).

### P1-2 · Fletes cercanos · Carlos (API) + Jeferson (pantalla)

La página Fletes debe funcionar como las publicaciones: el transportista ofrece
su servicio y el cliente ve los fletes **cercanos a su ubicación**.

Hay que separar dos conceptos:
- **Oferta de flete:** el transportista con su zona base, su vehículo, su
  capacidad, su precio y su horario. **Hoy no existe** como tabla.
- **Flete realizado:** un viaje concreto, con origen, destino, fecha y precio.
  Esto es `tbtransportistaflete`, que ya existe pero no se usa.

Tareas:
- [ ] **Decidir el modelo** (Carlos lo propone antes de programar). Propuesta:
      tabla `tbtransportistaoferta` (transportista, vehículo, `tbdireccionid` de
      la zona base, radio en km, precio por km o precio base, capacidad en
      cabezas, descripción, estado), con su horario en `tbtransportistahorario`,
      que ya existe y no se usa.
- [ ] `POST`, `PATCH` y `GET api/v1/fletes`: el transportista crea y edita sus ofertas.
- [ ] `GET api/v1/fletes?latitud=&longitud=`: ofertas activas ordenadas por
      distancia. **Reutilizar** la fórmula de distancia (Haversine) de
      `PublicacionCercaniaService`.
- [ ] Página Fletes: si la persona es Transportista, ve "Mis ofertas" y puede
      publicar una. Si no, ve las ofertas cercanas en tarjetas, con filtros de
      distancia y capacidad.
- [ ] Cada tarjeta muestra la foto del vehículo (P1-5), la zona, la capacidad,
      el precio y el horario.

### P1-3 · Fletes cerca de una publicación y solicitud de compra · Carlos (API) + Jeferson (pantalla), con apoyo de Jeremi

Desde una publicación marcada con "Me interesa", el cliente puede ver los fletes
cercanos a **la finca del animal** y decidir si compra solo el animal o el
animal con flete.

- [ ] `GET api/v1/fletes?publicacionId=`: ofertas cercanas a las coordenadas de
      la finca de la publicación (`tbfincadireccion` → `tbdireccion`).
- [ ] **Solicitud de compra.** No hay pagos en línea, así que es una solicitud
      que el vendedor acepta o rechaza. Propuesta: tabla `tbcomprasolicitud`
      (publicación, comprador, oferta de flete opcional, estado `PENDIENTE` /
      `ACEPTADA` / `RECHAZADA` / `CANCELADA`, fecha y mensaje).
- [ ] Cuando el vendedor acepta: registrar `tbventa` y `tbcompra` (ya existen,
      sin uso) y la publicación pasa a `VENDIDO`. Si la solicitud traía flete,
      se crea una solicitud de flete al transportista (P1-2).
- [ ] En el detalle de una publicación: botones **"Comprar animal"** y
      **"Comprar con flete"**. El segundo muestra antes los fletes cercanos.
- [ ] En Mi panel: "Solicitudes recibidas" (vendedor y transportista) y
      "Mis solicitudes" (comprador).

### P1-4 · Foto de perfil y edición de datos personales · Jeremi (API) + Jeferson (pantalla)

**Estado (2026-10-04): hecho por Jeferson en la rama `jefersonbustamante`**, API y pantalla (detalle en `MEMORIA.md`). Jeremi: solo falta tu revisión. Las bases MySQL existentes necesitan la migración `013personafoto.sql`.

- [x] Columna `tbpersonafotourl VARCHAR(500) NULL` en los 4 lugares.
      **No afecta a las cuentas que ya existen:** quedan en `NULL` y se sigue
      mostrando el avatar con iniciales.
- [x] Endpoint para editar los datos propios: foto, alias y teléfono (es el
      pendiente "Edición de identidad" de `MEMORIA.md`). La foto se valida igual
      que la de las publicaciones: solo `https://` y hasta 500 caracteres.
- [x] Subida desde el dispositivo con `Public/js/shared/storage.js` (bucket
      `publicaciones` o un bucket `perfiles` con la misma política).
- [x] Ajustes → Perfil: cambiar o quitar la foto y editar alias y teléfono.
      La foto aparece en el avatar del encabezado.

### P1-5 · Fotos de vehículos · Jeremi (API) + Jeferson (pantalla)

**Estado (2026-10-04): API hecha por Jeremi en la rama `backend`** (detalle en `MEMORIA.md`). Falta la pantalla. Las bases MySQL existentes necesitan la migración `014vehiculofoto.sql`.

- [x] Columna `tbvehiculofotourl VARCHAR(500) NULL` en los 4 lugares (una sola
      foto para empezar; varias fotos necesitarían una tabla aparte).
- [x] Aceptar y devolver `fotoUrl` en `api/v1/mi-vehiculos` (crear y editar),
      con la misma validación `https://`. En `PUT`, sin `fotoUrl` la foto se conserva.
- [ ] Mi panel → Mis vehículos: subir la foto con vista previa (mismo
      componente que en Publicar).
- [ ] La foto se muestra en las tarjetas de fletes (P1-2).

### P2-1 · Validación en tiempo real de cédula y correo · Jeremi (+ Jeferson)

Es el punto 2 del issue. Ya existe `POST api/v1/registro/identificacion`, que
valida el **formato** de la cédula.

**Estado (2026-10-04): hecho por Jeremi en la rama `backend`**, API y pantalla (detalle en `MEMORIA.md` y DEC-REG-001). Reabre la consulta del correo que `d7b5a88` había retirado, ahora con límite por IP (comprobado en Vercel). Máscaras de cédula y teléfono iguales al servidor. Las bases MySQL existentes necesitan la migración `015registroconsulta.sql`.

- [x] Ampliarlo, o crear `api/v1/registro/disponibilidad`, para responder solo
      `{ disponible: bool }` para la cédula y para el correo. Normalizar antes de
      buscar: correo en minúscula y sin espacios, cédula solo con dígitos.
      (Se amplió el endpoint existente: `{ correoElectronico }` o la cédula.)
- [x] Límite de consultas por IP para evitar que se use para averiguar quién
      está registrado. Hoy no hay infraestructura para esto: proponer una tabla
      de conteo o usar el límite de Vercel. (Tabla `tbregistroconsulta`: 20 por
      minuto por IP, 429 después.)
- [x] Al enviar el registro se vuelve a validar en PHP con `NamedLock` (sin
      `UNIQUE`; ver la sección 0). (Ya existía.)
- [x] Frontend: consulta 400 ms después de que se deja de escribir; el mensaje
      va debajo del campo (`field-errors.js`). Máscaras de cédula y teléfono
      iguales en el frontend y en el backend.

### P2-2 · Modelo de animal · Carlos (+ Jeferson)

Son los puntos 4 a 6 del issue. **Lo que ya existe:**
- `tbanimal`: identificación (sirve como arete), sexo, raza (texto libre) y
  características.
- `tbanimalproduccionsalud`: historial con edad en meses, peso, propósito y
  estado reproductivo.
- `tbanimalpublicacion`: precio, título, descripción, foto y finca.

**Lo que falta:** especie, tipo o categoría, raza de catálogo, fecha de
nacimiento, partos, estado del animal y dueño explícito.

- [ ] Primero, entregar la propuesta de modelado con alternativas. Recomendada:
      catálogos con referencia a la especie, que valida mejor que una tabla
      genérica de catálogos:
      - `tbespecie` (id, nombre, activo)
      - `tbanimaltipo` (id, especie, nombre, sexo permitido: `M`, `H` o nulo)
      - `tbraza` (id, especie, nombre, activo)
- [ ] Columnas nuevas en `tbanimal`: especie, tipo, raza, fecha de nacimiento
      (real o estimada), partos y estado (`ACTIVO`, `PUBLICADO`, `VENDIDO`,
      `INACTIVO`). Todas `NULL` para no romper los animales actuales.
- [ ] Validaciones en PHP: el tipo pertenece a la especie, el sexo coincide con
      el tipo y los partos solo aplican a hembras (entero ≥ 0).
- [ ] `GET api/v1/catalogos?especieId=`: especies, tipos y razas. Con datos
      iniciales de las especies y razas más comunes.
- [ ] Decidir si una publicación es de **un animal o de un lote**. Recomendación:
      un animal por ahora; un lote necesita una tabla intermedia.
- [ ] Confirmar el formato del arete de SENASA y si "categorización" es lo mismo que "tipo".
- [ ] Frontend: Publicar usa listas desplegables de especie, tipo y raza
      encadenadas, con el campo de partos solo para hembras.

### P2-3 · Historial de vacunación · Carlos (+ Jeferson)

- [ ] Tabla `tbanimalvacunacion` (animal, vacuna, fecha de aplicación, dosis,
      lote, aplicada por, próxima dosis y observaciones) y catálogo `tbvacuna`,
      o nombre libre si se decide no tener catálogo.
- [ ] Endpoints para registrar, listar y corregir el historial de un animal propio.
- [ ] El detalle de la publicación muestra el historial del animal.

### P2-4 · Mis animales (inventario) · Jeferson (+ Carlos)

- [ ] Sección "Mis animales" en Mi panel: registrar un animal sin publicarlo,
      ver su historial de pesos y vacunas, y un botón "Publicar" que crea la
      publicación a partir del animal.
- [ ] Depende de P2-2 y P2-3.

### P2-5 · Foto del documento de identidad y verificación · Jeremi (+ Jeferson)

Es el punto 1 del issue. **Sin IA generativa.**

**Estado (2026-10-05): subida y estado hechos por Jeremi en la rama `backend`** (detalle en `MEMORIA.md`). Se eligió la opción c. El tipo de documento es el de la identificación (sin columna aparte) y solo se sube en Ajustes → Perfil. Falta que el dueño de Supabase cree el bucket privado `documentos` (SQL en `MEMORIA.md`). Verificar, ver con enlace firmado y borrar a los 90 días queda en P2-6.

- [x] Recomendación: **opción c (captura manual + revisión de un administrador)**
      para empezar.
      - La opción a (OCR con Tesseract) no corre bien en los contenedores de Vercel.
      - La opción b (padrón) no tenemos de dónde importarla de forma legal y estable.
      - La opción c funciona hoy, y el OCR se puede agregar después.
- [ ] Bucket **privado** en Supabase Storage (`documentos`). Solo el dueño sube y
      solo el administrador lee, con enlaces firmados temporales. Tipos jpg, png,
      webp y pdf; máximo 5 MB. (Código y SQL listos; falta que el dueño de Supabase lo cree.)
- [x] Columnas en `tbpersona`: tipo de documento, ruta del archivo y estado de
      verificación (`PENDIENTE`, `VERIFICADO`, `RECHAZADO`). `NULL` para las
      cuentas actuales.
- [x] Definir cuánto tiempo se conserva la foto (propuesta: borrarla 90 días
      después de verificada).
- [x] Frontend: paso opcional al registrarse y en Ajustes → Perfil. (Solo Ajustes → Perfil, por decisión: al
      registrarse puede no haber sesión todavía y sin sesión no se puede subir.)

### P3-1 · Comerciante (solo investigación) · Jeremi

- [ ] No existe en el código ni en la base de datos (revisado). Proponer en el
      issue si es un vendedor con más volumen, una empresa o algo distinto.
      Podría ser una cuarta actividad junto a Comprador, Vendedor y Transportista.
      **No implementar.**

### P3-2 · Chat y comentarios en tiempo real (solo planificación) · Carlos

Es el punto 7 del issue. **No implementar.**

- [ ] Documento de planificación. Punto de partida: el backend PHP en Vercel no
      puede mantener WebSockets abiertos, así que se recomienda **Supabase
      Realtime**. Ya usamos su Auth, y el JWT sirve para autorizar los canales.
- [ ] Modelo: `tbconversacion` (publicación, comprador, vendedor),
      `tbmensaje` y `tbcomentario`.
- [ ] Cubrir: el chat se inicia desde el botón "Contactar" (el tipo `CONTACTAR`
      ya existe en las interacciones), mensajes no leídos, moderación, reportes y
      una estimación de esfuerzo.

### P3-3 · Limpieza de tablas sin uso · Carlos

- [ ] Decidir sobre el carrito (`tbcarrito`, `tbcarritoanimal`,
      `tbcarritoestadoperiodo`): solo se usa en pruebas. Con la solicitud de
      compra de P1-3 probablemente no hace falta.
- [ ] `tbanimalinteraccion` quedó reemplazada por `tbanimalpublicacioninteraccion`.
- [ ] `tbproductoractividad` no tiene ningún código.
- [ ] `tbtransportistaestadoperiodo` tiene código que nadie usa: la app guarda
      el estado en `tbtransportista`.
- [ ] Borrar una tabla requiere el mismo cuidado que agregar una: los 4 lugares,
      el diccionario de datos y el DER.

---

## 3. Parte del administrador

Se entra por `/admin/entrar`. Solo los correos que están en `tbadministrador`
con estado 1, y hoy se agregan a mano en la base de datos.

### Qué puede hacer hoy

| Pantalla | Qué hace |
|---|---|
| `/admin/dashboard` | Resumen |
| `/admin/productores` | Vendedores, sus fincas y direcciones |
| `/admin/compradores` | Compradores |
| `/admin/transportistas` | Transportistas y sus vehículos |
| `/admin/vehiculos` | Vehículos |
| `/admin/metodos-pago` | Catálogo de métodos de pago |

### Qué falta

### P1-6 · Moderar publicaciones · Jeferson (+ Carlos)

**Estado (2026-10-04): hecho por Jeferson en la rama `jefersonbustamante`**, API y pantalla (detalle en `MEMORIA.md`). Carlos: solo falta tu revisión.

- [x] Pantalla `/admin/publicaciones`: todas las publicaciones con su estado,
      vendedor y finca, con buscador.
- [x] Pausar o retirar una publicación con un motivo (usa el cambio de estado de P0-2).

### P2-6 · Catálogos, verificación de identidad y fletes · Jeremi (+ Jeferson)

- [ ] Catálogos: especies, tipos, razas y vacunas (P2-2 y P2-3), con el mismo
      patrón que Métodos de pago.
- [ ] Verificación de identidad: lista de personas en `PENDIENTE`, ver la foto
      del documento con enlace firmado, y Verificar o Rechazar con motivo (P2-5).
- [ ] Fletes: ver las ofertas y las solicitudes; pausar ofertas (P1-2 y P1-3).

### P3-4 · Gestionar administradores y ver la bitácora · Jeremi (+ Jeferson)

- [x] Agregar o desactivar administradores desde el panel, en lugar de editar
      la base a mano. (`/admin/administradores`; nadie se quita su propio acceso ni deja el panel sin administradores.)
- [ ] Visor de `tbbitacora`, filtrado por entidad, fecha y persona. Hoy se
      escribe en cada cambio pero nadie la consulta.
- [ ] Pendiente de decisión: Calidad pidió separar la sesión pública de la
      administrativa y la "política de administrador" sigue sin aprobarse
      (comentario en `Public/js/shared/auth-gate.js`).

---

## 4. Preguntas abiertas

- [ ] ¿Quién es el dueño del proyecto de Supabase y cuándo puede desactivar la
      confirmación de correo? (P0-1)
- [ ] ¿Oferta de flete como tabla nueva, o solo transportista + horario? (P1-2)
- [ ] ¿La solicitud de compra necesita aceptación del vendedor, o se reserva
      directamente? (P1-3)
- [ ] ¿Opción a, b o c para leer el documento? (P2-5; se recomienda la c)
- [ ] ¿"Categorización" es lo mismo que "tipo"? ¿Cuál es el formato del arete de SENASA? (P2-2)
- [ ] ¿Una publicación es un animal o un lote? (P2-2)
- [ ] ¿Qué es "comerciante"? (P3-1)
- [ ] ¿Se mantiene el carrito? (P3-3)
