-- =====================================================================
-- OPCIONAL: dados de TESTE de "Pacientes v2" (responsáveis, cota de consultas e consultas realizadas)
-- Rode DEPOIS da atualização 05. Só mexe nos pacientes de teste (*.teste@exemplo.com).
-- Cria 3 responsáveis fictícios, atribui os pacientes de teste e lança consultas de exemplo.
-- ATENÇÃO: a cota de consultas dos planos (Trimestral 2, Semestral 4, Anual 8) é só um EXEMPLO e só é
-- preenchida onde ainda estava em branco. Troque pelos números reais em Admin > Planos.
-- Pode rodar de novo quando quiser: apaga as consultas de teste e lança de novo, sem duplicar nada.
-- Instalação nova com demo_data.sql? Ignore este arquivo (já vem incluído lá).
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

DELETE FROM nodaysoffclub.consultations WHERE user_id IN (SELECT id FROM nodaysoffclub.users WHERE email LIKE '%.teste@exemplo.com');
UPDATE nodaysoffclub.users SET responsible_id = NULL WHERE email LIKE '%.teste@exemplo.com';

-- Responsáveis fictícios (modo babá)
INSERT IGNORE INTO nodaysoffclub.responsibles (name, active, created_at) VALUES ('Estagiária 1 (teste)', 1, NOW());
INSERT IGNORE INTO nodaysoffclub.responsibles (name, active, created_at) VALUES ('Estagiária 2 (teste)', 1, NOW());
INSERT IGNORE INTO nodaysoffclub.responsibles (name, active, created_at) VALUES ('Estagiária 3 (teste)', 1, NOW());

-- Cota de consultas por plano: valores de EXEMPLO (só onde ainda está em branco). Troque em Admin > Planos.
UPDATE nodaysoffclub.plans SET consultations_included = 2 WHERE name = 'Trimestral' AND consultations_included IS NULL;
UPDATE nodaysoffclub.plans SET consultations_included = 4 WHERE name = 'Semestral' AND consultations_included IS NULL;
UPDATE nodaysoffclub.plans SET consultations_included = 8 WHERE name = 'Anual' AND consultations_included IS NULL;

-- Quem é responsável por quem (Karen, Lucas e o paciente novo ficam sem responsável de propósito)
UPDATE nodaysoffclub.users SET responsible_id = (SELECT id FROM nodaysoffclub.responsibles WHERE name = 'Estagiária 1 (teste)') WHERE email = 'ana.teste@exemplo.com';
UPDATE nodaysoffclub.users SET responsible_id = (SELECT id FROM nodaysoffclub.responsibles WHERE name = 'Estagiária 1 (teste)') WHERE email = 'bruno.teste@exemplo.com';
UPDATE nodaysoffclub.users SET responsible_id = (SELECT id FROM nodaysoffclub.responsibles WHERE name = 'Estagiária 1 (teste)') WHERE email = 'carla.teste@exemplo.com';
UPDATE nodaysoffclub.users SET responsible_id = (SELECT id FROM nodaysoffclub.responsibles WHERE name = 'Estagiária 1 (teste)') WHERE email = 'diego.teste@exemplo.com';
UPDATE nodaysoffclub.users SET responsible_id = (SELECT id FROM nodaysoffclub.responsibles WHERE name = 'Estagiária 2 (teste)') WHERE email = 'elisa.teste@exemplo.com';
UPDATE nodaysoffclub.users SET responsible_id = (SELECT id FROM nodaysoffclub.responsibles WHERE name = 'Estagiária 2 (teste)') WHERE email = 'felipe.teste@exemplo.com';
UPDATE nodaysoffclub.users SET responsible_id = (SELECT id FROM nodaysoffclub.responsibles WHERE name = 'Estagiária 2 (teste)') WHERE email = 'gabi.teste@exemplo.com';
UPDATE nodaysoffclub.users SET responsible_id = (SELECT id FROM nodaysoffclub.responsibles WHERE name = 'Estagiária 2 (teste)') WHERE email = 'heitor.teste@exemplo.com';
UPDATE nodaysoffclub.users SET responsible_id = (SELECT id FROM nodaysoffclub.responsibles WHERE name = 'Estagiária 3 (teste)') WHERE email = 'isabela.teste@exemplo.com';
UPDATE nodaysoffclub.users SET responsible_id = (SELECT id FROM nodaysoffclub.responsibles WHERE name = 'Estagiária 3 (teste)') WHERE email = 'joao.teste@exemplo.com';

-- Consultas já realizadas (no plano atual de cada um)
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 95 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Avaliação inicial', NOW() FROM nodaysoffclub.users u WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 50 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Retorno', NOW() FROM nodaysoffclub.users u WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 12 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Retorno', NOW() FROM nodaysoffclub.users u WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 80 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Avaliação inicial', NOW() FROM nodaysoffclub.users u WHERE u.email = 'bruno.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 40 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Retorno', NOW() FROM nodaysoffclub.users u WHERE u.email = 'bruno.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 20 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Retorno', NOW() FROM nodaysoffclub.users u WHERE u.email = 'bruno.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 6 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Bioimpedância', NOW() FROM nodaysoffclub.users u WHERE u.email = 'bruno.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 30 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Avaliação inicial', NOW() FROM nodaysoffclub.users u WHERE u.email = 'carla.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 60 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Avaliação inicial', NOW() FROM nodaysoffclub.users u WHERE u.email = 'diego.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 25 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Retorno', NOW() FROM nodaysoffclub.users u WHERE u.email = 'diego.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 10 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Avaliação inicial', NOW() FROM nodaysoffclub.users u WHERE u.email = 'felipe.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 55 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Avaliação inicial', NOW() FROM nodaysoffclub.users u WHERE u.email = 'heitor.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 20 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Retorno', NOW() FROM nodaysoffclub.users u WHERE u.email = 'heitor.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 70 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Avaliação inicial', NOW() FROM nodaysoffclub.users u WHERE u.email = 'isabela.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 35 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Retorno', NOW() FROM nodaysoffclub.users u WHERE u.email = 'isabela.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 85 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Avaliação inicial', NOW() FROM nodaysoffclub.users u WHERE u.email = 'joao.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 120 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Avaliação inicial', NOW() FROM nodaysoffclub.users u WHERE u.email = 'karen.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 60 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Retorno', NOW() FROM nodaysoffclub.users u WHERE u.email = 'karen.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 10 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Retorno', NOW() FROM nodaysoffclub.users u WHERE u.email = 'karen.teste@exemplo.com';
INSERT INTO nodaysoffclub.consultations (user_id, held_on, cycle, note, created_at) SELECT u.id, CURDATE() - INTERVAL 3 DAY, (SELECT COUNT(*) FROM nodaysoffclub.renewals r WHERE r.user_id = u.id), 'Bioimpedância', NOW() FROM nodaysoffclub.users u WHERE u.email = 'karen.teste@exemplo.com';
