-- Migracao 007 — Tabelas de orcamentos
-- Execute no phpMyAdmin

CREATE TABLE IF NOT EXISTS orcamentos (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  codigo         VARCHAR(20)  NOT NULL,
  gc_cliente_id  VARCHAR(50)  DEFAULT NULL,
  cliente_nome   VARCHAR(255) NOT NULL,
  cliente_email  VARCHAR(255) DEFAULT NULL,
  cliente_telefone VARCHAR(30) DEFAULT NULL,
  observacoes    TEXT         DEFAULT NULL,
  validade_dias  TINYINT UNSIGNED NOT NULL DEFAULT 7,
  status         ENUM('rascunho','enviado','aprovado','recusado','convertido') NOT NULL DEFAULT 'rascunho',
  token          VARCHAR(64)  NOT NULL UNIQUE,
  os_id_gerada   INT          DEFAULT NULL,
  criado_por     INT          DEFAULT NULL,
  criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (criado_por)   REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (os_id_gerada) REFERENCES ordens_servico(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orcamento_itens (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  orcamento_id     INT NOT NULL,
  tipo             ENUM('servico','peca') NOT NULL DEFAULT 'servico',
  descricao        VARCHAR(255) NOT NULL,
  quantidade       DECIMAL(10,2) NOT NULL DEFAULT 1,
  valor_unitario   DECIMAL(10,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
