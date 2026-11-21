<?php

/*
|--------------------------------------------------------------------------
| MESARIA EXPRESS - SALVAR PEDIDO PÚBLICO
|--------------------------------------------------------------------------
| Endpoint sem login para o cliente enviar pedido via QR Code da mesa.
|
| Contrato:
| POST /public_salvar_pedido.php
| {
|   "mesa": 5,
|   "token": "TOKEN_DA_MESA",
|   "itens": [
|     {"id": 120, "qtd": 1, "obs": "sem milho", "ponto": "", "adicional": 1}
|   ]
| }
|
| Segurança:
| - Não confia em preço vindo do frontend.
| - Valida mesa + token na tabela mesas_qr.
| - Busca produtos ativos no banco via pedido_core.php.
| - Adiciona itens ao pedido aberto sem limpar itens anteriores.
|--------------------------------------------------------------------------
*/

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/conexao.php';
require __DIR__ . '/../helpers/response.php';
require __DIR__ . '/../helpers/request.php';
require __DIR__ . '/../helpers/logger.php';
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../core/pedido_core.php';
require __DIR__ . '/../core/mesa_qr_core.php';

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

$transacaoAberta = false;

function mesariaSanitizarItensPublicos(array $itens): array {

    if(count($itens) > 50){
        erro('Limite de 50 itens por envio excedido', 400);
    }

    $limpos = [];

    foreach($itens as $item){

        if(!is_array($item)){
            continue;
        }

        $produtoId = (int)($item['id'] ?? $item['produto_id'] ?? 0);

        $qtd = (int)(
            $item['qtd'] ??
            $item['quantidade'] ??
            0
        );

        if($produtoId <= 0 || $qtd <= 0){
            continue;
        }

        $qtd = min($qtd, 20);

        $obs = trim((string)(
            $item['obs'] ??
            $item['observacao'] ??
            ''
        ));

        $ponto = trim((string)($item['ponto'] ?? ''));

        $adicional = (int)($item['adicional'] ?? 0);
        $adicional = max(0, min(5, $adicional));

        $limpos[] = [
            'id' => $produtoId,
            'qtd' => $qtd,
            'obs' => mb_substr($obs, 0, 240, 'UTF-8'),
            'observacao' => mb_substr($obs, 0, 240, 'UTF-8'),
            'ponto' => mb_substr($ponto, 0, 50, 'UTF-8'),
            'adicional' => $adicional
        ];
    }

    return $limpos;
}

try {

    if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
        erro('Método não permitido', 405);
    }

    $data = bodyJson();

    $mesa = (int)($data['mesa'] ?? 0);
    $token = trim((string)($data['token'] ?? ''));
    $itens = $data['itens'] ?? null;

    if($mesa <= 0){
        erro('Mesa inválida', 400);
    }

    if($token === ''){
        erro('Token da mesa inválido', 400);
    }

    if(!is_array($itens)){
        erro('Itens inválidos', 400);
    }

    $itens = mesariaSanitizarItensPublicos($itens);

    if(empty($itens)){
        erro('Adicione pelo menos um item', 400);
    }

    $conn->begin_transaction();
    $transacaoAberta = true;

    mesariaValidarMesaToken(
        $conn,
        $mesa,
        $token,
        true
    );

    $pedido = buscarOuCriarPedido(
        $conn,
        null,
        $mesa,
        null,
        null
    );

    $pedidoId = (int)$pedido['pedido_id'];

    if($pedidoId <= 0){
        throw new Exception('Não foi possível criar ou localizar o pedido da mesa.');
    }

    if((bool)($pedido['created'] ?? false)){
        mesariaMarcarOrigemPedido($conn, $pedidoId, 'cliente_qr');
    }

    /*
     * Importante:
     * salvarItensPedido() apenas insere os itens recebidos.
     * Ele NÃO limpa itens anteriores. Isso permite novos pedidos da mesma mesa.
     */
    $totalItensSalvos = salvarItensPedido(
        $conn,
        $pedidoId,
        $itens
    );

    if($totalItensSalvos <= 0){
        throw new Exception('Nenhum item válido foi salvo.');
    }

    $totais = recalcularPedido(
        $conn,
        $pedidoId
    );

    atualizarStatusPedido(
        $conn,
        $pedidoId,
        STATUS_PENDENTE
    );

    $publicToken = garantirPublicTokenPedido(
        $conn,
        $pedidoId,
        $pedido['public_token'] ?? null
    );

    $conn->commit();
    $transacaoAberta = false;

    sucesso([
        'pedido_id' => $pedidoId,
        'id' => $pedidoId,
        'numero' => $pedidoId,
        'created' => (bool)($pedido['created'] ?? false),
        'status' => STATUS_PENDENTE,
        'origem' => 'cliente_qr',
        'mesa' => $mesa,
        'itens_salvos' => $totalItensSalvos,
        'totais' => $totais,
        'public_token' => $publicToken,
        'comanda_url' => 'comanda.php?pedido_id=' . $pedidoId . '&token=' . urlencode($publicToken)
    ]);

} catch(Throwable $e){

    if(isset($conn) && $transacaoAberta){
        try {
            $conn->rollback();
        } catch(Throwable $rollbackError) {
            logErro($rollbackError);
        }
    }

    logErro($e);

    erro(
        $e->getMessage(),
        500
    );
}

if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
