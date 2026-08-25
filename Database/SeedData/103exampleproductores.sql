USE dbmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Datos académicos ficticios. Las comprobaciones explícitas hacen el script idempotente.
START TRANSACTION;

UPDATE tbproductor SET
    tbproductorid = 1,
    tbproductoridentificaciontipo = 'CEDULA_FISICA',
    tbproductornombre = 'Maria Fernandez Solano',
    tbproductortelefono = '88881111',
    tbproductorcorreoelectronico = 'contacto.compartido@example.test',
    tbproductorestado = 1
WHERE tbproductoridentificacionnumero = '101110111';

UPDATE tbproductor SET
    tbproductorid = 2,
    tbproductoridentificaciontipo = 'CEDULA_JURIDICA',
    tbproductornombre = 'Ganaderia Valle Verde S.A.',
    tbproductortelefono = '+50622221111',
    tbproductorcorreoelectronico = 'contacto.compartido@example.test',
    tbproductorestado = 1
WHERE tbproductoridentificacionnumero = '3101111111';

INSERT INTO tbproductor (
    tbproductorid,
    tbproductoridentificacionnumero,
    tbproductoridentificaciontipo,
    tbproductornombre,
    tbproductortelefono,
    tbproductorcorreoelectronico,
    tbproductorestado
)
SELECT 1, '101110111', 'CEDULA_FISICA', 'Maria Fernandez Solano', '88881111',
       'contacto.compartido@example.test', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductor WHERE tbproductoridentificacionnumero = '101110111'
)
UNION ALL
SELECT 2, '3101111111', 'CEDULA_JURIDICA', 'Ganaderia Valle Verde S.A.', '+50622221111',
       'contacto.compartido@example.test', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductor WHERE tbproductoridentificacionnumero = '3101111111'
);

-- La ubicación vive en tbdireccion. tbproductordireccion solamente enlaza.
UPDATE tbdireccion SET
    tbdireccionprovincia = 'Alajuela',
    tbdireccioncanton = 'San Carlos',
    tbdirecciondistrito = 'Quesada',
    tbdireccionpueblo = 'Centro',
    tbdireccionsenas = 'Datos ficticios para demostracion.'
WHERE tbdireccionid = 1;

UPDATE tbdireccion SET
    tbdireccionprovincia = 'Guanacaste',
    tbdireccioncanton = 'Tilaran',
    tbdirecciondistrito = 'Tilaran',
    tbdireccionpueblo = NULL,
    tbdireccionsenas = 'Datos ficticios para demostracion.'
WHERE tbdireccionid = 2;

INSERT INTO tbdireccion (
    tbdireccionid,
    tbdireccionprovincia,
    tbdireccioncanton,
    tbdirecciondistrito,
    tbdireccionpueblo,
    tbdireccionsenas
)
SELECT 1, 'Alajuela', 'San Carlos', 'Quesada', 'Centro', 'Datos ficticios para demostracion.'
WHERE NOT EXISTS (SELECT 1 FROM tbdireccion WHERE tbdireccionid = 1)
UNION ALL
SELECT 2, 'Guanacaste', 'Tilaran', 'Tilaran', NULL, 'Datos ficticios para demostracion.'
WHERE NOT EXISTS (SELECT 1 FROM tbdireccion WHERE tbdireccionid = 2);

UPDATE tbproductordireccion SET tbdireccionid = 1 WHERE tbproductorid = 1;
UPDATE tbproductordireccion SET tbdireccionid = 2 WHERE tbproductorid = 2;

INSERT INTO tbproductordireccion (
    tbproductordireccionid,
    tbproductorid,
    tbdireccionid
)
SELECT 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM tbproductordireccion WHERE tbproductorid = 1)
UNION ALL
SELECT 2, 2, 2
WHERE NOT EXISTS (SELECT 1 FROM tbproductordireccion WHERE tbproductorid = 2);

UPDATE tbfinca SET tbfincaestado = 1
WHERE (tbproductorid = 1 AND tbfincanombre IN ('Finca El Roble', 'Finca Valle Verde'))
   OR (tbproductorid = 2 AND tbfincanombre = 'Finca Valle Verde');

INSERT INTO tbfinca (
    tbfincaid,
    tbproductorid,
    tbfincanombre,
    tbfincaestado
)
SELECT 1, 1, 'Finca El Roble', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbfinca WHERE tbproductorid = 1 AND tbfincanombre = 'Finca El Roble'
)
UNION ALL
SELECT 2, 1, 'Finca Valle Verde', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbfinca WHERE tbproductorid = 1 AND tbfincanombre = 'Finca Valle Verde'
)
UNION ALL
SELECT 3, 2, 'Finca Valle Verde', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbfinca WHERE tbproductorid = 2 AND tbfincanombre = 'Finca Valle Verde'
);

COMMIT;
