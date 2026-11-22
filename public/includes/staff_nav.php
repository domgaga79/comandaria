<?php
$staffRole = authTipo();
$staffHome = authDestinoPorTipo($staffRole)['url'];
$staffGroups = [
    'Operação' => [
        ['dashboard.php', 'Visão geral', 'speedometer2', ['admin']],
        ['index.php', 'Atendimento', 'grid', ['admin', 'atendente']],
        ['painel_cozinha.php', 'Cozinha', 'fire', ['admin', 'cozinha']],
        ['painel_caixa.php', 'Caixa', 'wallet2', ['admin', 'caixa']],
        ['controle_prazo.php', 'Contas a prazo', 'calendar-check', ['admin']],
    ],
    'Gestão' => [
        ['produtos.php', 'Produtos', 'box-seam', ['admin']],
        ['categorias.php', 'Categorias', 'tags', ['admin']],
        ['gerar_qr_mesas.php', 'QR Code das mesas', 'qr-code', ['admin']],
        ['usuarios.php', 'Usuários', 'people', ['admin']],
        ['relatorios.php', 'Relatórios', 'bar-chart', ['admin']],
    ],
];

if (!empty($adminPlataforma) || $staffPage === 'admin_empresas.php') {
    $staffGroups['Gestão'][] = ['admin_empresas.php', 'Empresas e planos', 'buildings', ['admin']];
}

$staffName = authNome() ?: 'Operador';
$staffCompany = authEmpresaNome();
?>
<nav class="cd-sidebar no-print" aria-label="Navegação principal">
    <div class="cd-sidebar-top">
        <a href="<?= htmlspecialchars($staffHome, ENT_QUOTES, 'UTF-8') ?>" class="cd-brand">
            <img class="cd-brand-mark" src="images/comandaria-marca.png" width="38" height="38" alt="">
            <span class="cd-brand-name">Comandaria<span> Express</span></span>
        </a>
        <button class="cd-menu-toggle" type="button" aria-controls="cd-sidebar-links" aria-expanded="false">
            <i class="bi bi-list" aria-hidden="true"></i> Menu
        </button>
    </div>

    <div id="cd-sidebar-links" class="cd-sidebar-links">
        <?php foreach ($staffGroups as $staffGroup => $staffEntries): ?>
            <?php $staffAllowed = array_filter($staffEntries, static fn($entry) => in_array($staffRole, $entry[3], true)); ?>
            <?php if ($staffAllowed): ?>
                <div class="cd-nav-group">
                    <span class="cd-nav-label"><?= $staffGroup ?></span>
                    <?php foreach ($staffAllowed as [$staffHref, $staffLabel, $staffIcon]): ?>
                        <a href="<?= $staffHref ?>"<?= $staffPage === $staffHref ? ' aria-current="page"' : '' ?>>
                            <i class="bi bi-<?= $staffIcon ?>" aria-hidden="true"></i>
                            <?= $staffLabel ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="cd-sidebar-account">
            <span class="cd-account-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
            <div class="cd-account-name">
                <strong><?= htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if ($staffCompany): ?>
                    <span><?= htmlspecialchars($staffCompany, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
            <a href="logout.php" class="cd-log-out" aria-label="Sair da conta" title="Sair da conta">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</nav>
<script src="assets/staff.js?v=2" defer></script>
