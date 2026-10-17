USE bdmercadoganadero;

ALTER TABLE tbbitacora
    MODIFY tbbitacoradatosanteriores LONGTEXT NULL,
    MODIFY tbbitacoradatosnuevos LONGTEXT NULL;

ALTER TABLE tbanimalproduccionsalud
    MODIFY tbanimalproduccionsaludproduccion LONGTEXT NULL,
    MODIFY tbanimalproduccionsaludsalud LONGTEXT NULL;
