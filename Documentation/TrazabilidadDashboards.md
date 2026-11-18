# Trazabilidad de los dashboards

Decisiones: DEC-DASHBOARD-001 (Mi dashboard) y DEC-DASHBOARD-002 (centro de inteligencia del administrador).
Última revisión: 17/11/2026.

Regla general: **una acción real produce un hecho guardado en su tabla; el indicador lo cuenta desde esa tabla y la
pantalla deja ver las filas que lo forman.** La bitácora dice quién hizo qué; no sustituye un hecho económico. Ningún
indicador se guarda en la base (sin tablas de sumas ni promedios) y el esquema no cambió.

## 1. Mapa de acciones y hechos

| Acción | Endpoint | Controlador → servicio/modelo | Tabla (hecho) | Indicadores | Vista | Prueba | Estado |
|---|---|---|---|---|---|---|---|
| Crear subasta | `PATCH/POST api/v1/subastas` | `SubastasController` → `Subasta` | `tbsubasta` (ACTIVA) | Activas | Mi dashboard vendedor, admin › Subastas | `subastas_test`, `admin_dashboard_test` | Implementada y comprobada |
| Pujar | `PATCH api/v1/subastas` (PUJAR) | `SubastasController` → `Subasta` | `tbsubastapuja` | Pujas válidas, compradores distintos, vas ganando / superadas | Ambos | `subastas_test`, `dashboard_service_test` | Implementada y comprobada |
| Cierre con ganador | cualquier petición (cierre perezoso) | `SubastaCierreService` | `tbsubasta` FINALIZADA + `tbcompra` + `tbventa` (origen `API_SUBASTAS`) | Adjudicadas, monto adjudicado, éxito, ventas por subasta | Ambos | `subastas_test`, `admin_dashboard_test` | Implementada y comprobada |
| Cierre sin pujas | ídem | `SubastaCierreService` | `tbsubasta` DESIERTA | Desiertas / sin ofertas | Ambos | ídem | Implementada y comprobada |
| Cancelar subasta | `PATCH api/v1/subastas` (CANCELAR) | `SubastasController` | `tbsubasta` CANCELADA | Canceladas (nunca venta ni desierta) | Ambos | `subastas_test`, `dashboard_service_test` | Implementada y comprobada |
| Me interesa | `POST api/v1/publicacion-interacciones` | `PublicacionInteraccion` | `tbanimalpublicacioninteraccion` (ME_INTERESA/REGISTRAR) | Interés registrado, personas distintas | Admin › Comercialización directa | `admin_dashboard_test` (evidencia) | Implementada sin verificar en navegador |
| Solicitud de compra | `POST api/v1/solicitudes-compra` | `SolicitudesCompraController` → `CompraSolicitud` | `tbcomprasolicitud` (PENDIENTE) | Solicitudes creadas | Admin | `solicitudes_compra_test` | Implementada y comprobada |
| Aceptar solicitud | `PATCH api/v1/solicitudes-compra` | ídem → `AnimalComercial` | `tbcompra` + `tbventa` por animal (con `tbcomprasolicitudid`) | Ventas por solicitud (operaciones = solicitudes distintas) | Admin | `solicitudes_compra_test`, `admin_dashboard_test` | Implementada y comprobada |
| Ofrecer flete | `api/v1/mi-ofertas` | `MiOfertasController` → `TransportistaOferta` | `tbtransportistaoferta` | Ofertas vigentes, capacidad ofertada | Admin › Transporte | `admin_dashboard_test` | Implementada y comprobada |
| Responder flete | `PATCH api/v1/solicitudes-compra` | `CompraSolicitud` | `tbcomprasolicitud.tbcomprasolicitudfleteestado` | Fletes pendientes/aceptados/rechazados | Admin | `solicitudes_compra_test` | Implementada y comprobada |
| Registrar viaje realizado | — (retirado, §4.1) | `TransportistaHistorico` (sin callers) | `tbtransportistaflete` | Viajes, cabezas, km y monto **registrados** en el histórico | Admin › Transporte | `admin_dashboard_test`, `solicitudes_compra_test` | **No instrumentada** |
| Alta de persona | `POST api/v1/registro` | `RegistroPublicoService` | `tbpersona` (sin fecha de alta) | Nuevas altas | — | — | **No instrumentada** |
| Pago efectivo | — | — | — | Dinero pagado | — | — | **No instrumentada** |
| Reseñas de transportista | — | — | `tbtransportistaresena` (sin flujo) | Confiabilidad | — | — | **Bloqueada por política de Calidad** |

## 2. Fechas de una subasta (no son intercambiables)

| Fecha | Columna | Significado |
|---|---|---|
| Publicación / apertura | `tbsubastafechainicio` | Empieza a recibir pujas. |
| Cierre programado | `tbsubastafechafin` | Última puja posible; en FINALIZADA y DESIERTA es el **cierre efectivo** (la ganadora queda fija ahí). |
| Procesamiento técnico | `tbsubastafechacierre` | Momento en que una petición ejecutó el cierre perezoso (puede ser horas después). En CANCELADA sí es el momento de la cancelación. |
| Venta registrada | `tbventafecha` | Fecha (UTC) del procesamiento del cierre o de la aceptación de la solicitud. |

