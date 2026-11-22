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
require __DIR__ . '/../core/tenant_core.php';

$empresaId = tenantResolverEmpresaId(authEmpresaId());

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dinheiro($value): string {
    return number_format((float)$value, 2, ',', '.');
}

function dataBr($value): string {

    if(!$value){
        return '-';
    }

    return date('d/m/Y H:i', strtotime($value));
}

function formaPagamentoLabel($forma): string {

    $labels = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'cartao' => 'Cartão',
        'prazo' => 'A prazo',
        'antecipado' => 'Antecipado'
    ];

    return $labels[(string)$forma] ?? '-';
}

function prazoStatusLabel($status): string {

    $labels = [
        'aberto' => 'Em aberto',
        'quitado' => 'Quitado'
    ];

    return $labels[(string)$status] ?? '-';
}

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$hoje = date('Y-m-d');

$dataInicio = $_GET['inicio'] ?? $hoje;
$dataFim = $_GET['fim'] ?? $hoje;

if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)){
    $dataInicio = $hoje;
}

if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)){
    $dataFim = $hoje;
}

$inicioSql = $dataInicio . ' 00:00:00';
$fimSql = $dataFim . ' 23:59:59';

$temColunaServico10Relatorio = false;
$temColunaTaxaServico10Relatorio = false;

$checkServico10Relatorio = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'servico_10_ativo'");

if($checkServico10Relatorio instanceof mysqli_result){
    $temColunaServico10Relatorio = $checkServico10Relatorio->num_rows > 0;
    $checkServico10Relatorio->free();
}

$checkTaxaServico10Relatorio = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'taxa_servico_10'");

if($checkTaxaServico10Relatorio instanceof mysqli_result){
    $temColunaTaxaServico10Relatorio = $checkTaxaServico10Relatorio->num_rows > 0;
    $checkTaxaServico10Relatorio->free();
}

$temEstruturaServico10Relatorio = $temColunaServico10Relatorio && $temColunaTaxaServico10Relatorio;
$totalCobravelExprRelatorio = $temEstruturaServico10Relatorio
    ? "(total + COALESCE(taxa_servico_10, 0))"
    : "total";

$selectServico10Relatorio = $temEstruturaServico10Relatorio
    ? "servico_10_ativo, taxa_servico_10,"
    : "0 AS servico_10_ativo, 0 AS taxa_servico_10,";

/*
|--------------------------------------------------------------------------
| RESUMO GERAL
|--------------------------------------------------------------------------
*/

