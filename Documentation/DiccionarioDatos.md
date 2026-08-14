# Diccionario de datos

El esquema no contiene claves, restricciones, índices, valores `DEFAULT`,
columnas `AUTO_INCREMENT` ni objetos programables. Las asociaciones, la unicidad lógica y los consecutivos son
políticas de PHP. Todos los identificadores SQL están en minúscula.

Columnas de cada ficha:

- **Tipo**: declaración exacta del script.
- **NULL**: si el motor acepta ausencia de valor.
- **Origen**: quién produce el dato.
- **Relación conceptual**: tabla a la que apunta el valor. Ninguna de estas
  relaciones existe como llave foránea; el motor no las verifica.

## tbproductor

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbproductorid` | `INT NOT NULL` | No | Consecutivo calculado por PHP. | Aplicación | - |
| `tbproductoridentificacionnumero` | `VARCHAR(250) NOT NULL` | No | Identificación canónica e inmutable por aplicación. | Usuario | - |
| `tbproductoridentificaciontipo` | `VARCHAR(40) NOT NULL` | No | Tipo validado por aplicación. | Usuario | - |
| `tbproductornombre` | `VARCHAR(150) NOT NULL` | No | Nombre del productor. | Usuario | - |
| `tbproductortelefono` | `VARCHAR(20) NOT NULL` | No | Teléfono. | Usuario | - |
| `tbproductorcorreoelectronico` | `VARCHAR(150) NOT NULL` | No | Correo, no único en MySQL. | Usuario | - |
| `tbproductorestado` | `TINYINT(1) NOT NULL` | No | Desactivación lógica. | Aplicación | - |

## tbproductordireccion

Asocia al productor con su residencia principal. No almacena datos de
ubicación: la política del modelo espera una sola fila por `tbproductorid`.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbproductordireccionid` | `INT NOT NULL` | No | Identificador de la asociación, distinto del productor y de la dirección. | Aplicación | - |
| `tbproductorid` | `INT NOT NULL` | No | Identificador lógico del productor asociado. | Aplicación | `tbproductor` |
| `tbdireccionid` | `INT NOT NULL` | No | Identificador lógico de la ubicación física. | Aplicación | `tbdireccion` |

Observación: provincia, cantón, distrito, pueblo y señas vivían antes en esta
tabla y ahora existen una sola vez en `tbdireccion`. La migración
`Database/Migrations/001normalizadireccionproductor.sql` trasladó los datos y
eliminó las columnas. El contrato de base cambió: la capa de aplicación escribe
la ubicación en `tbdireccion` y aquí solamente el enlace.

## tbdireccion

Ubicación física reutilizable. No pertenece a productor ni a finca.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbdireccionid` | `INT NOT NULL` | No | Identificador lógico de la ubicación. | Aplicación | - |
| `tbdireccionprovincia` | `VARCHAR(100) NOT NULL` | No | Provincia. | Usuario | - |
| `tbdireccioncanton` | `VARCHAR(100) NOT NULL` | No | Cantón. | Usuario | - |
| `tbdirecciondistrito` | `VARCHAR(100) NOT NULL` | No | Distrito. | Usuario | - |
| `tbdireccionpueblo` | `VARCHAR(150) NULL` | Sí | Pueblo opcional. | Usuario | - |
| `tbdireccionsenas` | `VARCHAR(500) NULL` | Sí | Señas opcionales. | Usuario | - |

## tbfinca

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbfincaid` | `INT NOT NULL` | No | Consecutivo calculado por PHP. | Aplicación | - |
| `tbproductorid` | `INT NOT NULL` | No | Productor dueño de la finca. Soporta la cardinalidad 1 a N. | Aplicación | `tbproductor` |
| `tbfincanombre` | `VARCHAR(150) NOT NULL` | No | Nombre validado por aplicación. | Usuario | - |
| `tbfincaestado` | `TINYINT(1) NOT NULL` | No | Estado lógico. | Aplicación | - |

## tbfincadireccion

Asocia una finca con su ubicación. La política del modelo espera una sola fila
por `tbfincaid`.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbfincadireccionid` | `INT NOT NULL` | No | Identificador de la asociación. | Aplicación | - |
| `tbfincaid` | `INT NOT NULL` | No | Identificador lógico de la finca. | Aplicación | `tbfinca` |
| `tbdireccionid` | `INT NOT NULL` | No | Identificador lógico de la ubicación. Puede repetir el valor usado por `tbproductordireccion` cuando es el mismo lugar físico. | Aplicación | `tbdireccion` |

## tbpagometodo

Catálogo de métodos de pago. No se relaciona todavía con ninguna operación.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbpagometodoid` | `INT NOT NULL` | No | Identificador lógico del método. | Datos iniciales | - |
| `tbpagometodonombre` | `VARCHAR(100) NOT NULL` | No | Nombre del método. Valor vigente: `Efectivo`. | Datos iniciales | - |
| `tbpagometododescripcion` | `VARCHAR(250) NOT NULL` | No | Descripción del método. | Datos iniciales | - |
| `tbpagometodoactivo` | `TINYINT(1) NOT NULL` | No | Disponibilidad del método. | Datos iniciales | - |

## tbtransportista

