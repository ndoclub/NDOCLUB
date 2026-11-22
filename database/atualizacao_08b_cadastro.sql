-- =====================================================================
-- ATUALIZAÇÃO 08.1: Cadastro (sexo, CPF, Instagram e endereço completo)
-- Para quem JÁ importou o schema.sql e as atualizações 01 a 08. NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar.
-- Faça isto ANTES de abrir o sistema com os arquivos novos.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- Todos os campos são opcionais e só o admin vê (nunca o paciente).
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- Sexo: 'F' (feminino) ou 'M' (masculino). Vazio = não informado. Alimenta o gráfico da carteira no painel.
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS sex CHAR(1) NULL AFTER msg_optin_at;

-- CPF (só os 11 números), usado para emitir recibo e nota fiscal.
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS cpf CHAR(11) NULL AFTER sex;

-- Instagram (só o nome de usuário, sem o @).
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS instagram VARCHAR(30) NULL AFTER cpf;

-- Endereço completo. País em branco = Brasil. Para quem mora fora, CEP e estado são texto livre.
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS addr_zip VARCHAR(12) NULL AFTER instagram;
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS addr_street VARCHAR(120) NULL AFTER addr_zip;
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS addr_number VARCHAR(20) NULL AFTER addr_street;
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS addr_complement VARCHAR(80) NULL AFTER addr_number;
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS addr_district VARCHAR(80) NULL AFTER addr_complement;
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS addr_city VARCHAR(80) NULL AFTER addr_district;
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS addr_state VARCHAR(40) NULL AFTER addr_city;
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS addr_country VARCHAR(60) NULL AFTER addr_state;

-- Índice para achar rápido um CPF repetido.
CREATE INDEX IF NOT EXISTS idx_users_cpf ON nodaysoffclub.users (cpf);
