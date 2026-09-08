# DEC-DBREADY-008 — Retiro del CRUD manual de Comprador

**Estado:** SUPERADA PARCIALMENTE el 2026-09-07  
**Decisión vigente:** `DEC-CALIDAD-HISTORICOS-2026-09-07.md`

## Qué se conserva

La decisión de **no administrar Comprador como un rol manual** se mantiene. La API y la interfaz de Compradores continúan siendo de solo lectura: no se permiten altas, ediciones, bajas ni reactivaciones administrativas.

## Qué queda superado

La afirmación anterior de que `tbcomprador` debía retirarse y de que la única fuente de verdad de Comprador era `tbproductorclasificacionperiodo` ya no se usa como criterio vigente.

La reunión de Calidad del 2026-09-07 trabaja explícitamente Productor y Comprador como contextos relacionados con la misma `tbpersona` y define para ambos un histórico independiente de teléfono. Por ello:

- `tbcomprador` se conserva como contexto de negocio de Persona;
- `Application/Model/Comprador.php` consulta `tbcomprador + tbpersona`;
- `/api/compradores.php` sigue siendo de solo lectura;
- `tbproductorclasificacionperiodo` se conserva temporalmente porque todavía tiene consumidores en `dev`, pero no sustituye a `tbcomprador` ni a la identidad de Persona;
- cualquier retiro de `tbproductorclasificacionperiodo` exige migrar primero todos sus consumidores y demostrar que no se pierde el único productor de un hecho necesario.

## Histórico de teléfono

La decisión vigente incorpora:

- `tbproductorpersonatelefonohistorico`;
- `tbcompradorpersonatelefonohistorico`.

Cada fila contiene ID, relación conceptual con el contexto, número nuevo y fecha `DATETIME`; no lleva estado ni fecha fin. La generación de IDs, validaciones, relaciones, concurrencia y transacciones permanecen en PHP.

## Pendiente

La política que determina **cuándo** se crea automáticamente un contexto `tbcomprador` desde los hechos del negocio todavía debe cerrarse. Mientras tanto no se reintroduce un CRUD administrativo para fabricarlo manualmente.
