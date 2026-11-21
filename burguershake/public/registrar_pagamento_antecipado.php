<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

$_SERVER['HTTP_ACCEPT'] = 'application/json';

require __DIR__ . '/auth.php';
requireRole(['caixa', 'admin']);

require __DIR__ . '/conexao.php';
require __DIR__ . '/../helpers/response.php';
require __DIR__ . '/../helpers/request.php';
require __DIR__ . '/../helpers/logger.php';
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../core/pedido_core.php';
require __DIR__ . '/../core/caixa_core.php';

$transacaoAberta = false;

try {

    $checkTabela = $conn->query("SHOW TABLES LIKE 'pagamentos_antecipados'");
    $temTabela = $checkTabela instanceof mysqli_result
        && $checkTabela->num_rows > 0;

    if($checkTabela instanceof mysqli_result){
        $checkTabela->free();
    }

    if(!$temTabela || !pedidoTemColunaTotalAntecipado($conn)){
        erro('Estrutura de pagamento antecipado indisponível no banco atual', 500);
    }

    if(!caixaEstruturaDisponivel($conn)){
        erro('Estrutura de controle de caixa indisponível no banco atual', 500);
    }

    $data = bodyJson();

    $pedidoId = (int)($data['pedido_id'] ?? 0);
    $valor = (float)($data['valor'] ?? 0);
    $formaPagamento = strtolower(trim((string)($data['forma_pagamento'] ?? '')));

    if($pedidoId <= 0){
        erro('Pedido inválido', 400);
    }

    if($valor <= 0){
        erro('Informe um valor antecipado maior que zero', 400);
    }

    $formasPermitidas = [
        PAGAMENTO_DINHEIRO,
        PAGAMENTO_PIX,
        PAGAMENTO_CARTAO
    ];

    if(!in_array($formaPagamento, $formasPermitidas, true)){
        erro('Forma de pagamento antecipado inválida', 400);
    }

    $usuarioId = isset($_SESSION['user_id'])
        ? (int)$_SESSION['user_id']
        : null;

    $conn->begin_transaction();
    $transacaoAberta = true;

    $caixaAberto = exigirCaixaAberto($conn);

    $pagamento = registrarPagamentoAntecipado(
        $conn,
        $pedidoId,
        $valor,
        $formaPagamento,
        $usuarioId
    );

    registrarMovimentoCaixa(
        $conn,
        (int)$caixaAberto['id'],
        CAIXA_MOVIMENTO_PAGAMENTO_ANTECIPADO,
        $formaPagamento,
        (float)$pagamento['valor'],
        $pedidoId,
        (int)$pagamento['pagamento_id'],
        $usuarioId,
        'Pagamento antecipado de mesa'
    );

    $conn->commit();
    $transacaoAberta = false;

    sucesso([
        'pedido_id' => $pedidoId,
        'pagamento_id' => $pagamento['pagamento_id'],
        'mesa' => $pagamento['mesa'],
        'valor' => $pagamento['valor'],
        'forma_pagamento' => $pagamento['forma_pagamento'],
        'total' => $pagamento['total'],
        'servico_10_ativo' => $pagamento['servico_10_ativo'] ?? false,
        'taxa_servico_10' => $pagamento['taxa_servico_10'] ?? 0,
        'total_com_servico' => $pagamento['total_com_servico'] ?? $pagamento['total'],
        'total_cobravel' => $pagamento['total_cobravel'] ?? $pagamento['total'],
        'total_antecipado' => $pagamento['total_antecipado'],
        'saldo' => $pagamento['saldo']
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

$conn->close();
