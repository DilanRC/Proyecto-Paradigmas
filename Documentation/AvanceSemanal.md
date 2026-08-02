# Avance semanal: refactor del CRUD de Productor

## Objetivo medible

El avance busca que una computadora con Docker pueda crear `dbtindercows` desde
cero y operar el CRUD de Productor por AJAX/JSON sin duplicar la identidad de
una persona, sin estados parciales y sin borrado físico. La evidencia observable
será: servicios saludables, nueve tablas con restricciones, respuestas JSON,
operaciones sin navegación completa, pruebas verdes, eventos de bitácora y un
respaldo restaurado con conteos equivalentes.

## Alcance implementado en el diseño

- Participante común con roles `PRODUCTOR`, `COMPRADOR` y catálogo futuro
  `ADMINISTRADOR`.
- Tipo de identificación como catálogo extensible.
- Identificación visible y normalizada con reserva aun en inactivos.
- Un teléfono, un correo de contacto no único y una dirección principal.
- Estructura 1:N para direcciones y protección de principal activa única.
- Finca independiente y asociación N:M por IDs existentes.
- Desactivación y reactivación lógica del mismo participante.
- Bitácora transaccional con actor `NO_AUTENTICADO`.
- API JSON y vista AJAX.
- Docker, scripts ordenados, respaldo, restauración y documentación.

## Separación entre estructura, mecanismo y política

| Dimensión | Decisión del avance |
|---|---|
| Estructura | `tbparticipante`, catálogos, tablas de relación, dirección, finca y bitácora. |
| Mecanismo | MVC, PDO preparado, transacciones, `fetch`, JSON, Docker y restricciones MySQL. |
| Política | Productor es un rol; identidad reservada; una principal activa; desactivación lógica; asociación no propiedad. |

## Trazabilidad

| Resultado esperado | Evidencia a registrar |
|---|---|
| Instalación reproducible | `docker compose ps`, tablas y catálogos. |
| Sin identidad duplicada | rechazo `409` y UK en MySQL. |
| Sin participante parcial | prueba de `ROLLBACK` por falla intermedia. |
| Una dirección principal | prueba positiva y rechazo de segunda principal. |
| N:M de fincas | productor con dos fincas y finca con dos productores. |
| Sin recarga completa | registro de red/navegación en navegador. |
| Historial veraz | bitácora dentro de transacción y ausencia tras rollback. |
| Entrega restaurable | SHA-256, restauración temporal y comparación. |

Los resultados reales todavía deben escribirse en
`Documentation/EvidenciasPruebas.md`. Este documento no declara pruebas
aprobadas sin su salida.

## Archivos principales

- Esquema y semillas: `Database/SqlScripts/` y `Database/SeedData/`.
- Controlador: `Application/Controller/ProductorController.php`.
- Modelos: `Application/Model/*.php`.
- Vista: `Application/View/productores/index.php`.
- API: `Public/api/productores.php`.
- AJAX: `Public/js/productores.js`.
- Respaldo/restauración: `Tools/`.
- Pruebas: `Tests/`.
- Decisiones y diagramas: `Documentation/`.

## Aspectos fuera de alcance o pendientes

No se implementan pujas, subastas, ganado, adjudicaciones, autenticación,
autorización, cálculo real de transporte, catálogo territorial completo ni
atributos no confirmados de finca. Continúan abiertas las cinco preguntas
enumeradas en `Documentation/Decisiones.md`.

## Estado de verificación

No se inventan resultados. Complete esta tabla únicamente después de ejecutar
cada comprobación sobre el commit candidato.

| Comprobación | Estado | Referencia de evidencia |
|---|---|---|
| Instalación limpia | PENDIENTE | `EvidenciasPruebas.md` |
| Gate tests | PENDIENTE | `EvidenciasPruebas.md` |
| Evals periódicas | PENDIENTE | `EvidenciasPruebas.md` |
| API e interfaz | PENDIENTE | `EvidenciasPruebas.md` |
| Rollback y bitácora | PENDIENTE | `EvidenciasPruebas.md` |
| Respaldo y restauración | PENDIENTE | `EvidenciasPruebas.md` |

