# TinderCows — CRUD de Productores

Aplicación académica PHP 8.3, MVC, MySQL 8, AJAX y JSON. La corrección del
Avance 01 aplica el modelo simplificado indicado por el profesor.

## Modelo vigente

La base `dbtindercows` contiene exactamente:

1. `tbproductores`
2. `tbproductoresdireccion`
3. `tbproductoresfinca`
4. `tbbitacora`

`tbproductoresIdentificacionNumero` es la única PRIMARY KEY del esquema.
Dirección, finca y bitácora no tienen PRIMARY KEY, y no existe ninguna FOREIGN
KEY. La identificación se repite en ellas solo como referencia lógica. No
existen tablas de participante, roles, tipos de identificación ni `tbfinca`.

## Requisitos e inicio

- Docker
- Docker Compose

```bash
cp .env.example .env
docker compose up --build -d
docker compose ps
```

- Aplicación: <http://localhost:8080>
- Adminer: <http://localhost:8081>, servidor `db`
- MySQL desde host: `localhost:3307`
- MySQL entre contenedores: `db:3306`
- Base: `dbtindercows`

Reinicio limpio, únicamente después de verificar un respaldo:

```bash
docker compose down -v
docker compose up --build -d
```

## Scripts

```text
Database/SqlScripts/001_create_database.sql
Database/SqlScripts/002_create_productores.sql
Database/SqlScripts/003_create_productores_direccion.sql
Database/SqlScripts/004_create_productores_finca.sql
Database/SqlScripts/005_create_audit.sql
Database/SeedData/103_example_productores.sql
```

La semilla usa datos ficticios y correos `example.test`.

## API JSON

Endpoint: `/api/productores.php`

| Método | Operación |
|---|---|
| GET | Listar, buscar, filtrar o consultar por `identificacionNumero` |
| POST | Crear productor, dirección, fincas y bitácora |
| PUT | Actualizar por `identificacionNumeroOriginal` |
| DELETE | Desactivar por `identificacionNumero` |
| PATCH | Reactivar por `identificacionNumero` |

```json
{
  "identificacion": {"tipoCodigo": "CEDULA_FISICA", "numero": "1-1111-1111"},
  "nombre": "Persona de ejemplo",
  "telefono": "88888888",
  "correoElectronico": "contacto@example.test",
  "direccionPrincipal": {
    "provincia": "Heredia",
    "canton": "Heredia",
    "distrito": "Mercedes",
    "pueblo": null,
    "senas": null
  },
  "fincas": [{"nombre": "Finca El Roble"}]
}
```

La identificación se almacena sin espacios ni guiones y con letras mayúsculas.
Como es la PK, PUT no puede cambiarla. El correo no es único. DELETE cambia el
estado y PATCH reutiliza la misma fila.

Si una identificación fue digitada incorrectamente, se desactiva el registro
incorrecto, se conserva su bitácora y se crea el registro correcto. La PRIMARY
KEY no se modifica directamente.

POST y PUT mantienen una dirección y evitan fincas duplicadas como políticas de
aplicación. Sin PK/FK adicionales, SQL directo puede insertar duplicados o
huérfanos; MySQL no aplica integridad referencial en esas tres tablas.

La base y las cuatro tablas usan `utf8mb4_unicode_ci`. Compose fija esta
intercalación en MySQL y `001_create_database.sql` altera también una base que
`MYSQL_DATABASE` haya creado antes de ejecutar los scripts.

## Interfaz

La vista usa `fetch()` con JSON y actualiza la tabla sin recargar. Incluye
paginación, búsqueda, filtros, errores por campo, bloqueo de doble envío,
reactivación, ARIA y escritura segura con `textContent`.

## Pruebas

```bash
docker compose exec -T app php Tests/naming_gate.php
docker compose exec -T app php Tests/schema_test.php
docker compose exec -T app php Tests/api_productores_test.php
docker compose exec -T app php Tests/transaction_test.php
docker compose exec -T app php Tests/address_policy_test.php
docker compose exec -T app php Tests/audit_test.php
docker compose exec -T app php Tests/concurrency_test.php
docker compose exec -T app php Tests/naming_eval.php
node Tests/ui_test.js
python3 Tests/documentation_test.py
```

## Respaldos

`Database/Backups/Avance01/`, `Avance01Correccion01/` y
`Avance01Correccion02/` son históricos e inmutables. La entrega vigente usa
`Database/Backups/Avance01Correccion03/` y `avance-01-correccion-03`.

```bash
Tools/backup-database.sh Avance01Correccion03 Dilan
Tools/test-restore.sh Avance01Correccion03
```

El paquete contiene dumps completo, estructura y datos, manifiesto, SHA-256 y
evidencia de restauración. No se versionan `.env`, credenciales ni datos reales.

Los PDF obligatorios se generan desde sus Markdown, sin contenido paralelo:

```bash
python3 Tools/generate-documentation-pdfs.py
python3 Tests/documentation_test.py
```

## Limitaciones

- No hay autenticación ni autorización.
- El tipo es una columna controlada, no un catálogo.
- El nombre de finca se repite si corresponde a varios productores.
- No se determina la relación jurídica con una finca.
- SQL directo puede crear huérfanos o duplicados en tablas sin llave.
