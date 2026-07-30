-- Migration 012: gc_id em fornecedores para rastrear origem GestaoClick

ALTER TABLE fornecedores
  ADD COLUMN IF NOT EXISTS gc_id INT NULL DEFAULT NULL COMMENT 'ID do fornecedor no GestaoClick',
  ADD UNIQUE KEY IF NOT EXISTS uk_fornecedor_gc_id (gc_id);
