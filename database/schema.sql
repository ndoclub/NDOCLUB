-- =====================================================================
-- No Days Off Club - Banco de dados (MySQL / MariaDB do XAMPP)
-- Como usar: phpMyAdmin > aba "Importar" > escolher este arquivo > Executar.
-- Ele cria o banco "nodaysoffclub", as tabelas e os dados iniciais.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS nodaysoffclub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nodaysoffclub;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Usuários (pacientes e administradores)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role ENUM('patient','admin') NOT NULL DEFAULT 'patient',
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(30) NULL,
  avatar VARCHAR(80) NULL,
  bio VARCHAR(280) NULL,
  goal VARCHAR(120) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  renewal_date DATE NULL,
  joined_on DATE NULL,
  birth_date DATE NULL,
  msg_optin TINYINT(1) NOT NULL DEFAULT 0,
  msg_optin_at DATETIME NULL,
  sex CHAR(1) NULL,
  cpf CHAR(11) NULL,
  instagram VARCHAR(30) NULL,
  addr_zip VARCHAR(12) NULL,
  addr_street VARCHAR(120) NULL,
  addr_number VARCHAR(20) NULL,
  addr_complement VARCHAR(80) NULL,
  addr_district VARCHAR(80) NULL,
  addr_city VARCHAR(80) NULL,
  addr_state VARCHAR(40) NULL,
  addr_country VARCHAR(60) NULL,
  plan_id INT UNSIGNED NULL,
  responsible_id INT UNSIGNED NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  app_access TINYINT(1) NOT NULL DEFAULT 1,
  lead_id INT UNSIGNED NULL,
  consent_at DATETIME NULL,
  consent_version VARCHAR(12) NULL,
  last_seen_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status),
  KEY idx_users_cpf (cpf),
  KEY idx_users_renewal (renewal_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Planos (Trimestral, Semestral, Anual...) e dados financeiros.
-- ATENÇÃO: valores em R$ são só do painel admin. O paciente nunca vê.
-- Planos não são apagados, só desativados (users.plan_id aponta para eles).
-- ---------------------------------------------------------------------
CREATE TABLE plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  duration_days INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NULL,
  consultations_included TINYINT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valor combinado com um paciente (quando diferente do valor do plano).
-- currency: BRL, USD ou EUR. source: 'manual' (combinado pelo Gabriel) ou
-- 'reajuste' (valor antigo mantido até a próxima renovação, depois que o plano mudou de preço).
CREATE TABLE patient_billing (
  user_id INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  source VARCHAR(10) NOT NULL DEFAULT 'manual',
  PRIMARY KEY (user_id),
  CONSTRAINT fk_billing_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Financeiro: pagamentos e parcelas (só o painel admin vê; o paciente NUNCA vê).
-- Um pagamento = uma venda/renovação (faturamento). As parcelas são os recebíveis:
-- cada uma tem data prevista, valor e se já caiu. Moedas: BRL, USD, EUR.
-- Para USD/EUR, o total em R$ usa o valor que CREDITOU na conta (received_brl).
-- ---------------------------------------------------------------------
CREATE TABLE payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  renewal_id INT UNSIGNED NULL,
  plan_id INT UNSIGNED NULL,
  total DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  method VARCHAR(12) NOT NULL DEFAULT 'outro',
  card_brand VARCHAR(12) NULL,
  fee_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  installments TINYINT UNSIGNED NOT NULL DEFAULT 1,
  sold_on DATE NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_payments_user (user_id),
  KEY idx_payments_sold (sold_on),
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_installments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id INT UNSIGNED NOT NULL,
  number TINYINT UNSIGNED NOT NULL,
  due_on DATE NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  net_amount DECIMAL(10,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  received_on DATE NULL,
  received_brl DECIMAL(10,2) NULL,
  proof_file VARCHAR(48) NULL,
  PRIMARY KEY (id),
  KEY idx_inst_payment (payment_id),
  KEY idx_inst_due (due_on, status),
  CONSTRAINT fk_inst_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Taxas do cartão (percentual descontado de cada parcela): bandeira x faixa de parcelas
-- (band 1 = à vista, 2 = 2 a 6x, 3 = 7 a 12x). Cadastradas pelo admin em Financeiro > Taxas do cartão.
CREATE TABLE payment_fees (
  brand VARCHAR(12) NOT NULL,
  band TINYINT UNSIGNED NOT NULL,
  pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (brand, band)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Perfil esportivo (atletas). Modalidades são cadastradas pelo Gabriel (Admin > Modalidades)
-- e NÃO são apagadas, só desativadas. Cada paciente pode ter várias modalidades.
-- ---------------------------------------------------------------------
CREATE TABLE sports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(40) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sports_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE athlete_profiles (
  user_id INT UNSIGNED NOT NULL,
  is_athlete TINYINT(1) NOT NULL DEFAULT 1,
  has_coach TINYINT(1) NOT NULL DEFAULT 0,
  coach_name VARCHAR(80) NULL,
  competes TINYINT(1) NOT NULL DEFAULT 0,
  event_name VARCHAR(100) NULL,
  event_date DATE NULL,
  show_public TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (user_id),
  KEY idx_athlete_event (event_date),
  CONSTRAINT fk_athlete_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sports (
  user_id INT UNSIGNED NOT NULL,
  sport_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, sport_id),
  CONSTRAINT fk_usersports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_usersports_sport FOREIGN KEY (sport_id) REFERENCES sports (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentativas de login (limite contra força bruta)
CREATE TABLE login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(160) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_attempts_email (email, created_at),
  KEY idx_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações gerais (editáveis no painel)
CREATE TABLE settings (
  skey VARCHAR(60) NOT NULL,
  svalue TEXT NULL,
  PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pontos
-- ---------------------------------------------------------------------
CREATE TABLE point_rules (
  rkey VARCHAR(30) NOT NULL,
  label VARCHAR(80) NOT NULL,
  points INT NOT NULL DEFAULT 0,
  daily_limit INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (rkey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extrato: toda movimentação de pontos fica registrada aqui (nunca se apaga)
CREATE TABLE points_ledger (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  rule_key VARCHAR(30) NOT NULL,
  points INT NOT NULL,
  counts_ranking TINYINT(1) NOT NULL DEFAULT 1,
  ref_type VARCHAR(20) NULL,
  ref_id INT UNSIGNED NULL,
  note VARCHAR(200) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ledger_ref (user_id, rule_key, ref_type, ref_id),
  KEY idx_ledger_user_date (user_id, created_at),
  KEY idx_ledger_rank (counts_ranking, created_at),
  CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Check-in diário, treino e dieta
-- ---------------------------------------------------------------------
CREATE TABLE checkins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  checkin_date DATE NOT NULL,
  sleep_hours TINYINT UNSIGNED NOT NULL,
  water_ml SMALLINT UNSIGNED NOT NULL,
  diet_score TINYINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checkin_day (user_id, checkin_date),
  CONSTRAINT fk_checkin_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workouts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  title VARCHAR(80) NOT NULL,
  details TEXT NULL,
  weekday TINYINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_workouts_user (user_id, active),
  CONSTRAINT fk_workouts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workout_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  workout_id INT UNSIGNED NOT NULL,
  done_on DATE NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_workout_day (user_id, workout_id, done_on),
  KEY idx_wlog_user_date (user_id, done_on),
  CONSTRAINT fk_wlog_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_wlog_workout FOREIGN KEY (workout_id) REFERENCES workouts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE diets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  link_url VARCHAR(500) NULL,
  pdf_file VARCHAR(80) NULL,
  notes TEXT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_diets_user (user_id),
  CONSTRAINT fk_diets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Comunidade (feed)
-- ---------------------------------------------------------------------
CREATE TABLE posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  image VARCHAR(80) NULL,
  theme VARCHAR(80) NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('visible','removed') NOT NULL DEFAULT 'visible',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_posts_feed (status, is_pinned, created_at),
  KEY idx_posts_user (user_id, created_at),
  CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  body VARCHAR(500) NOT NULL,
  status ENUM('visible','removed') NOT NULL DEFAULT 'visible',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_comments_post (post_id, created_at),
  CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_likes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_like (post_id, user_id),
  CONSTRAINT fk_likes_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id INT UNSIGNED NOT NULL,
  target_type ENUM('post','comment') NOT NULL,
  target_id INT UNSIGNED NOT NULL,
  reason VARCHAR(200) NOT NULL,
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  resolved_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_report (reporter_id, target_type, target_id),
  KEY idx_reports_status (status, created_at),
  CONSTRAINT fk_reports_user FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Loja de brindes
-- ---------------------------------------------------------------------
CREATE TABLE store_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(300) NULL,
  image VARCHAR(80) NULL,
  cost_points INT UNSIGNED NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_store_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE redemptions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  cost_points INT UNSIGNED NOT NULL,
  status ENUM('pending','approved','delivered','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(200) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_redemptions_status (status, created_at),
  KEY idx_redemptions_user (user_id),
  CONSTRAINT fk_redemptions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_redemptions_item FOREIGN KEY (item_id) REFERENCES store_items (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Evolução (dados de saúde: privados, só o paciente e o admin enxergam)
-- ---------------------------------------------------------------------
CREATE TABLE measurements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  measured_on DATE NOT NULL,
  weight_kg DECIMAL(5,2) NOT NULL,
  waist_cm DECIMAL(5,1) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_measure_day (user_id, measured_on),
  CONSTRAINT fk_measure_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Responsáveis ("modo babá": cada estagiária cuida de alguns pacientes) e consultas presenciais.
-- Responsáveis não são apagados, só desativados. Só o admin vê isso (nunca o paciente).
-- Consultas: cada plano tem uma cota (plans.consultations_included). A coluna "cycle" é o número de
-- renovações que o paciente tinha quando a consulta foi registrada; ao renovar, o contador "x de y" recomeça.
-- ---------------------------------------------------------------------
CREATE TABLE responsibles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_responsibles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE consultations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  held_on DATE NOT NULL,
  cycle SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  note VARCHAR(160) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_consultations_user (user_id, cycle),
  CONSTRAINT fk_consultations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CRM / Leads (Atualização 09). Quem ainda não é paciente: de onde veio, em que etapa está, o que foi combinado.
-- Origens, etapas e motivos de perda são listas editáveis (não se apaga, só se desativa).
-- A linha do tempo (lead_events) NUNCA é editada nem apagada pelo sistema: correção = nova linha.
-- ---------------------------------------------------------------------
CREATE TABLE lead_sources (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'other',
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lead_sources_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lead_stages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  kind VARCHAR(8) NOT NULL DEFAULT 'open',
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  potential TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lead_stages_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lead_lost_reasons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lead_lost_reasons_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leads (
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
  CONSTRAINT fk_leads_stage FOREIGN KEY (stage_id) REFERENCES lead_stages (id),
  CONSTRAINT fk_leads_patient FOREIGN KEY (patient_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_leads_referred FOREIGN KEY (referred_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lead_events (
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
  CONSTRAINT fk_lead_events_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lead_conversions (
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
  payment_id INT UNSIGNED NULL,
  mode VARCHAR(8) NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_lead_conversions_lead (lead_id),
  CONSTRAINT fk_lead_conversions_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE,
  CONSTRAINT fk_lead_conversions_patient FOREIGN KEY (patient_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Renovações e auditoria
-- ---------------------------------------------------------------------
CREATE TABLE renewals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  previous_date DATE NULL,
  new_date DATE NOT NULL,
  days_added INT NOT NULL,
  plan_id INT UNSIGNED NULL,
  amount DECIMAL(10,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_renewals_user (user_id),
  CONSTRAINT fk_renewals_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  target_type VARCHAR(20) NULL,
  target_id INT UNSIGNED NULL,
  detail VARCHAR(300) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DADOS INICIAIS
-- =====================================================================

-- Regras de pontos (editáveis em Admin > Pontos). daily_limit 0 = sem limite.
INSERT INTO point_rules (rkey, label, points, daily_limit, active, sort_order) VALUES
('checkin',    'Check-in diário',                    5,   1, 1, 1),
('workout',    'Treino concluído',                  20,   1, 1, 2),
('post',       'Publicar no feed',                  10,   3, 1, 3),
('comment',    'Comentar',                           3,   5, 1, 4),
('like',       'Curtir',                             1,  10, 1, 5),
('streak_7',   'Bônus: 7 dias seguidos',            30,   0, 1, 6),
('streak_30',  'Bônus: 30 dias seguidos',          150,   0, 1, 7),
('streak_100', 'Bônus: 100 dias seguidos',         500,   0, 1, 8),
('renewal',    'Renovação do acompanhamento',      100,   0, 1, 9),
('referral',   'Indicação que virou paciente',     200,   0, 1, 10);

-- Planos iniciais (o Gabriel define os valores em Admin > Planos)
INSERT INTO plans (name, duration_days, price, active, created_at) VALUES
('Trimestral', 90, NULL, 1, NOW()),
('Semestral', 180, NULL, 1, NOW()),
('Anual', 365, NULL, 1, NOW());

-- Modalidades iniciais (o Gabriel cria outras em Admin > Modalidades)
INSERT INTO sports (name, active, created_at) VALUES
('Corrida', 1, NOW()),
('Musculação', 1, NOW()),
('Hyrox', 1, NOW()),
('Natação', 1, NOW());

-- Configurações
INSERT INTO settings (skey, svalue) VALUES
('whatsapp_number',  '5511999999999'),
('whatsapp_message', 'Oi, Gabriel! Quero renovar meu acompanhamento no No Days Off Club.'),
('level_prata', '500'),
('level_ouro',  '1500'),
('level_black', '4000'),
('warn_yellow_days', '15'),
('warn_red_days', '7'),
('birthday_message', 'Feliz aniversário, {nome}! 🎉 Que este novo ciclo venha com muita saúde, energia e conquistas. Conte comigo e com a equipe do No Days Off Club. Um abraço!'),
('consent_version', '1.0'),
('lead_recovery_days', '20,30,45'),
('theme_1', 'Segunda de foco'),    ('theme_1_q', 'Qual é a sua meta para esta semana?'),
('theme_2', 'Terça do prato'),     ('theme_2_q', 'Mostre uma refeição sua de hoje.'),
('theme_3', 'Quarta de treino'),   ('theme_3_q', 'Conte como foi o seu treino de hoje.'),
('theme_4', 'Quinta de vitória'),  ('theme_4_q', 'Qual pequena conquista você teve esta semana?'),
('theme_5', 'Sexta de plano'),     ('theme_5_q', 'Como vai manter o foco no fim de semana?'),
('theme_6', 'Sábado em movimento'),('theme_6_q', 'O que você fez de ativo hoje?'),
('theme_7', 'Domingo de preparo'), ('theme_7_q', 'Como está a organização das suas refeições da semana?'),
('community_rules', 'Respeite todos os membros. Sem comentários sobre o corpo dos outros. Sem venda de produtos, sem divulgação de dietas ou medicamentos. Dúvidas clínicas são tratadas com o Gabriel, não no feed. Conteúdo que desrespeite as regras é removido.');

-- CRM: listas iniciais (editáveis em Admin > CRM > Configurar). Os motivos de perda são sugestões comuns.
INSERT INTO lead_sources (name, kind, sort_order, active) VALUES
('Instagram orgânico', 'other', 1, 1),
('Instagram Ads', 'other', 2, 1),
('Indicação de paciente', 'patient_referral', 3, 1),
('Indicação profissional', 'other', 4, 1),
('WhatsApp', 'other', 5, 1),
('Site', 'other', 6, 1),
('TikTok', 'other', 7, 1),
('Eventos e parcerias', 'other', 8, 1),
('Outros', 'other', 9, 1);

INSERT INTO lead_stages (name, kind, sort_order, active, potential) VALUES
('Novo lead', 'open', 1, 1, 0),
('Primeiro contato', 'open', 2, 1, 0),
('Qualificação', 'open', 3, 1, 0),
('Engajamento', 'open', 4, 1, 0),
('Proposta enviada', 'open', 5, 1, 1),
('Negociação', 'open', 6, 1, 1),
('Convertido', 'won', 7, 1, 0),
('Perdido', 'lost', 8, 1, 0);

INSERT INTO lead_lost_reasons (name, sort_order, active) VALUES
('Preço / investimento', 1, 1),
('Parou de responder', 2, 1),
('Sem interesse no momento', 3, 1),
('Escolheu outro profissional', 4, 1),
('Falta de tempo', 5, 1),
('Fora da região ou do formato de atendimento', 6, 1),
('Outro motivo', 7, 1);

-- Administrador inicial. E-mail: admin@ndoclub.local | Senha provisória: Trocar@123
-- (o sistema obriga a trocar a senha no primeiro acesso)
INSERT INTO users (role, name, email, password_hash, status, must_change_password, created_at) VALUES
('admin', 'Gabriel', 'admin@ndoclub.local', '$2y$10$naYT2oSKa9O877BWg6MVUOYmZlbsFfFNbxh6VtVqRuP2U9fVT7qhG', 'active', 1, NOW());
