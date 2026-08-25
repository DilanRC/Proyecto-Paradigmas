# TinderCows — CRUD de Productores

Aplicación académica PHP 8.3, MVC, MySQL 8, AJAX y JSON. La corrección del
Avance 01 aplica el modelo simplificado indicado por el profesor.

## Modelo vigente

La base `dbmercadoganadero` contiene exactamente:

1. `tbproductor`
2. `tbproductordireccion`
3. `tbfinca`
4. `tbbitacora`
5. `tbcomprador`
6. `tbdireccion`
7. `tbfincadireccion`
8. `tbpagometodo`
9. `tbtransportista`
10. `tbvehiculo`
11. `tbtransportistavehiculo`

Las seis últimas pertenecen al avance de direcciones, pagos y transporte. La
ubicación física vive **únicamente** en `tbdireccion`: `tbproductordireccion` y
`tbfincadireccion` solo guardan el enlace `tbdireccionid`, de modo que productor
y finca pueden compartir el mismo lugar sin duplicar el dato. Ver
`Documentation/DER.md`, `Documentation/DiccionarioDatos.md` y
`Database/Tests/README.md`.

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
- phpMyAdmin: <http://localhost:8081>, servidor preconfigurado `db`; ingrese con `DB_USER` y `DB_PASS`
- Verificación JWT Supabase: <http://127.0.0.1:3001>
- MySQL desde host: `localhost:${DB_HOST_PORT:-3309}`
- MySQL entre contenedores: `db:3306`
- Base MySQL: `dbmercadoganadero`

Reinicio limpio, únicamente después de verificar un respaldo:

```bash
docker compose down -v
docker compose up --build -d
```

## Scripts

```text
Database/SqlScripts/000instalacioncompleta.sql
Database/SeedData/101initialpagometodo.sql
Database/SeedData/103exampleproductores.sql
Database/Migrations/001normalizadireccionproductor.sql
Database/Tests/comprobacionestructura.sql
Database/Tests/comprobaciondatosiniciales.sql
Database/Tests/comprobacionrelaciones.sql
Database/Tests/diagnostico.sql
```

`000instalacioncompleta.sql` unifica, en orden, los módulos que antes eran los scripts
`001createdatabase.sql` a `012createtransportistavehiculo.sql`. Cada `CREATE
TABLE` usa `IF NOT EXISTS`, así que volver a ejecutarlo contra una base que ya
tiene algunas de esas tablas no falla: solo crea las que falten.

La semilla usa datos ficticios y correos `example.test`. `101initialpagometodo`
registra el único método de pago del alcance vigente. `Database/Migrations/`
solo se aplica a bases creadas antes del avance y `Database/Tests/` contiene
comprobaciones SQL, descritas en `Database/Tests/README.md`.

## Servicio Supabase

`services/supabase-server/` es un servicio Node independiente. Valida JWT con
`@supabase/server` usando `auth: 'user'`; no cambia ni reemplaza el CRUD PHP.

```bash
curl http://127.0.0.1:3001/health
curl -H "Authorization: Bearer <jwt>" http://127.0.0.1:3001/v1/auth/verify
```

El contrato está en `contracts/supabase-auth-v1.openapi.json`. La clave secreta
no se versiona y solo debe agregarse a `.env` cuando exista una operación
administrativa que la necesite.

## Despliegue

`Dockerfile` genera una imagen autocontenida; el volumen de Compose solo sirve
para desarrollo. `Dockerfile.vercel` permite que Vercel ejecute la misma
aplicación PHP y adapta Apache al puerto indicado por `PORT`. `vercel.json`
declara el contenedor como servicio `app` y dirige todas las rutas hacia él.
`Tools/vercel-ignore-build.sh` conserva `main` para producción y permite previews
automáticos únicamente desde `dev`; el resto de ramas se omite antes del build.

```bash
docker build -t tindercows:local .
docker run --rm -d --name tindercows-smoke -p 18080:80 tindercows:local
curl -fsS http://127.0.0.1:18080/ >/dev/null
docker stop tindercows-smoke
```

En Vercel, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS` deben apuntar
a una base MySQL externa: los contenedores de Vercel no almacenan estado. Tras
desplegar `main`, se debe asociar `tindervacas.dpdns.org` al proyecto y comprobar:

```bash
curl -fsS https://tindervacas.dpdns.org/ >/dev/null
```

Cuando la integración Supabase entrega `POSTGRES_URL`, el contenedor aplica
antes de iniciar Apache el esquema PostgreSQL de `services/supabase-database/`.
La migración `v3` crea las once tablas, normaliza `tbproductordireccion`
trasladando la ubicación a `tbdireccion`, registra `Efectivo` en
`tbpagometodo`, habilita RLS sin políticas públicas y valida las columnas. El
log `supabase_schema_status=ready tables=11 migration=v3` confirma el
resultado.

### Aplicar el esquema a una base existente

Los archivos de `/docker-entrypoint-initdb.d` solo se ejecutan al crear un
volumen MySQL vacío. Para un volumen existente, primero respalde la base y
luego reaplique `000instalacioncompleta.sql`: como cada `CREATE TABLE` usa
`IF NOT EXISTS`, las tablas ya presentes se ignoran y solo se crean las que
falten (por ejemplo `tbfinca`, `tbcomprador` o las del avance de direcciones,
pagos y transporte).

```bash
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
  < Database/SqlScripts/000instalacioncompleta.sql
