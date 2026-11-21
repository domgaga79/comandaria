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
require __DIR__ . '/../helpers/logger.php';
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../core/caixa_core.php';

$empresaId = tenantResolverEmpresaId(authEmpresaId());

try {
    exigirEstruturaCaixa($conn);

    $caixa = buscarCaixaAberto($conn, $empresaId);

    if(!$caixa){
        sucesso([
            'aberto' => false,
            'caixa' => null,
            'resumo' => resumoCaixaVazio()
        ]);
    }

    sucesso([
        'aberto' => true,
        'caixa' => $caixa,
        'resumo' => obterResumoCaixa($conn, (int)$caixa['id'], $empresaId)
    ]);

} catch(Throwable $e){
    logErro($e);
    erro($e->getMessage(), 500);
}

$conn->close();
