# Matriz arquitectónica P0-C

## Estado

CERRADA para ordenar implementación. Autoriza la estructura DB confirmada por
Calidad y no autoriza SQL sobre puntos marcados como PENDIENTE.

## Regla base

Productor es la entidad de negocio núcleo. Comprador y Vendedor son
clasificaciones derivadas del comportamiento del Productor. Compra y Venta son
hechos históricos propios.

`tbvendedor` no debe existir. `tbcomprador` tiene destino definitivo: es la
marca de capacidad de compra de una `tbpersona`, igual que `tbtransportista`,
y se queda así. No es entidad, no se amplía, no recibe tabla de periodos y no
se migra ni se retira después: la historia de la clasificación Comprador vive
completa en `tbproductorclasificacionperiodo`.

## Matriz

| Tema | Estado | Decisión P0-C | Evidencia | Límite |
|---|---|---|---|---|
| `tbcomprador` | APROBACIÓN DIRECTA DE CALIDAD | Destino definitivo: marca de capacidad de compra de una persona, no entidad y no legacy en tránsito. Se conserva con sus tres columnas y sin periodos. | Calidad indicó que no existen entidades separadas Productor, Comprador y Vendedor; un Productor puede actuar como Comprador o Vendedor. | No borrar tabla ni datos. No crear `tbcompradorestadoperiodo`. La historia va en `tbproductorclasificacionperiodo`. |
| Vendedor | APROBACIÓN DIRECTA DE CALIDAD | No crear `tbvendedor`, `tbvendedorestadoperiodo` ni histórico de perfil vendedor. | Calidad indicó que Vendedor no es entidad. | Venta sí puede existir como hecho histórico. |
| Clasificación Comprador/Vendedor | APROBACIÓN DIRECTA DE CALIDAD | Usar `tbproductorclasificacionperiodo` con tipo `COMPRADOR` o `VENDEDOR` validado en PHP. | Un Productor puede ser Comprador y Vendedor a la vez; una fila por tipo permite periodos simultáneos sin crear roles ni entidades separadas. | No implementar el algoritmo de alta/baja todavía. |
| Criterios y pesos | PENDIENTE DE CALIDAD/ARQUITECTURA | No guardar pesos en SQL. El algoritmo T10 debe iniciar en modo informe con política versionada fuera del esquema. | No hay pesos confirmados en la evidencia disponible. | Bloquea activación automática de transiciones. |
| Eventos que otorgan/pierden/reactivan clasificación | PARCIAL | Alta por hechos observables confirmados: me gusta, seguir, carrito, compra, venta. Pérdida/reactivación quedan como política de T10/T11. | Calidad confirmó funnel y Compra/Venta como hechos. | Sin umbrales ni caducidad aprobada no se cierran periodos automáticamente. |
| `tbpersonaestado` | DECISIÓN VIGENTE | Estado global de disponibilidad de la persona. Si está inactiva, ninguna capacidad/clasificación opera. | DEC-PER-003 y patrón implementado. | Semántica global, no historial de negocio. |
| `tbfincaestado` | DECISIÓN VIGENTE | Estado lógico de la finca, aplicado por PHP. | Patrón vigente de CRUD y diagnóstico. | No define temporalidad histórica. |
| `tbvehiculoestado` | PROPUESTA ACEPTADA PARA CRUD ACTUAL | Estado lógico del vehículo, aplicado por PHP. | Existe por patrón del CRUD actual; Calidad confirmó placa, VIN y modelo, no el estado. | No usar como prueba de horario, cobertura o asignación histórica. |
| `tbpagometodoactivo` | DECISIÓN VIGENTE | Disponibilidad del método en catálogo. | Alcance vigente contiene efectivo y CRUD de métodos. | No implica método usado en compra/venta/flete. |
| Animal | APROBACIÓN DIRECTA DE CALIDAD | `tbanimal` guarda la identidad aprobada: identificación, raza, sexo y características. `tbanimalproduccionsalud` guarda lo que cambia: peso, edad observada, producción y salud. | Calidad pidió identificación, raza, sexo y características como datos del animal, y producción/salud como dato variable. | Edad y peso no vuelven a la identidad. No inventar fecha de nacimiento ni estado sin aprobación. |
| Publicación | APROBACIÓN DIRECTA DE CALIDAD | Crear `tbanimalpublicacion` y congelar vendedor y finca del momento. El estado de la publicación es histórico: `tbanimalpublicacionestadoperiodo`, no columna mutable. | La reconstrucción no debe depender de relaciones futuras y un estado sobrescrito borra pasado. | El catálogo de estados y el ciclo funcional los define Backend; la estructura ya no pierde transiciones. |
| Compra | APROBACIÓN DIRECTA DE CALIDAD | Crear `tbcompra` como hecho económico con animal, comprador, finca/origen, fecha, hora, lugar, precio y método de pago. | Compra fue confirmada como hecho propio. | No crear `tbcompraestado` sin semántica aprobada. |
| Venta | APROBACIÓN DIRECTA DE CALIDAD | Crear `tbventa` como hecho económico con vendedor, comprador, animal, método de pago, dirección (`tbventadireccionid`), propósito (`tbventaproposito`) y snapshots opcionales. `tbcompraid` es opcional. | Calidad pidió dirección y propósito de la venta y no hay DEC que los descarte. | No obligar dependencia con compra. Ambos son NULL cuando no se conocen; no se inventan. |
| `tbanimalestado` | PENDIENTE | Debe definirse antes de usarlo en comportamiento. | No hay estado animal confirmado. | No crear columna ni tabla de estado animal todavía. |
| `tbcompraestado` | PENDIENTE | Debe definirse antes de usarlo en comportamiento. | Compra fue confirmada como hecho, no sus estados. | No crear SQL todavía. |
| Actividad | DECISIÓN ARQUITECTÓNICA | Registrar actividad por Productor + contexto, no por perfil Comprador/Vendedor. | Productor es núcleo y comprador/vendedor son clasificaciones. | Catálogo final debe aprobarse antes de T6. |
| Ubicación | DECISIÓN VIGENTE | Ubicación general de Productor; no ubicación por comprador/vendedor. | Ya existe `tbproductorubicacion` y Productor es núcleo. | No sustituye dirección residencial. |
| Horario de transportista | APROBACIÓN DIRECTA DE CALIDAD | Crear `tbtransportistahorario` con día, hora inicio, hora fin y periodo de vigencia. Cambiar horario cierra el periodo y abre otro. | Calidad pidió el horario del transportista como dato del perfil. | Sin cobertura geográfica: eso sigue PENDIENTE. |
| Cobertura histórica | PENDIENTE | No modelar hasta confirmación. | No hay evidencia aprobada. | Bloquea cobertura de transporte. |
| Vehículo-transportista histórico | PENDIENTE | La asignación actual sigue sin fechas. El vehículo usado en un flete se congela en `tbtransportistaflete.tbvehiculoid`, que sí es un hecho. | Existe asociación actual; su temporalidad no está confirmada, pero el flete sí registra con qué vehículo se hizo. | No agregar fechas a `tbtransportistavehiculo` sin aprobación. |
| Venta ligada a compra | PENDIENTE | No imponer obligatoriedad. | Compra y Venta son hechos separados; no hay regla de dependencia confirmada. | T8 debe decidir `tbcompraid` opcional/condicional antes de SQL. |
| Flete | APROBACIÓN DIRECTA DE CALIDAD | `tbtransportistaflete` recupera vehículo usado, cantidad de cabezas transportadas y distancia recorrida, además de origen, destino, fecha, hora, precio y método de pago. | Calidad enumeró esos datos del flete y ninguna DEC los descartó; se habían perdido al pasar la matriz a SQL. | Cantidad de fletes por semana y método frecuente siguen derivándose, no se guardan. |
| Reseña de transportista | APROBACIÓN DIRECTA DE CALIDAD | El autor es `tbpersonaid`: quien contrató el flete y califica es la persona, no una clasificación. | Transportista y comprador son capacidades de persona; exigir productor dejaba fuera a quien contrata sin ser productor. | La reseña sigue sin promedio almacenado. |
| Estados mutables sin histórico | DECISIÓN VIGENTE | Ningún estado de negocio vive como columna sobrescribible. `tbcarritoestado` y `tbanimalpublicacionestado` se eliminan y sus transiciones pasan a `tbcarritoestadoperiodo` y `tbanimalpublicacionestadoperiodo`. | Regla base del esquema histórico: no se borra pasado. | Los bits `tbpersonaestado`, `tbfincaestado`, `tbvehiculoestado` y `tbpagometodoactivo` no son estado de negocio y siguen sin histórico. |
| Método de pago de compra/venta/flete | APROBACIÓN DIRECTA DE CALIDAD | El hecho que use pago guarda `tbpagometodoid`; método frecuente se deriva. | Compra, venta y flete quedan como hechos. | No guardar agregados derivados. |
| Funnel | APROBACIÓN DIRECTA DE CALIDAD | Registrar me gusta, seguir, carrito y compra en `tbanimalinteraccion`; carrito usa `tbcarrito`, `tbcarritoanimal` y `tbcarritoestadoperiodo`. | Calidad confirmó la secuencia. | El estado del carrito no puede ser columna mutable. Visualización por fila sigue como propuesta, no requisito. |
| Visualización por fila | PROPUESTA | No capturar hasta aprobación. | No fue confirmada. | No crear tabla/evento de vista por fila. |

## Consecuencia operativa

Tras la pasada de concordancia, la capa DB llega a 30 tablas en `Database/Migrations/006estructuracomercialhistorica.sql`
porque las tablas nuevas representan hechos y periodos confirmados sin política
automática. T4b, T7, T8, T9, T10 y T11 solo pueden implementar comportamiento
cuyo evento o política esté aprobada. Donde esta matriz dice PENDIENTE, el
tramo debe parar antes de SQL.
