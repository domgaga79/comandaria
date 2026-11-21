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
require __DIR__ . '/../core/caixa_core.php';

$transacaoAberta = false;

try {
    exigirEstruturaCaixa($conn);

    $data = bodyJson();
    $saldoFinalInformado = round((float)($data['saldo_final_informado'] ?? 0), 2);
    $observacao = trim((string)($data['observacao'] ?? ''));

    if($saldoFinalInformado < 0){
        erro('O dinheiro contado não pode ser negativo', 400);
    }

    $usuarioId = isset($_SESSION['user_id'])
        ? (int)$_SESSION['user_id']
        : null;

    $conn->begin_transaction();
    $transacaoAberta = true;

    $resultado = fecharCaixaSessao(
        $conn,
        $saldoFinalInformado,
        $usuarioId,
        $observacao
    );

    $conn->commit();
    $transacaoAberta = false;

    $caixaFechado = $resultado['caixa'] ?? [];
    $caixaIdFechado = (int)($caixaFechado['id'] ?? 0);

    sucesso([
        'aberto' => false,
        'caixa_id' => $caixaIdFechado,
        'fechamento_id' => $caixaIdFechado,
        'caixa' => $caixaFechado,
        'resumo' => $resultado['resumo'],
        'saldo_final_informado' => $resultado['saldo_final_informado'],
        'saldo_esperado_dinheiro' => $resultado['saldo_esperado_dinheiro'],
        'diferenca_dinheiro' => $resultado['diferenca_dinheiro']
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
    erro($e->getMessage(), 500);
}

$conn->close();
