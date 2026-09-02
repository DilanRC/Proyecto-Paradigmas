USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Datos académicos ficticios. Las comprobaciones explícitas hacen el script idempotente.
START TRANSACTION;

UPDATE tbpersona SET tbpersonaidentificaciontipo = 'CEDULA_FISICA',
    tbpersonanombre = 'Maria Fernandez Solano', tbpersonatelefono = '88881111',
    tbpersonacorreoelectronico = 'contacto.compartido@example.test', tbpersonaestado = 1
WHERE tbpersonaidentificacionnumero = '101110111';

UPDATE tbpersona SET tbpersonaidentificaciontipo = 'CEDULA_JURIDICA',
    tbpersonanombre = 'Ganaderia Valle Verde S.A.', tbpersonatelefono = '+50622221111',
    tbpersonacorreoelectronico = 'contacto.compartido@example.test', tbpersonaestado = 1
WHERE tbpersonaidentificacionnumero = '3101111111';

INSERT INTO tbpersona (tbpersonaid, tbpersonaidentificacionnumero,
    tbpersonaidentificaciontipo, tbpersonanombre, tbpersonatelefono,
    tbpersonacorreoelectronico, tbpersonaestado)
SELECT 1, '101110111', 'CEDULA_FISICA', 'Maria Fernandez Solano', '88881111',
       'contacto.compartido@example.test', 1
WHERE NOT EXISTS (SELECT 1 FROM tbpersona WHERE tbpersonaidentificacionnumero = '101110111')
UNION ALL
SELECT 2, '3101111111', 'CEDULA_JURIDICA', 'Ganaderia Valle Verde S.A.', '+50622221111',
       'contacto.compartido@example.test', 1
WHERE NOT EXISTS (SELECT 1 FROM tbpersona WHERE tbpersonaidentificacionnumero = '3101111111');

-- El estado del productor vive en tbproductorestadoperiodo, no en tbproductor.
INSERT INTO tbproductor (
    tbproductorid,
    tbpersonaid
)
SELECT 1, 1 WHERE NOT EXISTS (SELECT 1 FROM tbproductor WHERE tbpersonaid = 1)
UNION ALL
SELECT 2, 2 WHERE NOT EXISTS (SELECT 1 FROM tbproductor WHERE tbpersonaid = 2);

-- Periodos iniciales: cada productor comienza como ACTIVO (estado 1).
-- INSERTs individuales porque MySQL aplica WHERE NOT EXISTS solo al
-- último SELECT de un UNION ALL.
INSERT INTO tbproductorestadoperiodo
    (tbproductorestadoperiodoid, tbproductorid, tbproductorestadoperiodoestado,
     tbproductorestadoperiodofechainicio, tbproductorestadoperiodofechafin,
     tbproductorestadoperiodomotivo)
SELECT 1, 1, 1, NOW(), NULL, 'Alta del productor'
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductorestadoperiodo WHERE tbproductorid = 1
);

INSERT INTO tbproductorestadoperiodo
    (tbproductorestadoperiodoid, tbproductorid, tbproductorestadoperiodoestado,
     tbproductorestadoperiodofechainicio, tbproductorestadoperiodofechafin,
     tbproductorestadoperiodomotivo)
SELECT 2, 2, 1, NOW(), NULL, 'Alta del productor'
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductorestadoperiodo WHERE tbproductorid = 2
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

-- fechainicio se fija aquí porque tbproductordireccion ya es histórica
-- (tramo 6 del remodelado EIF400): un enlace nunca queda abierto sin fecha.
INSERT INTO tbproductordireccion (
    tbproductordireccionid,
    tbproductorid,
    tbdireccionid,
    tbproductordireccionfechainicio
)
SELECT 1, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM tbproductordireccion WHERE tbproductorid = 1)
UNION ALL
SELECT 2, 2, 2, NOW()
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
