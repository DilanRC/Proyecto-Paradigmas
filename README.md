# TinderCows

CRUD académico de productores para una subasta ganadera. La pantalla habla de
“productores”, pero el dominio registra una persona física o jurídica en
`tbparticipante` y le asigna el rol activo `PRODUCTOR`. La aplicación usa PHP
8.3, MVC, MySQL 8.0, AJAX y JSON. Crear, consultar, actualizar, desactivar y
reactivar no recarga la página completa.

## Requisitos e instalación desde cero

Requisitos: Docker y Docker Compose.

```bash
cp .env.example .env
docker compose up --build -d
docker compose ps
```

En PowerShell, copie el archivo con `Copy-Item .env.example .env`. La base de
datos se llama exactamente `dbtindercows`.

| Servicio | Dirección desde el host | Dato interno |
|---|---|---|
| Aplicación | <http://localhost:8080> | Apache, puerto 80 |
| Adminer | <http://localhost:8081> | Servidor `db` |
| MySQL | `127.0.0.1:3307` | Servicio `db`, puerto 3306 |

En Adminer seleccione sistema `MySQL`, servidor `db`, y use `DB_USER`,
`DB_PASS` y `DB_NAME` definidos en `.env`. No use `localhost` como servidor de
Adminer.

Los scripts de inicialización se ejecutan una vez al crear el volumen. Para una
reinicialización limpia, primero genere y pruebe un respaldo si hay datos que
deban conservarse y luego ejecute:

```bash
docker compose down -v
docker compose up --build -d
docker compose ps
```

`docker compose down -v` elimina el volumen local de MySQL. No lo ejecute sobre
datos no respaldados.

## Creación de la base y datos semilla

Docker monta los archivos en `/docker-entrypoint-initdb.d/` y MySQL los ejecuta
en orden lexicográfico. La estructura y los datos iniciales están separados:

1. `Database/SqlScripts/001_create_database.sql`
2. `Database/SqlScripts/002_create_catalogs.sql`
3. `Database/SqlScripts/003_create_participante_schema.sql`
4. `Database/SqlScripts/004_create_productor_finca.sql`
5. `Database/SqlScripts/005_create_audit.sql`
6. `Database/SeedData/101_identification_types.sql`
7. `Database/SeedData/102_roles.sql`
8. `Database/SeedData/103_example_productores.sql`

Los catálogos incluyen `CEDULA_FISICA`, `CEDULA_JURIDICA`, `DIMEX`, `NITE` y
`PASAPORTE`, y los roles `PRODUCTOR`, `COMPRADOR` y `ADMINISTRADOR`. El último
rol es catálogo futuro, no evidencia de autenticación. Los productores, fincas
y asociaciones del script `103` son datos académicos ficticios.

## Uso del CRUD

1. Abra <http://localhost:8080>.
2. Use búsqueda y estado para filtrar; la consulta también devuelve catálogos.
3. Cree o edite nombre, teléfono, correo, identificación y dirección principal.
4. Asocie cero o más fincas existentes mediante el selector. El CRUD no crea
   fincas: cada elemento enviado contiene únicamente un `fincaId` existente y
   activo.
5. Desactive sin borrar la fila o reactive el mismo participante.

La identificación de un participante inactivo permanece reservada. Un `POST`
con esa identidad responde `409` y devuelve el `participanteId` que debe
reactivarse. El correo es contacto obligatorio, se almacena en minúsculas y no
es único ni una credencial.

## Contrato JSON de la API

Ruta única: `/api/productores.php`.

| Método | Ruta | Operación |
|---|---|---|
| `GET` | `/api/productores.php` | Lista paginada con catálogos |
| `GET` | `/api/productores.php?id=1` | Consulta un productor |
| `POST` | `/api/productores.php` | Crea participante, identidad, dirección, rol y asociaciones |
| `PUT` | `/api/productores.php` | Actualiza el productor completo |
| `DELETE` | `/api/productores.php` | Desactiva lógicamente |
| `PATCH` | `/api/productores.php` | Reactiva por ID o identificación |

Los cuerpos usan `Content-Type: application/json`; un tipo distinto responde
`415`. Todas las respuestas, incluso `400`, `404`, `405`, `409`, `415`, `422` y
`500`, usan `application/json; charset=utf-8`. `OPTIONS` responde `204` y anuncia
los métodos permitidos. Puede enviarse `X-Request-ID` con 1 a 100 caracteres de
`A-Z`, `a-z`, dígitos, punto, guion bajo, dos puntos o guion; si no es válido,
el servidor genera uno para la bitácora.

### Consultas GET

La lista admite `q` (nombre, correo o identificación), `estado` (`TODOS`,
`ACTIVO` o `INACTIVO`), `pagina` positiva y `tamanoPagina` entre 1 y 100. Los
valores predeterminados son `TODOS`, página 1 y 25 filas.

