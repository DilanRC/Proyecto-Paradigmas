USE bdmercadoganadero;

-- Foto opcional del vehículo: URL https (Supabase Storage o externa). Una sola
-- foto por vehículo; varias necesitarían una tabla aparte. Los vehículos
-- existentes quedan en NULL. La base solo almacena el texto; el formato se
-- valida en PHP.
ALTER TABLE tbvehiculo
    ADD COLUMN tbvehiculofotourl VARCHAR(500) NULL AFTER tbvehiculoestado;
