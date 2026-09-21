USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Cuenta técnica autorizada por el responsable del proyecto. La contraseña se
-- gestiona únicamente en Supabase Auth y nunca se guarda en esta base.
START TRANSACTION;

UPDATE tbadministrador
SET tbadministradorestado = 1
WHERE LOWER(tbadministradorcorreoelectronico) = LOWER('cortesdila2023@gmail.com');

INSERT INTO tbadministrador (
    tbadministradorid,
    tbadministradorcorreoelectronico,
    tbadministradorestado
)
SELECT 1, 'cortesdila2023@gmail.com', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbadministrador
    WHERE LOWER(tbadministradorcorreoelectronico) = LOWER('cortesdila2023@gmail.com')
);

COMMIT;
