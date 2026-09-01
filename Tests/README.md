# Pruebas

Ejecutar sobre una base limpia inicializada con Docker:

```bash
docker compose exec -T app php Tests/naming_gate.php
docker compose exec -T app php Tests/db_ready_test.php
docker compose exec -T app php Tests/backend_db_ready_test.php
docker compose exec -T app php Tests/comprador_clasificacion_test.php
docker compose exec -T app php Tests/backend_db_ready_eval.php
docker compose exec -T app php Tests/diagnostico_test.php
docker compose exec -T app php Tests/deployment_test.php
docker compose exec -T app php Tests/postgres_compatibility_test.php
docker compose exec -T app php Tests/schema_test.php
docker compose exec -T app php Tests/api_productores_test.php
docker compose exec -T app php Tests/transaction_test.php
docker compose exec -T app php Tests/address_policy_test.php
docker compose exec -T app php Tests/audit_test.php
docker compose exec -T app php Tests/concurrency_test.php
docker compose exec -T app php Tests/concurrency_eval.php
docker compose exec -T app php Tests/naming_eval.php
docker compose exec -T app php Tests/deployment_eval.php
docker compose exec -T app php Tests/postgres_compatibility_eval.php
node Tests/ui_test.js
node Tests/frontend_contract_test.js
node Tests/frontend_capacidades_eval.js
node Tests/frontend_contrast_test.mjs
node --test Tests/frontend/*.test.mjs
php services/supabase-database/tests/schema_test.php
php services/supabase-database/evals/schema_eval.php
```

Las pruebas generan identificaciones aleatorias y limpian únicamente sus filas.

`deployment_test.php` valida en menos de dos segundos que las imágenes contienen
el código, respetan `PORT` y conservan la creación idempotente de `tbfinca`.
`deployment_eval.php` exige que el artefacto y el procedimiento operativo estén
completos.

`frontend_contract_test.js` verifica que los payloads y endpoints usados por los
paneles de productores, compradores, métodos de pago, transportistas, vehículos y
direcciones de finca sigan coincidiendo con los campos aceptados por sus
controladores.

`frontend_capacidades_eval.js` sustituye a `frontend_retirement_eval.js`, que
puntuaba la propiedad contraria mientras Comprador estuvo retirado del frontend.
Ahora exige que las tres capacidades de una persona (productor, comprador y
transportista) tengan vista, ruta y módulo, se alcancen entre sí desde cualquier
menú, y que cada panel lea el parámetro `?q=` con el que la ficha enlaza a la
misma persona en otra capacidad.
