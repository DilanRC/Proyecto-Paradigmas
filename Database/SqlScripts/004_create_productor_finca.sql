USE dbtindercows;

CREATE TABLE IF NOT EXISTS tbfinca (
    tbfincaId BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tbfincaNombre VARCHAR(150) NOT NULL,
    tbfincaEstado TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT pk_tbfinca PRIMARY KEY (tbfincaId),
    CONSTRAINT ck_tbfinca_nombre_no_vacio CHECK (CHAR_LENGTH(TRIM(tbfincaNombre)) > 0),
    CONSTRAINT ck_tbfinca_estado CHECK (tbfincaEstado IN (0, 1)),
    INDEX idx_tbfinca_nombre (tbfincaNombre),
    INDEX idx_tbfinca_estado (tbfincaEstado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbproductorfinca (
    tbparticipanteId BIGINT UNSIGNED NOT NULL,
    tbfincaId BIGINT UNSIGNED NOT NULL,
    tbproductorfincaEstado TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT pk_tbproductorfinca PRIMARY KEY (tbparticipanteId, tbfincaId),
    CONSTRAINT fk_tbproductorfinca_participante FOREIGN KEY (tbparticipanteId)
        REFERENCES tbparticipante (tbparticipanteId) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_tbproductorfinca_finca FOREIGN KEY (tbfincaId)
        REFERENCES tbfinca (tbfincaId) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT ck_tbproductorfinca_estado CHECK (tbproductorfincaEstado IN (0, 1)),
    INDEX idx_tbproductorfinca_finca_estado (tbfincaId, tbproductorfincaEstado)
) ENGINE=InnoDB;