```json
{
  "success": true,
  "message": "Productores consultados correctamente.",
  "data": {
    "productores": [],
    "total": 0,
    "pagina": 1,
    "tamanoPagina": 25,
    "catalogos": {
      "tiposIdentificacion": [],
      "fincasDisponibles": []
    }
  }
}
```

### Crear y actualizar

`POST` usa este objeto. `PUT` usa el mismo objeto y agrega
`"participanteId": 1`. No se aceptan campos desconocidos.

```json
{
  "identificacion": {
    "tipoId": 1,
    "numero": "1-1111-1111"
  },
  "nombre": "Persona de ejemplo",
  "telefono": "+506 8888-8888",
  "correoElectronico": "contacto@ejemplo.test",
  "direccionPrincipal": {
    "provincia": "Heredia",
    "canton": "Heredia",
    "distrito": "Mercedes",
    "pueblo": "Barrio España",
    "senas": "Frente al parque"
  },
  "fincas": [
    { "fincaId": 1 },
    { "fincaId": 2 }
  ]
}
```

`fincas` puede ser `[]`, admite como máximo 100 elementos y rechaza IDs
duplicados o inexistentes. Una finca inactiva no puede agregarse como asociación
nueva; una asociación histórica ya existente sí puede conservarse al actualizar
otro dato. No acepta nombres ni crea fincas de forma implícita.

Respuesta de dominio de una operación individual:

```json
{
  "success": true,
  "message": "Productor creado correctamente.",
  "data": {
    "participanteId": 1,
    "rol": "PRODUCTOR",
    "identificacion": {
      "tipoId": 1,
      "tipoCodigo": "CEDULA_FISICA",
      "numero": "1-1111-1111"
    },
    "nombre": "Persona de ejemplo",
    "telefono": "+50688888888",
    "correoElectronico": "contacto@ejemplo.test",
    "estado": "ACTIVO",
    "direccionPrincipal": {
      "provincia": "Heredia",
      "canton": "Heredia",
      "distrito": "Mercedes",
      "pueblo": "Barrio España",
      "senas": "Frente al parque"
    },
    "fincas": [
      { "fincaId": 1, "nombre": "Finca de ejemplo" }
    ]
  }
}
```

### Desactivar y reactivar

`DELETE` recibe `{"participanteId": 1}`. `PATCH` recibe exactamente una de las
dos variantes siguientes:

```json
{ "participanteId": 1 }
```

```json
{
  "identificacion": {
    "tipoId": 1,
    "numero": "1-1111-1111"
  }
}
```

### Errores

```json
{
  "success": false,
  "message": "La identificación pertenece a un participante inactivo.",
  "data": {
    "reactivacion": {
      "participanteId": 1
    }
  },
  "errors": {
    "identificacion.numero": "Debe reactivarse el participante existente."
  }
}
```

`errors` solo aparece cuando hay errores por campo. El conflicto de un
participante inactivo incluye además
`data.reactivacion.participanteId`. Los errores `500` no exponen SQL,
credenciales ni trazas.

## Normalización y validaciones principales

El número visible de identificación se conserva como texto, incluidos letras,
ceros iniciales, espacios y guiones admitidos. Para comparar, el servidor:

1. valida `CEDULA_FISICA`, `CEDULA_JURIDICA` y `DIMEX` como dígitos, espacios y
   guiones, comenzando con un dígito;
2. valida `NITE`, `PASAPORTE` y tipos alfanuméricos futuros como letras ASCII,
   dígitos, espacios y guiones, comenzando con letra o dígito;
3. elimina únicamente espacios y guiones del valor comparado;
4. convierte letras a mayúsculas;
5. conserva los demás datos del valor visible sin convertir el documento a
   entero.

Por ejemplo, `ab-001 23` se compara como `AB00123`. No se aplica una expresión
que elimine indiscriminadamente todo carácter no numérico. La garantía final es
`UNIQUE (tbidentificaciontipoId,
tbparticipanteidentificacionNumeroNormalizado)`.

El nombre tiene de 3 a 150 caracteres. El teléfono admite un `+` inicial y
entre 8 y 15 dígitos efectivos. Provincia, cantón y distrito son obligatorios;
pueblo y señas son opcionales. Un participante activo debe conservar exactamente
una identificación principal activa, una dirección principal activa y un rol
`PRODUCTOR` activo.

## Arquitectura y persistencia

```text
Navegador (View + Public/js/productores.js)
  -> JSON /api/productores.php
  -> ProductorController
  -> modelos PDO con consultas preparadas
  -> MySQL dbtindercows
```

