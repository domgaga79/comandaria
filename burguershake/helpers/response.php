<?php

function sucesso(
    array $dados = [],
    int $http = 200
): void {

    http_response_code($http);

    echo json_encode(
        array_merge([
            'ok' => true
        ], $dados),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

function erro(
    string $mensagem,
    int $http = 400,
    array $extra = []
): void {

    http_response_code($http);

    echo json_encode(
        array_merge([
            'ok' => false,
            'erro' => $mensagem
        ], $extra),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}