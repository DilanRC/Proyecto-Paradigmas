# Pruebas del CRUD de productores

Las pruebas usan dos carriles. El carril gate es determinista, no requiere MySQL y debe terminar en menos de dos segundos. El carril de integración se ejecuta dentro del contenedor `app` contra `dbtindercows`.

## Gate local

```bash
php Tests/naming_gate.php
node Tests/ui_test.js
```

`naming_gate.php` comprueba rutas oficiales, ausencia de rutas obsoletas, tablas físicas, contrato JSON, uso seguro del DOM, consultas preparadas reales y ausencia de credenciales incrustadas en PHP.
`ui_test.js` enlaza todos los IDs usados por JavaScript con la vista y exige AJAX sin recarga, paginación, control de carreras y atributos ARIA.

La evaluación periódica también es determinista y no consume una API de LLM:

```bash
php Tests/naming_eval.php
```

`naming_eval.php` emite JSON con evidencia por criterio y exige una puntuación de 100. Evalúa la correspondencia entre decisiones, README, diccionario, SQL y endpoint.

## Integración con MySQL y API

Inicie el entorno y ejecute cada archivo dentro de `app`:

```bash
docker compose up --build -d
docker compose exec -T app php Tests/schema_test.php
docker compose exec -T app php Tests/api_productores_test.php
docker compose exec -T app php Tests/transaction_test.php
docker compose exec -T app php Tests/role_test.php
docker compose exec -T app php Tests/address_policy_test.php
docker compose exec -T app php Tests/audit_test.php
docker compose exec -T app php Tests/concurrency_test.php
```

Ejecución completa:

```bash
docker compose exec -T app sh -c '
  set -eu
  php Tests/naming_gate.php
  php Tests/schema_test.php
  php Tests/api_productores_test.php
  php Tests/transaction_test.php
  php Tests/role_test.php
  php Tests/address_policy_test.php
  php Tests/audit_test.php
  php Tests/concurrency_test.php
  php Tests/naming_eval.php
'
```

Cobertura de cada prueba:

- `schema_test.php`: tablas, PK/FK/UNIQUE, correo compartido, identidad duplicada, UTF-8 y referencia inválida.
- `api_productores_test.php`: contrato HTTP JSON, CRUD, búsqueda, fincas, identificación alfanumérica, duplicados, desactivación y reactivación.
- `transaction_test.php`: rollback ante identidad duplicada, finca inactiva y fallo provocado de bitácora, sin filas parciales ni evento falso.
- `role_test.php`: PRODUCTOR y COMPRADOR sobre el mismo participante, rol duplicado y exclusión de participantes sin rol PRODUCTOR.
- `address_policy_test.php`: dirección principal obligatoria, dirección adicional y única principal activa.
- `audit_test.php`: CREAR, ACTUALIZAR, DESACTIVAR y REACTIVAR con antes/después, `NO_AUTENTICADO`, usuario nulo, origen y solicitud.
- `concurrency_test.php`: contención real entre dos conexiones para identidad única y bloqueo compartido de catálogos.

## Aislamiento y limpieza

Las fixtures usan nombres, documentos y solicitudes con prefijo `TC_TEST_` y correos bajo `example.test`. Cada prueba conserva los IDs que creó y elimina únicamente esas filas en un bloque `finally`, respetando primero las tablas hijas. Las verificaciones de esquema usan transacciones con `ROLLBACK`. Ninguna prueba elimina bases, tablas, volúmenes, catálogos ni datos semilla.

Si una prueba se interrumpe externamente antes de ejecutar `finally`, localice los registros por el prefijo `TC_TEST_` antes de limpiarlos. No use `TRUNCATE`, `DROP DATABASE` ni `docker compose down -v` como mecanismo de limpieza de pruebas.
