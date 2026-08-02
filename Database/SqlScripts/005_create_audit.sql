USE dbtindercows;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbbitacora (
    tbbitacoraId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tbbitacoraEntidad VARCHAR(80) NOT NULL,
    tbbitacoraRegistroIdentificacionNumero VARCHAR(250) NOT NULL,
    tbbitacoraAccion VARCHAR(30) NOT NULL,
    tbbitacoraFecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tbbitacoraDatosAnteriores JSON NULL,
    tbbitacoraDatosNuevos JSON NULL,
    tbbitacoraActorTipo VARCHAR(30) NOT NULL,
    tbusuarioId BIGINT UNSIGNED NULL,
    tbbitacoraOrigen VARCHAR(100) NOT NULL,
    tbbitacoraSolicitudId VARCHAR(100) NOT NULL,
    CONSTRAINT ck_tbbitacora_entidad_no_vacia CHECK (CHAR_LENGTH(TRIM(tbbitacoraEntidad)) > 0),
    CONSTRAINT ck_tbbitacora_accion_no_vacia CHECK (CHAR_LENGTH(TRIM(tbbitacoraAccion)) > 0),
    CONSTRAINT ck_tbbitacora_actor_no_vacio CHECK (CHAR_LENGTH(TRIM(tbbitacoraActorTipo)) > 0),
    CONSTRAINT ck_tbbitacora_actor_no_autenticado CHECK (
        tbbitacoraActorTipo <> 'NO_AUTENTICADO' OR tbusuarioId IS NULL
    ),
    CONSTRAINT ck_tbbitacora_origen_no_vacio CHECK (CHAR_LENGTH(TRIM(tbbitacoraOrigen)) > 0),
    CONSTRAINT ck_tbbitacora_solicitud_no_vacia CHECK (CHAR_LENGTH(TRIM(tbbitacoraSolicitudId)) > 0),
    INDEX idx_tbbitacora_id (tbbitacoraId),
    INDEX idx_tbbitacora_entidad_registro_fecha (
        tbbitacoraEntidad,
        tbbitacoraRegistroIdentificacionNumero,
        tbbitacoraFecha
    ),
    INDEX idx_tbbitacora_solicitud (tbbitacoraSolicitudId),
    INDEX idx_tbbitacora_fecha (tbbitacoraFecha)
) ENGINE=InnoDB;
