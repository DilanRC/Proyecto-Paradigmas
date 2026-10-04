USE bdmercadoganadero;

-- Imagen opcional de una publicación: URL pública (Supabase Storage o una
-- dirección https externa). La base solo almacena el texto; el formato y el
-- esquema https se validan en PHP.
ALTER TABLE tbanimalpublicacion
    ADD COLUMN tbanimalpublicacionimagenurl VARCHAR(500) NULL AFTER tbanimalpublicaciondescripcion;
