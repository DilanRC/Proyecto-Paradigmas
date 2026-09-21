# Informe — Sprint 22 de septiembre · Sesiones reales + capacidades + fincas

> **Ruta del proyecto:** `/home/genaro/Documentos/2026/Segundo ciclo/Paradigmas/Proyecto-Paradigmas/`
> **Plan implementado:** `Plan-Sprint22Septiembre-SesionesCapacidadesFincas.md`
> **Rama de trabajo:** `test/Validaciones` (HEAD `6c05f27` previo al sprint; **sin commits**, según instrucción del usuario)
> **Fecha de verificación:** 20-sep-2026, sobre recreación limpia (`docker compose down -v && up --build -d`)

---

## 1. Contexto del proyecto

**TinderCows** — plataforma de ganadería (compra/venta/subastas transp. de ganado).
Arquitectura de responsabilidades separadas con un **backend PHP propio** y una
**vista pública + paneles administrativos** en PHP/JS plano (sin framework).

### Componentes principales

| Ruta | Rol |
|---|---|
| `Application/Controller/` | Contratos HTTP: `{success,message,data,errors}` en todas las API. |
| `Application/Service/` | Lógica transaccional: `ValidacionService`, `CapacidadService`, `CompradorClasificacionService`, etc. |
| `Application/Model/` | Acceso a datos con **locks MySQL** (`GET_LOCK`…) y `MAX(id)+1`; sin CHECK ni triggers privilegiados. |
| `Application/Auth/` | `SupabaseActorResolver` (Bearer → actor) y `AuthGuard` (`requerirAutenticado`). |
| `Application/View/` | Vistas PHP: públicas (`home`, `login`, `explorar`) y paneles admin (`productores`, `compradores`, `transportistas`, `vehiculos`, `pagometodos`). |
| `Public/api/` | Puntos de entrada HTTP. |
| `Public/js/` + `Public/js/shared/` | Módulos ES del navegador (`api.js`, `auth-gate.js`, `sesion.js`, `capacidades.js`, `inscripcion.js`, `fincas-lista.js`, …). |
| `Tests/` | Suite PHP (controladores + HTTP) y frontend (`*.test.mjs`, evals). |
| `Tools/seed-maestra.php` | Semilla maestra idempotente (DEC-32), por servicios PHP. |
| `Database/sql/` | Init de instalación: `000instalacioncompleta.sql`, `101initialpagometodo.sql`, `103exampleproductores.sql`. |

### Invariantes de diseño (DECs vigentes que este sprint respeta)

- **Persona ≠ Productor ≠ Comprador ≠ Transportista** (DEC-27/28/29): una misma
  Persona (`tbpersona`) participa en cualquiera de esos contextos; el sistema
  **no obliga a entender el modelo de datos**.
- **Superficies** (DEC-30): públicas sin guard = `identidad`, `publicaciones`,
  `productores-ubicacion`; admin con JWT Supabase en todos los verbos =
  `productores`, `productores-direccion`, `transportistas`, `vehiculos`,
  `pagometodos`, `compradores`, `fincas-direccion`,
  `transportistas-vehiculos` → sin sesión responden **401 `SIN_SESION`**.
- **Sobre de respuesta**: `{success,message,data,errors}` intacto en todas las
  API; `tbpagometodo` conserva exactamente 1 fila.
- **Semilla maestra** (DEC-32): idempotente, `--check` = COMPLETA, IDs civiles
  fijos (`104550123`, `3101556677`, `3101333344`, `108550999`), placas
  `ABC-148`/`ABC-901`, fincas La Primavera/El Bosque/Los Cerros.
- **Política de duplicados** (DEC-31): identificación de persona = duplicado
  imposible (409); nombre de finca repetido entre productores distintos =
  advertencia, nunca bloqueo.

---

## 2. Cambios realizados en este sprint

### TRAMO A (P0) — Sesión real del navegador ↔ backend

Objetivo: que el front **distinga autenticado (escritura) de público (solo
lectura)** sin inventar un login de servidor (el backend exige Bearer, DEC-30).

