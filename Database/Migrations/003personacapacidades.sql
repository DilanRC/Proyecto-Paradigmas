USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Ejecutar una sola vez tras un respaldo verificado. El preflight no crea
-- objetos ni altera tablas: todo conflicto falla antes del primer DDL.
SELECT GET_LOCK('bdmercadoganadero:migrar-persona-capacidades', 30) INTO @migracion_lock;
SET @preflight_sql := IF(@migracion_lock = 1, 'DO 0',
    'SELECT * FROM MIGRACION_ABORTADA_BLOQUEO_NO_ADQUIRIDO');
PREPARE preflight FROM @preflight_sql;
EXECUTE preflight;
DEALLOCATE PREPARE preflight;

SELECT COUNT(*) INTO @conflictos_duplicados FROM (
    SELECT tbproductoridentificacionnumero identificacion FROM tbproductor
      GROUP BY tbproductoridentificacionnumero HAVING COUNT(*) > 1
    UNION ALL
    SELECT tbcompradoridentificacionnumero FROM tbcomprador
      GROUP BY tbcompradoridentificacionnumero HAVING COUNT(*) > 1
    UNION ALL
    SELECT tbtransportistaidentificacionnumero FROM tbtransportista
      GROUP BY tbtransportistaidentificacionnumero HAVING COUNT(*) > 1
) conflictos;
-- La tabla inexistente detiene el lote sin DDL. PREPARE solo vive en la sesión.
SET @preflight_sql := IF(@conflictos_duplicados = 0, 'DO 0',
    'SELECT * FROM MIGRACION_ABORTADA_CAPACIDAD_DUPLICADA');
PREPARE preflight FROM @preflight_sql;
EXECUTE preflight;
DEALLOCATE PREPARE preflight;

SELECT COUNT(*) INTO @conflictos_personales FROM (
    SELECT identificacion FROM (
        SELECT tbproductoridentificacionnumero identificacion,
               tbproductoridentificaciontipo tipo, tbproductornombre nombre,
               tbproductortelefono telefono, tbproductorcorreoelectronico correo FROM tbproductor
        UNION ALL
        SELECT tbcompradoridentificacionnumero, tbcompradoridentificaciontipo,
               tbcompradornombre, tbcompradortelefono, tbcompradorcorreoelectronico FROM tbcomprador
        UNION ALL
        SELECT tbtransportistaidentificacionnumero, tbtransportistaidentificaciontipo,
               tbtransportistanombre, tbtransportistatelefono,
               tbtransportistacorreoelectronico FROM tbtransportista
    ) identidades GROUP BY identificacion
    HAVING COUNT(DISTINCT CONCAT_WS(CHAR(31), tipo, nombre, telefono, correo)) > 1
) conflictos;
SET @preflight_sql := IF(@conflictos_personales = 0, 'DO 0',
    'SELECT * FROM MIGRACION_ABORTADA_DATOS_PERSONALES_INCOMPATIBLES');
PREPARE preflight FROM @preflight_sql;
EXECUTE preflight;
DEALLOCATE PREPARE preflight;

SELECT (SELECT COUNT(*) FROM tbproductor) + (SELECT COUNT(*) FROM tbcomprador)
     + (SELECT COUNT(*) FROM tbtransportista) INTO @perfiles_antes;

CREATE TABLE tbpersona (
    tbpersonaid INT NOT NULL,
    tbpersonaidentificacionnumero VARCHAR(250) NOT NULL,
    tbpersonaidentificaciontipo VARCHAR(40) NOT NULL,
    tbpersonanombre VARCHAR(150) NOT NULL,
    tbpersonatelefono VARCHAR(20) NOT NULL,
    tbpersonacorreoelectronico VARCHAR(150) NOT NULL,
    tbpersonaestado TINYINT(1) NOT NULL
) ENGINE=InnoDB;
ALTER TABLE tbproductor ADD COLUMN tbpersonaid INT NULL AFTER tbproductorid;
ALTER TABLE tbcomprador ADD COLUMN tbpersonaid INT NULL AFTER tbcompradorid;
ALTER TABLE tbtransportista ADD COLUMN tbpersonaid INT NULL AFTER tbtransportistaid;

