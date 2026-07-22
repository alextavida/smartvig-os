-- ============================================================
-- App Técnicos - Schema do banco de dados (MySQL / phpMyAdmin)
-- Sistema 100% operacional: XAMPP + Apache + MySQL + PHP
-- Sem dependências externas (sem Firebase) - mídias em disco local,
-- notificações e GPS via tabelas consultadas por polling.
-- ============================================================

CREATE DATABASE IF NOT EXISTS app_tecnicos
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE app_tecnicos;

-- ------------------------------------------------------------
-- usuarios: gestores e técnicos do sistema
-- ------------------------------------------------------------
CREATE TABLE usuarios (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  nome              VARCHAR(100) NOT NULL,
  email             VARCHAR(150) NOT NULL UNIQUE,
  senha_hash        VARCHAR(255) NOT NULL,
  perfil            ENUM('gestor','tecnico') NOT NULL,
  telefone          VARCHAR(30) NULL,
  ativo             TINYINT(1) NOT NULL DEFAULT 1,
  gcid_usuario      INT NULL COMMENT 'ID do usuario no GestaoClick',
  gcid_funcionario  INT NULL COMMENT 'ID do funcionario no GestaoClick',
  criado_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ordens_servico: espelho local das OSs do GestaoClick
-- ------------------------------------------------------------
CREATE TABLE ordens_servico (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  gc_os_id          INT NOT NULL COMMENT 'ID da OS no GestaoClick',
  gc_cliente_id     INT NULL,
  gc_situacao_id    INT NULL,
  codigo            VARCHAR(50) NULL,
  cliente_nome      VARCHAR(200) NULL,
  cliente_endereco  TEXT NULL,
  cliente_telefone  VARCHAR(30) NULL,
  tecnico_id        INT NULL COMMENT 'Tecnico responsavel (denormalizado, ver os_tecnicos)',
  situacao_local    ENUM('aberto','em_andamento','pausado','reagendado','concluido','cancelado') NOT NULL DEFAULT 'aberto',
  data_agendamento  DATE NULL,
  data_conclusao    DATETIME NULL,
  observacoes       TEXT NULL COMMENT 'Descricao/laudo do tecnico',
  motivo_pausa      VARCHAR(500) NULL,
  produtos_json     JSON NULL COMMENT 'Array de produtos incluidos na OS',
  midias_json       JSON NULL COMMENT 'Array de caminhos locais das midias',
  latitude_atual    DECIMAL(10,8) NULL,
  longitude_atual   DECIMAL(11,8) NULL,
  sincronizado_gc   TINYINT(1) NOT NULL DEFAULT 0,
  criado_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_os_tecnico FOREIGN KEY (tecnico_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  KEY idx_gc_os_id (gc_os_id),
  KEY idx_tecnico (tecnico_id),
  KEY idx_situacao (situacao_local)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- os_tecnicos: tecnicos atribuidos a uma OS (N:N) + responsavel
-- ------------------------------------------------------------
CREATE TABLE os_tecnicos (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  os_id       INT NOT NULL,
  tecnico_id  INT NOT NULL,
  responsavel TINYINT(1) NOT NULL DEFAULT 0,
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ost_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
  CONSTRAINT fk_ost_tecnico FOREIGN KEY (tecnico_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_os_tecnico (os_id, tecnico_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- historico_os: timeline de acoes de uma OS
-- ------------------------------------------------------------
CREATE TABLE historico_os (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  os_id       INT NOT NULL,
  usuario_id  INT NULL,
  acao        VARCHAR(100) NOT NULL,
  detalhe     TEXT NULL,
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hist_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  KEY idx_hist_os (os_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- midias_os: fotos/videos anexados a uma OS (armazenados em disco)
-- ------------------------------------------------------------
CREATE TABLE midias_os (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  os_id          INT NOT NULL,
  tipo           ENUM('foto','video') NOT NULL,
  caminho_arquivo VARCHAR(500) NOT NULL COMMENT 'Caminho relativo dentro de imgs/os/{id}/',
  nome_arquivo   VARCHAR(255) NOT NULL,
  tamanho_bytes  INT NULL,
  enviado_por    INT NULL,
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_midia_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
  CONSTRAINT fk_midia_usuario FOREIGN KEY (enviado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  KEY idx_midia_os (os_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- gps_tecnicos: ultima posicao conhecida de cada tecnico
-- (consultada via polling pelo mapa do painel do gestor)
-- ------------------------------------------------------------
CREATE TABLE gps_tecnicos (
  tecnico_id     INT NOT NULL PRIMARY KEY,
  os_id          INT NULL COMMENT 'OS em andamento no momento da atualizacao',
  latitude       DECIMAL(10,8) NOT NULL,
  longitude      DECIMAL(11,8) NOT NULL,
  atualizado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_gps_tecnico FOREIGN KEY (tecnico_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- notificacoes: substitui push (FCM) - consultada via polling
-- ------------------------------------------------------------
CREATE TABLE notificacoes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id  INT NOT NULL,
  os_id       INT NULL,
  tipo        VARCHAR(50) NOT NULL COMMENT 'nova_os, reagendada, pausada, encerrada, midia, mensagem...',
  titulo      VARCHAR(200) NOT NULL,
  mensagem    VARCHAR(500) NOT NULL,
  lida        TINYINT(1) NOT NULL DEFAULT 0,
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_os FOREIGN KEY (os_id) REFERENCES ordens_servico(id) ON DELETE CASCADE,
  KEY idx_notif_usuario (usuario_id, lida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- configuracoes: chave/valor (tokens GestaoClick, segredo JWT etc)
-- ------------------------------------------------------------
CREATE TABLE configuracoes (
  chave   VARCHAR(100) PRIMARY KEY,
  valor   TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO configuracoes (chave, valor) VALUES
('gc_access_token',          'SEU_ACCESS_TOKEN_GESTAOCLICK'),
('gc_secret_access',         'SEU_SECRET_ACCESS_GESTAOCLICK'),
('gc_base_url',              'https://api.gestaoclick.com/'),
('gc_situacao_aberto',       ''),
('gc_situacao_em_andamento', ''),
('gc_situacao_pausado',      ''),
('gc_situacao_reagendado',   ''),
('gc_situacao_concluido',    ''),
('gc_situacao_cancelado',    ''),
('app_jwt_secret',           SHA2(UUID(), 256)),
('app_jwt_ttl_horas',        '12');

-- ------------------------------------------------------------
-- Usuario gestor inicial (senha: admin123 - TROCAR apos o primeiro login)
-- Hash gerado com password_hash('admin123', PASSWORD_BCRYPT)
-- ------------------------------------------------------------
INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo) VALUES
('Administrador', 'admin@smartvig.com.br', '$2y$10$q9wyUijtupbepoQl9V8.ce.fm7.wASBMCtT0h4wFvJ6NerP8t2JD6', 'gestor', 1);
