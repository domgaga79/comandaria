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

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dinheiroPrazo($value): string {
    return number_format((float)$value, 2, ',', '.');
}

function dataBrPrazo($value): string {

    if(empty($value)){
        return '-';
    }

    $timestamp = strtotime((string)$value);

    if(!$timestamp){
        return '-';
    }

    return date('d/m/Y H:i', $timestamp);
}

function bindParamsPrazo(mysqli_stmt $stmt, string $types, array &$params): void {

    if($types === '' || empty($params)){
        return;
    }

    $bind = [$types];

    foreach($params as $key => &$value){
        $bind[] = &$value;
    }

    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function statusPrazoPago($status): bool {

    $status = strtolower(trim((string)$status));

    return in_array($status, ['pago', 'recebido', 'quitado'], true);
}

function statusPrazoTexto($status): string {

    return statusPrazoPago($status)
        ? 'Pago'
        : 'Em aberto';
}

function statusPrazoBadge($status): string {

    return statusPrazoPago($status)
        ? 'success'
        : 'warning text-dark';
}


$statusFiltro = $_GET['status'] ?? 'aberto';

if(!in_array($statusFiltro, ['aberto', 'pago', 'todos'], true)){
    $statusFiltro = 'aberto';
}

$busca = trim((string)($_GET['q'] ?? ''));

$feedback = trim((string)($_GET['msg'] ?? ''));
$tipoFeedback = trim((string)($_GET['tipo'] ?? ''));

if(!in_array($tipoFeedback, ['ok', 'erro'], true)){
    $tipoFeedback = '';
}

$temColunaServico10Prazo = false;
$temColunaTaxaServico10Prazo = false;

$checkServico10Prazo = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'servico_10_ativo'");

if($checkServico10Prazo instanceof mysqli_result){
    $temColunaServico10Prazo = $checkServico10Prazo->num_rows > 0;
    $checkServico10Prazo->free();
}

$checkTaxaServico10Prazo = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'taxa_servico_10'");

if($checkTaxaServico10Prazo instanceof mysqli_result){
    $temColunaTaxaServico10Prazo = $checkTaxaServico10Prazo->num_rows > 0;
    $checkTaxaServico10Prazo->free();
}

$temEstruturaServico10Prazo = $temColunaServico10Prazo && $temColunaTaxaServico10Prazo;
$totalPrazoExpr = $temEstruturaServico10Prazo
    ? "(total + COALESCE(taxa_servico_10, 0))"
    : "total";
$totalPrazoExprP = $temEstruturaServico10Prazo
    ? "(p.total + COALESCE(p.taxa_servico_10, 0))"
    : "p.total";
$selectServico10PrazoP = $temEstruturaServico10Prazo
    ? "p.servico_10_ativo, p.taxa_servico_10,"
    : "0 AS servico_10_ativo, 0 AS taxa_servico_10,";

/*
|--------------------------------------------------------------------------
| RESUMO
|--------------------------------------------------------------------------
*/

$resumoPrazo = [
    'abertas_qtd' => 0,
    'abertas_total' => 0.00,
    'pagas_qtd' => 0,
    'pagas_total' => 0.00
];

try {

    $sqlResumo = "
        SELECT
            SUM(
                CASE
                    WHEN prazo_status IS NULL
                         OR LOWER(CAST(prazo_status AS CHAR)) NOT IN ('pago', 'recebido', 'quitado')
                    THEN 1
                    ELSE 0
                END
            ) AS abertas_qtd,

            SUM(
                CASE
                    WHEN prazo_status IS NULL
                         OR LOWER(CAST(prazo_status AS CHAR)) NOT IN ('pago', 'recebido', 'quitado')
                    THEN {$totalPrazoExpr}
                    ELSE 0
                END
            ) AS abertas_total,

            SUM(
                CASE
                    WHEN LOWER(CAST(prazo_status AS CHAR)) IN ('pago', 'recebido', 'quitado')
                    THEN 1
                    ELSE 0
                END
            ) AS pagas_qtd,

            SUM(
                CASE
                    WHEN LOWER(CAST(prazo_status AS CHAR)) IN ('pago', 'recebido', 'quitado')
                    THEN {$totalPrazoExpr}
                    ELSE 0
                END
            ) AS pagas_total
        FROM pedidos
        WHERE empresa_id = ?
        AND forma_pagamento = 'prazo'
    ";

    $stmtResumoPrazo = $conn->prepare($sqlResumo);

    if($stmtResumoPrazo){
        $stmtResumoPrazo->bind_param('i', $empresaId);
        $stmtResumoPrazo->execute();
        $resResumo = $stmtResumoPrazo->get_result();

        if($resResumo){
            $rowResumo = $resResumo->fetch_assoc() ?: [];

            $resumoPrazo = [
                'abertas_qtd' => (int)($rowResumo['abertas_qtd'] ?? 0),
                'abertas_total' => (float)($rowResumo['abertas_total'] ?? 0),
                'pagas_qtd' => (int)($rowResumo['pagas_qtd'] ?? 0),
                'pagas_total' => (float)($rowResumo['pagas_total'] ?? 0)
            ];
        }

        $stmtResumoPrazo->close();
    }

} catch(Throwable $e){
    error_log('[CONTROLE PRAZO RESUMO] ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| LISTAGEM
|--------------------------------------------------------------------------
*/

$condicoes = [
    "p.empresa_id = ?",
    "p.forma_pagamento = 'prazo'"
];

if($statusFiltro === 'aberto'){
    $condicoes[] = "(
        p.prazo_status IS NULL
        OR LOWER(CAST(p.prazo_status AS CHAR)) NOT IN ('pago', 'recebido', 'quitado')
    )";
}

if($statusFiltro === 'pago'){
    $condicoes[] = "LOWER(CAST(p.prazo_status AS CHAR)) IN ('pago', 'recebido', 'quitado')";
}

$params = [$empresaId];
$types = 'i';

if($busca !== ''){
    $like = '%' . $busca . '%';

    $condicoes[] = "(
        COALESCE(p.cliente, '') LIKE ?
        OR CAST(COALESCE(p.numero_pedido, p.id) AS CHAR) LIKE ?
        OR CAST(COALESCE(p.mesa, '') AS CHAR) LIKE ?
    )";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$sqlContas = "
    SELECT
        p.id,
        p.numero_pedido,
        p.mesa,
        p.cliente,
        p.status,
        p.forma_pagamento,
        p.prazo_status,
        p.subtotal,
        p.total_adicional,
        p.total,
        {$selectServico10PrazoP}
        {$totalPrazoExprP} AS total_cobravel,
        p.quantidade_itens,
        p.created_at,
        p.updated_at,
        vp.lancado_em,
        rp.recebido_em
    FROM pedidos p

    LEFT JOIN (
        SELECT
            pedido_id,
            MIN(created_at) AS lancado_em
        FROM caixa_movimentos
        WHERE tipo = 'venda_prazo'
        GROUP BY pedido_id
    ) vp
        ON vp.pedido_id = p.id

    LEFT JOIN (
        SELECT
            pedido_id,
            MAX(created_at) AS recebido_em
        FROM caixa_movimentos
        WHERE tipo = 'pagamento_pedido'
        GROUP BY pedido_id
    ) rp
        ON rp.pedido_id = p.id

    WHERE " . implode("\n    AND ", $condicoes) . "

    ORDER BY
        COALESCE(vp.lancado_em, p.updated_at, p.created_at) DESC,
        p.id DESC

    LIMIT 400
";

$stmtContas = $conn->prepare($sqlContas);

if(!$stmtContas){
    die('Erro ao preparar listagem de prazo: ' . h($conn->error));
}

bindParamsPrazo($stmtContas, $types, $params);

if(!$stmtContas->execute()){
    die('Erro ao consultar contas a prazo: ' . h($stmtContas->error));
}

$resContas = $stmtContas->get_result();

$contas = [];

while($row = $resContas->fetch_assoc()){
    $contas[] = $row;
}

$stmtContas->close();

/*
|--------------------------------------------------------------------------
| ITENS DISCRIMINADOS
|--------------------------------------------------------------------------
*/

$itensPorPedido = [];

$idsPedidos = array_map(
    static fn($conta) => (int)$conta['id'],
    $contas
);

$idsPedidos = array_filter(
    $idsPedidos,
    static fn($id) => $id > 0
);

if(!empty($idsPedidos)){

    $idsSql = implode(',', array_map('intval', $idsPedidos));

    $sqlItens = "
        SELECT
            pi.pedido_id,
            pi.id AS item_id,
            pi.quantidade,
            pi.preco_unitario,
            pi.adicional,
            pi.valor_adicional,
            pi.observacao AS obs,
            pi.ponto,
            pr.nome AS produto_nome
        FROM pedido_itens pi
        INNER JOIN produtos pr
            ON pr.id = pi.produto_id
           AND pr.empresa_id = pi.empresa_id
        WHERE pi.empresa_id = {$empresaId}
        AND pi.pedido_id IN ({$idsSql})
        ORDER BY
            pi.pedido_id ASC,
            pi.id ASC
    ";

    $resItens = $conn->query($sqlItens);

    if($resItens){
        while($item = $resItens->fetch_assoc()){

            $pedidoIdItem = (int)$item['pedido_id'];

            if(!isset($itensPorPedido[$pedidoIdItem])){
                $itensPorPedido[$pedidoIdItem] = [];
            }

            $itensPorPedido[$pedidoIdItem][] = $item;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Contas a Prazo - Comandaria Express</title>

<meta property="og:title" content="Comandaria Express">
<meta property="og:description" content="Controle de contas a prazo">
<meta property="og:type" content="website">
<meta property="og:image" content="images/comandaria_logo_og.png">

<link rel="icon" href="images/topo_comandaria_shake.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/financeiro.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/staff.css?v=2">
</head>

<body class="cd-staff finance-page finance-prazo">
<?php $staffPage = 'controle_prazo.php'; require __DIR__ . '/includes/staff_nav.php'; ?>

<main id="conteudo" class="finance-workspace">
    <header class="finance-heading">
        <h1>Contas a prazo</h1>
        <p>Consulte saldos, confira itens e registre recebimentos.</p>
    </header>

    <div class="card-box finance-filters">
        <?php if($feedback !== '' && $tipoFeedback !== ''): ?>
            <div role="status" class="alert alert-<?= $tipoFeedback === 'ok' ? 'success' : 'danger' ?> mb-3">
                <?= h($feedback) ?>
            </div>
        <?php endif; ?>

        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md">
                <label for="prazo-busca" class="form-label">
                    Buscar cliente, mesa ou pedido
                </label>

                <input
                    type="text"
                    name="q"
                    id="prazo-busca"
                    class="form-control"
                    value="<?= h($busca) ?>"
                    placeholder="Nome, nº do pedido ou mesa"
                >
            </div>

            <div class="col-md">
                <label for="prazo-status" class="form-label">
                    Status
                </label>

                <select id="prazo-status" name="status" class="form-select">
                    <option value="aberto" <?= $statusFiltro === 'aberto' ? 'selected' : '' ?>>
                        Em aberto
                    </option>
                    <option value="pago" <?= $statusFiltro === 'pago' ? 'selected' : '' ?>>
                        Pagas
                    </option>
                    <option value="todos" <?= $statusFiltro === 'todos' ? 'selected' : '' ?>>
                        Todas
                    </option>
                </select>
            </div>

            <div class="col-md-3 col-xl-2">
                <button type="submit" class="btn btn-success w-100">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <div class="grid-resumo">

        <div class="card-kpi yellow">
            <div class="label">
                Contas em aberto
            </div>

            <div class="value">
                <?= (int)$resumoPrazo['abertas_qtd'] ?>
            </div>
        </div>

        <div class="card-kpi yellow">
            <div class="label">
                Total em aberto
            </div>

            <div class="value">
                R$ <?= dinheiroPrazo($resumoPrazo['abertas_total']) ?>
            </div>
        </div>

        <div class="card-kpi green">
            <div class="label">
                Contas pagas
            </div>

            <div class="value">
                <?= (int)$resumoPrazo['pagas_qtd'] ?>
            </div>
        </div>

        <div class="card-kpi green">
            <div class="label">
                Total recebido
            </div>

            <div class="value">
                R$ <?= dinheiroPrazo($resumoPrazo['pagas_total']) ?>
            </div>
        </div>

    </div>

    <?php if(empty($contas)): ?>
        <div class="alerta-vazio" role="status">
            Nenhuma conta a prazo encontrada para o filtro selecionado.
        </div>
    <?php else: ?>
        <div class="contas-grid">
            <?php foreach($contas as $conta): ?>
                <?php
                    $pedidoId = (int)$conta['id'];
                    $numeroPedido = (int)($conta['numero_pedido'] ?? $conta['id']);
                    $pago = statusPrazoPago($conta['prazo_status'] ?? '');
                    $itensConta = $itensPorPedido[$pedidoId] ?? [];
                    $origem = !empty($conta['cliente'])
                        ? 'Cliente ' . $conta['cliente']
                        : (!empty($conta['mesa']) ? 'Mesa ' . (int)$conta['mesa'] : 'Sem identificação');

                    $dataLancamento = $conta['lancado_em']
                        ?: ($conta['updated_at'] ?: $conta['created_at']);

                    $dataRecebimento = $conta['recebido_em'] ?? null;
                ?>

                <div class="prazo-card <?= $pago ? 'pago' : '' ?>">
                    <div class="prazo-top">
                        <div>
                            <h2 class="prazo-title">
                                <?= h($origem) ?>
                            </h2>

                            <div class="prazo-meta">
                                Pedido #<?= $numeroPedido ?>
                                • Lançado em <?= h(dataBrPrazo($dataLancamento)) ?>
                            </div>

                            <?php if($pago && !empty($dataRecebimento)): ?>
                                <div class="prazo-meta">
                                    Recebido em <?= h(dataBrPrazo($dataRecebimento)) ?>
                                </div>
                            <?php endif; ?>

                            <div class="mt-2">
                                <span class="badge bg-<?= statusPrazoBadge($conta['prazo_status'] ?? '') ?> badge-pill">
                                    <?= h(statusPrazoTexto($conta['prazo_status'] ?? '')) ?>
                                </span>
                            </div>
                        </div>

                        <div class="prazo-total">
                            R$ <?= dinheiroPrazo($conta['total_cobravel'] ?? $conta['total'] ?? 0) ?>
                        </div>
                    </div>

                    <div class="prazo-actions">
                        <a
                            class="btn btn-outline-dark"
                            href="imprimir_conta_prazo.php?pedido_id=<?= $pedidoId ?>"
                            target="_blank" rel="noopener"
                        >
                            Imprimir conta
                        </a>
                    </div>

                    <?php if(!$pago): ?>
                        <div class="recebimento-box">
                            <form
                                method="POST"
                                action="receber_conta_prazo.php"
                                onsubmit="return confirm('Confirmar recebimento da conta a prazo do pedido #<?= $numeroPedido ?>?');"
                            >
                                <input type="hidden" name="pedido_id" value="<?= $pedidoId ?>">

                                <div class="row g-2 align-items-end">
                                    <div class="col-md-7">
                                        <label for="recebimento-<?= $pedidoId ?>" class="form-label">
                                            Forma de recebimento
                                        </label>

                                        <select id="recebimento-<?= $pedidoId ?>" name="forma_recebimento" class="form-select" required>
                                            <option value="">Selecione</option>
                                            <option value="dinheiro">Dinheiro</option>
                                            <option value="pix">Pix</option>
                                            <option value="cartao">Cartão</option>
                                        </select>
                                    </div>

                                    <div class="col-md-5">
                                        <button class="btn btn-success w-100">
                                            Marcar como pago
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <details class="itens-details">
                        <summary>
                            Itens e totais
                        </summary>

                        <div class="mt-2">
                            <?php if(empty($itensConta)): ?>
                                <div class="text-muted small">
                                    Nenhum item encontrado para este pedido.
                                </div>
                            <?php else: ?>
                                <?php foreach($itensConta as $item): ?>
                                    <?php
                                        $qtd = (int)($item['quantidade'] ?? 0);
                                        $precoUnitario = (float)($item['preco_unitario'] ?? 0);
                                        $qtdAdicional = max(0, (int)($item['adicional'] ?? 0));
                                        $valorAdicionalUnitario = (float)($item['valor_adicional'] ?? 0);
                                        $valorAdicionalTotalUnitario = $qtdAdicional * $valorAdicionalUnitario;
                                        $valorUnitarioFinal = $precoUnitario + $valorAdicionalTotalUnitario;
                                        $totalItem = $valorUnitarioFinal * $qtd;
                                    ?>

                                    <div class="item-row">
                                        <div>
                                            <div class="item-name">
                                                <?= $qtd ?>× <?= h($item['produto_nome'] ?? 'Produto') ?>
                                            </div>

                                            <div class="item-meta">
                                                Unitário: R$ <?= dinheiroPrazo($precoUnitario) ?>

                                                <?php if($qtdAdicional > 0 && $valorAdicionalUnitario > 0): ?>
                                                    • Adicional:
                                                    <?= $qtdAdicional ?>× R$ <?= dinheiroPrazo($valorAdicionalUnitario) ?>
                                                <?php endif; ?>
                                            </div>

                                            <?php if(!empty($item['ponto'])): ?>
                                                <div class="item-meta">
                                                    Preparo: <?= h($item['ponto']) ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if(!empty($item['obs'])): ?>
                                                <div class="item-meta">
                                                    Obs.: <?= h($item['obs']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="item-total">
                                            R$ <?= dinheiroPrazo($totalItem) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="linha-total">
                                <span>Subtotal</span>
                                <span>R$ <?= dinheiroPrazo($conta['subtotal'] ?? 0) ?></span>
                            </div>

                            <div class="linha-total">
                                <span>Adicionais</span>
                                <span>R$ <?= dinheiroPrazo($conta['total_adicional'] ?? 0) ?></span>
                            </div>

                            <div class="linha-total">
                                <span>10% serviço</span>
                                <span>
                                    <?= !empty($conta['servico_10_ativo'])
                                        ? 'R$ ' . dinheiroPrazo($conta['taxa_servico_10'] ?? 0)
                                        : 'Não cobrado' ?>
                                </span>
                            </div>

                            <div class="linha-total final">
                                <span>Total final</span>
                                <span>R$ <?= dinheiroPrazo($conta['total_cobravel'] ?? $conta['total'] ?? 0) ?></span>
                            </div>
                        </div>
                    </details>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

<?php
if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
?>