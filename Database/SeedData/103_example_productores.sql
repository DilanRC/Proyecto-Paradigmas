USE dbtindercows;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Datos académicos ficticios. La PK natural hace el script idempotente.
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

INSERT INTO tbproductoresdireccion (
    tbproductoresIdentificacionNumero,
    tbproductoresdireccionProvincia,
    tbproductoresdireccionCanton,
    tbproductoresdireccionDistrito,
    tbproductoresdireccionPueblo,
    tbproductoresdireccionSenas
) VALUES
    ('101110111', 'Alajuela', 'San Carlos', 'Quesada', 'Centro', 'Datos ficticios para demostracion.'),
    ('3101111111', 'Guanacaste', 'Tilaran', 'Tilaran', NULL, 'Datos ficticios para demostracion.')
ON DUPLICATE KEY UPDATE
    tbproductoresdireccionProvincia = VALUES(tbproductoresdireccionProvincia),
    tbproductoresdireccionCanton = VALUES(tbproductoresdireccionCanton),
    tbproductoresdireccionDistrito = VALUES(tbproductoresdireccionDistrito),
    tbproductoresdireccionPueblo = VALUES(tbproductoresdireccionPueblo),
    tbproductoresdireccionSenas = VALUES(tbproductoresdireccionSenas);

INSERT INTO tbproductoresfinca (
    tbproductoresIdentificacionNumero,
    tbproductoresfincaNombre,
    tbproductoresfincaEstado
) VALUES
    ('101110111', 'Finca El Roble', 1),
    ('101110111', 'Finca Valle Verde', 1),
    ('3101111111', 'Finca Valle Verde', 1)
ON DUPLICATE KEY UPDATE tbproductoresfincaEstado = VALUES(tbproductoresfincaEstado);

COMMIT;
