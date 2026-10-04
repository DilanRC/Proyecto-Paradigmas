# Arquitectura — dónde está cada cosa

MVC en PHP 8.3. `Public/` es la única carpeta expuesta por Apache; todo lo
demás queda fuera del navegador (`docker/apache/000-default.conf`).

```text
Proyecto-Paradigmas/
├── Public/                  ← raíz web (DocumentRoot)
│   ├── .htaccess            ← RUTAS: URL bonita → archivo PHP
│   ├── bootstrap.php        ← base path compartido por las páginas
│   ├── *.php                ← entrada de cada página (solo carga su vista)
│   ├── api/*.php            ← entrada de cada endpoint (carga su controlador)
│   ├── js/                  ← lógica del navegador por pantalla
│   │   └── shared/          ← módulos JS reutilizables (api, sesión, mapas…)
│   ├── css/                 ← estilos (tokens.css = variables de diseño)
│   └── assets/              ← imágenes, logos, geojson
│
├── Application/             ← LÓGICA (no accesible desde el navegador)
│   ├── Controller/          ← recibe la petición del endpoint y responde JSON
│   ├── Service/             ← reglas de negocio y validaciones
│   ├── Model/               ← acceso a tablas (PDO, consultas preparadas)
│   ├── Auth/                ← actor autenticado (Supabase) y permisos admin
│   ├── View/<pantalla>/     ← VISTAS: HTML de cada pantalla
│   └── HttpException.php
│
├── Configuration/           ← conexión a BD y helpers JSON
├── Database/                ← esquema, semillas, migraciones, respaldos, SQL de prueba
├── services/                ← servicios independientes (Node / scripts)
│   ├── supabase-server/     ← verificación de JWT (puerto 3001)
│   └── supabase-database/   ← esquema/migración para Postgres
├── contracts/               ← contratos OpenAPI entre servicios
├── Tests/                   ← pruebas PHP (raíz) y frontend (Tests/frontend)
├── Tools/                   ← scripts de respaldo, seeds, despliegue Vercel
├── docker/                  ← configuración Apache del contenedor
├── Documentation/           ← documentación del curso, decisiones, auditorías
└── .github/                 ← plantilla de pull request
```

## Flujo de una petición

**Página:** `/admin/productores` → `Public/.htaccess` → `Public/productores.php`
→ `Application/View/productores/index.php` → el navegador carga
`Public/js/productores.js`.

**API:** `/api/v1/productores` → `Public/.htaccess` → `Public/api/productores.php`
→ `Application/Controller/ProductorController.php` → `Service/*` → `Model/*` → MySQL.

## Cómo agregar algo nuevo

| Quiero agregar… | Archivos |
|---|---|
| Una pantalla | `Application/View/<pantalla>/index.php`, `Public/<pantalla>.php`, regla en `Public/.htaccess`, `Public/js/<pantalla>.js` |
| Un endpoint | `Public/api/<recurso>.php`, `Application/Controller/<Recurso>Controller.php`, regla `api/v1/...` en `Public/.htaccess` |
| Una regla de negocio | `Application/Service/` |
| Una tabla | `Database/SqlScripts/000instalacioncompleta.sql` + migración en `Database/Migrations/` + `Application/Model/` |

## WebSockets (futuro)

PHP con Apache no mantiene conexiones abiertas, así que el tiempo real va como
servicio aparte, igual que `supabase-server`:

```text
services/realtime-server/    ← servidor WebSocket (Node)
contracts/realtime-v1.*      ← mensajes/eventos que intercambia
```

Se agrega como servicio nuevo en `compose.yaml` y el navegador se conecta desde
un módulo en `Public/js/shared/`. El código PHP existente no necesita moverse.
