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

    ini_set('session.use_strict_mode', 1);

    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/conexao.php';

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function voltarLogin(string $mensagem): void {

    header(
        'Location: login.php?erro=' .
        urlencode($mensagem)
    );

    exit;
}

function redirecionarPorTipo(string $tipo): void {

    switch($tipo){

        case 'admin':
            header('Location: dashboard.php');
            exit;

        case 'atendente':
        case 'garcom':
        case 'garçom':
            header('Location: index.php');
            exit;

        case 'cozinha':
            header('Location: painel_cozinha.php');
            exit;

        case 'caixa':
            header('Location: painel_caixa.php');
            exit;

        default:
            voltarLogin('Tipo de usuário inválido');
    }
}

try {

    /*
    |--------------------------------------------------------------------------
    | Entrada
    |--------------------------------------------------------------------------
    | Aceita login por nome ou e-mail.
    |--------------------------------------------------------------------------
    */

    $login = trim(
        $_POST['nome'] ??
        $_POST['email'] ??
        ''
    );

    $senha = (string)(
        $_POST['senha'] ??
        ''
    );

    if($login === '' || $senha === ''){
        voltarLogin('Preencha todos os campos');
    }

    /*
    |--------------------------------------------------------------------------
    | Busca usuário
    |--------------------------------------------------------------------------
    | Estrutura esperada:
    |
    | usuarios.id
    | usuarios.nome
    | usuarios.email
    | usuarios.senha
    | usuarios.tipo
    | usuarios.status
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.nome,
            u.email,
            u.senha,
            u.tipo,
            u.status,
            u.empresa_id,
            e.nome AS empresa_nome,
            e.slug AS empresa_slug,
            e.plano_id,
            e.status AS empresa_status,
            e.status_assinatura,
            p.nome AS plano_nome
        FROM usuarios u
        INNER JOIN empresas e
            ON e.id = u.empresa_id
        LEFT JOIN planos p
            ON p.id = e.plano_id
        WHERE u.nome = ?
        OR u.email = ?
        LIMIT 1
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "ss",
        $login,
        $login
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();

    $user = $res->fetch_assoc();

    $stmt->close();

    if(!$user){
        voltarLogin('Usuário não encontrado');
    }

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    if(($user['status'] ?? '') !== 'ativo'){
        voltarLogin('Usuário bloqueado');
    }

    if(($user['empresa_status'] ?? '') !== 'ativa'){
        voltarLogin('Empresa inativa');
    }

    if(($user['status_assinatura'] ?? 'ativa') !== 'ativa'){
        voltarLogin('Assinatura suspensa ou cancelada');
    }

    /*
    |--------------------------------------------------------------------------
    | Senha
    |--------------------------------------------------------------------------
    */

    if(!password_verify($senha, $user['senha'])){
        voltarLogin('Senha inválida');
    }

    /*
    |--------------------------------------------------------------------------
    | Sessão
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_nome'] = $user['nome'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_tipo'] = $user['tipo'];

    /*
     * Mantido também como user_role para qualquer arquivo auxiliar
     * que eventualmente consulte esse nome.
     */
    $_SESSION['user_role'] = $user['tipo'];

    $_SESSION['empresa_id'] = (int)$user['empresa_id'];
    $_SESSION['empresa_nome'] = $user['empresa_nome'];
    $_SESSION['empresa_slug'] = $user['empresa_slug'];
    $_SESSION['plano_id'] = (int)($user['plano_id'] ?? 0);
    $_SESSION['plano_nome'] = $user['plano_nome'] ?? null;

    /*
    |--------------------------------------------------------------------------
    | Redirecionamento
    |--------------------------------------------------------------------------
    */

    redirecionarPorTipo($user['tipo']);

} catch(Throwable $e){

    error_log(
        '[ERRO processa_login.php] ' .
        $e->getMessage()
    );

    voltarLogin('Erro interno ao processar login');
}

$conn->close();