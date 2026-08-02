USE dbtindercows;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Datos académicos ficticios. Las comprobaciones explícitas hacen el script idempotente.
START TRANSACTION;

INSERT INTO tbproductores (
    tbproductoresIdentificacionNumero,
    tbproductoresIdentificacionTipo,
    tbproductoresNombre,
    tbproductoresTelefono,
    tbproductoresCorreoElectronico,
    tbproductoresEstado
) VALUES
    ('101110111', 'CEDULA_FISICA', 'Maria Fernandez Solano', '88881111', 'contacto.compartido@example.test', 1),
    ('3101111111', 'CEDULA_JURIDICA', 'Ganaderia Valle Verde S.A.', '+50622221111', 'contacto.compartido@example.test', 1)
ON DUPLICATE KEY UPDATE
    tbproductoresNombre = VALUES(tbproductoresNombre),
    tbproductoresTelefono = VALUES(tbproductoresTelefono),
    tbproductoresCorreoElectronico = VALUES(tbproductoresCorreoElectronico),
    tbproductoresEstado = VALUES(tbproductoresEstado);

UPDATE tbproductoresdireccion SET
    tbproductoresdireccionProvincia = 'Alajuela',
    tbproductoresdireccionCanton = 'San Carlos',
    tbproductoresdireccionDistrito = 'Quesada',
    tbproductoresdireccionPueblo = 'Centro',
    tbproductoresdireccionSenas = 'Datos ficticios para demostracion.'
WHERE tbproductoresIdentificacionNumero = '101110111';

UPDATE tbproductoresdireccion SET
    tbproductoresdireccionProvincia = 'Guanacaste',
    tbproductoresdireccionCanton = 'Tilaran',
    tbproductoresdireccionDistrito = 'Tilaran',
    tbproductoresdireccionPueblo = NULL,
    tbproductoresdireccionSenas = 'Datos ficticios para demostracion.'
WHERE tbproductoresIdentificacionNumero = '3101111111';

INSERT INTO tbproductoresdireccion (
    tbproductoresIdentificacionNumero,
    tbproductoresdireccionProvincia,
    tbproductoresdireccionCanton,
    tbproductoresdireccionDistrito,
    tbproductoresdireccionPueblo,
    tbproductoresdireccionSenas
) SELECT '101110111', 'Alajuela', 'San Carlos', 'Quesada', 'Centro', 'Datos ficticios para demostracion.'
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductoresdireccion WHERE tbproductoresIdentificacionNumero = '101110111'
)
UNION ALL
SELECT '3101111111', 'Guanacaste', 'Tilaran', 'Tilaran', NULL, 'Datos ficticios para demostracion.'
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductoresdireccion WHERE tbproductoresIdentificacionNumero = '3101111111'
);

UPDATE tbproductoresfinca SET tbproductoresfincaEstado = 1
WHERE (tbproductoresIdentificacionNumero = '101110111' AND tbproductoresfincaNombre IN ('Finca El Roble', 'Finca Valle Verde'))
   OR (tbproductoresIdentificacionNumero = '3101111111' AND tbproductoresfincaNombre = 'Finca Valle Verde');

INSERT INTO tbproductoresfinca (
    tbproductoresIdentificacionNumero,
    tbproductoresfincaNombre,
    tbproductoresfincaEstado
) SELECT '101110111', 'Finca El Roble', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductoresfinca
    WHERE tbproductoresIdentificacionNumero = '101110111' AND tbproductoresfincaNombre = 'Finca El Roble'
)
UNION ALL
SELECT '101110111', 'Finca Valle Verde', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductoresfinca
    WHERE tbproductoresIdentificacionNumero = '101110111' AND tbproductoresfincaNombre = 'Finca Valle Verde'
)
UNION ALL
SELECT '3101111111', 'Finca Valle Verde', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductoresfinca
    WHERE tbproductoresIdentificacionNumero = '3101111111' AND tbproductoresfincaNombre = 'Finca Valle Verde'
);

COMMIT;
