# Matriz arquitectónica P0-C

## Estado

CERRADA para ordenar implementación. No autoriza SQL sobre puntos marcados como
PENDIENTE.

## Regla base

Productor es la entidad de negocio núcleo. Comprador y Vendedor son
clasificaciones derivadas del comportamiento del Productor. Compra y Venta son
hechos históricos propios.

`tbvendedor` no debe existir. `tbcomprador` queda como estructura legacy de
compatibilidad hasta migración explícita; no debe ampliarse ni recibir tabla de
periodos.

## Matriz

| Tema | Estado | Decisión P0-C | Evidencia | Límite |
|---|---|---|---|---|
| `tbcomprador` | APROBACIÓN DIRECTA DE CALIDAD + LEGACY | Reinterpretar como compatibilidad legacy, no como entidad normativa. Futuro: migrar o retirar hacia clasificación histórica del Productor. | Calidad indicó que no existen entidades separadas Productor, Comprador y Vendedor; un Productor puede actuar como Comprador o Vendedor. | No borrar tabla ni datos ahora. No crear `tbcompradorestadoperiodo`. |
| Vendedor | APROBACIÓN DIRECTA DE CALIDAD | No crear `tbvendedor`, `tbvendedorestadoperiodo` ni histórico de perfil vendedor. | Calidad indicó que Vendedor no es entidad. | Venta sí puede existir como hecho histórico. |
| Clasificación Comprador/Vendedor | DECISIÓN ARQUITECTÓNICA | Usar dos históricos especializados: `tbproductorcompradorperiodo` y `tbproductorvendedorperiodo`. | Un Productor puede ser Comprador y Vendedor a la vez; dos tablas evitan roles genéricos y permiten reglas distintas. | Depende de T2 y de hechos T7/T8. No implementar hasta tener eventos aprobados. |
| Criterios y pesos | PENDIENTE DE CALIDAD/ARQUITECTURA | No guardar pesos en SQL. El algoritmo T10 debe iniciar en modo informe con política versionada fuera del esquema. | No hay pesos confirmados en la evidencia disponible. | Bloquea activación automática de transiciones. |
| Eventos que otorgan/pierden/reactivan clasificación | PARCIAL | Alta por hechos observables confirmados: me gusta, seguir, carrito, compra, venta. Pérdida/reactivación quedan como política de T10/T11. | Calidad confirmó funnel y Compra/Venta como hechos. | Sin umbrales ni caducidad aprobada no se cierran periodos automáticamente. |
| `tbpersonaestado` | DECISIÓN VIGENTE | Estado global de disponibilidad de la persona. Si está inactiva, ninguna capacidad/clasificación opera. | DEC-PER-003 y patrón implementado. | Semántica global, no historial de negocio. |
| `tbfincaestado` | DECISIÓN VIGENTE | Estado lógico de la finca, aplicado por PHP. | Patrón vigente de CRUD y diagnóstico. | No define temporalidad histórica. |
| `tbvehiculoestado` | PROPUESTA ACEPTADA PARA CRUD ACTUAL | Estado lógico del vehículo, aplicado por PHP. | Existe por patrón del CRUD actual; Calidad confirmó placa, VIN y modelo, no el estado. | No usar como prueba de horario, cobertura o asignación histórica. |
| `tbpagometodoactivo` | DECISIÓN VIGENTE | Disponibilidad del método en catálogo. | Alcance vigente contiene efectivo y CRUD de métodos. | No implica método usado en compra/venta/flete. |
| `tbanimalestado` | PENDIENTE | Debe definirse antes de T8. | No existe tabla animal actual ni estado confirmado. | No crear SQL todavía. |
| `tbcompraestado` | PENDIENTE | Debe definirse antes de T8. | Compra fue confirmada como hecho, no sus estados. | No crear SQL todavía. |
| Actividad | DECISIÓN ARQUITECTÓNICA | Registrar actividad por Productor + contexto, no por perfil Comprador/Vendedor. | Productor es núcleo y comprador/vendedor son clasificaciones. | Catálogo final debe aprobarse antes de T6. |
| Ubicación | DECISIÓN VIGENTE | Ubicación general de Productor; no ubicación por comprador/vendedor. | Ya existe `tbproductorubicacion` y Productor es núcleo. | No sustituye dirección residencial. |
| Horario histórico | PENDIENTE | No modelar hasta confirmación. | No hay evidencia aprobada. | Bloquea tramo de horario de transporte. |
| Cobertura histórica | PENDIENTE | No modelar hasta confirmación. | No hay evidencia aprobada. | Bloquea cobertura de transporte. |
| Vehículo-transportista histórico | PENDIENTE | La asignación actual puede seguir; temporalidad requiere aprobación. | Existe asociación actual; temporalidad no está confirmada. | No agregar fechas a la relación sin aprobación. |
| Venta ligada a compra | PENDIENTE | No imponer obligatoriedad. | Compra y Venta son hechos separados; no hay regla de dependencia confirmada. | T8 debe decidir `tbcompraid` opcional/condicional antes de SQL. |
| Método de pago de compra/venta/flete | PENDIENTE | El catálogo existe; el hecho que use pago debe guardar su método. | `tbpagometodo` existe, pero no hay hechos económicos implementados. | No agregar columnas hasta definir Compra/Venta/Flete. |
| Funnel | APROBACIÓN DIRECTA DE CALIDAD | Registrar me gusta, seguir, carrito y compra. | Calidad confirmó la secuencia. | Visualización por fila sigue como propuesta, no requisito. |
| Visualización por fila | PROPUESTA | No capturar hasta aprobación. | No fue confirmada. | No crear tabla/evento de vista por fila. |

## Consecuencia operativa

T0 y herramientas derivadas pueden avanzar porque no cambian semántica de
negocio. T4b, T7, T8, T9, T10 y T11 solo pueden implementar partes cuyo evento
o política esté aprobada. Donde esta matriz dice PENDIENTE, el tramo debe parar
antes de SQL.
