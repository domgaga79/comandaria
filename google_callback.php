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

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/public/conexao.php';
require __DIR__ . '/config/google_oauth.php';
require __DIR__ . '/core/tenant_core.php';
require __DIR__ . '/core/catalogo_inicial_core.php';

function googleVoltarLogin(string $mensagem): void {
    header('Location: login.php?erro=' . urlencode($mensagem));
    exit;
}

function googleRedirecionarPorTipo(string $tipo): void {
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
            googleVoltarLogin('Tipo de usuário inválido.');
    }
}

function googlePostJson(string $url, array $params): array {
    if(!function_exists('curl_init')){
        throw new Exception('A extensão cURL do PHP é necessária para o Google Login.');
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 20
    ]);

    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if($body === false || $err !== ''){
        throw new Exception('Falha na comunicação com o Google: ' . $err);
    }

    $json = json_decode((string)$body, true);

    if(!is_array($json)){
        throw new Exception('Resposta inválida do Google OAuth.');
    }

    if($http < 200 || $http >= 300){
        $detalhe = $json['error_description'] ?? $json['error'] ?? 'erro não identificado';
        throw new Exception('Google OAuth recusou a autenticação: ' . $detalhe);
    }

    return $json;
}

function googlePayloadVerificado(string $idToken, string $clientId): array {
    $autoload = __DIR__ . '/vendor/autoload.php';

    if(!is_file($autoload)){
        throw new Exception('Google Login requer vendor/autoload.php. Execute composer require google/apiclient no projeto antes de usar o acesso Google.');
    }

    require_once $autoload;

    if(class_exists('Google\\Client')){
        $client = new Google\Client(['client_id' => $clientId]);
    } elseif(class_exists('Google_Client')) {
        $client = new Google_Client(['client_id' => $clientId]);
    } else {
        throw new Exception('Biblioteca google/apiclient não encontrada após carregar vendor/autoload.php.');
    }

    $payload = $client->verifyIdToken($idToken);

    if(!is_array($payload) || empty($payload['sub'])){
        throw new Exception('Token de identidade Google inválido.');
    }

    return $payload;
}

function googleSlugBase(string $texto): string {
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $mapa = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $slug = strtolower((string)$mapa);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string)$slug, '-');

    return $slug !== '' ? $slug : 'empresa';
}

function googleGerarSlugUnico(mysqli $conn, string $base): string {
    $base = googleSlugBase($base);
    $slug = $base;
    $sufixo = 1;

    while(true){
        $stmt = $conn->prepare('SELECT id FROM empresas WHERE slug = ? LIMIT 1');
        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('s', $slug);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if(!$existe){
            return $slug;
        }

        $sufixo++;
        $slug = $base . '-' . $sufixo;
    }
}

