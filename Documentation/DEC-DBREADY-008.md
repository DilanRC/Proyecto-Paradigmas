# DEC-DBREADY-008 — Retiro del CRUD manual de Comprador

**Estado:** SUPERADA por DEC-28/29 el 2026-09-14  
**Decisión vigente:** `Documentation/Decisiones.md` — DEC-28 y DEC-29

## Qué queda superado

Toda la etapa de solo lectura queda revertida por DEC-28/29: Comprador vuelve a
ser un **contexto de Persona administrable** con escritura idempotente.
`Application/Model/Comprador.php` es un modelo de escritura, el
`CompradorConsultaController` fue eliminado y `/api/compradores.php` acepta
`POST` inscribir, `DELETE` desactivar y `PATCH` reactivar. El panel conserva su
vista de solo lectura (no construye cuerpos), pero la persistencia ya no pasa
por clasificación derivada del Productor.

`tbproductorclasificacionperiodo` (`tipo = COMPRADOR`) queda como registro
analítico (DEC-29): no sustituye a `tbcomprador` ni a la identidad de Persona,
y no gobierna el contexto.

## Histórico de teléfono

La decisión vigente incorpora:

- `tbproductorpersonatelefonohistorico`;
- `tbcompradorpersonatelefonohistorico`.

Cada fila contiene ID, relación conceptual con el contexto, número nuevo y fecha `DATETIME`; no lleva estado ni fecha fin. La generación de IDs, validaciones, relaciones, concurrencia y transacciones permanecen en PHP.