Las operaciones compuestas se confirman en una sola transacción. La bitácora se
inserta antes del `COMMIT`, con actor `NO_AUTENTICADO`, `tbusuarioId = NULL`,
origen `API_PRODUCTORES` e identificador de solicitud. Un fallo revierte tanto
el dominio como la bitácora. Consulte [diagrama de aplicación](Documentation/DAplicacion.md),
[DER](Documentation/DER.md), [diccionario](Documentation/DiccionarioDatos.md) y
[decisiones](Documentation/Decisiones.md).

La desactivación cambia `tbparticipanteEstado` a `0`; no elimina identificación,
dirección, roles, fincas ni historial. Reactivar reutiliza el mismo
`tbparticipanteId`.

## Pruebas

Los scripts disponibles se documentan en `Tests/README.md`. Ejecute todos los
gate tests deterministas y las evaluaciones periódicas que existan en la rama:

```bash
find Tests -maxdepth 1 -name '*_test.php' -print -exec php {} \;
php Tests/naming_gate.php
php Tests/naming_eval.php
```

Ejecute pruebas de API contra `http://localhost:8080/api/productores.php` con
Docker activo. Registre comandos, fecha, commit, salida real y evidencia en
`Documentation/EvidenciasPruebas.md`; no marque un caso como aprobado sin su
salida. Las categorías obligatorias son esquema, API, roles, dirección,
transacciones y rollback, bitácora, interfaz, seguridad y respaldo/restauración.

## Respaldos por entrega

Cada paquete se guarda sin sobrescribir entregas anteriores:

```text
Database/Backups/AvanceNN/
├── dbtindercows_avanceNN_completo.sql
├── dbtindercows_avanceNN_estructura.sql
├── dbtindercows_avanceNN_datos.sql
├── MANIFEST.md
└── SHA256SUMS.txt
```

Antes de exportar, congele y pruebe un commit candidato. En Linux/Git Bash:

```bash
Tools/backup-database.sh Avance01 "Nombre responsable"
Tools/test-restore.sh Avance01
```

En PowerShell:

```powershell
Tools/Backup-Database.ps1 -Avance Avance01 -Responsable "Nombre responsable"
Tools/Test-Restore.ps1 -Avance Avance01
```

Los scripts toman la contraseña del contenedor, congelan temporalmente las
escrituras, generan los tres SQL, el manifiesto y SHA-256. La restauración usa
`dbtindercows_restore_test` para el dump completo y
`dbtindercows_restore_parts_test` para estructura+datos; compara tablas,
restricciones, índices y conteos, ejecuta una consulta funcional y elimina ambas
bases temporales al terminar.

Verificación manual adicional en Linux/Git Bash:

```bash
cd Database/Backups/Avance01
sha256sum -c SHA256SUMS.txt
cd ../../..
```

El flujo oficial es: commit candidato, respaldo, restauración, evidencia,
commit final, etiqueta anotada `avance-NN`, push de rama y etiqueta. Git conserva
código y documentos; el SQL conserva el estado persistente. Ninguno sustituye
al otro. Consulte [política de respaldos](Documentation/Respaldos.md).

Los commits deben describir una unidad comprobable en español, por ejemplo
`Preparar código candidato del Avance 01` y `Agregar respaldo verificado del
Avance 01`. Después del candidato no se cambia lógica: el commit final agrega
solo respaldo y evidencia. La etiqueta oficial usa siempre `avance-NN`.

## Seguridad, privacidad y versionado

- `.env` no se versiona. Verifique `.gitignore` antes de cada commit.
- No incluya contraseñas, tokens, usuarios internos de MySQL ni datos personales
  reales en scripts, respaldos o manifiestos.
- Use únicamente datos académicos ficticios y revise los SQL antes del commit.
- No sobrescriba una entrega etiquetada. Una corrección usa otra carpeta y otra
  etiqueta.
- Convención de etiqueta oficial: `avance-NN`.

## Limitaciones y puntos pendientes

Este avance no incluye autenticación, autorización, pujas, ganado,
adjudicaciones ni cálculo de transporte. Tampoco afirma propiedad de una finca:
`tbproductorfinca` representa una asociación. Permanecen sin confirmar los
atributos exclusivos de comprador, la naturaleza jurídica/comercial de la
asociación productor-finca, el origen para transporte, los permisos futuros y
los atributos adicionales de finca. No deben cerrarse mediante supuestos.

## Documentación y exportación a PDF

Las fuentes versionables están en `Documentation/*.md`. No se versionan PDF
generados en este cambio. Si la entrega exige PDF y `pandoc` está instalado,
expórtelos sin modificar la fuente:

```bash
pandoc Documentation/AvanceSemanal.md -o Documentation/AvanceSemanal.pdf
pandoc Documentation/DAplicacion.md -o Documentation/DAplicacion.pdf
pandoc Documentation/DER.md -o Documentation/DER.pdf
```

No es necesario reiniciar servicios después de editar solo documentación.
