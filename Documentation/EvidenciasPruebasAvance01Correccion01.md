# Evidencias — Avance 01 Corrección 01

## Identificación

- Rama: `correccion/avance-01-modelo-profesor`
- Commit candidato: `PENDIENTE_COMMIT_CANDIDATO`
- Base: `dbtindercows`
- Entorno: Docker Compose, PHP 8.3, MySQL 8.0, Adminer 4
- Proyecto Compose aislado: `tindercows-profesor`

## Modelo comprobado

```text
tbbitacora
tbproductores
tbproductoresdireccion
tbproductoresfinca
```

- `tbproductoresIdentificacionNumero` es PK.
- Dirección comparte esa columna como PK/FK.
- Finca usa PK compuesta por identificación y nombre.
- No existen participante, roles, catálogo de tipos ni `tbfinca`.

## Pruebas

| Prueba | Resultado |
|---|---|
| `naming_gate.php` | OK |
| `schema_test.php` | OK |
| `api_productores_test.php` | OK |
| `transaction_test.php` | OK |
| `address_policy_test.php` | OK |
| `audit_test.php` | OK |
| `concurrency_test.php` | OK |
| `naming_eval.php` | 100/100 |
| `ui_test.js` | OK |

También se comprobaron respuestas HTTP JSON `400`, `415` y `405`, CRUD por
identificación, correo compartido, varias fincas, rollback de bitácora,
desactivación lógica y reactivación de la misma PK.

## Respaldo

- Carpeta: `Database/Backups/Avance01Correccion01/`
- Commit candidato: `PENDIENTE_COMMIT_CANDIDATO`
- SHA-256: `PENDIENTE`
- Restauración completa: `PENDIENTE`
- Restauración estructura + datos: `PENDIENTE`
- Comparación de tablas, restricciones, índices y filas: `PENDIENTE`
- Etiqueta: `avance-01-correccion-01`

Los marcadores se sustituyen únicamente con salidas reales después del commit
candidato y la restauración.
