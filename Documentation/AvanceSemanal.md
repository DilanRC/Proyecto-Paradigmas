# Avance semanal — Corrección 01

## Resultado

El CRUD fue simplificado según la retroalimentación docente. La base contiene
cuatro tablas y la identificación del productor es la clave primaria natural.

## Cambios visibles

- Se eliminaron participante, roles, catálogo de tipos y finca independiente.
- Dirección usa relación 1:1.
- `tbproductoresfinca` guarda nombres de finca sin IDs artificiales.
- API y JavaScript usan `identificacionNumero`, no `participanteId`.
- Se mantienen transacciones, JSON, AJAX, desactivación lógica y bitácora.

## Medición

La corrección se acepta únicamente si el gate confirma exactamente cuatro
tablas, todas las pruebas pasan y el respaldo restaura sin diferencias.
