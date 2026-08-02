SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dbtindercows;

INSERT INTO tbrol (tbrolCodigo, tbrolNombre, tbrolEstado) VALUES
    ('PRODUCTOR', 'Productor', 1),
    ('COMPRADOR', 'Comprador', 1),
    ('ADMINISTRADOR', 'Administrador', 1)
ON DUPLICATE KEY UPDATE
    tbrolNombre = VALUES(tbrolNombre),
    tbrolEstado = VALUES(tbrolEstado);
