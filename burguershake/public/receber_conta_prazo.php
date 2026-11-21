<?php
require __DIR__ . '/auth.php';
requireRole(['admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

date_default_timezone_set('America/Bahia');

require __DIR__ . '/conexao.php';

if(isset($conn) && $conn instanceof mysqli && method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

function redirecionarPrazo(string $tipo, string $mensagem): void {

    $url = 'controle_prazo.php?status=aberto'
        . '&tipo=' . rawurlencode($tipo)
        . '&msg=' . rawurlencode($mensagem);

    header('Location: ' . $url);
    exit;
}

function colunaExistePrazo(mysqli $conn, string $tabela, string $coluna): bool {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");

    if(!$stmt){
        return false;
    }

    $stmt->bind_param('ss', $tabela, $coluna);

    if(!$stmt->execute()){
        $stmt->close();
        return false;
    }

    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: [];
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

function statusBaixaPrazo(mysqli $conn): string {

    $res = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'prazo_status'");

    if(!$res){
        throw new Exception('Campo pedidos.prazo_status não encontrado.');
    }

    $row = $res->fetch_assoc();

    if(!$row){
        throw new Exception('Campo pedidos.prazo_status não encontrado.');
    }

    $type = strtolower((string)($row['Type'] ?? ''));

    if(strpos($type, "'pago'") !== false){
        return 'pago';
    }

    if(strpos($type, "'recebido'") !== false){
        return 'recebido';
    }

    if(strpos($type, "'quitado'") !== false){
        return 'quitado';
    }

    throw new Exception('O campo prazo_status não possui valor compatível para baixa.');
}

function bindDinamicoPrazo(mysqli_stmt $stmt, string $types, array &$params): void {

    $bind = [$types];

    foreach($params as $key => &$value){
        $bind[] = &$value;
    }

    call_user_func_array([$stmt, 'bind_param'], $bind);
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    redirecionarPrazo('erro', 'Ação inválida para registrar recebimento.');
}

$pedidoId = (int)($_POST['pedido_id'] ?? 0);
$formaRecebimento = trim((string)($_POST['forma_recebimento'] ?? ''));

$formasPermitidas = ['dinheiro', 'pix', 'cartao'];

if($pedidoId <= 0){
    redirecionarPrazo('erro', 'Pedido inválido.');
}

if(!in_array($formaRecebimento, $formasPermitidas, true)){
    redirecionarPrazo('erro', 'Selecione uma forma de recebimento válida.');
}

$transacaoAberta = false;

try {

    $conn->begin_transaction();
    $transacaoAberta = true;

    $temColunaServico10Prazo = colunaExistePrazo($conn, 'pedidos', 'servico_10_ativo');
    $temColunaTaxaServico10Prazo = colunaExistePrazo($conn, 'pedidos', 'taxa_servico_10');
    $temEstruturaServico10Prazo = $temColunaServico10Prazo && $temColunaTaxaServico10Prazo;

    $selectServico10Prazo = $temEstruturaServico10Prazo
        ? "servico_10_ativo, taxa_servico_10,"
        : "0 AS servico_10_ativo, 0 AS taxa_servico_10,";

    $stmtPedido = $conn->prepare("
        SELECT
            id,
            mesa,
            cliente,
            total,
            {$selectServico10Prazo}
            forma_pagamento,
            prazo_status
        FROM pedidos
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    if(!$stmtPedido){
        throw new Exception($conn->error);
    }

    $stmtPedido->bind_param('i', $pedidoId);

    if(!$stmtPedido->execute()){
        throw new Exception($stmtPedido->error);
    }

    $resPedido = $stmtPedido->get_result();
    $pedido = $resPedido->fetch_assoc();
    $stmtPedido->close();

    if(!$pedido){
        throw new Exception('Conta a prazo não encontrada.');
    }

    if((string)($pedido['forma_pagamento'] ?? '') !== 'prazo'){
        throw new Exception('O pedido informado não está marcado como pagamento a prazo.');
    }

    $statusAtual = mb_strtolower(trim((string)($pedido['prazo_status'] ?? '')), 'UTF-8');

    if(in_array($statusAtual, ['pago', 'recebido', 'quitado'], true)){
        throw new Exception('Esta conta a prazo já foi baixada.');
    }

    /*
    |--------------------------------------------------------------------------
    | Caixa aberto
    |--------------------------------------------------------------------------
    | O sistema atual abre/fecha caixa na tabela `caixas`.
    | A tabela antiga `caixa` pode existir no banco, mas não recebe os registros
    | operacionais atuais. Por isso a baixa da conta a prazo não encontrava
    | caixa aberto e fazia rollback da operação.
    */
    $stmtCaixa = $conn->prepare("
        SELECT id
        FROM caixas
        WHERE status = 'aberto'
        ORDER BY aberto_em DESC, id DESC
        LIMIT 1
        FOR UPDATE
    ");

    if(!$stmtCaixa){
        throw new Exception($conn->error);
    }

    if(!$stmtCaixa->execute()){
        throw new Exception($stmtCaixa->error);
    }

    $resCaixa = $stmtCaixa->get_result();
    $caixa = $resCaixa->fetch_assoc();
    $stmtCaixa->close();

    if(!$caixa){
        throw new Exception('Não há caixa aberto. Abra o caixa antes de registrar o recebimento.');
    }

    $caixaId = (int)$caixa['id'];

    $servico10AtivoPrazo = !empty($pedido['servico_10_ativo']);
    $taxaServico10Prazo = $servico10AtivoPrazo
        ? round((float)($pedido['taxa_servico_10'] ?? ((float)($pedido['total'] ?? 0) * 0.10)), 2)
        : 0.00;

    $valor = round((float)($pedido['total'] ?? 0) + $taxaServico10Prazo, 2);

    if($valor <= 0){
        throw new Exception('A conta a prazo está com valor inválido.');
    }

    $statusBaixa = statusBaixaPrazo($conn);

    $stmtUpdate = $conn->prepare("
        UPDATE pedidos
        SET
            prazo_status = ?,
            prazo_quitado_em = NOW(),
            updated_at = NOW()
        WHERE id = ?
        AND forma_pagamento = 'prazo'
        LIMIT 1
    ");

    if(!$stmtUpdate){
        throw new Exception($conn->error);
    }

    $stmtUpdate->bind_param('si', $statusBaixa, $pedidoId);

    if(!$stmtUpdate->execute()){
        throw new Exception($stmtUpdate->error);
    }

    if($stmtUpdate->affected_rows < 1){
        throw new Exception('Não foi possível marcar a conta a prazo como paga.');
    }

    $stmtUpdate->close();

    $referencia = !empty($pedido['cliente'])
        ? 'Cliente ' . trim((string)$pedido['cliente'])
        : (!empty($pedido['mesa']) ? 'Mesa ' . (int)$pedido['mesa'] : 'Sem identificação');

    $descricao = 'Recebimento de conta a prazo - Pedido #' . $pedidoId . ' - ' . $referencia;

    $colunas = ['caixa_id', 'tipo', 'valor', 'descricao', 'pedido_id'];
    $marcadores = ['?', '?', '?', '?', '?'];
    $types = 'isdsi';
    $params = [$caixaId, 'pagamento_pedido', $valor, $descricao, $pedidoId];

    if(colunaExistePrazo($conn, 'caixa_movimentos', 'forma_pagamento')){
        $colunas[] = 'forma_pagamento';
        $marcadores[] = '?';
        $types .= 's';
        $params[] = $formaRecebimento;
    }

    if(colunaExistePrazo($conn, 'caixa_movimentos', 'usuario_id')){
        $usuarioId = (int)($_SESSION['user_id'] ?? 0);
        $colunas[] = 'usuario_id';
        $marcadores[] = '?';
        $types .= 'i';
        $params[] = $usuarioId > 0 ? $usuarioId : null;
    }

    $sqlMovimento = "
        INSERT INTO caixa_movimentos
        (" . implode(', ', $colunas) . ")
        VALUES
        (" . implode(', ', $marcadores) . ")
    ";

    $stmtMov = $conn->prepare($sqlMovimento);

    if(!$stmtMov){
        throw new Exception($conn->error);
    }

    bindDinamicoPrazo($stmtMov, $types, $params);

    if(!$stmtMov->execute()){
        throw new Exception($stmtMov->error);
    }

    $stmtMov->close();

    $conn->commit();
    $transacaoAberta = false;

    redirecionarPrazo(
        'ok',
        'Recebimento registrado e conta a prazo marcada como paga.'
    );

} catch(Throwable $e){

    if($transacaoAberta){
        $conn->rollback();
    }

    error_log('[RECEBER CONTA PRAZO] ' . $e->getMessage());

    redirecionarPrazo(
        'erro',
        $e->getMessage()
    );
}

if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
?>