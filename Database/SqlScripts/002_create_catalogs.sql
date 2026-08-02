USE dbtindercows;

CREATE TABLE IF NOT EXISTS tbrol (
    tbrolId SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tbrolCodigo VARCHAR(40) NOT NULL,
    tbrolNombre VARCHAR(100) NOT NULL,
    tbrolEstado TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT pk_tbrol PRIMARY KEY (tbrolId),
    CONSTRAINT uq_tbrol_codigo UNIQUE (tbrolCodigo),
    CONSTRAINT uq_tbrol_nombre UNIQUE (tbrolNombre),
    CONSTRAINT ck_tbrol_codigo_no_vacio CHECK (CHAR_LENGTH(TRIM(tbrolCodigo)) > 0),
    CONSTRAINT ck_tbrol_nombre_no_vacio CHECK (CHAR_LENGTH(TRIM(tbrolNombre)) > 0),
    CONSTRAINT ck_tbrol_estado CHECK (tbrolEstado IN (0, 1)),
    INDEX idx_tbrol_estado (tbrolEstado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbidentificaciontipo (
    tbidentificaciontipoId SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tbidentificaciontipoCodigo VARCHAR(40) NOT NULL,
    tbidentificaciontipoNombre VARCHAR(100) NOT NULL,
    tbidentificaciontipoEstado TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT pk_tbidentificaciontipo PRIMARY KEY (tbidentificaciontipoId),
    CONSTRAINT uq_tbidentificaciontipo_codigo UNIQUE (tbidentificaciontipoCodigo),
    CONSTRAINT uq_tbidentificaciontipo_nombre UNIQUE (tbidentificaciontipoNombre),
    CONSTRAINT ck_tbidentificaciontipo_codigo_no_vacio CHECK (CHAR_LENGTH(TRIM(tbidentificaciontipoCodigo)) > 0),
    CONSTRAINT ck_tbidentificaciontipo_nombre_no_vacio CHECK (CHAR_LENGTH(TRIM(tbidentificaciontipoNombre)) > 0),
    CONSTRAINT ck_tbidentificaciontipo_estado CHECK (tbidentificaciontipoEstado IN (0, 1)),
    INDEX idx_tbidentificaciontipo_estado (tbidentificaciontipoEstado)
) ENGINE=InnoDB;
