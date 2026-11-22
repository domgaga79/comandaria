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

<link rel="stylesheet" href="assets/management.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/staff.css?v=2">
</head>

<body class="cd-staff">
<?php $staffPage = 'admin_empresas.php'; require __DIR__ . '/includes/staff_nav.php'; ?>

<main class="cd-management cd-companies">

    <header class="cd-page-head">
        <div>
            <h1>Empresas e planos</h1>
            <p>Acompanhe o uso por empresa e gerencie os planos e as assinaturas.</p>
        </div>
    </header>

    <?php if($msg !== ''): ?>
        <div role="status" class="alert <?= $alerta === 'erro' ? 'alert-danger' : 'alert-success' ?> shadow-sm">
            <?= h($msg) ?>
        </div>
    <?php endif; ?>

    <div class="card-box search-card">
        <form method="get">
            <div class="search-box">
                <input
                    type="search"
                    name="q"
                    aria-label="Buscar empresa"
                    value="<?= h($q) ?>"
                    class="form-control"
                    placeholder="Buscar por empresa, slug ou telefone"
                >
            </div>

            <button class="btn cd-primary" type="submit">Buscar</button>

            <?php if($q !== ''): ?>
                <a href="admin_empresas.php" class="btn cd-outline">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="grid-resumo">
        <div class="kpi yellow">
            <div class="label">Empresas</div>
            <div class="value"><?= (int)$resumo['total'] ?></div>
        </div>

        <div class="kpi green">
            <div class="label">Ativas</div>
            <div class="value"><?= (int)$resumo['ativas'] ?></div>
        </div>

        <div class="kpi dark">
            <div class="label">FREE</div>
            <div class="value"><?= (int)$resumo['free'] ?></div>
        </div>

        <div class="kpi blue">
            <div class="label">PRO</div>
            <div class="value"><?= (int)$resumo['pro'] ?></div>
        </div>

        <div class="kpi red">
            <div class="label">Suspensas</div>
            <div class="value"><?= (int)$resumo['suspensas'] ?></div>
        </div>
    </div>

    <h2 class="section-title">Planos disponíveis</h2>

    <div class="plans-grid mb-4">
        <?php foreach($planos as $plano): ?>
            <?php
                $nomePlanoMaiusculo = strtoupper($plano['nome']);
                $ehPro = $nomePlanoMaiusculo === 'PRO';
            ?>
            <div class="plan-card <?= $ehPro ? 'pro-plan' : '' ?>">
                <div class="plan-head">
                    <div class="plan-title-wrap">
                        <div class="plan-title">
                            <h3><?= h($plano['nome']) ?></h3>
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

    <h2 class="section-title">Empresas cadastradas</h2>

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

                        <div class="progress" aria-label="Uso de mesas" role="progressbar" aria-valuenow="<?= h((string)$pctMesas) ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar <?= classeUsoAdmin($pctMesas) ?>" style="width: <?= h((string)$pctMesas) ?>%"></div>
                        </div>
                    </div>

                    <div class="usage-box">
                        <small>Pedidos no mês</small>
                        <strong><?= (int)$empresa['pedidos_mes'] ?> / <?= (int)$empresa['limite_pedidos_mes'] ?></strong>

                        <div class="progress" aria-label="Uso de pedidos" role="progressbar" aria-valuenow="<?= h((string)$pctPedidos) ?>" aria-valuemin="0" aria-valuemax="100">
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
                            <label class="form-label" for="telefone-<?= (int)$empresa['id'] ?>">Telefone</label>

                            <input
                                type="text"
                                name="telefone" id="telefone-<?= (int)$empresa['id'] ?>"
                                maxlength="30"
                                class="form-control"
                                value="<?= h($empresa['telefone']) ?>"
                                placeholder="DDD + número"
                            >
                        </div>

                        <div>
                            <label class="form-label" for="plano_id-<?= (int)$empresa['id'] ?>">Plano</label>

                            <select name="plano_id" id="plano_id-<?= (int)$empresa['id'] ?>" class="form-select">
                                <?php foreach($planos as $plano): ?>
                                    <option value="<?= (int)$plano['id'] ?>" <?= $plano['id'] === $empresa['plano_id'] ? 'selected' : '' ?>>
                                        <?= h($plano['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="status-<?= (int)$empresa['id'] ?>">Empresa</label>

                            <select name="status" id="status-<?= (int)$empresa['id'] ?>" class="form-select">
                                <option value="ativa" <?= $empresa['status'] === 'ativa' ? 'selected' : '' ?>>Ativa</option>
                                <option value="inativa" <?= $empresa['status'] === 'inativa' ? 'selected' : '' ?>>Inativa</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="status_assinatura-<?= (int)$empresa['id'] ?>">Assinatura</label>

                            <select name="status_assinatura" id="status_assinatura-<?= (int)$empresa['id'] ?>" class="form-select">
                                <option value="ativa" <?= $empresa['status_assinatura'] === 'ativa' ? 'selected' : '' ?>>Ativa</option>
                                <option value="suspensa" <?= $empresa['status_assinatura'] === 'suspensa' ? 'selected' : '' ?>>Suspensa</option>
                                <option value="cancelada" <?= $empresa['status_assinatura'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn cd-primary" type="submit">
                            Salvar alterações
                        </button>
                    </div>
                </form>
            </article>
        <?php endforeach; ?>
    </div>

</main>

</body>
</html>
