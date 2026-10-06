USE bdmercadoganadero;

-- Oferta de flete (P1-2): el transportista publica su servicio con su vehículo,
-- su zona base (tbdireccion, con coordenadas), el radio que cubre, la capacidad
-- en cabezas y un precio base opcional. No es un viaje realizado: eso es
-- tbtransportistaflete. Estado: ACTIVA o PAUSADA.
CREATE TABLE IF NOT EXISTS tbtransportistaoferta (
    tbtransportistaofertaid INT NOT NULL,
    tbtransportistaid INT NOT NULL,
    tbvehiculoid INT NOT NULL,
    tbdireccionid INT NOT NULL,
    tbtransportistaofertaradiokm INT NOT NULL,
    tbtransportistaofertacapacidad INT NOT NULL,
    tbtransportistaofertaprecio DECIMAL(12,2) NULL,
    tbtransportistaofertadescripcion VARCHAR(500) NULL,
    tbtransportistaofertaestado VARCHAR(20) NOT NULL,
    tbtransportistaofertafecha DATETIME NOT NULL
) ENGINE=InnoDB;
