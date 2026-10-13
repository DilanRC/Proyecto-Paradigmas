# Esquema Supabase

Servicio de migración PostgreSQL para el proyecto Supabase conectado a Vercel.
Mantiene en `public` el equivalente de las 47 tablas MySQL. `tbpersona`
concentra identidad y contacto; `tbproductor`, `tbcomprador` y
`tbtransportista` son capacidades independientes enlazadas por `tbpersonaid`.
El bloque comercial agrega clasificación histórica del Productor, animales,
publicaciones, compra, venta, funnel, carrito, fletes y reseñas. Declara la llave
primaria de cada tabla (`<tabla>id`) y ninguna otra restricción: sin llaves foráneas, índices adicionales,
defaults ni objetos programables. En una base existente `migrate.php` agrega la llave a las tablas que no la
tienen; si una tiene identificadores repetidos o nulos la deja igual y lo avisa en la salida de error
(`supabase_primary_key_pendiente`).

`migrate.php` se ejecuta antes de Apache cuando Vercel entrega `POSTGRES_URL` o
`POSTGRES_URL_NON_POOLING`. La migración usa un bloqueo asesor transaccional,
crea las tablas de forma idempotente, habilita RLS sin políticas públicas y
normaliza `tbproductordireccion` trasladando la ubicación a `tbdireccion`, migra
las identidades heredadas tras rechazar duplicados o datos incompatibles, y
valida todos los nombres de columna. Antes de confirmar la transacción notifica
a PostgREST para recargar el esquema que publica la API REST. Un esquema incompatible detiene el
contenedor sin borrar ni alterar datos existentes.

Evidencia operativa esperada en los logs de Vercel:

```text
supabase_schema_status=ready tables=47 migration=v10
```

No se debe ejecutar la migración remota hasta verificar un snapshot y recibir
autorización expresa para modificar Supabase.

Pruebas:

```bash
php services/supabase-database/tests/schema_test.php
php services/supabase-database/evals/schema_eval.php
```
