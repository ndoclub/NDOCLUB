-- =====================================================================
-- OPCIONAL: dados de TESTE do Financeiro (para você ver as telas cheias)
-- Rode DEPOIS da atualização 04. Só mexe nos pacientes de teste (*.teste@exemplo.com).
-- ATENÇÃO: apaga os pagamentos e parcelas desses pacientes de teste e cria tudo de novo,
-- com datas relativas a HOJE. Pode rodar de novo quando quiser "zerar" os testes do Financeiro.
-- Tem: venda do mês, Pix para cobrar hoje, Pix atrasado, cartão com taxa e pacientes no exterior.
-- Instalação nova com demo_data.sql? Ignore este arquivo (já vem incluído lá).
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

DELETE FROM nodaysoffclub.payments WHERE user_id IN (SELECT id FROM nodaysoffclub.users WHERE email LIKE '%.teste@exemplo.com');

-- ana.teste@exemplo.com: BRL 4500.00 em 12x (cartao) visa
INSERT INTO nodaysoffclub.payments (user_id, plan_id, total, currency, method, card_brand, fee_pct, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 4500.00, 'BRL', 'cartao', 'visa', 4.50, 12, CURDATE() - INTERVAL 3 MONTH, NOW() FROM nodaysoffclub.users u WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() - INTERVAL 2 MONTH, 375, 358.13, 'BRL', 'received', CURDATE() - INTERVAL 2 MONTH, 358.13 FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 2, CURDATE() - INTERVAL 1 MONTH, 375, 358.13, 'BRL', 'received', CURDATE() - INTERVAL 1 MONTH, 358.13 FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 3, CURDATE(), 375, 358.13, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 4, CURDATE() + INTERVAL 1 MONTH, 375, 358.13, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 5, CURDATE() + INTERVAL 2 MONTH, 375, 358.13, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 6, CURDATE() + INTERVAL 3 MONTH, 375, 358.13, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 7, CURDATE() + INTERVAL 4 MONTH, 375, 358.13, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 8, CURDATE() + INTERVAL 5 MONTH, 375, 358.13, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 9, CURDATE() + INTERVAL 6 MONTH, 375, 358.13, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 10, CURDATE() + INTERVAL 7 MONTH, 375, 358.13, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 11, CURDATE() + INTERVAL 8 MONTH, 375, 358.13, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 12, CURDATE() + INTERVAL 9 MONTH, 375, 358.13, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'ana.teste@exemplo.com';

-- bruno.teste@exemplo.com: BRL 2400.00 em 6x (cartao) mastercard
INSERT INTO nodaysoffclub.payments (user_id, plan_id, total, currency, method, card_brand, fee_pct, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 2400.00, 'BRL', 'cartao', 'mastercard', 3.20, 6, CURDATE(), NOW() FROM nodaysoffclub.users u WHERE u.email = 'bruno.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() + INTERVAL 1 MONTH, 400, 387.20, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'bruno.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 2, CURDATE() + INTERVAL 2 MONTH, 400, 387.20, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'bruno.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 3, CURDATE() + INTERVAL 3 MONTH, 400, 387.20, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'bruno.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 4, CURDATE() + INTERVAL 4 MONTH, 400, 387.20, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'bruno.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 5, CURDATE() + INTERVAL 5 MONTH, 400, 387.20, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'bruno.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 6, CURDATE() + INTERVAL 6 MONTH, 400, 387.20, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'bruno.teste@exemplo.com';

-- carla.teste@exemplo.com: BRL 1200.00 em 3x (pix)
INSERT INTO nodaysoffclub.payments (user_id, plan_id, total, currency, method, card_brand, fee_pct, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 1200.00, 'BRL', 'pix', NULL, 0, 3, CURDATE() - INTERVAL 1 MONTH, NOW() FROM nodaysoffclub.users u WHERE u.email = 'carla.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() - INTERVAL 1 MONTH, 400, 400, 'BRL', 'received', CURDATE() - INTERVAL 1 MONTH, 400 FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'carla.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 2, CURDATE(), 400, 400, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'carla.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 3, CURDATE() + INTERVAL 1 MONTH, 400, 400, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'carla.teste@exemplo.com';

-- diego.teste@exemplo.com: BRL 1200.00 em 3x (pix)
INSERT INTO nodaysoffclub.payments (user_id, plan_id, total, currency, method, card_brand, fee_pct, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 1200.00, 'BRL', 'pix', NULL, 0, 3, CURDATE() - INTERVAL 3 MONTH, NOW() FROM nodaysoffclub.users u WHERE u.email = 'diego.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() - INTERVAL 3 MONTH, 400, 400, 'BRL', 'received', CURDATE() - INTERVAL 3 MONTH, 400 FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'diego.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 2, CURDATE() - INTERVAL 2 MONTH, 400, 400, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'diego.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 3, CURDATE() - INTERVAL 1 MONTH, 400, 400, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'diego.teste@exemplo.com';

-- gabi.teste@exemplo.com: BRL 1200.00 em 1x (pix)
INSERT INTO nodaysoffclub.payments (user_id, plan_id, total, currency, method, card_brand, fee_pct, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 1200.00, 'BRL', 'pix', NULL, 0, 1, CURDATE(), NOW() FROM nodaysoffclub.users u WHERE u.email = 'gabi.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE(), 1200.00, 1200.00, 'BRL', 'received', CURDATE(), 1200.00 FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'gabi.teste@exemplo.com';

-- heitor.teste@exemplo.com: BRL 1200.00 em 2x (pix)
INSERT INTO nodaysoffclub.payments (user_id, plan_id, total, currency, method, card_brand, fee_pct, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 1200.00, 'BRL', 'pix', NULL, 0, 2, CURDATE(), NOW() FROM nodaysoffclub.users u WHERE u.email = 'heitor.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE(), 600.00, 600.00, 'BRL', 'received', CURDATE(), 600.00 FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'heitor.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 2, CURDATE() + INTERVAL 1 MONTH, 600.00, 600.00, 'BRL', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'heitor.teste@exemplo.com';

-- karen.teste@exemplo.com: USD 900.00 em 1x (wise)
INSERT INTO nodaysoffclub.payments (user_id, plan_id, total, currency, method, card_brand, fee_pct, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 900.00, 'USD', 'wise', NULL, 0, 1, CURDATE() - INTERVAL 10 DAY, NOW() FROM nodaysoffclub.users u WHERE u.email = 'karen.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() - INTERVAL 10 DAY, 900.00, NULL, 'USD', 'received', CURDATE() - INTERVAL 10 DAY, 4950.00 FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'karen.teste@exemplo.com';

-- elisa.teste@exemplo.com: EUR 600.00 em 1x (wise)
INSERT INTO nodaysoffclub.payments (user_id, plan_id, total, currency, method, card_brand, fee_pct, installments, sold_on, created_at)
SELECT u.id, u.plan_id, 600.00, 'EUR', 'wise', NULL, 0, 1, CURDATE() - INTERVAL 2 DAY, NOW() FROM nodaysoffclub.users u WHERE u.email = 'elisa.teste@exemplo.com';
INSERT INTO nodaysoffclub.payment_installments (payment_id, number, due_on, amount, net_amount, currency, status, received_on, received_brl)
SELECT p.id, 1, CURDATE() + INTERVAL 5 DAY, 600.00, NULL, 'EUR', 'pending', NULL, NULL FROM nodaysoffclub.payments p JOIN nodaysoffclub.users u ON u.id = p.user_id WHERE u.email = 'elisa.teste@exemplo.com';

-- Valor combinado em dólar e euro (pacientes no exterior)
INSERT IGNORE INTO nodaysoffclub.patient_billing (user_id, price, currency, source) SELECT id, 900.00, 'USD', 'manual' FROM nodaysoffclub.users WHERE email = 'karen.teste@exemplo.com';
INSERT IGNORE INTO nodaysoffclub.patient_billing (user_id, price, currency, source) SELECT id, 600.00, 'EUR', 'manual' FROM nodaysoffclub.users WHERE email = 'elisa.teste@exemplo.com';

-- Taxas do cartão de exemplo (se já existirem, ficam como estão). Troque pelos números do contrato real.
INSERT IGNORE INTO nodaysoffclub.payment_fees (brand, band, pct) VALUES ('visa', 1, 2.50);
INSERT IGNORE INTO nodaysoffclub.payment_fees (brand, band, pct) VALUES ('visa', 2, 3.50);
INSERT IGNORE INTO nodaysoffclub.payment_fees (brand, band, pct) VALUES ('visa', 3, 4.50);
INSERT IGNORE INTO nodaysoffclub.payment_fees (brand, band, pct) VALUES ('mastercard', 1, 2.40);
INSERT IGNORE INTO nodaysoffclub.payment_fees (brand, band, pct) VALUES ('mastercard', 2, 3.20);
INSERT IGNORE INTO nodaysoffclub.payment_fees (brand, band, pct) VALUES ('mastercard', 3, 4.30);
