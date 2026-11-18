# Esquema Supabase

Servicio de migración PostgreSQL para el proyecto Supabase conectado a Vercel.
Mantiene en `public` el equivalente de las 48 tablas MySQL. `tbpersona`
concentra identidad y contacto; `tbproductor`, `tbcomprador` y
`tbtransportista` son capacidades independientes enlazadas por `tbpersonaid`.
El bloque comercial agrega clasificación histórica del Productor, animales,
publicaciones, compra, venta, funnel, carrito, fletes y reseñas. Declara la llave
primaria de cada tabla (`<tabla>id`) y ninguna otra restricción: sin llaves foráneas, índices adicionales,
defaults ni objetos programables. En una base existente `migrate.php` agrega la llave a las tablas que no la
tienen; si ya existe una llave primaria incompatible o hay identificadores repetidos o nulos, la deja igual y lo avisa en la salida de error
(`supabase_primary_key_pendiente`).

`docker/php-errores.ini` registra `migrate-request.php` como `auto_prepend_file`.
Así, la migración corre al atender la primera solicitud PHP de cada contenedor
cuando Vercel entrega `POSTGRES_URL` o `POSTGRES_URL_NON_POOLING`. Un archivo en
`/tmp` y `flock` comparten el resultado entre workers; una espera dura hasta 10
segundos y luego responde 503 JSON. El migrador prefiere la URL no agrupada para
no consumir el pool de sesiones. Un bloqueo asesor de PostgreSQL serializa
réplicas frías; si las 48 tablas, sus llaves primarias, RLS y nulabilidad ya
coinciden, las réplicas siguientes omiten el DDL y solo aseguran las semillas.
Apache sirve CSS, JavaScript e imágenes sin abrir PostgreSQL. La migración usa
un bloqueo asesor transaccional, crea las tablas de forma idempotente, habilita RLS sin políticas públicas y
normaliza `tbproductordireccion` trasladando la ubicación a `tbdireccion`, migra
las identidades heredadas tras rechazar duplicados o datos incompatibles, y
valida nombres de columna, llaves primarias, RLS y la nulabilidad declarada en
`schema.sql`; la llave primaria hace obligatorio el ID. Si una réplica detecta
una diferencia, ejecuta la ruta completa; esta repara la nulabilidad y valida
el esquema antes de confirmar la transacción.
`schema-contract.php` contiene las columnas esperadas y las comprobaciones de
columnas, PK, RLS y nulabilidad; `migrate.php` orquesta la migración y la reparación.
Si un campo que debe ser obligatorio ya contiene `NULL`, revierte la transacción
y responde 503 JSON. Una llave primaria que no se puede crear por una llave
existente incompatible, IDs nulos o repetidos queda en modo degradado: registra `supabase_primary_key_pendiente`,
permite continuar la solicitud y vuelve a intentarlo en una instancia futura.
Si el resto del esquema ya coincide, las instancias siguientes informan el modo
degradado sin repetir DDL. Si falta una PK, tras corregir sus IDs duplicados o
nulos una instancia nueva reintenta crearla; una PK existente incompatible
requiere corregir la propia llave en la base.
Después de aplicar el DDL notifica a PostgREST para recargar el esquema REST.

Al ejecutar `migrate.php` desde CLI, la salida de éxito es:

```text
supabase_schema_status=ready tables=48 migration=v10
```

No se debe ejecutar la migración remota hasta verificar un snapshot y recibir
autorización expresa para modificar Supabase.

Pruebas:

```bash
php services/supabase-database/tests/schema_test.php
php services/supabase-database/evals/schema_eval.php
```
