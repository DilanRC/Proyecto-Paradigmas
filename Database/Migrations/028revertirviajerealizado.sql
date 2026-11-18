USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Revierte la 027 (viaje realizado enlazado a la solicitud), retirada por decisión de Dilan (18/11/2026).
-- tbtransportistaflete vuelve a su definición anterior: sin tbcomprasolicitudid y con tbpagometodoid NOT NULL.
-- Una base que nunca corrió la 027 no cambia. Seguridad de los datos (MySQL confirma cada ALTER al instante,
-- así que no hay ROLLBACK posible; por eso cada paso se comprueba ANTES y phpMyAdmin puede seguir tras un error):
--   * la columna solo se borra si ningún viaje guarda el enlace (si no, el esquema anterior perdería ese hecho);
--   * NOT NULL solo se exige si ningún viaje tiene el método de pago vacío (no se inventa un método).
-- Si alguna condición falla, la consulta final lo indica y la base queda igual. Se puede repetir sin efectos.
SET @tiene_solicitud := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistaflete' AND COLUMN_NAME = 'tbcomprasolicitudid');
SET @sql_enlazados := IF(@tiene_solicitud = 1,
    'SELECT COUNT(*) INTO @enlazados FROM tbtransportistaflete WHERE tbcomprasolicitudid IS NOT NULL',
    'SELECT 0 INTO @enlazados');
PREPARE contar_enlazados FROM @sql_enlazados;
EXECUTE contar_enlazados;
DEALLOCATE PREPARE contar_enlazados;

SET @sql_quitar := IF(@tiene_solicitud = 1 AND @enlazados = 0,
    'ALTER TABLE tbtransportistaflete DROP COLUMN tbcomprasolicitudid',
    'SELECT 1');
PREPARE quitar_solicitud FROM @sql_quitar;
EXECUTE quitar_solicitud;
DEALLOCATE PREPARE quitar_solicitud;

SET @pago_opcional := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistaflete' AND COLUMN_NAME = 'tbpagometodoid' AND IS_NULLABLE = 'YES');
SET @pago_nulo := (SELECT COUNT(*) FROM tbtransportistaflete WHERE tbpagometodoid IS NULL);
SET @sql_pago := IF(@pago_opcional = 1 AND @pago_nulo = 0,
    'ALTER TABLE tbtransportistaflete MODIFY tbpagometodoid INT NOT NULL',
    'SELECT 1');
PREPARE pago_obligatorio FROM @sql_pago;
EXECUTE pago_obligatorio;
DEALLOCATE PREPARE pago_obligatorio;

SELECT CASE
    WHEN @tiene_solicitud = 1 AND @enlazados > 0
        THEN CONCAT('028 PENDIENTE: ', @enlazados, ' viajes guardan tbcomprasolicitudid; la columna se conservó.')
    WHEN @pago_opcional = 1 AND @pago_nulo > 0
        THEN CONCAT('028 PENDIENTE: ', @pago_nulo, ' viajes sin método de pago; tbpagometodoid sigue admitiendo NULL.')
    ELSE '028 OK: tbtransportistaflete coincide con el esquema anterior a la 027.'
END AS resultado;
