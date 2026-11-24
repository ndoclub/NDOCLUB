-- =====================================================================
-- ATUALIZAÇÃO 09.2: CRM - indicadores, responsáveis, follow-up, reativação e conversão
-- Para quem JÁ importou a atualização 09 (atualizacao_09_crm.sql). NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar. Faça isso ANTES de abrir o sistema.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Os nomes das tabelas já levam "nodaysoffclub." na frente.
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- Ordem das atualizações de banco: ... 08.1 > 09 > 09.2 (esta).
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- 1) Etapa que conta na "receita potencial em negociação" (começa em Proposta enviada e Negociação;
--    dá para mudar em CRM > Configurar). Só é preenchida na primeira vez que este arquivo roda.
ALTER TABLE nodaysoffclub.lead_stages ADD COLUMN IF NOT EXISTS potential TINYINT(1) NOT NULL DEFAULT 0 AFTER active;

UPDATE nodaysoffclub.lead_stages SET potential = 1
 WHERE kind = 'open' AND name IN ('Proposta enviada', 'Negociação')
   AND NOT EXISTS (SELECT 1 FROM nodaysoffclub.settings WHERE skey = 'lead_recovery_days');

-- 2) A conversão guarda qual venda (Financeiro) ela gerou e se o paciente foi criado ou já existia
ALTER TABLE nodaysoffclub.lead_conversions ADD COLUMN IF NOT EXISTS payment_id INT UNSIGNED NULL AFTER by_user_id;
ALTER TABLE nodaysoffclub.lead_conversions ADD COLUMN IF NOT EXISTS mode VARCHAR(8) NOT NULL DEFAULT 'new' AFTER payment_id;

-- 3) Cadência de reativação: dias depois da perda em que se tenta falar de novo (editável em CRM > Configurar)
INSERT IGNORE INTO nodaysoffclub.settings (skey, svalue) VALUES ('lead_recovery_days', '20,30,45');

-- Conferência: devem aparecer 3 linhas (as 3 colunas novas)
SELECT table_name AS tabela, column_name AS coluna_nova
  FROM information_schema.columns
 WHERE table_schema = 'nodaysoffclub'
   AND ((table_name = 'lead_stages' AND column_name = 'potential')
     OR (table_name = 'lead_conversions' AND column_name IN ('payment_id', 'mode')))
 ORDER BY table_name, column_name;
