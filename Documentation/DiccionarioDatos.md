# Diccionario de datos

## Convenciones

- Motor: MySQL 8.0, InnoDB, `utf8mb4_unicode_ci`.
- `1` significa activo/verdadero y `0` inactivo/falso.
- Las PK autoincrementales las genera MySQL.
- Las FK usan `ON UPDATE RESTRICT ON DELETE RESTRICT`.
- `UK` indica restricción única. `GENERATED` indica columna calculada y
  almacenada.

## `tbparticipante`

Representa la persona física o jurídica común a todos los roles.

| Columna | Tipo | Nulo | Clave | Descripción |
|---|---|---:|---|---|
| `tbparticipanteId` | `BIGINT UNSIGNED AUTO_INCREMENT` | No | PK | Identificador interno generado por MySQL. |
| `tbparticipanteNombre` | `VARCHAR(150)` | No | índice | Nombre de 3 a 150 caracteres. |
| `tbparticipanteTelefono` | `VARCHAR(20)` | No | | Teléfono principal normalizado; 8 a 15 dígitos efectivos y `+` opcional. |
| `tbparticipanteCorreoElectronico` | `VARCHAR(150)` | No | índice no único | Correo de contacto en minúsculas; no es credencial. |
| `tbparticipanteEstado` | `TINYINT(1)` | No | índice | Estado lógico. Predeterminado `1`. |

No contiene dirección, finca, identificación, contraseña ni marcas de tiempo
administrativas.

## `tbrol`

Catálogo de papeles del participante.

| Columna | Tipo | Nulo | Clave | Descripción |
|---|---|---:|---|---|
| `tbrolId` | `SMALLINT UNSIGNED AUTO_INCREMENT` | No | PK | Identificador del rol. |
| `tbrolCodigo` | `VARCHAR(40)` | No | UK | Código técnico, por ejemplo `PRODUCTOR`. |
| `tbrolNombre` | `VARCHAR(100)` | No | UK | Nombre visible. |
| `tbrolEstado` | `TINYINT(1)` | No | índice | Disponibilidad lógica del catálogo. |

Semillas: `PRODUCTOR`, `COMPRADOR`, `ADMINISTRADOR`.

## `tbparticipanterol`

Asignación N:M entre participante y rol.

| Columna | Tipo | Nulo | Clave | Descripción |
|---|---|---:|---|---|
| `tbparticipanteId` | `BIGINT UNSIGNED` | No | PK, FK | Participante asignado. |
| `tbrolId` | `SMALLINT UNSIGNED` | No | PK, FK | Rol asignado. |
| `tbparticipanterolEstado` | `TINYINT(1)` | No | índice | Estado lógico de la asignación. |

La PK compuesta impide asignar dos veces el mismo rol al mismo participante.

## `tbidentificaciontipo`

Catálogo extensible de documentos; evita modificar un `ENUM` al agregar tipos.

| Columna | Tipo | Nulo | Clave | Descripción |
|---|---|---:|---|---|
| `tbidentificaciontipoId` | `SMALLINT UNSIGNED AUTO_INCREMENT` | No | PK | Identificador del tipo. |
| `tbidentificaciontipoCodigo` | `VARCHAR(40)` | No | UK | Código técnico estable. |
| `tbidentificaciontipoNombre` | `VARCHAR(100)` | No | UK | Nombre visible. |
| `tbidentificaciontipoEstado` | `TINYINT(1)` | No | índice | Disponibilidad lógica. |

Semillas: `CEDULA_FISICA`, `CEDULA_JURIDICA`, `DIMEX`, `NITE` y `PASAPORTE`.

## `tbparticipanteidentificacion`

Documentos del participante. El avance usa exactamente una fila principal
activa por participante activo.

| Columna | Tipo | Nulo | Clave | Descripción |
|---|---|---:|---|---|
| `tbparticipanteidentificacionId` | `BIGINT UNSIGNED AUTO_INCREMENT` | No | PK | Identificador del documento. |
| `tbparticipanteId` | `BIGINT UNSIGNED` | No | FK, índice | Participante identificado. |
| `tbidentificaciontipoId` | `SMALLINT UNSIGNED` | No | FK, UK compuesta | Tipo de documento. |
| `tbparticipanteidentificacionNumero` | `VARCHAR(250)` | No | | Valor visible, conserva formato admitido. |
| `tbparticipanteidentificacionNumeroNormalizado` | `VARCHAR(250)` | No | UK compuesta | Valor de comparación: sin espacios/guiones y letras en mayúsculas. |
| `tbparticipanteidentificacionEsPrincipal` | `TINYINT(1)` | No | | Marca principal. Predeterminado `1`. |
| `tbparticipanteidentificacionEstado` | `TINYINT(1)` | No | índice | Estado lógico del documento. |
| `tbparticipanteidentificacionPrincipalActivaParticipanteId` | `BIGINT UNSIGNED GENERATED STORED` | Sí | UK | Vale el ID del participante solo para una fila principal activa; en las demás vale `NULL`. |

La UK compuesta `(tbidentificaciontipoId,
tbparticipanteidentificacionNumeroNormalizado)` reserva la identidad incluso si
el participante se desactiva.

## `tbparticipantedireccion`

Direcciones 1:N del participante. No representa la ubicación de la finca.

