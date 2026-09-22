# Rutas de TinderCows

Estado documentado: 2026-09-22, rama `dev`.

## Ejecución local

La aplicación expone Apache en `http://localhost:8080` mediante `compose.yaml` (`8080:80`).

```bash
docker compose up --build
```

La base MySQL usa el puerto interno `3306` y, por defecto, se publica en el host por `3309`. phpMyAdmin se publica en `http://127.0.0.1:8081`.

## Sitio público

| Ruta | Propósito |
|---|---|
| `/` | Inicio público y presentación del producto. |
| `/explorar.php` | Explorar publicaciones de muestra mediante tarjetas deslizables, búsqueda y filtros rápidos. |
| `/sobre-nosotros.php` | Descripción de TinderCows como producto. |
| `/como-usar.php` | Guía de exploración, búsqueda, cuenta y acciones. |
| `/privacidad.php` | Estado actual de privacidad y datos. |
| `/terminos.php` | Términos de uso. |
| `/legal.php` | Información legal y pendientes antes de operación real. |
| `/login.php` | Acceso local de demostración; por defecto vuelve a `/explorar.php`. |

La navegación primaria pública expone Inicio, Explorar, Nosotros y Cómo funciona. Privacidad, Términos e Información legal viven en el footer, no en el navbar.

## Administración interna

Estas rutas pasan por `Public/js/shared/auth-gate.js`. El gate controla la navegación visual, pero no sustituye la autorización de servidor. Las escrituras de las APIs administrativas exigen un JWT Supabase válido y una cuenta activa en `tbadministrador`, verificada por PHP con una consulta preparada.

| Ruta | Módulo |
|---|---|
| `/productores.php` | Productores. |
| `/compradores.php` | Compradores derivados. |
| `/transportistas.php` | Transportistas. |
| `/vehiculos.php` | Vehículos. |
| `/pagometodos.php` | Métodos de pago. |

El login acepta `?next=<ruta-permitida>` para volver a un destino local permitido. La lista contempla `/explorar.php` y las cinco rutas administrativas anteriores. Un acceso público sin `next` nunca abre administración: vuelve a `/explorar.php`.

## APIs del proyecto

| Ruta |
|---|
| `/api/productores.php` |
| `/api/productores-direccion.php` |
| `/api/productores-ubicacion.php` |
| `/api/fincas-direccion.php` |
| `/api/compradores.php` |
| `/api/transportistas.php` |
| `/api/transportistas-vehiculos.php` |
| `/api/vehiculos.php` |
| `/api/pagometodos.php` |
| `/api/capacidades.php` |
| `/api/identidad.php` |
| `/api/metodo-no-permitido.php` |

`/api/metodo-no-permitido.php` es una respuesta auxiliar para métodos HTTP no admitidos; no es una pantalla navegable.

`/api/identidad.php` resuelve la superficie del navegador: sin Bearer devuelve
una respuesta pública y con Bearer devuelve los contextos de la persona.
`/api/capacidades.php` permite inscribir, abandonar o reactivar contextos desde
la vista pública, siempre sujeto a la validación del servidor.

## Estado de autenticación

### Regla anti-recarga infinita

`/entrar` y el resto de rutas públicas no deben inicializar el shell administrativo,
la geolocalización automática ni ninguna navegación al cargar el módulo de
autenticación. `Public/js/shared/auth-gate.js` se importa desde código compartido,
por lo que su efecto de arranque está deliberadamente encerrado en
`isPrivateRoute(pathname)`. La única navegación automática del gate ocurre cuando
una ruta privada no tiene sesión administrativa verificada.

Si vuelve a observarse una recarga de `/entrar`, primero debe comprobarse la red:

- Muchos `GET /entrar` con respuesta `200` y sin `3xx` indican una recarga iniciada
  por el navegador o JavaScript, no una redirección de Apache.
- Revisar la consola del navegador y el panel Network antes de cambiar el servidor.
- Verificar que ningún módulo cargado por `/entrar` llame a `location.assign`,
  `location.replace`, `location.reload` o inicialice el shell privado durante la
  evaluación del módulo.
- Mantener una prueba de regresión que compruebe que el bloque de arranque privado
  está protegido por `isPrivateRoute(pathname)`.

La captura automática de ubicación se reserva para las pantallas administrativas
que realmente ofrecen funciones basadas en la ubicación; el login no debe pedir
permisos de geolocalización ni producir efectos secundarios de sesión.

Cuando se modifique el gate o una dependencia que lo importe, se debe aumentar
la versión de consulta del módulo (`auth-gate.js?v=...`) y de la entrada del login.
Los módulos ES se cachean por URL completa; cambiar solo el contenido del archivo
no garantiza que una pestaña abierta deje de ejecutar la versión anterior.
La entrada PHP de `/entrar` también envía `Cache-Control: no-store` para que una
pestaña atrapada en un ciclo no conserve el HTML anterior.

### Implementado

- Formulario de acceso con validación de navegador.
- Sesión Supabase en `sessionStorage`, con Bearer para las solicitudes JSON.
- Redirección pública por defecto hacia `explorar.php`.
- Redirección a `login.php?next=...` cuando una ruta administrativa no tiene marcador local válido.
- Cierre de la sesión local desde el shell administrativo.
- La interfaz privada se mantiene oculta hasta que el gate del frontend valida el marcador.

### No implementado todavía

- La marca `tindercows:admin-session` solo habilita el shell después de que
  `api/admin-status.php` confirmó la allowlist; no es una credencial y nunca
  sustituye el Bearer que valida el servidor.
