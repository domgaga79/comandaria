-- =====================================================================
-- Comandaria/Mesaria Express - Painel Bacuri Digital de Empresas/Planos
-- Banco: bacuridi_comandaria
-- Aplicar APÓS: migracao_multitenant_planos_google_19052026.sql
-- Data: 19/05/2026
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Identificação administrativa da plataforma
-- ---------------------------------------------------------------------
ALTER TABLE `empresas`
  ADD COLUMN IF NOT EXISTS `telefone` varchar(30) NULL AFTER `slug`,
  ADD COLUMN IF NOT EXISTS `is_admin_plataforma` tinyint(1) NOT NULL DEFAULT 0 AFTER `status_assinatura`;

ALTER TABLE `empresas`
  ADD KEY IF NOT EXISTS `idx_empresas_admin_plataforma` (`is_admin_plataforma`);

-- ---------------------------------------------------------------------
-- 2) Tenant administrador da Bacuri Digital
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
  'Bacuri Digital',
  'bacuri-digital',
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
  WHERE e.`slug` = 'bacuri-digital'
)
LIMIT 1;

UPDATE `empresas` e
INNER JOIN `planos` p
  ON p.`nome` = 'PRO'
SET
  e.`nome` = 'Bacuri Digital',
  e.`telefone` = '71993143374',
  e.`plano_id` = p.`id`,
  e.`status` = 'ativa',
  e.`status_assinatura` = 'ativa',
  e.`is_admin_plataforma` = 1
WHERE e.`slug` = 'bacuri-digital';

-- ---------------------------------------------------------------------
-- 3) Conferência
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
WHERE e.`slug` = 'bacuri-digital'
LIMIT 1;
