<?php

/*
|--------------------------------------------------------------------------
| Sessão segura
|--------------------------------------------------------------------------
*/

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (
        isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
    )
);

if(session_status() === PHP_SESSION_NONE){

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

/*
|--------------------------------------------------------------------------
| Limpa variáveis de sessão
|--------------------------------------------------------------------------
*/

$_SESSION = [];

/*
|--------------------------------------------------------------------------
| Remove cookie da sessão
|--------------------------------------------------------------------------
*/

if(ini_get('session.use_cookies')){

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'] ?? '',
        $params['secure'],
        $params['httponly']
    );
}

/*
|--------------------------------------------------------------------------
| Destrói sessão
|--------------------------------------------------------------------------
*/

session_destroy();

/*
|--------------------------------------------------------------------------
| Redireciona para login
|--------------------------------------------------------------------------
*/

header('Location: login.php');
exit;