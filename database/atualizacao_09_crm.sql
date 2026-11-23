-- =====================================================================
-- ATUALIZAÇÃO 09: CRM / Leads (tabelas e listas iniciais)
-- Para quem JÁ importou o schema.sql e as atualizações 01 a 08.1. NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar. Faça isso ANTES de abrir o sistema.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Os nomes das tabelas já levam "nodaysoffclub." na frente, então funciona mesmo que o phpMyAdmin
-- esteja com outro banco selecionado.
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- Liga o paciente ao lead de onde ele veio (usado na conversão, etapa 09.5)
ALTER TABLE nodaysoffclub.users ADD COLUMN IF NOT EXISTS lead_id INT UNSIGNED NULL AFTER app_access;

CREATE TABLE IF NOT EXISTS nodaysoffclub.lead_sources (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'other',
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lead_sources_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nodaysoffclub.lead_stages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  kind VARCHAR(8) NOT NULL DEFAULT 'open',
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lead_stages_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nodaysoffclub.lead_lost_reasons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lead_lost_reasons_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nodaysoffclub.leads (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NULL,
  phone_key VARCHAR(12) NULL,
  email VARCHAR(160) NULL,
  instagram VARCHAR(30) NULL,
  sport_id INT UNSIGNED NULL,
  goal VARCHAR(160) NULL,
  plan_id INT UNSIGNED NULL,
  source_id INT UNSIGNED NULL,
  channel VARCHAR(20) NULL,
  referred_by INT UNSIGNED NULL,
  responsible_id INT UNSIGNED NULL,
  temperature VARCHAR(8) NOT NULL DEFAULT 'warm',
  stage_id INT UNSIGNED NOT NULL,
  status VARCHAR(8) NOT NULL DEFAULT 'open',
  entered_on DATE NOT NULL,
  notes VARCHAR(1000) NULL,
  contact_ok TINYINT(1) NOT NULL DEFAULT 0,
  contact_ok_at DATETIME NULL,
  do_not_contact TINYINT(1) NOT NULL DEFAULT 0,
  do_not_contact_at DATETIME NULL,
  last_contact_at DATETIME NULL,
  next_on DATE NULL,
  next_type VARCHAR(16) NULL,
  next_responsible_id INT UNSIGNED NULL,
  lost_reason_id INT UNSIGNED NULL,
  lost_note VARCHAR(300) NULL,
  lost_at DATETIME NULL,
  recovery_status VARCHAR(12) NULL,
  recovery_step TINYINT UNSIGNED NOT NULL DEFAULT 0,
  cycle SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  patient_id INT UNSIGNED NULL,
  converted_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_leads_phone (phone_key),
  KEY idx_leads_email (email),
  KEY idx_leads_stage (status, stage_id),
  KEY idx_leads_next (next_on),
  CONSTRAINT fk_leads_stage FOREIGN KEY (stage_id) REFERENCES nodaysoffclub.lead_stages (id),
  CONSTRAINT fk_leads_patient FOREIGN KEY (patient_id) REFERENCES nodaysoffclub.users (id) ON DELETE SET NULL,
  CONSTRAINT fk_leads_referred FOREIGN KEY (referred_by) REFERENCES nodaysoffclub.users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nodaysoffclub.lead_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id INT UNSIGNED NOT NULL,
  kind VARCHAR(12) NOT NULL,
  happened_at DATETIME NOT NULL,
  by_user_id INT UNSIGNED NULL,
  channel VARCHAR(20) NULL,
  summary VARCHAR(600) NULL,
  result VARCHAR(16) NULL,
  notes VARCHAR(600) NULL,
  next_action VARCHAR(160) NULL,
  from_value VARCHAR(80) NULL,
  to_value VARCHAR(80) NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'manual',
  external_id VARCHAR(80) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_lead_events_lead (lead_id, happened_at),
  CONSTRAINT fk_lead_events_lead FOREIGN KEY (lead_id) REFERENCES nodaysoffclub.leads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nodaysoffclub.lead_conversions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id INT UNSIGNED NOT NULL,
  patient_id INT UNSIGNED NULL,
  plan_id INT UNSIGNED NULL,
  contracted_value DECIMAL(10,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  contracted_on DATE NULL,
  start_on DATE NULL,
  payment_conditions VARCHAR(160) NULL,
  responsible_id INT UNSIGNED NULL,
  source_id INT UNSIGNED NULL,
  by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_lead_conversions_lead (lead_id),
  CONSTRAINT fk_lead_conversions_lead FOREIGN KEY (lead_id) REFERENCES nodaysoffclub.leads (id) ON DELETE CASCADE,
  CONSTRAINT fk_lead_conversions_patient FOREIGN KEY (patient_id) REFERENCES nodaysoffclub.users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listas iniciais (INSERT IGNORE: se o nome já existir, não repete). Editáveis em Admin > CRM > Configurar.
INSERT IGNORE INTO nodaysoffclub.lead_sources (name, kind, sort_order, active) VALUES
('Instagram orgânico', 'other', 1, 1),
('Instagram Ads', 'other', 2, 1),
('Indicação de paciente', 'patient_referral', 3, 1),
('Indicação profissional', 'other', 4, 1),
('WhatsApp', 'other', 5, 1),
('Site', 'other', 6, 1),
('TikTok', 'other', 7, 1),
('Eventos e parcerias', 'other', 8, 1),
('Outros', 'other', 9, 1);

INSERT IGNORE INTO nodaysoffclub.lead_stages (name, kind, sort_order, active) VALUES
('Novo lead', 'open', 1, 1),
('Primeiro contato', 'open', 2, 1),
('Qualificação', 'open', 3, 1),
('Engajamento', 'open', 4, 1),
('Proposta enviada', 'open', 5, 1),
('Negociação', 'open', 6, 1),
('Convertido', 'won', 7, 1),
('Perdido', 'lost', 8, 1);

INSERT IGNORE INTO nodaysoffclub.lead_lost_reasons (name, sort_order, active) VALUES
('Preço / investimento', 1, 1),
('Parou de responder', 2, 1),
('Sem interesse no momento', 3, 1),
('Escolheu outro profissional', 4, 1),
('Falta de tempo', 5, 1),
('Fora da região ou do formato de atendimento', 6, 1),
('Outro motivo', 7, 1);
