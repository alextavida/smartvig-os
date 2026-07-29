-- Migration 010: tabela gps_tecnicos para rastreamento de posicao dos tecnicos
-- Executar no phpMyAdmin ou via mysql CLI

CREATE TABLE IF NOT EXISTS gps_tecnicos (
    tecnico_id   INT UNSIGNED NOT NULL,
    os_id        INT UNSIGNED NULL COMMENT 'OS em andamento no momento do envio',
    latitude     DECIMAL(10, 7) NULL,
    longitude    DECIMAL(10, 7) NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tecnico_id),
    CONSTRAINT fk_gps_usuario FOREIGN KEY (tecnico_id)
        REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
