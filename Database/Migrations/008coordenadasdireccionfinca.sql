USE bdmercadoganadero;

-- Punto geográfico opcional de una ubicación física. La base solo almacena
-- atributos/tipos; rangos, paridad latitud-longitud y política se validan en PHP.
ALTER TABLE tbdireccion
    ADD COLUMN tbdireccionlatitud DECIMAL(10,7) NULL AFTER tbdireccionsenas,
    ADD COLUMN tbdireccionlongitud DECIMAL(10,7) NULL AFTER tbdireccionlatitud;
