# Evidencias de pruebas - Avance 01 Corrección 04

## Entorno y candidato

- Rama: `correccion/avance-01-modelo-profesor`
- Commit candidato de código: `80d6fd753ab770525d0ec8ece7bea3e6dca569c5`
- Base: `dbtindercows`
- PHP: `8.3.32` dentro del contenedor `app`
- MySQL: `8.0.46`
- Docker: `29.6.2`, build `dfc4efb1e2`
- Docker Compose: `5.3.1`
- Fecha de ejecución: `2026-08-02`, zona `America/Costa_Rica`

## Modelo comprobado

`information_schema.TABLES` devolvió exactamente:

1. `tbbitacora`
2. `tbproductor`
3. `tbproductordireccion`
4. `tbproductorfinca`

Las tablas usan nombres singulares. Las columnas propias usan camelCase y el
prefijo de su tabla; `tbproductorId` se repite en dirección y finca como
asociación lógica, sin FK.

## Restricciones e ID

Consultas ejecutadas contra `information_schema`:

| Comprobación | Resultado |
|---|---:|
| Restricciones totales | 0 |
| PRIMARY KEY | 0 |
| FOREIGN KEY | 0 |
| CHECK | 0 |
| Índices únicos | 0 |
| Índices ordinarios distintos | 12 |
| Triggers después de pruebas | 0 |

`tbproductorId` fue comprobado como `INT NOT NULL`, `EXTRA` vacío y sin
`AUTO_INCREMENT`. `COLUMN_KEY=MUL` corresponde únicamente a un índice ordinario
no único. PHP asigna el consecutivo bajo `GET_LOCK` mediante
`MAX(tbproductorId) + 1` y mantiene el bloqueo hasta después del commit o
rollback.

SQL directo insertó dos productores con el mismo ID e identificación y valores
fuera del dominio. Esto comprobó que MySQL no aplica PK, UNIQUE ni CHECK. La
aplicación rechazó una identificación repetida con HTTP 409.

## Intercalación

```text
DEFAULT_CHARACTER_SET_NAME  DEFAULT_COLLATION_NAME
utf8mb4                     utf8mb4_unicode_ci
```

Las cuatro tablas devolvieron `utf8mb4_unicode_ci`. Ninguna usa
`utf8mb4_0900_ai_ci`.

## Sentencias preparadas

`naming_gate.php` y `naming_eval.php` comprobaron que los modelos:

- usan `PDO::prepare()`;
- no usan `PDO::query()` ni `PDO::exec()`;
- enlazan los valores variables mediante `execute()` o `bindValue()`;
- usan preparadas nativas con `PDO::ATTR_EMULATE_PREPARES = false`;
- calculan el consecutivo de productor en PHP bajo `GET_LOCK`.

## Resultados de pruebas

| Prueba | Resultado |
|---|---|
| `php Tests/naming_gate.php` | APROBADA: tablas singulares, cero PK/FK/CHECK, ID PHP, preparadas y PDF. |
| `php Tests/schema_test.php` | APROBADA: cuatro tablas, cero restricciones, cero índices únicos y `tbproductorId` ordinario. |
| `php Tests/api_productores_test.php` | APROBADA: CRUD JSON, ID PHP, identificación inmutable, fincas y estados. |
| `php Tests/transaction_test.php` | APROBADA dos veces: rollback forzado sin CHECK ni trigger privilegiado; columna restaurada a `VARCHAR(100)`. |
| `php Tests/address_policy_test.php` | APROBADA: dirección obligatoria y 1:1 como política de aplicación. |
| `php Tests/audit_test.php` | APROBADA: cuatro acciones, actor no autenticado y usuario nulo. |
| `php Tests/concurrency_test.php` | APROBADA: MySQL permite duplicados y el bloqueo PHP serializa el consecutivo. |
| `php Tests/naming_eval.php` | APROBADA: `score=100`, `threshold=100`, 18 criterios. |
| `node Tests/ui_test.js` | APROBADA: AJAX, DOM sin recarga, identificación inmutable y ARIA. |
| `python3 Tests/documentation_test.py` | APROBADA: tres PDF reproducibles y alineados. |

Las respuestas HTTP 400, 405 y 415 se comprobaron como JSON. PUT con otra
identificación respondió 422. La semilla fue ejecutada nuevamente y conservó
los conteos `2/2/3/0` sin duplicados.

## Pruebas negativas del restaurador

1. `RESTORE_TEST_INJECT_INVALID_METADATA=1` consultó
   `information_schema.TABLES_INVALIDA`. Resultado: exit 1, error 1109,
   manifiesto pendiente y cero bases temporales restantes.
2. `RESTORE_TEST_INJECT_SCHEMA_DIFFERENCE=1` agregó un CHECK únicamente a la
   restauración por partes. Resultado: exit 1 y diferencias detectadas en
   `TABLE_CONSTRAINTS` y `CHECK_CONSTRAINTS`; manifiesto pendiente y cero bases
   temporales restantes.
3. La ejecución normal terminó con exit 0 y solo entonces marcó el manifiesto
   como `APROBADO`.

## Respaldo, SHA-256 y restauración

Ubicación: `Database/Backups/Avance01Correccion04/`.

```text
c37223399dd873faafdb9ff08acd5f78c9c627a2c6a032fcd38e8224203d7ca2  dbtindercows_avance01_correccion04_completo.sql
d0e1a9bcf43823c2ceae7021bfc50ec545e9b9b113fd999872ec2818988d4670  dbtindercows_avance01_correccion04_estructura.sql
272e7927c7c11ed9bcc5224b74592bda9d78758d8f6a7b21bbd6440c88501898  dbtindercows_avance01_correccion04_datos.sql
```

El respaldo completo y la secuencia estructura más datos restauraron sin
diferencias. Se compararon tablas, columnas, conjuntos vacíos de claves y
CHECK, reglas referenciales, índices, intercalación, conteos y checksum.

| Tabla | Origen | Completo | Estructura + datos |
|---|---:|---:|---:|
| `tbbitacora` | 0 | 0 | 0 |
| `tbproductor` | 2 | 2 | 2 |
| `tbproductordireccion` | 2 | 2 | 2 |
| `tbproductorfinca` | 3 | 3 | 3 |

Las dos bases temporales fueron eliminadas al finalizar.

## Limitaciones y datos

- SQL directo puede crear duplicados, asociaciones huérfanas y valores fuera
  del dominio porque el esquema no tiene restricciones.
- El consecutivo de `tbproductorId` se protege únicamente en el flujo PHP.
- No hay autenticación ni autorización.
- Los scripts PowerShell fueron actualizados, pero no ejecutados porque `pwsh`
  no está instalado. El flujo Bash equivalente sí fue ejecutado.
- No se usaron datos reales. Los dos correos de la semilla terminan en
  `example.test`; la consulta de correos fuera de ese dominio devolvió 0.
