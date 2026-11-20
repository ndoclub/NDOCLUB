-- =====================================================================
-- OPCIONAL: dados de TESTE do Financeiro (para você ver as telas cheias)
-- Rode DEPOIS da atualização 03. Só mexe nos pacientes de teste (*.teste@exemplo.com).
-- Rode UMA vez: só cria pagamentos para quem ainda não tem nenhum.
-- Valores fictícios. Depois, em Admin > Financeiro, dá para ver as parcelas e clicar em "Recebi".
-- Instalação nova com demo_data.sql? Ignore este arquivo (já vem incluído lá).
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- ana.teste@exemplo.com: BRL 4500.00 em 12x (cartao)
INSERT INTO payments (user_id, plan_id, total, currency, method, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 4500.00, 'BRL', 'cartao', 12, CURDATE() - INTERVAL 3 MONTH, NOW() FROM users u
 WHERE u.email = 'ana.teste@exemplo.com' AND NOT EXISTS (SELECT 1 FROM payments pp WHERE pp.user_id = u.id);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() - INTERVAL 2 MONTH, 375.00, 'BRL', 'received', CURDATE() - INTERVAL 2 MONTH, 375.00 FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 1);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 2, CURDATE() - INTERVAL 1 MONTH, 375.00, 'BRL', 'received', CURDATE() - INTERVAL 1 MONTH, 375.00 FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 2);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 3, CURDATE(), 375.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 3);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 4, CURDATE() + INTERVAL 1 MONTH, 375.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 4);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 5, CURDATE() + INTERVAL 2 MONTH, 375.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 5);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 6, CURDATE() + INTERVAL 3 MONTH, 375.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 6);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 7, CURDATE() + INTERVAL 4 MONTH, 375.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 7);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 8, CURDATE() + INTERVAL 5 MONTH, 375.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 8);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 9, CURDATE() + INTERVAL 6 MONTH, 375.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 9);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 10, CURDATE() + INTERVAL 7 MONTH, 375.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 10);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 11, CURDATE() + INTERVAL 8 MONTH, 375.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 11);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 12, CURDATE() + INTERVAL 9 MONTH, 375.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'ana.teste@exemplo.com' AND p.total = 4500.00 AND p.method = 'cartao' AND p.installments = 12
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 12);

-- diego.teste@exemplo.com: BRL 1200.00 em 3x (pix)
INSERT INTO payments (user_id, plan_id, total, currency, method, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 1200.00, 'BRL', 'pix', 3, CURDATE() - INTERVAL 3 MONTH, NOW() FROM users u
 WHERE u.email = 'diego.teste@exemplo.com' AND NOT EXISTS (SELECT 1 FROM payments pp WHERE pp.user_id = u.id);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() - INTERVAL 3 MONTH, 400.00, 'BRL', 'received', CURDATE() - INTERVAL 3 MONTH, 400.00 FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'diego.teste@exemplo.com' AND p.total = 1200.00 AND p.method = 'pix' AND p.installments = 3
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 1);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 2, CURDATE() - INTERVAL 2 MONTH, 400.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'diego.teste@exemplo.com' AND p.total = 1200.00 AND p.method = 'pix' AND p.installments = 3
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 2);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 3, CURDATE() - INTERVAL 1 MONTH, 400.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'diego.teste@exemplo.com' AND p.total = 1200.00 AND p.method = 'pix' AND p.installments = 3
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 3);

-- carla.teste@exemplo.com: BRL 1200.00 em 3x (pix)
INSERT INTO payments (user_id, plan_id, total, currency, method, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 1200.00, 'BRL', 'pix', 3, CURDATE() - INTERVAL 1 MONTH, NOW() FROM users u
 WHERE u.email = 'carla.teste@exemplo.com' AND NOT EXISTS (SELECT 1 FROM payments pp WHERE pp.user_id = u.id);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() - INTERVAL 1 MONTH, 400.00, 'BRL', 'received', CURDATE() - INTERVAL 1 MONTH, 400.00 FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'carla.teste@exemplo.com' AND p.total = 1200.00 AND p.method = 'pix' AND p.installments = 3
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 1);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 2, CURDATE(), 400.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'carla.teste@exemplo.com' AND p.total = 1200.00 AND p.method = 'pix' AND p.installments = 3
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 2);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 3, CURDATE() + INTERVAL 1 MONTH, 400.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'carla.teste@exemplo.com' AND p.total = 1200.00 AND p.method = 'pix' AND p.installments = 3
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 3);

