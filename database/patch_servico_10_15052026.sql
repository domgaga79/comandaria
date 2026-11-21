-- Comandaria / Burguershake
-- Patch: ativar/desativar cobrança de 10% por pedido
-- Data: 2026-05-15

ALTER TABLE `pedidos`
    ADD COLUMN IF NOT EXISTS `servico_10_ativo` TINYINT(1) NOT NULL DEFAULT 0 AFTER `total`,
    ADD COLUMN IF NOT EXISTS `taxa_servico_10` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `servico_10_ativo`;

UPDATE `pedidos`
SET `taxa_servico_10` = CASE
    WHEN COALESCE(`servico_10_ativo`, 0) = 1
    THEN ROUND(COALESCE(`total`, 0) * 0.10, 2)
    ELSE 0.00
END;
