-- =====================================================================
-- ATUALIZAÇÃO 06: Importação de pacientes por planilha (modelo oficial NDO)
-- Para quem JÁ importou o schema.sql e as atualizações 01 a 05. NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar.
-- Faça isto ANTES de abrir o sistema com os arquivos novos.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- Quem pode entrar no app: 1 = pode entrar (todos os pacientes que já existem continuam podendo);
-- 0 = paciente importado por planilha que ainda não recebeu acesso (sem senha, não entra, não aparece
-- no ranking nem para os outros pacientes). O acesso é liberado em "Gerar nova senha provisória".
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS app_access TINYINT(1) NOT NULL DEFAULT 1 AFTER must_change_password;
