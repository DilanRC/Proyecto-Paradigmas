# ADR-MI-ACTIVIDAD-001 - Dashboard público y recursos propios

**Estado:** implementada en `dev`
**Fecha:** 2026-09-22
**Alcance:** `/mi-actividad`, `/api/v1/actividad`, `/api/v1/mi-fincas` y `/api/v1/mi-vehiculos`.

## Decisión

`/mi-actividad` es el centro de autogestión de una Persona autenticada. El
dashboard consulta la identidad, las tres actividades de negocio, las fincas
del Productor y los vehículos del Transportista. La identidad es una sola; las
actividades son contextos independientes y su estado se deriva de los hechos
persistidos.

Fincas y vehículos usan recursos separados porque el CRUD administrativo y la
autogestión tienen actores, políticas y contratos distintos:

```text
JWT verificado -> ActorContext -> personaId -> tbtransportista -> vehículos enlazados
                                 └───────> tbproductor -> fincas propias
```

El navegador nunca envía `personaId`, `identificacionNumero` ni
`transportistaId` como autoridad. `POST`, `PUT`, `DELETE` y `PATCH` rechazan
campos de identidad o asociación que no formen parte del contrato propio.

## API de fincas propias

El modal de `Mi actividad` reutiliza únicamente el subflujo de finca existente:
nombre, dirección administrativa opcional, señas y punto exacto en el mapa. No
reutiliza el formulario administrativo de identidad del Productor ni su
endpoint protegido para consultar direcciones por cédula.

| Método | Operación | Cuerpo |
|---|---|---|
| `GET` | lista las fincas propias activas | ninguno |
| `POST` | crea o reactiva una finca propia | `nombreFinca`, `direccionFinca` opcional |
| `PUT` | actualiza nombre y, si se envía, dirección | `fincaId`, `nombreFinca`, `direccionFinca` opcional |
| `DELETE` | desactiva lógicamente una finca propia | `fincaId` |
| `PATCH` | reactiva una finca propia | `fincaId` |

El Productor se deriva de `ActorContext.personaId`; el navegador no puede
elegir `productorId`, `personaId` ni una identificación objetivo. La respuesta
incluye los detalles de dirección solo después de comprobar que la finca
pertenece a ese Productor.

## API de vehículos propios

| Método | Operación | Cuerpo |
|---|---|---|
| `GET` | lista propia, incluso si la actividad está inactiva | ninguno |
| `POST` | crea y enlaza | `placa`, `vin`, `modelo` |
| `PUT` | actualiza un vehículo propio activo | `vehiculoId`, `placa`, `vin`, `modelo` |
| `DELETE` | desactiva lógicamente uno propio | `vehiculoId` |
| `PATCH` | reactiva uno propio | `vehiculoId` |

El endpoint no reemplaza ni debilita `/api/v1/vehiculos` ni
`/api/v1/transportistas/vehiculos`: esas superficies siguen exigiendo la
autorización administrativa del servidor.

## Transacción y concurrencia

Las escrituras propias bloquean, en orden estable, el alta de finca o el
enlace de vehículo, el alta de dirección cuando corresponde, la fila del
Productor/Transportista o del recurso y la bitácora. La operación de negocio
se confirma en una transacción; cualquier excepción hace `ROLLBACK`. Los identificadores se
asignan con `MAX(id)+1` protegido por `NamedLock`, conforme a la regla de la
base deliberadamente mínima. No se agregan PK, FK, UNIQUE, CHECK,
AUTO_INCREMENT, triggers ni procedimientos almacenados.

La bitácora registra actor, entidad `FINCA` o `VEHICULO`, acción, estado
anterior/nuevo, origen `API_MI_FINCAS` o `API_MI_VEHICULOS` y solicitud. La
relación Transportista-Vehículo no se trata como histórico: es la asociación
vigente; la bitácora conserva quién ejecutó el cambio.

## Estados de interfaz

El dashboard distingue carga, error y contenido para la actividad principal.
Fincas y vehículos tienen su propio estado de carga, error con reintento, vacío,
contenido y escritura no disponible cuando Transportista no está configurado
o está inactivo. Un error de vehículos no oculta la identidad ni las demás
actividades. Los modales conservan los datos del formulario ante error,
validan por campo, evitan doble envío, devuelven el foco al disparador y
permiten teclado y Escape.

El enlace opcional **Completar actividad** del menú público se decide con una
lectura autoritativa de `/api/v1/actividad`: se muestra solo si falta algún
contexto y se oculta si todos están configurados o si la consulta falla. La
pantalla de registro ya redirige al perfil después de un alta exitosa y el
perfil vuelve a consultar al servidor, por lo que `sessionStorage` solo sirve
como cache visual y nunca como autorización.
