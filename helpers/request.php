<?php

/*
|--------------------------------------------------------------------------
| Leitura segura de JSON
|--------------------------------------------------------------------------
| Depende de helpers/response.php.
|--------------------------------------------------------------------------
*/

if(!function_exists('bodyJson')){

    function bodyJson(): array {

        $raw = file_get_contents('php://input');

        if($raw === false || trim($raw) === ''){
            erro('Body vazio', 400);
        }

        $data = json_decode($raw, true);

        if(json_last_error() !== JSON_ERROR_NONE){
            erro(
                'JSON inválido: ' . json_last_error_msg(),
                400
            );
        }

        if(!is_array($data)){
            erro('JSON inválido', 400);
        }

        return $data;
    }
}

/*
|--------------------------------------------------------------------------
| Helpers opcionais para GET/POST
|--------------------------------------------------------------------------
*/

if(!function_exists('inputInt')){

    function inputInt(
        array $source,
        string $key,
        int $default = 0
    ): int {

        return isset($source[$key])
            ? (int)$source[$key]
            : $default;
    }
}

if(!function_exists('inputString')){

    function inputString(
        array $source,
        string $key,
        string $default = ''
    ): string {

        return isset($source[$key])
            ? trim((string)$source[$key])
            : $default;
    }
}