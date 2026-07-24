-- Migração 004 — Timer de atendimento + SLA/Prazo
-- Execute no phpMyAdmin ou via linha de comando MySQL

-- Timer: tempo total de atendimento em segundos (acumulado entre iniciar/pausar)
ALTER TABLE ordens_servico
  ADD COLUMN IF NOT EXISTS tempo_atendimento_segundos INT UNSIGNED NULL DEFAULT NULL
  COMMENT 'Tempo total de atendimento em segundos (acumulado)';

-- SLA: prazo máximo de conclusão definido pelo gestor
ALTER TABLE ordens_servico
  ADD COLUMN IF NOT EXISTS data_prazo DATE NULL DEFAULT NULL
  COMMENT 'Prazo limite para conclusao da OS (SLA)';

-- Índice para consultas de OS com prazo vencido
ALTER TABLE ordens_servico
  ADD INDEX IF NOT EXISTS idx_data_prazo (data_prazo);
