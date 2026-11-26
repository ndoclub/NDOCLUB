-- =====================================================================
-- ATUALIZAÇÃO 12: Jornada do paciente, parte 2 (Entrada) + troca flexível de responsável
-- Para quem JÁ tem o sistema instalado. NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar. Faça isso ANTES de abrir o sistema.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Os nomes das tabelas já levam "nodaysoffclub." na frente.
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- Ordem das atualizações de banco: ... 09 > 09.2 > 11 > 12 (esta). Precisa do arquivo 11 já importado.
-- (A Atualização 10, Equipe e perfis, ainda não foi feita: o 12 não depende dela.)
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- 1) Plano com treino: o Gabriel monta o treino em até 2 dias úteis depois da 1ª consulta.
--    Todo plano que já existe começa SEM treino; marque em Planos os que incluem.
ALTER TABLE nodaysoffclub.plans ADD COLUMN IF NOT EXISTS with_training TINYINT(1) NOT NULL DEFAULT 0 AFTER journey_type;

-- 2) Entrada do paciente: dia em que abriu, 1ª consulta marcada e (Escalável) formulário recebido.
--    Quem já está na carteira NÃO ganha Entrada (entry_on fica vazio): ela é só para paciente novo ou que voltou.
ALTER TABLE nodaysoffclub.journey_cycles ADD COLUMN IF NOT EXISTS entry_on DATE NULL AFTER next_consult_on;
ALTER TABLE nodaysoffclub.journey_cycles ADD COLUMN IF NOT EXISTS entry_kind VARCHAR(10) NULL AFTER entry_on;
ALTER TABLE nodaysoffclub.journey_cycles ADD COLUMN IF NOT EXISTS first_consult_on DATE NULL AFTER entry_kind;
ALTER TABLE nodaysoffclub.journey_cycles ADD COLUMN IF NOT EXISTS form_on DATE NULL AFTER first_consult_on;

-- 3) Troca de responsável: quando alguém passa uma tarefa para outra pessoa, ela fica "travada" nessa pessoa
--    (o sistema não devolve sozinho para o responsável padrão).
ALTER TABLE nodaysoffclub.journey_tasks ADD COLUMN IF NOT EXISTS assignee_manual TINYINT(1) NOT NULL DEFAULT 0 AFTER assignee;

-- Conferência: a primeira lista deve ter 6 linhas e mostra as colunas novas
SELECT table_name AS tabela, column_name AS coluna_nova
  FROM information_schema.columns
 WHERE table_schema = 'nodaysoffclub'
   AND ((table_name = 'plans' AND column_name = 'with_training')
     OR (table_name = 'journey_cycles' AND column_name IN ('entry_on', 'entry_kind', 'first_consult_on', 'form_on'))
     OR (table_name = 'journey_tasks' AND column_name = 'assignee_manual'))
 ORDER BY table_name, column_name;
