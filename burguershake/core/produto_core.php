<?php

/*
|--------------------------------------------------------------------------
| Produto Core
|--------------------------------------------------------------------------
| Centraliza regras de listagem de produtos para o atendimento.
|
| Banco atual:
|
| produtos:
| id, nome, categoria_id, preco, ativo, created_at, tempo,
| permite_obs, adicional_permitido, valor_adicional, deleted_at
|
| categorias:
| id, nome, ativo
|--------------------------------------------------------------------------
*/

function iconeCategoriaProduto(?string $categoria): string {

    $icones = [
        'Whisky'               => '🥃',
        'Vodka'                => '🍸',
        'Drinks'               => '🍹',
        'Pingas'               => '🥃',

        'Cerveja Lata'         => '🍺',
        'Cerveja Litrinho'     => '🍺',
        'Long Neck'            => '🍻',

        'Refrigerantes'        => '🥤',
        'Bebidas'              => '🥤',

        'Lanches Artesanais'   => '🍔',
        'Lanches Tradicionais' => '🍔',
        'Lanches Gourmet'      => '🍔',

        'Caldos'               => '🥣',
        'Pastéis'              => '🥟',
        'Crepes'               => '🥞',
        'Espetinhos'           => '🍢',
        'Petiscos'             => '🍟',
        'Sucos'                => '🥤',
        'Tapiocas'             => '🌮',
        'Milkshakes'           => '🥤',
        'Casquinha'            => '🦀'
    ];

    return $icones[$categoria] ?? '🍽️';
}

function listarProdutosAtivos(mysqli $conn): array {

    $sql = "
        SELECT
            p.id,
            p.nome,
            p.categoria_id,
            p.preco,
            p.ativo,
            p.created_at,
            p.tempo,
            p.permite_obs,
            p.permite_ponto,
            p.adicional_permitido,
            p.valor_adicional,
            p.deleted_at,

            c.nome AS categoria_nome,
            c.ativo AS categoria_ativa

        FROM produtos p

        LEFT JOIN categorias c
            ON c.id = p.categoria_id

        WHERE p.ativo = 1
        AND p.deleted_at IS NULL
        AND (
            c.id IS NULL
            OR c.ativo = 1
        )

        ORDER BY
            c.nome ASC,
            p.nome ASC
    ";

    $res = $conn->query($sql);

    if(!$res){
        throw new Exception($conn->error);
    }

    $produtos = [];

    while($row = $res->fetch_assoc()){

        $categoria = $row['categoria_nome'] ?: 'Sem categoria';

        $icone = iconeCategoriaProduto($categoria);

        $adicionalPermitido = (int)$row['adicional_permitido'];

        $valorAdicional = (float)$row['valor_adicional'];

        $adicionalAtivo = (
            $adicionalPermitido === 1 &&
            $valorAdicional > 0
        );

        $produtos[] = [
            'id' => (int)$row['id'],

            /*
             * Nome com emoji para o atendimento.
             */
            'nome' => $icone . ' ' . $row['nome'],

            /*
             * Nome puro para futuras telas ou impressão.
             */
            'nome_original' => $row['nome'],

            'categoria_id' => $row['categoria_id'] !== null
                ? (int)$row['categoria_id']
                : null,

            /*
             * O frontend principal usa "cat".
             */
            'cat' => $categoria,

            /*
             * Campo semântico para novos módulos.
             */
            'categoria' => $categoria,

            'icone' => $icone,

            'preco' => (float)$row['preco'],

            'tempo' => (int)$row['tempo'],

            'permite_obs' => (int)$row['permite_obs'],
            'permite_ponto' => (int)($row['permite_ponto'] ?? 0),

            'adicional_permitido' => $adicionalPermitido,

            'valor_adicional' => $adicionalAtivo
                ? $valorAdicional
                : 0,

            /*
             * Campo auxiliar para o frontend:
             * adicional aqui representa o valor monetário disponível.
             * No pedido salvo, adicional será flag 0/1.
             */
            'adicional' => $adicionalAtivo
                ? $valorAdicional
                : 0,

            'ativo' => (int)$row['ativo'],

            'created_at' => $row['created_at']
        ];
    }

    return $produtos;
}