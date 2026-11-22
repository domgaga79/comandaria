-- =====================================================================
-- Comandaria/Mesaria Express - Garantia de limites dos planos
-- Banco: bacuridi_comandaria
-- Data: 19/05/2026
-- =====================================================================

UPDATE `planos`
SET
  `limite_mesas` = 10,
  `limite_pedidos_mes` = 100,
  `ativo` = 1
WHERE `nome` = 'FREE';

UPDATE `planos`
SET
  `limite_mesas` = 30,
  `limite_pedidos_mes` = 1000,
  `ativo` = 1
WHERE `nome` = 'PRO';

SELECT
  `id`,
  `nome`,
  `limite_mesas`,
  `limite_pedidos_mes`,
  `ativo`
FROM `planos`
WHERE `nome` IN ('FREE', 'PRO')
ORDER BY `id` ASC;
