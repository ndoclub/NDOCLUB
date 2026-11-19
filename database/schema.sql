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
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  consent_at DATETIME NULL,
  consent_version VARCHAR(12) NULL,
  last_seen_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status),
  KEY idx_users_renewal (renewal_date)
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
-- Renovações e auditoria
-- ---------------------------------------------------------------------
CREATE TABLE renewals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  previous_date DATE NULL,
  new_date DATE NOT NULL,
  days_added INT NOT NULL,
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

-- Configurações
INSERT INTO settings (skey, svalue) VALUES
('whatsapp_number',  '5511999999999'),
('whatsapp_message', 'Oi, Gabriel! Quero renovar meu acompanhamento no No Days Off Club.'),
('level_prata', '500'),
('level_ouro',  '1500'),
('level_black', '4000'),
('warn_yellow_days', '15'),
('warn_red_days', '7'),
('consent_version', '1.0'),
('theme_1', 'Segunda de foco'),    ('theme_1_q', 'Qual é a sua meta para esta semana?'),
('theme_2', 'Terça do prato'),     ('theme_2_q', 'Mostre uma refeição sua de hoje.'),
('theme_3', 'Quarta de treino'),   ('theme_3_q', 'Conte como foi o seu treino de hoje.'),
('theme_4', 'Quinta de vitória'),  ('theme_4_q', 'Qual pequena conquista você teve esta semana?'),
('theme_5', 'Sexta de plano'),     ('theme_5_q', 'Como vai manter o foco no fim de semana?'),
('theme_6', 'Sábado em movimento'),('theme_6_q', 'O que você fez de ativo hoje?'),
('theme_7', 'Domingo de preparo'), ('theme_7_q', 'Como está a organização das suas refeições da semana?'),
('community_rules', 'Respeite todos os membros. Sem comentários sobre o corpo dos outros. Sem venda de produtos, sem divulgação de dietas ou medicamentos. Dúvidas clínicas são tratadas com o Gabriel, não no feed. Conteúdo que desrespeite as regras é removido.');

-- Administrador inicial. E-mail: admin@ndoclub.local | Senha provisória: Trocar@123
-- (o sistema obriga a trocar a senha no primeiro acesso)
INSERT INTO users (role, name, email, password_hash, status, must_change_password, created_at) VALUES
('admin', 'Gabriel', 'admin@ndoclub.local', '$2y$10$naYT2oSKa9O877BWg6MVUOYmZlbsFfFNbxh6VtVqRuP2U9fVT7qhG', 'active', 1, NOW());
