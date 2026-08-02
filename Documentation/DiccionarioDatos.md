# Diccionario de datos

Ninguna columna es PK o FK y no existen CHECK. Todos los índices son ordinarios
no únicos. Las reglas indicadas como validación pertenecen a PHP.

## tbproductor

| Columna | Tipo | Uso |
|---|---|---|
| `tbproductorId` | `INT NOT NULL` | Consecutivo calculado por PHP; sin clave y sin AUTO_INCREMENT. |
| `tbproductorIdentificacionNumero` | `VARCHAR(250)` | Identificación canónica e inmutable por aplicación. |
| `tbproductorIdentificacionTipo` | `VARCHAR(40)` | Tipo validado por aplicación. |
| `tbproductorNombre` | `VARCHAR(150)` | Nombre del productor. |
| `tbproductorTelefono` | `VARCHAR(20)` | Teléfono. |
| `tbproductorCorreoElectronico` | `VARCHAR(150)` | Correo, no único. |
| `tbproductorEstado` | `TINYINT(1)` | Desactivación lógica. |

## tbproductordireccion

| Columna | Tipo | Uso |
|---|---|---|
| `tbproductorId` | `INT` | Asociación lógica con productor, sin FK. |
| `tbproductordireccionProvincia` | `VARCHAR(100)` | Provincia. |
| `tbproductordireccionCanton` | `VARCHAR(100)` | Cantón. |
| `tbproductordireccionDistrito` | `VARCHAR(100)` | Distrito. |
| `tbproductordireccionPueblo` | `VARCHAR(150) NULL` | Pueblo opcional. |
| `tbproductordireccionSenas` | `VARCHAR(500) NULL` | Señas opcionales. |

## tbproductorfinca

| Columna | Tipo | Uso |
|---|---|---|
| `tbproductorId` | `INT` | Asociación lógica con productor, sin FK. |
| `tbproductorfincaNombre` | `VARCHAR(150)` | Nombre validado por aplicación. |
| `tbproductorfincaEstado` | `TINYINT(1)` | Estado lógico. |

## tbbitacora

| Columna | Tipo | Uso |
|---|---|---|
| `tbbitacoraId` | `BIGINT UNSIGNED AUTO_INCREMENT` | Secuencia con índice ordinario no único. |
| `tbbitacoraEntidad` | `VARCHAR(80)` | Entidad auditada. |
| `tbbitacoraRegistroIdentificacionNumero` | `VARCHAR(250)` | Identificación lógica auditada. |
| `tbbitacoraAccion` | `VARCHAR(30)` | Acción. |
| `tbbitacoraFecha` | `DATETIME` | Fecha del evento. |
| `tbbitacoraDatosAnteriores` | `JSON NULL` | Estado anterior. |
| `tbbitacoraDatosNuevos` | `JSON NULL` | Estado nuevo. |
| `tbbitacoraActorTipo` | `VARCHAR(30)` | Tipo de actor. |
| `tbbitacoraUsuarioId` | `BIGINT UNSIGNED NULL` | Usuario, nulo antes de autenticación. |
| `tbbitacoraOrigen` | `VARCHAR(100)` | Origen técnico. |
| `tbbitacoraSolicitudId` | `VARCHAR(100)` | Correlación de solicitud. |
