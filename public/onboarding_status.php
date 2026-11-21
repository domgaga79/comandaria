<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/conexao.php';

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

function onboardingResponder(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function onboardingSessaoInteiro(array $chaves): int {
    foreach($chaves as $chave){
        if(isset($_SESSION[$chave]) && is_numeric($_SESSION[$chave])){
            $valor = (int)$_SESSION[$chave];

            if($valor > 0){
                return $valor;
            }
        }
    }

    return 0;
}

function onboardingSessaoTexto(array $chaves): string {
    foreach($chaves as $chave){
        if(isset($_SESSION[$chave])){
            $valor = trim((string)$_SESSION[$chave]);

            if($valor !== ''){
                return $valor;
            }
        }
    }

    return '';
}

function onboardingPaginaPermitida(string $pagina): string {
    $pagina = basename(trim($pagina));

    $permitidas = [
        'dashboard.php',
        'index.php',
        'painel_cozinha.php',
        'painel_caixa.php'
    ];

    return in_array($pagina, $permitidas, true) ? $pagina : '';
}

function onboardingTourKey(string $pagina): string {
    return match($pagina){
        'dashboard.php'      => 'dashboard_admin_v1',
        'index.php'          => 'atendimento_v1',
        'painel_cozinha.php' => 'cozinha_v1',
        'painel_caixa.php'   => 'caixa_v1',
        default              => ''
    };
}

try {
    $usuarioId = onboardingSessaoInteiro([
        'user_id',
        'usuario_id',
        'id_usuario',
        'id'
    ]);

    if($usuarioId <= 0){
        onboardingResponder([
            'ok' => false,
            'mostrar' => false,
            'erro' => 'Usuário não identificado na sessão.'
        ], 401);
    }

    $pagina = onboardingPaginaPermitida((string)($_GET['page'] ?? ''));

    if($pagina === ''){
        onboardingResponder([
            'ok' => true,
            'mostrar' => false,
            'tour_key' => '',
            'pagina' => ''
        ]);
    }

    $tourKey = onboardingTourKey($pagina);

    if($tourKey === ''){
        onboardingResponder([
            'ok' => true,
            'mostrar' => false,
            'tour_key' => '',
            'pagina' => $pagina
        ]);
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM usuarios_onboarding_tours
        WHERE usuario_id = ?
        AND tour_key = ?
        LIMIT 1
    ");

    if(!$stmt){
        throw new RuntimeException($conn->error);
    }

    $stmt->bind_param('is', $usuarioId, $tourKey);

    if(!$stmt->execute()){
        throw new RuntimeException($stmt->error);
    }

    $res = $stmt->get_result();
    $jaConcluido = $res && $res->num_rows > 0;

    $stmt->close();

    onboardingResponder([
        'ok' => true,
        'mostrar' => !$jaConcluido,
        'tour_key' => $tourKey,
        'pagina' => $pagina,
        'usuario_id' => $usuarioId
    ]);

} catch(Throwable $e) {
    error_log('[ERRO onboarding_status.php] ' . $e->getMessage());

    onboardingResponder([
        'ok' => false,
        'mostrar' => false,
        'erro' => 'Não foi possível verificar o onboarding.'
    ], 500);
} finally {
    if(isset($conn) && $conn instanceof mysqli){
        $conn->close();
    }
}
