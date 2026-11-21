<?php

/*
|--------------------------------------------------------------------------
| Logger simples
|--------------------------------------------------------------------------
| Grava no error_log padrão do PHP.
| Depois podemos evoluir para /storage/logs/app.log.
|--------------------------------------------------------------------------
*/

function logErro(Throwable $e): void {

    error_log(sprintf(
        "[%s] %s | Arquivo: %s | Linha: %d",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
}

function logInfo(string $mensagem, array $contexto = []): void {

    $extra = '';

    if(!empty($contexto)){
        $extra = ' | ' . json_encode(
            $contexto,
            JSON_UNESCAPED_UNICODE
        );
    }

    error_log(sprintf(
        "[%s] INFO: %s%s",
        date('Y-m-d H:i:s'),
        $mensagem,
        $extra
    ));
}