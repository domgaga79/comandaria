<?php

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
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

require __DIR__ . '/../config/google_oauth.php';

$config = googleOauthConfig();

if($config['client_id'] === ''){
    header('Location: login.php?erro=' . urlencode('Google Login não configurado: informe GOOGLE_CLIENT_ID.'));
    exit;
}

$state = bin2hex(random_bytes(24));
$nonce = bin2hex(random_bytes(24));

$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_nonce'] = $nonce;
$_SESSION['google_oauth_started_at'] = time();

$params = [
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'nonce' => $nonce,
    'prompt' => 'select_account',
    'access_type' => 'online',
    'include_granted_scopes' => 'true'
];

$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

header('Location: ' . $url);
exit;
