-- =====================================================================
-- ATUALIZAÇÃO 03: Financeiro (moedas, pagamentos, parcelas e recebíveis)
-- Para quem JÁ importou o schema.sql antes (e as atualizações 01 e 02). NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Os nomes das tabelas aqui já levam "nodaysoffclub." na frente, então funciona mesmo que o phpMyAdmin
-- esteja com outro banco selecionado.
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- Moeda e origem do "valor combinado" de cada paciente
ALTER TABLE nodaysoffclub.patient_billing ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'BRL' AFTER price;
ALTER TABLE nodaysoffclub.patient_billing ADD COLUMN IF NOT EXISTS source VARCHAR(10) NOT NULL DEFAULT 'manual' AFTER currency;

-- Moeda da renovação
ALTER TABLE nodaysoffclub.renewals ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'BRL' AFTER amount;

-- Pagamentos (uma venda/renovação) e parcelas (os recebíveis)
CREATE TABLE IF NOT EXISTS nodaysoffclub.payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  renewal_id INT UNSIGNED NULL,
  plan_id INT UNSIGNED NULL,
  total DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  method VARCHAR(12) NOT NULL DEFAULT 'outro',
  installments TINYINT UNSIGNED NOT NULL DEFAULT 1,
  sold_on DATE NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_payments_user (user_id),
  KEY idx_payments_sold (sold_on),
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES nodaysoffclub.users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nodaysoffclub.payment_installments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id INT UNSIGNED NOT NULL,
  number TINYINT UNSIGNED NOT NULL,
  due_on DATE NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  received_on DATE NULL,
  received_brl DECIMAL(10,2) NULL,
  PRIMARY KEY (id),
  KEY idx_inst_payment (payment_id),
  KEY idx_inst_due (due_on, status),
  CONSTRAINT fk_inst_payment FOREIGN KEY (payment_id) REFERENCES nodaysoffclub.payments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
