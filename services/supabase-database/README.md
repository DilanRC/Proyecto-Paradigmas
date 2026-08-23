# Esquema Supabase

Servicio de migración PostgreSQL para el proyecto Supabase conectado a Vercel.
Mantiene en `public` el equivalente de las catorce tablas MySQL, sin claves,
índices ni valores automáticos. El único dato inicial es el método de pago
`Efectivo`.

`migrate.php` se ejecuta antes de Apache cuando Vercel entrega `POSTGRES_URL` o
`POSTGRES_URL_NON_POOLING`. La migración usa un bloqueo asesor transaccional,
crea las tablas de forma idempotente, habilita RLS sin políticas públicas y
normaliza `tbproductordireccion` trasladando la ubicación a `tbdireccion`, y
valida todos los nombres de columna. Antes de confirmar la transacción notifica
a PostgREST para recargar el esquema que publica la API REST. Un esquema incompatible detiene el
contenedor sin borrar ni alterar datos existentes.

Evidencia operativa esperada en los logs de Vercel:

```text
supabase_schema_status=ready tables=14 migration=v4
```

Pruebas:

```bash
php services/supabase-database/tests/schema_test.php
php services/supabase-database/evals/schema_eval.php
```
