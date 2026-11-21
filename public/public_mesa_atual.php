<?php

/*
|--------------------------------------------------------------------------
| MESARIA EXPRESS - PEDIDO ATUAL DA MESA
|--------------------------------------------------------------------------
| Endpoint público usado por mesa.php para mostrar a conta aberta da mesa.
|
| GET /public_mesa_atual.php?mesa=5&token=TOKEN_DA_MESA
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/conexao.php';
require __DIR__ . '/../helpers/response.php';
require __DIR__ . '/../helpers/logger.php';
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../core/mesa_qr_core.php';

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

function mesariaMontarPedidoPublicoMesa(mysqli $conn, int $pedidoId, int $empresaId): ?array {

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

    $stmt = $conn->prepare("\n        SELECT\n            p.id AS pedido_id,\n            p.numero_pedido,\n            p.mesa,\n            p.cliente,\n            p.status AS pedido_status,\n            p.created_at AS pedido_created_at,\n            p.updated_at AS pedido_updated_at,\n            p.usuario_id,\n            u.nome AS atendente_nome,\n            p.subtotal AS pedido_subtotal,\n            p.total_adicional AS pedido_total_adicional,\n            p.total AS pedido_total,\n            {$selectServico10Ativo}\n            {$selectTaxaServico10}\n            p.quantidade_itens AS pedido_quantidade_itens,\n            p.public_token,\n\n            pi.id AS item_id,\n            pi.produto_id,\n            pi.quantidade,\n            pi.preco_unitario,\n            pi.observacao,\n            pi.ponto,\n            pi.quantidade_pronta,\n            pi.adicional,\n            pi.valor_adicional,\n            pi.created_at AS item_created_at,\n            pi.updated_at AS item_updated_at,\n\n            pr.nome AS produto_nome,\n\n            c.id AS categoria_id,\n            c.nome AS categoria_nome\n\n        FROM pedidos p\n\n        LEFT JOIN pedido_itens pi\n            ON pi.pedido_id = p.id\n           AND pi.empresa_id = p.empresa_id\n\n        LEFT JOIN produtos pr\n            ON pr.id = pi.produto_id\n           AND pr.empresa_id = p.empresa_id\n\n        LEFT JOIN categorias c\n            ON c.id = pr.categoria_id\n           AND c.empresa_id = p.empresa_id\n\n        LEFT JOIN usuarios u\n            ON u.id = p.usuario_id\n           AND u.empresa_id = p.empresa_id\n\n        WHERE p.id = ?\n        AND p.empresa_id = ?\n\n        ORDER BY\n            pi.id ASC\n    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param('ii', $pedidoId, $empresaId);

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();

    if($res->num_rows === 0){
        $stmt->close();
        return null;
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

            $pedido = [
                'id' => (int)$row['pedido_id'],
                'pedido_id' => (int)$row['pedido_id'],
                'numero' => (int)($row['numero_pedido'] ?? $row['pedido_id']),
                'numero_pedido' => (int)($row['numero_pedido'] ?? $row['pedido_id']),
                'tipo' => 'mesa',
                'referencia' => (string)$row['mesa'],
                'identificador' => (string)$row['mesa'],
                'mesa' => $row['mesa'] !== null ? (int)$row['mesa'] : null,
                'cliente' => $row['cliente'],
                'status' => $row['pedido_status'],
                'status_producao' => STATUS_PENDENTE,
                'created_at' => $row['pedido_created_at'],
                'updated_at' => $row['pedido_updated_at'],
                'usuario_id' => $row['usuario_id'] !== null ? (int)$row['usuario_id'] : null,
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
                'public_token' => $row['public_token'],
                'comanda_url' => 'comanda.php?pedido_id=' . (int)$row['pedido_id'] . '&token=' . urlencode((string)$row['public_token']),
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

        $qtdAdicionais = max(0, (int)$row['adicional']);
        $valorAdicional = $qtdAdicionais > 0 ? (float)$row['valor_adicional'] : 0;

        $subtotalItem = (
            $precoUnitario + ($valorAdicional * $qtdAdicionais)
        ) * $quantidade;

        $statusItem = $quantidadePronta >= $quantidade
            ? STATUS_PRONTO
            : STATUS_PENDENTE;

        if($statusItem !== STATUS_PRONTO){
            $todosProntos = false;
        }

        $subtotalCalculado += $precoUnitario * $quantidade;
        $totalAdicionalCalculado += $valorAdicional * $qtdAdicionais * $quantidade;
        $totalCalculado += $subtotalItem;
        $quantidadeCalculada += $quantidade;

        $pedido['itens'][] = [
            'id' => (int)$row['item_id'],
            'item_id' => (int)$row['item_id'],
            'pedido_id' => (int)$row['pedido_id'],
            'produto_id' => (int)$row['produto_id'],
            'nome' => $row['produto_nome'],
            'categoria_id' => $row['categoria_id'] !== null ? (int)$row['categoria_id'] : null,
            'categoria' => $row['categoria_nome'],
            'quantidade' => $quantidade,
            'qtd' => $quantidade,
            'quantidade_pronta' => $quantidadePronta,
            'qtd_pronta' => $quantidadePronta,
            'preco_unitario' => $precoUnitario,
            'preco' => $precoUnitario,
            'adicional' => $qtdAdicionais,
            'valor_adicional' => $valorAdicional,
            'observacao' => $row['observacao'] ?? '',
            'obs' => $row['observacao'] ?? '',
            'ponto' => $row['ponto'] ?? '',
            'status' => $statusItem,
            'subtotal' => round($subtotalItem, 2),
            'created_at' => $row['item_created_at'],
            'updated_at' => $row['item_updated_at']
        ];
    }

    $stmt->close();

    if(!$pedido){
        return null;
    }

    $pedido['subtotal'] = round((float)($pedido['subtotal'] ?: $subtotalCalculado), 2);
    $pedido['total_adicional'] = round((float)($pedido['total_adicional'] ?: $totalAdicionalCalculado), 2);
    $pedido['total'] = round((float)($pedido['total'] ?: $totalCalculado), 2);
    $pedido['servico_10_ativo'] = !empty($pedido['servico_10_ativo']);
    $pedido['taxa_servico_10'] = $pedido['servico_10_ativo']
        ? round(max(0, (float)($pedido['taxa_servico_10'] ?? 0)), 2)
        : 0.00;

    if($pedido['servico_10_ativo'] && $pedido['taxa_servico_10'] <= 0){
        $pedido['taxa_servico_10'] = round((float)$pedido['total'] * 0.10, 2);
    }

    $pedido['total_com_servico'] = round((float)$pedido['total'] + (float)$pedido['taxa_servico_10'], 2);
    $pedido['total_cobravel'] = $pedido['total_com_servico'];
    $pedido['quantidade_itens'] = (int)($pedido['quantidade_itens'] ?: $quantidadeCalculada);

    if(
        $pedido['status'] === STATUS_PAGO ||
        $pedido['status'] === STATUS_CANCELADO
    ){
        $pedido['status_producao'] = $pedido['status'];
    } else {
        $pedido['status_producao'] = ($temItens && $todosProntos)
            ? STATUS_PRONTO
            : STATUS_PENDENTE;
    }

    return $pedido;
}

try {

    $mesa = (int)($_GET['mesa'] ?? 0);
    $token = trim((string)($_GET['token'] ?? ''));

    if($mesa <= 0){
        erro('Mesa inválida', 400);
    }

    if($token === ''){
        erro('Token da mesa inválido', 400);
    }

    $qrMesa = mesariaValidarMesaToken($conn, $mesa, $token, false);
    $empresaId = (int)($qrMesa['empresa_id'] ?? 0);

    if($empresaId <= 0){
        erro('Empresa da mesa não identificada', 400);
    }

    $pedidoAberto = mesariaBuscarPedidoAbertoMesa($conn, $mesa, $empresaId);

    if(!$pedidoAberto){
        sucesso([
            'mesa' => $mesa,
            'pedido' => null
        ]);
    }

    $pedido = mesariaMontarPedidoPublicoMesa(
        $conn,
        (int)$pedidoAberto['pedido_id'],
        $empresaId
    );

    sucesso([
        'mesa' => $mesa,
        'pedido' => $pedido
    ]);

} catch(Throwable $e){

    logErro($e);

    erro(
        $e->getMessage(),
        500
    );
}

if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
