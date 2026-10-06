USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Catálogos iniciales del animal (especies, tipos y razas). Idempotente: solo siembra una tabla vacía.
START TRANSACTION;

INSERT INTO tbespecie (tbespecieid, tbespecienombre, tbespecieactivo)
SELECT v.* FROM (
    SELECT 1 AS tbespecieid, 'Bovino' AS tbespecienombre, 1 AS tbespecieactivo
    UNION ALL
    SELECT 2, 'Porcino', 1
    UNION ALL
    SELECT 3, 'Equino', 1
    UNION ALL
    SELECT 4, 'Ovino', 1
    UNION ALL
    SELECT 5, 'Caprino', 1
    UNION ALL
    SELECT 6, 'Bufalino', 1
) v
WHERE NOT EXISTS (SELECT 1 FROM tbespecie);

INSERT INTO tbanimaltipo (tbanimaltipoid, tbespecieid, tbanimaltiponombre, tbanimaltiposexo, tbanimaltipoactivo)
SELECT v.* FROM (
    SELECT 1 AS tbanimaltipoid, 1 AS tbespecieid, 'Ternero' AS tbanimaltiponombre, 'M' AS tbanimaltiposexo, 1 AS tbanimaltipoactivo
    UNION ALL
    SELECT 2, 1, 'Ternera', 'H', 1
    UNION ALL
    SELECT 3, 1, 'Torete', 'M', 1
    UNION ALL
    SELECT 4, 1, 'Novillo', 'M', 1
    UNION ALL
    SELECT 5, 1, 'Vaquilla', 'H', 1
    UNION ALL
    SELECT 6, 1, 'Vaca', 'H', 1
    UNION ALL
    SELECT 7, 1, 'Toro', 'M', 1
    UNION ALL
    SELECT 8, 1, 'Buey', 'M', 1
    UNION ALL
    SELECT 9, 2, 'Lechón', NULL, 1
    UNION ALL
    SELECT 10, 2, 'Cerdo de engorde', NULL, 1
    UNION ALL
    SELECT 11, 2, 'Cerda', 'H', 1
    UNION ALL
    SELECT 12, 2, 'Verraco', 'M', 1
    UNION ALL
    SELECT 13, 3, 'Potro', 'M', 1
    UNION ALL
    SELECT 14, 3, 'Potra', 'H', 1
    UNION ALL
    SELECT 15, 3, 'Caballo', 'M', 1
    UNION ALL
    SELECT 16, 3, 'Yegua', 'H', 1
    UNION ALL
    SELECT 17, 4, 'Cordero', 'M', 1
    UNION ALL
    SELECT 18, 4, 'Cordera', 'H', 1
    UNION ALL
    SELECT 19, 4, 'Carnero', 'M', 1
    UNION ALL
    SELECT 20, 4, 'Oveja', 'H', 1
    UNION ALL
    SELECT 21, 5, 'Cabrito', 'M', 1
    UNION ALL
    SELECT 22, 5, 'Cabrita', 'H', 1
    UNION ALL
    SELECT 23, 5, 'Macho cabrío', 'M', 1
    UNION ALL
    SELECT 24, 5, 'Cabra', 'H', 1
    UNION ALL
    SELECT 25, 6, 'Búfalo', 'M', 1
    UNION ALL
    SELECT 26, 6, 'Búfala', 'H', 1
) v
WHERE NOT EXISTS (SELECT 1 FROM tbanimaltipo);

INSERT INTO tbraza (tbrazaid, tbespecieid, tbrazanombre, tbrazaactivo)
SELECT v.* FROM (
    SELECT 1 AS tbrazaid, 1 AS tbespecieid, 'Brahman' AS tbrazanombre, 1 AS tbrazaactivo
    UNION ALL
    SELECT 2, 1, 'Holstein', 1
    UNION ALL
    SELECT 3, 1, 'Jersey', 1
    UNION ALL
    SELECT 4, 1, 'Pardo Suizo', 1
    UNION ALL
    SELECT 5, 1, 'Angus', 1
    UNION ALL
    SELECT 6, 1, 'Nelore', 1
    UNION ALL
    SELECT 7, 1, 'Gyr', 1
    UNION ALL
    SELECT 8, 1, 'Girolando', 1
    UNION ALL
    SELECT 9, 1, 'Simmental', 1
    UNION ALL
    SELECT 10, 1, 'Guzerat', 1
    UNION ALL
    SELECT 11, 1, 'Sindi', 1
    UNION ALL
    SELECT 12, 1, 'Charolais', 1
    UNION ALL
    SELECT 13, 1, 'Santa Gertrudis', 1
    UNION ALL
    SELECT 14, 1, 'Brangus', 1
    UNION ALL
    SELECT 15, 1, 'Beefmaster', 1
    UNION ALL
    SELECT 16, 1, 'Criollo', 1
    UNION ALL
    SELECT 17, 1, 'Mestizo', 1
    UNION ALL
    SELECT 18, 2, 'Landrace', 1
    UNION ALL
    SELECT 19, 2, 'Yorkshire', 1
    UNION ALL
    SELECT 20, 2, 'Duroc', 1
    UNION ALL
    SELECT 21, 2, 'Pietrain', 1
    UNION ALL
    SELECT 22, 2, 'Criollo', 1
    UNION ALL
    SELECT 23, 2, 'Mestizo', 1
    UNION ALL
    SELECT 24, 3, 'Criollo', 1
    UNION ALL
    SELECT 25, 3, 'Cuarto de milla', 1
    UNION ALL
    SELECT 26, 3, 'Pura sangre', 1
    UNION ALL
    SELECT 27, 3, 'Paso fino', 1
    UNION ALL
    SELECT 28, 3, 'Mestizo', 1
    UNION ALL
    SELECT 29, 4, 'Pelibuey', 1
    UNION ALL
    SELECT 30, 4, 'Katahdin', 1
    UNION ALL
    SELECT 31, 4, 'Dorper', 1
    UNION ALL
    SELECT 32, 4, 'Criollo', 1
    UNION ALL
    SELECT 33, 4, 'Mestizo', 1
    UNION ALL
    SELECT 34, 5, 'Saanen', 1
    UNION ALL
    SELECT 35, 5, 'Alpina', 1
    UNION ALL
    SELECT 36, 5, 'Toggenburg', 1
    UNION ALL
    SELECT 37, 5, 'Nubia', 1
    UNION ALL
    SELECT 38, 5, 'Criollo', 1
    UNION ALL
    SELECT 39, 5, 'Mestizo', 1
    UNION ALL
    SELECT 40, 6, 'Murrah', 1
    UNION ALL
    SELECT 41, 6, 'Mediterráneo', 1
    UNION ALL
    SELECT 42, 6, 'Mestizo', 1
) v
WHERE NOT EXISTS (SELECT 1 FROM tbraza);

COMMIT;
