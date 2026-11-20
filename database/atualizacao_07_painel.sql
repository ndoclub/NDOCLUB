-- =====================================================================
-- ATUALIZAÇÃO 07: Painel gerencial (visão executiva)
-- Para quem JÁ importou o schema.sql e as atualizações 01 a 06. NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar.
-- Faça isto ANTES de abrir o sistema com os arquivos novos.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- "Paciente desde": dia em que o paciente começou o acompanhamento. É ele que o painel usa para contar
-- "novos pacientes" e a evolução da carteira (assim, cadastrar os 170 pacientes de uma vez não aparece
-- como 170 novos num único dia). Vazio = o painel usa a data do cadastro.
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS joined_on DATE NULL AFTER renewal_date;

-- Pacientes que já existem: começam na data do cadastro...
UPDATE nodaysoffclub.users SET joined_on = DATE(created_at) WHERE role = 'patient' AND joined_on IS NULL;

-- ...ou na data da primeira venda, quando ela for mais antiga que o cadastro.
UPDATE nodaysoffclub.users u
   SET u.joined_on = (SELECT MIN(p.sold_on) FROM nodaysoffclub.payments p WHERE p.user_id = u.id)
 WHERE u.role = 'patient'
   AND EXISTS (SELECT 1 FROM nodaysoffclub.payments p2 WHERE p2.user_id = u.id AND p2.sold_on < u.joined_on);
