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

## tbpersona

Fuente única de identidad y contacto compartida por todas las capacidades.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbpersonaid` | `INT NOT NULL` | No | Consecutivo calculado por PHP bajo bloqueo nombrado. | Aplicación | - |
| `tbpersonaidentificacionnumero` | `VARCHAR(250) NOT NULL` | No | Identificación canónica; PHP impide duplicados. | Usuario | - |
| `tbpersonaidentificaciontipo` | `VARCHAR(40) NOT NULL` | No | Tipo de identificación validado por PHP. | Usuario | - |
| `tbpersonanombre` | `VARCHAR(150) NOT NULL` | No | Nombre compartido por todos los perfiles. | Usuario | - |
| `tbpersonatelefono` | `VARCHAR(20) NOT NULL` | No | Teléfono compartido por todos los perfiles. | Usuario | - |
| `tbpersonacorreoelectronico` | `VARCHAR(150) NOT NULL` | No | Correo compartido por todos los perfiles. | Usuario | - |
| `tbpersonaestado` | `TINYINT(1) NOT NULL` | No | Disponibilidad global de la identidad. | Aplicación | - |

## tbproductor

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbproductorid` | `INT NOT NULL` | No | Consecutivo calculado por PHP. | Aplicación | - |
| `tbpersonaid` | `INT NOT NULL` | No | Persona que posee la capacidad. | Aplicación | `tbpersona` |

El productor no guarda estado propio: su estado vigente es el del periodo
abierto en `tbproductorestadoperiodo`.

## tbproductordireccion

Asocia al productor con su residencia principal. No almacena datos de
ubicación: la política del modelo espera una sola fila por `tbproductorid`.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbproductordireccionid` | `INT NOT NULL` | No | Identificador de la asociación, distinto del productor y de la dirección. | Aplicación | - |
| `tbproductorid` | `INT NOT NULL` | No | Identificador lógico del productor asociado. | Aplicación | `tbproductor` |
| `tbdireccionid` | `INT NOT NULL` | No | Identificador lógico de la ubicación física. | Aplicación | `tbdireccion` |
| `tbproductordireccionfechainicio` | `DATETIME NULL` | Sí | Inicio del periodo de residencia. | Aplicación | - |
| `tbproductordireccionfechafin` | `DATETIME NULL` | Sí | Fin del periodo; nulo mientras sea vigente. | Aplicación | - |

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

Capacidad logística de una persona. Conserva su identificador histórico.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbtransportistaid` | `INT NOT NULL` | No | Identificador lógico propio del transportista. | Aplicación | - |
| `tbpersonaid` | `INT NOT NULL` | No | Persona que posee la capacidad logística. | Aplicación | `tbpersona` |
| `tbtransportistaestado` | `TINYINT(1) NOT NULL` | No | Participación independiente como transportista. | Aplicación | - |

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

## tbproductorubicacion

Histórico append-only de ubicaciones GPS observadas del productor (plan §9,
§14-16). Cada lectura inserta una fila nueva; ninguna se actualiza ni se
elimina. La fecha la asigna siempre PHP con el reloj del servidor.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbproductorubicacionid` | `INT NOT NULL` | No | Consecutivo calculado por PHP bajo el bloqueo `tindercows_productor_ubicacion_alta`. | Aplicación | - |
| `tbproductorid` | `INT NOT NULL` | No | Productor al que pertenece la lectura. | Aplicación | `tbproductor` |
| `tbproductorubicacionlatitud` | `DECIMAL(10,7) NOT NULL` | No | Latitud validada por aplicación en el rango -90 a 90. | Usuario | - |
| `tbproductorubicacionlongitud` | `DECIMAL(10,7) NOT NULL` | No | Longitud validada por aplicación en el rango -180 a 180. | Usuario | - |
| `tbproductorubicacionprecision` | `DECIMAL(10,2) NULL` | Sí | Precisión de la lectura, sin unidades en la columna. | Usuario | - |
| `tbproductorubicacionfecha` | `DATETIME NOT NULL` | No | Fecha y hora del servidor; el cliente no puede falsearla. | Aplicación | - |
| `tbproductorubicacionorigen` | `VARCHAR(40) NOT NULL` | No | Origen de la lectura: conjunto controlado `NAVEGADOR` o `MANUAL`. | Usuario | - |

## tbproductorestadoperiodo

Histórico de participación del productor. PHP mantiene como máximo un periodo
abierto por productor.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbproductorestadoperiodoid` | `INT NOT NULL` | No | Consecutivo calculado por PHP. | Aplicación | - |
| `tbproductorid` | `INT NOT NULL` | No | Productor observado. | Aplicación | `tbproductor` |
| `tbproductorestadoperiodoestado` | `TINYINT(1) NOT NULL` | No | Estado durante el periodo. | Aplicación | - |
| `tbproductorestadoperiodofechainicio` | `DATETIME NOT NULL` | No | Inicio del periodo. | Aplicación | - |
| `tbproductorestadoperiodofechafin` | `DATETIME NULL` | Sí | Fin; nulo para el periodo abierto. | Aplicación | - |
| `tbproductorestadoperiodomotivo` | `VARCHAR(250) NULL` | Sí | Motivo opcional del cambio. | Aplicación | - |

