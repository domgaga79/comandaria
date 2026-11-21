<?php

/*
|--------------------------------------------------------------------------
| Catálogo Inicial por Empresa
|--------------------------------------------------------------------------
| Cria categorias e produtos editáveis para empresas que nascem pelo
| primeiro login Google. A função é conservadora: se a empresa já tiver
| categorias ou produtos, ela não injeta novos registros automaticamente.
|--------------------------------------------------------------------------
*/

if(!function_exists('catalogoInicialContarRegistros')){
    function catalogoInicialContarRegistros(mysqli $conn, string $tabela, int $empresaId): int {
        $tabelasPermitidas = ['categorias', 'produtos'];

        if(!in_array($tabela, $tabelasPermitidas, true)){
            throw new InvalidArgumentException('Tabela não permitida para contagem do catálogo inicial.');
        }

        $sql = "SELECT COUNT(*) AS total FROM {$tabela} WHERE empresa_id = ?";
        $stmt = $conn->prepare($sql);

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('i', $empresaId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['total'] ?? 0);
    }
}

if(!function_exists('catalogoInicialCriarParaEmpresa')){
    function catalogoInicialCriarParaEmpresa(mysqli $conn, int $empresaId): array {
        if($empresaId <= 0){
            throw new InvalidArgumentException('Empresa inválida para criar catálogo inicial.');
        }

        $totalCategorias = catalogoInicialContarRegistros($conn, 'categorias', $empresaId);
        $totalProdutos = catalogoInicialContarRegistros($conn, 'produtos', $empresaId);

        if($totalCategorias > 0 || $totalProdutos > 0){
            return [
                'criado' => false,
                'motivo' => 'empresa_ja_possui_catalogo',
                'categorias' => 0,
                'produtos' => 0
            ];
        }

        $categorias = [
            'Lanches Tradicionais',
            'Petiscos',
            'Refrigerantes',
            'Bebidas',
            'Sucos',
            'Milkshakes',
            'Sobremesas'
        ];

        $categoriaIds = [];

        $stmtCategoria = $conn->prepare("\n            INSERT INTO categorias (\n                empresa_id,\n                nome,\n                ativo\n            ) VALUES (?, ?, 1)\n        ");

        if(!$stmtCategoria){
            throw new Exception($conn->error);
        }

        foreach($categorias as $categoriaNome){
            $stmtCategoria->bind_param('is', $empresaId, $categoriaNome);

            if(!$stmtCategoria->execute()){
                throw new Exception($stmtCategoria->error);
            }

            $categoriaIds[$categoriaNome] = (int)$conn->insert_id;
        }

        $stmtCategoria->close();

        $produtos = [
            [
                'nome' => 'Hambúrguer Tradicional',
                'categoria' => 'Lanches Tradicionais',
                'preco' => 18.00,
                'tempo' => 15,
                'permite_obs' => 1
            ],
            [
                'nome' => 'X-Salada',
                'categoria' => 'Lanches Tradicionais',
                'preco' => 22.00,
                'tempo' => 15,
                'permite_obs' => 1
            ],
            [
                'nome' => 'X-Bacon',
                'categoria' => 'Lanches Tradicionais',
                'preco' => 25.00,
                'tempo' => 15,
                'permite_obs' => 1
            ],
            [
                'nome' => 'Batata Frita Média',
                'categoria' => 'Petiscos',
                'preco' => 18.00,
                'tempo' => 15,
                'permite_obs' => 1
            ],
            [
                'nome' => 'Porção de Calabresa',
                'categoria' => 'Petiscos',
                'preco' => 28.00,
                'tempo' => 18,
                'permite_obs' => 1
            ],
            [
                'nome' => 'Refrigerante Lata',
                'categoria' => 'Refrigerantes',
                'preco' => 6.00,
                'tempo' => 5,
                'permite_obs' => 0
            ],
            [
                'nome' => 'Água Mineral',
                'categoria' => 'Bebidas',
                'preco' => 3.00,
                'tempo' => 5,
                'permite_obs' => 0
            ],
            [
                'nome' => 'Suco Natural',
                'categoria' => 'Sucos',
                'preco' => 8.00,
                'tempo' => 8,
                'permite_obs' => 1
            ],
            [
                'nome' => 'Milkshake Chocolate',
                'categoria' => 'Milkshakes',
                'preco' => 16.00,
                'tempo' => 8,
                'permite_obs' => 1
            ],
            [
                'nome' => 'Pudim',
                'categoria' => 'Sobremesas',
                'preco' => 10.00,
                'tempo' => 5,
                'permite_obs' => 0
            ]
        ];

        $stmtProduto = $conn->prepare("\n            INSERT INTO produtos (\n                empresa_id,\n                nome,\n                categoria_id,\n                preco,\n                ativo,\n                tempo,\n                permite_obs,\n                permite_ponto,\n                adicional_permitido,\n                valor_adicional,\n                created_at,\n                deleted_at\n            ) VALUES (\n                ?, ?, ?, ?, 1, ?, ?, 0, 0, 0.00, NOW(), NULL\n            )\n        ");

        if(!$stmtProduto){
            throw new Exception($conn->error);
        }

        foreach($produtos as $produto){
            $categoriaId = (int)($categoriaIds[$produto['categoria']] ?? 0);

            if($categoriaId <= 0){
                throw new Exception('Categoria do catálogo inicial não encontrada: ' . $produto['categoria']);
            }

            $nome = (string)$produto['nome'];
            $preco = (float)$produto['preco'];
            $tempo = (int)$produto['tempo'];
            $permiteObs = (int)$produto['permite_obs'];

            $stmtProduto->bind_param(
                'isidii',
                $empresaId,
                $nome,
                $categoriaId,
                $preco,
                $tempo,
                $permiteObs
            );

            if(!$stmtProduto->execute()){
                throw new Exception($stmtProduto->error);
            }
        }

        $stmtProduto->close();

        return [
            'criado' => true,
            'motivo' => 'catalogo_inicial_criado',
            'categorias' => count($categorias),
            'produtos' => count($produtos)
        ];
    }
}
