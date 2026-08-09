# Pruebas

Ejecutar sobre una base limpia inicializada con Docker:

```bash
docker compose exec -T app php Tests/naming_gate.php
docker compose exec -T app php Tests/deployment_test.php
docker compose exec -T app php Tests/schema_test.php
docker compose exec -T app php Tests/api_productores_test.php
docker compose exec -T app php Tests/transaction_test.php
docker compose exec -T app php Tests/address_policy_test.php
docker compose exec -T app php Tests/audit_test.php
docker compose exec -T app php Tests/concurrency_test.php
docker compose exec -T app php Tests/concurrency_eval.php
docker compose exec -T app php Tests/naming_eval.php
docker compose exec -T app php Tests/deployment_eval.php
node Tests/ui_test.js
```

Las pruebas generan identificaciones aleatorias y limpian únicamente sus filas.

`deployment_test.php` valida en menos de dos segundos que las imágenes contienen
el código, respetan `PORT` y conservan la creación idempotente de `tbfinca`.
`deployment_eval.php` exige que el artefacto y el procedimiento operativo estén
completos.
