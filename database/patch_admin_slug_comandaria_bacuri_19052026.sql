-- =====================================================================
-- Comandaria/Mesaria Express - Ajuste do Tenant Admin da Plataforma
-- Banco: bacuridi_comandaria
-- Admin real: comandaria-de-bacuri-digital
-- Data: 19/05/2026
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Garante colunas do painel administrativo central
-- ---------------------------------------------------------------------
ALTER TABLE `empresas`
  ADD COLUMN IF NOT EXISTS `telefone` varchar(30) NULL AFTER `slug`,
  ADD COLUMN IF NOT EXISTS `is_admin_plataforma` tinyint(1) NOT NULL DEFAULT 0 AFTER `status_assinatura`;

ALTER TABLE `empresas`
  ADD KEY IF NOT EXISTS `idx_empresas_admin_plataforma` (`is_admin_plataforma`);

-- ---------------------------------------------------------------------
-- 2) Se o tenant ainda não existir, cria o tenant administrador correto
-- ---------------------------------------------------------------------
INSERT INTO `empresas` (
  `nome`,
  `slug`,
  `telefone`,
  `plano_id`,
  `status`,
  `status_assinatura`,
  `is_admin_plataforma`,
  `ultimo_numero_pedido`,
  `created_at`,
  `updated_at`
)
SELECT
  'Comandaria de Bacuri Digital',
  'comandaria-de-bacuri-digital',
  '71993143374',
  p.`id`,
  'ativa',
  'ativa',
  1,
  0,
  NOW(),
  NOW()
FROM `planos` p
WHERE p.`nome` = 'PRO'
AND NOT EXISTS (
  SELECT 1
  FROM `empresas` e
  WHERE e.`slug` = 'comandaria-de-bacuri-digital'
)
LIMIT 1;

-- ---------------------------------------------------------------------
-- 3) Transforma o tenant real da Bacuri Digital em admin da plataforma
-- ---------------------------------------------------------------------
UPDATE `empresas` e
INNER JOIN `planos` p
  ON p.`nome` = 'PRO'
SET
  e.`nome` = 'Comandaria de Bacuri Digital',
  e.`telefone` = '71993143374',
  e.`plano_id` = p.`id`,
  e.`status` = 'ativa',
  e.`status_assinatura` = 'ativa',
  e.`is_admin_plataforma` = 1
WHERE e.`slug` = 'comandaria-de-bacuri-digital';

-- ---------------------------------------------------------------------
-- 4) Evita manter dois tenants administradores por engano, caso o patch
--    antigo de bacuri-digital tenha sido executado anteriormente.
-- ---------------------------------------------------------------------
UPDATE `empresas`
SET `is_admin_plataforma` = 0
WHERE `slug` = 'bacuri-digital'
  AND `slug` <> 'comandaria-de-bacuri-digital';

-- ---------------------------------------------------------------------
-- 5) Conferência rápida
-- ---------------------------------------------------------------------
SELECT
  e.`id`,
  e.`nome`,
  e.`slug`,
  e.`telefone`,
  e.`is_admin_plataforma`,
  p.`nome` AS plano,
  e.`status`,
  e.`status_assinatura`
FROM `empresas` e
INNER JOIN `planos` p
  ON p.`id` = e.`plano_id`
WHERE e.`slug` IN ('comandaria-de-bacuri-digital', 'bacuri-digital')
ORDER BY e.`slug` ASC;
