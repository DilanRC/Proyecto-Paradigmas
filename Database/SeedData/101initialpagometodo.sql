USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Dato inicial obligatorio del avance: el único método de pago del alcance
-- vigente. La comprobación explícita hace el script idempotente sin llaves.
START TRANSACTION;

UPDATE tbpagometodo SET
    tbpagometodonombre = 'Efectivo',
    tbpagometododescripcion = 'Pago realizado en efectivo',
    tbpagometodoactivo = 1
WHERE tbpagometodoid = 1;

INSERT INTO tbpagometodo (
    tbpagometodoid,
    tbpagometodonombre,
    tbpagometododescripcion,
    tbpagometodoactivo
)
SELECT 1, 'Efectivo', 'Pago realizado en efectivo', 1
WHERE NOT EXISTS (
    SELECT 1 FROM tbpagometodo WHERE tbpagometodoid = 1
);

COMMIT;
