USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- La identidad puede no declarar alias. Algunas instalaciones anteriores
-- agregaron la columna como NOT NULL y rechazaban altas sin alias.
-- MODIFY no cambia valores existentes: solo alinea la nulabilidad con el
-- contrato canónico de 000instalacioncompleta.sql y Supabase.
ALTER TABLE tbpersona
    MODIFY COLUMN tbpersonaalias VARCHAR(150) NULL AFTER tbpersonanombre;
