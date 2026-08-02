USE dbtindercows;

CREATE TABLE IF NOT EXISTS tbparticipante (
    tbparticipanteId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tbparticipanteNombre VARCHAR(150) NOT NULL,
    tbparticipanteTelefono VARCHAR(20) NOT NULL,
    tbparticipanteCorreoElectronico VARCHAR(150) NOT NULL,
    tbparticipanteEstado TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT pk_tbparticipante PRIMARY KEY (tbparticipanteId),
    CONSTRAINT ck_tbparticipante_nombre_longitud CHECK (CHAR_LENGTH(TRIM(tbparticipanteNombre)) BETWEEN 3 AND 150),
    CONSTRAINT ck_tbparticipante_telefono_no_vacio CHECK (CHAR_LENGTH(TRIM(tbparticipanteTelefono)) > 0),
    CONSTRAINT ck_tbparticipante_correo_no_vacio CHECK (CHAR_LENGTH(TRIM(tbparticipanteCorreoElectronico)) > 0),
    CONSTRAINT ck_tbparticipante_correo_minuscula CHECK (
        BINARY tbparticipanteCorreoElectronico = BINARY LOWER(tbparticipanteCorreoElectronico)
    ),
    CONSTRAINT ck_tbparticipante_estado CHECK (tbparticipanteEstado IN (0, 1)),
    INDEX idx_tbparticipante_nombre (tbparticipanteNombre),
    INDEX idx_tbparticipante_estado (tbparticipanteEstado),
    INDEX idx_tbparticipante_correo (tbparticipanteCorreoElectronico)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbparticipanterol (
    tbparticipanteId BIGINT UNSIGNED NOT NULL,
    tbrolId SMALLINT UNSIGNED NOT NULL,
    tbparticipanterolEstado TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT pk_tbparticipanterol PRIMARY KEY (tbparticipanteId, tbrolId),
    CONSTRAINT fk_tbparticipanterol_participante FOREIGN KEY (tbparticipanteId)
        REFERENCES tbparticipante (tbparticipanteId) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_tbparticipanterol_rol FOREIGN KEY (tbrolId)
        REFERENCES tbrol (tbrolId) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT ck_tbparticipanterol_estado CHECK (tbparticipanterolEstado IN (0, 1)),
    INDEX idx_tbparticipanterol_rol_estado (tbrolId, tbparticipanterolEstado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbparticipanteidentificacion (
    tbparticipanteidentificacionId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tbparticipanteId BIGINT UNSIGNED NOT NULL,
    tbidentificaciontipoId SMALLINT UNSIGNED NOT NULL,
    tbparticipanteidentificacionNumero VARCHAR(250) NOT NULL,
    tbparticipanteidentificacionNumeroNormalizado VARCHAR(250) NOT NULL,
    tbparticipanteidentificacionEsPrincipal TINYINT(1) NOT NULL DEFAULT 1,
    tbparticipanteidentificacionEstado TINYINT(1) NOT NULL DEFAULT 1,
    tbparticipanteidentificacionPrincipalActivaParticipanteId BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE
                WHEN tbparticipanteidentificacionEsPrincipal = 1
                 AND tbparticipanteidentificacionEstado = 1
                THEN tbparticipanteId
                ELSE NULL
            END
        ) STORED,
    CONSTRAINT pk_tbparticipanteidentificacion PRIMARY KEY (tbparticipanteidentificacionId),
    CONSTRAINT fk_tbparticipanteidentificacion_participante FOREIGN KEY (tbparticipanteId)
        REFERENCES tbparticipante (tbparticipanteId) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_tbparticipanteidentificacion_tipo FOREIGN KEY (tbidentificaciontipoId)
        REFERENCES tbidentificaciontipo (tbidentificaciontipoId) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT uq_tbparticipanteidentificacion_tipo_numero_normalizado UNIQUE (
        tbidentificaciontipoId,
        tbparticipanteidentificacionNumeroNormalizado
    ),
    CONSTRAINT uq_tbparticipanteidentificacion_principal_activa UNIQUE (
        tbparticipanteidentificacionPrincipalActivaParticipanteId
    ),
    CONSTRAINT ck_tbparticipanteidentificacion_numero_no_vacio CHECK (
        CHAR_LENGTH(TRIM(tbparticipanteidentificacionNumero)) > 0
    ),
    CONSTRAINT ck_tbparticipanteidentificacion_normalizado_no_vacio CHECK (
        CHAR_LENGTH(TRIM(tbparticipanteidentificacionNumeroNormalizado)) > 0
    ),
    CONSTRAINT ck_tbparticipanteidentificacion_principal CHECK (
        tbparticipanteidentificacionEsPrincipal IN (0, 1)
    ),
    CONSTRAINT ck_tbparticipanteidentificacion_estado CHECK (
        tbparticipanteidentificacionEstado IN (0, 1)
    ),
    INDEX idx_tbparticipanteidentificacion_participante_estado (
        tbparticipanteId,
        tbparticipanteidentificacionEstado
    )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbparticipantedireccion (
    tbparticipantedireccionId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tbparticipanteId BIGINT UNSIGNED NOT NULL,
    tbparticipantedireccionProvincia VARCHAR(100) NOT NULL,
    tbparticipantedireccionCanton VARCHAR(100) NOT NULL,
    tbparticipantedireccionDistrito VARCHAR(100) NOT NULL,
    tbparticipantedireccionPueblo VARCHAR(150) NULL,
    tbparticipantedireccionSenas VARCHAR(500) NULL,
    tbparticipantedireccionEsPrincipal TINYINT(1) NOT NULL DEFAULT 1,
    tbparticipantedireccionEstado TINYINT(1) NOT NULL DEFAULT 1,
    tbparticipantedireccionPrincipalActivaParticipanteId BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE
                WHEN tbparticipantedireccionEsPrincipal = 1
                 AND tbparticipantedireccionEstado = 1
                THEN tbparticipanteId
                ELSE NULL
            END
        ) STORED,
    CONSTRAINT pk_tbparticipantedireccion PRIMARY KEY (tbparticipantedireccionId),
    CONSTRAINT fk_tbparticipantedireccion_participante FOREIGN KEY (tbparticipanteId)
        REFERENCES tbparticipante (tbparticipanteId) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT uq_tbparticipantedireccion_principal_activa UNIQUE (
        tbparticipantedireccionPrincipalActivaParticipanteId
    ),
    CONSTRAINT ck_tbparticipantedireccion_provincia_no_vacia CHECK (
        CHAR_LENGTH(TRIM(tbparticipantedireccionProvincia)) > 0
    ),
    CONSTRAINT ck_tbparticipantedireccion_canton_no_vacio CHECK (
        CHAR_LENGTH(TRIM(tbparticipantedireccionCanton)) > 0
    ),
    CONSTRAINT ck_tbparticipantedireccion_distrito_no_vacio CHECK (
        CHAR_LENGTH(TRIM(tbparticipantedireccionDistrito)) > 0
    ),
    CONSTRAINT ck_tbparticipantedireccion_principal CHECK (tbparticipantedireccionEsPrincipal IN (0, 1)),
    CONSTRAINT ck_tbparticipantedireccion_estado CHECK (tbparticipantedireccionEstado IN (0, 1)),
    INDEX idx_tbparticipantedireccion_participante_estado (tbparticipanteId, tbparticipantedireccionEstado)
) ENGINE=InnoDB;
