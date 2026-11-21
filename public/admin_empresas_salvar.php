<?php
require __DIR__ . '/auth.php';
requireRole(['admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/conexao.php';
require __DIR__ . '/../core/plataforma_admin_core.php';

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

plataformaRequireAdmin($conn);

function voltarAdminEmpresas(string $msg, string $alerta = 'ok'): void {
    header('Location: admin_empresas.php?msg=' . urlencode($msg) . '&alerta=' . urlencode($alerta));
    exit;
}

try {
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        voltarAdminEmpresas('Método inválido.', 'erro');
    }

    $csrfSessao = (string)($_SESSION['admin_empresas_csrf'] ?? '');
    $csrfRecebido = (string)($_POST['csrf'] ?? '');

    if($csrfSessao === '' || $csrfRecebido === '' || !hash_equals($csrfSessao, $csrfRecebido)){
        voltarAdminEmpresas('Sessão expirada. Reabra o painel e tente novamente.', 'erro');
    }

    $empresaId = (int)($_POST['empresa_id'] ?? 0);
    $planoId = (int)($_POST['plano_id'] ?? 0);
    $status = trim((string)($_POST['status'] ?? 'ativa'));
    $statusAssinatura = trim((string)($_POST['status_assinatura'] ?? 'ativa'));
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    $telefoneNormalizado = plataformaNormalizarTelefone($telefone);

    if($empresaId <= 0){
        voltarAdminEmpresas('Empresa inválida.', 'erro');
    }

    if($planoId <= 0){
        voltarAdminEmpresas('Plano inválido.', 'erro');
    }

    if(!in_array($status, ['ativa', 'inativa'], true)){
        voltarAdminEmpresas('Status de empresa inválido.', 'erro');
    }

    if(!in_array($statusAssinatura, ['ativa', 'suspensa', 'cancelada'], true)){
        voltarAdminEmpresas('Status de assinatura inválido.', 'erro');
    }

    if($telefoneNormalizado !== '' && strlen($telefoneNormalizado) < 10){
        voltarAdminEmpresas('Telefone inválido. Informe DDD e número.', 'erro');
    }

    if(strlen($telefoneNormalizado) > 20){
        voltarAdminEmpresas('Telefone excede o tamanho permitido.', 'erro');
    }

    $conn->begin_transaction();

    $stmtEmpresa = $conn->prepare("\n        SELECT\n            id,\n            nome,\n            slug,\n            plano_id,\n            status,\n            status_assinatura,\n            is_admin_plataforma\n        FROM empresas\n        WHERE id = ?\n        LIMIT 1\n        FOR UPDATE\n    ");

    if(!$stmtEmpresa){
        throw new Exception($conn->error);
    }

    $stmtEmpresa->bind_param('i', $empresaId);

    if(!$stmtEmpresa->execute()){
        throw new Exception($stmtEmpresa->error);
    }

    $empresa = $stmtEmpresa->get_result()->fetch_assoc();
    $stmtEmpresa->close();

    if(!$empresa){
        throw new Exception('Empresa não encontrada.');
    }

    $stmtPlano = $conn->prepare("\n        SELECT\n            id,\n            nome,\n            limite_mesas,\n            limite_pedidos_mes,\n            ativo\n        FROM planos\n        WHERE id = ?\n        LIMIT 1\n    ");

    if(!$stmtPlano){
        throw new Exception($conn->error);
    }

    $stmtPlano->bind_param('i', $planoId);

    if(!$stmtPlano->execute()){
        throw new Exception($stmtPlano->error);
    }

    $plano = $stmtPlano->get_result()->fetch_assoc();
    $stmtPlano->close();

    if(!$plano || (int)$plano['ativo'] !== 1){
        throw new Exception('Plano inexistente ou inativo.');
    }

    $isAdminPlataforma = (int)($empresa['is_admin_plataforma'] ?? 0) === 1;

    if($isAdminPlataforma){
        if($status !== 'ativa' || $statusAssinatura !== 'ativa'){
            throw new Exception('A empresa administradora da Bacuri Digital não pode ser inativada ou suspensa.');
        }

        if(strtoupper((string)$plano['nome']) !== 'PRO'){
            throw new Exception('A empresa administradora da Bacuri Digital deve permanecer no plano PRO.');
        }
    }

    $stmtMesas = $conn->prepare("\n        SELECT COUNT(*) AS total\n        FROM mesas_qr\n        WHERE empresa_id = ?\n    ");

    if(!$stmtMesas){
        throw new Exception($conn->error);
    }

    $stmtMesas->bind_param('i', $empresaId);

    if(!$stmtMesas->execute()){
        throw new Exception($stmtMesas->error);
    }

    $mesasUsadas = (int)(($stmtMesas->get_result()->fetch_assoc()['total'] ?? 0));
    $stmtMesas->close();

    $stmtPedidos = $conn->prepare("\n        SELECT COUNT(*) AS total\n        FROM pedidos\n        WHERE empresa_id = ?\n        AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')\n        AND created_at < DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'), INTERVAL 1 MONTH)\n    ");

    if(!$stmtPedidos){
        throw new Exception($conn->error);
    }

    $stmtPedidos->bind_param('i', $empresaId);

    if(!$stmtPedidos->execute()){
        throw new Exception($stmtPedidos->error);
    }

    $pedidosMes = (int)(($stmtPedidos->get_result()->fetch_assoc()['total'] ?? 0));
    $stmtPedidos->close();

    $limiteMesas = (int)$plano['limite_mesas'];
    $limitePedidos = (int)$plano['limite_pedidos_mes'];

    if($mesasUsadas > $limiteMesas){
        throw new Exception(
            'Não foi possível aplicar o plano ' . $plano['nome'] . ': a empresa usa ' .
            $mesasUsadas . ' mesas e o plano permite ' . $limiteMesas . '.'
        );
    }

    if($pedidosMes > $limitePedidos){
        throw new Exception(
            'Não foi possível aplicar o plano ' . $plano['nome'] . ': a empresa já registrou ' .
            $pedidosMes . ' pedidos neste mês e o plano permite ' . $limitePedidos . '.'
        );
    }

    $telefonePersistido = $telefoneNormalizado !== '' ? $telefoneNormalizado : null;

    $stmtUpdate = $conn->prepare("\n        UPDATE empresas\n        SET\n            plano_id = ?,\n            status = ?,\n            status_assinatura = ?,\n            telefone = ?\n        WHERE id = ?\n        LIMIT 1\n    ");

    if(!$stmtUpdate){
        throw new Exception($conn->error);
    }

    $stmtUpdate->bind_param('isssi', $planoId, $status, $statusAssinatura, $telefonePersistido, $empresaId);

    if(!$stmtUpdate->execute()){
        throw new Exception($stmtUpdate->error);
    }

    $stmtUpdate->close();
    $conn->commit();

    voltarAdminEmpresas('Empresa atualizada com sucesso.');

} catch(Throwable $e){
    try {
        if(isset($conn) && $conn instanceof mysqli){
            $conn->rollback();
        }
    } catch(Throwable $rollbackError){
        error_log('[ADMIN EMPRESAS ROLLBACK] ' . $rollbackError->getMessage());
    }

    error_log('[ADMIN EMPRESAS] ' . $e->getMessage());
    voltarAdminEmpresas($e->getMessage(), 'erro');
}
