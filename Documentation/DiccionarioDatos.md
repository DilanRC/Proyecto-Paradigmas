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
| `tbproductoresIdentificacionNumero` | `VARCHAR(250)` | | Referencia lógica; sin PK ni FK. La aplicación mantiene una dirección. |
| `tbproductoresdireccionProvincia` | `VARCHAR(100)` | | Obligatoria. |
| `tbproductoresdireccionCanton` | `VARCHAR(100)` | | Obligatorio. |
| `tbproductoresdireccionDistrito` | `VARCHAR(100)` | | Obligatorio. |
| `tbproductoresdireccionPueblo` | `VARCHAR(150)` | | Opcional. |
| `tbproductoresdireccionSenas` | `VARCHAR(500)` | | Opcional. |

## `tbproductoresfinca`

| Columna | Tipo | Clave | Regla |
|---|---|---|---|
| `tbproductoresIdentificacionNumero` | `VARCHAR(250)` | | Referencia lógica al productor; sin FK. |
| `tbproductoresfincaNombre` | `VARCHAR(150)` | índice no único | Nombre no vacío; no forma una PK. |
| `tbproductoresfincaEstado` | `TINYINT(1)` | | Estado lógico de la finca del productor. |

## `tbbitacora`

| Columna | Tipo | Clave | Regla |
|---|---|---|---|
| `tbbitacoraId` | `BIGINT UNSIGNED AUTO_INCREMENT` | índice no único | Secuencia técnica; no es PK. |
| `tbbitacoraEntidad` | `VARCHAR(80)` | índice | `PRODUCTOR`. |
| `tbbitacoraRegistroIdentificacionNumero` | `VARCHAR(250)` | índice | Identificación textual auditada. |
| `tbbitacoraAccion` | `VARCHAR(30)` | | `CREAR`, `ACTUALIZAR`, `DESACTIVAR` o `REACTIVAR`. |
| `tbbitacoraFecha` | `DATETIME` | índice | Fecha del servidor. |
| `tbbitacoraDatosAnteriores` | `JSON` | | Estado anterior o NULL. |
| `tbbitacoraDatosNuevos` | `JSON` | | Estado nuevo o NULL. |
| `tbbitacoraActorTipo` | `VARCHAR(30)` | | `NO_AUTENTICADO`. |
| `tbusuarioId` | `BIGINT UNSIGNED` | | NULL antes de autenticación. |
| `tbbitacoraOrigen` | `VARCHAR(100)` | | `API_PRODUCTORES`. |
| `tbbitacoraSolicitudId` | `VARCHAR(100)` | índice | Correlación técnica. |

## Juego de caracteres e intercalación

La base `dbtindercows` y sus cuatro tablas usan `utf8mb4` y
`utf8mb4_unicode_ci`.
