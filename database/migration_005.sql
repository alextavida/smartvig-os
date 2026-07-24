-- Migração 005 — Portal do cliente + SLA
-- Execute no phpMyAdmin ou via linha de comando MySQL

-- Token único para o portal público do cliente (UUID / hex 32 chars)
ALTER TABLE ordens_servico
  ADD COLUMN IF NOT EXISTS portal_token VARCHAR(64) NULL DEFAULT NULL
  COMMENT 'Token único para acesso público ao portal do cliente',
  ADD UNIQUE INDEX IF NOT EXISTS idx_portal_token (portal_token);

-- Preenche portal_token para OS existentes que ainda não têm
UPDATE ordens_servico
   SET portal_token = LOWER(HEX(RANDOM_BYTES(16)))
 WHERE portal_token IS NULL;
