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

function onboardingResponderConcluir(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function onboardingSessaoInteiroConcluir(array $chaves): int {
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

function onboardingTourKeyValida(string $tourKey): string {
    $tourKey = trim($tourKey);

    if($tourKey === ''){
        return '';
    }

    if(!preg_match('/^[a-z0-9_-]{3,120}$/', $tourKey)){
        return '';
    }

    $permitidas = [
        'dashboard_admin_v1',
        'atendimento_v1',
        'cozinha_v1',
        'caixa_v1'
    ];

    return in_array($tourKey, $permitidas, true) ? $tourKey : '';
}

try {
    if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'){
        onboardingResponderConcluir([
            'ok' => false,
            'erro' => 'Método não permitido.'
        ], 405);
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '{}', true);

    if(!is_array($payload)){
        $payload = [];
    }

    $tourKey = onboardingTourKeyValida((string)($payload['tour_key'] ?? $_POST['tour_key'] ?? ''));

    if($tourKey === ''){
        onboardingResponderConcluir([
            'ok' => false,
            'erro' => 'Tour inválido.'
        ], 422);
    }

    $usuarioId = onboardingSessaoInteiroConcluir([
        'user_id',
        'usuario_id',
        'id_usuario',
        'id'
    ]);

    if($usuarioId <= 0){
        onboardingResponderConcluir([
            'ok' => false,
            'erro' => 'Usuário não identificado na sessão.'
        ], 401);
    }

    $empresaId = onboardingSessaoInteiroConcluir([
        'empresa_id',
        'user_empresa_id',
        'empresa'
    ]);

    $empresaIdSql = $empresaId > 0 ? $empresaId : null;

    $stmt = $conn->prepare("
        INSERT INTO usuarios_onboarding_tours (
            usuario_id,
            empresa_id,
            tour_key,
            concluido_em
        ) VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            concluido_em = concluido_em
    ");

    if(!$stmt){
        throw new RuntimeException($conn->error);
    }

    $stmt->bind_param('iis', $usuarioId, $empresaIdSql, $tourKey);

    if(!$stmt->execute()){
        throw new RuntimeException($stmt->error);
    }

    $stmt->close();

    onboardingResponderConcluir([
        'ok' => true,
        'mensagem' => 'Onboarding concluído.',
        'tour_key' => $tourKey
    ]);

} catch(Throwable $e) {
    error_log('[ERRO onboarding_concluir.php] ' . $e->getMessage());

    onboardingResponderConcluir([
        'ok' => false,
        'erro' => 'Não foi possível registrar a conclusão do onboarding.'
    ], 500);
} finally {
    if(isset($conn) && $conn instanceof mysqli){
        $conn->close();
    }
}
