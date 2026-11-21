<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

$_SERVER['HTTP_ACCEPT'] = 'application/json';

require __DIR__ . '/auth.php';
requireRole(['admin', 'caixa']);

require __DIR__ . '/conexao.php';
require __DIR__ . '/../helpers/response.php';
require __DIR__ . '/../helpers/request.php';
require __DIR__ . '/../helpers/logger.php';
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../core/tenant_core.php';

$empresaId = tenantResolverEmpresaId(authEmpresaId());

try {

    $data = bodyJson();

    $pedidoId = (int)($data['pedido_id'] ?? 0);
    $acao = strtolower(trim((string)($data['acao'] ?? '')));

    if($pedidoId <= 0){
        erro('Pedido inválido', 400);
    }

    if(!in_array($acao, ['quitar', 'reabrir'], true)){
        erro('Ação inválida', 400);
    }

    foreach(['forma_pagamento', 'prazo_status', 'prazo_quitado_em'] as $coluna){
        $check = $conn->query("SHOW COLUMNS FROM pedidos LIKE '" . $conn->real_escape_string($coluna) . "'");

        $temColuna = $check instanceof mysqli_result && $check->num_rows > 0;

        if($check instanceof mysqli_result){
            $check->free();
        }

        if(!$temColuna){
            erro('Estrutura de contas a prazo indisponível no banco atual', 500);
        }
    }

    $stmt = $conn->prepare("\n        SELECT\n            id,\n            status,\n            forma_pagamento,\n            prazo_status\n        FROM pedidos\n        WHERE id = ?\n        AND empresa_id = ?\n        LIMIT 1\n    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param('ii', $pedidoId, $empresaId);

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();
    $pedido = $res->fetch_assoc();
    $stmt->close();

    if(!$pedido){
        erro('Pedido não encontrado', 404);
    }

    if(($pedido['status'] ?? '') !== STATUS_PAGO || ($pedido['forma_pagamento'] ?? '') !== PAGAMENTO_PRAZO){
        erro('Este pedido não é uma conta a prazo paga/fechada', 409);
    }

    if($acao === 'quitar'){
        $novoStatus = PRAZO_STATUS_QUITADO;
        $sql = "\n            UPDATE pedidos\n            SET\n                prazo_status = ?,\n                prazo_quitado_em = NOW(),\n                updated_at = NOW()\n            WHERE id = ?\n            AND empresa_id = ?\n            AND forma_pagamento = ?\n            AND status = ?\n        ";
    } else {
        $novoStatus = PRAZO_STATUS_ABERTO;
        $sql = "\n            UPDATE pedidos\n            SET\n                prazo_status = ?,\n                prazo_quitado_em = NULL,\n                updated_at = NOW()\n            WHERE id = ?\n            AND empresa_id = ?\n            AND forma_pagamento = ?\n            AND status = ?\n        ";
    }

    $stmtUpdate = $conn->prepare($sql);

    if(!$stmtUpdate){
        throw new Exception($conn->error);
    }

    $formaPrazo = PAGAMENTO_PRAZO;
    $statusPago = STATUS_PAGO;

    $stmtUpdate->bind_param(
        'siiss',
        $novoStatus,
        $pedidoId,
        $empresaId,
        $formaPrazo,
        $statusPago
    );

    if(!$stmtUpdate->execute()){
        throw new Exception($stmtUpdate->error);
    }

    $stmtUpdate->close();

    sucesso([
        'pedido_id' => $pedidoId,
        'prazo_status' => $novoStatus,
        'prazo_quitado_em' => $acao === 'quitar' ? date('Y-m-d H:i:s') : null
    ]);

} catch(Throwable $e){

    logErro($e);

    erro($e->getMessage(), 500);
}

$conn->close();