$stmtResumo = $conn->prepare("
    SELECT
        COUNT(*) AS total_pedidos,

        SUM(
            CASE
                WHEN status = 'pago'
                THEN 1
                ELSE 0
            END
        ) AS pedidos_pagos,

        SUM(
            CASE
                WHEN status = 'cancelado'
                THEN 1
                ELSE 0
            END
        ) AS pedidos_cancelados,

        SUM(
            CASE
                WHEN status = 'pendente'
                THEN 1
                ELSE 0
            END
        ) AS pedidos_pendentes,

        SUM(
            CASE
                WHEN status = 'pronto'
                THEN 1
                ELSE 0
            END
        ) AS pedidos_prontos,

        SUM(
            CASE
                WHEN status = 'pago'
                THEN {$totalCobravelExprRelatorio}
                ELSE 0
            END
        ) AS faturamento,

        SUM(
            CASE
                WHEN status = 'pago'
                THEN total_adicional
                ELSE 0
            END
        ) AS total_adicionais,

        SUM(
            CASE
                WHEN status = 'pago'
                THEN quantidade_itens
                ELSE 0
            END
        ) AS quantidade_itens_vendidos

    FROM pedidos
    WHERE empresa_id = ?
    AND created_at BETWEEN ? AND ?
");

if(!$stmtResumo){
    die("Erro resumo: " . h($conn->error));
}

$stmtResumo->bind_param(
    "iss",
    $empresaId,
    $inicioSql,
    $fimSql
);

$stmtResumo->execute();

$resResumo = $stmtResumo->get_result();

$resumo = $resResumo->fetch_assoc() ?: [];

$stmtResumo->close();

$totalPedidos = (int)($resumo['total_pedidos'] ?? 0);
$pedidosPagos = (int)($resumo['pedidos_pagos'] ?? 0);
$pedidosCancelados = (int)($resumo['pedidos_cancelados'] ?? 0);
$pedidosPendentes = (int)($resumo['pedidos_pendentes'] ?? 0);
$pedidosProntos = (int)($resumo['pedidos_prontos'] ?? 0);

$faturamento = (float)($resumo['faturamento'] ?? 0);
$totalAdicionais = (float)($resumo['total_adicionais'] ?? 0);
$quantidadeItensVendidos = (int)($resumo['quantidade_itens_vendidos'] ?? 0);

$ticketMedio = $pedidosPagos > 0
    ? $faturamento / $pedidosPagos
    : 0;


/*
|--------------------------------------------------------------------------
| VENDAS A PRAZO NO PERÍODO
|--------------------------------------------------------------------------
| No schema atual, a data financeira da venda a prazo vem do lançamento
| em caixa_movimentos, não da criação original do pedido.
|--------------------------------------------------------------------------
*/

$vendasPrazoQtd = 0;
$vendasPrazoTotal = 0.00;
$estruturaPrazoRelatorioOk = false;

$checkFormaPagamento = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'forma_pagamento'");
$checkCaixaMovimentos = $conn->query("SHOW TABLES LIKE 'caixa_movimentos'");

$temFormaPagamentoPrazo = $checkFormaPagamento instanceof mysqli_result && $checkFormaPagamento->num_rows > 0;
$temTabelaCaixaMovimentos = $checkCaixaMovimentos instanceof mysqli_result && $checkCaixaMovimentos->num_rows > 0;

if($checkFormaPagamento instanceof mysqli_result){
    $checkFormaPagamento->free();
}

if($checkCaixaMovimentos instanceof mysqli_result){
    $checkCaixaMovimentos->free();
}

if($temFormaPagamentoPrazo && $temTabelaCaixaMovimentos){
    $stmtPrazo = $conn->prepare("
        SELECT
            COUNT(DISTINCT cm.pedido_id) AS vendas_prazo_qtd,
            COALESCE(SUM(cm.valor), 0) AS vendas_prazo_total
        FROM caixa_movimentos cm
        INNER JOIN pedidos p
            ON p.id = cm.pedido_id
        WHERE cm.empresa_id = ?
        AND p.empresa_id = ?
        AND cm.tipo = 'venda_prazo'
        AND p.forma_pagamento = 'prazo'
        AND cm.created_at BETWEEN ? AND ?
    ");

    if($stmtPrazo){
        $stmtPrazo->bind_param('iiss', $empresaId, $empresaId, $inicioSql, $fimSql);

        if($stmtPrazo->execute()){
            $resPrazo = $stmtPrazo->get_result();
            $rowPrazo = $resPrazo ? ($resPrazo->fetch_assoc() ?: []) : [];

            $vendasPrazoQtd = (int)($rowPrazo['vendas_prazo_qtd'] ?? 0);
            $vendasPrazoTotal = (float)($rowPrazo['vendas_prazo_total'] ?? 0);
            $estruturaPrazoRelatorioOk = true;
        }

        $stmtPrazo->close();
    }
}
/*
|--------------------------------------------------------------------------
| PRODUTOS MAIS VENDIDOS
|--------------------------------------------------------------------------
*/

$stmtProdutos = $conn->prepare("
    SELECT
        pr.id AS produto_id,
        pr.nome AS produto_nome,
        c.nome AS categoria_nome,

        SUM(pi.quantidade) AS quantidade_vendida,

        SUM(
            pi.quantidade * pi.preco_unitario
        ) AS subtotal,

        SUM(
            CASE
                WHEN pi.adicional > 0
                THEN pi.quantidade * pi.valor_adicional * pi.adicional
                ELSE 0
            END
        ) AS total_adicional,

        SUM(
            pi.quantidade *
            (
                pi.preco_unitario +
                CASE
                    WHEN pi.adicional > 0
                    THEN pi.valor_adicional * pi.adicional
                    ELSE 0
                END
            )
        ) AS total_vendido

    FROM pedido_itens pi

    INNER JOIN pedidos p
        ON p.id = pi.pedido_id

    INNER JOIN produtos pr
        ON pr.id = pi.produto_id

    LEFT JOIN categorias c
        ON c.id = pr.categoria_id

    WHERE pi.empresa_id = ?
    AND p.empresa_id = ?
    AND pr.empresa_id = ?
    AND p.status = 'pago'
    AND p.created_at BETWEEN ? AND ?

    GROUP BY
        pr.id,
        pr.nome,
        c.nome

    ORDER BY
        quantidade_vendida DESC,
        total_vendido DESC,
        pr.nome ASC

    LIMIT 20
");

if(!$stmtProdutos){
    die("Erro produtos: " . h($conn->error));
}

$stmtProdutos->bind_param(
    "iiiss",
    $empresaId,
    $empresaId,
    $empresaId,
    $inicioSql,
    $fimSql
);

$stmtProdutos->execute();

$resProdutos = $stmtProdutos->get_result();

$produtosVendidos = [];

while($row = $resProdutos->fetch_assoc()){
    $produtosVendidos[] = $row;
}

$stmtProdutos->close();

/*
|--------------------------------------------------------------------------
| PEDIDOS DO PERÍODO
|--------------------------------------------------------------------------
*/

$temColunaFormaPagamento = false;

$checkFormaPagamento = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'forma_pagamento'");

if($checkFormaPagamento instanceof mysqli_result){
    $temColunaFormaPagamento = $checkFormaPagamento->num_rows > 0;
    $checkFormaPagamento->free();
}

$selectFormaPagamento = $temColunaFormaPagamento
    ? "forma_pagamento,"
    : "NULL AS forma_pagamento,";

$temColunaTotalAntecipado = false;

$checkTotalAntecipado = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'total_antecipado'");

if($checkTotalAntecipado instanceof mysqli_result){
    $temColunaTotalAntecipado = $checkTotalAntecipado->num_rows > 0;
    $checkTotalAntecipado->free();
}

$selectTotalAntecipado = $temColunaTotalAntecipado
    ? "total_antecipado,"
    : "0 AS total_antecipado,";

$stmtPedidos = $conn->prepare("
    SELECT
        id,
        numero_pedido,
        mesa,
        cliente,
        status,
        {$selectFormaPagamento}
        {$selectTotalAntecipado}
        {$selectServico10Relatorio}
        subtotal,
        total_adicional,
        total,
        {$totalCobravelExprRelatorio} AS total_cobravel,
        quantidade_itens,
        created_at,
        updated_at
    FROM pedidos
    WHERE empresa_id = ?
    AND created_at BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT 100
");

if(!$stmtPedidos){
    die("Erro pedidos: " . h($conn->error));
}

$stmtPedidos->bind_param(
    "iss",
    $empresaId,
    $inicioSql,
    $fimSql
);

$stmtPedidos->execute();

$resPedidos = $stmtPedidos->get_result();

$pedidos = [];

while($row = $resPedidos->fetch_assoc()){
    $pedidos[] = $row;
}

$stmtPedidos->close();

/*
|--------------------------------------------------------------------------
| CLIENTES A PRAZO / CONTAS A RECEBER
|--------------------------------------------------------------------------
*/

$temColunaPrazoStatus = false;
$temColunaPrazoQuitadoEm = false;

$checkPrazoStatus = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'prazo_status'");

if($checkPrazoStatus instanceof mysqli_result){
    $temColunaPrazoStatus = $checkPrazoStatus->num_rows > 0;
    $checkPrazoStatus->free();
}

$checkPrazoQuitado = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'prazo_quitado_em'");

if($checkPrazoQuitado instanceof mysqli_result){
    $temColunaPrazoQuitadoEm = $checkPrazoQuitado->num_rows > 0;
    $checkPrazoQuitado->free();
}

$contasPrazo = [];
$totalPrazoAberto = 0;
$totalPrazoQuitado = 0;
$qtdPrazoAberto = 0;
$qtdPrazoQuitado = 0;

$saldoPrazoExpr = $temColunaTotalAntecipado
    ? "GREATEST({$totalCobravelExprRelatorio} - COALESCE(total_antecipado, 0), 0)"
    : $totalCobravelExprRelatorio;

$selectPrazoAntecipado = $temColunaTotalAntecipado
    ? "COALESCE(total_antecipado, 0) AS total_antecipado,"
    : "0 AS total_antecipado,";

if($temColunaFormaPagamento && $temColunaPrazoStatus && $temColunaPrazoQuitadoEm){

    $stmtPrazoResumo = $conn->prepare("
        SELECT
            SUM(CASE WHEN prazo_status = 'aberto' THEN {$saldoPrazoExpr} ELSE 0 END) AS total_aberto,
            SUM(CASE WHEN prazo_status = 'quitado' THEN {$saldoPrazoExpr} ELSE 0 END) AS total_quitado,
            SUM(CASE WHEN prazo_status = 'aberto' THEN 1 ELSE 0 END) AS qtd_aberto,
            SUM(CASE WHEN prazo_status = 'quitado' THEN 1 ELSE 0 END) AS qtd_quitado
        FROM pedidos
        WHERE empresa_id = ?
        AND status = 'pago'
        AND forma_pagamento = 'prazo'
        AND EXISTS (
        SELECT 1
        FROM caixa_movimentos cm_prazo
        WHERE cm_prazo.pedido_id = pedidos.id
          AND cm_prazo.empresa_id = pedidos.empresa_id
          AND cm_prazo.tipo = 'venda_prazo'
          AND cm_prazo.created_at BETWEEN ? AND ?
    )
    ");

    if($stmtPrazoResumo){
        $stmtPrazoResumo->bind_param('iss', $empresaId, $inicioSql, $fimSql);
        $stmtPrazoResumo->execute();
        $resPrazoResumo = $stmtPrazoResumo->get_result();
        $prazoResumo = $resPrazoResumo->fetch_assoc() ?: [];
        $stmtPrazoResumo->close();

        $totalPrazoAberto = (float)($prazoResumo['total_aberto'] ?? 0);
        $totalPrazoQuitado = (float)($prazoResumo['total_quitado'] ?? 0);
        $qtdPrazoAberto = (int)($prazoResumo['qtd_aberto'] ?? 0);
        $qtdPrazoQuitado = (int)($prazoResumo['qtd_quitado'] ?? 0);
    }

    $stmtPrazo = $conn->prepare("
        SELECT
            id,
            numero_pedido,
            mesa,
            cliente,
            total,
            {$totalCobravelExprRelatorio} AS total_cobravel,
            {$selectPrazoAntecipado}
            {$saldoPrazoExpr} AS saldo_prazo,
            quantidade_itens,
            created_at,
            prazo_status,
            prazo_quitado_em
        FROM pedidos
        WHERE empresa_id = ?
        AND status = 'pago'
        AND forma_pagamento = 'prazo'
        AND EXISTS (
        SELECT 1
        FROM caixa_movimentos cm_prazo
        WHERE cm_prazo.pedido_id = pedidos.id
          AND cm_prazo.empresa_id = pedidos.empresa_id
          AND cm_prazo.tipo = 'venda_prazo'
          AND cm_prazo.created_at BETWEEN ? AND ?
    )
        ORDER BY
            CASE WHEN prazo_status = 'aberto' THEN 0 ELSE 1 END ASC,
            created_at DESC
        LIMIT 100
    ");

    if($stmtPrazo){
        $stmtPrazo->bind_param('iss', $empresaId, $inicioSql, $fimSql);
        $stmtPrazo->execute();
        $resPrazo = $stmtPrazo->get_result();

        while($row = $resPrazo->fetch_assoc()){
            $contasPrazo[] = $row;
        }

        $stmtPrazo->close();
    }
}


require_once __DIR__ . '/inc/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Relatórios - Comandaria Express</title>

<?= ce_og_tags('Relatórios - Comandaria Express', 'Relatórios de vendas e desempenho do Comandaria Express') ?>
<link rel="icon" href="images/comandaria_logo.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/financeiro.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/staff.css?v=2">
</head>

<body class="cd-staff finance-page finance-relatorios">
<?php $staffPage = 'relatorios.php'; require __DIR__ . '/includes/staff_nav.php'; ?>

<main id="conteudo" class="finance-workspace">
    <header class="finance-heading">
        <h1>Relatórios</h1>
        <p>Acompanhe as vendas, o desempenho dos produtos e os pagamentos.</p>
    </header>

    <div class="card-box finance-filters">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md">
                <label for="periodo-inicio" class="form-label">
                    Data inicial
                </label>

                <input
                    type="date"
                    name="inicio"
                    id="periodo-inicio"
                    class="form-control"
                    value="<?= h($dataInicio) ?>"
                >
            </div>

            <div class="col-md">
                <label for="periodo-fim" class="form-label">
                    Data final
                </label>

                <input
                    type="date"
                    name="fim"
                    id="periodo-fim"
                    class="form-control"
                    value="<?= h($dataFim) ?>"
                >
            </div>

            <div class="col-md-3 col-xl-2">
                <button type="submit" class="btn btn-success w-100">
                    Filtrar período
                </button>
            </div>
        </form>
    </div>

    <div class="grid-resumo mb-3">

        <div class="card-kpi green finance-kpi-primary">
            <div class="label">
                Faturamento pago
            </div>

            <div class="value">
                R$ <?= dinheiro($faturamento) ?>
            </div>
        </div>

        <div class="card-kpi blue">
            <div class="label">
                Ticket médio
            </div>

            <div class="value">
                R$ <?= dinheiro($ticketMedio) ?>
            </div>
        </div>

        <div class="card-kpi yellow">
            <div class="label">
                Pedidos pagos
            </div>

            <div class="value">
                <?= $pedidosPagos ?>
            </div>
        </div>

        <div class="card-kpi red">
            <div class="label">
                Cancelados
            </div>

            <div class="value">
                <?= $pedidosCancelados ?>
            </div>
        </div>

    </div>

    <div class="grid-resumo mb-3">

        <div class="card-kpi">
            <div class="label">
                Total de pedidos
            </div>

            <div class="value">
                <?= $totalPedidos ?>
            </div>
        </div>

        <div class="card-kpi">
            <div class="label">
                Pendentes
            </div>

            <div class="value">
                <?= $pedidosPendentes ?>
            </div>
        </div>

        <div class="card-kpi">
            <div class="label">
                Prontos
            </div>

            <div class="value">
                <?= $pedidosProntos ?>
            </div>
        </div>

        <div class="card-kpi">
            <div class="label">
                Adicionais vendidos
            </div>

            <div class="value">
                R$ <?= dinheiro($totalAdicionais) ?>
            </div>
        </div>

    </div>
    <div class="card-box">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
            <div>
                <h2 class="finance-section-title mb-0">Vendas a prazo</h2>

                <div class="text-muted small">
                    Resumo do período selecionado.
                </div>
            </div>

            <a href="controle_prazo.php" class="btn btn-outline-dark btn-sm">
                Gerenciar contas a prazo
            </a>
        </div>

        <?php if(!$estruturaPrazoRelatorioOk): ?>
            <div class="alert alert-warning mb-3">
                A estrutura financeira de prazo não está disponível para este relatório.
            </div>
        <?php endif; ?>

        <div class="grid-resumo grid-resumo-compacto">
            <div class="card-kpi blue">
                <div class="label">
                    Contas lançadas a prazo
                </div>

                <div class="value">
                    <?= $vendasPrazoQtd ?>
                </div>
            </div>

            <div class="card-kpi green">
                <div class="label">
                    Total vendido a prazo
                </div>

                <div class="value">
                    R$ <?= dinheiro($vendasPrazoTotal) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-box">
        <h2 class="finance-section-title mb-3">Produtos mais vendidos</h2>

        <div class="table-responsive">
            <table class="table table-hover align-middle tabela-produtos">
                <thead>
                    <tr>
                        <th scope="col">Produto</th>
                        <th scope="col">Categoria</th>
                        <th scope="col">Qtd</th>
                        <th scope="col">Subtotal</th>
                        <th scope="col">Adicionais</th>
                        <th scope="col">Total</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if(empty($produtosVendidos)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Nenhum produto vendido no período.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach($produtosVendidos as $p): ?>
                        <tr>
                            <td>
                                <strong><?= h($p['produto_nome']) ?></strong>

                                <div class="small text-muted">
                                    ID #<?= (int)$p['produto_id'] ?>
                                </div>
                            </td>

                            <td>
                                <?= h($p['categoria_nome'] ?? 'Sem categoria') ?>
                            </td>

                            <td>
                                <?= (int)$p['quantidade_vendida'] ?>
                            </td>

                            <td>
                                R$ <?= dinheiro($p['subtotal']) ?>
                            </td>

                            <td>
                                R$ <?= dinheiro($p['total_adicional']) ?>
                            </td>

                            <td>
                                <strong>
                                    R$ <?= dinheiro($p['total_vendido']) ?>
                                </strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-card">
            <?php if(empty($produtosVendidos)): ?>
                <div class="text-center text-muted py-4">
                    Nenhum produto vendido no período.
                </div>
            <?php endif; ?>

            <?php foreach($produtosVendidos as $p): ?>
                <div class="produto-card">
                    <div class="nome">
                        <?= h($p['produto_nome']) ?>
                    </div>

                    <div class="meta">
                        <?= h($p['categoria_nome'] ?? 'Sem categoria') ?>
                    </div>

                    <div class="meta">
                        Qtd: <?= (int)$p['quantidade_vendida'] ?>
                        • Total: R$ <?= dinheiro($p['total_vendido']) ?>
                    </div>

                    <div class="meta">
                        Subtotal: R$ <?= dinheiro($p['subtotal']) ?>
                        • Adicionais: R$ <?= dinheiro($p['total_adicional']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>


    <div class="card-box">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="finance-section-title mb-0">Clientes a prazo</h2>
            <small class="text-muted">
                Contas fechadas como <strong>A prazo</strong> no período selecionado.
            </small>
        </div>

        <?php if(!$temColunaFormaPagamento || !$temColunaPrazoStatus || !$temColunaPrazoQuitadoEm): ?>
            <div class="alert alert-warning mb-0">
                A estrutura de contas a prazo ainda não está disponível no banco deste ambiente.
            </div>
        <?php else: ?>

            <div class="grid-resumo grid-resumo-compacto mb-3">
                <div class="card-kpi card-prazo-resumo">
                    <div class="label">A prazo em aberto</div>
                    <div class="value">R$ <?= dinheiro($totalPrazoAberto) ?></div>
                    <small><?= (int)$qtdPrazoAberto ?> conta(s)</small>
                </div>

                <div class="card-kpi green">
                    <div class="label">A prazo quitado</div>
                    <div class="value">R$ <?= dinheiro($totalPrazoQuitado) ?></div>
                    <small><?= (int)$qtdPrazoQuitado ?> conta(s)</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle tabela-prazo">
                    <thead>
                        <tr>
                            <th scope="col">Pedido</th>
                            <th scope="col">Cliente/Mesa</th>
                            <th scope="col">Status prazo</th>
                            <th scope="col">Itens</th>
                            <th scope="col">Saldo a prazo</th>
                            <th scope="col">Fechado em</th>
                            <th scope="col">Quitado em</th>
                            <th scope="col" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($contasPrazo)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    Nenhuma conta a prazo no período.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach($contasPrazo as $p): ?>
                            <?php
                                $prazoStatus = $p['prazo_status'] ?: 'aberto';
                                $badgePrazo = $prazoStatus === 'quitado' ? 'success' : 'warning text-dark';
                            ?>
                            <tr data-prazo-row="<?= (int)$p['id'] ?>">
                                <td><strong>#<?= (int)($p['numero_pedido'] ?? $p['id']) ?></strong></td>
                                <td>
                                    <?php if(!empty($p['mesa'])): ?>
                                        Mesa <?= (int)$p['mesa'] ?>
                                    <?php else: ?>
                                        Cliente <?= h($p['cliente'] ?: '-') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $badgePrazo ?> badge-pill">
                                        <?= h(prazoStatusLabel($prazoStatus)) ?>
                                    </span>
                                </td>
                                <td><?= (int)$p['quantidade_itens'] ?></td>
                                <td class="right"><strong>R$ <?= dinheiro($p['saldo_prazo'] ?? $p['total']) ?></strong></td>
                                <td><?= h(dataBr($p['created_at'])) ?></td>
                                <td class="text-center"><?= h(dataBr($p['prazo_quitado_em'])) ?></td>
                                <td>
                                    <div class="prazo-actions">
                                        <?php if($prazoStatus === 'quitado'): ?>
                                            <button class="btn btn-sm btn-outline-warning" data-prazo-action="reabrir" data-pedido-id="<?= (int)$p['id'] ?>">
                                                Reabrir débito
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-success" data-prazo-action="quitar" data-pedido-id="<?= (int)$p['id'] ?>">
                                                Marcar quitado
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mobile-card">
                <?php if(empty($contasPrazo)): ?>
                    <div class="text-center text-muted py-4">
                        Nenhuma conta a prazo no período.
                    </div>
                <?php endif; ?>

                <?php foreach($contasPrazo as $p): ?>
                    <?php $prazoStatus = $p['prazo_status'] ?: 'aberto'; ?>
                    <div class="pedido-card" data-prazo-row="<?= (int)$p['id'] ?>">
                        <div class="nome">Pedido #<?= (int)($p['numero_pedido'] ?? $p['id']) ?></div>
                        <div class="meta">
                            <?php if(!empty($p['mesa'])): ?>
                                Mesa <?= (int)$p['mesa'] ?>
                            <?php else: ?>
                                Cliente <?= h($p['cliente'] ?: '-') ?>
                            <?php endif; ?>
                            • <?= h(prazoStatusLabel($prazoStatus)) ?>
                        </div>
                        <div class="meta">
                            Itens: <?= (int)$p['quantidade_itens'] ?>
                            • Saldo a prazo: R$ <?= dinheiro($p['saldo_prazo'] ?? $p['total']) ?>
                        </div>
                        <div class="meta">
                            Fechado: <?= h(dataBr($p['created_at'])) ?>
                            <?php if(!empty($p['prazo_quitado_em'])): ?>
                                • Quitado: <?= h(dataBr($p['prazo_quitado_em'])) ?>
                            <?php endif; ?>
                        </div>
                        <div class="prazo-actions mt-2">
                            <?php if($prazoStatus === 'quitado'): ?>
                                <button class="btn btn-sm btn-outline-warning" data-prazo-action="reabrir" data-pedido-id="<?= (int)$p['id'] ?>">
                                    Reabrir débito
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-success" data-prazo-action="quitar" data-pedido-id="<?= (int)$p['id'] ?>">
                                    Marcar quitado
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-box">
        <h2 class="finance-section-title mb-3">Pedidos do período</h2>

        <div class="table-responsive">
            <table class="table table-hover align-middle tabela-pedidos">
                <thead>
                    <tr>
                        <th scope="col">Pedido</th>
                        <th scope="col">Origem</th>
                        <th scope="col">Status</th>
                        <th scope="col">Pagamento</th>
                        <th scope="col">Itens</th>
                        <th scope="col">Subtotal</th>
                        <th scope="col">Adicionais</th>
                        <th scope="col">Antecipado</th>
                        <th scope="col">Total</th>
                        <th scope="col">Data</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if(empty($pedidos)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                Nenhum pedido encontrado no período.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach($pedidos as $p): ?>
                        <?php
                            $status = $p['status'];
                            $badge = 'secondary';

                            if($status === 'pago'){
                                $badge = 'success';
                            } elseif($status === 'cancelado'){
                                $badge = 'danger';
                            } elseif($status === 'pendente'){
                                $badge = 'warning text-dark';
                            } elseif($status === 'pronto'){
                                $badge = 'primary';
                            }
                        ?>

                        <tr>
                            <td>
                                <strong>#<?= (int)($p['numero_pedido'] ?? $p['id']) ?></strong>
                            </td>

                            <td>
                                <?php if(!empty($p['mesa'])): ?>
                                    Mesa <?= (int)$p['mesa'] ?>
                                <?php else: ?>
                                    Cliente <?= h($p['cliente'] ?: '-') ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge bg-<?= $badge ?> badge-pill">
                                    <?= h($status) ?>
                                </span>
                            </td>

                            <td>
                                <?= h(formaPagamentoLabel($p['forma_pagamento'] ?? null)) ?>
                            </td>

                            <td>
                                <?= (int)$p['quantidade_itens'] ?>
                            </td>

                            <td>
                                R$ <?= dinheiro($p['subtotal']) ?>
                            </td>

                            <td>
                                R$ <?= dinheiro($p['total_adicional']) ?>
                            </td>

                            <td>
                                R$ <?= dinheiro($p['total_antecipado'] ?? 0) ?>
                            </td>

                            <td class="right">
                                <strong>
                                    R$ <?= dinheiro($p['total_cobravel'] ?? $p['total']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= h(dataBr($p['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-card">
            <?php if(empty($pedidos)): ?>
                <div class="text-center text-muted py-4">
                    Nenhum pedido encontrado no período.
                </div>
            <?php endif; ?>

            <?php foreach($pedidos as $p): ?>
                <div class="pedido-card">
                    <div class="nome">
                        Pedido #<?= (int)($p['numero_pedido'] ?? $p['id']) ?>
                    </div>

                    <div class="meta">
                        <?php if(!empty($p['mesa'])): ?>
                            Mesa <?= (int)$p['mesa'] ?>
                        <?php else: ?>
                            Cliente <?= h($p['cliente'] ?: '-') ?>
                        <?php endif; ?>
                        • <?= h($p['status']) ?>
                    </div>

                    <div class="meta">
                        Pagamento: <?= h(formaPagamentoLabel($p['forma_pagamento'] ?? null)) ?>
                    </div>

                    <div class="meta">
                        Itens: <?= (int)$p['quantidade_itens'] ?>
                        • Total: R$ <?= dinheiro($p['total_cobravel'] ?? $p['total']) ?>
                        <?php if((float)($p['total_antecipado'] ?? 0) > 0): ?>
                            • Antecipado: R$ <?= dinheiro($p['total_antecipado']) ?>
                        <?php endif; ?>
                    </div>

                    <div class="meta">
                        <?= h(dataBr($p['created_at'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<script>
document.addEventListener('click', async function(e){
    const btn = e.target.closest('[data-prazo-action]');

    if(!btn){
        return;
    }

    const pedidoId = Number(btn.dataset.pedidoId || 0);
    const acao = btn.dataset.prazoAction;

    if(!pedidoId || !['quitar','reabrir'].includes(acao)){
        return;
    }

    const pergunta = acao === 'quitar'
        ? `Marcar o pedido #${pedidoId} como quitado?`
        : `Reabrir o débito do pedido #${pedidoId}?`;

    if(!confirm(pergunta)){
        return;
    }

    btn.disabled = true;
    const textoOriginal = btn.textContent;
    btn.textContent = 'Salvando...';

    try {
        const res = await fetch('atualizar_prazo_pedido.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                pedido_id: pedidoId,
                acao: acao
            })
        });

        const data = await res.json();

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao atualizar conta a prazo');
        }

        location.reload();

    } catch(err){
        alert(err.message || 'Erro ao atualizar conta a prazo');
        btn.disabled = false;
        btn.textContent = textoOriginal;
    }
});
</script>

</body>
</html>

<?php
$conn->close();
?>