# Matriz arquitectónica P0-C

## Estado

CERRADA para ordenar implementación. Autoriza la estructura DB confirmada por
Calidad y no autoriza SQL sobre puntos marcados como PENDIENTE.

## Regla base

Productor es la entidad de negocio núcleo. Comprador y Vendedor son
clasificaciones derivadas del comportamiento del Productor. Compra y Venta son
hechos históricos propios.

`tbvendedor` no debe existir. `tbcomprador` es LEGACY de compatibilidad
temporal: no es entidad ni capacidad permanente de `tbpersona`. Desde
DEC-DBREADY-008 el CRUD ya fue retirado y la tabla sobrevive únicamente para
backfill/auditoría hasta el paso (e). La única fuente de verdad de Comprador y
Vendedor es `tbproductorclasificacionperiodo`.

## Matriz

| Tema | Estado | Decisión P0-C | Evidencia | Límite |
|---|---|---|---|---|
| Comprador | APROBACIÓN DIRECTA DE CALIDAD | Comprador es una clasificación del Productor y se lee **únicamente** en `tbproductorclasificacionperiodo` con `tipo = COMPRADOR`. No es entidad ni capacidad de persona. | Calidad indicó que no existen entidades separadas Productor, Comprador y Vendedor; un Productor puede actuar como Comprador o Vendedor. | Ninguna consulta de negocio debe leer Comprador desde `tbcomprador`. |
| `tbcomprador` | LEGACY / COMPATIBILIDAD TEMPORAL | El CRUD manual quedó retirado en DEC-DBREADY-008. La tabla física se conserva solo como evidencia/entrada del backfill hasta el paso (e). | Su semántica original (Comprador como perfil independiente) quedó superada por la evidencia de Calidad. | No ampliarla, no darle históricos, no crear `tbcompradorestadoperiodo`. No hacer `DROP` hasta verificar el backfill sobre datos reales y tomar respaldo recuperable. |
| Vendedor | APROBACIÓN DIRECTA DE CALIDAD | No crear `tbvendedor`, `tbvendedorestadoperiodo` ni histórico de perfil vendedor. | Calidad indicó que Vendedor no es entidad. | Venta sí puede existir como hecho histórico. |
| Clasificación Comprador/Vendedor | APROBACIÓN DIRECTA DE CALIDAD | Usar `tbproductorclasificacionperiodo` con tipo `COMPRADOR` o `VENDEDOR` validado por PHP. | Un Productor puede ser Comprador y Vendedor a la vez; una fila por tipo permite periodos simultáneos sin crear roles ni entidades separadas. | No implementar criterios automáticos sin cerrar la política de T10. |
| Criterios y pesos | PENDIENTE DE CALIDAD/ARQUITECTURA | No guardar pesos en SQL. T10 debe iniciar en modo informe con política versionada fuera del esquema. | No hay pesos confirmados en la evidencia disponible. | Bloquea activación automática de transiciones. |
| Eventos que otorgan/pierden/reactivan clasificación | PENDIENTE DE POLÍTICA | Me gusta, seguir, carrito, compra y venta son hechos/señales disponibles, pero la evidencia no fija por sí sola cuál abre/cierra una clasificación ni con qué peso. | Calidad confirmó el funnel y Compra/Venta como hechos; la regla de clasificación no quedó fijada. | Sin umbrales ni regla aprobada no se abren/cierran periodos automáticamente. Entre DEC-DBREADY-008 y T10 solo se conservan clasificaciones existentes/backfill. |
| `tbpersonaestado` | DECISIÓN VIGENTE | Estado global de disponibilidad de la persona. Una Persona inactiva no opera, pero ese bit no borra ni cierra por sí solo un periodo COMPRADOR/VENDEDOR. | DEC-PER-003 y DEC-DBREADY-008. | Semántica global, no histórico de clasificación. |
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
| Venta ligada a compra | DECISIÓN CONSERVADORA | No imponer obligatoriedad; `tbventa.tbcompraid` es opcional. | Compra y Venta son hechos separados y un animal puede existir fuera de una compra registrada en el sistema. | Backend no debe exigir compra previa si no existe evidencia de esa regla. |
| Flete | APROBACIÓN DIRECTA DE CALIDAD | `tbtransportistaflete` conserva vehículo usado, cantidad de cabezas transportadas y distancia recorrida, además de origen, destino, fecha, hora, precio y método de pago. | Calidad enumeró esos datos del flete y ninguna DEC los descartó. | Cantidad de fletes por semana y método frecuente siguen derivándose, no se guardan. |
| Reseña de transportista | APROBACIÓN DIRECTA DE CALIDAD | El autor es `tbpersonaid`: quien contrata/califica se identifica por Persona, no por una clasificación Comprador. | La identidad compartida vive en `tbpersona`; exigir una clasificación concreta mezclaría identidad con comportamiento. | La reseña sigue sin promedio almacenado. |
| Estados mutables sin histórico | DECISIÓN VIGENTE | Ningún estado de negocio vive como columna sobrescribible. `tbcarritoestado` y `tbanimalpublicacionestado` se eliminan y sus transiciones pasan a `tbcarritoestadoperiodo` y `tbanimalpublicacionestadoperiodo`. | Regla base del esquema histórico: no se borra pasado. | Los bits `tbpersonaestado`, `tbfincaestado`, `tbvehiculoestado` y `tbpagometodoactivo` no son estado de negocio y siguen sin histórico. |
| Método de pago de compra/venta/flete | APROBACIÓN DIRECTA DE CALIDAD | El hecho que use pago guarda `tbpagometodoid`; método frecuente se deriva. | Compra, venta y flete quedan como hechos. | No guardar agregados derivados. |
| Funnel | APROBACIÓN DIRECTA DE CALIDAD | Registrar me gusta, seguir, carrito y compra en `tbanimalinteraccion`; carrito usa `tbcarrito`, `tbcarritoanimal` y `tbcarritoestadoperiodo`. | Calidad confirmó la secuencia. | El estado del carrito no puede ser columna mutable. Visualización por fila sigue como propuesta, no requisito. |
| Visualización por fila | PROPUESTA | No capturar hasta aprobación. | No fue confirmada. | No crear tabla/evento de vista por fila. |

## Consecuencia operativa

`tbcomprador` no cuenta como parte del modelo objetivo y el CRUD ya no existe.
DEC-DBREADY-008 conserva únicamente un panel/endpoint GET de lectura sobre
`tbproductorclasificacionperiodo`; la tabla legacy se mantiene para el paso (e)
y para poder auditar/migrar datos reales sin inventar pasado.

La capa DB mantiene 30 tablas en
`Database/Migrations/006estructuracomercialhistorica.sql` porque las tablas
nuevas representan hechos y periodos confirmados sin política automática. T4b,
T7, T8, T9, T10 y T11 solo pueden implementar comportamiento cuyo evento o
política esté aprobado. Donde esta matriz dice PENDIENTE, el tramo debe parar
antes de convertir una suposición en regla de negocio.
