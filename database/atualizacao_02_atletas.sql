-- =====================================================================
-- ATUALIZAÇÃO 02: Perfil esportivo (atletas) e modalidades
-- Para quem JÁ importou o schema.sql antes (e a atualização 01). NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar.
-- Pode rodar mais de uma vez sem problema.
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(40) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sports_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS athlete_profiles (
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

CREATE TABLE IF NOT EXISTS user_sports (
  user_id INT UNSIGNED NOT NULL,
  sport_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, sport_id),
  CONSTRAINT fk_usersports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_usersports_sport FOREIGN KEY (sport_id) REFERENCES sports (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modalidades iniciais (só cria as que ainda não existem; o Gabriel cria outras em Admin > Modalidades)
INSERT INTO sports (name, active, created_at) SELECT 'Corrida', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM sports WHERE name = 'Corrida');
INSERT INTO sports (name, active, created_at) SELECT 'Musculação', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM sports WHERE name = 'Musculação');
INSERT INTO sports (name, active, created_at) SELECT 'Hyrox', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM sports WHERE name = 'Hyrox');
INSERT INTO sports (name, active, created_at) SELECT 'Natação', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM sports WHERE name = 'Natação');

-- ---------------------------------------------------------------------
-- OPCIONAL: atletas de TESTE (*.teste@exemplo.com), para ver na tela.
-- Só mexe nos e-mails de teste; pacientes reais não são tocados. Pode apagar este bloco.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO athlete_profiles (user_id, is_athlete, has_coach, coach_name, competes, event_name, event_date, show_public, updated_at)
SELECT id, 1, 1, 'Assessoria Pace', 1, 'Maratona de São Paulo', CURDATE() + INTERVAL 25 DAY, 1, NOW() FROM users WHERE email = 'ana.teste@exemplo.com';
INSERT IGNORE INTO athlete_profiles (user_id, is_athlete, has_coach, coach_name, competes, event_name, event_date, show_public, updated_at)
SELECT id, 1, 0, NULL, 0, NULL, NULL, 0, NOW() FROM users WHERE email = 'bruno.teste@exemplo.com';
INSERT IGNORE INTO athlete_profiles (user_id, is_athlete, has_coach, coach_name, competes, event_name, event_date, show_public, updated_at)
SELECT id, 1, 1, 'Team Aqua', 1, 'Travessia do Forte', CURDATE() + INTERVAL 48 DAY, 1, NOW() FROM users WHERE email = 'carla.teste@exemplo.com';
INSERT IGNORE INTO athlete_profiles (user_id, is_athlete, has_coach, coach_name, competes, event_name, event_date, show_public, updated_at)
SELECT id, 1, 0, NULL, 1, 'Meia Maratona de Floripa', CURDATE() + INTERVAL 12 DAY, 0, NOW() FROM users WHERE email = 'diego.teste@exemplo.com';
INSERT IGNORE INTO athlete_profiles (user_id, is_athlete, has_coach, coach_name, competes, event_name, event_date, show_public, updated_at)
SELECT id, 1, 0, NULL, 0, NULL, NULL, 1, NOW() FROM users WHERE email = 'karen.teste@exemplo.com';

INSERT IGNORE INTO user_sports (user_id, sport_id) SELECT u.id, s.id FROM users u, sports s WHERE u.email = 'ana.teste@exemplo.com' AND s.name IN ('Corrida', 'Hyrox');
INSERT IGNORE INTO user_sports (user_id, sport_id) SELECT u.id, s.id FROM users u, sports s WHERE u.email = 'bruno.teste@exemplo.com' AND s.name IN ('Musculação');
INSERT IGNORE INTO user_sports (user_id, sport_id) SELECT u.id, s.id FROM users u, sports s WHERE u.email = 'carla.teste@exemplo.com' AND s.name IN ('Natação');
INSERT IGNORE INTO user_sports (user_id, sport_id) SELECT u.id, s.id FROM users u, sports s WHERE u.email = 'diego.teste@exemplo.com' AND s.name IN ('Corrida', 'Musculação');
INSERT IGNORE INTO user_sports (user_id, sport_id) SELECT u.id, s.id FROM users u, sports s WHERE u.email = 'karen.teste@exemplo.com' AND s.name IN ('Hyrox');
