# Evidencias — Avance 01 Corrección 01

## Identificación

- Rama: `correccion/avance-01-modelo-profesor`
- Commit candidato: `903351a81f57aaff4dccbbba6c0d1b0fe25864c0`
- Base: `dbtindercows`
- Entorno: Docker Compose, PHP 8.3, MySQL 8.0, Adminer 4
- Proyecto Compose aislado: `tindercows-profesor`
- Captura local: `/tmp/tindercows-avance01-correccion01-evidencias/productores-correccion01.png`
- SHA-256 de captura: `d00e002aa9cba9ff90c3f73a51a360ff2f0a40526444bd6b99059d052c7db954`

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
- Commit candidato: `903351a81f57aaff4dccbbba6c0d1b0fe25864c0`
- SHA-256: 3 de 3 archivos correctos.
- Restauración completa: correcta.
- Restauración estructura + datos: correcta.
- Comparación: 4 tablas, 21 restricciones y 14 filas de índices, sin diferencias.
- Conteos origen/completo/partes: bitácora 0/0/0, productores 2/2/2,
  direcciones 2/2/2 y fincas 3/3/3.
- Bases temporales eliminadas después de comparar.
- `read_only` y `super_read_only` restaurados a `0`.
- Etiqueta: `avance-01-correccion-01`

Sumas verificadas:

```text
b7d65ea07f19d185ca766326b261f683a731de1b45c7063ed71dc82523651bea  dbtindercows_avance01_correccion01_completo.sql
47fc992cd9ed4607f2b11c873c7daef9a5ec6b315cc97c44b755ebc3d73f2618  dbtindercows_avance01_correccion01_estructura.sql
4a27003eb9ab21874c401f6fc755d6c0856679923820adef69fe3d2aba02829b  dbtindercows_avance01_correccion01_datos.sql
```

## Veredicto

- Gates: 8 de 8 aprobados.
- Eval: 100/100.
- Restauraciones: 2 de 2 aprobadas.
- Diferencias de integridad: 0.
- Datos versionados: únicamente ficticios bajo `example.test`.
- PowerShell: revisión estática; `pwsh` no está instalado en este host.