| Archivo | Cambio |
|---|---|
| **NUEVO** `Public/js/shared/sesion.js` | Resuelve la superficie con `GET api/identidad.php` (`resolverSuperficie`): con Bearer → actor; sin Bearer o 401 → modo público (`PUBLICO`/`SIN_SESION`). Conserva actor + Bearer en `sessionStorage` (`tindercows:actor`, `tindercows:bearer`) solo con sesión real. `flujoLogin` orquesta el login. |
| **MOD** `Public/js/login.js` | Al enviar el formulario corre `flujoLogin`; si el navegador porta Bearer real guarda el actor (sesión verificada), si no queda en modo público de solo lectura con mensaje claro. Conserva `resolveNext` y el marcador local del demo. |
| **MOD** `Public/js/shared/api.js` | `setBearer`/`getBearer`; `request()` adjunta `Authorization: Bearer …` cuando hay token (los headers explícitos del llamador tienen prioridad). |
| **MOD** `Public/js/shared/auth-gate.js` | Al arrancar el shell privado, `enriquecerSuperficie` carga el Bearer al API y resuelve la identidad (no bloqueante, no rompe el gate del marcador). |
| **MOD** `Documentation/Decisiones.md` | **DEC-33 – Superficie del navegador: identidad resuelve autenticado vs público.** |
| **NUEVO** `Tests/frontend/sesion.test.mjs` | `identidad.php` distingue modos; sin Bearer no se fabrica sesión; 401 ≠ fallo de red; `flujoLogin` conserva actor+bearer solo con persona real. |

**Comportamiento resultante:** con Bearer real las superficies admin funcionan
de verdad; en el demo local (sin Bearer) el navegador queda en **modo público**:
los 401 `SIN_SESION` se traducen a "inicie sesión", nunca a error genérico.

### TRAMO B (P0) — Flujo no-CRUD de inscripción desde la vista

Objetivo: inscribirse/abandonar/reactivar contextos desde la **acción natural
de la vista** (comprar, vender, fletear), sin menú administrativo.

| Archivo | Cambio |
|---|---|
| **NUEVO** `Public/js/shared/inscripcion.js` | Catálogo `CONTEXTOS_INSCRIPCION` (comprador/productor/transportista con su acción de negocio), `accionParaContexto` (activo→abandonar, inactivo→reactivar, no registrado→inscribir), `enviarAccionInscripcion` contra `capacidades.php` (POST `{accion,contexto,identificacionNumero,motivo,datosPersona}`) y `montarInscripcion` (render accesible: sin sesión → aviso + enlace a `login.php`; con sesión → estado y botón de cada contexto; 401 → "inicie sesión"). |
| **MOD** `Application/View/explorar/index.php` | Sección "Participa sin dominar el modelo de datos" con `[data-inscripcion-contextos]`. |
| **MOD** `Public/js/explore.js` | `montarInscripcion` al inicializar la vista Explorar. |
| **MOD** `Public/css/explore.css` | Estilos `.inscripcion…` con tokens (`--tc-*`), verificado por el gate de contraste AA. |
| **NUEVO** `Tests/frontend/inscripcion.test.mjs` | Catálogo, decisión de acción por estado, cuerpo exacto de `capacidades.php`, 401 → `requiereSesion`, fallo de red ≠ sesión vencida. |

### TRAMO C (P1) — Fincas repetibles con dirección semántica

Objetivo: reemplazar el campo agrupado (textarea) por un componente **"Agregar
finca"** donde cada finca tiene su nombre y su propia dirección.

| Archivo | Cambio |
|---|---|
| **NUEVO** `Public/js/shared/fincas-lista.js` | Componente de lista editable: fila = input nombre + toggle "Dirección ▾" (cascada provincia→cantón→distrito→pueblo→señas con `conectarDireccion`) + botón Quitar. `obtenerFincas()`, `hidratar()`, `reiniciar()`. |
| **MOD** `Application/View/productores/index.php` | `textarea#fincas-nombres` → `#fincas-lista` + botón `#agregar-finca` + `#error-fincas`. |
| **MOD** `Public/js/productores.js` | `normalizarFincas` (payload `fincas` solo como `[{nombre}]`, recorta, descarta vacíos, **no repite** nombres); hidrata la lista al editar; tras guardar asocia la dirección de cada finca con `fincas-direccion.php` (201/422/404). |
| **MOD** `Public/css/admin-refinements.css` | Estilos `.finca-fila` con tokens (`--surface`, `--border`, `--primary`…). |
| **MOD** `Public/js/shared/field-errors.js` | Comentario de contrato actualizado (colapso `fincas.N` sigue vigente). |
| **MOD** `Tests/ui_test.js` | Aserción del componente ("Fincas deben capturarse por nombre con el componente Agregar finca"). |
| **MOD** `Tests/frontend/payload_parity.test.mjs` | Paridad del nuevo cuerpo: `fincas:[{nombre}]` (la dirección viaja por `fincas-direccion.php`), dedupe sin distinguir mayúsculas. |
| **MOD** `Documentation/Decisiones.md` | **DEC-34 – Fincas repetibles con dirección semántica.** |

