SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dbtindercows;

-- Datos academicos ficticios. El script es idempotente por identificacion normalizada.
START TRANSACTION;

INSERT INTO tbparticipante (
    tbparticipanteNombre,
    tbparticipanteTelefono,
    tbparticipanteCorreoElectronico,
    tbparticipanteEstado
)
SELECT 'Maria Fernandez Solano', '88881111', 'contacto.compartido@example.test', 1
WHERE NOT EXISTS (
    SELECT 1
    FROM tbparticipanteidentificacion i
    INNER JOIN tbidentificaciontipo t
        ON t.tbidentificaciontipoId = i.tbidentificaciontipoId
    WHERE t.tbidentificaciontipoCodigo = 'CEDULA_FISICA'
      AND i.tbparticipanteidentificacionNumeroNormalizado = '101110111'
);

SET @mariaId := COALESCE(
    (
        SELECT i.tbparticipanteId
        FROM tbparticipanteidentificacion i
        INNER JOIN tbidentificaciontipo t
            ON t.tbidentificaciontipoId = i.tbidentificaciontipoId
        WHERE t.tbidentificaciontipoCodigo = 'CEDULA_FISICA'
          AND i.tbparticipanteidentificacionNumeroNormalizado = '101110111'
        LIMIT 1
    ),
    LAST_INSERT_ID()
);

INSERT INTO tbparticipanteidentificacion (
    tbparticipanteId,
    tbidentificaciontipoId,
    tbparticipanteidentificacionNumero,
    tbparticipanteidentificacionNumeroNormalizado,
    tbparticipanteidentificacionEsPrincipal,
    tbparticipanteidentificacionEstado
)
SELECT @mariaId, t.tbidentificaciontipoId, '1-0111-0111', '101110111', 1, 1
FROM tbidentificaciontipo t
WHERE t.tbidentificaciontipoCodigo = 'CEDULA_FISICA'
  AND NOT EXISTS (
      SELECT 1
      FROM tbparticipanteidentificacion i
      WHERE i.tbidentificaciontipoId = t.tbidentificaciontipoId
        AND i.tbparticipanteidentificacionNumeroNormalizado = '101110111'
  );

INSERT INTO tbparticipantedireccion (
    tbparticipanteId,
    tbparticipantedireccionProvincia,
    tbparticipantedireccionCanton,
    tbparticipantedireccionDistrito,
    tbparticipantedireccionPueblo,
    tbparticipantedireccionSenas,
    tbparticipantedireccionEsPrincipal,
    tbparticipantedireccionEstado
)
SELECT @mariaId, 'Alajuela', 'San Carlos', 'Quesada', 'Centro', 'Datos ficticios para demostracion.', 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbparticipantedireccion d
    WHERE d.tbparticipanteId = @mariaId
      AND d.tbparticipantedireccionEsPrincipal = 1
      AND d.tbparticipantedireccionEstado = 1
);

INSERT INTO tbparticipanterol (tbparticipanteId, tbrolId, tbparticipanterolEstado)
SELECT @mariaId, tbrolId, 1 FROM tbrol WHERE tbrolCodigo IN ('PRODUCTOR', 'COMPRADOR')
ON DUPLICATE KEY UPDATE tbparticipanterolEstado = VALUES(tbparticipanterolEstado);

INSERT INTO tbparticipante (
    tbparticipanteNombre,
    tbparticipanteTelefono,
    tbparticipanteCorreoElectronico,
    tbparticipanteEstado
)
SELECT 'Ganaderia Valle Verde S.A.', '+50622221111', 'contacto.compartido@example.test', 1
WHERE NOT EXISTS (
    SELECT 1
    FROM tbparticipanteidentificacion i
    INNER JOIN tbidentificaciontipo t
        ON t.tbidentificaciontipoId = i.tbidentificaciontipoId
    WHERE t.tbidentificaciontipoCodigo = 'CEDULA_JURIDICA'
      AND i.tbparticipanteidentificacionNumeroNormalizado = '3101111111'
);

SET @valleVerdeId := COALESCE(
    (
        SELECT i.tbparticipanteId
        FROM tbparticipanteidentificacion i
        INNER JOIN tbidentificaciontipo t
            ON t.tbidentificaciontipoId = i.tbidentificaciontipoId
        WHERE t.tbidentificaciontipoCodigo = 'CEDULA_JURIDICA'
          AND i.tbparticipanteidentificacionNumeroNormalizado = '3101111111'
        LIMIT 1
    ),
    LAST_INSERT_ID()
);

INSERT INTO tbparticipanteidentificacion (
    tbparticipanteId,
    tbidentificaciontipoId,
    tbparticipanteidentificacionNumero,
    tbparticipanteidentificacionNumeroNormalizado,
    tbparticipanteidentificacionEsPrincipal,
    tbparticipanteidentificacionEstado
)
SELECT @valleVerdeId, t.tbidentificaciontipoId, '3-101-111111', '3101111111', 1, 1
FROM tbidentificaciontipo t
WHERE t.tbidentificaciontipoCodigo = 'CEDULA_JURIDICA'
  AND NOT EXISTS (
      SELECT 1
      FROM tbparticipanteidentificacion i
      WHERE i.tbidentificaciontipoId = t.tbidentificaciontipoId
        AND i.tbparticipanteidentificacionNumeroNormalizado = '3101111111'
  );

INSERT INTO tbparticipantedireccion (
    tbparticipanteId,
    tbparticipantedireccionProvincia,
    tbparticipantedireccionCanton,
    tbparticipantedireccionDistrito,
    tbparticipantedireccionPueblo,
    tbparticipantedireccionSenas,
    tbparticipantedireccionEsPrincipal,
    tbparticipantedireccionEstado
)
SELECT @valleVerdeId, 'Guanacaste', 'Tilaran', 'Tilaran', NULL, 'Datos ficticios para demostracion.', 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbparticipantedireccion d
    WHERE d.tbparticipanteId = @valleVerdeId
      AND d.tbparticipantedireccionEsPrincipal = 1
      AND d.tbparticipantedireccionEstado = 1
);

INSERT INTO tbparticipanterol (tbparticipanteId, tbrolId, tbparticipanterolEstado)
SELECT @valleVerdeId, tbrolId, 1 FROM tbrol WHERE tbrolCodigo = 'PRODUCTOR'
ON DUPLICATE KEY UPDATE tbparticipanterolEstado = VALUES(tbparticipanterolEstado);

INSERT INTO tbfinca (tbfincaNombre, tbfincaEstado)
SELECT 'Finca El Roble', 1
WHERE NOT EXISTS (SELECT 1 FROM tbfinca WHERE tbfincaNombre = 'Finca El Roble');
SET @fincaRobleId := (SELECT MIN(tbfincaId) FROM tbfinca WHERE tbfincaNombre = 'Finca El Roble');

INSERT INTO tbfinca (tbfincaNombre, tbfincaEstado)
SELECT 'Finca Valle Verde', 1
WHERE NOT EXISTS (SELECT 1 FROM tbfinca WHERE tbfincaNombre = 'Finca Valle Verde');
SET @fincaValleId := (SELECT MIN(tbfincaId) FROM tbfinca WHERE tbfincaNombre = 'Finca Valle Verde');

INSERT INTO tbproductorfinca (tbparticipanteId, tbfincaId, tbproductorfincaEstado) VALUES
    (@mariaId, @fincaRobleId, 1),
    (@mariaId, @fincaValleId, 1),
    (@valleVerdeId, @fincaValleId, 1)
ON DUPLICATE KEY UPDATE tbproductorfincaEstado = VALUES(tbproductorfincaEstado);

COMMIT;
