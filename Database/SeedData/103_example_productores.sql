USE dbtindercows;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Datos académicos ficticios. Las comprobaciones explícitas hacen el script idempotente.
START TRANSACTION;

UPDATE tbproductor SET
    tbproductorId = 1,
    tbproductorIdentificacionTipo = 'CEDULA_FISICA',
    tbproductorNombre = 'Maria Fernandez Solano',
    tbproductorTelefono = '88881111',
    tbproductorCorreoElectronico = 'contacto.compartido@example.test',
    tbproductorEstado = 1
WHERE tbproductorIdentificacionNumero = '101110111';

UPDATE tbproductor SET
    tbproductorId = 2,
    tbproductorIdentificacionTipo = 'CEDULA_JURIDICA',
    tbproductorNombre = 'Ganaderia Valle Verde S.A.',
    tbproductorTelefono = '+50622221111',
    tbproductorCorreoElectronico = 'contacto.compartido@example.test',
    tbproductorEstado = 1
WHERE tbproductorIdentificacionNumero = '3101111111';

INSERT INTO tbproductor (
    tbproductorId,
    tbproductorIdentificacionNumero,
    tbproductorIdentificacionTipo,
    tbproductorNombre,
    tbproductorTelefono,
    tbproductorCorreoElectronico,
    tbproductorEstado
)
SELECT 1, '101110111', 'CEDULA_FISICA', 'Maria Fernandez Solano', '88881111',
       'contacto.compartido@example.test', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductor WHERE tbproductorIdentificacionNumero = '101110111'
)
UNION ALL
SELECT 2, '3101111111', 'CEDULA_JURIDICA', 'Ganaderia Valle Verde S.A.', '+50622221111',
       'contacto.compartido@example.test', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductor WHERE tbproductorIdentificacionNumero = '3101111111'
);

UPDATE tbproductordireccion SET
    tbproductordireccionProvincia = 'Alajuela',
    tbproductordireccionCanton = 'San Carlos',
    tbproductordireccionDistrito = 'Quesada',
    tbproductordireccionPueblo = 'Centro',
    tbproductordireccionSenas = 'Datos ficticios para demostracion.'
WHERE tbproductorId = 1;

UPDATE tbproductordireccion SET
    tbproductordireccionProvincia = 'Guanacaste',
    tbproductordireccionCanton = 'Tilaran',
    tbproductordireccionDistrito = 'Tilaran',
    tbproductordireccionPueblo = NULL,
    tbproductordireccionSenas = 'Datos ficticios para demostracion.'
WHERE tbproductorId = 2;

INSERT INTO tbproductordireccion (
    tbproductordireccionId,
    tbproductorId,
    tbproductordireccionProvincia,
    tbproductordireccionCanton,
    tbproductordireccionDistrito,
    tbproductordireccionPueblo,
    tbproductordireccionSenas
)
SELECT 1, 1, 'Alajuela', 'San Carlos', 'Quesada', 'Centro', 'Datos ficticios para demostracion.'
WHERE NOT EXISTS (SELECT 1 FROM tbproductordireccion WHERE tbproductorId = 1)
UNION ALL
SELECT 2, 2, 'Guanacaste', 'Tilaran', 'Tilaran', NULL, 'Datos ficticios para demostracion.'
WHERE NOT EXISTS (SELECT 1 FROM tbproductordireccion WHERE tbproductorId = 2);

UPDATE tbproductorfinca SET tbproductorfincaEstado = 1
WHERE (tbproductorId = 1 AND tbproductorfincaNombre IN ('Finca El Roble', 'Finca Valle Verde'))
   OR (tbproductorId = 2 AND tbproductorfincaNombre = 'Finca Valle Verde');

INSERT INTO tbproductorfinca (
    tbproductorfincaId,
    tbproductorId,
    tbproductorfincaNombre,
    tbproductorfincaEstado
)
SELECT 1, 1, 'Finca El Roble', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductorfinca WHERE tbproductorId = 1 AND tbproductorfincaNombre = 'Finca El Roble'
)
UNION ALL
SELECT 2, 1, 'Finca Valle Verde', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductorfinca WHERE tbproductorId = 1 AND tbproductorfincaNombre = 'Finca Valle Verde'
)
UNION ALL
SELECT 3, 2, 'Finca Valle Verde', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductorfinca WHERE tbproductorId = 2 AND tbproductorfincaNombre = 'Finca Valle Verde'
);

COMMIT;
