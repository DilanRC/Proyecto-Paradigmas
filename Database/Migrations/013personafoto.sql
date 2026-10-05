USE bdmercadoganadero;

-- Foto de perfil opcional de la Persona: URL https (Supabase Storage o externa).
-- Las cuentas existentes quedan en NULL y siguen mostrando el avatar con
-- iniciales. La base solo almacena el texto; el formato se valida en PHP.
ALTER TABLE tbpersona
    ADD COLUMN tbpersonafotourl VARCHAR(500) NULL AFTER tbpersonaestado;
