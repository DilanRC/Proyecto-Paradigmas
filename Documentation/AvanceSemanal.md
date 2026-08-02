# Avance semanal - Corrección 03 del Avance 01

## Nueva aclaración docente

El profesor Cristian Brenes aclaró que el modelo no debe contener PRIMARY KEY
ni FOREIGN KEY en dirección, finca o bitácora. La única PRIMARY KEY permitida
es `tbproductoresIdentificacionNumero` dentro de `tbproductores`.

## Modelo vigente

La entrega conserva exactamente cuatro tablas:

- `tbproductores`, con la única PRIMARY KEY del modelo;
- `tbproductoresdireccion`, sin PRIMARY KEY y sin FOREIGN KEY;
- `tbproductoresfinca`, sin PRIMARY KEY compuesta y sin FOREIGN KEY;
- `tbbitacora`, sin PRIMARY KEY y sin FOREIGN KEY.

No existen participante, roles, catálogo de tipos, identificaciones separadas,
`tbfinca` ni tablas adicionales. `tbbitacoraId` sigue siendo `AUTO_INCREMENT`
mediante un índice ordinario no único.

## Comportamiento de aplicación

- POST y PUT mantienen una dirección por productor como política de aplicación.
- La sincronización de fincas usa consulta, actualización e inserción explícitas;
  no depende de `ON DUPLICATE KEY` ni de una PK compuesta.
- PUT continúa rechazando cambios de identificación.
- La desactivación es lógica y la reactivación usa la misma identificación.
- La bitácora permanece dentro de la transacción.

## Pruebas y respaldo

La aceptación exige exactamente una PRIMARY KEY global, cero FOREIGN KEY,
cuatro tablas, `utf8mb4_unicode_ci`, pruebas PHP/Node/PDF y restauraciones sin
diferencias. La evidencia se registra en
`EvidenciasPruebasAvance01Correccion03.md` y el paquete nuevo en
`Database/Backups/Avance01Correccion03/`. Las correcciones anteriores permanecen
históricas e intactas.

## Limitación consciente

MySQL acepta filas huérfanas y duplicados en las tablas sin llave. La aplicación
evita generarlos en sus flujos normales, pero SQL directo no tiene esa garantía.
