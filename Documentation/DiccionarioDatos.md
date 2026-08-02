# Diccionario de datos

## `tbproductores`

| Columna | Tipo | Clave | Regla |
|---|---|---|---|
| `tbproductoresIdentificacionNumero` | `VARCHAR(250)` | PK | Identificación canónica, inmutable y no vacía. |
| `tbproductoresIdentificacionTipo` | `VARCHAR(40)` | | Tipo permitido por CHECK. |
| `tbproductoresNombre` | `VARCHAR(150)` | índice | Entre 3 y 150 caracteres. |
| `tbproductoresTelefono` | `VARCHAR(20)` | | Entre 8 y 15 dígitos efectivos. |
| `tbproductoresCorreoElectronico` | `VARCHAR(150)` | índice no único | Contacto en minúsculas. |
| `tbproductoresEstado` | `TINYINT(1)` | índice | `1` activo, `0` inactivo. |

## `tbproductoresdireccion`

| Columna | Tipo | Clave | Regla |
|---|---|---|---|
| `tbproductoresIdentificacionNumero` | `VARCHAR(250)` | PK, FK | Una dirección por productor. |
| `tbproductoresdireccionProvincia` | `VARCHAR(100)` | | Obligatoria. |
| `tbproductoresdireccionCanton` | `VARCHAR(100)` | | Obligatorio. |
| `tbproductoresdireccionDistrito` | `VARCHAR(100)` | | Obligatorio. |
| `tbproductoresdireccionPueblo` | `VARCHAR(150)` | | Opcional. |
| `tbproductoresdireccionSenas` | `VARCHAR(500)` | | Opcional. |

## `tbproductoresfinca`

| Columna | Tipo | Clave | Regla |
|---|---|---|---|
| `tbproductoresIdentificacionNumero` | `VARCHAR(250)` | PK, FK | Productor al que pertenece el registro. |
| `tbproductoresfincaNombre` | `VARCHAR(150)` | PK | Nombre no vacío; completa la PK compuesta. |
| `tbproductoresfincaEstado` | `TINYINT(1)` | | Estado lógico de la finca del productor. |

## `tbbitacora`

| Columna | Tipo | Clave | Regla |
|---|---|---|---|
| `tbbitacoraId` | `BIGINT UNSIGNED AUTO_INCREMENT` | PK | Secuencia técnica del evento. |
| `tbbitacoraEntidad` | `VARCHAR(80)` | índice | `PRODUCTOR`. |
| `tbbitacoraRegistroIdentificacionNumero` | `VARCHAR(250)` | índice | Identificación textual auditada. |
| `tbbitacoraAccion` | `VARCHAR(30)` | | Acción CRUD. |
| `tbbitacoraFecha` | `DATETIME` | índice | Fecha del servidor. |
| `tbbitacoraDatosAnteriores` | `JSON` | | Estado anterior o NULL. |
| `tbbitacoraDatosNuevos` | `JSON` | | Estado nuevo o NULL. |
| `tbbitacoraActorTipo` | `VARCHAR(30)` | | `NO_AUTENTICADO`. |
| `tbusuarioId` | `BIGINT UNSIGNED` | | NULL antes de autenticación. |
| `tbbitacoraOrigen` | `VARCHAR(100)` | | `API_PRODUCTORES`. |
| `tbbitacoraSolicitudId` | `VARCHAR(100)` | índice | Correlación técnica. |
