# Pruebas

Ejecutar sobre una base limpia inicializada con Docker:

```bash
docker compose exec -T app php Tests/naming_gate.php
docker compose exec -T app php Tests/comprador_retiro_gate.php
docker compose exec -T app php Tests/db_ready_test.php
docker compose exec -T app php Tests/backend_db_ready_test.php
docker compose exec -T app php Tests/comprador_clasificacion_test.php
docker compose exec -T app php Tests/comprador_backfill_test.php
docker compose exec -T app php Tests/comprador_consulta_test.php
docker compose exec -T app php Tests/backend_db_ready_eval.php
docker compose exec -T app php Tests/diagnostico_test.php
docker compose exec -T app php Tests/deployment_test.php
docker compose exec -T app php Tests/vercel_prune_registry_test.php
docker compose exec -T app php Tests/postgres_compatibility_test.php
docker compose exec -T app php Tests/schema_test.php
docker compose exec -T app php Tests/api_productores_test.php
docker compose exec -T app php Tests/api_publicaciones_test.php
docker compose exec -T app php Tests/transaction_test.php
docker compose exec -T app php Tests/address_policy_test.php
docker compose exec -T app php Tests/audit_test.php
docker compose exec -T app php Tests/concurrency_test.php
docker compose exec -T app php Tests/concurrency_eval.php
docker compose exec -T app php Tests/naming_eval.php
docker compose exec -T app php Tests/deployment_eval.php
docker compose exec -T app php Tests/postgres_compatibility_eval.php
```

Las pruebas de frontend requieren Node. Si el Node del host funciona:

```bash
node Tests/ui_test.js
node Tests/frontend_contract_test.js
node Tests/frontend_capacidades_eval.js
node Tests/frontend/official_app_shell.eval.mjs
node Tests/frontend_contrast_test.mjs
node --test Tests/frontend/*.test.mjs
```

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

Espejo PostgreSQL/Supabase:

```bash
php services/supabase-database/tests/schema_test.php
php services/supabase-database/evals/schema_eval.php
```

Las pruebas generan identificaciones aleatorias y limpian únicamente sus filas.

`comprador_retiro_gate.php` es el gate estático de DEC-DBREADY-008: exige que
modelo/controlador legacy sigan retirados, que el endpoint y la vista sean de
solo lectura, que Comprador permanezca marcado como clasificación derivada, que
Productor no vuelva a ser alias de Vendedor y que `tbcomprador` no se elimine
antes del paso (e).

`comprador_consulta_test.php` fija el contrato dinámico del paso (d):
`/api/compradores.php` es de solo lectura, la fuente es el periodo `COMPRADOR`
abierto, una Persona inactiva sigue visible como clasificada pero no disponible,
`COMPRADOR` y `VENDEDOR` pueden coexistir y ninguna lectura depende de
`tbcomprador`.

`deployment_test.php` valida que las imágenes contienen el código, respetan
`PORT` y conservan la creación idempotente de `tbfinca`. `deployment_eval.php`
exige que el artefacto y el procedimiento operativo estén completos.

`api_publicaciones_test.php` cubre el listado que alimenta Explorar. Sus tres
casos centrales no son de forma sino de verdad: que varias observaciones de un
mismo animal no multipliquen la publicación, que edad, peso y propósito salgan
todos de la observación más reciente (y no mezclados entre filas), y que una
publicación sin periodo de estado abierto deje de aparecer como activa. Las tres
se verificaron mutando la consulta: cada mutación rompe la prueba.

`Tests/frontend/explore_card_content.test.mjs` cubre el lado del navegador: el
formato de precio no depende del ICU del entorno, un campo sin observación se
muestra vacío en vez de inventado, y la tarjeta se arma con `createElement` y
`textContent` porque título y descripción vienen de la base.

`frontend_contract_test.js` verifica contratos UI/API. En Compradores la
propiedad es deliberadamente distinta a los CRUD: existe vista y endpoint de
consulta, pero no formulario, payload ni método HTTP de escritura manual.

`frontend_capacidades_eval.js` conserva la navegación entre las lecturas de una
misma identidad, pero distingue semántica: Productor y Transportista son
capacidades operativas; Comprador es una clasificación derivada del Productor.
El eval también impide volver a usar Productor como alias de Vendedor: VENDEDOR
es otra clasificación del mismo Productor.
