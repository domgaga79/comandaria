-- ============================================================
-- COMANDARIA / MESARIA EXPRESS MULTITENANT
-- Onboarding de primeira utilização por usuário e por tour
-- ============================================================
-- Execute uma única vez no banco atual.
-- A tabela é independente da estrutura da tabela usuarios,
-- o que evita impactos em cadastros já existentes.
-- ============================================================

CREATE TABLE IF NOT EXISTS `usuarios_onboarding_tours` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `usuario_id` INT NOT NULL,
  `empresa_id` INT DEFAULT NULL,
  `tour_key` VARCHAR(120) NOT NULL,
  `concluido_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_onboarding_usuario_tour` (`usuario_id`, `tour_key`),
  KEY `idx_onboarding_empresa` (`empresa_id`),
  KEY `idx_onboarding_tour` (`tour_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
