USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Normaliza una instalación heredada que creó el histórico del Comprador con
-- el nombre tbcompradortelefonohistorico. La instalación canónica y Supabase
-- usan tbcompradorpersonatelefonohistorico (DEC-DBREADY-008).
--
-- No se copia ni se elimina información. Antes del RENAME se aborta si:
--   * ambas tablas existen (la procedencia sería ambigua), o
--   * algún teléfono heredado supera VARCHAR(20) y podría truncarse.
-- Una instalación ya normalizada no hace nada.

SET @legacy_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tbcompradortelefonohistorico'
);
SET @canonical_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tbcompradorpersonatelefonohistorico'
);
SET @legacy_oversized := IF(@legacy_exists = 1,
    (SELECT COUNT(*)
     FROM tbcompradortelefonohistorico
     WHERE CHAR_LENGTH(tbcompradorpersonatelefonohistoriconuevo) > 20),
    0
);

-- PREPARE permite que una condición inválida falle sin crear procedimientos,
-- triggers ni otros objetos persistentes en la base.
SET @preflight_sql := CASE
    WHEN @legacy_exists = 1 AND @canonical_exists = 1
        THEN 'SELECT columna_de_migracion_ambigua'
    WHEN @legacy_exists = 1 AND @legacy_oversized > 0
        THEN 'SELECT telefono_heredado_supera_20_caracteres'
    ELSE 'SELECT 1'
END;
PREPARE preflight FROM @preflight_sql;
EXECUTE preflight;
DEALLOCATE PREPARE preflight;

SET @rename_sql := IF(@legacy_exists = 1 AND @canonical_exists = 0,
    'RENAME TABLE tbcompradortelefonohistorico TO tbcompradorpersonatelefonohistorico',
    'SELECT 1');
PREPARE rename_table FROM @rename_sql;
EXECUTE rename_table;
DEALLOCATE PREPARE rename_table;

SET @shape_sql := IF(@legacy_exists = 1 AND @canonical_exists = 0,
    'ALTER TABLE tbcompradorpersonatelefonohistorico
        CHANGE COLUMN tbcompradortelefonohistorico
            tbcompradorpersonatelefonohistoricoid INT NOT NULL,
        CHANGE COLUMN tbcompradorpersonatelefonohistoriconuevo
            tbcompradorpersonatelefonohistoriconuevo VARCHAR(20) NOT NULL,
        CHANGE COLUMN tbcompradortelefonofecha
            tbcompradorpersonatelefonohistoricofecha DATETIME NOT NULL',
    'SELECT 1');
PREPARE normalize_shape FROM @shape_sql;
EXECUTE normalize_shape;
DEALLOCATE PREPARE normalize_shape;
