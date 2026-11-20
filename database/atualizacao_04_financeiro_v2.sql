-- =====================================================================
-- ATUALIZAÇÃO 04: Financeiro v2 (taxas do cartão, valor líquido, comprovante do Pix, parcela cancelada)
-- Para quem JÁ importou o schema.sql e as atualizações 01, 02 e 03. NÃO apaga nenhum dado.
-- Como usar: phpMyAdmin > clique no banco "nodaysoffclub" (menu da esquerda)
--            > aba "Importar" > escolha este arquivo > Executar.
-- Pode rodar mais de uma vez sem problema (o XAMPP usa MariaDB, que aceita "IF NOT EXISTS").
-- Os nomes das tabelas já levam "nodaysoffclub." na frente, então funciona mesmo que o phpMyAdmin
-- esteja com outro banco selecionado.
-- Instalação nova (sem banco ainda)? Ignore este arquivo: o schema.sql já tem tudo.
-- =====================================================================
USE nodaysoffclub;
SET NAMES utf8mb4;

-- Bandeira e taxa (%) usadas em cada venda no cartão
ALTER TABLE nodaysoffclub.payments ADD COLUMN IF NOT EXISTS card_brand VARCHAR(12) NULL AFTER method;
ALTER TABLE nodaysoffclub.payments ADD COLUMN IF NOT EXISTS fee_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER card_brand;

-- Valor líquido previsto de cada parcela (depois da taxa) e comprovante anexado (Pix etc.)
ALTER TABLE nodaysoffclub.payment_installments ADD COLUMN IF NOT EXISTS net_amount DECIMAL(10,2) NULL AFTER amount;
ALTER TABLE nodaysoffclub.payment_installments ADD COLUMN IF NOT EXISTS proof_file VARCHAR(48) NULL AFTER received_brl;

-- Parcelas que já existiam em reais: o líquido é igual ao valor (ainda sem taxa)
UPDATE nodaysoffclub.payment_installments SET net_amount = amount WHERE net_amount IS NULL AND currency = 'BRL';

-- Tabela de taxas do cartão (bandeira x faixa de parcelas). Começa vazia: preencha em Financeiro > Taxas do cartão.
CREATE TABLE IF NOT EXISTS nodaysoffclub.payment_fees (
  brand VARCHAR(12) NOT NULL,
  band TINYINT UNSIGNED NOT NULL,
  pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (brand, band)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
