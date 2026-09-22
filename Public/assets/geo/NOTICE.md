# Límites locales de Costa Rica

`costa-rica-limits.geojson` es un artefacto simplificado para validación de puntos, no una carta de navegación.

## Tierra e islas

- Fuente: [IGN/SNIT, Distritos_CR](https://services5.arcgis.com/4u1m1BBDkNDTVWsd/arcgis/rest/services/Distritos_CR/FeatureServer/0).
- Servicio descrito como límite distrital oficial del Instituto Geográfico Nacional/SNIT.
- Consulta utilizada: todos los distritos, `outSR=4326`, `maxAllowableOffset=0.02`.
- La geometría se simplificó para navegador; no sustituye la cartografía oficial de precisión.

## Mar territorial

- Fuente vectorial abierta complementaria: [Fundación MarViva, capa Jurisdiccional](https://services1.arcgis.com/GWTczcNsFHCvuTLo/ArcGIS/rest/services/Jurisdiccional/FeatureServer/1).
- Se seleccionó `pais = Costa Rica` y `nom_zj = Mar territorial`.
- El atributo de la fuente identifica el mar territorial como 12 millas náuticas.
- La fuente complementaria no se presenta como sustituto de los límites oficiales del IGN.

## Referencia oficial marítima

La referencia normativa y cartográfica oficial es [SNIT — Límites continentales y marítimos](https://www1.snitcr.go.cr/geoportal_limites), incluido el [mapa oficial continental, insular y marítimo](https://files.snitcr.go.cr/Visor/limites/MAPA%20OFICIAL%20CONTINENTAL%20INSULAR%20Y%20MARITIMO.pdf).

Al redistribuir este archivo debe mantenerse esta atribución y revisarse la licencia vigente de cada fuente. La capa marítima de MarViva se publica en el servicio consultado; no debe utilizarse para navegación ni como determinación jurídica independiente.
