SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dbtindercows;

INSERT INTO tbidentificaciontipo (
    tbidentificaciontipoCodigo,
    tbidentificaciontipoNombre,
    tbidentificaciontipoEstado
) VALUES
    ('CEDULA_FISICA', 'Cédula física', 1),
    ('CEDULA_JURIDICA', 'Cédula jurídica', 1),
    ('DIMEX', 'DIMEX', 1),
    ('NITE', 'NITE', 1),
    ('PASAPORTE', 'Pasaporte', 1)
ON DUPLICATE KEY UPDATE
    tbidentificaciontipoNombre = VALUES(tbidentificaciontipoNombre),
    tbidentificaciontipoEstado = VALUES(tbidentificaciontipoEstado);
