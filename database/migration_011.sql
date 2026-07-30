-- Migration 011: Sistema de Solicitação de Compras + Múltiplos perfis por usuário
-- CORREÇÃO: usuarios.id é INT (signed), portanto todas as FKs para usuarios(id)
-- usam INT, não INT UNSIGNED.

SET FOREIGN_KEY_CHECKS = 0;

-- ================================================================
-- MÚLTIPLOS PERFIS POR USUÁRIO
-- Roles do módulo de compras: 'solicitante', 'comprador', 'aprovador'
-- O campo perfil original continua como perfil PRIMÁRIO.
-- ================================================================

CREATE TABLE IF NOT EXISTS usuario_roles (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id   INT          NOT NULL,
    role         VARCHAR(50)  NOT NULL,
    criado_em    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_role (usuario_id, role),
    CONSTRAINT fk_ur_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- FORNECEDORES
-- ================================================================

CREATE TABLE IF NOT EXISTS fornecedores (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome          VARCHAR(255)  NOT NULL,
    razao_social  VARCHAR(255)  NULL,
    cnpj          VARCHAR(20)   NULL,
    telefone      VARCHAR(30)   NULL,
    email         VARCHAR(255)  NULL,
    contato       VARCHAR(255)  NULL,
    observacoes   TEXT          NULL,
    ativo         TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- CATEGORIAS DE COMPRA
-- ================================================================

CREATE TABLE IF NOT EXISTS categorias_compra (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome      VARCHAR(100) NOT NULL,
    ativo     TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categorias_compra (id, nome) VALUES
    (1, 'Material Elétrico'),
    (2, 'Cabeamento'),
    (3, 'Câmeras e DVR'),
    (4, 'Alarmes e Sensores'),
    (5, 'Controle de Acesso'),
    (6, 'Ferramentas'),
    (7, 'EPI / Segurança'),
    (8, 'Informática'),
    (9, 'Serviços'),
    (10, 'Outros');

-- ================================================================
-- CENTROS DE CUSTO
-- ================================================================

CREATE TABLE IF NOT EXISTS centros_custo (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome      VARCHAR(100) NOT NULL,
    codigo    VARCHAR(30)  NULL,
    ativo     TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO centros_custo (id, nome, codigo) VALUES
    (1, 'Administrativo', 'ADM'),
    (2, 'Operações',      'OPE'),
    (3, 'Projetos',       'PRJ'),
    (4, 'Manutenção',     'MAN'),
    (5, 'Frota',          'FRT');

-- ================================================================
-- SOLICITAÇÕES DE COMPRA
-- usuarios.id é INT (signed) → solicitante_id, aprovador_id,
-- comprador_id e recebido_por_id também devem ser INT.
-- ================================================================

CREATE TABLE IF NOT EXISTS solicitacoes_compra (
    id                      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    numero                  VARCHAR(20)    NOT NULL,

    -- Solicitante
    solicitante_id          INT            NOT NULL,
    prioridade              ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
    destino                 ENUM('cliente','condominio','obra','estoque','manutencao','veiculo','outro') NOT NULL DEFAULT 'estoque',
    destino_referencia      VARCHAR(255)   NULL,
    centro_custo_id         INT UNSIGNED   NULL,
    categoria_id            INT UNSIGNED   NULL,
    justificativa           TEXT           NOT NULL,
    observacoes             TEXT           NULL,
    valor_estimado          DECIMAL(12,2)  NULL,

    -- Fluxo
    status ENUM(
        'rascunho',
        'aguardando_aprovacao',
        'aprovado',
        'reprovado',
        'devolvido',
        'em_compra',
        'recebido',
        'concluido',
        'cancelado'
    ) NOT NULL DEFAULT 'rascunho',

    -- Aprovação
    aprovador_id            INT            NULL,
    aprovado_em             DATETIME       NULL,
    motivo_reprovacao       TEXT           NULL,

    -- Compra
    comprador_id            INT            NULL,
    fornecedor_id           INT UNSIGNED   NULL,
    valor_negociado         DECIMAL(12,2)  NULL,
    valor_frete             DECIMAL(12,2)  NULL DEFAULT 0.00,
    frete_gratis            TINYINT(1)     NOT NULL DEFAULT 0,
    prazo_entrega           DATE           NULL,
    numero_pedido           VARCHAR(100)   NULL,
    data_compra             DATE           NULL,
    observacoes_compra      TEXT           NULL,
    valor_final             DECIMAL(12,2)  NULL,

    -- Nota Fiscal
    nota_fiscal_numero      VARCHAR(100)   NULL,
    nota_fiscal_data        DATE           NULL,
    nota_fiscal_arquivo     VARCHAR(500)   NULL,

    -- Recebimento
    recebido_por_id         INT            NULL,
    recebido_em             DATETIME       NULL,
    observacoes_recebimento TEXT           NULL,

    -- Auditoria
    criado_em               TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_numero (numero),
    KEY idx_status      (status),
    KEY idx_solicitante (solicitante_id),
    KEY idx_comprador   (comprador_id),
    KEY idx_criado_em   (criado_em),

    CONSTRAINT fk_sc_solicitante  FOREIGN KEY (solicitante_id)  REFERENCES usuarios (id),
    CONSTRAINT fk_sc_aprovador    FOREIGN KEY (aprovador_id)    REFERENCES usuarios (id),
    CONSTRAINT fk_sc_comprador    FOREIGN KEY (comprador_id)    REFERENCES usuarios (id),
    CONSTRAINT fk_sc_recebedor    FOREIGN KEY (recebido_por_id) REFERENCES usuarios (id),
    CONSTRAINT fk_sc_fornecedor   FOREIGN KEY (fornecedor_id)   REFERENCES fornecedores (id),
    CONSTRAINT fk_sc_centro_custo FOREIGN KEY (centro_custo_id) REFERENCES centros_custo (id),
    CONSTRAINT fk_sc_categoria    FOREIGN KEY (categoria_id)    REFERENCES categorias_compra (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- ITENS DA SOLICITAÇÃO
-- ================================================================

CREATE TABLE IF NOT EXISTS solicitacao_itens (
    id                    INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    solicitacao_id        INT UNSIGNED   NOT NULL,
    produto_codigo        VARCHAR(100)   NULL,
    produto_nome          VARCHAR(255)   NOT NULL,
    produto_unidade       VARCHAR(30)    NULL DEFAULT 'UN',
    categoria_id          INT UNSIGNED   NULL,
    quantidade            DECIMAL(10,3)  NOT NULL DEFAULT 1.000,
    quantidade_recebida   DECIMAL(10,3)  NULL,
    valor_estimado        DECIMAL(12,2)  NULL,
    valor_final           DECIMAL(12,2)  NULL,
    observacao            TEXT           NULL,
    gc_produto_id         INT UNSIGNED   NULL,
    PRIMARY KEY (id),
    KEY idx_solicitacao (solicitacao_id),
    CONSTRAINT fk_si_solicitacao FOREIGN KEY (solicitacao_id)
        REFERENCES solicitacoes_compra (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- ANEXOS
-- ================================================================

CREATE TABLE IF NOT EXISTS solicitacao_anexos (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    solicitacao_id INT UNSIGNED NOT NULL,
    usuario_id     INT          NULL,
    nome_original  VARCHAR(255) NOT NULL,
    caminho        VARCHAR(500) NOT NULL,
    tamanho        INT UNSIGNED NULL,
    tipo_mime      VARCHAR(100) NULL,
    criado_em      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_sa_solicitacao FOREIGN KEY (solicitacao_id)
        REFERENCES solicitacoes_compra (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- HISTÓRICO / AUDITORIA (NUNCA APAGAR)
-- ================================================================

CREATE TABLE IF NOT EXISTS solicitacao_historico (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    solicitacao_id INT UNSIGNED NOT NULL,
    usuario_id     INT          NULL,
    usuario_nome   VARCHAR(255) NULL,
    acao           VARCHAR(100) NOT NULL,
    detalhe        TEXT         NULL,
    criado_em      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_solicitacao (solicitacao_id),
    CONSTRAINT fk_sh_solicitacao FOREIGN KEY (solicitacao_id)
        REFERENCES solicitacoes_compra (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- FOTOS DE RECEBIMENTO
-- ================================================================

CREATE TABLE IF NOT EXISTS recebimento_fotos (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    solicitacao_id INT UNSIGNED NOT NULL,
    caminho        VARCHAR(500) NOT NULL,
    criado_em      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_rf_solicitacao FOREIGN KEY (solicitacao_id)
        REFERENCES solicitacoes_compra (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
