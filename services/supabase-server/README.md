# Supabase Server sidecar

Servicio Node independiente para validar JWT de Supabase sin modificar el CRUD
PHP. Usa `@supabase/server/core` con `auth: 'user'` y crea un cliente limitado
por RLS. No expone ni usa el cliente administrativo.

## Contrato

- `GET /health`: preparación del servicio, sin mostrar valores de configuración.
- `GET /v1/auth/verify`: exige `Authorization: Bearer <jwt>` y devuelve únicamente
  `id`, `email` y `role` verificados.
- OpenAPI: `contracts/supabase-auth-v1.openapi.json`.

El CRUD PHP consume este endpoint cuando recibe `Authorization`. La identidad
de negocio no sale del `role` del proveedor: se resuelve por `email` contra
`tbpersona.tbpersonacorreoelectronico`. Token inválido conserva 401/403,
servicio no disponible responde 503, y token válido sin Persona vinculada
responde 409.

## Variables

`SUPABASE_URL`, `SUPABASE_PUBLISHABLE_KEY` y `SUPABASE_JWKS_URL` son obligatorias.
`SUPABASE_SECRET_KEY` queda configurada en Docker, pero es opcional mientras el
servicio no ejecute operaciones administrativas. Su valor real debe vivir solo
en `.env` o en el gestor de secretos del despliegue.

## Ejecución

```bash
npm ci --ignore-scripts
npm test
npm run eval
npm start
```

Con Compose queda disponible únicamente en el host local:
`http://127.0.0.1:3001`.
