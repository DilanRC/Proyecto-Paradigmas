# TinderCows — CRUD de Productores

Aplicación académica PHP 8.3, MVC, MySQL 8, AJAX y JSON. La corrección del
Avance 01 aplica el modelo simplificado indicado por el profesor.

## Modelo vigente

La base `dbtindervacas` contiene exactamente:

1. `tbproductor`
2. `tbproductordireccion`
3. `tbfinca`
4. `tbbitacora`

El esquema no contiene claves, restricciones, índices, valores `DEFAULT`,
columnas `AUTO_INCREMENT`, triggers, rutinas ni eventos. Todos los nombres SQL están en minúscula. PHP calcula los
consecutivos y `tbproductordireccion`/`tbfinca` usan `tbproductorid` como
asociación lógica. No existen tablas de participante, roles ni tipos de
identificación.

Todos los valores, incluida la fecha de bitácora y los estados iniciales, se
envían desde PHP mediante `PDO::prepare()` y parámetros enlazados. MySQL solo
almacena los datos.

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
- MySQL desde host: `localhost:${DB_HOST_PORT:-3307}`
- MySQL entre contenedores: `db:3306`
- Base: `dbtindervacas`

Reinicio limpio, únicamente después de verificar un respaldo:

```bash
docker compose down -v
docker compose up --build -d
```

## Scripts

```text
Database/SqlScripts/001createdatabase.sql
Database/SqlScripts/002createproductores.sql
Database/SqlScripts/003createproductoresdireccion.sql
Database/SqlScripts/004createfinca.sql
Database/SqlScripts/005createaudit.sql
Database/SeedData/103exampleproductores.sql
```

La semilla usa datos ficticios y correos `example.test`.

## API JSON

Endpoint: `/api/productores.php`

| Método | Operación |
|---|---|
| GET | Listar, buscar, filtrar o consultar por `identificacionNumero` |
| POST | Crear productor, dirección vacía, fincas y bitácora |
| PUT | Actualizar y completar la dirección por `identificacionNumeroOriginal` |
| DELETE | Desactivar por `identificacionNumero` |
| PATCH | Reactivar por `identificacionNumero` |

```json
{
  "identificacion": {"tipoCodigo": "CEDULA_FISICA", "numero": "1-1111-1111"},
  "nombre": "Persona de ejemplo",
  "telefono": "88888888",
  "correoElectronico": "contacto@example.test",
  "fincas": [{"nombre": "Finca El Roble"}]
}
```

POST no acepta `direccionPrincipal`: crea automáticamente una fila de dirección
vacía. La dirección se completa en un PUT posterior, que incluye
`identificacionNumeroOriginal` y `direccionPrincipal`:

```json
{
  "identificacionNumeroOriginal": "111111111",
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
PUT no puede cambiarla por contrato de aplicación, no por una clave MySQL. El
correo no es único. DELETE cambia el estado y PATCH reutiliza la misma fila.

Si una identificación fue digitada incorrectamente, se desactiva el registro
incorrecto, se conserva su bitácora y se crea el registro correcto. La
identificación existente no se modifica directamente.

POST instancia una dirección vacía y PUT completa o edita esa misma fila. Ambos
flujos evitan fincas duplicadas como políticas de aplicación. Sin PK, FK,
UNIQUE ni CHECK, SQL directo puede insertar duplicados, huérfanos o valores
fuera del dominio.

Los modelos usan `PDO::prepare()` con parámetros enlazados y preparadas nativas.
Ningún valor recibido por HTTP se concatena al SQL. Para crear un productor,
PHP adquiere en orden los bloqueos nombrados de productor, dirección y finca,
consulta los respectivos `MAX(id) + 1`, ejecuta la transacción y libera los
bloqueos en orden inverso después del commit o rollback. Las actualizaciones
que pueden crear fincas y la reparación de una dirección mantienen del mismo
modo su bloqueo hasta que termina la transacción.

La base y las cuatro tablas usan `utf8mb4_unicode_ci`. Compose fija esta
intercalación en MySQL y `001createdatabase.sql` altera también una base que
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

Los respaldos hasta `Avance01Correccion03/` son históricos e inmutables. La
entrega vigente usa `Database/Backups/Avance01Correccion04/` y
`avance-01-correccion-04`.

```bash
Tools/backup-database.sh Avance01Correccion04 Dilan
Tools/test-restore.sh Avance01Correccion04
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
- SQL directo puede crear huérfanos, duplicados y valores fuera del dominio.
- `tbproductorid` no tiene garantía de unicidad en MySQL; el consecutivo solo se
  serializa dentro del flujo PHP.
