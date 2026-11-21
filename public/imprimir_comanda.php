<?php
require __DIR__ . '/auth.php';
requireRole(['caixa','admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/conexao.php';
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../core/pedido_core.php';

$empresaId = tenantResolverEmpresaId(authEmpresaId());

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

$pedidoId = (int)(
    $_GET['pedido_id'] ??
    $_GET['id'] ??
    0
);

if($pedidoId <= 0){
    echo "Pedido inválido";
    exit;
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dinheiro($value): string {
    return number_format((float)$value, 2, ',', '.');
}

/*
|--------------------------------------------------------------------------
| Pedido
|--------------------------------------------------------------------------
*/
$temColunaFormaPagamento = false;

$checkFormaPagamento = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'forma_pagamento'");

if($checkFormaPagamento instanceof mysqli_result){
    $temColunaFormaPagamento = $checkFormaPagamento->num_rows > 0;
    $checkFormaPagamento->free();
}

$selectFormaPagamento = $temColunaFormaPagamento
    ? "p.forma_pagamento AS forma_pagamento,"
    : "NULL AS forma_pagamento,";

$temColunaTotalAntecipado = false;

$checkTotalAntecipado = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'total_antecipado'");

if($checkTotalAntecipado instanceof mysqli_result){
    $temColunaTotalAntecipado = $checkTotalAntecipado->num_rows > 0;
    $checkTotalAntecipado->free();
}

$selectTotalAntecipado = $temColunaTotalAntecipado
    ? "p.total_antecipado AS total_antecipado,"
    : "0 AS total_antecipado,";

$temColunaServico10Ativo = false;
$temColunaTaxaServico10 = false;

$checkServico10Ativo = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'servico_10_ativo'");

if($checkServico10Ativo instanceof mysqli_result){
    $temColunaServico10Ativo = $checkServico10Ativo->num_rows > 0;
    $checkServico10Ativo->free();
}

$checkTaxaServico10 = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'taxa_servico_10'");

if($checkTaxaServico10 instanceof mysqli_result){
    $temColunaTaxaServico10 = $checkTaxaServico10->num_rows > 0;
    $checkTaxaServico10->free();
}

$temEstruturaServico10 = $temColunaServico10Ativo && $temColunaTaxaServico10;

$selectServico10Ativo = $temEstruturaServico10
    ? "p.servico_10_ativo AS servico_10_ativo,"
    : "0 AS servico_10_ativo,";

$selectTaxaServico10 = $temEstruturaServico10
    ? "p.taxa_servico_10 AS taxa_servico_10,"
    : "0 AS taxa_servico_10,";

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.numero_pedido,
        p.mesa,
        p.cliente,
        p.status,
        p.created_at,
        p.updated_at,
        p.usuario_id,
        {$selectFormaPagamento}
        {$selectTotalAntecipado}
        {$selectServico10Ativo}
        {$selectTaxaServico10}
        u.nome AS atendente_nome,
        p.subtotal,
        p.total_adicional,
        p.total,
        p.quantidade_itens,
        p.public_token
    FROM pedidos p
    LEFT JOIN usuarios u
        ON u.id = p.usuario_id
    WHERE p.id = ?
    AND p.empresa_id = ?
    LIMIT 1
");

if(!$stmt){
    echo "Erro prepare pedido: " . h($conn->error);
    exit;
}

$stmt->bind_param("ii", $pedidoId, $empresaId);
$stmt->execute();

$res = $stmt->get_result();
$pedido = $res->fetch_assoc();

$stmt->close();

if(!$pedido){
    echo "Pedido não encontrado";
    exit;
}

/*
|--------------------------------------------------------------------------
| Garante token público para QR Code
|--------------------------------------------------------------------------
*/
$publicToken = garantirPublicTokenPedido(
    $conn,
    $pedidoId,
    $pedido['public_token'] ?? null,
    $empresaId
);

$pedido['public_token'] = $publicToken;

/*
|--------------------------------------------------------------------------
| Itens
|--------------------------------------------------------------------------
*/
$stmtItens = $conn->prepare("
    SELECT
        pi.id AS item_id,
        pi.produto_id,
        pi.quantidade,
        pi.preco_unitario,
        pi.observacao,
        pi.ponto,
        pi.quantidade_pronta,
        pi.adicional,
        pi.valor_adicional,
        pi.created_at,
        pi.updated_at,

        p.nome AS produto_nome,

        c.nome AS categoria_nome

    FROM pedido_itens pi

    INNER JOIN produtos p
        ON p.id = pi.produto_id

    LEFT JOIN categorias c
        ON c.id = p.categoria_id

    WHERE pi.pedido_id = ?
    AND pi.empresa_id = ?

    ORDER BY
        c.nome ASC,
        pi.id ASC
");

if(!$stmtItens){
    echo "Erro prepare itens: " . h($conn->error);
    exit;
}

$stmtItens->bind_param("ii", $pedidoId, $empresaId);
$stmtItens->execute();

$resItens = $stmtItens->get_result();

$itens = [];

$subtotal = 0;
$totalAdicional = 0;
$total = 0;
$quantidadeItens = 0;

while($item = $resItens->fetch_assoc()){

    $quantidade = (int)$item['quantidade'];

    $precoUnitario = (float)$item['preco_unitario'];

    $adicionalFlag = max(0, (int)$item['adicional']);

    $valorAdicional = $adicionalFlag > 0
        ? (float)$item['valor_adicional']
        : 0;

    $subtotalItem = $precoUnitario * $quantidade;

    $adicionalItem = $valorAdicional * $adicionalFlag * $quantidade;

    $totalItem = $subtotalItem + $adicionalItem;

    $subtotal += $subtotalItem;
    $totalAdicional += $adicionalItem;
    $total += $totalItem;
    $quantidadeItens += $quantidade;

    $detalhes = [];

    if($adicionalFlag > 0 && $valorAdicional > 0){
        $detalhes[] = $adicionalFlag . " " . ($adicionalFlag > 1 ? "adicionais" : "adicional") . " x R$ " . dinheiro($valorAdicional);
    }

    if(!empty($item['observacao'])){
        $detalhes[] = "Obs: " . $item['observacao'];
    }

    if(!empty($item['ponto'])){
        $detalhes[] = "Ponto: " . $item['ponto'];
    }

    $itens[] = [
        'id' => (int)$item['item_id'],
        'produto_id' => (int)$item['produto_id'],
        'nome' => $item['produto_nome'],
        'categoria' => $item['categoria_nome'] ?: 'Sem categoria',
        'quantidade' => $quantidade,
        'preco_unitario' => $precoUnitario,
        'adicional' => $adicionalFlag,
        'valor_adicional' => $valorAdicional,
        'subtotal' => $subtotalItem,
        'total_adicional' => $adicionalItem,
        'total' => $totalItem,
        'detalhes' => $detalhes
    ];
}

$stmtItens->close();

/*
|--------------------------------------------------------------------------
| Dados de exibição
|--------------------------------------------------------------------------
*/
$numero = (int)($pedido['numero_pedido'] ?? $pedido['id']);

$status = $pedido['status'];

$hora = !empty($pedido['created_at'])
    ? date('H:i', strtotime($pedido['created_at']))
    : date('H:i');

$data = !empty($pedido['created_at'])
    ? date('d/m/Y', strtotime($pedido['created_at']))
    : date('d/m/Y');

$tipo = !empty($pedido['mesa'])
    ? 'Mesa'
    : 'Cliente';

$referencia = !empty($pedido['mesa'])
    ? (string)$pedido['mesa']
    : (string)$pedido['cliente'];

$atendenteNome = !empty($pedido['atendente_nome'])
    ? (string)$pedido['atendente_nome']
    : 'Não informado';

$formaPagamentoLabels = [
    PAGAMENTO_DINHEIRO => 'Dinheiro',
    PAGAMENTO_PIX => 'Pix',
    PAGAMENTO_CARTAO => 'Cartão',
    PAGAMENTO_PRAZO => 'A prazo',
    PAGAMENTO_ANTECIPADO => 'Antecipado'
];

$formaPagamento = (string)($pedido['forma_pagamento'] ?? '');

$formaPagamentoLabel = $formaPagamentoLabels[$formaPagamento] ?? null;

$servico10Ativo = !empty($pedido['servico_10_ativo']);
$taxaServico10 = $servico10Ativo
    ? round((float)($pedido['taxa_servico_10'] ?? ($total * 0.10)), 2)
    : 0.00;
$totalComServico = round($total + $taxaServico10, 2);
$totalAntecipado = max(0, (float)($pedido['total_antecipado'] ?? 0));
$saldoConta = max(0, $totalComServico - $totalAntecipado);

/*
|--------------------------------------------------------------------------
| Link público seguro da comanda via QR Code
|--------------------------------------------------------------------------
| Agora usa pedido_id + token.
|--------------------------------------------------------------------------
*/
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (
        isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
    )
);

$scheme = $isHttps ? 'https' : 'http';

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');

$baseUrl = $scheme . '://' . $host . ($dir ? $dir : '');

$linkComanda = $baseUrl
    . '/comanda.php?pedido_id='
    . urlencode((string)$pedidoId)
    . '&token='
    . urlencode($publicToken);

$qr = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data="
    . urlencode($linkComanda);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">

<title>Comanda #<?= h($numero) ?></title>

<style>
body {
    font-family: monospace;
    font-size: 12px;
    width: 58mm;
    margin: 0;
    color: #000;
}

@page {
    size: 58mm auto;
    margin: 3mm;
}

.center {
    text-align: center;
}

.right {
    text-align: right;
}

hr {
    border: 0;
    border-top: 1px dashed #000;
    margin: 7px 0;
}

.item {
    margin-bottom: 7px;
}

.item-top {
    display: flex;
    justify-content: space-between;
    gap: 5px;
}

.item-nome {
    max-width: 36mm;
    overflow-wrap: break-word;
}

.item-total {
    white-space: nowrap;
    text-align: right;
}

.detalhe {
    font-size: 10px;
    margin-left: 8px;
    overflow-wrap: break-word;
}

.total-box {
    margin-top: 8px;
}

.linha-total {
    display: flex;
    justify-content: space-between;
}

.total-final {
    font-weight: bold;
    font-size: 14px;
    margin-top: 4px;
}

.small {
    font-size: 9px;
    word-break: break-word;
}

.cancelada {
    color: red;
    font-weight: bold;
    font-size: 14px;
    margin-top: 5px;
}

.status {
    font-size: 11px;
    text-transform: uppercase;
}

.qr {
    margin-top: 10px;
}

#btnImprimir {
    margin-top: 10px;
    padding: 6px 10px;
    font-size: 12px;
}

@media print {
    #btnImprimir {
        display: none;
    }
}
</style>
</head>

<body onload="setTimeout(imprimirAutomatico, 500)">

<div class="center">
    🧾 <strong>COMANDARIA EXPRESS</strong><br>
    <strong>COMANDA #<?= h($numero) ?></strong><br>

    <?php if($status === STATUS_CANCELADO): ?>
        <div class="cancelada">❌ COMANDA CANCELADA</div>
    <?php endif; ?>

    <span class="status">
        Status: <?= h($status) ?>
    </span><br>

    <?= h($tipo) ?>: <?= h($referencia ?: '-') ?><br>
    Atendente: <?= h($atendenteNome) ?><br>
    <?php if($formaPagamentoLabel): ?>
        Pagamento: <?= h($formaPagamentoLabel) ?><br>
    <?php endif; ?>
    Data: <?= h($data) ?> - Hora: <?= h($hora) ?>
</div>

<hr>

<?php if(count($itens) > 0): ?>

    <?php foreach($itens as $item): ?>

        <div class="item">
            <div class="item-top">
                <span class="item-nome">
                    <?= h($item['quantidade']) ?>x <?= h($item['nome']) ?>
                </span>

                <span class="item-total">
                    <?= dinheiro($item['total']) ?>
                </span>
            </div>

            <div class="detalhe">
                Unit: R$ <?= dinheiro($item['preco_unitario']) ?>

                <?php if($item['adicional'] > 0 && $item['valor_adicional'] > 0): ?>
                    + <?= h($item['adicional']) ?>x R$ <?= dinheiro($item['valor_adicional']) ?>
                <?php endif; ?>
            </div>

            <?php if(!empty($item['detalhes'])): ?>
                <?php foreach($item['detalhes'] as $detalhe): ?>
                    <div class="detalhe">
                        <?= h($detalhe) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php endforeach; ?>

<?php else: ?>

    <div class="center">
        Nenhum item
    </div>

<?php endif; ?>

<hr>

<div class="total-box">
    <div class="linha-total">
        <span>Itens</span>
        <span><?= h($quantidadeItens) ?></span>
    </div>

    <div class="linha-total">
        <span>Subtotal</span>
        <span>R$ <?= dinheiro($subtotal) ?></span>
    </div>

    <div class="linha-total">
        <span>Adicionais</span>
        <span>R$ <?= dinheiro($totalAdicional) ?></span>
    </div>

    <div class="linha-total total-final">
        <span>TOTAL</span>
        <span>R$ <?= dinheiro($total) ?></span>
    </div>

    <?php if($totalAntecipado > 0): ?>
        <div class="linha-total">
            <span>Antecipado</span>
            <span>R$ <?= dinheiro($totalAntecipado) ?></span>
        </div>

        <div class="linha-total total-final">
            <span>Saldo da conta</span>
            <span>R$ <?= dinheiro($saldoConta) ?></span>
        </div>
    <?php endif; ?>

    <div class="linha-total">
        <span>10% serviço</span>
        <span><?= $servico10Ativo ? 'R$ ' . dinheiro($taxaServico10) : 'Não cobrado' ?></span>
    </div>

    <div class="linha-total total-final">
        <span>TOTAL FINAL</span>
        <span>R$ <?= dinheiro($totalComServico) ?></span>
    </div>
</div>

<hr>
<!--
<div class="center qr">
    <img src="<?//= h($qr) ?>" width="120" alt="QR Code"><br>

    <div class="small">
        Escaneie para acompanhar a comanda
    </div>

    <div class="small">
        <?//= h($linkComanda) ?>
    </div>
</div>
-->
<br>

<div class="center">
    Obrigado pela preferência 🙌
</div>

<center>
    <button id="btnImprimir" onclick="imprimirAutomatico()">
        Imprimir Cupom
    </button>
</center>

<script>
function imprimirAutomatico(){

    if(typeof gonative !== "undefined" && gonative.share){
        gonative.share.sharePage();
        return;
    }

    if(navigator.share){
        navigator.share({
            title: document.title,
            url: window.location.href
        }).catch(() => {
            window.print();
        });

        return;
    }

    window.print();
}
</script>

</body>
</html>

<?php
$conn->close();
?>
