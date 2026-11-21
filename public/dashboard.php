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

$nome = $_SESSION['user_nome'] ?? 'Administrador';
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

<style>
body {
    background: #111;
    color: #fff;
    font-family: Arial, sans-serif;
    min-height: 100vh;
}

.header {
    text-align: center;
    padding: 20px 10px 10px;
}

.header img {
    max-width: 430px;
    width: 100%;
}

.userbox {
    text-align: center;
    margin-bottom: 20px;
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

.grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 14px;
    max-width: 1050px;
    margin: 0 auto;
    padding: 10px;
}

@media(min-width: 600px) {
    .grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(min-width: 900px) {
    .grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.card-menu {
    background: #fff;
    color: #111;
    border-radius: 18px;
    padding: 20px;
    text-decoration: none;
    min-height: 150px;
    box-shadow: 0 4px 16px rgba(0,0,0,.3);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: .2s ease;
}

.card-menu:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,.45);
    color: #111;
}

.icon {
    font-size: 36px;
    margin-bottom: 8px;
}

.title {
    font-size: 19px;
    font-weight: 800;
    margin-bottom: 4px;
}

.desc {
    font-size: 13px;
    color: #555;
}

.badge-status {
    align-self: flex-end;
    margin-top: 12px;
    border-radius: 999px;
    padding: 4px 9px;
    font-size: 11px;
    background: #111;
    color: #fff;
    text-align: right;
    white-space: nowrap;
}


.upgrade-price {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    padding: 9px 13px;
    border-radius: 999px;
    background: #fff3cd;
    color: #6b4f00;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
    transition: .2s ease;
    font-size: 13px;
    font-weight: 800;
    border: 1px solid rgba(107,79,0,.18);
}

.upgrade-price:hover {
    color: #6b4f00;
    transform: translateY(-1px);
    filter: brightness(1.02);
}

.footer {
    text-align: center;
    color: #777;
    font-size: 12px;
    padding: 25px 10px;
}

.logout {
    color: #ffc107;
    text-decoration: none;
}

.logout:hover {
    color: #ffda6a;
}

/* Mantém o conteúdo do card à esquerda e o status/valor à direita */
.card-menu > div:first-child {
    width: 100%;
    text-align: left;
}

.card-menu > .badge-status {
    align-self: flex-end;
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
            <strong><?= h($nome) ?></strong>
        </span>

        <a href="logout.php" class="logout">
            sair
        </a>
    </div>
</div>

<div class="grid">

    <a href="index.php" class="card-menu">
        <div>
            <div class="icon">🧾</div>

            <div class="title">
                Atendimento
            </div>

            <div class="desc">
                Abrir mesas, lançar pedidos e enviar comandas para a cozinha.
            </div>
        </div>

        <div class="badge-status">
            atendente/admin
        </div>
    </a>

    <a href="painel_cozinha.php" class="card-menu">
        <div>
            <div class="icon">🍳</div>

            <div class="title">
                Cozinha
            </div>

            <div class="desc">
                Visualizar pedidos em preparo e marcar itens como prontos.
            </div>
        </div>

        <div class="badge-status">
            cozinha/admin
        </div>
    </a>

    <a href="painel_caixa.php" class="card-menu">
        <div>
            <div class="icon">💰</div>

            <div class="title">
                Caixa
            </div>

            <div class="desc">
                Conferir comandas, imprimir, cancelar e fechar contas.
            </div>
        </div>

        <div class="badge-status">
            caixa/admin
        </div>
    </a>
    
    

    <a href="controle_prazo.php" class="card-menu">
        <div>
            <div class="icon">💳</div>

            <div class="title">
                Contas a Prazo
            </div>

            <div class="desc">
                Controlar contas em aberto, discriminar itens, imprimir e registrar recebimentos.
            </div>
        </div>

        <div class="badge-status">
            <?= (int)$prazoResumo['quantidade_abertas'] ?> em aberto • R$ <?= dinheiroDashboard($prazoResumo['total_aberto']) ?>
        </div>
    </a>

    <a href="produtos.php" class="card-menu">
        <div>
            <div class="icon">🍔</div>

            <div class="title">
                Produtos
            </div>

            <div class="desc">
                Gerenciar produtos, preços, tempo de preparo, observações e adicionais.
            </div>
        </div>

        <div class="badge-status">
            admin
        </div>
    </a>

    <a href="categorias.php" class="card-menu">
        <div>
            <div class="icon">🏷️</div>

            <div class="title">
                Categorias
            </div>

            <div class="desc">
                Organizar produtos por grupos como lanches, bebidas, porções e combos.
            </div>
        </div>

        <div class="badge-status">
            admin
        </div>
    </a>
    
    
        
    <a href="gerar_qr_mesas.php" class="card-menu">
        <div>
            <div class="icon">🪑</div>

            <div class="title">
                Gerar Mesas
            </div>

            <div class="desc">
                Criar e administrar mesas com QR Code para pedidos da Mesaria Express.
            </div>
        </div>

        <div class="badge-status">
            admin
        </div>
    </a>
    
    


    <?php if(tenantPlanoPermiteUsuarios($usoPlano['plano'])): ?>
    <a href="usuarios.php" class="card-menu">
        <div>
            <div class="icon">👥</div>

            <div class="title">
                Usuários
            </div>

            <div class="desc">
                Cadastrar operadores, caixas, cozinha, atendentes e administradores.
            </div>
        </div>

        <div class="badge-status">
            admin
        </div>
    </a>
    <?php endif; ?>

    <a href="relatorios.php" class="card-menu">
        <div>
            <div class="icon">📊</div>

            <div class="title">
                Relatórios
            </div>

            <div class="desc">
                Consultar vendas, produtos mais vendidos, ticket médio e histórico de comandas.
            </div>
        </div>

        <div class="badge-status">
            admin
        </div>
    </a>


    <div class="card-menu">
        <div>
            <div class="icon">📦</div>

            <div class="title">
                Plano <?= h((string)($usoPlano['plano']['plano_nome'] ?? authPlanoNome())) ?>
            </div>

            <div class="desc">
                Mesas: <?= (int)$usoPlano['mesas_usadas'] ?> / <?= (int)$usoPlano['mesas_limite'] ?><br>
                Pedidos no mês: <?= (int)$usoPlano['pedidos_usados'] ?> / <?= (int)$usoPlano['pedidos_limite'] ?>
            </div>

            <?php if($planoAtualDashboard === 'FREE' && $whatsappUpgradeUrl !== ''): ?>
                <a
                    href="<?= h($whatsappUpgradeUrl) ?>"
                    class="upgrade-price"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Solicitar upgrade para o plano PRO por WhatsApp"
                >
                    ⭐ PRO por R$ 229,90/mês
                </a>
            <?php endif; ?>
        </div>

        <div class="badge-status">
            uso mensal <?= h((string)$usoPlano['pedidos_percentual']) ?>%
        </div>
    </div>
    
    
    <?php if($adminPlataforma): ?>
        <a href="admin_empresas.php" class="card-menu">
            <div>
                <div class="icon">🏢</div>

                <div class="title">
                    Empresas e Planos
                </div>

                <div class="desc">
                    Administrar tenants, acompanhar limites e alterar clientes entre FREE e PRO.
                </div>
            </div>

            <div class="badge-status">
                Bacuri Digital
            </div>
        </a>
    <?php endif; ?>

</div>

<div class="footer">
    Comandaria Express<br>
    Painel administrativo
</div>

<?php require __DIR__ . '/includes/onboarding_tour.php'; ?>

</body>
</html>