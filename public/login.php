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

/*
|--------------------------------------------------------------------------
| Se já estiver logado, redireciona
|--------------------------------------------------------------------------
*/

if(!empty($_SESSION['user_id'])){

    $tipo = $_SESSION['user_tipo'] ?? $_SESSION['user_role'] ?? '';

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
    }
}

$erro = trim($_GET['erro'] ?? '');

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/inc/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Login - Comandaria Express</title>

<?= ce_og_tags('Login - Comandaria Express', 'Acesse o sistema de comandas do Comandaria Express') ?>
<link rel="icon" href="images/comandaria_logo.png" type="image/png">

<meta name="theme-color" content="#f7f7f2">
<link rel="stylesheet" href="assets/guest-access.css?v=3">
</head>
<body>
    <main class="login-layout">
        <section class="login-card" aria-labelledby="login-title">
            <div class="login-brand">
                <img src="images/comandaria_logo.png" width="52" height="52" alt="">
                <span>Comandaria Express</span>
            </div>

            <div class="login-body">
                <h1 id="login-title">Acesse sua conta</h1>
                <p>Entre com Google ou com o acesso cadastrado pela sua empresa.</p>

                <?php if($erro !== ''): ?>
                    <div class="login-alert" role="alert"><?= h($erro) ?></div>
                <?php endif; ?>

                <a href="google_login.php" class="btn-google">
                    <img src="images/google-signin.png" width="20" height="20" alt="">
                    Entrar com Google
                </a>

                <details class="login-password"<?= $erro !== '' ? ' open' : '' ?>>
                    <summary>Entrar com e-mail e senha</summary>
                    <form action="processa_login.php" method="post">
                        <label for="login-email">E-mail</label>
                        <input type="email" name="email" id="login-email" autocomplete="username" required>
                        <label for="login-senha">Senha</label>
                        <input type="password" name="senha" id="login-senha" autocomplete="current-password" required>
                        <button type="submit">Entrar</button>
                    </form>
                </details>
            </div>

            <footer class="login-footer">Bacuri Digital</footer>
        </section>
        <div class="login-visual" aria-hidden="true"></div>
    </main>
</body>
</html>
