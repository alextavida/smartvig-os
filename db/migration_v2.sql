-- Migration v2: prioridade nas OS + foto de perfil nos tecnicos
USE app_tecnicos;

ALTER TABLE ordens_servico
  ADD COLUMN prioridade ENUM('baixo','intermediario','urgente') NOT NULL DEFAULT 'baixo'
  AFTER situacao_local;

ALTER TABLE usuarios
  ADD COLUMN foto_perfil VARCHAR(500) NULL COMMENT 'Caminho relativo da foto de perfil em imgs/avatares/'
  AFTER ativo;

-- Garante que a pasta de avatares existe via registro (criada pelo PHP no primeiro upload)