**Nota de contrato:** el backend (`ValidacionService`) exige `fincas`
"únicamente nombre"; por eso la dirección de cada finca se asocia con el API
dedicado `fincas-direccion.php`, cumpliendo la aceptación "cada finca expone su
dirección" sin romper el cuerpo del alta.

### TRAMO D (P1) — Política de duplicados demostrable

| Archivo | Cambio |
|---|---|
| **MOD** `Tests/duplicados_test.php` | Regla 4 explícita: **reenvío idempotente de la misma identificación no duplica** — el alta administrativa responde 409 "ya está registrada" y los conteos quedan en 1 persona / 1 contexto Productor. Las otras 3 reglas ya estaban (persona 409 imposible; finca repetida → advertencia sin bloqueo; persona ya inscrita con datos idénticos → 201 compartiendo persona). La inscripción idempotente por contexto (200 ACTIVO) la cubre `capacidad_test.php`. |
| **MOD** `Documentation/Decisiones.md` | DEC-31 referenciado con las 4 reglas (ya documentado previamente). |

**Gates:** `duplicados_test.php` pasa sin tocar esquema (naming_gate verde).

### TRAMO E (P1) — Semilla maestra + instalación limpia

| Archivo | Cambio |
|---|---|
| **MOD** `Tests/instalacion_limpia_test.php` | Se elimina la **transacción madre** que envolvía la primera corrida de la semilla (anidaba `beginTransaction` sobre la misma conexión y fallaba en BD fresca con *"already an active transaction"*). La semilla transacciona por llamada, igual que la API real; la idempotencia se comprueba comparando conteos de **dos corridas completas**. |

Con esto el gate pasa **sobre instalación limpia** (el requisito del plan),
no solo sobre una BD ya sembrada. `Tools/seed-maestra.php` no cambió: sigue
siendo la semilla idempotente por servicios PHP (DEC-32).

### Documentación y pruebas

| Archivo | Cambio |
|---|---|
| `Documentation/Decisiones.md` | DEC-33 y DEC-34 agregadas (al final, tras DEC-32). |
| `Documentation/RutasFrontend.md` | Tabla de APIs con `identidad.php` y `capacidades.php`; sección de autenticación con superficie real e inscripción desde la vista. |
| `Tests/README.md` | Verificación transversal del sprint; tests nuevos de frontend; reglas de `duplicados_test.php` actualizadas. |

---

## 3. Verificación transversal (gates, sobre instalación limpia)

Comando de cierre ejecutado: `docker compose down -v && docker compose up --build -d`.

| Gate | Resultado |
|---|---|
| `naming_gate.php` | ✅ OK |
| `schema_test.php` | ✅ OK |
| `instalacion_limpia_test.php` | ✅ OK (semilla idempotente, 32 tablas, sin huérfanos) |
| `capacidad_test.php` | ✅ OK (inscribir/abandonar/reactivar uniformes e idempotentes) |
| `comprador_clasificacion_test.php` | ✅ OK |
| `comprador_test.php` | ✅ OK |
| `duplicados_test.php` | ✅ OK (4 reglas DEC-31) |
| `transportista_test.php` | ✅ OK |
| `vehiculo_test.php` | ✅ OK |
| `pagometodo_test.php` | ✅ OK (1 fila tbpagometodo) |
| `api_requires_test.php` | ✅ OK |
| `concurrency_test.php` | ✅ OK |
| `finca_direccion_test.php` | ✅ OK (Tramo C) |
| `frontend_capacidades_eval.js` | ✅ OK (17/17, score 1.00) |
| `frontend_contract_test.js` | ✅ OK |
| `ui_test.js` | ✅ OK |
| `Tests/frontend/*.test.mjs` | ✅ **237/237** (incluye `sesion.test.mjs`, `inscripcion.test.mjs`, payload paridad) |
| `frontend_contrast_test.mjs` | ✅ OK (16 combinaciones AA, sin rgba ni tipografías fantasma) |
| `official_app_shell.eval.mjs` | ✅ OK (13/13) |

