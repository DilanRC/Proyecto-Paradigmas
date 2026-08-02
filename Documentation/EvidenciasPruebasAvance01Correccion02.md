# Evidencias de pruebas - Avance 01 Corrección 02

## Entorno y candidato

- Rama: `correccion/avance-01-modelo-profesor`
- Commit candidato de código: `12ca8b871c9136ba0cf752a030adac60852f3b53`
- Base: `dbtindercows`
- PHP: `8.3.32` dentro del contenedor `app`
- MySQL: `8.0.46`
- Docker: `29.6.2`, build `dfc4efb1e2`
- Docker Compose: `5.3.1`
- Fecha de ejecución: `2026-08-02`, zona `America/Costa_Rica`

## Modelo comprobado

`information_schema.TABLES` devolvió exactamente:

1. `tbbitacora`
2. `tbproductores`
3. `tbproductoresdireccion`
4. `tbproductoresfinca`

No existen las tablas descartadas del modelo de nueve tablas. La prueba de
columnas confirmó que productor, dirección y finca no tienen IDs artificiales.

## Intercalación

Consulta de esquema:

```text
DEFAULT_CHARACTER_SET_NAME  DEFAULT_COLLATION_NAME
utf8mb4                    utf8mb4_unicode_ci
```

Consulta de tablas:

| Tabla | TABLE_COLLATION |
|---|---|
| `tbbitacora` | `utf8mb4_unicode_ci` |
| `tbproductores` | `utf8mb4_unicode_ci` |
| `tbproductoresdireccion` | `utf8mb4_unicode_ci` |
| `tbproductoresfinca` | `utf8mb4_unicode_ci` |

Ninguna tabla usa `utf8mb4_0900_ai_ci`.

## PK y FK

- `tbproductores`: PK `tbproductoresIdentificacionNumero`.
- `tbproductoresdireccion`: la misma identificación es PK y FK hacia
  `tbproductores.tbproductoresIdentificacionNumero`.
- `tbproductoresfinca`: PK compuesta por identificación y nombre; la
  identificación es FK hacia productores.
- Una dirección y una finca huérfanas fueron rechazadas por MySQL con error 1452.

Reglas consultadas en `information_schema.REFERENTIAL_CONSTRAINTS`:

| Tabla | Restricción | UPDATE_RULE | DELETE_RULE |
|---|---|---|---|
| `tbproductoresdireccion` | `fk_tbproductoresdireccion_productor` | `RESTRICT` | `RESTRICT` |
| `tbproductoresfinca` | `fk_tbproductoresfinca_productor` | `RESTRICT` | `RESTRICT` |

## Resultados de pruebas

Todas estas pruebas fueron ejecutadas contra el candidato:

| Prueba | Resultado |
|---|---|
| `php Tests/naming_gate.php` | APROBADA: cuatro tablas, PK natural, collation, RESTRICT, PDF y ausencia del modelo descartado. |
| `php Tests/schema_test.php` | APROBADA: esquema y tablas unicode, PK, FK, PK compuesta, huérfanos e IDs artificiales. |
| `php Tests/api_productores_test.php` | APROBADA: CRUD JSON, PUT inmutable, varias fincas, finca duplicada, desactivación física y reactivación. |
| `php Tests/transaction_test.php` | APROBADA: rollback ante duplicado y ante fallo forzado de bitácora. |
| `php Tests/address_policy_test.php` | APROBADA: dirección obligatoria y 1:1 por PK/FK. |
| `php Tests/audit_test.php` | APROBADA: CREAR, ACTUALIZAR, DESACTIVAR y REACTIVAR; actor no autenticado y usuario nulo. |
| `php Tests/concurrency_test.php` | APROBADA: dos conexiones no insertan la misma PK. |
| `php Tests/naming_eval.php` | APROBADA: `score=100`, `threshold=100`, 13 criterios. |
| `node Tests/ui_test.js` | APROBADA: fetch/AJAX, DOM sin recarga, PK natural, carreras y ARIA. |
| `python3 Tests/documentation_test.py` | APROBADA: tres PDF reproducibles y alineados con Markdown. |

Las respuestas HTTP 400, 405 y 415 se solicitaron al Apache real y se validaron
como `application/json`. PUT con otra identificación respondió 422. La
desactivación conservó las filas físicas y PATCH reactivó la misma PK.

## Respaldo, SHA-256 y restauración

Ubicación: `Database/Backups/Avance01Correccion02/`.

```text
cac1ab43dd653ffb2019d33383c86cd2ddc2799b3dceb93173ae01641af18af4  dbtindercows_avance01_correccion02_completo.sql
20a0f1b927507fd1e1a1617bdd7af64e447e7185cc3d5881ae22013323043c80  dbtindercows_avance01_correccion02_estructura.sql
14ffd87853efb4c6e763c5a7c4fa9811a136b7aedb4d9541a9477189546c82e8  dbtindercows_avance01_correccion02_datos.sql
```

`sha256sum -c SHA256SUMS.txt` aprobó los tres archivos. El respaldo completo y
la secuencia estructura + datos restauraron correctamente. Se compararon
tablas, columnas, PK, FK, CHECK, reglas referenciales, índices, intercalación,
conteos y checksum de datos.

| Tabla | Origen | Completo | Estructura + datos |
|---|---:|---:|---:|
| `tbbitacora` | 0 | 0 | 0 |
| `tbproductores` | 2 | 2 | 2 |
| `tbproductoresdireccion` | 2 | 2 | 2 |
| `tbproductoresfinca` | 3 | 3 | 3 |

Totales comparados: 4 tablas, 21 restricciones y 11 índices. Una prueba
negativa agregó temporalmente un índice al origen; el verificador detectó una
diferencia en `STATISTICS` y terminó con error. Tras retirar el índice, la
restauración válida volvió a aprobar. Las dos bases temporales fueron eliminadas.

## Limitaciones y datos

- No hay autenticación ni autorización; la bitácora usa `NO_AUTENTICADO` y
  `tbusuarioId = NULL`.
- No existe una operación para cambiar la identificación.
- Los scripts PowerShell fueron actualizados, pero no ejecutados porque `pwsh`
  no está instalado en el entorno; el flujo Bash equivalente sí fue ejecutado.
- No se usaron datos reales. La base limpia contiene solo la semilla académica
  con correos `example.test`; la consulta de correos fuera de ese dominio devolvió 0.
