# Pruebas

Ejecutar sobre una base limpia inicializada con Docker:

```bash
docker compose exec -T app php Tests/naming_gate.php
docker compose exec -T app php Tests/deployment_test.php
docker compose exec -T app php Tests/vercel_prune_registry_test.php
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
php services/supabase-database/tests/schema_test.php
php services/supabase-database/evals/schema_eval.php
```

Las pruebas generan identificaciones aleatorias y limpian únicamente sus filas.

`deployment_test.php` valida en menos de dos segundos que las imágenes contienen
el código, respetan `PORT` y conservan la creación idempotente de `tbfinca`.
`deployment_eval.php` exige que el artefacto y el procedimiento operativo estén
completos.

`frontend_contract_test.js` verifica que los payloads y endpoints usados por los
paneles de productores, compradores, métodos de pago, transportistas, vehículos
y direcciones de finca sigan coincidiendo con los campos aceptados por sus
controladores. Evita que una validación backend rompa silenciosamente un
formulario que continúa enviando un contrato distinto.
