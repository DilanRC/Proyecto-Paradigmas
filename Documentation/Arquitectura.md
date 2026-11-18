# Arquitectura — dónde está cada cosa

MVC en PHP 8.3. En los despliegues normales, `Public/` es el único
`DocumentRoot`. En el ZIP para XAMPP, la carpeta raíz puede estar dentro de
`htdocs`: `.htaccess` niega su contenido y solo abre `Public/` y un redirector
raíz. Ese modo requiere que Apache aplique los `.htaccess` de la raíz y de
`Public/` (`AllowOverride All`).

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
Un recurso, un controlador: la dirección principal del productor (`api/v1/productores/direccion`) la atiende
`ProductorDireccionController.php`. La validación de un campo suelto del cuerpo JSON (texto, número, URL de imagen)
vive en `Service/CampoValidacion.php`; ningún controlador depende de otro controlador.

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

## Estado de la cuenta y estados de carga

Tener sesión de Supabase no significa tener cuenta en Ganado Cerca: el servidor decide si existe la Persona.

| Pieza | Dónde |
|---|---|
| 409 sin Persona con `data.codigo = REGISTRO_INCOMPLETO` | `Application/Auth/SupabaseActorResolver.php` (`CODIGO_REGISTRO_INCOMPLETO`) |
| Estados `ANONIMA`, `VERIFICANDO`, `REGISTRO_INCOMPLETO`, `CUENTA_COMPLETA`, `CONFLICTO_CUENTA`, `ERROR_VERIFICACION` y clasificación de errores | `Public/js/shared/estado-cuenta.js` |
| Consulta compartida por página y cuenta; caché de perfil y paso pendiente en páginas privadas | `Public/js/shared/cuenta.js` |
| Encabezado, menú de cuenta, pie y navegación según el estado | `Public/js/public-ui.js` (`initializeAccountState`, `paintAccount`) |
| Esqueletos de carga (misma geometría que el componente real, ocultos a lectores de pantalla) | `Application/View/shared/esqueletos.php`; estilos `.skeleton__row*` en `Public/css/base.css` |
| Spinner con la etapa real dentro del botón que inició la acción | `Public/js/shared/progreso.js` (`iniciarProgreso` → `etapa`, `terminar`) |

Skeleton = contenido que todavía no llegó; spinner = operación que la persona inició; toast = resultado global.
