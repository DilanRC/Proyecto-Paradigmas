# ADR-MAPAS-001 - Cartografia, ubicaciones y futura logistica

**Estado:** aceptada para Front 2.0, con puntos pendientes de validacion de Calidad  
**Fecha:** 2026-09-14  
**Alcance:** frontend cartografico y politica de ubicacion. No modifica el esquema MySQL.

## Clasificacion

### HECHO COMPROBADO

- La aplicacion ya posee `tbdireccion` para provincia, canton, distrito, pueblo y senas.
- `Public/js/shared/territorio.js` y `direccion.js` mantienen el catalogo territorial propio y la cascada administrativa.
- `tbproductorubicacion` representa observaciones historicas del productor y es append-only desde PHP.
- `ProductorUbicacionController` solo permite GET y POST para ese historico; PUT/PATCH/DELETE estan rechazados.
- Antes de esta ADR, `auth-gate.js` iniciaba una captura GPS en background despues del login.
- El frontend no tiene bundler ni gestor de dependencias de navegador: `Public/js/package.json` solo declara `type=module`.

### INFERENCIA

- Una coordenada permanente de finca seria un hecho diferente de la direccion declarada y de la ubicacion observada del productor.
- Las futuras rutas de flete necesitaran puntos de origen/destino estables o confirmados, pero ese proceso aun no justifica modificar la base.

### PROPUESTA ADOPTADA

- MapLibre GL JS como renderer cartografico.
- OpenStreetMap como fuente abierta de datos geograficos.
- OpenFreeMap como proveedor inicial de estilo/vector tiles, configurado en un unico modulo sustituible.
- El mapa es ayuda visual y nunca requisito para completar un CRUD.
- La geolocalizacion del navegador solo se solicita despues de una accion explicita y no se persiste hasta una confirmacion adicional.
- La ubicacion observada del productor se muestra como dato distinto de direccion/finca.

### PENDIENTE

- Confirmar con Calidad si “comunicacion exclusivamente mediante JSON” se refiere al API de negocio Browser <-> PHP o literalmente a todas las peticiones HTTP del navegador. MapLibre consume style JSON, vector tiles, sprites y fuentes que no forman parte del API de negocio.
- Definir el hecho espacial permanente de una finca antes de crear estructura para coordenadas de finca.
- Aprobar un caso de uso real de geocodificacion antes de integrar Photon.
- Aprobar un caso de uso de rutas/distancias antes de integrar Valhalla.

## Necesidad

El sistema necesita capacidades geograficas para tres problemas distintos:

1. visualizar lugares sin reemplazar el catalogo territorial del negocio;
2. permitir observaciones de ubicacion de un productor con consentimiento explicito;
3. preparar una base tecnica para futura exploracion geografica y logistica.

El mapa no debe convertir por accidente esos tres problemas en un solo dato.

## Alternativas

### Leaflet + OSM raster

Beneficios: integracion simple, madura y apropiada para mapas basicos y pocos marcadores.  
Costos: el crecimiento hacia capas vectoriales, clustering intensivo y estilos complejos queda menos natural. Ademas, no se desea depender directamente de `tile.openstreetmap.org` en produccion.

### MapLibre GL JS

Beneficios:
- open source;
- vector tiles y renderizado WebGL/GPU;
- estilos intercambiables;
- sources/layers y clustering adecuados para una futura vista lista + mapa;
- permite cambiar proveedor sin cambiar reglas de negocio si la configuracion se centraliza.

Costos:
- mayor complejidad que Leaflet;
- requiere WebGL;
- mayor peso inicial;
- la accesibilidad exige alternativa HTML/textual.

### OpenLayers

Beneficios: open source y muy completo para GIS, proyecciones y multiples fuentes.  
Costo: para los casos actuales aporta una superficie de API y complejidad mayor de la necesaria; el proyecto no requiere aun GIS avanzado.

## Decision

Usar **MapLibre GL JS 6.9.0** como renderer y **OpenFreeMap** como proveedor inicial de estilo/vector tiles mediante:

- modulo propio: `Public/js/shared/mapa.js`;
- MapLibre fijado a `6.9.0`;
- ESM: `https://unpkg.com/maplibre-gl@6.9.0/dist/maplibre-gl.mjs`;
- CSS: `https://unpkg.com/maplibre-gl@6.9.0/dist/maplibre-gl.css`;
- estilo inicial: `https://tiles.openfreemap.org/styles/liberty`.

La URL de estilo vive en `MAP_STYLE_URL` y `crearMapa()` acepta otro `styleUrl`. OpenFreeMap no forma parte del dominio ni de las reglas del negocio.

### Por que CDN en este avance

El repositorio no tiene bundler ni flujo npm de navegador; introducir uno solo para el mapa seria un refactor desproporcionado. Se usa CDN con version exacta y la dependencia queda documentada.

Riesgo: indisponibilidad del CDN/proveedor.  
Mitigacion: version fijada, proveedor configurable, timeout, fallback manual y mapa no critico para CRUD. Una evolucion posterior puede vendorizar MapLibre o incorporarlo a un pipeline real.

## API propia del mapa

Los consumidores usan:

- `crearMapa()`;
- `establecerMarcador()`;
- `obtenerCoordenadas()`;
- `centrar()`;
- `ajustar()`;
- `redimensionar()`;
- `destruir()`.

Los formularios de negocio no construyen `new maplibre.Map()` ni conocen la URL de OpenFreeMap.

## Direccion declarada vs coordenada vs observacion

### Direccion declarada