Filtro de periodo: `Subasta::SQL_FECHA_HECHO` = fin programado si cerró, cancelación si se canceló, inicio si sigue
activa. Los gráficos mensuales agrupan por esa misma fecha en hora de Costa Rica. Las ventas del admin usan
`tbventafecha` (es la fecha del hecho «venta»).

## 3. Indicadores de Mi dashboard

Periodo: días de Costa Rica, inclusive, sobre la fecha del hecho. Sin base → `null` («—»), nunca un 0 inventado.

### Vendedor (`DashboardService::indicadoresVendedor`)

| Indicador | Fórmula | Excluye | Evidencia (`ids`) |
|---|---|---|---|
| Activas / adjudicadas / desiertas / canceladas | conteo por `tbsubastaestado` | — | subastas de cada estado |
| Cerradas | adjudicadas + desiertas | canceladas | ídem |
| % de éxito | adjudicadas ÷ cerradas × 100 | canceladas; sin cerradas → null | adjudicadas |
| Monto adjudicado | Σ puja ganadora de las adjudicadas | canceladas, desiertas | adjudicadas |
| Precio promedio por animal | monto ÷ adjudicadas (1 subasta = 1 animal; los lotes no se subastan) | — | adjudicadas |
| Incremento promedio | promedio de (final − base) ÷ base × 100 | base 0 | adjudicadas |
| Compradores distintos | personas distintas en `tbsubastapuja` | — | subastas con pujas |
| Tiempo de adjudicación | promedio de (fin programado − inicio) | sin fechas válidas | adjudicadas |
| Animales sin ofertas | DESIERTA | canceladas | desiertas |

### Comprador (`DashboardService::indicadoresComprador`)

| Indicador | Fórmula | Evidencia |
|---|---|---|
| Pujas activas / vas ganando / superadas | ACTIVA donde pujó; su puja ≥ / < la más alta | cada grupo |
| Ganadas / perdidas / % adjudicación | FINALIZADA con ganador = persona; ganadas ÷ finalizadas donde pujó | ganadas |
| Monto adjudicado | Σ de lo que ganó (para el comprador es un costo, no un éxito: se pinta en el tono de datos) | ganadas |
| Costo estimado | **total = Σ (puja + flete más barato con precio) solo si a ninguna le falta flete**; si falta alguno: `total = null`, `completo = false` y se informan `pujas`, `fletesConocidos`, `sinFlete`, `idsSinFlete` | activas con puja |
| Comparación de precios | promedio de (precio actual − promedio comparable) ÷ promedio; comparable = mismo tipo y raza, si no mismo tipo, si no misma especie | activas + oportunidades con comparables |
| Oportunidades | activas de otros sin su puja; con ubicación: dentro del radio; sin ubicación: provincia elegida (`modo = PROVINCIA`) | oportunidades |

Corrección del 17/11: antes el flete desconocido valía 0 y el total se mostraba como completo
(`dashboard_service_test` fija el caso: 850 000 → `null` + desglose).

## 4. Centro de inteligencia (admin)

`GET|POST api/v1/admin/dashboard` (`AdminAuthorization::require`, el administrador puede no tener Persona).
Vistas: `RESUMEN` (secciones A-F), `EVIDENCIA` (nodo + estado, paginada, máx. 50), `TRANSPORTISTAS` (paginada, con
`q`, `estado` ACTIVO|INACTIVO y `orden` de una lista cerrada), y `PERSONA` (expediente o búsqueda por nombre). Nada devuelve correo, teléfono, identificación ni ruta de documentos.

| Sección | Indicador | Fuente | Periodo |
|---|---|---|---|
| A. Red | personas únicas (una identidad aunque tenga 3 actividades), compradores/transportistas activos e inactivos, productores, fincas, vehículos, fincas por provincia | `tbpersona`, `tbcomprador`, `tbproductor`, `tbtransportista`, `tbfinca`, `tbvehiculo` | foto actual |
| B. Inventario | animales por estado; publicaciones por estado **vigente** (periodo abierto del histórico); nuevas; cambios de estado; activas por especie y provincia; con solicitudes pendientes | `tbanimal`, `tbanimalpublicacion`, `tbanimalpublicacionestadoperiodo` | alta y cambios: periodo; estado: actual |
| C. Comercialización | Me interesa (registros, personas, publicaciones); solicitudes creadas y respondidas; ventas por canal (animales, operaciones, monto) | `tbanimalpublicacioninteraccion`, `tbcomprasolicitud`, `tbventa` | fecha de cada hecho |
| D. Subastas | mismas fórmulas que el vendedor sobre todas las subastas + pujas, por especie y provincia | `tbsubasta`, `tbsubastapuja` | fecha del hecho |
| E. Transporte | ofertas por estado y capacidad; solicitudes de flete por estado; viajes registrados en el histórico (§4.1); por transportista: ofertas, capacidad, vehículos, solicitudes recibidas/aceptadas/rechazadas, viajes, cabezas, km, ingresos registrados, promedio por viaje | `tbtransportistaoferta`, `tbcomprasolicitud`, `tbtransportistaflete` | solicitudes y viajes: periodo |
| F. Control | documentos de identidad por estado y cambios de correo pendientes | `tbpersona`, `tbpersonacorreocambio` | foto actual |