-- bruno.teste@exemplo.com: BRL 2400.00 em 6x (cartao)
INSERT INTO payments (user_id, plan_id, total, currency, method, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 2400.00, 'BRL', 'cartao', 6, CURDATE() - INTERVAL 20 DAY, NOW() FROM users u
 WHERE u.email = 'bruno.teste@exemplo.com' AND NOT EXISTS (SELECT 1 FROM payments pp WHERE pp.user_id = u.id);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() + INTERVAL 1 MONTH, 400.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'bruno.teste@exemplo.com' AND p.total = 2400.00 AND p.method = 'cartao' AND p.installments = 6
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 1);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 2, CURDATE() + INTERVAL 2 MONTH, 400.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'bruno.teste@exemplo.com' AND p.total = 2400.00 AND p.method = 'cartao' AND p.installments = 6
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 2);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 3, CURDATE() + INTERVAL 3 MONTH, 400.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'bruno.teste@exemplo.com' AND p.total = 2400.00 AND p.method = 'cartao' AND p.installments = 6
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 3);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 4, CURDATE() + INTERVAL 4 MONTH, 400.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'bruno.teste@exemplo.com' AND p.total = 2400.00 AND p.method = 'cartao' AND p.installments = 6
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 4);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 5, CURDATE() + INTERVAL 5 MONTH, 400.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'bruno.teste@exemplo.com' AND p.total = 2400.00 AND p.method = 'cartao' AND p.installments = 6
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 5);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 6, CURDATE() + INTERVAL 6 MONTH, 400.00, 'BRL', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'bruno.teste@exemplo.com' AND p.total = 2400.00 AND p.method = 'cartao' AND p.installments = 6
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 6);

-- karen.teste@exemplo.com: USD 900.00 em 1x (wise)
INSERT INTO payments (user_id, plan_id, total, currency, method, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 900.00, 'USD', 'wise', 1, CURDATE() - INTERVAL 10 DAY, NOW() FROM users u
 WHERE u.email = 'karen.teste@exemplo.com' AND NOT EXISTS (SELECT 1 FROM payments pp WHERE pp.user_id = u.id);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() - INTERVAL 10 DAY, 900.00, 'USD', 'received', CURDATE() - INTERVAL 10 DAY, 4950.00 FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'karen.teste@exemplo.com' AND p.total = 900.00 AND p.method = 'wise' AND p.installments = 1
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 1);

-- elisa.teste@exemplo.com: EUR 600.00 em 1x (wise)
INSERT INTO payments (user_id, plan_id, total, currency, method, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 600.00, 'EUR', 'wise', 1, CURDATE() - INTERVAL 2 DAY, NOW() FROM users u
 WHERE u.email = 'elisa.teste@exemplo.com' AND NOT EXISTS (SELECT 1 FROM payments pp WHERE pp.user_id = u.id);
INSERT INTO payment_installments (payment_id, number, due_on, amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() + INTERVAL 5 DAY, 600.00, 'EUR', 'pending', NULL, NULL FROM payments p JOIN users u ON u.id = p.user_id
 WHERE u.email = 'elisa.teste@exemplo.com' AND p.total = 600.00 AND p.method = 'wise' AND p.installments = 1
   AND NOT EXISTS (SELECT 1 FROM payment_installments i WHERE i.payment_id = p.id AND i.number = 1);

-- Valor combinado em dólar e euro (pacientes no exterior)
INSERT IGNORE INTO patient_billing (user_id, price, currency, source) SELECT id, 900.00, 'USD', 'manual' FROM users WHERE email = 'karen.teste@exemplo.com';
INSERT IGNORE INTO patient_billing (user_id, price, currency, source) SELECT id, 600.00, 'EUR', 'manual' FROM users WHERE email = 'elisa.teste@exemplo.com';
