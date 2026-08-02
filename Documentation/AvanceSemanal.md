# Avance semanal - Corrección 04 del Avance 01

## Observación docente aplicada

El profesor Cristian Brenes indicó que la creación de la base no debe usar
`PRIMARY KEY`, `FOREIGN KEY` ni `CHECK`. También solicitó tablas singulares,
columnas camelCase, `tbproductorId INT NOT NULL` sin `AUTO_INCREMENT` en MySQL y
sentencias preparadas en PHP.

## Modelo vigente

La base continúa llamándose `dbtindercows` para facilitar la migración y
conserva exactamente cuatro tablas:

- `tbproductor`;
- `tbproductordireccion`;
- `tbproductorfinca`;
- `tbbitacora`.

Todas las columnas usan camelCase. Los datos de dirección y finca se asocian
lógicamente mediante `tbproductorId`, sin FK. La identificación continúa
inmutable por contrato de aplicación, pero no es una PK.

## Implementación

- PHP adquiere `GET_LOCK`, calcula `MAX(tbproductorId) + 1`, inserta y libera el
  bloqueo después del commit o rollback.
- Los modelos usan `PDO::prepare()` y parámetros enlazados. Los valores de las
  solicitudes no se concatenan al SQL.
- POST y PUT mantienen una dirección y evitan fincas duplicadas como política
  de aplicación.
- La bitácora permanece dentro de la transacción.
- Los scripts de creación no contienen restricciones PK, FK, UNIQUE ni CHECK.

## Validación y respaldo

La aceptación comprueba cuatro tablas singulares, cero restricciones, doce
índices ordinarios no únicos, `tbproductorId` sin `AUTO_INCREMENT`,
`utf8mb4_unicode_ci`, sentencias preparadas, pruebas PHP/Node/PDF y restauración
sin diferencias. La evidencia corresponde a
`EvidenciasPruebasAvance01Correccion04.md` y el respaldo a
`Database/Backups/Avance01Correccion04/`.

## Limitación consciente

SQL directo puede insertar IDs o identificaciones duplicadas, asociaciones
huérfanas y valores fuera del dominio. Esa ausencia de seguridad estructural es
intencional según la indicación docente. Los flujos PHP aplican las reglas del
CRUD, pero no convierten esas reglas en restricciones de MySQL.
