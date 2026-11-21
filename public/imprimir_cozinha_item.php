<?php
require __DIR__ . '/auth.php';
requireRole(['cozinha','admin']);

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

$itemId = (int)($_GET['item_id'] ?? 0);
$qtdImpressaoSolicitada = isset($_GET['qtd_impressao'])
    ? (int)$_GET['qtd_impressao']
    : 0;
$modoAuto = isset($_GET['_auto']) && (string)$_GET['_auto'] === '1';

if($itemId <= 0){
    echo "Item inválido";
    exit;
}

$stmt = $conn->prepare("
    SELECT
        pi.id AS item_id,
        pi.pedido_id,
        pi.produto_id,
        pi.quantidade,
        pi.quantidade_pronta,
        pi.observacao,
        pi.ponto,
        pi.adicional,
        pi.valor_adicional,
        pi.created_at AS item_created_at,

        p.id AS pedido_id,
        p.numero_pedido AS pedido_numero,
        p.mesa,
        p.cliente,
        p.status AS pedido_status,
        p.created_at AS pedido_created_at,

        pr.nome AS produto_nome,

        c.nome AS categoria_nome

    FROM pedido_itens pi

    INNER JOIN pedidos p
        ON p.id = pi.pedido_id
       AND p.empresa_id = pi.empresa_id

    INNER JOIN produtos pr
        ON pr.id = pi.produto_id
       AND pr.empresa_id = pi.empresa_id

    LEFT JOIN categorias c
        ON c.id = pr.categoria_id
       AND c.empresa_id = pi.empresa_id

    WHERE pi.id = ?
    AND pi.empresa_id = ?

    LIMIT 1
");

if(!$stmt){
    echo "Erro prepare: " . h($conn->error);
    exit;
}

$stmt->bind_param("ii", $itemId, $empresaId);

$stmt->execute();

$res = $stmt->get_result();

$item = $res->fetch_assoc();

$stmt->close();

if(!$item){
    echo "Item não encontrado";
    exit;
}

$pedidoId = (int)$item['pedido_id'];
$numeroPedido = (int)($item['pedido_numero'] ?? $pedidoId);

$quantidadeTotal = max(0, (int)$item['quantidade']);
$qtdPronta = max(0, (int)$item['quantidade_pronta']);
$qtdPendente = max(0, $quantidadeTotal - $qtdPronta);

$qtdImpressao = $qtdImpressaoSolicitada > 0
    ? min($qtdImpressaoSolicitada, max(1, $quantidadeTotal))
    : max(1, $quantidadeTotal);

$impressaoParcial = $qtdImpressaoSolicitada > 0;

$qtdAdicionais = max(0, (int)$item['adicional']);

$valorAdicional = (float)$item['valor_adicional'];

$tipo = !empty($item['mesa'])
    ? 'Mesa'
    : 'Cliente';

$referencia = !empty($item['mesa'])
    ? (string)$item['mesa']
    : (string)$item['cliente'];

$dataPedido = !empty($item['pedido_created_at'])
    ? date('d/m/Y H:i', strtotime($item['pedido_created_at']))
    : date('d/m/Y H:i');

$dataItem = !empty($item['item_created_at'])
    ? date('d/m/Y H:i', strtotime($item['item_created_at']))
    : date('d/m/Y H:i');

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">

<title>Item Cozinha #<?= h($itemId) ?></title>

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

hr {
    border: 0;
    border-top: 1px dashed #000;
    margin: 7px 0;
}

.titulo-item {
    font-size: 15px;
    font-weight: bold;
    text-transform: uppercase;
    word-break: break-word;
}

.linha {
    margin: 4px 0;
}

.detalhe {
    font-size: 11px;
    margin-top: 4px;
    word-break: break-word;
}

.qtd {
    font-size: 22px;
    font-weight: bold;
    text-align: center;
    margin: 8px 0;
}

.alerta {
    font-weight: bold;
    font-size: 13px;
}

.small {
    font-size: 10px;
    word-break: break-word;
}

.tag-novo {
    display: inline-block;
    margin-top: 4px;
    padding: 2px 6px;
    border: 1px solid #000;
    font-size: 10px;
    font-weight: bold;
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

<body onload="setTimeout(() => window.print(), 500)">

<div class="center">
    🍳 <strong>COZINHA</strong><br>
    <strong><?= $impressaoParcial ? 'ITEM NOVO DO PEDIDO' : 'ITEM DO PEDIDO' ?></strong><br>
    Pedido #<?= h($numeroPedido) ?><br>
    <?= h($tipo) ?>: <?= h($referencia ?: '-') ?><br>
    <?= h($dataPedido) ?>

    <?php if($modoAuto || $impressaoParcial): ?>
        <br><span class="tag-novo">AUTOIMPRESSÃO - NOVO ENVIO</span>
    <?php endif; ?>
</div>

<hr>

<div class="center">
    <div class="titulo-item">
        <?= h($item['produto_nome']) ?>
    </div>

    <?php if(!empty($item['categoria_nome'])): ?>
        <div class="small">
            <?= h($item['categoria_nome']) ?>
        </div>
    <?php endif; ?>
</div>

<div class="qtd">
    <?= h($qtdImpressao) ?>x
</div>

<div class="center small">
    <?php if($impressaoParcial): ?>
        Quantidade nova para produzir: <?= h($qtdImpressao) ?>x<br>
        Total atual deste item no pedido: <?= h($quantidadeTotal) ?>x<br>
    <?php endif; ?>

    Pronto: <?= h($qtdPronta) ?>/<?= h($quantidadeTotal) ?>
    <?php if($qtdPendente > 0): ?>
        <br>Pendente total: <?= h($qtdPendente) ?>
    <?php endif; ?>
</div>

<hr>

<?php if(!empty($item['observacao'])): ?>
    <div class="detalhe">
        <strong>OBS:</strong>
        <?= h($item['observacao']) ?>
    </div>
<?php endif; ?>

<?php if(!empty($item['ponto'])): ?>
    <div class="detalhe">
        <strong>PONTO:</strong>
        <?= h($item['ponto']) ?>
    </div>
<?php endif; ?>

<?php if($qtdAdicionais > 0): ?>
    <div class="detalhe alerta">
        ADICIONAL:
        <?= h($qtdAdicionais) ?>x
        <?php if($valorAdicional > 0): ?>
            R$ <?= dinheiro($valorAdicional) ?> cada
        <?php endif; ?>
    </div>
<?php endif; ?>

<hr>

<div class="small center">
    Item #<?= h($itemId) ?><br>
    Enviado em: <?= h($dataItem) ?>
</div>

<center>
    <button id="btnImprimir" onclick="window.print()">
        Imprimir item
    </button>
</center>

</body>
</html>

<?php
$conn->close();
?>