Sin doble conteo: una adjudicación escribe `tbcompra` y `tbventa`; solo se suma `tbventa`. Una solicitud de un lote
escribe una venta por animal: «operaciones» cuenta solicitudes distintas y «animales» las filas.

El embudo `Publicación → Me interesa → Solicitud → Venta` **no es una cohorte**: cada etapa cuenta su propio hecho en
el periodo. No afirma que todo comprador pasó por «Me interesa».

### 4.1 Viajes: histórico sin proceso que lo alimente (DEC-VIAJE-002)

La 027 (`tbtransportistaflete.tbcomprasolicitudid` y `tbpagometodoid` opcional) y la acción `REGISTRAR_VIAJE` se
**revirtieron** el 18/11/2026 (decisión de Dilan; migración correctiva 028). Sin ese enlace, el esquema no tiene una
relación inequívoca entre una solicitud de compra y un viaje (`tbtransportistaflete` guarda transportista, finca,
vehículo y fecha, pero no la solicitud), y el método de pago del viaje es obligatorio mientras el de la solicitud es
opcional: registrar el viaje exigiría inventar un método. Por eso no hay pantalla que registre viajes.

Lo que muestra el panel admin:
- **Fletes aceptados** (respuesta del transportista). Nunca se cuentan como viajes.
- **Viajes registrados**, **cabezas registradas**, **distancia registrada** y **monto registrado por fletes**: solo si
  `tbtransportistaflete` tiene filas (histórico cargado fuera de la aplicación). El monto no se presenta como pago
  cobrado ni como ganancia; las cabezas ausentes se muestran «sin registrar», no 0.
- Sin filas: «Viajes realizados: información todavía no disponible», sin número.
- `viajesInstrumentados = false` y `instrumentacion.viajesRealizados.motivo` viajan en la respuesta.

La revisión de calidad de datos y la sección de bitácora se quitaron del panel (decisión de Dilan, 18/11): los datos de
demostración de staging se conservan. La investigación de esos registros quedó en el registro de cambios.

## 5. Política de datos nulos

- Porcentaje sin denominador → `null` → «—» o «Sin datos suficientes».
- Monto sin hechos → 0 solo cuando la suma está bien definida (no hubo ventas); promedio sin hechos → `null`.
- Hecho sin flujo que lo escriba → «Información todavía no disponible» con su motivo, nunca 0.
- Clave de agrupación vacía → `SIN_DATO`.

## 6. Pruebas de comprobación

- `Tests/dashboard_service_test.php`: fórmulas puras, canceladas ≠ ventas, costo con flete desconocido (falla con el
  código anterior), tiempo de adjudicación con procesamiento tardío.
- `Tests/admin_dashboard_test.php`: concilia cada número con SQL independiente (personas, estados, monto, pujas,
  ventas por canal sin la compra espejo, fletes ≠ viajes), periodo vacío → null, 422 por campo, evidencia paginada con
  el mismo total que el indicador, expediente sin datos sensibles, 404.
- `Tests/frontend/mi_dashboard.test.mjs` y `dashboard.test.mjs`: tokens AA en ambos temas, color por significado,
  segunda señal, evidencia conectada por ids, viajes registrados como etapa propia y aparte de los fletes aceptados, monto «registrado» (no cobrado).
- `Tests/solicitudes_compra_test.php`: `REGISTRAR_VIAJE` responde 422 y aceptar un flete no escribe `tbtransportistaflete`
  (falla con el código de 4999a1e).
- `Tests/migracion_revertir_viaje_test.php`: la 028 deja la tabla como el instalador 000, es repetible, conserva un
  viaje enlazado y no inventa un método de pago.
- `Tests/api_auth_admin_http_test.php`: el endpoint nuevo exige sesión en todos sus verbos.

## 7. Pendientes de aprobación de Calidad

| Decisión | Dato necesario | Por qué no se infiere | Riesgo | Alternativa sin cambiar el esquema |
|---|---|---|---|---|
| Fecha de alta de personas | `tbpersona` sin fecha | la bitácora no es la fuente de hechos | «nuevas altas» sin dato | aprobar columna `tbpersonafecharegistro` (4 lugares + diccionario) |
| Pago efectivo | estado de pago | adjudicar no es pagar | confundir monto adjudicado con dinero cobrado | mantener el texto «monto acordado/adjudicado» |
| Índice de confiabilidad | política de cálculo | no aprobada | puntaje arbitrario | no mostrar |
| Cierre programado de subastas | tarea periódica | hoy el cierre es perezoso | `tbventafecha` puede caer días después del fin | usar `fechaFin` para el hecho «subasta cerrada» (ya aplicado) |
