<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

/*
|--------------------------------------------------------------------------
| Força respostas JSON em caso de sessão expirada
|--------------------------------------------------------------------------
*/
$_SERVER['HTTP_ACCEPT'] = 'application/json';

require __DIR__ . '/auth.php';
requireRole(['atendente', 'cozinha', 'caixa', 'admin']);

require __DIR__ . '/conexao.php';
require __DIR__ . '/../core/tenant_core.php';

$empresaId = tenantResolverEmpresaId(authEmpresaId());

require __DIR__ . '/../helpers/response.php';
require __DIR__ . '/../helpers/logger.php';
require __DIR__ . '/../config/constants.php';

try {

    /*
    |--------------------------------------------------------------------------
    | PARÂMETROS
    |--------------------------------------------------------------------------
    | ?last=HASH       => usado para long polling simples
    | ?todos=1         => inclui pagos/cancelados
    | ?status=pronto   => filtra por status específico
    |--------------------------------------------------------------------------
    */
    $last = trim($_GET['last'] ?? '');

    $incluirFechados = isset($_GET['todos']) && (int)$_GET['todos'] === 1;

    $statusFiltro = trim($_GET['status'] ?? '');

    $where = [];
    $params = [];
    $types = '';

    $where[] = "p.empresa_id = ?";
    $params[] = $empresaId;
    $types .= 'i';

    if(!$incluirFechados){
        $where[] = "p.status NOT IN (?, ?)";
        $params[] = STATUS_PAGO;
        $params[] = STATUS_CANCELADO;
        $types .= 'ss';
    }

    if($statusFiltro !== ''){

        $statusPermitidos = [
            STATUS_PENDENTE,
            STATUS_PRONTO,
            STATUS_PAGO,
            STATUS_CANCELADO
        ];

        if(!in_array($statusFiltro, $statusPermitidos, true)){
            erro('Status inválido', 400);
        }

        $where[] = "p.status = ?";
        $params[] = $statusFiltro;
        $types .= 's';
    }

    $whereSql = !empty($where)
        ? 'WHERE ' . implode(' AND ', $where)
        : '';

    /*
    |--------------------------------------------------------------------------
    | CONSULTA
    |--------------------------------------------------------------------------
    | Compatibilidade: se o SQL da Mesaria ainda não tiver sido executado,
    | mantém a listagem interna funcionando com origem padrão 'atendimento'.
    |--------------------------------------------------------------------------
    */
    $temColunaOrigem = false;

    $checkOrigem = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'origem'");

    if($checkOrigem instanceof mysqli_result){
        $temColunaOrigem = $checkOrigem->num_rows > 0;
        $checkOrigem->free();
    }

    $selectOrigem = $temColunaOrigem
        ? "p.origem AS pedido_origem,"
        : "'atendimento' AS pedido_origem,";

    $temColunaFormaPagamento = false;

    $checkFormaPagamento = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'forma_pagamento'");

    if($checkFormaPagamento instanceof mysqli_result){
        $temColunaFormaPagamento = $checkFormaPagamento->num_rows > 0;
        $checkFormaPagamento->free();
    }

    $selectFormaPagamento = $temColunaFormaPagamento
        ? "p.forma_pagamento AS pedido_forma_pagamento,"
        : "NULL AS pedido_forma_pagamento,";

    $temColunaTotalAntecipado = false;

    $checkTotalAntecipado = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'total_antecipado'");

    if($checkTotalAntecipado instanceof mysqli_result){
        $temColunaTotalAntecipado = $checkTotalAntecipado->num_rows > 0;
        $checkTotalAntecipado->free();
    }

    $selectTotalAntecipado = $temColunaTotalAntecipado
        ? "p.total_antecipado AS pedido_total_antecipado,"
        : "0 AS pedido_total_antecipado,";

    $temColunaServico10Ativo = false;
    $temColunaTaxaServico10 = false;

    $checkServico10Ativo = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'servico_10_ativo'");

    if($checkServico10Ativo instanceof mysqli_result){
        $temColunaServico10Ativo = $checkServico10Ativo->num_rows > 0;
        $checkServico10Ativo->free();
    }

    $checkTaxaServico10 = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'taxa_servico_10'");

    if($checkTaxaServico10 instanceof mysqli_result){
        $temColunaTaxaServico10 = $checkTaxaServico10->num_rows > 0;
        $checkTaxaServico10->free();
    }

    $temEstruturaServico10 = $temColunaServico10Ativo && $temColunaTaxaServico10;

    $selectServico10Ativo = $temEstruturaServico10
        ? "p.servico_10_ativo AS pedido_servico_10_ativo,"
        : "0 AS pedido_servico_10_ativo,";

    $selectTaxaServico10 = $temEstruturaServico10
        ? "p.taxa_servico_10 AS pedido_taxa_servico_10,"
        : "0 AS pedido_taxa_servico_10,";

    $sql = "
        SELECT
            p.id AS pedido_id,
            p.numero_pedido,
            p.mesa,
            p.cliente,
            p.status AS pedido_status,
            p.created_at AS pedido_created_at,
            p.updated_at AS pedido_updated_at,
            p.usuario_id,
            {$selectOrigem}
            {$selectFormaPagamento}
            {$selectTotalAntecipado}
            {$selectServico10Ativo}
            {$selectTaxaServico10}
            u.nome AS atendente_nome,
            p.subtotal AS pedido_subtotal,
            p.total_adicional AS pedido_total_adicional,
            p.total AS pedido_total,
            p.quantidade_itens AS pedido_quantidade_itens,

            pi.id AS item_id,
            pi.produto_id,
            pi.quantidade,
            pi.preco_unitario,
            pi.observacao,
            pi.ponto,
            pi.quantidade_pronta,
            pi.adicional,
            pi.valor_adicional,
            pi.created_at AS item_created_at,
            pi.updated_at AS item_updated_at,

            pr.nome AS produto_nome,
            pr.imagem_url AS produto_imagem_url,
            pr.tempo AS produto_tempo,
            pr.permite_obs,
            pr.adicional_permitido,

            c.id AS categoria_id,
            c.nome AS categoria_nome

        FROM pedidos p

        LEFT JOIN pedido_itens pi
            ON pi.pedido_id = p.id

        LEFT JOIN produtos pr
            ON pr.id = pi.produto_id
            AND pr.empresa_id = p.empresa_id

        LEFT JOIN categorias c
            ON c.id = pr.categoria_id

        LEFT JOIN usuarios u
            ON u.id = p.usuario_id

        {$whereSql}

        ORDER BY
            p.created_at ASC,
            p.id ASC,
            pi.id ASC
    ";

    $stmt = $conn->prepare($sql);

    if(!$stmt){
        throw new Exception($conn->error);
    }

    if(!empty($params)){
        $stmt->bind_param($types, ...$params);
    }

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();

    $pedidos = [];
    $hashBase = [];

    while($row = $res->fetch_assoc()){

        $pedidoId = (int)$row['pedido_id'];

        if(!isset($pedidos[$pedidoId])){

            $tipo = !empty($row['mesa'])
                ? 'mesa'
                : 'cliente';

            $referencia = $tipo === 'mesa'
                ? (string)$row['mesa']
                : (string)$row['cliente'];

            $pedidos[$pedidoId] = [
                'id' => $pedidoId,
                'pedido_id' => $pedidoId,

                // Número operacional da comanda
                'numero' => $pedidoId,

                'tipo' => $tipo,
                'referencia' => $referencia,
                'identificador' => $referencia,

                'mesa' => $row['mesa'] !== null
                    ? (int)$row['mesa']
                    : null,

                'cliente' => $row['cliente'],

                'status' => $row['pedido_status'],
                'status_producao' => STATUS_PENDENTE,

                'created_at' => $row['pedido_created_at'],
                'updated_at' => $row['pedido_updated_at'],

                'usuario_id' => $row['usuario_id'] !== null
                    ? (int)$row['usuario_id']
                    : null,

                'origem' => $row['pedido_origem'] ?? 'atendimento',
                'origem_label' => (($row['pedido_origem'] ?? 'atendimento') === 'cliente_qr')
                    ? 'Cliente via QR Code'
                    : 'Atendimento',

                'forma_pagamento' => $row['pedido_forma_pagamento'] ?: null,
                'forma_pagamento_label' => [
                    PAGAMENTO_DINHEIRO => 'Dinheiro',
                    PAGAMENTO_PIX => 'Pix',
                    PAGAMENTO_CARTAO => 'Cartão',
                    PAGAMENTO_PRAZO => 'A prazo',
                    PAGAMENTO_ANTECIPADO => 'Antecipado'
                ][(string)($row['pedido_forma_pagamento'] ?? '')] ?? null,

                'atendente_nome' => $row['atendente_nome'] ?: null,
                'atendente' => $row['atendente_nome'] ?: null,

                'subtotal' => (float)$row['pedido_subtotal'],
                'total_adicional' => (float)$row['pedido_total_adicional'],
                'total' => (float)$row['pedido_total'],
                'servico_10_ativo' => !empty($row['pedido_servico_10_ativo']),
                'taxa_servico_10' => (float)($row['pedido_taxa_servico_10'] ?? 0),
                'total_com_servico' => (float)$row['pedido_total'] + (float)($row['pedido_taxa_servico_10'] ?? 0),
                'total_cobravel' => (float)$row['pedido_total'] + (float)($row['pedido_taxa_servico_10'] ?? 0),
                'total_antecipado' => (float)($row['pedido_total_antecipado'] ?? 0),
                'saldo' => max(0, ((float)$row['pedido_total'] + (float)($row['pedido_taxa_servico_10'] ?? 0)) - (float)($row['pedido_total_antecipado'] ?? 0)),
                'quantidade_itens' => (int)$row['pedido_quantidade_itens'],

                'itens' => []
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Pedido sem item
        |--------------------------------------------------------------------------
        */
        if(empty($row['item_id'])){
            $hashBase[] = [
                'pedido' => $pedidoId,
                'status' => $row['pedido_status'],
                'updated_at' => $row['pedido_updated_at'],
                'total' => $row['pedido_total'],
                'atendente_nome' => $row['atendente_nome'],
                'origem' => $row['pedido_origem'] ?? 'atendimento',
                'forma_pagamento' => $row['pedido_forma_pagamento'] ?? null,
                'servico_10_ativo' => $row['pedido_servico_10_ativo'] ?? 0,
                'taxa_servico_10' => $row['pedido_taxa_servico_10'] ?? 0,
                'total_antecipado' => $row['pedido_total_antecipado'] ?? 0
            ];

            continue;
        }

        $quantidade = (int)$row['quantidade'];
        $quantidadePronta = (int)$row['quantidade_pronta'];

        $precoUnitario = (float)$row['preco_unitario'];

        $adicionalFlag = max(0, (int)$row['adicional']);

        $valorAdicional = $adicionalFlag > 0
            ? (float)$row['valor_adicional']
            : 0;

        $subtotalItem = (
            $precoUnitario + ($valorAdicional * $adicionalFlag)
        ) * $quantidade;

        $statusItem = $quantidadePronta >= $quantidade
            ? STATUS_PRONTO
            : STATUS_PENDENTE;

        $pedidos[$pedidoId]['itens'][] = [
            'id' => (int)$row['item_id'],
            'item_id' => (int)$row['item_id'],

            'pedido_id' => $pedidoId,

            'produto_id' => (int)$row['produto_id'],
            'nome' => $row['produto_nome'],
            'imagem_url' => $row['produto_imagem_url'],

            'categoria_id' => $row['categoria_id'] !== null
                ? (int)$row['categoria_id']
                : null,

            'categoria' => $row['categoria_nome'],

            'quantidade' => $quantidade,
            'qtd' => $quantidade,

            'quantidade_pronta' => $quantidadePronta,
            'qtd_pronta' => $quantidadePronta,

            'preco_unitario' => $precoUnitario,
            'preco' => $precoUnitario,

            /*
             * adicional = flag 0/1
             * valor_adicional = valor monetário unitário
             */
            'adicional' => $adicionalFlag,
            'valor_adicional' => $valorAdicional,

            'observacao' => $row['observacao'] ?? '',
            'obs' => $row['observacao'] ?? '',

            'ponto' => $row['ponto'] ?? '',

            'status' => $statusItem,

            'subtotal' => round($subtotalItem, 2),

            'tempo' => $row['produto_tempo'] !== null
                ? (int)$row['produto_tempo']
                : null,

            'permite_obs' => (int)($row['permite_obs'] ?? 0),

            'adicional_permitido' => (int)($row['adicional_permitido'] ?? 0),

            'created_at' => $row['item_created_at'],
            'updated_at' => $row['item_updated_at']
        ];

        $hashBase[] = [
            'pedido_id' => $pedidoId,
            'pedido_status' => $row['pedido_status'],
            'pedido_updated_at' => $row['pedido_updated_at'],
            'pedido_total' => $row['pedido_total'],
            'forma_pagamento' => $row['pedido_forma_pagamento'] ?? null,
            'servico_10_ativo' => $row['pedido_servico_10_ativo'] ?? 0,
            'taxa_servico_10' => $row['pedido_taxa_servico_10'] ?? 0,
            'total_antecipado' => $row['pedido_total_antecipado'] ?? 0,
            'item_id' => $row['item_id'],
            'quantidade' => $row['quantidade'],
            'quantidade_pronta' => $row['quantidade_pronta'],
            'preco_unitario' => $row['preco_unitario'],
            'adicional' => $row['adicional'],
            'valor_adicional' => $row['valor_adicional'],
            'observacao' => $row['observacao'],
            'ponto' => $row['ponto'],
            'item_updated_at' => $row['item_updated_at']
        ];
    }

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | DERIVAÇÕES
    |--------------------------------------------------------------------------
    */
    foreach($pedidos as &$pedido){

        $itens = $pedido['itens'];

        $subtotalCalculado = 0;
        $totalAdicionalCalculado = 0;
        $totalCalculado = 0;
        $quantidadeCalculada = 0;

        $todosProntos = !empty($itens);

        foreach($itens as $item){

            $quantidade = (int)$item['quantidade'];

            $preco = (float)$item['preco_unitario'];

            $subtotalCalculado += $preco * $quantidade;

            $qtdAdicionais = max(0, (int)$item['adicional']);

            if($qtdAdicionais > 0){
                $totalAdicionalCalculado +=
                    (float)$item['valor_adicional'] * $qtdAdicionais * $quantidade;
            }

            $totalCalculado += (float)$item['subtotal'];

            $quantidadeCalculada += $quantidade;

            if($item['status'] !== STATUS_PRONTO){
                $todosProntos = false;
            }
        }

        /*
         * Usa snapshot do banco.
         * Se estiver zerado, usa cálculo em memória.
         */
        $pedido['subtotal'] = round(
            (float)($pedido['subtotal'] ?: $subtotalCalculado),
            2
        );

        $pedido['total_adicional'] = round(
            (float)($pedido['total_adicional'] ?: $totalAdicionalCalculado),
            2
        );

        $pedido['total'] = round(
            (float)($pedido['total'] ?: $totalCalculado),
            2
        );

        $pedido['servico_10_ativo'] = !empty($pedido['servico_10_ativo']);

        $pedido['taxa_servico_10'] = $pedido['servico_10_ativo']
            ? round(max(0, (float)($pedido['taxa_servico_10'] ?? 0)), 2)
            : 0.00;

        if($pedido['servico_10_ativo'] && $pedido['taxa_servico_10'] <= 0){
            $pedido['taxa_servico_10'] = round((float)$pedido['total'] * 0.10, 2);
        }

        $pedido['total_com_servico'] = round(
            (float)$pedido['total'] + (float)$pedido['taxa_servico_10'],
            2
        );

        $pedido['total_cobravel'] = $pedido['total_com_servico'];

        $pedido['total_antecipado'] = round(
            max(0, (float)($pedido['total_antecipado'] ?? 0)),
            2
        );

        $pedido['saldo'] = round(
            max(0, (float)$pedido['total_cobravel'] - (float)$pedido['total_antecipado']),
            2
        );

        $pedido['quantidade_itens'] = (int)(
            $pedido['quantidade_itens'] ?: $quantidadeCalculada
        );

        if(
            $pedido['status'] === STATUS_PAGO ||
            $pedido['status'] === STATUS_CANCELADO
        ){
            $pedido['status_producao'] = $pedido['status'];
        } else {
            $pedido['status_producao'] = $todosProntos
                ? STATUS_PRONTO
                : STATUS_PENDENTE;
        }
    }

    unset($pedido);

    /*
    |--------------------------------------------------------------------------
    | HASH PARA ATUALIZAÇÃO
    |--------------------------------------------------------------------------
    */
    $ultima = md5(
        json_encode(
            $hashBase,
            JSON_UNESCAPED_UNICODE
        )
    );

    if($last !== '' && hash_equals($ultima, $last)){

        sucesso([
            'changed' => false,
            'ultima' => $ultima,
            'pedidos' => []
        ]);
    }

    sucesso([
        'changed' => true,
        'ultima' => $ultima,
        'pedidos' => array_values($pedidos)
    ]);

} catch(Throwable $e){

    logErro($e);

    erro(
        $e->getMessage(),
        500
    );
}

$conn->close();