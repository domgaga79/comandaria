<?php

/*
|--------------------------------------------------------------------------
| MESARIA EXPRESS - GERADOR DE QR CODES DAS MESAS
|--------------------------------------------------------------------------
| Acesso interno: admin.
|
| Uso:
| /gerar_qr_mesas.php
| /gerar_qr_mesas.php?gerar=1&ate=30
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
requireRole(['admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/conexao.php';
require __DIR__ . '/../core/mesa_qr_core.php';

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$erro = null;
$sucesso = null;

try {

    mesariaGarantirTabelaQr($conn);

    if(isset($_GET['gerar'])){

        $ate = (int)($_GET['ate'] ?? 30);
        $ate = max(1, min(200, $ate));

        for($mesa = 1; $mesa <= $ate; $mesa++){
            mesariaCriarQrMesaSeNaoExiste($conn, $mesa);
        }

        $sucesso = "QR Codes garantidos para mesas 1 a {$ate}.";
    }

    $mesas = mesariaListarMesasQr($conn);
    $base = mesariaUrlBasePublica();

} catch(Throwable $e){
    $erro = $e->getMessage();
    $mesas = [];
    $base = mesariaUrlBasePublica();
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mesaria Express - QR Codes das Mesas</title>

<meta name="description" content="Sistema de comandas para restaurantes e bares">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="Comandaria Express">
<meta property="og:title" content="Comandaria Express">
<meta property="og:description" content="Sistema de comandas para restaurantes e bares">
<meta property="og:type" content="website">
<meta property="og:url" content="https://comandaria.bacuridigital.com/burguershake/index.php">
<meta property="og:image" content="https://comandaria.bacuridigital.com/burguershake/public/images/comandaria_logo_og_1200x630.png">
<meta property="og:image:secure_url" content="https://comandaria.bacuridigital.com/burguershake/public/images/comandaria_logo_og_1200x630.png">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Comandaria Express">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Comandaria Express">
<meta name="twitter:description" content="Sistema de comandas para restaurantes e bares">
<meta name="twitter:image" content="https://comandaria.bacuridigital.com/burguershake/public/images/comandaria_logo_og_1200x630.png">
<link rel="icon" href="images/topo_comandaria_shake.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{
    background:#111;
    color:#fff;
    font-family:Arial,sans-serif;
    padding:16px;
}
.header{
    max-width:1100px;
    margin:0 auto 18px;
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
}
.card-mesa{
    background:#fff;
    color:#111;
    border-radius:18px;
    padding:14px;
    text-align:center;
    page-break-inside:avoid;
    box-shadow:0 3px 14px rgba(0,0,0,.25);
}
.grid{
    max-width:1100px;
    margin:0 auto;
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(240px,1fr));
    gap:14px;
}
.qr{
    width:190px;
    height:190px;
    object-fit:contain;
}
.link{
    word-break:break-all;
    font-size:11px;
    color:#555;
    margin-top:8px;
}
.badge-ativo{
    display:inline-block;
    border-radius:999px;
    padding:4px 8px;
    background:#198754;
    color:#fff;
    font-size:11px;
}
.badge-inativo{
    display:inline-block;
    border-radius:999px;
    padding:4px 8px;
    background:#dc3545;
    color:#fff;
    font-size:11px;
}
@media print{
    body{
        background:#fff;
        color:#000;
    }
    .no-print{
        display:none!important;
    }
    .grid{
        grid-template-columns:repeat(2,1fr);
        gap:10px;
    }
    .card-mesa{
        box-shadow:none;
        border:1px solid #ddd;
    }
}
</style>
</head>
<body>

<div class="header">
    <div>
        <h1 class="h3 m-0">🍽️ Mesaria Express</h1>
        <div class="text-secondary">QR Codes fixos das mesas</div>
    </div>

    <div class="d-flex gap-2 no-print flex-wrap">
        <form method="get" class="d-flex gap-2">
            <input type="hidden" name="gerar" value="1">
            <input type="number" name="ate" value="30" min="1" max="200" class="form-control form-control-sm" style="width:90px">
            <button class="btn btn-warning btn-sm">Gerar/garantir</button>
        </form>

        <button onclick="window.print()" class="btn btn-success btn-sm">Imprimir</button>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">Voltar</a>
    </div>
</div>

<div class="container no-print">
    <?php if($erro): ?>
        <div class="alert alert-danger"><?= h($erro) ?></div>
    <?php endif; ?>

    <?php if($sucesso): ?>
        <div class="alert alert-success"><?= h($sucesso) ?></div>
    <?php endif; ?>
</div>

<div class="grid">
<?php foreach($mesas as $mesa): ?>
    <?php
        $link = $base . '/mesa.php?mesa=' . (int)$mesa['mesa'] . '&token=' . urlencode($mesa['token']);
        $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . urlencode($link);
    ?>
    <div class="card-mesa">
        <h2 class="h4 mb-1">Mesa <?= (int)$mesa['mesa'] ?></h2>
        <div class="mb-2">
            <?php if((int)$mesa['ativo'] === 1): ?>
                <span class="badge-ativo">ativo</span>
            <?php else: ?>
                <span class="badge-inativo">inativo</span>
            <?php endif; ?>
        </div>

        <img class="qr" src="<?= h($qr) ?>" alt="QR Code Mesa <?= (int)$mesa['mesa'] ?>">

        <div class="fw-bold mt-2">Escaneie e faça seu pedido</div>
        <div class="small text-muted">Mesaria Express</div>

        <div class="link no-print">
            <a href="<?= h($link) ?>" target="_blank"><?= h($link) ?></a>
        </div>
    </div>
<?php endforeach; ?>
</div>

</body>
</html>
<?php
if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
