-- =====================================================================
-- ATUALIZAÇÃO 05: Pacientes v2 (consultas presenciais, cota por plano e responsáveis / "modo babá")
-- Para quem JÁ importou o schema.sql e as atualizações 01 a 04. NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Os nomes das tabelas já levam "nodaysoffclub." na frente, então funciona mesmo que o phpMyAdmin
-- esteja com outro banco selecionado.
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- Quantas consultas presenciais cada plano inclui (vazio = ainda não definido; preencha em Admin > Planos)
ALTER TABLE nodaysoffclub.plans ADD COLUMN IF NOT EXISTS consultations_included TINYINT UNSIGNED NULL AFTER price;

-- Quem é o responsável por cada paciente (estagiária do "modo babá")
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS responsible_id INT UNSIGNED NULL AFTER plan_id;

-- Lista de responsáveis (cadastrada em Admin > Responsáveis; não é apagada, só desativada)
CREATE TABLE IF NOT EXISTS nodaysoffclub.responsibles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_responsibles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consultas presenciais realizadas (uma linha por consulta). "cycle" = número de renovações do paciente
-- quando a consulta foi registrada: ao renovar o plano, o contador "x de y" recomeça.
CREATE TABLE IF NOT EXISTS nodaysoffclub.consultations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  held_on DATE NOT NULL,
  cycle SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  note VARCHAR(160) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_consultations_user (user_id, cycle),
  CONSTRAINT fk_consultations_user FOREIGN KEY (user_id) REFERENCES nodaysoffclub.users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
