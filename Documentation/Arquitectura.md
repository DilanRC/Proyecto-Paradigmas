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

## Tiempo real (subastas y avisos)

PHP en Vercel no mantiene conexiones abiertas, así que no hay un servidor WebSocket propio: se usa
**Supabase Realtime (Broadcast)** (`DEC-SUBASTA-001`).

| Pieza | Dónde |
|---|---|
| Aviso desde el servidor (después del COMMIT, con `SUPABASE_SECRET_KEY`) | `Application/Service/TiempoReal.php` |
| Cliente en el navegador (WebSocket nativo, reconexión, latido) | `Public/js/shared/tiempo-real.js` |
| Cierre de subastas vencidas (sin tarea programada) | `Application/Service/SubastaCierreService.php` |

Tópicos: `subastas` (la lista), `subasta-<id>` (una subasta) y `notificaciones-<personaId>` (la campana). El aviso
no lleva datos: quien escucha vuelve a pedir el estado a la API. Para usarlo en otra pantalla:
`escuchar('<tópico>', agrupar(recargar))` en el navegador y `$tiempoReal->avisar(['<tópico>'])` en PHP.
