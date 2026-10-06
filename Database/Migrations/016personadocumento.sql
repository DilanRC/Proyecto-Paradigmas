USE bdmercadoganadero;

-- Foto del documento de identidad (P2-5). El archivo vive en el bucket privado
-- "documentos" de Supabase Storage; aquí solo se guarda su ruta dentro del
-- bucket (<id de usuario>/<uuid>.<ext>), nunca una URL pública. El tipo de
-- documento es el de la identificación de la Persona (tbpersonaidentificaciontipo).
-- Estado: PENDIENTE, VERIFICADO o RECHAZADO; la fecha es la del último cambio
-- de estado (UTC). Las cuentas existentes quedan en NULL (sin documento).
ALTER TABLE tbpersona
    ADD COLUMN tbpersonadocumentoruta VARCHAR(255) NULL AFTER tbpersonafotourl,
    ADD COLUMN tbpersonadocumentoestado VARCHAR(20) NULL AFTER tbpersonadocumentoruta,
    ADD COLUMN tbpersonadocumentofecha DATETIME NULL AFTER tbpersonadocumentoestado;
