<?php
require __DIR__ . '/auth.php';
requireRole(['admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require __DIR__ . '/conexao.php';
require __DIR__ . '/../core/tenant_core.php';
require __DIR__ . '/../core/plataforma_admin_core.php';

$empresaId = tenantResolverEmpresaId(authEmpresaId());

if(isset($conn) && $conn instanceof mysqli && method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dinheiroDashboard($value): string {
    return number_format((float)$value, 2, ',', '.');
}

$adminPlataforma = false;

$empresaDashboard = [
    'id' => $empresaId,
    'nome' => authEmpresaNome() ?: 'Empresa não identificada',
    'slug' => authEmpresaSlug() ?: '',
    'telefone' => ''
];

$planoAtualDashboard = strtoupper((string)(authPlanoNome() ?: ''));
$whatsappUpgradeUrl = '';

$prazoResumo = [
    'quantidade_abertas' => 0,
    'total_aberto' => 0.00
];

$usoPlano = [
    'plano' => [
        'plano_nome' => authPlanoNome()
    ],
    'mesas_usadas' => 0,
    'mesas_limite' => 0,
    'mesas_percentual' => 0,
    'pedidos_usados' => 0,
    'pedidos_limite' => 0,
    'pedidos_percentual' => 0
];

try {

    $sqlPrazo = "
        SELECT
            COUNT(*) AS quantidade_abertas,
            COALESCE(SUM(total), 0) AS total_aberto
        FROM pedidos
        WHERE empresa_id = ?
        AND forma_pagamento = 'prazo'
        AND (
            prazo_status IS NULL
            OR LOWER(CAST(prazo_status AS CHAR)) NOT IN ('pago', 'recebido', 'quitado')
        )
    ";

    $stmtPrazo = $conn->prepare($sqlPrazo);

    if(!$stmtPrazo){
        throw new Exception($conn->error);
    }

    $stmtPrazo->bind_param('i', $empresaId);
    $stmtPrazo->execute();

    $resPrazo = $stmtPrazo->get_result();

    if($resPrazo){
        $rowPrazo = $resPrazo->fetch_assoc() ?: [];

        $prazoResumo = [
            'quantidade_abertas' => (int)($rowPrazo['quantidade_abertas'] ?? 0),
            'total_aberto' => (float)($rowPrazo['total_aberto'] ?? 0)
        ];
    }

    $stmtPrazo->close();

    $usoPlano = tenantResumoUsoPlano($conn, $empresaId);
    $adminPlataforma = plataformaEhAdminPlataforma($conn);

    $stmtEmpresaDashboard = $conn->prepare("
        SELECT
            id,
            nome,
            slug,
            COALESCE(telefone, '') AS telefone
        FROM empresas
        WHERE id = ?
        LIMIT 1
    ");

    if($stmtEmpresaDashboard){
        $stmtEmpresaDashboard->bind_param('i', $empresaId);
        $stmtEmpresaDashboard->execute();
        $resEmpresaDashboard = $stmtEmpresaDashboard->get_result();
        $rowEmpresaDashboard = $resEmpresaDashboard ? ($resEmpresaDashboard->fetch_assoc() ?: []) : [];

        if($rowEmpresaDashboard){
            $empresaDashboard = [
                'id' => (int)($rowEmpresaDashboard['id'] ?? $empresaId),
                'nome' => (string)($rowEmpresaDashboard['nome'] ?? $empresaDashboard['nome']),
                'slug' => (string)($rowEmpresaDashboard['slug'] ?? $empresaDashboard['slug']),
                'telefone' => (string)($rowEmpresaDashboard['telefone'] ?? '')
            ];
        }

        $stmtEmpresaDashboard->close();
    }

    $planoAtualDashboard = strtoupper((string)($usoPlano['plano']['plano_nome'] ?? authPlanoNome() ?? ''));

    if($planoAtualDashboard === 'FREE'){
        $telefoneEmpresaUpgrade = trim((string)($empresaDashboard['telefone'] ?? ''));
        $emailEmpresaUpgrade = trim((string)($_SESSION['user_email'] ?? ''));

        $mensagemUpgrade = "Olá, Bacuri Digital! Quero fazer upgrade da minha empresa para o plano PRO por R$ 229,90 mensal.

";
        $mensagemUpgrade .= "Dados da empresa:
";
        $mensagemUpgrade .= "- ID: " . (int)($empresaDashboard['id'] ?? $empresaId) . "
";
        $mensagemUpgrade .= "- Empresa: " . (string)($empresaDashboard['nome'] ?? '') . "
";
        $mensagemUpgrade .= "- Slug: " . (string)($empresaDashboard['slug'] ?? '') . "
";

        if($telefoneEmpresaUpgrade !== ''){
            $mensagemUpgrade .= "- Telefone cadastrado: " . $telefoneEmpresaUpgrade . "
";
        } else {
            $mensagemUpgrade .= "- E-mail de contato: " . ($emailEmpresaUpgrade !== '' ? $emailEmpresaUpgrade : 'não informado') . "
";
        }

        $mensagemUpgrade .= "- Plano atual: FREE
";
        $mensagemUpgrade .= "- Mesas em uso: " . (int)($usoPlano['mesas_usadas'] ?? 0) . " / " . (int)($usoPlano['mesas_limite'] ?? 0) . "
";
        $mensagemUpgrade .= "- Pedidos no mês: " . (int)($usoPlano['pedidos_usados'] ?? 0) . " / " . (int)($usoPlano['pedidos_limite'] ?? 0) . "

";
        $mensagemUpgrade .= "Por favor, solicito a ativação do plano PRO.";

        // Evita que asteriscos residuais de versões anteriores alterem a leitura no WhatsApp.
        $mensagemUpgrade = str_replace('*', '', $mensagemUpgrade);

        $whatsappUpgradeUrl = 'https://wa.me/5571993143374?text=' . rawurlencode($mensagemUpgrade);
    }

} catch(Throwable $e){
    error_log('[DASHBOARD PRAZO] ' . $e->getMessage());
}

if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
$dashboardSections = [
    'Operação' => [
        ['index.php', 'grid', 'Atendimento', 'Abra mesas e envie pedidos à cozinha.'],
        ['painel_cozinha.php', 'fire', 'Cozinha', 'Veja a fila e acompanhe o preparo.'],
        ['painel_caixa.php', 'wallet2', 'Caixa', 'Receba pagamentos e feche contas.'],
        ['controle_prazo.php', 'calendar-check', 'Contas a prazo', 'Consulte e registre recebimentos.'],
    ],
    'Gestão' => [
        ['produtos.php', 'box-seam', 'Produtos', 'Atualize cardápio, preços e adicionais.'],
        ['categorias.php', 'tags', 'Categorias', 'Organize os produtos do cardápio.'],
        ['gerar_qr_mesas.php', 'qr-code', 'QR Code das mesas', 'Gere e imprima os códigos para o salão.'],
        ['usuarios.php', 'people', 'Usuários', 'Gerencie os acessos da equipe.'],
        ['relatorios.php', 'bar-chart', 'Relatórios', 'Consulte vendas e histórico de comandas.'],
    ]
];

if ($adminPlataforma) {
    $dashboardSections['Gestão'][] = ['admin_empresas.php', 'buildings', 'Empresas e planos', 'Acompanhe empresas e gerencie os planos.'];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Dashboard - Comandaria Express</title>

<meta property="og:title" content="Comandaria Express">
<meta property="og:description" content="Painel administrativo">
<meta property="og:type" content="website">
<meta property="og:image" content="images/comandaria_logo_og.png">

<link rel="icon" href="images/comandaria_logo.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/dashboard.css?v=2">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/staff.css?v=2">
</head>
<body class="cd-staff">
<?php $staffPage = 'dashboard.php'; require __DIR__ . '/includes/staff_nav.php'; ?>

<main class="dash-main" id="conteudo">
    <header class="dash-heading">
        <div>
            <p class="dash-company"><?= h($empresaDashboard['nome']) ?></p>
            <h1>Visão geral</h1>
            <p>Acompanhe a operação da sua empresa.</p>
        </div>
        <a class="dash-cta" href="index.php"><i class="bi bi-grid" aria-hidden="true"></i> Abrir atendimento</a>
    </header>

    <div class="dash-summary" aria-label="Resumo da empresa">
        <div class="dash-stat">
            <span><i class="bi bi-grid" aria-hidden="true"></i> Mesas cadastradas</span>
            <strong><?= (int)$usoPlano['mesas_usadas'] ?> <small>/ <?= (int)$usoPlano['mesas_limite'] ?></small></strong>
        </div>
        <div class="dash-stat">
            <span><i class="bi bi-receipt" aria-hidden="true"></i> Pedidos no mês</span>
            <strong><?= (int)$usoPlano['pedidos_usados'] ?> <small>/ <?= (int)$usoPlano['pedidos_limite'] ?></small></strong>
        </div>
        <div class="dash-stat">
            <span><i class="bi bi-calendar-check" aria-hidden="true"></i> Contas a prazo em aberto</span>
            <strong>R$ <?= dinheiroDashboard($prazoResumo['total_aberto']) ?></strong>
            <small><?= (int)$prazoResumo['quantidade_abertas'] ?> em aberto</small>
        </div>
    </div>

    <div class="grid">
        <?php foreach ($dashboardSections as $dashboardSection => $dashboardLinks): ?>
            <section class="dash-section">
                <h2><?= h($dashboardSection) ?></h2>
                <div class="dash-links">
                    <?php foreach ($dashboardLinks as [$href, $icon, $label, $description]): ?>
                        <a href="<?= h($href) ?>" class="card-menu">
                            <i class="bi bi-<?= h($icon) ?> dash-link-icon" aria-hidden="true"></i>
                            <span class="dash-link-copy">
                                <strong><?= h($label) ?></strong>
                                <span><?= h($description) ?></span>
                            </span>
                            <i class="bi bi-arrow-up-right dash-arrow" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="dash-plan">
        <div>
            <h2>Plano <?= h($planoAtualDashboard) ?></h2>
            <p><?= h((string)$usoPlano['pedidos_percentual']) ?>% do limite mensal de pedidos utilizado</p>
        </div>
        <?php if ($planoAtualDashboard === 'FREE' && $whatsappUpgradeUrl !== ''): ?>
            <a href="<?= h($whatsappUpgradeUrl) ?>" target="_blank" rel="noopener noreferrer" class="dash-upgrade">
                <i class="bi bi-whatsapp" aria-hidden="true"></i> Plano PRO · R$ 229,90/mês
            </a>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/includes/onboarding_tour.php'; ?>
</body>
</html>
