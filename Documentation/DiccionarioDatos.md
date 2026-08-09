# Diccionario de datos

El esquema no contiene claves, restricciones, índices, valores `DEFAULT`,
columnas `AUTO_INCREMENT` ni objetos programables. Las asociaciones, la unicidad lógica y los consecutivos son
políticas de PHP. Todos los identificadores SQL están en minúscula.

## tbproductor

| Columna | Tipo | Uso |
|---|---|---|
| `tbproductorid` | `INT NOT NULL` | Consecutivo calculado por PHP. |
| `tbproductoridentificacionnumero` | `VARCHAR(250)` | Identificación canónica e inmutable por aplicación. |
| `tbproductoridentificaciontipo` | `VARCHAR(40)` | Tipo validado por aplicación. |
| `tbproductornombre` | `VARCHAR(150)` | Nombre del productor. |
| `tbproductortelefono` | `VARCHAR(20)` | Teléfono. |
| `tbproductorcorreoelectronico` | `VARCHAR(150)` | Correo, no único en MySQL. |
| `tbproductorestado` | `TINYINT(1)` | Desactivación lógica. |

## tbproductordireccion

| Columna | Tipo | Uso |
|---|---|---|
| `tbproductordireccionid` | `INT NOT NULL` | Consecutivo calculado por PHP. |
| `tbproductorid` | `INT NOT NULL` | Asociación lógica con productor. |
| `tbproductordireccionprovincia` | `VARCHAR(100)` | Provincia. |
| `tbproductordireccioncanton` | `VARCHAR(100)` | Cantón. |
| `tbproductordirecciondistrito` | `VARCHAR(100)` | Distrito. |
| `tbproductordireccionpueblo` | `VARCHAR(150) NULL` | Pueblo opcional. |
| `tbproductordireccionsenas` | `VARCHAR(500) NULL` | Señas opcionales. |

## tbfinca

| Columna | Tipo | Uso |
|---|---|---|
| `tbfincaid` | `INT NOT NULL` | Consecutivo calculado por PHP. |
| `tbproductorid` | `INT NOT NULL` | Asociación lógica con productor. |
| `tbfincanombre` | `VARCHAR(150)` | Nombre validado por aplicación. |
| `tbfincaestado` | `TINYINT(1)` | Estado lógico. |

## tbbitacora

| Columna | Tipo | Uso |
|---|---|---|
| `tbbitacoraid` | `BIGINT UNSIGNED NOT NULL` | Consecutivo calculado por PHP. |
| `tbbitacoraentidad` | `VARCHAR(80)` | Entidad auditada. |
| `tbbitacoraregistroidentificacionnumero` | `VARCHAR(250)` | Identificación lógica auditada. |
| `tbbitacoraaccion` | `VARCHAR(30)` | Acción. |
| `tbbitacorafecha` | `DATETIME` | Fecha del evento. |
| `tbbitacoradatosanteriores` | `JSON NULL` | Estado anterior. |
| `tbbitacoradatosnuevos` | `JSON NULL` | Estado nuevo. |
| `tbbitacoraactortipo` | `VARCHAR(30)` | Tipo de actor. |
| `tbbitacorausuarioid` | `BIGINT UNSIGNED NULL` | Usuario, nulo antes de autenticación. |
| `tbbitacoraorigen` | `VARCHAR(100)` | Origen técnico. |
| `tbbitacorasolicitudid` | `VARCHAR(100)` | Correlación de solicitud. |
