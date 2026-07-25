-- Migracao 008 — NPS de atendimento + perfil supervisor
-- Execute no phpMyAdmin

-- 1. Adiciona perfil supervisor
ALTER TABLE usuarios
  MODIFY COLUMN perfil ENUM('gestor','tecnico','supervisor') NOT NULL DEFAULT 'tecnico';

-- 2. Colunas NPS na OS
ALTER TABLE ordens_servico
  ADD COLUMN IF NOT EXISTS nps_token       VARCHAR(64) DEFAULT NULL UNIQUE AFTER portal_token,
  ADD COLUMN IF NOT EXISTS nps_nota        TINYINT UNSIGNED DEFAULT NULL AFTER nps_token,
  ADD COLUMN IF NOT EXISTS nps_comentario  TEXT DEFAULT NULL AFTER nps_nota,
  ADD COLUMN IF NOT EXISTS nps_respondido  TINYINT(1) NOT NULL DEFAULT 0 AFTER nps_comentario;

-- 3. Tabela de avaliacoes NPS detalhadas (historico)
CREATE TABLE IF NOT EXISTS nps_avaliacoes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  os_id       INT NOT NULL,
  nota        TINYINT UNSIGNED NOT NULL COMMENT '1 a 5',
  comentario  TEXT DEFAULT NULL,
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
