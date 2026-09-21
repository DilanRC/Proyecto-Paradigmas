# Pruebas

Suite de verificación del backend y del contrato transversal. Se ejecuta contra
la base inicializada con Docker (`docker compose up --build -d`). Las pruebas
PHP generan identificaciones aleatorias (`TST...`) y limpian únicamente sus
propias filas; la semilla maestra usa identificaciones civiles fijas
(`104550123`, `3101556677`, `3101333344`, `108550999`), de modo que nunca
colisionan.

## Gates y suite completa (backend)

Ejecutar la verificación del plan de avance 3 en orden:

```bash
for t in naming_gate schema_manifest_test backend_db_ready_test \
  comprador_test comprador_clasificacion_test capacidad_test \
  duplicados_test duplicados_api_http_test auth_guard_test \
  api_requires_test instalacion_limpia_test api_identidad_test; do
  docker compose exec -T app php Tests/$t.php
done
```

Verificación transversal del sprint de sesiones/capacidades/fincas (sección 3
del plan):

```bash
for t in naming_gate schema_test instalacion_limpia_test capacidad_test \
         comprador_clasificacion_test comprador_test duplicados_test \
         transportista_test vehiculo_test pagometodo_test api_requires_test \
         concurrency_test; do
  docker compose exec -T app php Tests/$t.php
done
node Tests/frontend_capacidades_eval.js
node Tests/frontend_contract_test.js
node --test Tests/frontend/*.test.mjs
```

Gates y pruebas individuales disponibles:

- **`naming_gate.php`** — gate estático de nomenclatura: exige que las tablas,
  columnas y convenciones del esquema cumplan el diccionario (ETL/API), sin
  tocar la base.
- **`schema_manifest_test.php`** — manifiesto canónico del esquema: exige las 32
  tablas (`table_count === 32`, incluye
  `tbcompradorpersonatelefonohistorico` y `tbproductorpersonatelefonohistorico`)
  y verifica el manifiesto contra el esquema vivo en `information_schema`.
- **`backend_db_ready_test.php`** / **`db_ready_test.php`** — base lista:
  tablas presentes, columnas y tipados esperados, sin huérfanos.
- **`comprador_test.php`** — el contexto Comprador vuelve a ser escritura sobre
  `tbcomprador` (DEC-28): inscribir/reactivar/desactivar, reutilización de
  Persona, 409 por identidad distinta y 404 por comprador inexistente, con
  idempotencia y audición en `tbbitacora`.
- **`comprador_clasificacion_test.php`** — el periodo `COMPRADOR` de
  `tbproductorclasificacionperiodo` queda como registro analítico (DEC-29):
  documenta cuándo una persona fue comprador sin gobernar el contexto.
- **`capacidad_test.php`** — capacidades propias de una persona consultadas en
  un perfil u otro (`Public/js/shared/capacidades.js`).
- **`duplicados_test.php`** — política de duplicados a nivel controlador
  (DEC-31), 4 reglas: persona duplicada (identificación + datos distintos) →
  409 imposible; finca con nombre repetido entre productores distintos →
  advertencia sin bloqueo; persona ya inscrita con datos idénticos → 201
  compartiendo persona; y reenvío idempotente de la misma identificación → no
  duplica filas (el alta administrativa responde 409 y la inscripción por
  contexto en `capacidades.php` es la idempotente 200 ACTIVO, probada en
  `capacidad_test.php`).
- **`duplicados_api_http_test.php`** — contrato HTTP de la superficie admin
  (DEC-30/31): las 9 escrituras admin anónimas responden
  `401 errors['auth'] = 'SIN_SESION'` con cuerpos JSON bien formados (incluida
  capacitades con `{accion,contexto,identificacionNumero}` y `{}` → 400 antes
  de la sesión), y las lecturas públicas conservan el sobre
  `{success,message,data,errors}`.
- **`auth_guard_test.php`** / **`auth_actor_test.php`** /
  **`api_requires_test.php`** — guard de sesión: sin sesión → 401, rol
  insuficiente → 403, `Authorization` mal formado → 401, y el orden de
  precedencia 405/415/400/OPTIONS no cambia.
- **`instalacion_limpia_test.php`** — instalación limpia verificable (DEC-32):
  32 tablas vivas, semilla maestra idempotente (dos ejecuciones con los mismos
  conteos), `--check` COMPLETA, mínimos de tablas madres, IDs únicos por tabla
  madre, sin huérfanos SQL, lecturas públicas HTTP y resolución de identidad
  sobre las personas sembradas.
- **`api_identidad_test.php`** — superficie de identidad pública/autenticada
  (DEC-30): sin sesión `{ esProductor, esComprador, esTransportista }` en
  `false` y `persona:null`; con sesión resuelve persona y los tres contextos;
  una misma persona compartida por Productor y Comprador.

Resto de la suite de backend (no exhaustiva, según módulo):

- Esquema/estado: `direccion_test.php`, `direccion_historico_test.php`,
  `direccion_lock_test.php`, `finca_direccion_test.php`,
  `productor_estado_flujo_test.php`, `productor_estado_periodo_test.php`,
  `productor_ubicacion_test.php`, `persona_telefono_historico_contract_test.php`,
  `transacciones: transaction_test.php`, `servicios_test.php`.
- Concurrencia: `concurrency_test.php`, `concurrencia_ids_test.php`,
  `concurrencia_transporte_test.php`.
- Entidades: `vehiculo_test.php`, `transportista_test.php`,
  `transportista_vehiculo_test.php`, `pagometodo_test.php`.