- La allowlist administrativa debe configurarse en el entorno del servidor.
- Catálogo real de ganado/subastas conectado a la vista Explorar.
- Persistencia real de favoritos, contacto y pujas.
- Publicar y comprar muestran el estado de preparación sin escribir en la base:
  el contrato comercial histórico todavía liga comprador/interacción a
  Productor y no permite implementar de forma segura un Comprador independiente.
- Política definitiva de privacidad, retención y ejercicio de derechos.

La autorización real de API continúa siendo un mecanismo separado del login visual: `SupabaseActorResolver` verifica el Bearer y `AdminAuthorization` aplica la allowlist para escrituras administrativas.

## Sincronización Docker y remoto

### Incidente registrado: checkout equivocado (2026-09-22)

Docker monta como `/var/www/html` el checkout local
`/home/dilan/Documentos/GitHub/Proyecto-Paradigmas`. Un checkout temporal bajo
`/tmp/` puede servir para revisar archivos, pero no cambia lo que ve el
contenedor. Editar o probar únicamente ese checkout deja Docker, localhost y
`origin/dev` en estados distintos; ese fue el origen del desorden de esta
sesión.

Antes de modificar frontend o configuración se debe comprobar el mismo origen
en tres puntos: `git rev-parse HEAD` y `git status --short` en el checkout
montado, `git ls-remote origin refs/heads/dev` para el remoto, y el montaje de
`docker inspect proyecto-paradigmas-app-1`. Si hay cambios locales, se guardan
en un stash o parche con nombre antes de actualizar; nunca se reemplazan con un
reset destructivo. Después de integrar se compara el SHA de `HEAD` con el SHA
remoto, se comprueban los archivos por HTTP desde el contenedor y se conserva
el respaldo hasta terminar las pruebas.

La regresión se demuestra comprobando que Docker sirve el mismo commit y los
mismos recursos que el checkout, incluido `/assets/geo/costa-rica-limits.geojson`.
Una pantalla remota que difiera de localhost requiere primero revisar el
checkout montado, el caché de módulos ES y el volumen del contenedor; no se
deben corregir versiones distintas en paralelo.

### Incidente registrado: fullscreen recortaba los controles del mapa (2026-09-22)

El mapa tenía búsqueda, capas, ubicación y cierre fuera del nodo que MapLibre
maximizaba. Al activar pantalla completa solo se veía el lienzo, por lo que las
opciones parecían desaparecer. El control debe recibir como `container` el
selector completo de finca, no únicamente el canvas del mapa. El selector se
organiza en fullscreen con `flex` y el canvas ocupa el espacio restante.
Toda modificación futura del contenedor de fullscreen debe comprobar que los
controles siguen visibles y operables, que `Esc` restaura el estado normal y que
el mapa conserva scroll, zoom, búsqueda, capas y ubicación.

### Incidente registrado: etiquetas ausentes en vista satelital (2026-09-22)

La capa satelital se añadía al final del estilo MapLibre. Ese orden cubría las
capas `symbol` que dibujan nombres de calles, barrios y lugares. Las capas
raster del mapa y de SNIT/IGN deben insertarse antes de la primera capa de
etiquetas del estilo; así la imagen satelital queda debajo de los nombres y los
detalles siguen siendo legibles. El control de capas usa botones con
`aria-pressed` para que Mapa, Satélite y Detalles oficiales tengan un estado
visible y reversible.

La composición visual inicial siguió una regla de prioridad: la búsqueda era
una barra flotante compacta sobre la esquina superior izquierda del mapa; el
selector de capas quedaba bajo fullscreen en la esquina superior derecha; y
ubicación y quitar punto quedaban juntos en la esquina inferior derecha. Esa
versión no debe restaurarse, porque reducía el área útil y no seguía el patrón
de mapas solicitado.

La distribución posterior se alineó con el patrón visual solicitado de mapas
de navegación: la búsqueda permanece arriba a la izquierda con sus resultados
desplegables; zoom, pantalla completa y orientación quedan en el lateral
derecho; ubicación y quitar punto se agrupan abajo a la derecha; y las capas se
presentan abajo a la izquierda como tarjetas visuales, con Satélite como opción
destacada y Mapa/Detalles oficiales como alternativas. Esta es solo una
decisión de interfaz: la cartografía sigue siendo OpenFreeMap/OSM con SNIT/IGN
y Esri para las capas autorizadas.

### Incidente registrado: sobre-zoom y búsqueda sin sugerencias (2026-09-22)

OpenFreeMap usa datos vectoriales de OpenMapTiles con un nivel máximo operativo.
Permitir que el usuario se acercara sin límite hacía que MapLibre solicitara
teselas inexistentes y mostrara bloques grises con “Map data not yet available”.
El mapa ahora limita el zoom a 14, y los centrados por ubicación automática o
por búsqueda respetan ese mismo límite.

La búsqueda ahora consulta después de una pausa breve mientras se escribe,
además de conservar Enter y el botón de búsqueda. Las sugerencias se obtienen
de Nominatim con país Costa Rica y el área acotada del proyecto; al elegir una
se conserva la latitud y longitud devueltas por el proveedor. No se copia la
cartografía ni el autocompletado propietario de Google: la ubicación automática
usa la geolocalización del navegador y la búsqueda usa el proveedor abierto
aprobado para el proyecto, evitando una dependencia de pago y manteniendo la
atribución requerida.
