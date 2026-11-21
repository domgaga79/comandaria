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
    WHERE created_at BETWEEN ? AND ?
");

if(!$stmtResumo){
    die("Erro resumo: " . h($conn->error));
}

$stmtResumo->bind_param(
    "ss",
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
        WHERE cm.tipo = 'venda_prazo'
        AND p.forma_pagamento = 'prazo'
        AND cm.created_at BETWEEN ? AND ?
    ");

    if($stmtPrazo){
        $stmtPrazo->bind_param('ss', $inicioSql, $fimSql);

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

    WHERE p.status = 'pago'
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
    "ss",
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
    WHERE created_at BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT 100
");

if(!$stmtPedidos){
    die("Erro pedidos: " . h($conn->error));
}

$stmtPedidos->bind_param(
    "ss",
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
        WHERE status = 'pago'
        AND forma_pagamento = 'prazo'
        AND EXISTS (
        SELECT 1
        FROM caixa_movimentos cm_prazo
        WHERE cm_prazo.pedido_id = pedidos.id
          AND cm_prazo.tipo = 'venda_prazo'
          AND cm_prazo.created_at BETWEEN ? AND ?
    )
    ");

    if($stmtPrazoResumo){
        $stmtPrazoResumo->bind_param('ss', $inicioSql, $fimSql);
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
        WHERE status = 'pago'
        AND forma_pagamento = 'prazo'
        AND EXISTS (
        SELECT 1
        FROM caixa_movimentos cm_prazo
        WHERE cm_prazo.pedido_id = pedidos.id
          AND cm_prazo.tipo = 'venda_prazo'
          AND cm_prazo.created_at BETWEEN ? AND ?
    )
        ORDER BY
            CASE WHEN prazo_status = 'aberto' THEN 0 ELSE 1 END ASC,
            created_at DESC
        LIMIT 100
    ");

    if($stmtPrazo){
        $stmtPrazo->bind_param('ss', $inicioSql, $fimSql);
        $stmtPrazo->execute();
        $resPrazo = $stmtPrazo->get_result();

        while($row = $resPrazo->fetch_assoc()){
            $contasPrazo[] = $row;
        }

        $stmtPrazo->close();
    }
}

$nomeUsuario = $_SESSION['user_nome'] ?? 'Admin';

require_once __DIR__ . '/inc/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Relatórios - Comandaria Express</title>

<?= ce_og_tags('Relatórios - Comandaria Express', 'Relatórios de vendas e desempenho do Comandaria Express') ?>
<link rel="icon" href="images/topo_comandaria_shake.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #111;
    color: #fff;
    font-family: Arial, sans-serif;
    min-height: 100vh;
}

.header {
    text-align: center;
    padding: 18px 10px 8px;
}

.header img {
    max-width: 430px;
    width: 100%;
}

.userbox {
    text-align: center;
    margin-bottom: 12px;
}

.userbox .inner {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 999px;
    background: rgba(255,255,255,.06);
    font-size: 14px;
}

.container-main {
    max-width: 1180px;
    margin: 0 auto;
    padding: 10px;
}

.card-box {
    background: #fff;
    color: #111;
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 14px;
    box-shadow: 0 4px 16px rgba(0,0,0,.3);
}

.grid-resumo {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 12px;
}

@media(min-width: 600px) {
    .grid-resumo {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(min-width: 900px) {
    .grid-resumo {
        grid-template-columns: repeat(4, 1fr);
    }
}

.card-kpi {
    background: #f8f9fa;
    color: #111;
    border-radius: 14px;
    padding: 16px;
    border-left: 5px solid #111;
}

.card-kpi .label {
    font-size: 13px;
    color: #666;
    font-weight: 700;
}

.card-kpi .value {
    font-size: 25px;
    font-weight: 900;
    margin-top: 4px;
    color: #111 !important;
}

.card-kpi.yellow {
    border-color: #ffc107;
}

.card-kpi.green {
    border-color: #198754;
}

.card-kpi.blue {
    border-color: #0d6efd;
}

.card-kpi.red {
    border-color: #dc3545;
}

.badge-pill {
    border-radius: 999px;
    padding: 5px 9px;
    font-size: 11px;
}

.table {
    vertical-align: middle;
}

.mobile-card {
    display: none;
}

@media(max-width: 768px) {
    .table-responsive {
        display: none;
    }

    .mobile-card {
        display: block;
    }

    .pedido-card,
    .produto-card {
        background: #fff;
        color: #111;
        border-radius: 14px;
        padding: 13px;
        margin-bottom: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,.25);
    }

    .pedido-card .nome,
    .produto-card .nome {
        font-weight: 800;
        font-size: 16px;
    }

    .pedido-card .meta,
    .produto-card .meta {
        color: #555;
        font-size: 13px;
        margin-top: 3px;
    }
}

.logout {
    color: #ffc107;
    text-decoration: none;
}

.logout:hover {
    color: #ffda6a;
}

/* Correção: textos escuros em fundos claros dos relatórios */
.card-box,
.card-box h1,
.card-box h2,
.card-box h3,
.card-box h4,
.card-box h5,
.card-box h6,
.card-box label,
.card-box .form-label,
.card-box .table,
.card-box .table td,
.card-box .table th,
.card-box .table tbody,
.card-box .table tbody td,
.card-box .table tbody th {
    color: #111 !important;
}

.card-kpi,
.card-kpi .value {
    color: #111 !important;
}

.card-kpi .label {
    color: #666 !important;
}

.card-box .text-muted,
.card-box small,
.card-box .small {
    color: #555 !important;
}

.card-box .badge.bg-success,
.card-box .badge.bg-danger,
.card-box .badge.bg-primary,
.card-box .badge.bg-dark,
.card-box .badge.bg-secondary {
    color: #fff !important;
}

.card-box .badge.bg-warning,
.card-box .badge.bg-light {
    color: #111 !important;
}


/* ================================
   ALINHAMENTO DE VALORES À DIREITA
   Mantém todos os dados e muda apenas visual
================================ */
.card-kpi .value {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

/* Produtos mais vendidos:
   3=Qtd, 4=Subtotal, 5=Adicionais, 6=Total */
.tabela-produtos th:nth-child(3),
.tabela-produtos td:nth-child(3),
.tabela-produtos th:nth-child(4),
.tabela-produtos td:nth-child(4),
.tabela-produtos th:nth-child(5),
.tabela-produtos td:nth-child(5),
.tabela-produtos th:nth-child(6),
.tabela-produtos td:nth-child(6) {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

/* Pedidos do período:
   5=Itens, 6=Subtotal, 7=Adicionais, 8=Total */
.tabela-pedidos th:nth-child(5),
.tabela-pedidos td:nth-child(5),
.tabela-pedidos th:nth-child(6),
.tabela-pedidos td:nth-child(6),
.tabela-pedidos th:nth-child(7),
.tabela-pedidos td:nth-child(7),
.tabela-pedidos th:nth-child(8),
.tabela-pedidos td:nth-child(8) {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

@media(max-width: 768px) {
    .produto-card .meta,
    .pedido-card .meta {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
}


.card-prazo-resumo {
    border-left: 5px solid #fd7e14;
}

.prazo-actions {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    flex-wrap: wrap;
}

@media(max-width: 768px) {
    .prazo-actions {
        justify-content: stretch;
    }

    .prazo-actions .btn {
        width: 100%;
    }
}
.right {
    text-align: right;
}
img {
  animation: pulsar 5s infinite;
}

@keyframes pulsar {
  0% {
    transform: scale(1);
  }
  80% {
    transform: scale(1);
  }
  90% {
    transform: scale(0.95);
  }
  100% {
    transform: scale(1);
  }
}
</style>
</head>

<body>

<div class="header">
    <img src="images/topo_comandaria_shake.png" alt="Comandaria Express">
</div>

<div class="userbox">
    <div class="inner">
        <span>
            Bem-vindo,
            <strong><?= h($nomeUsuario) ?></strong>
        </span>

        <a href="dashboard.php" class="logout">
            dashboard
        </a>

        <a href="logout.php" class="logout">
            sair
        </a>
    </div>
</div>

<div class="container-main">

    <div class="card-box">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
            <div>
                <h3 class="mb-0">📊 Relatórios</h3>

                <div class="text-muted small">
                    Vendas, comandas, ticket médio e produtos mais vendidos.
                </div>
            </div>
        </div>

        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <label class="form-label">
                    Data inicial
                </label>

                <input
                    type="date"
                    name="inicio"
                    class="form-control"
                    value="<?= h($dataInicio) ?>"
                >
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    Data final
                </label>

                <input
                    type="date"
                    name="fim"
                    class="form-control"
                    value="<?= h($dataFim) ?>"
                >
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-dark w-100">
                    Filtrar período
                </button>
            </div>
        </form>
    </div>

    <div class="grid-resumo mb-3">

        <div class="card-kpi green">
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
                <h4 class="mb-0">💳 Vendas a prazo</h4>

                <div class="text-muted small">
                    Resumo gerencial do período filtrado. O controle detalhado, impressão e baixa ficam em Contas a Prazo.
                </div>
            </div>

            <a href="controle_prazo.php" class="btn btn-outline-dark btn-sm">
                Abrir controle
            </a>
        </div>

        <?php if(!$estruturaPrazoRelatorioOk): ?>
            <div class="alert alert-warning mb-3">
                A estrutura financeira de prazo não está disponível para este relatório.
            </div>
        <?php endif; ?>

        <div class="grid-resumo">
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
        <h4 class="mb-3">🏆 Produtos mais vendidos</h4>

        <div class="table-responsive">
            <table class="table table-hover align-middle tabela-produtos">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Qtd</th>
                        <th>Subtotal</th>
                        <th>Adicionais</th>
                        <th>Total</th>
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
            <h4 class="mb-0">📒 Clientes a prazo</h4>
            <small class="text-muted">
                Contas fechadas como <strong>A prazo</strong> no período selecionado.
            </small>
        </div>

        <?php if(!$temColunaFormaPagamento || !$temColunaPrazoStatus || !$temColunaPrazoQuitadoEm): ?>
            <div class="alert alert-warning mb-0">
                A estrutura de contas a prazo ainda não está disponível no banco deste ambiente.
            </div>
        <?php else: ?>

            <div class="grid-resumo mb-3">
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
                            <th>Pedido</th>
                            <th>Cliente/Mesa</th>
                            <th>Status prazo</th>
                            <th>Itens</th>
                            <th>Saldo a prazo</th>
                            <th>Fechado em</th>
                            <th>Quitado em</th>
                            <th class="text-end">Ações</th>
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
                                <td><strong>#<?= (int)$p['id'] ?></strong></td>
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
                        <div class="nome">Pedido #<?= (int)$p['id'] ?></div>
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
        <h4 class="mb-3">🧾 Pedidos do período</h4>

        <div class="table-responsive">
            <table class="table table-hover align-middle tabela-pedidos">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Origem</th>
                        <th>Status</th>
                        <th>Pagamento</th>
                        <th>Itens</th>
                        <th>Subtotal</th>
                        <th>Adicionais</th>
                        <th>Antecipado</th>
                        <th>Total</th>
                        <th>Data</th>
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
                                <strong>#<?= (int)$p['id'] ?></strong>
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
                        Pedido #<?= (int)$p['id'] ?>
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

</div>

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