# Esquema Supabase

Servicio de migración PostgreSQL para el proyecto Supabase conectado a Vercel.
Mantiene en `public` el equivalente de las 27 tablas MySQL. `tbpersona`
concentra identidad y contacto; `tbproductor`, `tbcomprador` y
`tbtransportista` son capacidades independientes enlazadas por `tbpersonaid`.
El bloque comercial agrega clasificación histórica del Productor, animales,
publicaciones, compra, venta, funnel, carrito, fletes y reseñas. No declara
claves, restricciones, índices, defaults ni objetos programables.

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
supabase_schema_status=ready tables=27 migration=v6
```

No se debe ejecutar la migración remota hasta verificar un snapshot y recibir
autorización expresa para modificar Supabase.

Pruebas:

```bash
php services/supabase-database/tests/schema_test.php
php services/supabase-database/evals/schema_eval.php
```