Estado final de la instalación:

```
Semilla maestra COMPLETA.   (Tools/seed-maestra.php --check)
identidad pública → {"success":true, ..., "persona":null}
productores admin sin sesión → 401 {"errors":{"auth":"SIN_SESION"}}   (por diseño, DEC-30)
productores-ubicacion pública → 200 con sobre intacto
```

Nota de mapeo: el plan lista `capacidad_comprador_test` y `capacidades_eval.js`;
en el repo esos gates corresponden a `capacidad_test.php` +
`comprador_clasificacion_test.php` y a `frontend_capacidades_eval.js`
respectivamente (se ejecutaron los archivos reales).

---

## 4. Cómo correr el proyecto (receta)

```bash
cd "/home/genaro/Documentos/2026/Segundo ciclo/Paradigmas/Proyecto-Paradigmas"
cp .env.example .env          # credenciales locales (DB_HOST=db, etc.)
docker compose up --build -d
# si se quiere una instalación 100% limpia:
#   docker compose down -v && docker compose up --build -d

# Semilla maestra (idempotente):
docker compose exec -T app php Tools/seed-maestra.php          # sembrar
docker compose exec -T app php Tools/seed-maestra.php --check   # auditar (COMPLETA)

# Verificación transversal:
for t in naming_gate schema_test instalacion_limpia_test capacidad_test \
         comprador_clasificacion_test comprador_test duplicados_test \
         transportista_test vehiculo_test pagometodo_test api_requires_test \
         concurrency_test; do
  docker compose exec -T app php Tests/$t.php
done
node Tests/frontend_capacidades_eval.js
node Tests/frontend_contract_test.js
node --test Tests/frontend/*.test.mjs

# Accesos locales
#   App:            http://localhost:8080  (Explorar público / login demo)
#   phpMyAdmin:     http://localhost:8081
#   Supabase sidecar: http://localhost:54321 (verify de Bearer)
```

---

## 5. Estado del demo local y decisión pendiente

El listado de `productores.php` **no aparece en el demo local por diseño**
(DEC-30): sin JWT Supabase la API admin responde `401 SIN_SESION`, y el login
local guarda solo un marcador de navegación (el backend resuelve la superficie
por Bearer). Este sprint **no cambió esa política** (decisión previa del
usuario: "solo diagnóstico, no tocar nada"). Lo que sí quedó operativo:

- `identidad.php` (público) resuelve la superficie y es la fuente para
  "inicie sesión" en la vista;
- con un Bearer real del proveedor, el navegador conserva actor + token y las
  superficies admin funcionan (Tramo A);
- la semilla maestra está **aplicada y COMPLETA** en la BD actual (los 2
  productores visibles en DB son los ejemplos de `103exampleproductores.sql`
  más los 2 sembrados: `104550123` y `3101556677`).

Caminos (ambos **no ejecutados** aquí, según decisión previa): re-aplicar la
semilla (`--sembrar` + `--check`) o implementar un "modo demo" con flag de
entorno en `AuthGuard` (actualizar DEC-30 y los gates que asertan el 401).

---

## 6. Resumen de archivos tocados

**Nuevos**
- `Public/js/shared/sesion.js`
- `Public/js/shared/inscripcion.js`
- `Public/js/shared/fincas-lista.js`
- `Tests/frontend/sesion.test.mjs`
- `Tests/frontend/inscripcion.test.mjs`
- `Informe-Sprint22Septiembre-ContextoYCambios.md` (este documento)

**Modificados**
- `Public/js/login.js`, `Public/js/shared/api.js`, `Public/js/shared/auth-gate.js`
- `Public/js/explore.js`, `Public/css/explore.css`
- `Public/js/productores.js`, `Public/css/admin-refinements.css`,
  `Public/js/shared/field-errors.js`
- `Application/View/explorar/index.php`, `Application/View/productores/index.php`
- `Tests/duplicados_test.php`, `Tests/instalacion_limpia_test.php`,
  `Tests/ui_test.js`, `Tests/frontend/payload_parity.test.mjs`
- `Documentation/Decisiones.md` (DEC-33, DEC-34),
  `Documentation/RutasFrontend.md`, `Tests/README.md`

Ningún archivo de `Database/sql/` (esquema), `Application/Service` de dominio,
ni API del backend cambió: el sprint es frontend + pruebas + documentación
sobre el backend ya consolidado de Avance 3.