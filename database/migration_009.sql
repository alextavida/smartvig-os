-- Migration 009: checklist_json em ordens_servico + gc_orcamento_id em orcamentos
-- Executar no phpMyAdmin ou via mysql CLI

-- Checklist da OS (itens JSON: [{texto, concluido}])
ALTER TABLE ordens_servico
  ADD COLUMN IF NOT EXISTS checklist_json TEXT NULL COMMENT 'Itens do checklist JSON' AFTER nps_token;

-- ID do orcamento no GestaoClick (para sincronizacao)
ALTER TABLE orcamentos
  ADD COLUMN IF NOT EXISTS gc_orcamento_id INT UNSIGNED NULL COMMENT 'ID do orcamento no GestaoClick' AFTER gc_cliente_id;

-- gc_cliente_id em ordens_servico (caso ainda nao exista)
ALTER TABLE ordens_servico
  ADD COLUMN IF NOT EXISTS gc_cliente_id INT UNSIGNED NULL COMMENT 'ID do cliente no GestaoClick' AFTER gc_os_id;