- HTTP (superficie admin y pública): `api_productores_test.php`,
  `api_publicaciones_test.php`, `api_auth_admin_http_test.php`,
  `api_capacidades_http_test.php`, `api_productores_ubicacion_http_test.php`,
  `api_transportistas_http_test.php`, `api_transportistas_vehiculos_http_test.php`,
  `api_vehiculos_http_test.php`, `fincas_direccion_http_test.php`.
- Diagnóstico/compatibilidad/despliegue: `diagnostico_test.php`,
  `deployment_test.php`, `vercel_prune_registry_test.php`,
  `postgres_compatibility_test.php`, `schema_test.php`, `audit_test.php`,
  `address_policy_test.php`.
- Evals/gates estáticos: `backend_db_ready_eval.php`,
  `persona_capabilities_gate.php`, `persona_capabilities_eval.php`,
  `naming_eval.php`, `concurrency_eval.php`, `deployment_eval.php`,
  `postgres_compatibility_eval.php`.
- Documentación: `documentation_test.py` valida los documentos de
  `Documentation/`.

Nota: no existe `comprador_retiro_gate.php` ni `comprador_backfill_test.php` ni
`comprador_consulta_test.php`. El retiro de Comprador como tabla legacy fue
superado por DEC-28/29: `tbcomprador` es la fuente de verdad del contexto y el
periodo `COMPRADOR` solo un registro analítico.

## Semilla maestra (instalación limpia)

La siembra de datos iniciales es un tool PHP transaccional e idempotente:

```bash
docker compose exec -T app php Tools/seed-maestra.php            # sembrar
docker compose exec -T app php Tools/seed-maestra.php --check    # auditar (0/1)
docker compose exec -T app php Tools/seed-maestra.php --limpiar  # revertir
```

Ver DEC-32 y `Tests/instalacion_limpia_test.php`.

## Frontend

Las pruebas de frontend requieren Node. Si el Node del host funciona:

```bash
node Tests/ui_test.js
node Tests/frontend_contract_test.js
node Tests/frontend_capacidades_eval.js
node Tests/frontend/official_app_shell.eval.mjs
node Tests/frontend_contrast_test.mjs
node --test Tests/frontend/*.test.mjs
```

Pruebas nuevas del sprint de sesiones/capacidades/fincas:

- **`Tests/frontend/sesion.test.mjs`** — superficie del navegador
  (`Public/js/shared/sesion.js`, DEC-33): `identidad.php` distingue
  autenticado de público; sin Bearer no se fabrica sesión; un 401 SIN_SESION no
  se confunde con un fallo de red; `flujoLogin` conserva actor + Bearer solo
  cuando el proveedor devolvió una persona.
- **`Tests/frontend/inscripcion.test.mjs`** — flujo no-CRUD de inscripción
  (`Public/js/shared/inscripcion.js`, Tramo B): los tres contextos se
  presentan por su acción de negocio (comprar, vender, fletear); la acción
  correcta se decide por el estado (abandonar/ reactivar / inscribir); el
  cuerpo de `capacidades.php` es exacto y un 401 se traduce en "inicie sesión".
- **`Tests/frontend/payload_parity.test.mjs`** — paridad del contrato: el alta
  de productor envía `fincas` solo como `[{nombre}]` (la dirección de cada
  finca viaja por `fincas-direccion.php`), recorta vacíos y no repite nombres
  (Tramo C / DEC-34).

Si el Node del host no puede arrancar o no se quiere depender de sus librerías
compartidas, ejecutar el mismo contrato en un contenedor desechable. El `sh -lc`
es importante: expande `Tests/frontend/*.test.mjs` dentro del contenedor; pasar
`Tests/frontend/` directamente a `node --test` no ejecuta la carpeta como suite.

```bash
docker run --rm -v "$PWD":/app -w /app node:22-alpine sh -lc '
  node Tests/ui_test.js &&
  node Tests/frontend_contract_test.js &&
  node Tests/frontend_capacidades_eval.js &&
  node Tests/frontend/official_app_shell.eval.mjs &&
  node Tests/frontend_contrast_test.mjs &&
  node --test Tests/frontend/*.test.mjs
'
```

## Espejo PostgreSQL/Supabase

```bash
php services/supabase-database/tests/schema_test.php
php services/supabase-database/evals/schema_eval.php
```

## Notas de contrato

- `api_publicaciones_test.php` cubre el listado que alimenta Explorar: que
  varias observaciones de un mismo animal no multipliquen la publicación, que
  edad, peso y propósito salgan todos de la observación más reciente (y no
  mezclados entre filas), y que una publicación sin periodo de estado abierto
  deje de aparecer como activa.
- `Tests/frontend/explore_card_content.test.mjs` cubre el lado del navegador del
  mismo listado: formato de precio sin dependencia del ICU del entorno, campo
  sin observación mostrado vacío, y tarjeta armada con `createElement` +
  `textContent` porque título y descripción vienen de la base.
- `frontend_contract_test.js` verifica contratos UI/API. En Compradores la
  propiedad es deliberadamente distinta a los CRUD: existe vista y endpoint de
  consulta, pero no formulario, payload ni método HTTP de escritura manual
  (contexto superado por DEC-28: la escritura ocurre contra `api/compradores.php`
  con sesión).
- `frontend_capacidades_eval.js` conserva la navegación entre las lecturas de
  una misma identidad, pero distingue semántica: Productor y Transportista son
  capacidades operativas; Comprador es una clasificación derivada del Productor.
  El eval también impide volver a usar Productor como alias de Vendedor: VENDEDOR
  es otra clasificación del mismo Productor.