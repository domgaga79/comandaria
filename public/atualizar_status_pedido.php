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
require __DIR__ . '/conexao.php';

require __DIR__ . '/../helpers/response.php';
require __DIR__ . '/../helpers/request.php';
require __DIR__ . '/../helpers/logger.php';
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../core/pedido_core.php';
require __DIR__ . '/../core/caixa_core.php';

$empresaId = tenantResolverEmpresaId(authEmpresaId());

$transacaoAberta = false;

try {

    $data = bodyJson();

    $acao = trim((string)($data['acao'] ?? ''));

    if($acao === ''){
        erro('Ação inválida', 400);
    }

    $formaPagamento = strtolower(trim((string)($data['forma_pagamento'] ?? '')));

    $formasPagamentoPermitidas = [
        PAGAMENTO_DINHEIRO,
        PAGAMENTO_PIX,
        PAGAMENTO_CARTAO,
        PAGAMENTO_PRAZO,
        PAGAMENTO_ANTECIPADO
    ];

    /*
    |--------------------------------------------------------------------------
    | AÇÕES PERMITIDAS
    |--------------------------------------------------------------------------
    */
    $acoesPermitidas = [
        'produzir_item',
        STATUS_PAGO,
        STATUS_CANCELADO,
        STATUS_PRONTO,
        'reabrir',
        'alternar_servico_10'
    ];

    if(!in_array($acao, $acoesPermitidas, true)){
        erro('Ação inválida', 400);
    }

    /*
    |--------------------------------------------------------------------------
    | PERMISSÃO POR AÇÃO
    |--------------------------------------------------------------------------
    */
    if($acao === 'produzir_item'){
        requireRole(['cozinha', 'admin']);
    }

    if($acao === STATUS_PAGO){
        requireRole(['caixa', 'admin']);
    }

    if($acao === STATUS_CANCELADO){
        requireRole(['caixa', 'admin']);
    }

    if($acao === STATUS_PRONTO){
        requireRole(['cozinha', 'admin']);
    }

    if($acao === 'reabrir'){
        requireRole(['caixa', 'admin']);
    }

    if($acao === 'alternar_servico_10'){
        requireRole(['caixa', 'admin']);
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUZIR ITEM
    |--------------------------------------------------------------------------
    | Contrato:
    |
    | {
    |   "acao": "produzir_item",
    |   "item_id": 10
    | }
    |--------------------------------------------------------------------------
    */
    if($acao === 'produzir_item'){

        $itemId = (int)($data['item_id'] ?? 0);

        if($itemId <= 0){
            erro('Item inválido', 400);
        }

        /*
         * Valida se o item pertence a um pedido ainda operacional.
         * Não permite produzir item de pedido pago/cancelado.
         */
        $stmtItem = $conn->prepare("
            SELECT
                pi.id,
                pi.pedido_id,
                p.status
            FROM pedido_itens pi

            INNER JOIN pedidos p
                ON p.id = pi.pedido_id

            WHERE pi.id = ?
            AND pi.empresa_id = ?
            AND p.empresa_id = ?
            LIMIT 1
        ");

        if(!$stmtItem){
            throw new Exception($conn->error);
        }

        $stmtItem->bind_param(
            "iii",
            $itemId,
            $empresaId,
            $empresaId
        );

        if(!$stmtItem->execute()){
            throw new Exception($stmtItem->error);
        }

        $resItem = $stmtItem->get_result();

        if($resItem->num_rows === 0){

            $stmtItem->close();

            erro('Item não encontrado', 404);
        }

        $itemAtual = $resItem->fetch_assoc();

        $stmtItem->close();

        $pedidoId = (int)$itemAtual['pedido_id'];
        $statusPedido = $itemAtual['status'];

        if($statusPedido === STATUS_PAGO){
            erro('Pedido já está pago', 409);
        }

        if($statusPedido === STATUS_CANCELADO){
            erro('Pedido cancelado não pode ser alterado', 409);
        }

        $conn->begin_transaction();
        $transacaoAberta = true;

        $item = produzirItem(
            $conn,
            $itemId,
            $empresaId
        );

        $totais = recalcularPedido(
            $conn,
            $pedidoId,
            $empresaId
        );

        $conn->commit();
        $transacaoAberta = false;

        sucesso([
            'acao' => 'produzir_item',
            'pedido_id' => $pedidoId,
            'item' => $item,
            'status_pedido' => $item['status_pedido'] ?? null,
            'totais' => $totais
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | AÇÕES DE PEDIDO
    |--------------------------------------------------------------------------
    | Contrato:
    |
    | {
    |   "acao": "pago",
    |   "pedido_id": 10
    | }
    |--------------------------------------------------------------------------
    */
    $pedidoId = (int)($data['pedido_id'] ?? 0);

    if($pedidoId <= 0){
        erro('Pedido inválido', 400);
    }

    if(!pedidoExiste($conn, $pedidoId, $empresaId)){
        erro('Pedido não encontrado', 404);
    }

    $statusAtual = buscarStatusPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    if(!$statusAtual){
        erro('Pedido não encontrado', 404);
    }

    /*
    |--------------------------------------------------------------------------
    | REGRAS DE ESTADO
    |--------------------------------------------------------------------------
    */
    if($statusAtual === STATUS_PAGO && $acao !== 'reabrir'){
        erro('Pedido já está pago', 409);
    }

    if($statusAtual === STATUS_CANCELADO && $acao !== 'reabrir'){
        erro('Pedido cancelado não pode ser alterado', 409);
    }

    /*
     * Pagamento só deve ocorrer quando pedido estiver pronto.
     */
    if($acao === STATUS_PAGO && $statusAtual !== STATUS_PRONTO){
        erro('Pedido ainda não está pronto para pagamento', 409);
    }

    if($acao === STATUS_PAGO){

        if($formaPagamento === ''){
            erro('Informe a forma de pagamento', 400);
        }

        if(!in_array($formaPagamento, $formasPagamentoPermitidas, true)){
            erro('Forma de pagamento inválida', 400);
        }

        $colunasPagamento = [
            'forma_pagamento',
            'prazo_status',
            'prazo_quitado_em'
        ];

        foreach($colunasPagamento as $colunaPagamento){

            $checkColuna = $conn->query("SHOW COLUMNS FROM pedidos LIKE '" . $conn->real_escape_string($colunaPagamento) . "'");

            $temColuna = $checkColuna instanceof mysqli_result
                && $checkColuna->num_rows > 0;

            if($checkColuna instanceof mysqli_result){
                $checkColuna->free();
            }

            if(!$temColuna){
                erro('Estrutura de forma de pagamento e contas a prazo indisponível no banco atual', 500);
            }
        }

        if($formaPagamento === PAGAMENTO_ANTECIPADO && !pedidoTemColunaTotalAntecipado($conn)){
            erro('Estrutura de pagamento antecipado indisponível no banco atual', 500);
        }

        if(!caixaEstruturaDisponivel($conn)){
            erro('Estrutura de controle de caixa indisponível no banco atual', 500);
        }
    }

    /*
     * Reabrir pedido pago pode gerar divergência financeira.
     * Mantemos reabertura apenas para cancelados.
     */
    if($acao === 'reabrir' && $statusAtual === STATUS_PAGO){
        erro('Pedido pago não pode ser reaberto por este endpoint', 409);
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSACTION
    |--------------------------------------------------------------------------
    */
    $conn->begin_transaction();
    $transacaoAberta = true;

    switch($acao){

        case STATUS_PAGO:

            $caixaAberto = exigirCaixaAberto($conn, $empresaId);
            $resumoFinanceiroAntesPagamento = buscarResumoFinanceiroPedido($conn, $pedidoId, $empresaId);
            $saldoRecebidoAgora = round((float)($resumoFinanceiroAntesPagamento['saldo'] ?? 0), 2);

            marcarPago(
                $conn,
                $pedidoId,
                $formaPagamento,
                $empresaId
            );

            if($formaPagamento !== PAGAMENTO_ANTECIPADO && $saldoRecebidoAgora > 0){
                $tipoMovimentoCaixa = $formaPagamento === PAGAMENTO_PRAZO
                    ? CAIXA_MOVIMENTO_VENDA_PRAZO
                    : CAIXA_MOVIMENTO_PAGAMENTO_PEDIDO;

                registrarMovimentoCaixa(
                    $conn,
                    (int)$caixaAberto['id'],
                    $tipoMovimentoCaixa,
                    $formaPagamento,
                    $saldoRecebidoAgora,
                    $pedidoId,
                    null,
                    isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
                    $formaPagamento === PAGAMENTO_PRAZO
                        ? 'Conta encerrada a prazo'
                        : 'Pagamento final de conta',
                    $empresaId
                );
            }

            $statusFinal = STATUS_PAGO;

            break;

        case STATUS_CANCELADO:

            cancelarPedido(
                $conn,
                $pedidoId,
                $empresaId
            );

            $statusFinal = STATUS_CANCELADO;

            break;

        case STATUS_PRONTO:

            /*
             * Status manual pronto precisa manter itens e pedido coerentes.
             * Por isso atualiza todos os itens:
             *
             * quantidade_pronta = quantidade
             */
            marcarTodosItensProntos(
                $conn,
                $pedidoId,
                $empresaId
            );

            $statusFinal = STATUS_PRONTO;

            break;

        case 'reabrir':

            reabrirPedido(
                $conn,
                $pedidoId,
                $empresaId
            );

            $statusFinal = STATUS_PENDENTE;

            break;

        case 'alternar_servico_10':

            $ativoServico10 = filter_var(
                $data['ativo'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if($ativoServico10 === null){
                erro('Estado dos 10% inválido', 400);
            }

            $resultadoServico10 = atualizarServico10Pedido(
                $conn,
                $pedidoId,
                (bool)$ativoServico10,
                $empresaId
            );

            $statusFinal = $statusAtual;

            break;

        default:

            throw new Exception('Ação inválida');
    }

    /*
    |--------------------------------------------------------------------------
    | RECALCULA TOTAIS
    |--------------------------------------------------------------------------
    */
    $totais = $acao === 'alternar_servico_10'
        ? ($resultadoServico10['totais'] ?? recalcularPedido($conn, $pedidoId, $empresaId))
        : recalcularPedido(
            $conn,
            $pedidoId,
            $empresaId
        );

    $financeiro = $acao === 'alternar_servico_10'
        ? ($resultadoServico10['financeiro'] ?? buscarResumoFinanceiroPedido($conn, $pedidoId, $empresaId))
        : buscarResumoFinanceiroPedido($conn, $pedidoId, $empresaId);

    $conn->commit();
    $transacaoAberta = false;

    sucesso([
        'pedido_id' => $pedidoId,
        'acao' => $acao,
        'status_anterior' => $statusAtual,
        'status' => $statusFinal,
        'forma_pagamento' => $acao === STATUS_PAGO ? $formaPagamento : null,
        'totais' => $totais,
        'financeiro' => $financeiro
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