| Columna | Tipo | Nulo | Clave | Descripción |
|---|---|---:|---|---|
| `tbparticipantedireccionId` | `BIGINT UNSIGNED AUTO_INCREMENT` | No | PK | Identificador de dirección. |
| `tbparticipanteId` | `BIGINT UNSIGNED` | No | FK, índice | Participante asociado. |
| `tbparticipantedireccionProvincia` | `VARCHAR(100)` | No | | Provincia escrita por el usuario. |
| `tbparticipantedireccionCanton` | `VARCHAR(100)` | No | | Cantón escrito por el usuario. |
| `tbparticipantedireccionDistrito` | `VARCHAR(100)` | No | | Distrito escrito por el usuario. |
| `tbparticipantedireccionPueblo` | `VARCHAR(150)` | Sí | | Pueblo o barrio opcional. |
| `tbparticipantedireccionSenas` | `VARCHAR(500)` | Sí | | Señas opcionales. |
| `tbparticipantedireccionEsPrincipal` | `TINYINT(1)` | No | | Marca principal. Predeterminado `1`. |
| `tbparticipantedireccionEstado` | `TINYINT(1)` | No | índice | Estado lógico. |
| `tbparticipantedireccionPrincipalActivaParticipanteId` | `BIGINT UNSIGNED GENERATED STORED` | Sí | UK | Vale el ID del participante solo para la dirección principal activa. |

Provincia, cantón y distrito son texto porque el catálogo territorial oficial
completo está fuera del alcance.

## `tbfinca`

Finca que puede asociarse con uno o varios productores.

| Columna | Tipo | Nulo | Clave | Descripción |
|---|---|---:|---|---|
| `tbfincaId` | `BIGINT UNSIGNED AUTO_INCREMENT` | No | PK | Identificador de la finca. |
| `tbfincaNombre` | `VARCHAR(150)` | No | índice | Nombre no vacío. No demuestra titularidad. |
| `tbfincaEstado` | `TINYINT(1)` | No | índice | Estado lógico. Una finca inactiva no admite nuevas asociaciones. |

No se agregan matrícula, plano, área, ubicación o capacidad porque no están
confirmados.

## `tbproductorfinca`

Asociación N:M, no afirmación de propiedad registral.

| Columna | Tipo | Nulo | Clave | Descripción |
|---|---|---:|---|---|
| `tbparticipanteId` | `BIGINT UNSIGNED` | No | PK, FK | Participante con rol `PRODUCTOR` activo. |
| `tbfincaId` | `BIGINT UNSIGNED` | No | PK, FK, índice | Finca asociada. |
| `tbproductorfincaEstado` | `TINYINT(1)` | No | índice | Estado lógico de la asociación. |

La PK compuesta impide duplicados. La condición “participante con rol
PRODUCTOR” es semántica y se comprueba en la aplicación; las FK garantizan que
participante y finca existan.

## `tbbitacora`

Historial administrativo insertado en la misma transacción del cambio.

| Columna | Tipo | Nulo | Clave | Descripción |
|---|---|---:|---|---|
| `tbbitacoraId` | `BIGINT UNSIGNED AUTO_INCREMENT` | No | PK | Identificador del evento. |
| `tbbitacoraEntidad` | `VARCHAR(80)` | No | índice compuesto | Tipo lógico de registro; en este avance `PARTICIPANTE`. |
| `tbbitacoraRegistroId` | `BIGINT UNSIGNED` | No | índice compuesto, sin FK | ID lógico polimórfico de la entidad indicada. |
| `tbbitacoraAccion` | `VARCHAR(30)` | No | | `CREAR`, `ACTUALIZAR`, `DESACTIVAR` o `REACTIVAR`. |
| `tbbitacoraFecha` | `DATETIME` | No | índice | Fecha del servidor, predeterminada a `CURRENT_TIMESTAMP`. |
| `tbbitacoraDatosAnteriores` | `JSON` | Sí | | Contrato de dominio anterior o `NULL`. |
| `tbbitacoraDatosNuevos` | `JSON` | Sí | | Contrato de dominio posterior o `NULL`. |
| `tbbitacoraActorTipo` | `VARCHAR(30)` | No | | `NO_AUTENTICADO` antes de autenticación. |
| `tbusuarioId` | `BIGINT UNSIGNED` | Sí | sin FK | `NULL` porque `tbusuario` no existe aún. |
| `tbbitacoraOrigen` | `VARCHAR(100)` | No | | Origen técnico; `API_PRODUCTORES`. |
| `tbbitacoraSolicitudId` | `VARCHAR(100)` | No | índice | ID técnico recibido o generado por el servidor. |

`tbbitacoraRegistroId` no tiene FK porque el destino depende de
`tbbitacoraEntidad`. Esto debe tratarse como referencia lógica polimórfica, no
como integridad referencial física. La IP, si se agrega en el futuro, sería un
dato técnico y no prueba identidad humana.

## Índices secundarios

| Tabla | Índice | Columnas / propósito |
|---|---|---|
| `tbparticipante` | `idx_tbparticipante_nombre` | búsqueda por nombre |
| `tbparticipante` | `idx_tbparticipante_estado` | filtro lógico |
| `tbparticipante` | `idx_tbparticipante_correo` | búsqueda, no unicidad |
| `tbrol` | `idx_tbrol_estado` | catálogo activo |
| `tbidentificaciontipo` | `idx_tbidentificaciontipo_estado` | catálogo activo |
| `tbparticipanterol` | `idx_tbparticipanterol_rol_estado` | rol y estado |
| `tbparticipanteidentificacion` | `idx_tbparticipanteidentificacion_participante_estado` | principal activa del participante |
| `tbparticipantedireccion` | `idx_tbparticipantedireccion_participante_estado` | principal activa del participante |
| `tbfinca` | `idx_tbfinca_nombre`, `idx_tbfinca_estado` | listado y disponibilidad |
| `tbproductorfinca` | `idx_tbproductorfinca_finca_estado` | asociaciones por finca |
| `tbbitacora` | `idx_tbbitacora_entidad_registro_fecha` | historial de un registro |
| `tbbitacora` | `idx_tbbitacora_solicitud` | correlación técnica |
| `tbbitacora` | `idx_tbbitacora_fecha` | orden cronológico |

