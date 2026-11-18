USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Viaje realizado (DEC-VIAJE-001): el transportista marca como realizado un flete aceptado y PHP escribe
-- tbtransportistaflete con la solicitud de compra que lo originó (una por solicitud, validado en PHP). El método
-- de pago de la solicitud es opcional, así que el del viaje también. Se puede repetir: cada cambio se comprueba
-- antes (phpMyAdmin sigue tras un error) y no cambia datos.
SET @falta_solicitud := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistaflete' AND COLUMN_NAME = 'tbcomprasolicitudid');
SET @sql_solicitud := IF(@falta_solicitud,
    'ALTER TABLE tbtransportistaflete ADD COLUMN tbcomprasolicitudid INT NULL AFTER tbtransportistaid',
    'SELECT 1');
PREPARE agregar_solicitud FROM @sql_solicitud;
EXECUTE agregar_solicitud;
DEALLOCATE PREPARE agregar_solicitud;

SET @pago_obligatorio := (SELECT COUNT(*) = 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistaflete' AND COLUMN_NAME = 'tbpagometodoid' AND IS_NULLABLE = 'NO');
SET @sql_pago := IF(@pago_obligatorio,
    'ALTER TABLE tbtransportistaflete MODIFY tbpagometodoid INT NULL',
    'SELECT 1');
PREPARE pago_opcional FROM @sql_pago;
EXECUTE pago_opcional;
DEALLOCATE PREPARE pago_opcional;
