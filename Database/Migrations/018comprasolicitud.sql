USE bdmercadoganadero;

-- Solicitud de compra (P1-3). tbcompra y tbventa dejan de exigir un Productor
-- comprador y un método de pago: el comprador es un Comprador (tbcompradorid) y
-- el pago es opcional. Aditivo: las filas existentes siguen válidas.
ALTER TABLE tbcompra
    MODIFY COLUMN tbproductorcompradorid INT NULL,
    MODIFY COLUMN tbpagometodoid INT NULL,
    ADD COLUMN tbcompradorid INT NULL AFTER tbproductorcompradorid;

ALTER TABLE tbventa
    MODIFY COLUMN tbproductorcompradorid INT NULL,
    MODIFY COLUMN tbpagometodoid INT NULL,
    ADD COLUMN tbcompradorid INT NULL AFTER tbproductorcompradorid,
    ADD COLUMN tbcomprasolicitudid INT NULL AFTER tbcompraid;

-- Solicitud de compra (P1-3, DEC-COMPRA-001): el comprador pide un animal, con
-- flete opcional (una oferta de tbtransportistaoferta), y el vendedor la acepta
-- o la rechaza. Al aceptar se registran tbcompra y tbventa. Estado: PENDIENTE,
-- ACEPTADA, RECHAZADA o CANCELADA. El flete lo responde aparte el transportista
-- (PENDIENTE, ACEPTADA, RECHAZADA o CANCELADA; NULL si no pidió flete).
CREATE TABLE IF NOT EXISTS tbcomprasolicitud (
    tbcomprasolicitudid INT NOT NULL,
    tbanimalpublicacionid INT NOT NULL,
    tbcompradorid INT NOT NULL,
    tbtransportistaofertaid INT NULL,
    tbpagometodoid INT NULL,
    tbcomprasolicitudprecio DECIMAL(12,2) NULL,
    tbcomprasolicitudmensaje VARCHAR(500) NULL,
    tbcomprasolicitudestado VARCHAR(20) NOT NULL,
    tbcomprasolicitudfleteestado VARCHAR(20) NULL,
    tbcomprasolicitudfecha DATETIME NOT NULL,
    tbcomprasolicitudrespuestafecha DATETIME NULL,
    tbcomprasolicitudrespuestamotivo VARCHAR(250) NULL,
    tbcomprasolicitudfleterespuestafecha DATETIME NULL
) ENGINE=InnoDB;