## tbproductoractividad

Eventos de actividad usados para trazabilidad y políticas del productor.
`tbproductoractividadtipo` usa el catálogo cerrado del Tramo 12
(Decisiones.md del arquitecto, decisión #2): `login`,
`actualizacion_ubicacion`, `actualizacion_perfil`,
`registro_actividad_productiva`, `contacto_comprador`. Un GET o una vista de
pantalla no genera fila aquí. La base no declara `CHECK` (regla del
profesor); el dominio lo valida PHP al escribir (Tramo 15) y
`Database/Tests/diagnostico.sql` (D-12) lo audita después.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbproductoractividadid` | `INT NOT NULL` | No | Consecutivo calculado por PHP. | Aplicación | - |
| `tbproductorid` | `INT NOT NULL` | No | Productor relacionado. | Aplicación | `tbproductor` |
| `tbproductoractividadtipo` | `VARCHAR(60) NOT NULL` | No | Tipo de actividad, catálogo cerrado (ver arriba), validado por PHP. | Aplicación | - |
| `tbproductoractividadfecha` | `DATETIME NOT NULL` | No | Fecha asignada por PHP. | Aplicación | - |
| `tbproductoractividadorigen` | `VARCHAR(100) NOT NULL` | No | Origen técnico de la actividad. | Aplicación | - |

## tbcomprador

Estructura legacy de compatibilidad. P0-C supera la lectura anterior de
Comprador como capacidad normativa independiente: Comprador y Vendedor son
clasificaciones derivadas del Productor. La tabla se conserva para no romper
datos, pruebas ni frontend existente, pero no debe ampliarse ni recibir
`tbcompradorestadoperiodo`.

| Columna | Tipo | NULL | Descripción | Origen | Relación conceptual |
|---|---|---|---|---|---|
| `tbcompradorid` | `INT NOT NULL` | No | Identificador legacy asignado por la aplicación. | Aplicación | - |
| `tbpersonaid` | `INT NOT NULL` | No | Persona que posee la capacidad. | Aplicación | `tbpersona` |
| `tbcompradorestado` | `TINYINT(1) NOT NULL` | No | Estado legacy de compatibilidad. No sustituye la futura clasificación histórica del Productor. | Aplicación | - |

## Histórico transversal (Tramo 12/13)

La matriz P0-C (`Documentation/MatrizArquitectonicaP0C.md`) supera la
conclusión del Tramo 13 anterior. Productor mantiene sus históricos actuales:
`tbproductorestadoperiodo`, `tbproductorubicacion` y
`tbproductoractividad`. Comprador y Vendedor deben modelarse después como
clasificaciones históricas del Productor, mediante
`tbproductorcompradorperiodo` y `tbproductorvendedorperiodo`.

`tbbitacora` se mantiene exclusivamente como auditoría técnica y no sustituye
históricos de negocio. Compra y Venta son hechos históricos propios, no estados
de `tbcomprador` ni de un `tbvendedor`.

## Estado efectivo y coherencia

Una capacidad está activa solo si `tbpersonaestado` y el estado del perfil
están activos. `DELETE` y `PATCH` modifican el perfil, nunca eliminan filas ni
reactivan una persona globalmente inactiva. Al actualizar identidad o contacto
desde cualquier capacidad, PHP actualiza `tbpersona` y el cambio se observa en
las demás. La unicidad por identificación y la coincidencia de datos se
garantizan con transacciones, sentencias preparadas y bloqueos nombrados.

## Estructura, relación y política

| Nivel | Ejemplo | Quién lo garantiza |
|---|---|---|
| Estructura | existen `tbvehiculo`, `tbtransportista` y `tbtransportistavehiculo` | el script SQL |
| Relación conceptual | `tbtransportistavehiculo.tbtransportistaid` indica el transportista del vehículo | el dato, sin verificación del motor |
| Política | un vehículo pertenece a un solo transportista | la capa de aplicación, todavía no implementada |

Ninguna política de este documento se implementa con llaves, restricciones,
triggers, procedimientos, funciones ni eventos. `Database/Tests/diagnostico.sql`
solamente permite detectar los incumplimientos.
