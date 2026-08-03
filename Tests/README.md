# Pruebas

Ejecutar sobre una base limpia inicializada con Docker:

```bash
docker compose exec -T app php Tests/naming_gate.php
docker compose exec -T app php Tests/schema_test.php
docker compose exec -T app php Tests/api_productores_test.php
docker compose exec -T app php Tests/transaction_test.php
docker compose exec -T app php Tests/address_policy_test.php
docker compose exec -T app php Tests/audit_test.php
docker compose exec -T app php Tests/concurrency_test.php
docker compose exec -T app php Tests/concurrency_eval.php
docker compose exec -T app php Tests/naming_eval.php
node Tests/ui_test.js
```

Las pruebas generan identificaciones aleatorias y limpian únicamente sus filas.
