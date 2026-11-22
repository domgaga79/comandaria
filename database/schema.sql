
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;




CREATE TABLE `auditoria` (
  `id` bigint(20) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `entidade` varchar(100) NOT NULL,
  `entidade_id` int(11) DEFAULT NULL,
  `dados_antes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_antes`)),
  `dados_depois` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_depois`)),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;



CREATE TABLE `caixa` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `status` enum('aberto','fechado') NOT NULL DEFAULT 'aberto',
  `valor_abertura` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_fechamento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_sistema` decimal(10,2) NOT NULL DEFAULT 0.00,
  `diferenca` decimal(10,2) NOT NULL DEFAULT 0.00,
  `observacao` text DEFAULT NULL,
  `aberto_em` datetime NOT NULL DEFAULT current_timestamp(),
  `fechado_em` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;



CREATE TABLE `caixas` (
  `id` int(11) NOT NULL,
  `status` enum('aberto','fechado') NOT NULL DEFAULT 'aberto',
  `saldo_inicial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aberto_em` datetime NOT NULL,
  `aberto_por_usuario_id` int(11) DEFAULT NULL,
  `fechado_em` datetime DEFAULT NULL,
  `fechado_por_usuario_id` int(11) DEFAULT NULL,
  `saldo_final_informado` decimal(10,2) DEFAULT NULL,
  `saldo_esperado_dinheiro` decimal(10,2) DEFAULT NULL,
  `diferenca_dinheiro` decimal(10,2) DEFAULT NULL,
  `observacao_abertura` text DEFAULT NULL,
  `observacao_fechamento` text DEFAULT NULL,
  `resumo_fechamento_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






CREATE TABLE `caixa_movimentos` (
  `id` int(11) NOT NULL,
  `caixa_id` int(11) NOT NULL,
  `tipo` enum('venda','sangria','suprimento','cancelamento','pagamento_pedido','pagamento_antecipado','venda_prazo') NOT NULL,
  `forma_pagamento` enum('dinheiro','pix','cartao','prazo') DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `pedido_id` int(11) DEFAULT NULL,
  `pagamento_antecipado_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;






CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;






CREATE TABLE `fila_impressao` (
  `id` bigint(20) NOT NULL,
  `tipo` enum('cozinha','bar','balcao','caixa') NOT NULL,
  `entidade` varchar(50) NOT NULL,
  `entidade_id` int(11) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('pendente','processando','impresso','erro') NOT NULL DEFAULT 'pendente',
  `tentativas` int(11) NOT NULL DEFAULT 0,
  `erro` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;



CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `acao` varchar(100) DEFAULT NULL,
  `alvo_id` int(11) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;



CREATE TABLE `mesas_qr` (
  `id` int(11) NOT NULL,
  `mesa` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;






CREATE TABLE `pagamentos_antecipados` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `mesa` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `forma_pagamento` enum('dinheiro','pix','cartao') NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `mesa` int(11) DEFAULT NULL,
  `cliente` varchar(255) DEFAULT NULL,
  `status` enum('pendente','pago','pronto','cancelado') NOT NULL DEFAULT 'pendente',
  `forma_pagamento` enum('dinheiro','pix','cartao','prazo','antecipado') DEFAULT NULL,
  `prazo_status` enum('aberto','quitado') DEFAULT NULL,
  `prazo_quitado_em` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `origem` enum('atendimento','cliente_qr') NOT NULL DEFAULT 'atendimento',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_adicional` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_antecipado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantidade_itens` int(11) NOT NULL DEFAULT 0,
  `public_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;




DELIMITER $$
CREATE TRIGGER `update_pedidos_timestamp` BEFORE UPDATE ON `pedidos` FOR EACH ROW SET NEW.updated_at = NOW()
$$
DELIMITER ;



CREATE TABLE `pedido_item_adicionais` (
  `id` int(11) NOT NULL,
  `pedido_item_id` int(11) NOT NULL,
  `adicional_id` int(11) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;



CREATE TABLE `pedido_itens` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(10,2) NOT NULL,
  `observacao` text DEFAULT NULL,
  `ponto` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp(),
  `quantidade_pronta` int(11) DEFAULT 0,
  `adicional` tinyint(4) NOT NULL DEFAULT 0,
  `valor_adicional` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;




DELIMITER $$
CREATE TRIGGER `itens_update_pedido` AFTER INSERT ON `pedido_itens` FOR EACH ROW UPDATE pedidos
SET updated_at = NOW()
WHERE id = NEW.pedido_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `itens_update_timestamp` BEFORE UPDATE ON `pedido_itens` FOR EACH ROW SET NEW.updated_at = NOW()
$$
DELIMITER ;



CREATE TABLE `permissoes` (
  `id` int(11) NOT NULL,
  `chave` varchar(100) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


INSERT INTO `permissoes` (`id`, `chave`, `descricao`) VALUES
(1, 'pedido.criar', 'Criar pedidos'),
(2, 'pedido.cancelar', 'Cancelar pedidos'),
(3, 'pedido.editar', 'Editar pedidos'),
(4, 'cozinha.visualizar', 'Ver cozinha'),
(5, 'cozinha.producao', 'Atualizar produção'),
(6, 'caixa.visualizar', 'Ver caixa'),
(7, 'caixa.fechar', 'Fechar caixa'),
(8, 'relatorio.visualizar', 'Ver relatórios'),
(9, 'admin.total', 'Acesso total');



CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `tempo` int(11) DEFAULT 10,
  `permite_obs` tinyint(1) DEFAULT 0,
  `permite_ponto` tinyint(1) NOT NULL DEFAULT 0,
  `adicional_permitido` tinyint(1) DEFAULT 0,
  `valor_adicional` decimal(10,2) DEFAULT 0.00,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;






CREATE TABLE `produto_adicionais` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;



CREATE TABLE `roles_permissoes` (
  `role` varchar(50) NOT NULL,
  `permissao_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


INSERT INTO `roles_permissoes` (`role`, `permissao_id`) VALUES
('admin', 1),
('admin', 2),
('admin', 3),
('admin', 4),
('admin', 5),
('admin', 6),
('admin', 7),
('admin', 8),
('admin', 9),
('caixa', 1),
('caixa', 2),
('caixa', 6),
('caixa', 7),
('caixa', 8),
('cozinha', 4),
('cozinha', 5);



CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `tipo` enum('admin','atendente','cozinha','caixa') DEFAULT 'atendente',
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `senha_pura` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;





ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `entidade` (`entidade`),
  ADD KEY `entidade_id` (`entidade_id`),
  ADD KEY `created_at` (`created_at`);

ALTER TABLE `caixa`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `caixas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_caixas_status` (`status`),
  ADD KEY `idx_caixas_aberto_em` (`aberto_em`),
  ADD KEY `idx_caixas_fechado_em` (`fechado_em`);

ALTER TABLE `caixa_movimentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `caixa_id` (`caixa_id`),
  ADD KEY `idx_caixa_movimentos_tipo` (`tipo`),
  ADD KEY `idx_caixa_movimentos_forma` (`forma_pagamento`),
  ADD KEY `idx_caixa_movimentos_pedido` (`pedido_id`),
  ADD KEY `idx_caixa_movimentos_pagamento_antecipado` (`pagamento_antecipado_id`),
  ADD KEY `idx_caixa_movimentos_created_at` (`created_at`);

ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

ALTER TABLE `fila_impressao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `tipo` (`tipo`);

ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `mesas_qr`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mesas_qr_mesa` (`mesa`),
  ADD UNIQUE KEY `uniq_mesas_qr_token` (`token`),
  ADD KEY `idx_mesas_qr_ativo` (`ativo`);

ALTER TABLE `pagamentos_antecipados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pag_antecipado_pedido` (`pedido_id`),
  ADD KEY `idx_pag_antecipado_mesa` (`mesa`),
  ADD KEY `idx_pag_antecipado_data` (`created_at`);

ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pedidos_public_token_unique` (`public_token`),
  ADD KEY `mesa` (`mesa`),
  ADD KEY `cliente` (`cliente`),
  ADD KEY `idx_pedidos_status` (`status`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_pedidos_mesa_status` (`mesa`,`status`),
  ADD KEY `idx_pedidos_created_at` (`created_at`),
  ADD KEY `idx_pedidos_updated_at` (`updated_at`),
  ADD KEY `idx_pedidos_status_updated` (`status`,`updated_at`),
  ADD KEY `idx_pedidos_forma_pagamento` (`forma_pagamento`),
  ADD KEY `idx_pedidos_total_antecipado` (`total_antecipado`),
  ADD KEY `idx_pedidos_prazo_status` (`prazo_status`);

ALTER TABLE `pedido_item_adicionais`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `pedido_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `idx_pedido_itens_pedido` (`pedido_id`),
  ADD KEY `idx_pedido_itens_status` (`quantidade_pronta`,`quantidade`),
  ADD KEY `idx_pedido_itens_updated` (`updated_at`),
  ADD KEY `idx_cozinha` (`quantidade_pronta`,`updated_at`),
  ADD KEY `idx_pedido_itens_pedido_id` (`pedido_id`),
  ADD KEY `idx_pedido_itens_produto_id` (`produto_id`);

ALTER TABLE `permissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave` (`chave`);

ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_categoria_id` (`categoria_id`),
  ADD KEY `idx_produtos_categoria_id` (`categoria_id`),
  ADD KEY `idx_produtos_ativo_deleted` (`ativo`,`deleted_at`);

ALTER TABLE `produto_adicionais`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `roles_permissoes`
  ADD PRIMARY KEY (`role`,`permissao_id`);

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nome` (`nome`);


ALTER TABLE `auditoria`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `caixa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `caixas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `caixa_movimentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `fila_impressao`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `mesas_qr`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `pagamentos_antecipados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `pedido_item_adicionais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `pedido_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `permissoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `produto_adicionais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_pedidos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

ALTER TABLE `pedido_itens`
  ADD CONSTRAINT `pedido_itens_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pedido_itens_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);

ALTER TABLE `produtos`
  ADD CONSTRAINT `fk_produto_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
