-- =====================================================================
-- ATUALIZAÇÃO 11: Jornada do paciente, parte 1 (Renovação e Saída)
-- Para quem JÁ tem o sistema instalado. NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar. Faça isso ANTES de abrir o sistema.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Os nomes das tabelas já levam "nodaysoffclub." na frente.
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- Ordem das atualizações de banco: ... 09 > 09.2 > 11 (esta). As 09.3, 09.3.1, 09.4 e 09.5 não têm SQL.
-- (A Atualização 10, Equipe e perfis, ainda não foi feita: o 11 não depende dela.)
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- 1) Tipo de jornada de cada plano (premium = com consultas e dossiê, smart, escalavel = sem consultas, mensal).
--    Todo plano que já existe começa como "premium"; o Gabriel muda em Planos, se precisar.
ALTER TABLE nodaysoffclub.plans ADD COLUMN IF NOT EXISTS journey_type VARCHAR(10) NOT NULL DEFAULT 'premium' AFTER consultations_included;

-- 2) Saída do paciente: motivo, dia, observação e (se for pausa) dia de recontato
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS exit_reason VARCHAR(16) NULL AFTER lead_id;
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS exited_on DATE NULL AFTER exit_reason;
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS exit_note VARCHAR(255) NULL AFTER exited_on;
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS recontact_on DATE NULL AFTER exit_note;

-- 3) Dados de cada ciclo do plano (data da última consulta, resposta de renovação)
CREATE TABLE IF NOT EXISTS nodaysoffclub.journey_cycles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  cycle SMALLINT UNSIGNED NOT NULL,
  last_consult_on DATE NULL,
  answer VARCHAR(14) NULL,
  answer_on DATE NULL,
  agreed_on DATE NULL,
  next_consult_on DATE NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_journey_cycle (user_id, cycle),
  CONSTRAINT fk_jcycle_user FOREIGN KEY (user_id) REFERENCES nodaysoffclub.users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) As tarefas da Jornada (dossiê, última consulta, contato de renovação etc.)
CREATE TABLE IF NOT EXISTS nodaysoffclub.journey_tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  cycle SMALLINT UNSIGNED NOT NULL,
  code VARCHAR(24) NOT NULL,
  assignee VARCHAR(40) NULL,
  status VARCHAR(8) NOT NULL DEFAULT 'pending',
  due_on DATE NULL,
  done_at DATETIME NULL,
  done_by INT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_journey_task (user_id, cycle, code),
  KEY idx_jtasks_status (status, due_on),
  CONSTRAINT fk_jtask_user FOREIGN KEY (user_id) REFERENCES nodaysoffclub.users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Linha do tempo (nunca é editada nem apagada pelo sistema: correção = nova linha)
CREATE TABLE IF NOT EXISTS nodaysoffclub.journey_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  cycle SMALLINT UNSIGNED NULL,
  code VARCHAR(24) NULL,
  kind VARCHAR(16) NOT NULL,
  summary VARCHAR(255) NOT NULL,
  by_user_id INT UNSIGNED NULL,
  happened_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_jevents_user (user_id, happened_at),
  CONSTRAINT fk_jevent_user FOREIGN KEY (user_id) REFERENCES nodaysoffclub.users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Dia em que a Jornada começa a valer: hoje (só na primeira vez que este arquivo roda).
--    O que venceu ANTES deste dia aparece como "anterior ao sistema" e não vira tarefa atrasada.
--    Dá para mudar depois em Jornada > Ajustes.
INSERT IGNORE INTO nodaysoffclub.settings (skey, svalue) VALUES ('journey_start_on', CURDATE());

-- Conferência: a primeira lista deve ter 5 linhas (1 coluna em plans e 4 em users) e a segunda, 3 tabelas
SELECT table_name AS tabela, column_name AS coluna_nova
  FROM information_schema.columns
 WHERE table_schema = 'nodaysoffclub'
   AND ((table_name = 'plans' AND column_name = 'journey_type')
     OR (table_name = 'users' AND column_name IN ('exit_reason', 'exited_on', 'exit_note', 'recontact_on')))
 ORDER BY table_name, column_name;

SELECT table_name AS tabela_nova
  FROM information_schema.tables
 WHERE table_schema = 'nodaysoffclub' AND table_name IN ('journey_cycles', 'journey_tasks', 'journey_events')
 ORDER BY table_name;
