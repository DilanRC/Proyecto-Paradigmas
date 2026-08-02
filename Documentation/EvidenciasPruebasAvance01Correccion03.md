# Evidencias de pruebas - Avance 01 Corrección 03

## Entorno y candidato

- Rama: `correccion/avance-01-modelo-profesor`
- Commit candidato de código: `68dab641517a27b18f49e4bfe32fdf04ffb2b8b4`
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
utf8mb4                     utf8mb4_unicode_ci
```

Consulta de tablas:

| Tabla | TABLE_COLLATION |
|---|---|
| `tbbitacora` | `utf8mb4_unicode_ci` |
| `tbproductores` | `utf8mb4_unicode_ci` |
| `tbproductoresdireccion` | `utf8mb4_unicode_ci` |
| `tbproductoresfinca` | `utf8mb4_unicode_ci` |

Ninguna tabla usa `utf8mb4_0900_ai_ci`.

## Claves e índices

`information_schema.TABLE_CONSTRAINTS` confirmó:

- una sola PRIMARY KEY en todo el esquema;
- la PRIMARY KEY es
  `tbproductores.tbproductoresIdentificacionNumero`;
- cero FOREIGN KEY;
- cero restricciones referenciales;
- 15 restricciones CHECK;
- 16 restricciones totales.

Dirección, finca y bitácora no tienen PRIMARY KEY ni FOREIGN KEY.
`tbbitacoraId` conserva `AUTO_INCREMENT` mediante un índice ordinario no único.
No existe ningún índice único auxiliar. Los 11 índices distintos comparados
incluyen la PRIMARY KEY de productor y los índices ordinarios de consulta.

Sin FOREIGN KEY, MySQL acepta referencias lógicas huérfanas. `schema_test.php`
insertó y eliminó una dirección y una finca sin productor para demostrar esa
ausencia. La dirección 1:1 y la no duplicación de fincas son políticas de los
flujos de la aplicación, no garantías para SQL directo.

## Resultados de pruebas

Todas estas pruebas fueron ejecutadas contra el candidato:

| Prueba | Resultado |
|---|---|
| `php Tests/naming_gate.php` | APROBADA: cuatro tablas, única PK de productor, cero FK, collation, PDF y ausencia del modelo descartado. |
| `php Tests/schema_test.php` | APROBADA: esquema y tablas unicode, una PK global, cero FK, cero índices únicos auxiliares e IDs artificiales ausentes. |
| `php Tests/api_productores_test.php` | APROBADA: CRUD JSON, PUT inmutable e idempotente, varias fincas, finca duplicada rechazada, desactivación lógica y reactivación. |
| `php Tests/transaction_test.php` | APROBADA: rollback ante PK duplicada y ante fallo forzado de bitácora. |
| `php Tests/address_policy_test.php` | APROBADA: dirección obligatoria y relación 1:1 controlada por la aplicación. |
| `php Tests/audit_test.php` | APROBADA: CREAR, ACTUALIZAR, DESACTIVAR y REACTIVAR; actor no autenticado y usuario nulo. |
| `php Tests/concurrency_test.php` | APROBADA: dos conexiones no insertan la misma identificación. |
| `php Tests/naming_eval.php` | APROBADA: `score=100`, `threshold=100`, 15 criterios. |
| `node Tests/ui_test.js` | APROBADA: fetch/AJAX, DOM sin recarga, PK natural, carreras y ARIA. |
| `python3 Tests/documentation_test.py` | APROBADA: tres PDF reproducibles y alineados con Markdown. |

Las respuestas HTTP 400, 405 y 415 se solicitaron al Apache real y se validaron
como `application/json`. PUT con otra identificación respondió 422. La
desactivación conservó las filas físicas y PATCH reactivó la misma PK. La
semilla se ejecutó otra vez y mantuvo 2 productores, 2 direcciones y 3 fincas,
sin crear duplicados.

## Respaldo, SHA-256 y restauración

Ubicación: `Database/Backups/Avance01Correccion03/`.

```text
509520eadd60c7ab118fbbb5f839a46e231382fdeb82052d6b37bb2dcbbaa098  dbtindercows_avance01_correccion03_completo.sql
3a39150acae38db4c68c9acb4c36be7d4ad52daec553862a1d244ed03d9f2800  dbtindercows_avance01_correccion03_estructura.sql
fef98c3b9a1d4ebdf967a0abbc45dfca3190be57a362e949e4f3f8a1e7c8a5a2  dbtindercows_avance01_correccion03_datos.sql
```

`sha256sum -c SHA256SUMS.txt` aprobó los tres archivos. El respaldo completo y
la secuencia estructura + datos restauraron correctamente. Se compararon
tablas, columnas, PRIMARY KEY, ausencia de FOREIGN KEY, CHECK, ausencia de
reglas referenciales, índices, intercalación, conteos y checksum de datos.

| Tabla | Origen | Completo | Estructura + datos |
|---|---:|---:|---:|
| `tbbitacora` | 0 | 0 | 0 |
| `tbproductores` | 2 | 2 | 2 |
| `tbproductoresdireccion` | 2 | 2 | 2 |
| `tbproductoresfinca` | 3 | 3 | 3 |

Totales comparados: 4 tablas, 16 restricciones, 11 índices, una PRIMARY KEY y
cero FOREIGN KEY. Las bases temporales `dbtindercows_restore_test` y
`dbtindercows_restore_parts_test` fueron eliminadas al finalizar.

## Limitaciones y datos

- No hay autenticación ni autorización; la bitácora usa `NO_AUTENTICADO` y
  `tbusuarioId = NULL`.
- No existe una operación para cambiar la identificación.
- Al no haber PK ni FK en las tablas dependientes, SQL directo puede crear
  duplicados o referencias huérfanas. Los flujos normales de la aplicación los
  evitan y abortan si detectan un estado ambiguo.
- Los scripts PowerShell fueron actualizados, pero no ejecutados porque `pwsh`
  no está instalado en el entorno; el flujo Bash equivalente sí fue ejecutado.
- No se usaron datos reales. La base limpia contiene solo la semilla académica
  con correos `example.test`; la consulta de correos fuera de ese dominio
  devolvió 0.
