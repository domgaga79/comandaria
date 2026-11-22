-- =====================================================================
-- Comandaria/Mesaria Express - Migração Multitenant + Planos + Google
-- Versão corrigida/reexecutável: evita erro por colunas, índices e FKs já existentes
-- Banco de destino: bacuridi_comandaria
-- Data: 19/05/2026
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Planos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `planos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `limite_mesas` int(11) NOT NULL,
  `limite_pedidos_mes` int(11) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_planos_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `planos` (`nome`, `limite_mesas`, `limite_pedidos_mes`, `ativo`)
VALUES
  ('FREE', 10, 100, 1),
  ('PRO', 30, 1000, 1)
ON DUPLICATE KEY UPDATE
  `limite_mesas` = VALUES(`limite_mesas`),
  `limite_pedidos_mes` = VALUES(`limite_pedidos_mes`),
  `ativo` = VALUES(`ativo`);

-- ---------------------------------------------------------------------
-- 2) Empresas / tenants
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `empresas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(180) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `status` enum('ativa','inativa') NOT NULL DEFAULT 'ativa',
  `status_assinatura` enum('ativa','suspensa','cancelada') NOT NULL DEFAULT 'ativa',
  `ultimo_numero_pedido` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_empresas_slug` (`slug`),
  KEY `idx_empresas_plano` (`plano_id`),
  CONSTRAINT `fk_empresas_plano`
    FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `empresas` (
  `nome`,
  `slug`,
  `plano_id`,
  `status`,
  `status_assinatura`,
  `ultimo_numero_pedido`
)
SELECT
  'BurguerShake Lanchonete',
  'burguershake',
  p.id,
  'ativa',
  'ativa',
  0
FROM `planos` p
WHERE p.nome = 'PRO'
AND NOT EXISTS (
  SELECT 1
  FROM `empresas` e
  WHERE e.slug = 'burguershake'
)
LIMIT 1;

SET @empresa_padrao_id := (
  SELECT id
  FROM `empresas`
  WHERE slug = 'burguershake'
  LIMIT 1
);

-- ---------------------------------------------------------------------
-- 3) Colunas de tenant nas tabelas operacionais
-- ---------------------------------------------------------------------
ALTER TABLE `usuarios`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `google_sub` varchar(255) NULL AFTER `senha_pura`,
  ADD COLUMN IF NOT EXISTS `google_foto` varchar(500) NULL AFTER `google_sub`,
  ADD COLUMN IF NOT EXISTS `login_google_ativo` tinyint(1) NOT NULL DEFAULT 0 AFTER `google_foto`;

ALTER TABLE `categorias`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `produtos`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `produto_adicionais`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `pedidos`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `numero_pedido` int(11) NULL AFTER `empresa_id`;

ALTER TABLE `pedido_itens`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `pedido_item_adicionais`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `mesas_qr`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `caixa`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `caixas`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `caixa_movimentos`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `pagamentos_antecipados`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `auditoria`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `fila_impressao`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

ALTER TABLE `logs`
  ADD COLUMN IF NOT EXISTS `empresa_id` int(11) NULL AFTER `id`;

-- ---------------------------------------------------------------------
-- 4) Vincula os dados atuais ao tenant BurguerShake
-- ---------------------------------------------------------------------
UPDATE `usuarios`
SET `empresa_id` = @empresa_padrao_id
WHERE `empresa_id` IS NULL;

UPDATE `categorias`
SET `empresa_id` = @empresa_padrao_id
WHERE `empresa_id` IS NULL;

UPDATE `produtos`
SET `empresa_id` = @empresa_padrao_id
WHERE `empresa_id` IS NULL;

UPDATE `produto_adicionais` pa
INNER JOIN `produtos` p
  ON p.id = pa.produto_id
SET pa.`empresa_id` = p.`empresa_id`
WHERE pa.`empresa_id` IS NULL;

UPDATE `pedidos`
SET
  `empresa_id` = @empresa_padrao_id,
  `numero_pedido` = `id`
WHERE `empresa_id` IS NULL
   OR `numero_pedido` IS NULL;

UPDATE `pedido_itens` pi
INNER JOIN `pedidos` p
  ON p.id = pi.pedido_id
SET pi.`empresa_id` = p.`empresa_id`
WHERE pi.`empresa_id` IS NULL;

UPDATE `pedido_item_adicionais` pia
INNER JOIN `pedido_itens` pi
  ON pi.id = pia.pedido_item_id
SET pia.`empresa_id` = pi.`empresa_id`
WHERE pia.`empresa_id` IS NULL;

UPDATE `mesas_qr`
SET `empresa_id` = @empresa_padrao_id
WHERE `empresa_id` IS NULL;

UPDATE `caixa`
SET `empresa_id` = @empresa_padrao_id
WHERE `empresa_id` IS NULL;

UPDATE `caixas`
SET `empresa_id` = @empresa_padrao_id
WHERE `empresa_id` IS NULL;

UPDATE `caixa_movimentos` cm
LEFT JOIN `caixas` c
  ON c.id = cm.caixa_id
LEFT JOIN `caixa` cx
  ON cx.id = cm.caixa_id
LEFT JOIN `pedidos` p
  ON p.id = cm.pedido_id
SET cm.`empresa_id` = COALESCE(c.`empresa_id`, cx.`empresa_id`, p.`empresa_id`, @empresa_padrao_id)
WHERE cm.`empresa_id` IS NULL;

UPDATE `pagamentos_antecipados` pa
INNER JOIN `pedidos` p
  ON p.id = pa.pedido_id
SET pa.`empresa_id` = p.`empresa_id`
WHERE pa.`empresa_id` IS NULL;

UPDATE `auditoria`
SET `empresa_id` = @empresa_padrao_id
WHERE `empresa_id` IS NULL;

UPDATE `fila_impressao`
SET `empresa_id` = @empresa_padrao_id
WHERE `empresa_id` IS NULL;

UPDATE `logs`
SET `empresa_id` = @empresa_padrao_id
WHERE `empresa_id` IS NULL;

UPDATE `empresas` e
SET e.`ultimo_numero_pedido` = (
  SELECT COALESCE(MAX(p.`numero_pedido`), 0)
  FROM `pedidos` p
  WHERE p.`empresa_id` = e.`id`
)
WHERE e.`id` = @empresa_padrao_id;

-- ---------------------------------------------------------------------
-- 5) Torna empresa_id obrigatório nas tabelas principais
-- ---------------------------------------------------------------------
ALTER TABLE `usuarios`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `categorias`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `produtos`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `produto_adicionais`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `pedidos`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL,
  MODIFY COLUMN IF EXISTS `numero_pedido` int(11) NOT NULL;

ALTER TABLE `pedido_itens`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `pedido_item_adicionais`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `mesas_qr`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `caixa`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `caixas`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `caixa_movimentos`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `pagamentos_antecipados`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `auditoria`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `fila_impressao`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

ALTER TABLE `logs`
  MODIFY COLUMN IF EXISTS `empresa_id` int(11) NOT NULL;

-- ---------------------------------------------------------------------
-- 6) Índices multitenant
-- ---------------------------------------------------------------------
ALTER TABLE `usuarios`
  ADD KEY IF NOT EXISTS `idx_usuarios_empresa` (`empresa_id`),
  ADD UNIQUE KEY IF NOT EXISTS `uk_usuarios_google_sub` (`google_sub`);

ALTER TABLE `categorias`
  DROP INDEX IF EXISTS `nome`;

ALTER TABLE `categorias`
  ADD UNIQUE KEY IF NOT EXISTS `uk_categorias_empresa_nome` (`empresa_id`, `nome`),
  ADD KEY IF NOT EXISTS `idx_categorias_empresa` (`empresa_id`);

ALTER TABLE `produtos`
  ADD KEY IF NOT EXISTS `idx_produtos_empresa` (`empresa_id`),
  ADD KEY IF NOT EXISTS `idx_produtos_empresa_ativo_deleted` (`empresa_id`, `ativo`, `deleted_at`);

ALTER TABLE `produto_adicionais`
  ADD KEY IF NOT EXISTS `idx_produto_adicionais_empresa` (`empresa_id`);

ALTER TABLE `pedidos`
  ADD UNIQUE KEY IF NOT EXISTS `uk_pedidos_empresa_numero` (`empresa_id`, `numero_pedido`),
  ADD KEY IF NOT EXISTS `idx_pedidos_empresa` (`empresa_id`),
  ADD KEY IF NOT EXISTS `idx_pedidos_empresa_mesa_status` (`empresa_id`, `mesa`, `status`),
  ADD KEY IF NOT EXISTS `idx_pedidos_empresa_created` (`empresa_id`, `created_at`);

ALTER TABLE `pedido_itens`
  ADD KEY IF NOT EXISTS `idx_pedido_itens_empresa` (`empresa_id`);

ALTER TABLE `pedido_item_adicionais`
  ADD KEY IF NOT EXISTS `idx_pedido_item_adicionais_empresa` (`empresa_id`);

ALTER TABLE `mesas_qr`
  DROP INDEX IF EXISTS `uniq_mesas_qr_mesa`;

ALTER TABLE `mesas_qr`
  ADD UNIQUE KEY IF NOT EXISTS `uk_mesas_qr_empresa_mesa` (`empresa_id`, `mesa`),
  ADD KEY IF NOT EXISTS `idx_mesas_qr_empresa_ativo` (`empresa_id`, `ativo`);

ALTER TABLE `caixa`
  ADD KEY IF NOT EXISTS `idx_caixa_empresa` (`empresa_id`);

ALTER TABLE `caixas`
  ADD KEY IF NOT EXISTS `idx_caixas_empresa` (`empresa_id`),
  ADD KEY IF NOT EXISTS `idx_caixas_empresa_status` (`empresa_id`, `status`);

ALTER TABLE `caixa_movimentos`
  ADD KEY IF NOT EXISTS `idx_caixa_movimentos_empresa` (`empresa_id`),
  ADD KEY IF NOT EXISTS `idx_caixa_movimentos_empresa_created` (`empresa_id`, `created_at`);

ALTER TABLE `pagamentos_antecipados`
  ADD KEY IF NOT EXISTS `idx_pagamentos_antecipados_empresa` (`empresa_id`);

ALTER TABLE `auditoria`
  ADD KEY IF NOT EXISTS `idx_auditoria_empresa` (`empresa_id`);

ALTER TABLE `fila_impressao`
  ADD KEY IF NOT EXISTS `idx_fila_impressao_empresa` (`empresa_id`);

ALTER TABLE `logs`
  ADD KEY IF NOT EXISTS `idx_logs_empresa` (`empresa_id`);

-- ---------------------------------------------------------------------
-- 7) Chaves estrangeiras de tenant
-- ---------------------------------------------------------------------
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `categorias`
  ADD CONSTRAINT `fk_categorias_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `produtos`
  ADD CONSTRAINT `fk_produtos_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `produto_adicionais`
  ADD CONSTRAINT `fk_produto_adicionais_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedidos_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `pedido_itens`
  ADD CONSTRAINT `fk_pedido_itens_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `pedido_item_adicionais`
  ADD CONSTRAINT `fk_pedido_item_adicionais_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `mesas_qr`
  ADD CONSTRAINT `fk_mesas_qr_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `caixa`
  ADD CONSTRAINT `fk_caixa_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `caixas`
  ADD CONSTRAINT `fk_caixas_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `caixa_movimentos`
  ADD CONSTRAINT `fk_caixa_movimentos_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `pagamentos_antecipados`
  ADD CONSTRAINT `fk_pagamentos_antecipados_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `auditoria`
  ADD CONSTRAINT `fk_auditoria_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `fila_impressao`
  ADD CONSTRAINT `fk_fila_impressao_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

ALTER TABLE `logs`
  ADD CONSTRAINT `fk_logs_empresa`
    FOREIGN KEY IF NOT EXISTS (`empresa_id`) REFERENCES `empresas` (`id`);

-- ---------------------------------------------------------------------
-- 8) Resultado esperado / conferência rápida
-- ---------------------------------------------------------------------
SELECT
  e.id,
  e.nome,
  e.slug,
  p.nome AS plano,
  p.limite_mesas,
  p.limite_pedidos_mes,
  e.ultimo_numero_pedido
FROM empresas e
INNER JOIN planos p
  ON p.id = e.plano_id
ORDER BY e.id ASC;

SELECT
  empresa_id,
  COUNT(*) AS total_pedidos,
  MIN(numero_pedido) AS primeiro_numero,
  MAX(numero_pedido) AS ultimo_numero
FROM pedidos
GROUP BY empresa_id;
