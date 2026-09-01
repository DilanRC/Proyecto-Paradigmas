# DEC-DBREADY-008 — Retiro del CRUD de Comprador y consulta de solo lectura

**Estado:** APROBADA E IMPLEMENTADA  
**Alcance:** paso (d) del retiro de `tbcomprador`  
**Paso siguiente de la deuda legacy:** (e), retirar la tabla únicamente después de validar la migración real y con respaldo previo.  
**Supera:** cualquier texto previo de DEC-DBREADY-005/007 que todavía describa el paso (d) como pendiente o presente al Productor como alias de Vendedor.

## Conclusión

Comprador no se administra como registro ni como capacidad manual. Es una
clasificación histórica del `Productor` y su única fuente de verdad es un
periodo abierto `COMPRADOR` en `tbproductorclasificacionperiodo`.

El paso (d) elimina el CRUD manual y conserva `/compradores.php` como una vista
de **solo lectura**. La pantalla permite observar quién está clasificado, desde
cuándo, por qué origen/motivo y cuál es la disponibilidad global actual de la
Persona. No permite crear, editar, desactivar, reactivar ni marcar la
clasificación.

## Estructura

Se retiran del modelo vigente:

- `Application/Model/Comprador.php`;
- `Application/Controller/CompradorController.php`;
- el formulario/modal y las acciones de alta, edición, baja y reactivación del
  panel de Compradores;
- cualquier payload HTTP de escritura generado por `Public/js/compradores.js`.

Se conservan:

- `Public/compradores.php`, como ruta de la vista informativa;
- `Application/View/compradores/index.php`, como panel de solo lectura;
- `Public/api/compradores.php`, únicamente para `GET` y `OPTIONS`;
- `Application/Controller/CompradorConsultaController.php`, únicamente para
  consulta;
- `Application/Model/ProductorClasificacionPeriodo.php`, fuente de la lectura;
- `Application/Service/CompradorClasificacionService.php`, mecanismo de escritura
  de periodos que utilizará el proceso autorizado que corresponda (por ejemplo
  T10), **no** una API administrativa de Comprador;
- `tbcomprador`, físicamente, solo como estructura legacy necesaria para
  backfill/auditoría hasta el paso (e).

## Mecanismo de consulta

```text
GET /api/compradores.php
        ↓
CompradorConsultaController
        ↓
ProductorClasificacionPeriodo::listarClasificados(COMPRADOR)
        ↓
tbproductorclasificacionperiodo (fechafin IS NULL)
        ↓
tbproductor
        ↓
tbpersona
```

El listado devuelve el Productor, identificación/contacto compartidos,
`clasificadoDesde`, motivo/origen y `personaEstado`.

Una consulta exacta responde:

- `200` si existe un periodo `COMPRADOR` abierto;
- `404` si no existe periodo abierto;
- `422` para parámetros inválidos.

`POST`, `PUT`, `PATCH` y `DELETE` responden `405`. El endpoint los rechaza antes
de abrir una conexión de base de datos, por lo que una caída de MySQL no puede
hacer aparecer una escritura prohibida como un `500`.

## Política

La clasificación es **derivada, nunca administrativa**. No se agrega checkbox,
botón, endpoint ni acción en Productores para marcar manualmente `COMPRADOR` o
`VENDEDOR`.

`Productor` tampoco es alias de `Vendedor`. Ambos términos se separan:

```text
Productor
├── periodo COMPRADOR
└── periodo VENDEDOR
```

Los dos periodos pueden estar abiertos simultáneamente. No existe `tbvendedor`.

## Persona inactiva

`tbpersonaestado = 0` representa indisponibilidad global de la Persona y **no**
cierra automáticamente el periodo `COMPRADOR`.

Por tanto:

```text
periodo COMPRADOR abierto + Persona activa   → Clasificado actualmente
periodo COMPRADOR abierto + Persona inactiva → Clasificado, persona inactiva
periodo COMPRADOR cerrado/ausente             → Sin clasificación vigente
```

La vista mantiene visible la clasificación abierta de una Persona inactiva y
muestra su disponibilidad por separado. Esto evita confundir un cambio global de
Persona con la pérdida histórica de una clasificación comercial.

## Origen y transformación

Los periodos existentes pueden provenir del backfill del CRUD legacy. El motivo
`MIGRACION_TBCOMPRADOR_LEGACY` se presenta como una migración del registro
anterior. Los motivos `ALTA_CRUD_COMPRADOR` y
`REACTIVACION_CRUD_COMPRADOR`, si existen por el intervalo previo al retiro,
se presentan como evidencia histórica de ese mecanismo ya retirado.

No se reescriben esos motivos y no se inventa pasado.

## Límite hasta T10

T10, que derivará `COMPRADOR`/`VENDEDOR` del comportamiento, sigue pendiente de
política: criterios, pesos, pérdida y reactivación automática no se inventan en
este tramo.

Consecuencia deliberada entre (d) y T10: el sistema **solo conserva y muestra**
clasificaciones ya abiertas/migradas. No existe un flujo administrativo para
crear nuevas clasificaciones. Esto es preferible a reintroducir el mismo rol
manual que Calidad pidió eliminar.

## Concurrencia, fallos y recuperación

El panel de consulta no escribe. Las escrituras de periodos continúan bajo los
locks y transacciones de `ProductorClasificacionPeriodo` y
`CompradorClasificacionService`.

El backfill previo conserva su precheck, revalidación por fila y manejo de
cambios concurrentes. `tbcomprador` no se elimina en este paso, de modo que el
rollback operativo previo al paso (e) conserva la evidencia legacy necesaria.

## Comprobación

Backend y contrato read-only:

```bash
docker compose exec -T app php Tests/naming_gate.php
docker compose exec -T app php Tests/comprador_retiro_gate.php
docker compose exec -T app php Tests/comprador_clasificacion_test.php
docker compose exec -T app php Tests/comprador_backfill_test.php
docker compose exec -T app php Tests/comprador_consulta_test.php
docker compose exec -T app php Tests/persona_capabilities_test.php
docker compose exec -T app php Tests/backend_db_ready_test.php
docker compose exec -T app php Tests/diagnostico_test.php
```

Frontend, usando Node del host si funciona o un contenedor desechable:

```bash
docker run --rm -v "$PWD":/app -w /app node:22-alpine sh -lc '
  node Tests/frontend_contract_test.js &&
  node Tests/frontend_capacidades_eval.js &&
  node --test Tests/frontend/*.test.mjs
'
```

La regresión debe demostrar como mínimo:

1. sin periodo `COMPRADOR` → no aparece;
2. periodo abierto → aparece con fecha y motivo;
3. periodo cerrado → deja de aparecer, sin borrar historia;
4. `COMPRADOR` + `VENDEDOR` abiertos → sigue apareciendo como Comprador;
5. cerrar `VENDEDOR` no altera `COMPRADOR`;
6. Persona inactiva → clasificación permanece abierta y visible como no
   disponible;
7. no existe fila `tbcomprador` para el Productor de prueba y aun así la
   consulta funciona;
8. cualquier método HTTP de escritura manual de Comprador recibe `405`.

## Pendientes explícitos

- **T10:** política y algoritmo de clasificación derivada desde comportamiento.
- **Paso (e):** `DROP TABLE tbcomprador` mediante migración propia, solo después
  de ejecutar/verificar backfill sobre los datos compartidos/reales y tomar un
  respaldo recuperable.
