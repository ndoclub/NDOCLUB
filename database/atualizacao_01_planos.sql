-- =====================================================================
-- ATUALIZAÇÃO 01: Planos (Trimestral, Semestral, Anual) e valores
-- Para quem JÁ importou o schema.sql antes. NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  duration_days INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patient_billing (
  user_id INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_billing_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN IF NOT EXISTS plan_id INT UNSIGNED NULL AFTER renewal_date;
ALTER TABLE renewals ADD COLUMN IF NOT EXISTS plan_id INT UNSIGNED NULL AFTER days_added;
ALTER TABLE renewals ADD COLUMN IF NOT EXISTS amount DECIMAL(10,2) NULL AFTER plan_id;

-- Planos iniciais (só cria se ainda não existir nenhum). Os valores em R$ você preenche em Admin > Planos.
INSERT INTO plans (name, duration_days, price, active, created_at)
SELECT 'Trimestral', 90, NULL, 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans);
INSERT INTO plans (name, duration_days, price, active, created_at)
SELECT 'Semestral', 180, NULL, 1, NOW() FROM DUAL WHERE (SELECT COUNT(*) FROM plans) = 1;
INSERT INTO plans (name, duration_days, price, active, created_at)
SELECT 'Anual', 365, NULL, 1, NOW() FROM DUAL WHERE (SELECT COUNT(*) FROM plans) = 2;

-- ---------------------------------------------------------------------
-- OPCIONAL: ligar os pacientes de TESTE (*.teste@exemplo.com) a um plano, para ver na tela.
-- Só mexe nos e-mails de teste; pacientes reais não são tocados.
-- ---------------------------------------------------------------------
UPDATE users SET plan_id = (SELECT id FROM plans WHERE name = 'Anual' ORDER BY id LIMIT 1)      WHERE plan_id IS NULL AND email IN ('ana.teste@exemplo.com', 'karen.teste@exemplo.com');
UPDATE users SET plan_id = (SELECT id FROM plans WHERE name = 'Semestral' ORDER BY id LIMIT 1)  WHERE plan_id IS NULL AND email IN ('bruno.teste@exemplo.com', 'elisa.teste@exemplo.com', 'isabela.teste@exemplo.com');
UPDATE users SET plan_id = (SELECT id FROM plans WHERE name = 'Trimestral' ORDER BY id LIMIT 1) WHERE plan_id IS NULL AND email IN ('carla.teste@exemplo.com', 'diego.teste@exemplo.com', 'felipe.teste@exemplo.com', 'gabi.teste@exemplo.com', 'heitor.teste@exemplo.com', 'joao.teste@exemplo.com', 'lucas.teste@exemplo.com', 'novo.teste@exemplo.com');
