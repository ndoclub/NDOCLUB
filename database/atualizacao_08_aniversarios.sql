-- =====================================================================
-- ATUALIZAÇÃO 08: Aniversários (data de nascimento + autorização de mensagens)
-- Para quem JÁ importou o schema.sql e as atualizações 01 a 07. NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar.
-- Faça isto ANTES de abrir o sistema com os arquivos novos.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- Data de nascimento (opcional). Só o admin vê. Serve para o bloco "Aniversariantes" do painel.
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS birth_date DATE NULL AFTER joined_on;

-- Autorização para receber mensagens (aniversário e novidades). 0 = não autorizou (padrão).
-- msg_optin_at guarda o dia e a hora em que foi marcado (para comprovar a autorização).
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS msg_optin TINYINT(1) NOT NULL DEFAULT 0 AFTER birth_date;
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS msg_optin_at DATETIME NULL AFTER msg_optin;

-- Texto da mensagem de aniversário (editável em Admin > Configurações). {nome} vira o primeiro nome.
INSERT INTO nodaysoffclub.settings (skey, svalue)
SELECT 'birthday_message', 'Feliz aniversário, {nome}! 🎉 Que este novo ciclo venha com muita saúde, energia e conquistas. Conte comigo e com a equipe do No Days Off Club. Um abraço!'
WHERE NOT EXISTS (SELECT 1 FROM nodaysoffclub.settings WHERE skey = 'birthday_message');
