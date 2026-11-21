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

require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../core/tenant_core.php';

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

/*
|--------------------------------------------------------------------------
| Pedido
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        id,
        numero_pedido,
        mesa,
        cliente,
        status,
        created_at,
        updated_at
    FROM pedidos
    WHERE id = ?
    AND empresa_id = ?
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
| Itens
|--------------------------------------------------------------------------
*/
$stmtItens = $conn->prepare("
    SELECT
        pi.id AS item_id,
        pi.produto_id,
        pi.quantidade,
        pi.quantidade_pronta,
        pi.observacao,
        pi.ponto,
        pi.adicional,
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

while($item = $resItens->fetch_assoc()){

    $quantidade = (int)$item['quantidade'];
    $quantidadePronta = (int)$item['quantidade_pronta'];

    $statusItem = $quantidadePronta >= $quantidade
        ? STATUS_PRONTO
        : STATUS_PENDENTE;

    $instrucoes = [];

    if((int)$item['adicional'] === 1){
        $instrucoes[] = "COM ADICIONAL";
    }

    if(!empty($item['observacao'])){
        $instrucoes[] = "OBS: " . $item['observacao'];
    }

    if(!empty($item['ponto'])){
        $instrucoes[] = "PONTO: " . $item['ponto'];
    }

    $itens[] = [
        'id' => (int)$item['item_id'],
        'produto_id' => (int)$item['produto_id'],
        'nome' => $item['produto_nome'],
        'categoria' => $item['categoria_nome'] ?: 'Sem categoria',
        'quantidade' => $quantidade,
        'quantidade_pronta' => $quantidadePronta,
        'status' => $statusItem,
        'instrucoes' => $instrucoes
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

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">

<title>Cozinha - Pedido #<?= h($numero) ?></title>

<style>
body {
    font-family: monospace;
    font-size: 13px;
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
    margin: 8px 0;
}

.item {
    margin-bottom: 10px;
    padding-bottom: 6px;
    border-bottom: 1px dotted #000;
}

.item:last-child {
    border-bottom: 0;
}

.categoria {
    font-size: 10px;
    text-transform: uppercase;
}

.nome {
    font-size: 15px;
    font-weight: bold;
}

.qtd {
    font-size: 17px;
    font-weight: bold;
}

.progresso {
    font-size: 11px;
    margin-top: 2px;
}

.instrucao {
    font-size: 11px;
    font-weight: bold;
    margin-left: 6px;
}

.pronto {
    text-decoration: line-through;
    opacity: .6;
}

.status {
    font-size: 11px;
    text-transform: uppercase;
}

.alerta {
    font-size: 15px;
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

<body onload="setTimeout(imprimirAutomatico, 500)">

<div class="center">
    🍳 <strong>COZINHA</strong><br>
    <strong>PEDIDO #<?= h($numero) ?></strong><br>

    <span class="status">
        Status: <?= h($status) ?>
    </span><br>

    <?= h($tipo) ?>: <?= h($referencia ?: '-') ?><br>
    <?= h($data) ?> - <?= h($hora) ?>
</div>

<hr>

<?php if(count($itens) > 0): ?>

    <?php foreach($itens as $item): ?>

        <div class="item <?= $item['status'] === STATUS_PRONTO ? 'pronto' : '' ?>">
            <div class="categoria">
                <?= h($item['categoria']) ?>
            </div>

            <div>
                <span class="qtd">
                    <?= h($item['quantidade']) ?>x
                </span>

                <span class="nome">
                    <?= h($item['nome']) ?>
                </span>
            </div>

            <div class="progresso">
                Progresso:
                <?= h($item['quantidade_pronta']) ?>/<?= h($item['quantidade']) ?>
            </div>

            <?php if(!empty($item['instrucoes'])): ?>
                <?php foreach($item['instrucoes'] as $instrucao): ?>
                    <div class="instrucao">
                        ⚠ <?= h($instrucao) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php endforeach; ?>

<?php else: ?>

    <div class="center alerta">
        Nenhum item
    </div>

<?php endif; ?>

<hr>

<div class="center">
    Produção interna<br>
    COMANDARIA EXPRESS
</div>

<center>
    <button id="btnImprimir" onclick="imprimirAutomatico()">
        Imprimir Cozinha
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