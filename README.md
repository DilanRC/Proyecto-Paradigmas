# TinderCows — CRUD de Productores

Aplicación académica PHP 8.3, MVC, MySQL 8, AJAX y JSON. La corrección del
Avance 01 aplica el modelo simplificado indicado por el profesor.

## Modelo vigente

La base `bdmercadoganadero` contiene exactamente 15 tablas:

1. `tbpersona`
2. `tbproductor`
3. `tbcomprador`
4. `tbtransportista`
5. `tbproductordireccion`
6. `tbdireccion`
7. `tbfinca`
8. `tbfincadireccion`
9. `tbpagometodo`
10. `tbvehiculo`
11. `tbtransportistavehiculo`
12. `tbbitacora`
13. `tbproductorestadoperiodo`
14. `tbproductorubicacion`
15. `tbproductoractividad`

`tbpersona` guarda una sola identidad y contacto. `tbproductor` es la entidad
de negocio núcleo y `tbtransportista` es una capacidad operativa actual.
`tbcomprador` se conserva como estructura legacy de compatibilidad; P0-C
define que Comprador y Vendedor son clasificaciones históricas derivadas del
Productor. La ubicación física vive **únicamente** en
`tbdireccion`: `tbproductordireccion` y
`tbfincadireccion` solo guardan el enlace `tbdireccionid`, de modo que productor
y finca pueden compartir el mismo lugar sin duplicar el dato. Ver
`Documentation/MatrizArquitectonicaP0C.md`, `Documentation/DER.md`,
`Documentation/DiccionarioDatos.md` y `Database/Tests/README.md`.

El esquema no contiene claves, restricciones, índices, valores `DEFAULT`,
columnas `AUTO_INCREMENT`, triggers, rutinas ni eventos. Todos los nombres SQL están en minúscula. PHP calcula los
consecutivos y `tbproductordireccion`/`tbfinca` usan `tbproductorid` como
asociación lógica. No existen tablas de participante, roles ni catálogos de
roles.

El listado de tablas se deriva del SQL canónico con
`Tools/schema-manifest.php`; gates, tests y restore no deben mantener otro
manifest editado a mano.

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
- Base MySQL: `bdmercadoganadero`

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

Las APIs PHP usan ese contrato cuando reciben `Authorization: Bearer <jwt>`.
El `email` verificado debe coincidir de forma única con
`tbpersona.tbpersonacorreoelectronico`; si no existe vínculo, la escritura falla
con 409 y no inventa usuario. Sin encabezado `Authorization` se conserva el modo
local `NO_AUTENTICADO`.

## Despliegue

`Dockerfile` genera una imagen autocontenida; el volumen de Compose solo sirve
para desarrollo. `Dockerfile.vercel` permite que Vercel ejecute la misma
aplicación PHP y adapta Apache al puerto indicado por `PORT`. `vercel.json`
declara el contenedor como servicio `app` y dirige todas las rutas hacia él.
`git.deploymentEnabled` bloquea la creación de deployments para cualquier rama
salvo `main` y `dev`. `Tools/vercel-ignore-build.sh` mantiene una segunda guarda:
conserva `main` para producción y permite previews automáticos únicamente desde
`dev`.

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
El migrador crea y valida las 15 tablas, incluida la identidad compartida en
`tbpersona`, habilita RLS sin políticas públicas y valida las columnas. La
migración remota de persona no se ejecuta ni se activa mediante push hasta
confirmar un snapshot y autorizar expresamente el cambio sobre Supabase.

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

La identificación, tipo, nombre, teléfono y correo se leen mediante JOIN con
`tbpersona`. Crear una capacidad reutiliza la persona por identificación o la
crea si no existe. La API responde 409 si esa capacidad ya existe o si los
datos personales no coinciden. Actualizar contacto desde cualquier capacidad
actualiza `tbpersona` y se refleja en los otros perfiles. `DELETE` desactiva
solo el perfil, `PATCH` reactiva solo el perfil y ninguno puede operar si
`tbpersonaestado` está inactivo. Ningún endpoint ejecuta `DELETE FROM`.

Si una identificación fue digitada incorrectamente, se desactiva el registro
incorrecto, se conserva su bitácora y se crea el registro correcto. La
identificación existente no se modifica directamente.

POST instancia una dirección vacía y PUT completa o edita esa misma fila. Ambos
flujos evitan fincas duplicadas como políticas de aplicación. Sin PK, FK,
UNIQUE ni CHECK, SQL directo puede insertar duplicados, huérfanos o valores
fuera del dominio.

La migración a persona compartida respalda primero, detecta identificaciones
duplicadas y datos incompatibles, y aborta antes de retirar columnas ante
cualquier conflicto. Después de enlazar y verificar conteos, IDs y ausencia de
huérfanos, elimina las columnas personales de los perfiles. Los IDs de perfil
y sus relaciones no cambian. Consulte `Documentation/Respaldos.md` antes de
aplicarla. Para Supabase se exige snapshot confirmado y autorización explícita.

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

La base y las 15 tablas usan `utf8mb4_unicode_ci`. Compose fija esta
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
node Tests/frontend_contract_test.js
node Tests/frontend_capacidades_eval.js
node Tests/frontend_contrast_test.mjs
node --test Tests/frontend/*.test.mjs
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
