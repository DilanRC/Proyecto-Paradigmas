USE bdmercadoganadero;

-- Lectura automática del número del documento (P2-5/P2-6, opción a del plan
-- como ayuda al administrador). El navegador lee el número de la foto (OCR) y
-- solo envía ese número; PHP calcula el resultado comparándolo con la
-- identificación de la Persona: COINCIDE, NO_COINCIDE, OTRA_CUENTA o
-- SIN_LECTURA. NULL = no se intentó leer (por ejemplo, un PDF). No aprueba
-- nada: la decisión sigue siendo del administrador.
ALTER TABLE tbpersona
    ADD COLUMN tbpersonadocumentonumeroleido VARCHAR(20) NULL AFTER tbpersonadocumentomotivo,
    ADD COLUMN tbpersonadocumentolectura VARCHAR(20) NULL AFTER tbpersonadocumentonumeroleido;