`provincia -> canton -> distrito -> pueblo -> senas` se valida con el catalogo interno. Es la fuente administrativa del negocio.

### Coordenada de finca

No se persiste en este avance. Falta decidir si representa centro de finca, entrada, corral, punto de retiro u otro punto logistico; quien la valida; precision requerida; si cambia; si el valor anterior importa y como se corrige.

### Ubicacion observada de productor

Se conserva en `tbproductorubicacion` como historico append-only. Puede originarse en navegador o entrada manual. No modifica la direccion del productor ni una finca.

## Consentimiento y privacidad

La geolocalizacion deja de ejecutarse al entrar al sistema.

Flujo:

1. el usuario ve para que sirve la captura;
2. pulsa **Usar mi ubicacion**;
3. se invoca `navigator.geolocation`;
4. se muestran latitud, longitud, precision y origen;
5. puede cancelar/repetir;
6. solo **Registrar observacion** hace POST al API propio.

Por defecto se usa `enableHighAccuracy=false` para no pedir mas precision de la necesaria. Un aviso de UX se presenta sobre 100 m, pero ese umbral **no es una regla de negocio ni rechaza el dato**.

**Ingresar ubicacion manualmente** permanece disponible aunque GPS o mapa fallen.

### Terceros

- GPS: no se envia a OpenFreeMap automaticamente.
- API propio: las coordenadas confirmadas se envian a PHP por JSON.
- Mapa: solo se abre al pulsar **Mostrar en mapa**. En ese momento el navegador solicita a OpenFreeMap estilo/teselas de la zona y el proveedor recibe los metadatos normales de una peticion web.
- No se ejecuta reverse geocoding en este avance, por lo que no se envian coordenadas a Photon.

## Fallos y degradacion

- Permiso denegado: mensaje claro + alternativa manual.
- Navegador sin geolocalizacion: alternativa manual.
- Timeout/ubicacion no disponible: reintento + alternativa manual.
- Cancelacion: una respuesta tardia del navegador se ignora y no se persiste.
- Baja precision: aviso y posibilidad de repetir; no hay falso rechazo.
- MapLibre/CDN/style/OpenFreeMap inaccesible: timeout/fallback; datos textuales y CRUD siguen disponibles.
- Error de tesela/recurso posterior: mapa parcialmente disponible; no bloquea el proceso.
- POST 4xx/5xx/red/JSON invalido: no se anuncia exito y la candidata queda para reintentar.
- Cierre/reapertura: el mapa se destruye y puede recrearse.
- Resize: `ResizeObserver` solicita `map.resize()`.
- Movil: el caso actual usa mapa de lectura `interactive=false`, por lo que no secuestra scroll/zoom tactil.

## Geocodificacion - Photon

**No se integra Photon todavia.**

Motivos:
- no existe un proceso aprobado que necesite autocomplete/reverse geocoding;
- el catalogo territorial interno ya resuelve la validacion administrativa;
- la instancia publica de Photon puede limitar uso y no garantiza disponibilidad;
- reverse geocoding enviaria coordenadas a un tercero y requiere una decision de privacidad.

Punto de extension futuro:

```text
Frontend -> JSON -> GeocodificacionController
                 -> GeocodificacionService
                 -> GeocodificacionProvider / PhotonClient
                 -> Photon
Frontend <- JSON <- Controller <- Service <- Provider
```

Politicas previstas: limitar a Costa Rica cuando corresponda, debounce, AbortController, minimo de caracteres, timeout, cache justificada, sanitizacion, validacion del servidor y nunca sobrescribir silenciosamente provincia/canton/distrito/pueblo.

## Routing - Valhalla

No se implementa ahora. Si Transporte requiere distancia, duracion, origen/destino, rutas, matrices o varias paradas, el candidato open source sera Valhalla:

```text
Frontend -> JSON -> PHP Controller -> Service -> Valhalla
         <- JSON <- PHP Controller <- Service <- geometria/metricas
```

MapLibre solo dibuja geometria; no decide precio, transportista, elegibilidad ni reglas de transporte.

## PMTiles

Se conserva como alternativa futura para reducir dependencia del proveedor y habilitar self-hosting. No se incorpora ahora porque su complejidad no aporta valor suficiente al avance actual.

## Contrato JSON y recursos cartograficos

El contrato de negocio Browser <-> PHP continua siendo JSON.

Style JSON, vector tiles, sprites y fuentes son recursos cartograficos estaticos externos al API de negocio. **PENDIENTE CALIDAD:** confirmar si “exclusivamente JSON” pretende abarcar tambien estos recursos tecnicos. Si el profesor lo interpreta literalmente para toda peticion del navegador, la arquitectura debera revisarse antes de fusionar a `dev`.

## Decision sobre DB

No se agregan ni modifican tablas/columnas. En particular:

- no se agregan coordenadas a `tbfinca`;
- no se agrega tabla de puntos de finca;
- no se modifica `tbproductorubicacion`;
- no se agregan PK/FK/UNIQUE/CHECK/AUTO_INCREMENT, indices, triggers ni procedimientos.

La estructura de finca solo se reconsiderara despues de definir: que punto representa, quien lo captura/valida, si cambia, si el anterior importa, precision, origen, correccion y proceso dependiente.

## Fuentes tecnicas consultadas

- MapLibre GL JS: https://maplibre.org/maplibre-gl-js/docs/
- OpenFreeMap: https://openfreemap.org/
- Photon: https://github.com/komoot/photon
- OpenStreetMap attribution: https://www.openstreetmap.org/copyright
