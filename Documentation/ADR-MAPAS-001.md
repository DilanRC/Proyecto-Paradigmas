# ADR-MAPAS-001 - Ubicacion automatica, punto exacto de finca y cercania

**Estado:** aceptada para Front 2.0  
**Fecha:** 2026-09-14  
**Alcance:** ubicacion del visitante, cartografia de finca y ranking por cercania.

## Conclusion

TinderCows maneja dos hechos espaciales diferentes:

1. **ubicacion actual del visitante**: se intenta obtener automaticamente con la API de geolocalizacion del navegador y se conserva solo temporalmente en la sesion del navegador para recomendar publicaciones cercanas;
2. **punto exacto de una finca**: dato persistente y opcional de `tbdireccion`, elegido con un mapa solo cuando el usuario o el administrador desea precisar la finca.

`tbproductorubicacion` no se usa para recomendar cercania. Ese historico representa observaciones del productor y no indica necesariamente donde esta el ganado publicado.

## Hechos comprobados del modelo

- Una publicacion de animal referencia `tbfinca`.
- `tbfincadireccion` enlaza la finca con `tbdireccion`.
- `tbdireccion` ya contiene provincia, canton, distrito, pueblo y senas.
- El frontend ya posee un catalogo territorial interno para provincia -> canton -> distrito -> pueblo/localidad.
- `tbproductorubicacion` es un historico separado de observaciones GPS del productor.
- La API de publicaciones permite leer el catalogo para Explorar.

## Decision de datos

`tbdireccion` incorpora dos atributos opcionales:

```text
tbdireccionlatitud  DECIMAL(10,7) NULL
tbdireccionlongitud DECIMAL(10,7) NULL
```

No se crea `tbfincaubicacion` porque el punto describe la ubicacion fisica que ya representa `tbdireccion`.

No se agregan PK, FK, UNIQUE, CHECK, AUTO_INCREMENT, indices, triggers ni procedimientos. PHP valida:

- que latitud y longitud lleguen juntas o ambas ausentes;
- latitud entre -90 y 90;
- longitud entre -180 y 180;
- formato numerico;
- relacion conceptual con la finca mediante los modelos existentes.

Las direcciones que no necesiten punto exacto conservan ambas columnas en `NULL`.

## Ubicacion automatica del visitante

### Proposito

Permitir que Explorar muestre primero ganado ubicado en fincas mas cercanas a la posicion actual del visitante.

### Mecanismo

El sitio intenta `navigator.geolocation.getCurrentPosition()` automaticamente al iniciar la experiencia publica y en el shell privado cuando corresponde.

El navegador mantiene su propia politica de permisos: el sistema puede iniciar la solicitud automaticamente, pero no puede saltarse una decision del usuario o del navegador.

La posicion obtenida:

- vive en `sessionStorage` bajo `tindercows:ubicacion-usuario`;
- caduca a los 15 minutos;
- no se escribe en MySQL;
- no modifica Persona, Productor, Comprador ni Transportista;
- no crea filas en `tbproductorubicacion`;
- no se envia a OpenFreeMap solo por obtenerla.

Si dos modulos la solicitan al mismo tiempo, comparten la misma captura en curso para evitar dos prompts/lecturas simultaneas.

### Fallos

Si el permiso se deniega, el navegador no soporta geolocalizacion, hay timeout o la posicion no esta disponible:

- Explorar sigue funcionando;
- no se anuncia una ubicacion ficticia;
- el orden vuelve al criterio normal de publicaciones recientes.

## Ranking por cercania

Cuando existe una posicion fresca del visitante, el frontend envia al API de publicaciones:

```text
latitud
longitud
```

El controlador valida ambos valores y `PublicacionCercaniaService`:

1. resuelve la finca de cada publicacion;
2. obtiene el punto opcional de su `tbdireccion`;
3. calcula distancia Haversine en PHP;
4. ordena primero publicaciones con punto conocido por distancia ascendente;
5. deja publicaciones sin punto despues, conservando recencia como desempate;
6. devuelve `distanciaKm`, no las coordenadas privadas de la finca.

Sin posicion del visitante se conserva el ranking `RECIENTE`.

### Limite de escala actual

Para ordenar correctamente antes de paginar, la implementacion del avance obtiene el conjunto filtrado y lo ordena en PHP. Es suficiente para el volumen actual del proyecto, pero si el catalogo crece de forma importante debe evolucionar a una estrategia de candidatos por zona/geohash/bounding box antes de calcular distancia fina.

No se agrega un indice espacial a MySQL porque contradice la regla actual de Calidad de mantener la base deliberadamente minima y sin indices.

