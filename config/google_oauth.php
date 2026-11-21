<?php

/*
|--------------------------------------------------------------------------
| Configuração Google OAuth / OpenID Connect
|--------------------------------------------------------------------------
| Recomenda-se configurar via variáveis de ambiente:
|
| GOOGLE_CLIENT_ID
| GOOGLE_CLIENT_SECRET
| GOOGLE_REDIRECT_URI   (opcional)
|
| Se GOOGLE_REDIRECT_URI não for informado, o sistema monta automaticamente
| a URL do callback a partir do domínio atual e da pasta /public.
|--------------------------------------------------------------------------
*/

if(!function_exists('googleOauthIsHttps')){
    function googleOauthIsHttps(): bool {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
    }
}

if(!function_exists('googleOauthRedirectUriPadrao')){
    function googleOauthRedirectUriPadrao(): string {
        $scheme = googleOauthIsHttps() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/google_callback.php';
        $dir = rtrim(dirname($scriptName), '/\\');
        $dir = ($dir === '.' || $dir === '/') ? '' : $dir;

        return $scheme . '://' . $host . $dir . '/google_callback.php';
    }
}

if(!function_exists('googleOauthConfig')){
    function googleOauthConfig(): array {
        
        
        $clientId = trim((string)getenv('GOOGLE_CLIENT_ID'));
        $clientSecret = trim((string)getenv('GOOGLE_CLIENT_SECRET'));
        $redirectUri = trim((string)getenv('GOOGLE_REDIRECT_URI'));
        

        if($redirectUri === ''){
            $redirectUri = googleOauthRedirectUriPadrao();
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri
        ];
    }
}
