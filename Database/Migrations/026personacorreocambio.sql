USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Bases creadas antes de esta tabla. Se puede repetir (IF NOT EXISTS); no cambia datos.
-- Histórico de cambios de correo de una Persona. La fila PENDIENTE se guarda antes de
-- llamar a Supabase Auth; con ella PHP concilia los dos sistemas si el cambio se
-- interrumpe. Estado: PENDIENTE, APLICADO o CANCELADO. Fechas en UTC.
CREATE TABLE IF NOT EXISTS tbpersonacorreocambio (
    tbpersonacorreocambioid INT NOT NULL,
    tbpersonaid INT NOT NULL,
    tbpersonacorreocambiosujeto VARCHAR(100) NOT NULL,
    tbpersonacorreocambioanterior VARCHAR(150) NOT NULL,
    tbpersonacorreocambionuevo VARCHAR(150) NOT NULL,
    tbpersonacorreocambioestado VARCHAR(20) NOT NULL,
    tbpersonacorreocambiofecha DATETIME NOT NULL,
    tbpersonacorreocambiofecharesolucion DATETIME NULL,
    PRIMARY KEY (tbpersonacorreocambioid)
) ENGINE=InnoDB;
