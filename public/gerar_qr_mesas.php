<?php

/*
|--------------------------------------------------------------------------
| MESARIA EXPRESS - GERADOR DE QR CODES DAS MESAS
|--------------------------------------------------------------------------
| Acesso interno: admin.
|
| Uso:
| /gerar_qr_mesas.php
| /gerar_qr_mesas.php?gerar=1&ate=10
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
require __DIR__ . '/../core/tenant_core.php';

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$erro = null;
$sucesso = null;
$planoAtual = null;
$limiteMesasPlano = 10;
$ateCampo = 10;

try {

    $empresaId = tenantResolverEmpresaId(authEmpresaId());
    mesariaGarantirTabelaQr($conn);

    $planoAtual = tenantPlanoEmpresa($conn, $empresaId);
    $limiteMesasPlano = max(1, (int)($planoAtual['limite_mesas'] ?? 10));
    $ateCampo = (int)($_GET['ate'] ?? $limiteMesasPlano);
    $ateCampo = max(1, min($limiteMesasPlano, $ateCampo));

    if(isset($_GET['gerar'])){

        $ate = $ateCampo;
        $novasMesas = 0;

        for($mesa = 1; $mesa <= $ate; $mesa++){
            if(mesariaBuscarQrPorMesa($conn, $mesa, $empresaId) === null){
                $novasMesas++;
            }
        }

        if($novasMesas > 0){
            tenantGarantirLimiteMesas($conn, $empresaId, $novasMesas);
        }

        for($mesa = 1; $mesa <= $ate; $mesa++){
            mesariaCriarQrMesaSeNaoExiste($conn, $mesa, $empresaId);
        }

        $sucesso = "QR Codes garantidos para mesas 1 a {$ate}.";
    }

    $mesas = mesariaListarMesasQr($conn, $empresaId);
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
<meta property="og:url" content="https://comandaria.bacuridigital.com/index.php">
<meta property="og:image" content="https://comandaria.bacuridigital.com/images/comandaria_logo_og_1200x630.png">
<meta property="og:image:secure_url" content="https://comandaria.bacuridigital.com/images/comandaria_logo_og_1200x630.png">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Comandaria Express">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Comandaria Express">
<meta name="twitter:description" content="Sistema de comandas para restaurantes e bares">
<meta name="twitter:image" content="https://comandaria.bacuridigital.com/images/comandaria_logo_og_1200x630.png">
<link rel="icon" href="images/comandaria_logo.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/management.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/staff.css?v=2">
</head>
<body class="cd-staff">
<?php $staffPage = 'gerar_qr_mesas.php'; require __DIR__ . '/includes/staff_nav.php'; ?>

<main class="cd-management cd-qr">
    <header class="cd-page-head">
        <div>
            <h1>QR Codes das mesas</h1>
            <p class="no-print">Plano <?= h((string)($planoAtual['plano_nome'] ?? 'FREE')) ?> · até <?= (int)$limiteMesasPlano ?> mesas</p>
        </div>
        <button type="button" onclick="window.print()" class="btn cd-outline no-print" <?= !$mesas ? 'disabled' : '' ?>>Imprimir cartões</button>
    </header>

    <div class="card-box no-print">
        <form method="get" class="cd-qr-form">
            <input type="hidden" name="gerar" value="1">
            <div>
                <label class="form-label" for="gerar-ate">Gerar da mesa 1 até</label>
                <input type="number" id="gerar-ate" name="ate" value="<?= (int)$ateCampo ?>" min="1" max="<?= (int)$limiteMesasPlano ?>" class="form-control" required>
            </div>
            <button class="btn cd-primary">Gerar QR Codes</button>
        </form>
    </div>

    <div class="no-print">
        <?php if($erro): ?>
            <div class="alert alert-danger"><?= h($erro) ?></div>
        <?php endif; ?>

        <?php if($sucesso): ?>
            <div class="alert alert-success"><?= h($sucesso) ?></div>
        <?php endif; ?>
    </div>

    <?php if(!$mesas && !$erro): ?>
        <div class="empty-state no-print">Os cartões das mesas aparecem aqui após gerar os QR Codes.</div>
    <?php endif; ?>

    <div class="cd-qr-grid">
    <?php foreach($mesas as $mesa): ?>
        <?php
            $link = $base . '/mesa.php?mesa=' . (int)$mesa['mesa'] . '&token=' . urlencode($mesa['token']);
            $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . urlencode($link);
        ?>
        <article class="card-mesa">
            <h2 class="h4 mb-1">Mesa <?= (int)$mesa['mesa'] ?></h2>
            <div class="no-print mb-2">
                <?php if((int)$mesa['ativo'] === 1): ?>
                    <span class="badge-ativo">ativo</span>
                <?php else: ?>
                    <span class="badge-inativo">inativo</span>
                <?php endif; ?>
            </div>

            <img class="qr" width="190" height="190" src="<?= h($qr) ?>" alt="QR Code Mesa <?= (int)$mesa['mesa'] ?>">

            <div class="fw-bold mt-2">Escaneie e faça seu pedido</div>
            <div class="small text-muted">Mesaria Express</div>

            <div class="link no-print">
                <a href="<?= h($link) ?>" target="_blank" rel="noopener">Abrir cardápio da mesa <span aria-hidden="true">↗</span></a>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</main>

</body>
</html>
<?php
if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
