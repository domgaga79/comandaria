<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

$_SERVER['HTTP_ACCEPT'] = 'application/json';

/*
|--------------------------------------------------------------------------
| Este endpoint retorna array puro.
|--------------------------------------------------------------------------
| O /index.php espera:
|
| [
|   {
|     "id": 1,
|     "nome": "🍔 X-Burger",
|     "cat": "Lanches",
|     "preco": 25
|   }
| ]
|
| Portanto não retorna:
|
| {
|   "ok": true,
|   "produtos": []
| }
|--------------------------------------------------------------------------
*/

require __DIR__ . '/conexao.php';

require __DIR__ . '/../helpers/logger.php';
require __DIR__ . '/../core/produto_core.php';

try {

    $produtos = listarProdutosAtivos($conn);

    echo json_encode(
        $produtos,
        JSON_UNESCAPED_UNICODE
    );

} catch(Throwable $e){

    logErro($e);

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'erro' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();