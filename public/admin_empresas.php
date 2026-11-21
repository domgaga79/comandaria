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
require __DIR__ . '/../core/plataforma_admin_core.php';

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

plataformaRequireAdmin($conn);

if(empty($_SESSION['admin_empresas_csrf'])){
    $_SESSION['admin_empresas_csrf'] = bin2hex(random_bytes(32));
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function telefoneVisualAdmin(?string $telefone): string {
    $digits = preg_replace('/\D+/', '', (string)$telefone) ?: '';

    if(strlen($digits) === 11){
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7);
    }

    if(strlen($digits) === 10){
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6);
    }

    return $digits !== '' ? $digits : '—';
}

function percentualUsoAdmin(int $usado, int $limite): float {
    if($limite <= 0){
        return 0.0;
    }

    return min(100, round(($usado / $limite) * 100, 1));
}

function classeUsoAdmin(float $percentual): string {
    if($percentual >= 100){
        return 'bg-danger';
    }

    if($percentual >= 80){
        return 'bg-warning text-dark';
    }

    return 'bg-success';
}

$alerta = trim((string)($_GET['alerta'] ?? ''));
$msg = trim((string)($_GET['msg'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$planos = [];
$empresas = [];
$resumo = [
    'total' => 0,
    'ativas' => 0,
    'free' => 0,
    'pro' => 0,
    'suspensas' => 0
];

try {
    $resPlanos = $conn->query("
        SELECT
            id,
            nome,
            limite_mesas,
            limite_pedidos_mes,
            ativo
        FROM planos
        ORDER BY FIELD(nome, 'FREE', 'PRO'), nome ASC
    ");

    if(!$resPlanos){
        throw new Exception($conn->error);
    }

    while($row = $resPlanos->fetch_assoc()){
        $planos[] = [
            'id' => (int)$row['id'],
            'nome' => (string)$row['nome'],
            'limite_mesas' => (int)$row['limite_mesas'],
            'limite_pedidos_mes' => (int)$row['limite_pedidos_mes'],
            'ativo' => (int)$row['ativo']
        ];
    }

    $sqlEmpresas = "
        SELECT
            e.id,
            e.nome,
            e.slug,
            e.telefone,
            e.plano_id,
            e.status,
            e.status_assinatura,
            e.is_admin_plataforma,
            e.created_at,
            e.updated_at,
            p.nome AS plano_nome,
            p.limite_mesas,
            p.limite_pedidos_mes,
            (
                SELECT COUNT(*)
                FROM mesas_qr mq
                WHERE mq.empresa_id = e.id
            ) AS mesas_usadas,
            (
                SELECT COUNT(*)
                FROM pedidos pd
                WHERE pd.empresa_id = e.id
                AND pd.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
                AND pd.created_at < DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'), INTERVAL 1 MONTH)
            ) AS pedidos_mes,
            (
                SELECT COUNT(*)
                FROM usuarios u
                WHERE u.empresa_id = e.id
            ) AS usuarios_total
        FROM empresas e
        INNER JOIN planos p
            ON p.id = e.plano_id
    ";

    $buscaNome = '';
    $buscaSlug = '';
    $buscaTelefone = '';

    if($q !== ''){
        $sqlEmpresas .= "
            WHERE e.nome LIKE ?
            OR e.slug LIKE ?
            OR COALESCE(e.telefone, '') LIKE ?
        ";

        $buscaNome = '%' . $q . '%';
        $buscaSlug = $buscaNome;
        $buscaTelefone = $buscaNome;
    }

    $sqlEmpresas .= "
        ORDER BY
            e.is_admin_plataforma DESC,
            e.nome ASC,
            e.id ASC
    ";

    $stmtEmpresas = $conn->prepare($sqlEmpresas);

    if(!$stmtEmpresas){
        throw new Exception($conn->error);
    }

    if($q !== ''){
        $stmtEmpresas->bind_param('sss', $buscaNome, $buscaSlug, $buscaTelefone);
    }

    if(!$stmtEmpresas->execute()){
        throw new Exception($stmtEmpresas->error);
    }

    $resEmpresas = $stmtEmpresas->get_result();

    while($row = $resEmpresas->fetch_assoc()){
        $empresa = [
            'id' => (int)$row['id'],
            'nome' => (string)$row['nome'],
            'slug' => (string)$row['slug'],
            'telefone' => (string)($row['telefone'] ?? ''),
            'plano_id' => (int)$row['plano_id'],
            'plano_nome' => (string)$row['plano_nome'],
            'limite_mesas' => (int)$row['limite_mesas'],
            'limite_pedidos_mes' => (int)$row['limite_pedidos_mes'],
            'status' => (string)$row['status'],
            'status_assinatura' => (string)$row['status_assinatura'],
            'is_admin_plataforma' => (int)$row['is_admin_plataforma'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
            'mesas_usadas' => (int)$row['mesas_usadas'],
            'pedidos_mes' => (int)$row['pedidos_mes'],
            'usuarios_total' => (int)$row['usuarios_total']
        ];

        $empresas[] = $empresa;

        $resumo['total']++;

        if($empresa['status'] === 'ativa'){
            $resumo['ativas']++;
        }

        if($empresa['status_assinatura'] !== 'ativa'){
            $resumo['suspensas']++;
        }

        if(strtoupper($empresa['plano_nome']) === 'FREE'){
            $resumo['free']++;
        }

        if(strtoupper($empresa['plano_nome']) === 'PRO'){
            $resumo['pro']++;
        }
    }

    $stmtEmpresas->close();

} catch(Throwable $e){
    $alerta = 'erro';
    $msg = $e->getMessage();
}

$nomeUsuario = $_SESSION['user_nome'] ?? 'Administrador';

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Empresas e Planos - Comandaria Express</title>

<!-- Favicon / ícone da aba -->
<link rel="icon" href="images/comandaria_logo.png" type="image/png">

<!-- SEO básico -->
<meta name="description" content="Painel administrativo de empresas, planos, limites de uso e assinaturas da Comandaria Express.">
<meta name="theme-color" content="#111111">

<!-- Open Graph / WhatsApp / Facebook -->
<meta property="og:title" content="Comandaria Express | Administração de Empresas e Planos">
<meta property="og:description" content="Painel administrativo para acompanhar tenants, planos FREE e PRO, limites de mesas, pedidos mensais e assinaturas.">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:url" content="https://comandaria.bacuridigital.com/admin_empresas.php">
<meta property="og:image" content="https://comandaria.bacuridigital.com/images/comandaria_logo_og.png">
<meta property="og:image:secure_url" content="https://comandaria.bacuridigital.com/images/comandaria_logo_og.png">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Comandaria Express - Administração de empresas e planos">

<link rel="canonical" href="https://comandaria.bacuridigital.com/admin_empresas.php">

<!-- Twitter/X -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Comandaria Express | Administração de Empresas e Planos">
<meta name="twitter:description" content="Painel administrativo para acompanhar tenants, planos FREE e PRO, limites de uso e assinaturas.">
<meta name="twitter:image" content="https://comandaria.bacuridigital.com/images/comandaria_logo_og.png">

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
    height: auto;
}

.userbox {
    text-align: center;
    margin-bottom: 14px;
}

.userbox .inner {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 7px 15px;
    border-radius: 999px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.08);
    font-size: 14px;
    flex-wrap: wrap;
}

.nav-link-top {
    color: #ffc107;
    text-decoration: none;
    font-weight: 700;
}

.nav-link-top:hover {
    color: #ffda6a;
}

.container-main {
    max-width: 1320px;
    margin: 0 auto;
    padding: 10px 12px 34px;
}

.card-box {
    background: #fff;
    color: #111;
    border-radius: 18px;
    padding: 18px;
    margin-bottom: 14px;
    box-shadow: 0 4px 16px rgba(0,0,0,.30);
}

.page-card {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 18px;
    flex-wrap: wrap;
}

.page-card h1 {
    font-size: clamp(26px, 4vw, 34px);
    font-weight: 900;
    margin: 0 0 5px;
    letter-spacing: -.03em;
}

.page-card .subtitle {
    color: #555;
    font-size: 14px;
    max-width: 920px;
    margin: 0;
}

.page-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border-radius: 999px;
    padding: 6px 11px;
    background: #111;
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    margin-bottom: 10px;
}

.page-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.grid-resumo {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 12px;
    margin-bottom: 14px;
}

@media(min-width: 700px) {
    .grid-resumo {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(min-width: 1050px) {
    .grid-resumo {
        grid-template-columns: repeat(5, 1fr);
    }
}

.kpi {
    background: #fff;
    color: #111;
    border-radius: 18px;
    padding: 16px;
    box-shadow: 0 4px 16px rgba(0,0,0,.30);
    min-height: 116px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border-left: 6px solid #111;
}

.kpi.yellow {
    border-left-color: #ffc107;
}

.kpi.green {
    border-left-color: #198754;
}

.kpi.blue {
    border-left-color: #0d6efd;
}

.kpi.dark {
    border-left-color: #111;
}

.kpi.red {
    border-left-color: #dc3545;
}

.kpi .label {
    color: #666;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.kpi .value {
    font-size: 30px;
    line-height: 1;
    font-weight: 900;
    margin-top: 8px;
}

.kpi .icon-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.kpi .icon {
    font-size: 24px;
}

.section-title {
    font-size: 22px;
    font-weight: 900;
    margin: 22px 0 10px;
}

.section-note {
    color: rgba(255,255,255,.76);
    font-size: 13px;
    margin: -2px 0 14px;
}

.search-card form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.search-box {
    min-width: min(100%, 360px);
    flex: 1 1 360px;
}

.plans-grid,
.companies-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 14px;
}

@media(min-width: 900px) {
    .plans-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media(min-width: 1120px) {
    .companies-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.plan-card,
.company-card {
    background: #fff;
    color: #111;
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 4px 16px rgba(0,0,0,.30);
}

.plan-card {
    border-top: 5px solid #ffc107;
}

.plan-card.pro-plan {
    border-top-color: #111;
}

.plan-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.plan-title-wrap {
    display: flex;
    align-items: center;
    gap: 11px;
}

.plan-icon {
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    border-radius: 15px;
    background: #f4f4f4;
    font-size: 23px;
    flex: 0 0 auto;
}

.plan-title h3 {
    font-size: 24px;
    font-weight: 900;
    margin: 0 0 2px;
}

.plan-title small {
    color: #666;
    font-size: 13px;
}

.plan-metrics {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 12px;
}

@media(min-width: 520px) {
    .plan-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.metric {
    border-radius: 15px;
    background: #f7f7f7;
    border: 1px solid #ededed;
    padding: 13px;
}

.metric small {
    display: block;
    color: #666;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 4px;
}

.metric strong {
    display: block;
    font-size: 22px;
    line-height: 1.1;
    font-weight: 900;
}

.company-card {
    border-top: 5px solid #111;
}

.company-card.plan-free {
    border-top-color: #ffc107;
}

.company-card.plan-pro {
    border-top-color: #198754;
}

.company-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.company-title-wrap {
    display: flex;
    gap: 11px;
    align-items: flex-start;
}

.company-avatar {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    background: #111;
    color: #fff;
    font-size: 23px;
    flex: 0 0 auto;
}

.company-head h3 {
    font-size: 21px;
    line-height: 1.15;
    font-weight: 900;
    margin: 0 0 5px;
}

.company-meta {
    color: #666;
    font-size: 13px;
    line-height: 1.5;
}

.badges-row {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    flex-wrap: wrap;
    max-width: 100%;
}

.badge {
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 11px;
    font-weight: 800;
}

.usage-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}

@media(min-width: 700px) {
    .usage-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.usage-box {
    border-radius: 15px;
    border: 1px solid #e8e8e8;
    padding: 12px;
    background: #fafafa;
}

.usage-box small {
    display: block;
    color: #666;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 5px;
}

.usage-box strong {
    display: block;
    font-size: 18px;
    font-weight: 900;
    margin-bottom: 8px;
}

.usage-box .small {
    color: #666 !important;
}

.progress {
    height: 8px;
    border-radius: 999px;
    background: #e9ecef;
}

.form-area {
    border-top: 1px solid #ececec;
    padding-top: 14px;
    margin-top: 2px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 10px;
}

@media(min-width: 760px) {
    .form-grid {
        grid-template-columns: 1.25fr 1fr 1fr 1fr;
    }
}

.form-label {
    color: #444;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    margin-bottom: 5px;
}

.form-control,
.form-select {
    min-height: 42px;
    border-radius: 12px;
}

.form-actions {
    margin-top: 12px;
    display: flex;
    justify-content: flex-end;
}

.empty-state {
    padding: 26px;
    border-radius: 18px;
    background: rgba(255,255,255,.07);
    border: 1px dashed rgba(255,255,255,.25);
    color: rgba(255,255,255,.88);
    text-align: center;
    margin-bottom: 14px;
}

.alert {
    border-radius: 16px;
}

.footer {
    text-align: center;
    color: #777;
    font-size: 12px;
    padding: 22px 10px 8px;
}

@media(max-width: 700px) {
    .container-main {
        padding-left: 10px;
        padding-right: 10px;
    }

    .card-box,
    .plan-card,
    .company-card,
    .kpi {
        border-radius: 16px;
    }

    .page-actions,
    .form-actions {
        width: 100%;
    }

    .page-actions .btn,
    .form-actions .btn,
    .search-card .btn {
        width: 100%;
    }

    .badges-row {
        justify-content: flex-start;
    }

    .company-title-wrap {
        width: 100%;
    }
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

        <a href="dashboard.php" class="nav-link-top">
            dashboard
        </a>

        <a href="logout.php" class="nav-link-top">
            sair
        </a>
    </div>
</div>

<div class="container-main">

    <div class="card-box page-card">
        <div>
            <div class="page-chip">🏢 Bacuri Digital · Administração central</div>

            <h1>Empresas e Planos</h1>

            <p class="subtitle">
                Controle global dos tenants da Comandaria/Mesaria Express. Altere clientes entre FREE e PRO,
                acompanhe consumo de mesas e pedidos mensais e gerencie a situação da assinatura.
            </p>
        </div>

        <div class="page-actions">
            <a href="dashboard.php" class="btn btn-outline-dark fw-bold">← Voltar ao dashboard</a>
            <a href="logout.php" class="btn btn-warning fw-bold">Sair</a>
        </div>
    </div>

    <?php if($msg !== ''): ?>
        <div class="alert <?= $alerta === 'erro' ? 'alert-danger' : 'alert-success' ?> shadow-sm">
            <?= h($msg) ?>
        </div>
    <?php endif; ?>

    <div class="card-box search-card">
        <form method="get">
            <div class="search-box">
                <input
                    type="search"
                    name="q"
                    value="<?= h($q) ?>"
                    class="form-control"
                    placeholder="Buscar por empresa, slug ou telefone"
                >
            </div>

            <button class="btn btn-dark fw-bold" type="submit">🔎 Buscar</button>

            <?php if($q !== ''): ?>
                <a href="admin_empresas.php" class="btn btn-outline-dark fw-bold">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="grid-resumo">
        <div class="kpi yellow">
            <div class="icon-row">
                <div class="label">Empresas</div>
                <div class="icon">🏢</div>
            </div>
            <div class="value"><?= (int)$resumo['total'] ?></div>
        </div>

        <div class="kpi green">
            <div class="icon-row">
                <div class="label">Ativas</div>
                <div class="icon">✅</div>
            </div>
            <div class="value"><?= (int)$resumo['ativas'] ?></div>
        </div>

        <div class="kpi dark">
            <div class="icon-row">
                <div class="label">FREE</div>
                <div class="icon">🆓</div>
            </div>
            <div class="value"><?= (int)$resumo['free'] ?></div>
        </div>

        <div class="kpi blue">
            <div class="icon-row">
                <div class="label">PRO</div>
                <div class="icon">🚀</div>
            </div>
            <div class="value"><?= (int)$resumo['pro'] ?></div>
        </div>

        <div class="kpi red">
            <div class="icon-row">
                <div class="label">Suspensas</div>
                <div class="icon">⏸️</div>
            </div>
            <div class="value"><?= (int)$resumo['suspensas'] ?></div>
        </div>
    </div>

    <h2 class="section-title">📦 Planos disponíveis</h2>

    <div class="plans-grid mb-4">
        <?php foreach($planos as $plano): ?>
            <?php
                $nomePlanoMaiusculo = strtoupper($plano['nome']);
                $ehPro = $nomePlanoMaiusculo === 'PRO';
            ?>
            <div class="plan-card <?= $ehPro ? 'pro-plan' : '' ?>">
                <div class="plan-head">
                    <div class="plan-title-wrap">
                        <div class="plan-icon"><?= $ehPro ? '🚀' : '🆓' ?></div>

                        <div class="plan-title">
                            <h3><?= h($plano['nome']) ?></h3>
                            <small>Limites operacionais cadastrados</small>
                        </div>
                    </div>

                    <span class="badge <?= $plano['ativo'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                        <?= $plano['ativo'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                </div>

                <div class="plan-metrics">
                    <div class="metric">
                        <small>Limite de mesas</small>
                        <strong><?= (int)$plano['limite_mesas'] ?></strong>
                    </div>

                    <div class="metric">
                        <small>Pedidos por mês</small>
                        <strong><?= (int)$plano['limite_pedidos_mes'] ?></strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <h2 class="section-title">👥 Clientes cadastrados</h2>

    <p class="section-note">
        O downgrade para um plano menor é bloqueado quando o cliente já ultrapassa os limites daquele plano.
    </p>

    <?php if(!$empresas): ?>
        <div class="empty-state">
            Nenhuma empresa encontrada para os filtros informados.
        </div>
    <?php endif; ?>

    <div class="companies-grid">
        <?php foreach($empresas as $empresa): ?>
            <?php
                $pctMesas = percentualUsoAdmin($empresa['mesas_usadas'], $empresa['limite_mesas']);
                $pctPedidos = percentualUsoAdmin($empresa['pedidos_mes'], $empresa['limite_pedidos_mes']);
                $planoClasse = strtoupper($empresa['plano_nome']) === 'PRO' ? 'plan-pro' : 'plan-free';
            ?>
            <article class="company-card <?= h($planoClasse) ?>">
                <div class="company-head">
                    <div class="company-title-wrap">
                        <div class="company-avatar">🏪</div>

                        <div>
                            <h3><?= h($empresa['nome']) ?></h3>

                            <div class="company-meta">
                                slug: <strong><?= h($empresa['slug']) ?></strong><br>
                                telefone: <strong><?= h(telefoneVisualAdmin($empresa['telefone'])) ?></strong><br>
                                usuários: <strong><?= (int)$empresa['usuarios_total'] ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="badges-row">
                        <?php if($empresa['is_admin_plataforma']): ?>
                            <span class="badge text-bg-dark">Bacuri Admin</span>
                        <?php endif; ?>

                        <span class="badge <?= $empresa['status'] === 'ativa' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= h(ucfirst($empresa['status'])) ?>
                        </span>

                        <span class="badge <?= $empresa['status_assinatura'] === 'ativa' ? 'text-bg-primary' : 'text-bg-warning' ?>">
                            <?= h(ucfirst($empresa['status_assinatura'])) ?>
                        </span>

                        <span class="badge text-bg-warning text-dark">
                            Plano <?= h($empresa['plano_nome']) ?>
                        </span>
                    </div>
                </div>

                <div class="usage-grid">
                    <div class="usage-box">
                        <small>Mesas</small>
                        <strong><?= (int)$empresa['mesas_usadas'] ?> / <?= (int)$empresa['limite_mesas'] ?></strong>

                        <div class="progress" role="progressbar" aria-valuenow="<?= h((string)$pctMesas) ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar <?= classeUsoAdmin($pctMesas) ?>" style="width: <?= h((string)$pctMesas) ?>%"></div>
                        </div>
                    </div>

                    <div class="usage-box">
                        <small>Pedidos no mês</small>
                        <strong><?= (int)$empresa['pedidos_mes'] ?> / <?= (int)$empresa['limite_pedidos_mes'] ?></strong>

                        <div class="progress" role="progressbar" aria-valuenow="<?= h((string)$pctPedidos) ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar <?= classeUsoAdmin($pctPedidos) ?>" style="width: <?= h((string)$pctPedidos) ?>%"></div>
                        </div>
                    </div>

                    <div class="usage-box">
                        <small>Cadastro</small>
                        <strong><?= h(date('d/m/Y', strtotime($empresa['created_at']))) ?></strong>

                        <div class="small">
                            Atualizado em <?= h(date('d/m/Y H:i', strtotime($empresa['updated_at']))) ?>
                        </div>
                    </div>
                </div>

                <form method="post" action="admin_empresas_salvar.php" class="form-area">
                    <input type="hidden" name="csrf" value="<?= h((string)$_SESSION['admin_empresas_csrf']) ?>">
                    <input type="hidden" name="empresa_id" value="<?= (int)$empresa['id'] ?>">

                    <div class="form-grid">
                        <div>
                            <label class="form-label">Telefone</label>

                            <input
                                type="text"
                                name="telefone"
                                maxlength="30"
                                class="form-control"
                                value="<?= h($empresa['telefone']) ?>"
                                placeholder="DDD + número"
                            >
                        </div>

                        <div>
                            <label class="form-label">Plano</label>

                            <select name="plano_id" class="form-select">
                                <?php foreach($planos as $plano): ?>
                                    <option value="<?= (int)$plano['id'] ?>" <?= $plano['id'] === $empresa['plano_id'] ? 'selected' : '' ?>>
                                        <?= h($plano['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Empresa</label>

                            <select name="status" class="form-select">
                                <option value="ativa" <?= $empresa['status'] === 'ativa' ? 'selected' : '' ?>>Ativa</option>
                                <option value="inativa" <?= $empresa['status'] === 'inativa' ? 'selected' : '' ?>>Inativa</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Assinatura</label>

                            <select name="status_assinatura" class="form-select">
                                <option value="ativa" <?= $empresa['status_assinatura'] === 'ativa' ? 'selected' : '' ?>>Ativa</option>
                                <option value="suspensa" <?= $empresa['status_assinatura'] === 'suspensa' ? 'selected' : '' ?>>Suspensa</option>
                                <option value="cancelada" <?= $empresa['status_assinatura'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-dark fw-bold" type="submit">
                            Salvar alterações
                        </button>
                    </div>
                </form>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="footer">
        Comandaria Express<br>
        Administração de empresas e planos
    </div>
</div>

</body>
</html>
