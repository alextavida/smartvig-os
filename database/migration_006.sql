-- Migracao 006 — JWT TTL estendido para 720h (30 dias)
-- Execute no phpMyAdmin
INSERT INTO configuracoes (chave, valor)
  VALUES ('app_jwt_ttl_horas', '720')
  ON DUPLICATE KEY UPDATE valor = '720';
