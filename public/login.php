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

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
html,
body {
    margin: 0;
    width: 100%;
}

body {
    min-height: 100vh;
    min-height: 100svh;
    background: radial-gradient(circle at top, #2b2b2b, #070707);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-family: Arial, sans-serif;
    padding: 16px;
    box-sizing: border-box;
}

.login-card {
    width: 100%;
    max-width: 390px;
    background: #fff;
    color: #111;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 10px 35px rgba(0,0,0,.45);
}

.login-header {
    background: #111;
    color: #fff;
    text-align: center;
    padding: 24px 18px 18px;
}

.login-header img {
    max-width: 260px;
    width: 100%;
    margin-bottom: 10px;
}

.login-header h1 {
    font-size: 20px;
    font-weight: 800;
    margin: 0;
}

.login-body {
    padding: 22px;
}

.form-label {
    font-weight: 700;
    font-size: 14px;
}

.form-control {
    border-radius: 10px;
    padding: 11px 12px;
}

.btn-login {
    background: #ffc107;
    border: none;
    color: #111;
    font-weight: 800;
    border-radius: 10px;
    padding: 12px;
    width: 100%;
}

.btn-login:hover {
    background: #ffca2c;
    color: #111;
}

.btn-google {
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    border:1px solid #d7d7d7;
    background:#fff;
    color:#202124;
    text-decoration:none;
    font-weight:800;
    border-radius:10px;
    padding:12px;
    width:100%;
    margin-top:12px;
}

.btn-google:hover {
    background:#f8f9fa;
    color:#202124;
}

.btn-google .gmark {
    display:inline-flex;
    width:20px;
    height:20px;
    border-radius:50%;
    align-items:center;
    justify-content:center;
    font-weight:900;
    border:1px solid #ddd;
    font-size:13px;
}

.login-separator {
    display:flex;
    align-items:center;
    gap:10px;
    color:#777;
    font-size:12px;
    margin:16px 0 12px;
    text-transform:uppercase;
    letter-spacing:.08em;
}

.login-separator::before,
.login-separator::after {
    content:'';
    flex:1;
    height:1px;
    background:#e5e5e5;
}

.alert {
    border-radius: 10px;
    font-size: 14px;
}

.footer {
    text-align: center;
    font-size: 12px;
    color: #777;
    padding: 0 22px 18px;
}


@media (max-width: 576px) {
    body {
        min-height: 100vh;
        min-height: 100svh;
        padding: 12px 14px;
        align-items: center;
    }

    .login-card {
        max-width: 100%;
    }

    .login-header {
        padding: 18px 16px 14px;
    }

    .login-header img {
        max-width: 210px;
        margin-bottom: 6px;
    }

    .login-header h1 {
        font-size: 18px;
    }

    .login-body {
        padding: 18px;
    }

    .form-control {
        padding: 10px 11px;
    }

    .footer {
        padding: 0 18px 14px;
    }
}
img {
  animation: pulsar 5s infinite;
}

@keyframes pulsar {
  0% {
    transform: scale(1);
  }
  80% {
    transform: scale(1);
  }
  90% {
    transform: scale(0.95);
  }
  100% {
    transform: scale(1);
  }
}
img {
  animation: pulsar 5s infinite;
}

@keyframes pulsar {
  0% {
    transform: scale(1);
  }
  80% {
    transform: scale(1);
  }
  90% {
    transform: scale(0.95);
  }
  100% {
    transform: scale(1);
  }
}
</style>
</head>

<body>

<div class="login-card">

    <div class="login-header">
        <img src="images/topo_comandaria_shake.png" alt="Comandaria Express">

        <h1>Acesso ao Sistema</h1>
    </div>

    <div class="login-body">

        <?php if($erro !== ''): ?>
            <div class="alert alert-danger">
                <?= h($erro) ?>
            </div>
        <?php endif; ?>
        
        <!--
        <form method="POST" action="processa_login.php" autocomplete="off">
            <div class="mb-3">
                <label for="nome" class="form-label">
                    Usuário ou e-mail
                </label>

                <input
                    type="text"
                    name="nome"
                    id="nome"
                    class="form-control"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Digite seu usuário"
                >
            </div>

            <div class="mb-3">
                <label for="senha" class="form-label">
                    Senha
                </label>

                <input
                    type="password"
                    name="senha"
                    id="senha"
                    class="form-control"
                    required
                    autocomplete="current-password"
                    placeholder="Digite sua senha"
                >
            </div>
            

            <button type="submit" class="btn-login">
                Entrar
            </button>
            
        </form>

        <div class="login-separator">ou</div>
        -->
        
        <a href="google_login.php" class="btn-google">
            <img src="https://www.svgrepo.com/show/355037/google.svg" width="20" height="20" alt="Google">
            Entrar com Google
        </a>

    </div>

    <div class="footer">
        Comandaria Express
    </div>

</div>

</body>
</html>