<?php

/*
|--------------------------------------------------------------------------
| PUBLIC BUSCAR PEDIDO
|--------------------------------------------------------------------------
| Endpoint público para a tela da comanda via QR Code.
|
| Não usa auth.php.
|
| Contrato:
|
| GET /public_buscar_pedido.php?pedido_id=10&token=HASH
|
| Este arquivo exige que a tabela pedidos tenha:
|
| public_token VARCHAR(64) NULL
|--------------------------------------------------------------------------
*/

header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/conexao.php';
require __DIR__ . '/../config/constants.php';

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

function responderPublico(
    bool $ok,
    array $dados = [],
    int $http = 200
): void {

    http_response_code($http);

    echo json_encode(
        array_merge(
            [
                'ok' => $ok
            ],
            $dados
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

try {

    $pedidoId = (int)(
        $_GET['pedido_id'] ??
        $_GET['id'] ??
        0
    );

    $token = trim((string)($_GET['token'] ?? ''));

    if($pedidoId <= 0){
        responderPublico(false, [
            'erro' => 'Pedido inválido'
        ], 400);
    }

    if($token === ''){
        responderPublico(false, [
            'erro' => 'Token inválido'
        ], 400);
    }

    /*
    |--------------------------------------------------------------------------
    | Valida token público
    |--------------------------------------------------------------------------
    */
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

    $stmt = $conn->prepare("
        SELECT
            p.id AS pedido_id,
            p.numero_pedido,
            p.mesa,
            p.cliente,
            p.status AS pedido_status,
            p.created_at AS pedido_created_at,
            p.updated_at AS pedido_updated_at,
            p.usuario_id,
            u.nome AS atendente_nome,
            p.subtotal AS pedido_subtotal,
            p.total_adicional AS pedido_total_adicional,
            p.total AS pedido_total,
            {$selectServico10Ativo}
            {$selectTaxaServico10}
            p.quantidade_itens AS pedido_quantidade_itens,
            p.public_token,

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

            c.id AS categoria_id,
            c.nome AS categoria_nome

        FROM pedidos p

        LEFT JOIN pedido_itens pi
            ON pi.pedido_id = p.id
           AND pi.empresa_id = p.empresa_id

        LEFT JOIN produtos pr
            ON pr.id = pi.produto_id
           AND pr.empresa_id = p.empresa_id

        LEFT JOIN categorias c
            ON c.id = pr.categoria_id
           AND c.empresa_id = p.empresa_id

        LEFT JOIN usuarios u
            ON u.id = p.usuario_id
           AND u.empresa_id = p.empresa_id

        WHERE p.id = ?
        AND p.public_token = ?

        ORDER BY
            pi.id ASC
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "is",
        $pedidoId,
        $token
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();

    if($res->num_rows === 0){

        $stmt->close();

        responderPublico(false, [
            'erro' => 'Comanda não encontrada'
        ], 404);
    }

    $pedido = null;

    $subtotalCalculado = 0;
    $totalAdicionalCalculado = 0;
    $totalCalculado = 0;
    $quantidadeCalculada = 0;

    $todosProntos = true;
    $temItens = false;

    while($row = $res->fetch_assoc()){

        if(!$pedido){

            $tipo = !empty($row['mesa'])
                ? 'mesa'
                : 'cliente';

            $referencia = $tipo === 'mesa'
                ? (string)$row['mesa']
                : (string)$row['cliente'];

            $pedido = [
                'id' => (int)$row['pedido_id'],
                'pedido_id' => (int)$row['pedido_id'],
                'numero' => (int)($row['numero_pedido'] ?? $row['pedido_id']),
                'numero_pedido' => (int)($row['numero_pedido'] ?? $row['pedido_id']),

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

                'atendente_nome' => $row['atendente_nome'] ?: null,
                'atendente' => $row['atendente_nome'] ?: null,

                'subtotal' => (float)$row['pedido_subtotal'],
                'total_adicional' => (float)$row['pedido_total_adicional'],
                'total' => (float)$row['pedido_total'],
                'servico_10_ativo' => !empty($row['pedido_servico_10_ativo']),
                'taxa_servico_10' => (float)($row['pedido_taxa_servico_10'] ?? 0),
                'total_com_servico' => (float)$row['pedido_total'] + (float)($row['pedido_taxa_servico_10'] ?? 0),
                'total_cobravel' => (float)$row['pedido_total'] + (float)($row['pedido_taxa_servico_10'] ?? 0),
                'quantidade_itens' => (int)$row['pedido_quantidade_itens'],

                /*
                 * Retornado para a própria tela pública manter polling.
                 * Não concede permissão administrativa.
                 */
                'public_token' => $row['public_token'],

                'itens' => []
            ];
        }

        if(empty($row['item_id'])){
            continue;
        }

        $temItens = true;

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

        if($statusItem !== STATUS_PRONTO){
            $todosProntos = false;
        }

        $item = [
            'id' => (int)$row['item_id'],
            'item_id' => (int)$row['item_id'],

            'pedido_id' => (int)$row['pedido_id'],

            'produto_id' => (int)$row['produto_id'],

            'nome' => $row['produto_nome'],

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

            'adicional' => $adicionalFlag,
            'valor_adicional' => $valorAdicional,

            'observacao' => $row['observacao'] ?? '',
            'obs' => $row['observacao'] ?? '',

            'ponto' => $row['ponto'] ?? '',

            'status' => $statusItem,

            'subtotal' => round($subtotalItem, 2),

            'created_at' => $row['item_created_at'],
            'updated_at' => $row['item_updated_at']
        ];

        $pedido['itens'][] = $item;

        $subtotalCalculado += $precoUnitario * $quantidade;

        if($adicionalFlag > 0){
            $totalAdicionalCalculado += $valorAdicional * $adicionalFlag * $quantidade;
        }

        $totalCalculado += $subtotalItem;

        $quantidadeCalculada += $quantidade;
    }

    $stmt->close();

    if(!$pedido){
        responderPublico(false, [
            'erro' => 'Comanda não encontrada'
        ], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | Status de produção
    |--------------------------------------------------------------------------
    */
    if(
        $pedido['status'] === STATUS_PAGO ||
        $pedido['status'] === STATUS_CANCELADO
    ){
        $pedido['status_producao'] = $pedido['status'];
    } else {
        $pedido['status_producao'] = (
            $temItens &&
            $todosProntos
        )
            ? STATUS_PRONTO
            : STATUS_PENDENTE;
    }

    /*
    |--------------------------------------------------------------------------
    | Totais
    |--------------------------------------------------------------------------
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

    $pedido['total_com_servico'] = round((float)$pedido['total'] + (float)$pedido['taxa_servico_10'], 2);
    $pedido['total_cobravel'] = $pedido['total_com_servico'];

    $pedido['quantidade_itens'] = (int)(
        $pedido['quantidade_itens'] ?: $quantidadeCalculada
    );

    responderPublico(true, [
        'pedido' => $pedido
    ]);

} catch(Throwable $e){

    error_log(
        '[ERRO public_buscar_pedido.php] ' .
        $e->getMessage()
    );

    responderPublico(false, [
        'erro' => $e->getMessage()
    ], 500);
}

$conn->close();
