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
require __DIR__ . '/../core/pedido_core.php';

$transacaoAberta = false;

try {

    $data = bodyJson();

    if(!is_array($data)){
        erro('JSON inválido', 400);
    }

    $itemId = (int)($data['item_id'] ?? 0);

    if($itemId <= 0){
        erro('Item inválido', 400);
    }

    $conn->begin_transaction();
    $transacaoAberta = true;

    $resultado = excluirItemConta(
        $conn,
        $itemId
    );

    $conn->commit();
    $transacaoAberta = false;

    sucesso([
        'acao' => 'excluir_item_conta',
        'pedido_id' => $resultado['pedido_id'],
        'status_pedido' => $resultado['status_pedido'],
        'item_excluido' => $resultado['item_excluido'],
        'totais' => $resultado['totais'],
        'financeiro' => $resultado['financeiro']
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
