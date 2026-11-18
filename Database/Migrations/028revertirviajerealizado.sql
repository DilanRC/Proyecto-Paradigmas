USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Revierte la 027 (viaje realizado enlazado a la solicitud), retirada por decisión de Dilan (18/11/2026).
-- tbtransportistaflete vuelve a su definición anterior: sin tbcomprasolicitudid y con tbpagometodoid NOT NULL.
-- Una base que nunca corrió la 027 no cambia. Ambas condiciones se comprueban ANTES del único ALTER:
--   * ningún viaje guarda tbcomprasolicitudid (se perdería el enlace al retirar la columna);
--   * ningún viaje tiene tbpagometodoid NULL (no se inventa un método de pago).
-- Si falla alguna, no se ejecuta ningún DDL; la consulta final informa el bloqueo. Si no falla,
-- los cambios necesarios se agrupan en un solo ALTER TABLE. Es repetible incluso tras un bloqueo.
-- MySQL confirma los ALTER implícitamente: ejecutar con respaldo y sin escrituras concurrentes,
-- porque entre la comprobación y el ALTER no existe un bloqueo transaccional que proteja los datos.
SET @tiene_solicitud := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistaflete' AND COLUMN_NAME = 'tbcomprasolicitudid');
SET @sql_enlazados := IF(@tiene_solicitud = 1,
    'SELECT COUNT(*) INTO @enlazados FROM tbtransportistaflete WHERE tbcomprasolicitudid IS NOT NULL',
    'SELECT 0 INTO @enlazados');
PREPARE contar_enlazados FROM @sql_enlazados;
EXECUTE contar_enlazados;
DEALLOCATE PREPARE contar_enlazados;

-- Comprobar las dos precondiciones antes de ejecutar ningún ALTER.
SET @pago_opcional := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistaflete' AND COLUMN_NAME = 'tbpagometodoid' AND IS_NULLABLE = 'YES');
SET @pago_nulo := (SELECT COUNT(*) FROM tbtransportistaflete WHERE tbpagometodoid IS NULL);
SET @bloqueada := (@tiene_solicitud = 1 AND @enlazados > 0)
    OR (@pago_opcional = 1 AND @pago_nulo > 0);

-- Un solo ALTER si ambas condiciones son seguras; cero ALTER si una falla o ya está revertida.
SET @acciones := CONCAT_WS(', ',
    IF(@tiene_solicitud = 1, 'DROP COLUMN tbcomprasolicitudid', NULL),
    IF(@pago_opcional = 1, 'MODIFY COLUMN tbpagometodoid INT NOT NULL', NULL));
SET @sql_revertir := IF(@bloqueada OR @acciones = '', 'SELECT 1',
    CONCAT('ALTER TABLE tbtransportistaflete ', @acciones));
PREPARE revertir_027 FROM @sql_revertir;
EXECUTE revertir_027;
DEALLOCATE PREPARE revertir_027;

SELECT CASE
    WHEN @tiene_solicitud = 1 AND @enlazados > 0 AND @pago_opcional = 1 AND @pago_nulo > 0
        THEN CONCAT('028 PENDIENTE: ', @enlazados, ' viajes enlazados y ', @pago_nulo, ' viajes sin método de pago; no se modificó la tabla.')
    WHEN @tiene_solicitud = 1 AND @enlazados > 0
        THEN CONCAT('028 PENDIENTE: ', @enlazados, ' viajes guardan tbcomprasolicitudid; la columna se conservó.')
    WHEN @pago_opcional = 1 AND @pago_nulo > 0
        THEN CONCAT('028 PENDIENTE: ', @pago_nulo, ' viajes sin método de pago; tbpagometodoid sigue admitiendo NULL.')
    ELSE '028 OK: tbtransportistaflete coincide con el esquema anterior a la 027.'
END AS resultado;
