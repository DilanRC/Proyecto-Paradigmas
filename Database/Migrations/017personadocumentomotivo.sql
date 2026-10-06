USE bdmercadoganadero;

-- Motivo del rechazo del documento de identidad (P2-6). Lo escribe el
-- administrador al rechazar y la persona lo ve en Ajustes → Perfil para saber
-- qué corregir. NULL si no fue rechazado; PHP lo limpia al subir otro documento.
ALTER TABLE tbpersona
    ADD COLUMN tbpersonadocumentomotivo VARCHAR(250) NULL AFTER tbpersonadocumentofecha;