function googlePlanoFreeId(mysqli $conn): int {
    $res = $conn->query("SELECT id FROM planos WHERE nome = 'FREE' LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;

    if(!$row){
        throw new Exception('Plano FREE não encontrado. Execute a migração multitenant.');
    }

    return (int)$row['id'];
}

try {
    $config = googleOauthConfig();

    if($config['client_id'] === '' || $config['client_secret'] === ''){
        googleVoltarLogin('Google Login não configurado: informe GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET.');
    }

    if(!empty($_GET['error'])){
        googleVoltarLogin('Login Google cancelado ou recusado.');
    }

    $stateRecebido = trim((string)($_GET['state'] ?? ''));
    $stateSessao = (string)($_SESSION['google_oauth_state'] ?? '');
    $inicio = (int)($_SESSION['google_oauth_started_at'] ?? 0);

    if($stateRecebido === '' || $stateSessao === '' || !hash_equals($stateSessao, $stateRecebido)){
        googleVoltarLogin('Estado OAuth inválido. Tente novamente.');
    }

    if($inicio <= 0 || (time() - $inicio) > 900){
        googleVoltarLogin('Tentativa de login Google expirada. Tente novamente.');
    }

    $code = trim((string)($_GET['code'] ?? ''));

    if($code === ''){
        googleVoltarLogin('Código de autorização Google não recebido.');
    }

    $tokens = googlePostJson('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code'
    ]);

    $idToken = trim((string)($tokens['id_token'] ?? ''));

    if($idToken === ''){
        throw new Exception('O Google não retornou id_token.');
    }

    $payload = googlePayloadVerificado($idToken, $config['client_id']);

    $nonceSessao = (string)($_SESSION['google_oauth_nonce'] ?? '');
    $noncePayload = (string)($payload['nonce'] ?? '');

    if($nonceSessao === '' || $noncePayload === '' || !hash_equals($nonceSessao, $noncePayload)){
        throw new Exception('Nonce do login Google inválido.');
    }

    $googleSub = trim((string)($payload['sub'] ?? ''));
    $email = mb_strtolower(trim((string)($payload['email'] ?? '')), 'UTF-8');
    $emailVerificado = !empty($payload['email_verified']);
    $nome = trim((string)($payload['name'] ?? 'Usuário Google'));
    $foto = trim((string)($payload['picture'] ?? ''));

    if($googleSub === '' || $email === ''){
        throw new Exception('Perfil Google incompleto para autenticação.');
    }

    if(!$emailVerificado){
        throw new Exception('O e-mail da conta Google não está verificado.');
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare("\n        SELECT\n            u.id,\n            u.nome,\n            u.email,\n            u.tipo,\n            u.status,\n            u.google_sub,\n            u.empresa_id,\n            e.nome AS empresa_nome,\n            e.slug AS empresa_slug,\n            e.plano_id,\n            p.nome AS plano_nome\n        FROM usuarios u\n        INNER JOIN empresas e\n            ON e.id = u.empresa_id\n        LEFT JOIN planos p\n            ON p.id = e.plano_id\n        WHERE u.google_sub = ?\n        OR u.email = ?\n        ORDER BY CASE WHEN u.google_sub = ? THEN 0 ELSE 1 END\n        LIMIT 1\n        FOR UPDATE\n    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param('sss', $googleSub, $email, $googleSub);

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if($user){
        $subAtual = trim((string)($user['google_sub'] ?? ''));

        if($subAtual !== '' && !hash_equals($subAtual, $googleSub)){
            throw new Exception('Este e-mail já está vinculado a outra conta Google.');
        }

        if(($user['status'] ?? '') !== 'ativo'){
            throw new Exception('Usuário bloqueado.');
        }

        $stmt = $conn->prepare("\n            UPDATE usuarios\n            SET\n                google_sub = ?,\n                google_foto = ?,\n                login_google_ativo = 1\n            WHERE id = ?\n            LIMIT 1\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $usuarioId = (int)$user['id'];
        $stmt->bind_param('ssi', $googleSub, $foto, $usuarioId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $stmt->close();
    } else {
        $planoFreeId = googlePlanoFreeId($conn);
        $nomeEmpresa = 'Comandaria de ' . ($nome !== '' ? $nome : $email);
        $slug = googleGerarSlugUnico($conn, $nomeEmpresa);

        $stmt = $conn->prepare("\n            INSERT INTO empresas (\n                nome,\n                slug,\n                plano_id,\n                status,\n                status_assinatura,\n                ultimo_numero_pedido,\n                created_at,\n                updated_at\n            ) VALUES (?, ?, ?, 'ativa', 'ativa', 0, NOW(), NOW())\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('ssi', $nomeEmpresa, $slug, $planoFreeId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $empresaId = (int)$conn->insert_id;
        $stmt->close();

        $nomeUsuario = $nome !== '' ? $nome : explode('@', $email)[0];
        $tipo = 'admin';
        $status = 'ativo';

        $stmt = $conn->prepare("\n            INSERT INTO usuarios (\n                empresa_id,\n                nome,\n                email,\n                senha,\n                tipo,\n                status,\n                google_sub,\n                google_foto,\n                login_google_ativo\n            ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1)\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('issssss', $empresaId, $nomeUsuario, $email, $tipo, $status, $googleSub, $foto);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $usuarioId = (int)$conn->insert_id;
        $stmt->close();

        $user = [
            'id' => $usuarioId,
            'nome' => $nomeUsuario,
            'email' => $email,
            'tipo' => $tipo,
            'status' => $status,
            'google_sub' => $googleSub,
            'empresa_id' => $empresaId,
            'empresa_nome' => $nomeEmpresa,
            'empresa_slug' => $slug,
            'plano_id' => $planoFreeId,
            'plano_nome' => 'FREE'
        ];
    }

    /*
    |------------------------------------------------------------------
    | Catálogo inicial / reparo de tenant recém-criado vazio
    |------------------------------------------------------------------
    | - Novos tenants Google recebem categorias e produtos padrão;
    | - Tenants que nasceram antes desta correção e seguem vazios
    |   são reparados no próximo login;
    | - O tenant administrativo Bacuri Digital não recebe cardápio.
    */
    $empresaCatalogoId = (int)($user['empresa_id'] ?? 0);
    $empresaCatalogoSlug = trim((string)($user['empresa_slug'] ?? ''));

    if($empresaCatalogoId > 0 && $empresaCatalogoSlug !== 'bacuri-digital'){
        catalogoInicialCriarParaEmpresa($conn, $empresaCatalogoId);
    }

    $conn->commit();

    unset(
        $_SESSION['google_oauth_state'],
        $_SESSION['google_oauth_nonce'],
        $_SESSION['google_oauth_started_at']
    );

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_nome'] = (string)$user['nome'];
    $_SESSION['user_email'] = (string)$user['email'];
    $_SESSION['user_tipo'] = (string)$user['tipo'];
    $_SESSION['user_role'] = (string)$user['tipo'];

    $_SESSION['empresa_id'] = (int)$user['empresa_id'];
    $_SESSION['empresa_nome'] = (string)$user['empresa_nome'];
    $_SESSION['empresa_slug'] = (string)$user['empresa_slug'];
    $_SESSION['plano_id'] = (int)($user['plano_id'] ?? 0);
    $_SESSION['plano_nome'] = (string)($user['plano_nome'] ?? '');

    googleRedirecionarPorTipo((string)$user['tipo']);

} catch(Throwable $e){
    try {
        if(isset($conn) && $conn instanceof mysqli){
            $conn->rollback();
        }
    } catch(Throwable $rollbackError){
        error_log('[GOOGLE LOGIN ROLLBACK] ' . $rollbackError->getMessage());
    }

    error_log('[GOOGLE LOGIN] ' . $e->getMessage());
    googleVoltarLogin($e->getMessage());
}
