<?php
require __DIR__ . '/auth.php';
requireRole(['admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

date_default_timezone_set('America/Bahia');

require __DIR__ . '/conexao.php';
require __DIR__ . '/../core/tenant_core.php';

$empresaId = tenantResolverEmpresaId(authEmpresaId());

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

function tabelaExistePrazo(mysqli $conn, string $tabela): bool {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
    ");

    if(!$stmt){
        return false;
    }

    $stmt->bind_param('s', $tabela);

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

function textoMinusculoPrazo(string $texto): string {
    if(function_exists('mb_strtolower')){
        return mb_strtolower($texto, 'UTF-8');
    }

    return strtolower($texto);
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

    if($empresaId <= 0){
        throw new Exception('Empresa não identificada para registrar o recebimento.');
    }

    $conn->begin_transaction();
    $transacaoAberta = true;

    $temColunaNumeroPedidoPrazo = colunaExistePrazo($conn, 'pedidos', 'numero_pedido');
    $temColunaServico10Prazo = colunaExistePrazo($conn, 'pedidos', 'servico_10_ativo');
    $temColunaTaxaServico10Prazo = colunaExistePrazo($conn, 'pedidos', 'taxa_servico_10');
    $temEstruturaServico10Prazo = $temColunaServico10Prazo && $temColunaTaxaServico10Prazo;

    $selectNumeroPedidoPrazo = $temColunaNumeroPedidoPrazo
        ? "numero_pedido,"
        : "id AS numero_pedido,";

    $selectServico10Prazo = $temEstruturaServico10Prazo
        ? "servico_10_ativo, taxa_servico_10,"
        : "0 AS servico_10_ativo, 0 AS taxa_servico_10,";

    $stmtPedido = $conn->prepare("
        SELECT
            id,
            {$selectNumeroPedidoPrazo}
            mesa,
            cliente,
            total,
            {$selectServico10Prazo}
            forma_pagamento,
            prazo_status
        FROM pedidos
        WHERE id = ?
        AND empresa_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    if(!$stmtPedido){
        throw new Exception($conn->error);
    }

    $stmtPedido->bind_param('ii', $pedidoId, $empresaId);

    if(!$stmtPedido->execute()){
        throw new Exception($stmtPedido->error);
    }

    $resPedido = $stmtPedido->get_result();
    $pedido = $resPedido->fetch_assoc();
    $stmtPedido->close();

    if(!$pedido){
        throw new Exception('Conta a prazo não encontrada para esta empresa.');
    }

    if((string)($pedido['forma_pagamento'] ?? '') !== 'prazo'){
        throw new Exception('O pedido informado não está marcado como pagamento a prazo.');
    }

    $statusAtual = textoMinusculoPrazo(trim((string)($pedido['prazo_status'] ?? '')));

    if(in_array($statusAtual, ['pago', 'recebido', 'quitado'], true)){
        throw new Exception('Esta conta a prazo já foi baixada.');
    }

    if(!tabelaExistePrazo($conn, 'caixas')){
        throw new Exception('Tabela caixas não encontrada. O recebimento a prazo usa a estrutura atual de abertura/fechamento de caixa.');
    }

    $temEmpresaCaixasPrazo = colunaExistePrazo($conn, 'caixas', 'empresa_id');

    if(!$temEmpresaCaixasPrazo){
        throw new Exception('Campo caixas.empresa_id não encontrado. A baixa multitenant precisa localizar o caixa aberto da empresa logada.');
    }

    $stmtCaixa = $conn->prepare("
        SELECT id
        FROM caixas
        WHERE empresa_id = ?
        AND status = 'aberto'
        ORDER BY aberto_em DESC, id DESC
        LIMIT 1
        FOR UPDATE
    ");

    if(!$stmtCaixa){
        throw new Exception($conn->error);
    }

    $stmtCaixa->bind_param('i', $empresaId);

    if(!$stmtCaixa->execute()){
        throw new Exception($stmtCaixa->error);
    }

    $resCaixa = $stmtCaixa->get_result();
    $caixa = $resCaixa->fetch_assoc();
    $stmtCaixa->close();

    if(!$caixa){
        throw new Exception('Não há caixa aberto para esta empresa. Abra o caixa antes de registrar o recebimento.');
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

    $setsUpdate = [
        'prazo_status = ?',
        'updated_at = NOW()'
    ];
    $typesUpdate = 's';
    $paramsUpdate = [$statusBaixa];

    if(colunaExistePrazo($conn, 'pedidos', 'prazo_quitado_em')){
        $setsUpdate[] = 'prazo_quitado_em = NOW()';
    }

    $typesUpdate .= 'ii';
    $paramsUpdate[] = $pedidoId;
    $paramsUpdate[] = $empresaId;

    $sqlUpdate = "
        UPDATE pedidos
        SET " . implode(",\n            ", $setsUpdate) . "
        WHERE id = ?
        AND empresa_id = ?
        AND forma_pagamento = 'prazo'
        LIMIT 1
    ";

    $stmtUpdate = $conn->prepare($sqlUpdate);

    if(!$stmtUpdate){
        throw new Exception($conn->error);
    }

    bindDinamicoPrazo($stmtUpdate, $typesUpdate, $paramsUpdate);

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

    $numeroPedido = (int)($pedido['numero_pedido'] ?? $pedidoId);
    $descricao = 'Recebimento de conta a prazo - Pedido #' . $numeroPedido . ' - ' . $referencia;

    $colunas = [];
    $marcadores = [];
    $types = '';
    $params = [];

    if(colunaExistePrazo($conn, 'caixa_movimentos', 'empresa_id')){
        $colunas[] = 'empresa_id';
        $marcadores[] = '?';
        $types .= 'i';
        $params[] = $empresaId;
    }

    $colunas = array_merge($colunas, ['caixa_id', 'tipo', 'valor', 'descricao', 'pedido_id']);
    $marcadores = array_merge($marcadores, ['?', '?', '?', '?', '?']);
    $types .= 'isdsi';
    $params[] = $caixaId;
    $params[] = 'pagamento_pedido';
    $params[] = $valor;
    $params[] = $descricao;
    $params[] = $pedidoId;

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

    error_log('[RECEBER CONTA PRAZO MULTITENANT] ' . $e->getMessage());

    redirecionarPrazo(
        'erro',
        $e->getMessage()
    );
}

if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
?>
