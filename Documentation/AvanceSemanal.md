# Avance semanal - Corrección 02 del Avance 01

## Problema detectado y observación docente

El respaldo anterior reveló una contradicción: los scripts solicitaban
`utf8mb4_unicode_ci`, pero Docker creaba la base antes de ejecutarlos y las
tablas terminaban con `utf8mb4_0900_ai_ci`. El profesor Cristian Brenes también
indicó que el modelo de nueve tablas excedía el alcance del avance.

## Modelo vigente

Se eliminó del modelo activo la propuesta de participante, roles, tipos de
identificación, identificaciones separadas y finca independiente. La entrega
conserva exactamente cuatro tablas:

- `tbproductores`, con `tbproductoresIdentificacionNumero` como PRIMARY KEY natural `VARCHAR`;
- `tbproductoresdireccion`, con la identificación como PRIMARY KEY y FOREIGN KEY, una dirección por productor;
- `tbproductoresfinca`, con PRIMARY KEY compuesta por identificación y nombre, varias fincas por productor;
- `tbbitacora`, con eventos CREAR, ACTUALIZAR, DESACTIVAR y REACTIVAR dentro de la transacción.

## Correcciones verificables

- `001_create_database.sql` altera la base existente a `utf8mb4_unicode_ci`.
- Compose fija el juego de caracteres y la intercalación del servidor MySQL.
- Las FK de dirección y finca cambiaron de `ON UPDATE CASCADE` a
  `ON UPDATE RESTRICT`; ambas conservan `ON DELETE RESTRICT`.
- PUT rechaza modificar la identificación.
- La desactivación es lógica y la reactivación conserva la misma PK.

## Pruebas y respaldo

La aceptación exige los gates PHP y Node, consultas de `information_schema`,
restauración completa, restauración de estructura + datos y SHA-256 de los tres
dumps. La evidencia ejecutada se registra en
`EvidenciasPruebasAvance01Correccion02.md`; el paquete nuevo vive en
`Database/Backups/Avance01Correccion02/`. Los respaldos anteriores no se modifican.

## Limitaciones pendientes

No existen autenticación ni autorización. El tipo de identificación sigue como
columna controlada. No se implementa una operación para cambiar la PK: una
identificación incorrecta se desactiva y se crea nuevamente de forma trazable.