SET @persona_id := 0;
INSERT INTO tbpersona
SELECT (@persona_id := @persona_id + 1), identificacion, MIN(tipo), MIN(nombre),
       MIN(telefono), MIN(correo), 1
FROM (
    SELECT tbproductoridentificacionnumero identificacion, tbproductoridentificaciontipo tipo,
           tbproductornombre nombre, tbproductortelefono telefono,
           tbproductorcorreoelectronico correo FROM tbproductor
    UNION ALL
    SELECT tbcompradoridentificacionnumero, tbcompradoridentificaciontipo, tbcompradornombre,
           tbcompradortelefono, tbcompradorcorreoelectronico FROM tbcomprador
    UNION ALL
    SELECT tbtransportistaidentificacionnumero, tbtransportistaidentificaciontipo,
           tbtransportistanombre, tbtransportistatelefono,
           tbtransportistacorreoelectronico FROM tbtransportista
) identidades GROUP BY identificacion ORDER BY identificacion;

UPDATE tbproductor p JOIN tbpersona x
  ON x.tbpersonaidentificacionnumero = p.tbproductoridentificacionnumero
  SET p.tbpersonaid = x.tbpersonaid;
UPDATE tbcomprador c JOIN tbpersona x
  ON x.tbpersonaidentificacionnumero = c.tbcompradoridentificacionnumero
  SET c.tbpersonaid = x.tbpersonaid;
UPDATE tbtransportista t JOIN tbpersona x
  ON x.tbpersonaidentificacionnumero = t.tbtransportistaidentificacionnumero
  SET t.tbpersonaid = x.tbpersonaid;

SELECT (SELECT COUNT(*) FROM tbproductor WHERE tbpersonaid IS NULL)
     + (SELECT COUNT(*) FROM tbcomprador WHERE tbpersonaid IS NULL)
     + (SELECT COUNT(*) FROM tbtransportista WHERE tbpersonaid IS NULL) INTO @enlaces_invalidos;
SELECT (SELECT COUNT(*) FROM tbproductor) + (SELECT COUNT(*) FROM tbcomprador)
     + (SELECT COUNT(*) FROM tbtransportista) INTO @perfiles_despues;
SET @verificacion_sql := IF(
    @enlaces_invalidos = 0 AND @perfiles_antes = @perfiles_despues,
    'DO 0', 'SELECT * FROM MIGRACION_ABORTADA_VERIFICACION_ENLACES_CONTEOS');
PREPARE verificacion FROM @verificacion_sql;
EXECUTE verificacion;
DEALLOCATE PREPARE verificacion;

ALTER TABLE tbproductor
  DROP COLUMN tbproductoridentificacionnumero, DROP COLUMN tbproductoridentificaciontipo,
  DROP COLUMN tbproductornombre, DROP COLUMN tbproductortelefono,
  DROP COLUMN tbproductorcorreoelectronico, MODIFY tbpersonaid INT NOT NULL;
ALTER TABLE tbcomprador
  DROP COLUMN tbcompradoridentificacionnumero, DROP COLUMN tbcompradoridentificaciontipo,
  DROP COLUMN tbcompradornombre, DROP COLUMN tbcompradortelefono,
  DROP COLUMN tbcompradorcorreoelectronico, MODIFY tbpersonaid INT NOT NULL;
ALTER TABLE tbtransportista
  DROP COLUMN tbtransportistaidentificacionnumero, DROP COLUMN tbtransportistaidentificaciontipo,
  DROP COLUMN tbtransportistanombre, DROP COLUMN tbtransportistatelefono,
  DROP COLUMN tbtransportistacorreoelectronico, MODIFY tbpersonaid INT NOT NULL;

SELECT RELEASE_LOCK('bdmercadoganadero:migrar-persona-capacidades');
