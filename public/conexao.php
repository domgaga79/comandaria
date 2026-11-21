<?php

/*
|--------------------------------------------------------------------------
| Conexão MySQL - Comandaria Express
|--------------------------------------------------------------------------
| Este arquivo expõe a variável global:
|
| $conn
|
| Todos os arquivos novos do sistema usam mysqli através dela.
|--------------------------------------------------------------------------
*/

date_default_timezone_set('America/Bahia');

/*
|--------------------------------------------------------------------------
| Configuração do banco
|--------------------------------------------------------------------------
| Mantenha aqui os dados reais do seu servidor.
|--------------------------------------------------------------------------
*/

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = (string)getenv('DB_USER');
$db_pass = (string)getenv('DB_PASSWORD');
$db_name = (string)getenv('DB_NAME');

/*
|--------------------------------------------------------------------------
| Modo de erro do MySQLi
|--------------------------------------------------------------------------
| Faz erros SQL virarem exceções capturáveis por try/catch.
|--------------------------------------------------------------------------
*/

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    $conn = new mysqli(
        $db_host,
        $db_user,
        $db_pass,
        $db_name
    );

    /*
    |--------------------------------------------------------------------------
    | Charset
    |--------------------------------------------------------------------------
    | Necessário para emojis, acentos e nomes de produtos.
    |--------------------------------------------------------------------------
    */

    $conn->set_charset('utf8mb4');

    /*
    |--------------------------------------------------------------------------
    | Timezone do MySQL
    |--------------------------------------------------------------------------
    | Bahia = UTC-03:00.
    |--------------------------------------------------------------------------
    */

    $conn->query("SET time_zone = '-03:00'");

} catch(Throwable $e){

    error_log(
        '[ERRO CONEXAO] ' .
        $e->getMessage()
    );

    http_response_code(500);

    /*
     * Se for requisição JSON, responde JSON.
     * Se for página HTML, mostra mensagem simples.
     */
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    $isJson = (
        stripos($accept, 'application/json') !== false ||
        stripos($contentType, 'application/json') !== false
    );

    if($isJson){

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'ok' => false,
            'erro' => 'Erro ao conectar ao banco de dados'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    echo '<h3>Erro ao conectar ao banco de dados.</h3>';
    exit;
}