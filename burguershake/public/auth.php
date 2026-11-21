<?php

/*
|--------------------------------------------------------------------------
| Segurança de sessão
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

    ini_set('session.use_strict_mode', 1);

    session_start();
}

/*
|--------------------------------------------------------------------------
| Detecta se a requisição espera JSON
|--------------------------------------------------------------------------
*/

if(!function_exists('isJsonRequest')){

    function isJsonRequest(): bool {

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return (
            stripos($accept, 'application/json') !== false ||
            stripos($contentType, 'application/json') !== false ||
            strtolower($requestedWith) === 'xmlhttprequest'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Usuário autenticado
|--------------------------------------------------------------------------
*/

if(!function_exists('authUser')){

    function authUser(): array {

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'nome' => $_SESSION['user_nome'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'tipo' => $_SESSION['user_tipo'] ?? ($_SESSION['user_role'] ?? null)
        ];
    }
}

if(!function_exists('authId')){

    function authId(): ?int {

        return isset($_SESSION['user_id'])
            ? (int)$_SESSION['user_id']
            : null;
    }
}

if(!function_exists('authNome')){

    function authNome(): ?string {

        return $_SESSION['user_nome'] ?? null;
    }
}

if(!function_exists('authTipo')){

    function authTipo(): ?string {

        return $_SESSION['user_tipo'] ?? ($_SESSION['user_role'] ?? null);
    }
}

if(!function_exists('isLoggedIn')){

    function isLoggedIn(): bool {

        return !empty($_SESSION['user_id']);
    }
}

/*
|--------------------------------------------------------------------------
| Respostas de bloqueio
|--------------------------------------------------------------------------
*/

if(!function_exists('authJsonError')){

    function authJsonError(
        string $mensagem,
        int $http = 403
    ): void {

        http_response_code($http);

        if(!headers_sent()){
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'ok' => false,
            'erro' => $mensagem
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}

if(!function_exists('authDestinoPorTipo')){

    function authDestinoPorTipo(?string $tipo = null): array {

        $tipo = $tipo ?: authTipo();

        switch($tipo){

            case 'admin':
                return [
                    'url' => 'dashboard.php',
                    'texto' => 'Voltar para o Dashboard'
                ];

            case 'atendente':
                return [
                    'url' => 'index.php',
                    'texto' => 'Voltar para o Atendimento'
                ];

            case 'cozinha':
                return [
                    'url' => 'painel_cozinha.php',
                    'texto' => 'Voltar para a Cozinha'
                ];

            case 'caixa':
                return [
                    'url' => 'painel_caixa.php',
                    'texto' => 'Voltar para o Caixa'
                ];

            default:
                return [
                    'url' => 'login.php',
                    'texto' => 'Voltar para o Login'
                ];
        }
    }
}

if(!function_exists('authHtmlDenied')){

    function authHtmlDenied(
        string $mensagem = 'Você não tem acesso a esta área.'
    ): void {

        http_response_code(403);

        $destino = authDestinoPorTipo();
        $voltarUrl = htmlspecialchars($destino['url'], ENT_QUOTES, 'UTF-8');
        $voltarTexto = htmlspecialchars($destino['texto'], ENT_QUOTES, 'UTF-8');

        echo '<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Acesso negado</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-dark text-white d-flex align-items-center justify-content-center vh-100">

    <div class="card text-center shadow" style="max-width:380px;width:92%;">
        <div class="card-body">
            <h3 class="text-danger mb-3">🚫 Acesso negado</h3>

            <p class="text-muted">' . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . '</p>

            <a href="' . $voltarUrl . '" class="btn btn-primary w-100 mb-2">
                ' . $voltarTexto . '
            </a>

            <a href="logout.php" class="btn btn-outline-secondary w-100">
                Sair
            </a>
        </div>
    </div>

</body>
</html>';

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Login obrigatório
|--------------------------------------------------------------------------
*/

if(!function_exists('requireLogin')){

    function requireLogin(): void {

        if(isLoggedIn()){
            return;
        }

        if(isJsonRequest()){
            authJsonError(
                'Sessão expirada ou usuário não autenticado',
                401
            );
        }

        header('Location: login.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Autorização por papel
|--------------------------------------------------------------------------
| Roles atuais:
|
| admin
| atendente
| cozinha
| caixa
|--------------------------------------------------------------------------
*/

if(!function_exists('requireRole')){

    function requireRole(array $roles): void {

        requireLogin();

        $tipo = authTipo();

        if(!$tipo){

            if(isJsonRequest()){
                authJsonError(
                    'Tipo de usuário não identificado',
                    403
                );
            }

            authHtmlDenied(
                'Tipo de usuário não identificado.'
            );
        }

        /*
         * Admin tem acesso global.
         */
        if($tipo === 'admin'){
            return;
        }

        if(in_array($tipo, $roles, true)){
            return;
        }

        if(isJsonRequest()){
            authJsonError(
                'Sem permissão para acessar este recurso',
                403
            );
        }

        authHtmlDenied(
            'Você não tem permissão para acessar esta área.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Redirecionamento por papel
|--------------------------------------------------------------------------
*/

if(!function_exists('redirectByRole')){

    function redirectByRole(?string $tipo = null): void {

        $destino = authDestinoPorTipo($tipo);

        header('Location: ' . $destino['url']);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Bloqueio automático
|--------------------------------------------------------------------------
| Todo arquivo que inclui auth.php exige login.
|--------------------------------------------------------------------------
*/

requireLogin();