Persona independiente responsable del transporte. No es productor, comprador,
usuario ni empresa: tiene identificador propio.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbtransportistaid` | `INT NOT NULL` | No | Identificador lógico propio del transportista. | Aplicación | - |
| `tbtransportistaidentificacionnumero` | `VARCHAR(250) NOT NULL` | No | Identificación canónica. | Usuario | - |
| `tbtransportistaidentificaciontipo` | `VARCHAR(40) NOT NULL` | No | Tipo de identificación. | Usuario | - |
| `tbtransportistanombre` | `VARCHAR(150) NOT NULL` | No | Nombre del transportista. | Usuario | - |
| `tbtransportistatelefono` | `VARCHAR(20) NOT NULL` | No | Teléfono. | Usuario | - |
| `tbtransportistacorreoelectronico` | `VARCHAR(150) NOT NULL` | No | Correo electrónico. | Usuario | - |
| `tbtransportistaestado` | `TINYINT(1) NOT NULL` | No | Estado lógico. | Aplicación | - |

Hecho confirmado: el transportista es una persona independiente con
identificador propio y puede tener varios vehículos.

Propuesta de modelado: identificación, tipo, nombre, teléfono, correo y estado
se agregaron para poder identificar y contactar a la persona, siguiendo el
patrón de personas ya registrado en el proyecto. No fueron solicitados y pueden
retirarse si el dominio no los necesita.

PENDIENTE DE CONFIRMACIÓN: cuáles de esos atributos son obligatorios, y si
hacen falta licencia, permisos, pólizas, tarifas, capacidad, horarios o vínculo
con empresa. Ninguno de estos últimos se agregó.

## tbvehiculo

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbvehiculoid` | `INT NOT NULL` | No | Identificador lógico interno del vehículo. | Aplicación | - |
| `tbvehiculoplaca` | `VARCHAR(20) NOT NULL` | No | Placa del vehículo. Sin unicidad en el motor. | Usuario | - |
| `tbvehiculovin` | `VARCHAR(50) NOT NULL` | No | VIN del vehículo. Sin unicidad en el motor. | Usuario | - |
| `tbvehiculomodelo` | `VARCHAR(100) NOT NULL` | No | Modelo del vehículo. | Usuario | - |
| `tbvehiculoestado` | `TINYINT(1) NOT NULL` | No | Estado lógico. Propuesta de modelado, no dato confirmado: sigue el patrón del resto de tablas. | Aplicación | - |

Datos confirmados: placa, vin y modelo. `tbvehiculoestado` es una propuesta de
modelado.

PENDIENTE DE CONFIRMACIÓN: color, año, marca, peso, capacidad, combustible,
marchamo e inspección técnica. Ninguno se agregó.

## tbtransportistavehiculo

Asocia transportista y vehículo.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbtransportistavehiculoid` | `INT NOT NULL` | No | Identificador de la asociación. | Aplicación | - |
| `tbtransportistaid` | `INT NOT NULL` | No | Identificador lógico del transportista. | Aplicación | `tbtransportista` |
| `tbvehiculoid` | `INT NOT NULL` | No | Identificador lógico del vehículo. | Aplicación | `tbvehiculo` |

## tbbitacora

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbbitacoraid` | `BIGINT UNSIGNED NOT NULL` | No | Consecutivo calculado por PHP. | Aplicación | - |
| `tbbitacoraentidad` | `VARCHAR(80) NOT NULL` | No | Entidad auditada. | Aplicación | - |
| `tbbitacoraregistroidentificacionnumero` | `VARCHAR(250) NOT NULL` | No | Identificación lógica auditada. | Aplicación | - |
| `tbbitacoraaccion` | `VARCHAR(30) NOT NULL` | No | Acción. | Aplicación | - |
| `tbbitacorafecha` | `DATETIME NOT NULL` | No | Fecha del evento, enviada por PHP. | Aplicación | - |
| `tbbitacoradatosanteriores` | `JSON NULL` | Sí | Estado anterior. | Aplicación | - |
| `tbbitacoradatosnuevos` | `JSON NULL` | Sí | Estado nuevo. | Aplicación | - |
| `tbbitacoraactortipo` | `VARCHAR(30) NOT NULL` | No | Tipo de actor. | Aplicación | - |
| `tbbitacorausuarioid` | `BIGINT UNSIGNED NULL` | Sí | Usuario, nulo antes de autenticación. | Aplicación | - |
| `tbbitacoraorigen` | `VARCHAR(100) NOT NULL` | No | Origen técnico. | Aplicación | - |
| `tbbitacorasolicitudid` | `VARCHAR(100) NOT NULL` | No | Correlación de solicitud. | Aplicación | - |

## tbcomprador

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbcompradorid` | `INT NOT NULL` | No | Identificador asignado por la aplicación. | Aplicación | - |
| `tbcompradoridentificacionnumero` | `VARCHAR(250) NOT NULL` | No | Identificación canónica. | Usuario | - |
| `tbcompradoridentificaciontipo` | `VARCHAR(40) NOT NULL` | No | Tipo de identificación. | Usuario | - |
| `tbcompradornombre` | `VARCHAR(150) NOT NULL` | No | Nombre del comprador. | Usuario | - |
| `tbcompradortelefono` | `VARCHAR(20) NOT NULL` | No | Teléfono. | Usuario | - |
| `tbcompradorcorreoelectronico` | `VARCHAR(150) NOT NULL` | No | Correo electrónico. | Usuario | - |
| `tbcompradorestado` | `TINYINT(1) NOT NULL` | No | Estado lógico. | Aplicación | - |

## Estructura, relación y política

| Nivel | Ejemplo | Quién lo garantiza |
|---|---|---|
| Estructura | existen `tbvehiculo`, `tbtransportista` y `tbtransportistavehiculo` | el script SQL |
| Relación conceptual | `tbtransportistavehiculo.tbtransportistaid` indica el transportista del vehículo | el dato, sin verificación del motor |
| Política | un vehículo pertenece a un solo transportista | la capa de aplicación, todavía no implementada |

Ninguna política de este documento se implementa con llaves, restricciones,
triggers, procedimientos, funciones ni eventos. `Database/Tests/diagnostico.sql`
solamente permite detectar los incumplimientos.