## Mapa de finca

El mapa aparece **solo de forma opcional dentro de una finca**.

Casos:

- registro/configuracion de Productor por el usuario;
- CRUD administrativo de Productor/Finca;
- edicion posterior de la direccion de una finca.

Flujo:

1. agregar o editar finca;
2. completar provincia, canton, distrito, pueblo y senas cuando corresponda;
3. opcionalmente pulsar **Abrir mapa para ubicar finca**;
4. el mapa se centra en el punto existente, en la ubicacion temporal del visitante si esta disponible o, como ultimo recurso, en Costa Rica;
5. hacer clic o arrastrar el marcador;
6. el punto queda pendiente en el formulario;
7. solo guardar la direccion persiste las coordenadas.

**Quitar punto exacto** deja latitud/longitud en `NULL` sin eliminar la direccion textual.

## Renderer y proveedor

Se mantiene:

- **MapLibre GL JS 6.9.0** como renderer;
- **OpenStreetMap** como fuente geografica abierta;
- **OpenFreeMap** como proveedor inicial de estilo/vector tiles;
- `Public/js/shared/mapa.js` como unica capa que conoce MapLibre directamente;
- `Public/js/shared/finca-mapa.js` como componente de negocio reutilizable por usuario y admin.

El mapa no se carga al mostrar un formulario. MapLibre/OpenFreeMap se cargan solo al pulsar el boton de mapa.

La atribucion a OpenFreeMap y OpenStreetMap permanece visible.

## Privacidad

### Posicion del visitante

Se usa para cercania y centrado de mapa. No se guarda en servidor en este flujo.

### Punto de finca

Es persistente porque describe un lugar del negocio. La UI debe dejar claro que marcarlo es opcional.

### OpenFreeMap

OpenFreeMap recibe peticiones de estilo/teselas solamente cuando se abre un mapa. Obtener GPS automaticamente no abre el mapa ni envia esa coordenada al proveedor cartografico.

## Contrato JSON

El contrato de negocio Browser <-> PHP sigue usando JSON.

El ranking de publicaciones utiliza parametros de consulta para GET, igual que los filtros existentes, y las escrituras de direccion de finca se realizan mediante cuerpos JSON.

Style JSON, tiles, sprites y fuentes son recursos cartograficos externos y no endpoints del dominio PHP.

## Geocodificacion y rutas

### Photon

No se integra todavia. El catalogo territorial interno resuelve la validacion administrativa y actualmente no existe una necesidad aprobada de autocomplete/reverse geocoding que justifique enviar direcciones o coordenadas a otro tercero.

Si se incorpora, sera detras de PHP:

```text
Frontend -> JSON -> Controller -> Service -> PhotonClient -> Photon
```

con timeout, debounce, cancelacion de consultas obsoletas, limite geografico cuando aplique y sin sobrescribir silenciosamente la direccion elegida por el usuario.

### Valhalla

Se reserva para un proceso real de fletes que requiera ruta, distancia vial, duracion, matriz o multiples paradas. MapLibre solo dibuja; no decide precio ni elegibilidad de transporte.

## Fallos y degradacion

- GPS denegado/no disponible: ranking reciente.
- ubicacion vencida: se intenta renovar.
- mapa/CDN/style/teselas fallan: direccion manual sigue disponible.
- mapa tarda demasiado: timeout y fallback, nunca `Cargando...` permanente.
- punto de finca ausente: publicacion sigue visible, pero queda despues de las publicaciones cuya distancia puede calcularse.
- escritura opcional de direccion de finca falla tras guardar Productor: la UI informa especificamente que finca no pudo completar su direccion y permite reintentar desde esa finca; no anuncia que todo se guardo correctamente.

## Pendiente

- Ejecutar migracion `008coordenadasdireccionfinca.sql` sobre una base MySQL existente antes de usar el nuevo contrato.
- Alinear y ejecutar el espejo PostgreSQL/Supabase antes de desplegar esa variante.
- Verificar navegador real: permisos GPS, WebGL, movil, teclado y fallo real del proveedor.
- Validar con Calidad si el punto exacto debe representar entrada, corral/punto de retiro u otra referencia cuando Transporte se implemente; por ahora la UI lo presenta como punto de referencia exacto de la finca, no como poligono ni limite catastral.

## Fuentes tecnicas

- MapLibre GL JS: https://maplibre.org/maplibre-gl-js/docs/
- OpenFreeMap: https://openfreemap.org/
- OpenStreetMap attribution: https://www.openstreetmap.org/copyright
- Photon: https://github.com/komoot/photon