docker compose exec -T app php Tests/schema_test.php
```

`CREATE TABLE IF NOT EXISTS` no altera una tabla incompatible que ya exista.
Antes de usar `ALTER` o `DROP`, compare su estructura con
`Tests/schema_test.php` y genere un respaldo.

En Vercel, el arranque ejecuta la migración v3 contra Supabase, recarga la caché
de esquema de PostgREST y falla antes de iniciar Apache si alguna tabla existe
con columnas incompatibles.

### Aplicar el avance de direcciones, pagos y transporte a una base existente

Un volumen MySQL creado antes de este avance conserva la ubicación dentro de
`tbproductordireccion`. Respalde primero y luego aplique `000instalacioncompleta.sql` (crea
únicamente las tablas nuevas, gracias a `IF NOT EXISTS`) y la migración que
normaliza la dirección:

```bash
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
  < Database/SqlScripts/000instalacioncompleta.sql
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
  < Database/Migrations/001normalizadireccionproductor.sql
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
  < Database/SeedData/101initialpagometodo.sql
docker compose exec -T app php Tests/schema_test.php
```

La migración se ejecuta una sola vez: copia cada residencia a `tbdireccion`,
comprueba que ningún productor quedó sin enlace y después elimina las cinco
columnas heredadas. Repetirla termina con el error 1091 de MySQL. Una base
limpia ya nace normalizada desde `000instalacioncompleta.sql` y no debe ejecutarla.

Tras la migración, el contrato de base cambió: la aplicación debe escribir
provincia, cantón, distrito, pueblo y señas en `tbdireccion`, no en
`tbproductordireccion`. Hasta que `Application/Model/ProductorDireccion.php` se
adapte, el CRUD de productores falla con `Unknown column
'tbproductordireccionprovincia'`.

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

### Ubicaciones GPS del productor

Endpoint: `/api/productores-ubicacion.php`

| Método | Operación |
|---|---|
| GET | Histórico por `productorId`, paginado (`pagina`, `tamano`) o por rango (`desde`, `hasta`) |
| POST | Registrar una nueva lectura GPS (append-only) |

```json
{"productorId": 12, "latitud": 10.1234567, "longitud": -84.1234567,
 "precisionMetros": 25.4, "origen": "NAVEGADOR"}
```

La tabla es append-only (DEC-16): PUT, PATCH y DELETE responden 405. La fecha
la asigna siempre PHP; el campo `fecha` del cliente se descarta. El origen solo
acepta `NAVEGADOR` o `MANUAL`. Latitud, longitud y precisión se validan por
rango con errores por campo. Cada inserción queda en la bitácora dentro de la
misma transacción.

La base y las cinco tablas usan `utf8mb4_unicode_ci`. Compose fija esta
intercalación en MySQL y `000instalacioncompleta.sql` altera también una base que
`MYSQL_DATABASE` haya creado antes de ejecutar los scripts.

## Interfaz

La vista usa `fetch()` con JSON y actualiza la tabla sin recargar. Incluye
paginación, búsqueda, filtros, errores por campo, bloqueo de doble envío,
reactivación, ARIA y escritura segura con `textContent`.

## Pruebas

```bash
docker compose exec -T app php Tests/naming_gate.php
docker compose exec -T app php Tests/deployment_test.php
docker compose exec -T app php Tests/postgres_compatibility_test.php
docker compose exec -T app php Tests/schema_test.php
docker compose exec -T app php Tests/api_productores_test.php
docker compose exec -T app php Tests/transaction_test.php
docker compose exec -T app php Tests/address_policy_test.php
docker compose exec -T app php Tests/audit_test.php
docker compose exec -T app php Tests/concurrency_test.php
docker compose exec -T app php Tests/productor_ubicacion_test.php
docker compose exec -T app php Tests/api_productores_ubicacion_http_test.php
docker compose exec -T app php Tests/naming_eval.php
docker compose exec -T app php Tests/deployment_eval.php
docker compose exec -T app php Tests/postgres_compatibility_eval.php
node Tests/ui_test.js
python3 Tests/documentation_test.py
cd services/supabase-server && npm test && npm run eval
php services/supabase-database/tests/schema_test.php
php services/supabase-database/evals/schema_eval.php
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
