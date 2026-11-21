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

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dinheiroImpressaoPrazo($value): string {
    return number_format((float)$value, 2, ',', '.');
}

function dataBrImpressaoPrazo($value): string {

    if(empty($value)){
        return '-';
    }

    $timestamp = strtotime((string)$value);

    if(!$timestamp){
        return '-';
    }

    return date('d/m/Y H:i', $timestamp);
}

function prazoPagoImpressao($status): bool {

    $status = strtolower(trim((string)$status));

    return in_array($status, ['pago', 'recebido', 'quitado'], true);
}

$pedidoId = (int)($_GET['pedido_id'] ?? 0);

if($pedidoId <= 0){
    die('Pedido inválido.');
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
$selectServico10Prazo = $temEstruturaServico10Prazo
    ? "p.servico_10_ativo, p.taxa_servico_10,"
    : "0 AS servico_10_ativo, 0 AS taxa_servico_10,";

$stmtPedido = $conn->prepare("
    SELECT
        p.id,
        p.mesa,
        p.cliente,
        p.status,
        p.forma_pagamento,
        p.prazo_status,
        p.subtotal,
        p.total_adicional,
        p.total,
        {$selectServico10Prazo}
        p.quantidade_itens,
        p.created_at,
        p.updated_at,
        vp.lancado_em
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
    WHERE p.id = ?
    AND p.forma_pagamento = 'prazo'
    LIMIT 1
");

if(!$stmtPedido){
    die('Erro ao preparar conta a prazo: ' . h($conn->error));
}

$stmtPedido->bind_param('i', $pedidoId);

if(!$stmtPedido->execute()){
    die('Erro ao consultar conta a prazo: ' . h($stmtPedido->error));
}

$resPedido = $stmtPedido->get_result();
$pedido = $resPedido->fetch_assoc();
$stmtPedido->close();

if(!$pedido){
    die('Conta a prazo não encontrada.');
}

$stmtItens = $conn->prepare("
    SELECT
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
    WHERE pi.pedido_id = ?
    ORDER BY pi.id ASC
");

if(!$stmtItens){
    die('Erro ao preparar itens: ' . h($conn->error));
}

$stmtItens->bind_param('i', $pedidoId);

if(!$stmtItens->execute()){
    die('Erro ao consultar itens: ' . h($stmtItens->error));
}

$resItens = $stmtItens->get_result();

$itens = [];

while($item = $resItens->fetch_assoc()){
    $itens[] = $item;
}

$stmtItens->close();

$origem = !empty($pedido['cliente'])
    ? 'Cliente: ' . trim((string)$pedido['cliente'])
    : (!empty($pedido['mesa']) ? 'Mesa: ' . (int)$pedido['mesa'] : 'Sem identificação');

$statusPrazo = prazoPagoImpressao($pedido['prazo_status'] ?? '')
    ? 'PAGO'
    : 'EM ABERTO';

$dataLancamento = $pedido['lancado_em']
    ?: ($pedido['updated_at'] ?: $pedido['created_at']);

$servico10AtivoPrazo = !empty($pedido['servico_10_ativo']);
$taxaServico10Prazo = $servico10AtivoPrazo
    ? round((float)($pedido['taxa_servico_10'] ?? ((float)($pedido['total'] ?? 0) * 0.10)), 2)
    : 0.00;
$totalFinalPrazo = round((float)($pedido['total'] ?? 0) + $taxaServico10Prazo, 2);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Imprimir Conta a Prazo #<?= (int)$pedido['id'] ?></title>

<style>
@page {
    size: 58mm auto;
    margin: 3mm;
}

* {
    box-sizing: border-box;
}

body {
    width: 58mm;
    margin: 0;
    padding: 0;
    color: #000;
    background: #fff;
    font-family: Arial, sans-serif;
    font-size: 11px;
    line-height: 1.35;
}

.topo {
    text-align: center;
    border-bottom: 1px dashed #000;
    padding-bottom: 7px;
    margin-bottom: 7px;
}

.topo strong {
    display: block;
    font-size: 15px;
}

.titulo {
    font-size: 13px;
    font-weight: 900;
    margin-top: 4px;
}

.info {
    margin-bottom: 7px;
}

.info div {
    margin-bottom: 2px;
}

.item {
    border-top: 1px dashed #999;
    padding: 5px 0;
}

.item:first-of-type {
    border-top: 1px dashed #000;
}

.item-nome {
    font-weight: 800;
}

.item-meta {
    font-size: 10px;
    margin-top: 2px;
}

.item-total {
    font-weight: 900;
    text-align: right;
    margin-top: 2px;
}

.totais {
    border-top: 1px dashed #000;
    padding-top: 7px;
    margin-top: 7px;
}

.linha {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 3px;
}

.linha.total {
    font-size: 14px;
    font-weight: 900;
    margin-top: 6px;
    border-top: 1px dashed #000;
    padding-top: 6px;
}

.rodape {
    border-top: 1px dashed #000;
    margin-top: 9px;
    padding-top: 8px;
    text-align: center;
    font-size: 10px;
}

.acoes {
    display: flex;
    gap: 8px;
    margin: 10px 0;
    width: 58mm;
}

.acoes button,
.acoes a {
    flex: 1;
    border: 0;
    border-radius: 7px;
    padding: 8px 6px;
    font-weight: 800;
    font-size: 11px;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
}

.acoes button {
    background: #111;
    color: #fff;
}

.acoes a {
    background: #ddd;
    color: #111;
}

@media print {
    .acoes {
        display: none !important;
    }

    body {
        width: 52mm;
    }
}
</style>
</head>
<body>

<div class="acoes">
    <button type="button" onclick="window.print()">
        Imprimir
    </button>

    <a href="controle_prazo.php">
        Voltar
    </a>
</div>

<div class="topo">
    <strong>Comandaria Express</strong>
    <div class="titulo">Conta a Prazo</div>
</div>

<div class="info">
    <div><strong>Pedido:</strong> #<?= (int)$pedido['id'] ?></div>
    <div><strong><?= h($origem) ?></strong></div>
    <div><strong>Status:</strong> <?= h($statusPrazo) ?></div>
    <div><strong>Lançamento:</strong> <?= h(dataBrImpressaoPrazo($dataLancamento)) ?></div>
</div>

<?php if(empty($itens)): ?>
    <div class="item">
        Nenhum item encontrado.
    </div>
<?php endif; ?>

<?php foreach($itens as $item): ?>
    <?php
        $qtd = (int)($item['quantidade'] ?? 0);
        $precoUnitario = (float)($item['preco_unitario'] ?? 0);
        $qtdAdicional = max(0, (int)($item['adicional'] ?? 0));
        $valorAdicionalUnitario = (float)($item['valor_adicional'] ?? 0);
        $valorAdicionalTotalUnitario = $qtdAdicional * $valorAdicionalUnitario;
        $valorUnitarioFinal = $precoUnitario + $valorAdicionalTotalUnitario;
        $totalItem = $valorUnitarioFinal * $qtd;
    ?>

    <div class="item">
        <div class="item-nome">
            <?= $qtd ?>× <?= h($item['produto_nome'] ?? 'Produto') ?>
        </div>

        <div class="item-meta">
            Unit.: R$ <?= dinheiroImpressaoPrazo($precoUnitario) ?>

            <?php if($qtdAdicional > 0 && $valorAdicionalUnitario > 0): ?>
                | Adicional: <?= $qtdAdicional ?>× R$ <?= dinheiroImpressaoPrazo($valorAdicionalUnitario) ?>
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

        <div class="item-total">
            R$ <?= dinheiroImpressaoPrazo($totalItem) ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="totais">
    <div class="linha">
        <span>Subtotal</span>
        <span>R$ <?= dinheiroImpressaoPrazo($pedido['subtotal'] ?? 0) ?></span>
    </div>

    <div class="linha">
        <span>Adicionais</span>
        <span>R$ <?= dinheiroImpressaoPrazo($pedido['total_adicional'] ?? 0) ?></span>
    </div>

    <div class="linha">
        <span>10% serviço</span>
        <span><?= $servico10AtivoPrazo ? 'R$ ' . dinheiroImpressaoPrazo($taxaServico10Prazo) : 'Não cobrado' ?></span>
    </div>

    <div class="linha total">
        <span>Total final</span>
        <span>R$ <?= dinheiroImpressaoPrazo($totalFinalPrazo) ?></span>
    </div>
</div>

<div class="rodape">
    Conta emitida para controle de pagamento a prazo.
</div>

<script>
window.addEventListener('load', function(){
    setTimeout(function(){
        window.print();
    }, 250);
});
</script>

</body>
</html>

<?php
if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
?>