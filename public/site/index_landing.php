<?php

$landingAtRoot = $landingAtRoot ?? false;
$whatsappNumero = '5571993143374';
$linkWhatsapp = 'https://wa.me/' . $whatsappNumero . '?text=';
$linkWhatsappFree = $linkWhatsapp . rawurlencode('Olá, Bacuri Digital! Quero começar no plano FREE da Comandaria Express.');
$mensagemPro = $landingAtRoot
    ? 'Olá, Bacuri Digital! Quero saber mais sobre o plano PRO da Comandaria Express por R$ 59,90/mês.'
    : 'Olá, Bacuri Digital! Quero saber mais sobre o plano PRO da Comandaria Express.';
$linkWhatsappPro = $linkWhatsapp . rawurlencode($mensagemPro);
$linkWhatsappDemo = $linkWhatsapp . rawurlencode('Olá, Bacuri Digital! Quero conhecer melhor a Comandaria Express.');
$linkLogin = $landingAtRoot ? 'login.php' : '../login.php';
$caminhoPublico = $landingAtRoot ? 'public/' : '../';

$recursos = [
    ['Atendimento e comandas', 'Lance itens com observações e adicionais e envie a comanda para a cozinha.'],
    ['Gestão de mesas', 'Veja as mesas disponíveis e em atendimento e acompanhe as contas abertas.'],
    ['Painel da cozinha', 'Organize a fila e atualize o preparo de cada pedido em tempo real.'],
    ['Caixa e fechamento', 'Acompanhe os pagamentos e feche a conta com os valores conferidos.'],
    ['Pedidos por QR Code', 'Ofereça um cardápio por mesa para o cliente pedir e acompanhar.'],
    ['Produtos e adicionais', 'Edite o cardápio, categorias, preços e opções extras.'],
    ['Relatórios operacionais', 'Consulte movimentações, pedidos e resultados do período.'],
    ['Gestão por estabelecimento', 'Separe as operações de cada estabelecimento em sua própria conta.'],
];

function landingEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f7f2">
    <meta name="description" content="Comandaria Express: pedidos, mesas, cozinha, caixa e cardápio por QR Code para bares e restaurantes.">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Comandaria Express">
    <meta property="og:description" content="Pedidos, mesas, cozinha e caixa em um só sistema.">
    <meta property="og:image" content="https://comandaria.bacuridigital.com/images/comandaria_logo_og_1200x630.png">
    <title>Comandaria Express | Pedidos, Mesas, Cozinha e Caixa</title>
    <link rel="icon" href="<?= landingEscape($caminhoPublico) ?>images/comandaria_logo.png" type="image/png">
    <link rel="stylesheet" href="<?= landingEscape($caminhoPublico) ?>assets/guest-landing.css?v=2">
</head>
<body>
    <header class="site-header">
        <div class="site-container navbar" id="navbar">
            <a class="wordmark" href="#inicio">
                <img src="<?= landingEscape($caminhoPublico) ?>images/comandaria_logo.png" alt="" width="40" height="40">
                Comandaria Express
            </a>
            <nav class="site-nav" id="siteNav" aria-label="Navegação principal">
                <a href="#funcionalidades">Funcionalidades</a>
                <a href="#diferenciais">Como funciona</a>
                <a href="#planos">Planos</a>
                <a href="#publico">Para quem é</a>
            </nav>
            <div class="header-actions">
                <a class="button button-outline" href="<?= landingEscape($linkLogin) ?>">Entrar</a>
                <button class="nav-toggle" id="mobileToggle" type="button" aria-controls="siteNav" aria-expanded="false">Menu</button>
            </div>
        </div>
    </header>

    <main id="inicio">
        <section class="site-container hero">
            <div class="hero-copy">
                <h1>Do pedido ao caixa, em um só lugar.</h1>
                <p>Organize o atendimento, acompanhe o preparo e feche as contas em um sistema feito para bares, lanchonetes e restaurantes.</p>
                <div class="actions">
                    <?php if($landingAtRoot): ?>
                        <a class="button button-primary" href="<?= landingEscape($linkLogin) ?>">Começar grátis</a>
                    <?php else: ?>
                        <a class="button button-primary" href="<?= landingEscape($linkWhatsappFree) ?>" target="_blank" rel="noopener">Começar grátis</a>
                    <?php endif; ?>
                    <a class="button button-outline" href="#planos">Ver planos</a>
                </div>
            </div>
            <figure class="hero-image">
                <img src="<?= landingEscape($caminhoPublico) ?>images/operacao-restaurante.webp" width="1120" height="747" alt="Atendente usando um tablet no balcão de um restaurante">
            </figure>
        </section>

        <section class="section section-tinted" id="funcionalidades">
            <div class="site-container">
                <h2>Recursos para cada etapa do serviço</h2>
                <div class="feature-grid">
                    <?php foreach($recursos as [$titulo, $descricao]): ?>
                        <article class="feature-card">
                            <h3><?= landingEscape($titulo) ?></h3>
                            <p><?= landingEscape($descricao) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section site-container" id="diferenciais">
            <div class="flow">
                <h2>Como funciona no salão</h2>
                <ol>
                    <li><strong>Receba o pedido</strong><span>A equipe lança a comanda ou o cliente pede pelo QR Code da mesa.</span></li>
                    <li><strong>Acompanhe o preparo</strong><span>A cozinha recebe os itens e informa quando estão prontos.</span></li>
                    <li><strong>Feche a conta</strong><span>O caixa revisa os valores e registra os pagamentos.</span></li>
                </ol>
            </div>
        </section>

        <section class="section section-tinted" id="planos">
            <div class="site-container">
                <h2>Planos</h2>
                <div class="plans">
                    <article class="plan">
                        <h3>FREE</h3>
                        <p class="price"><strong>R$ 0</strong> /mês</p>
                        <ul>
                            <li>Até 10 mesas cadastradas</li>
                            <li>Até 100 pedidos por mês</li>
                            <?php if($landingAtRoot): ?><li>Login pelo Google para 1 usuário</li><?php endif; ?>
                            <li>Fluxo de atendimento, cozinha e caixa</li>
                        </ul>
                        <div class="actions">
                            <a class="button button-outline" href="<?= landingEscape($linkWhatsappFree) ?>" target="_blank" rel="noopener">Quero o FREE</a>
                        </div>
                    </article>
                    <article class="plan plan-pro">
                        <h3>PRO</h3>
                        <p class="price"><strong><?= $landingAtRoot ? 'R$ 59,90' : 'R$ 129,90' ?></strong> /mês</p>
                        <?php if($landingAtRoot): ?>
                            <ul>
                                <li>Mesas ilimitadas</li>
                                <li>Pedidos ilimitados</li>
                                <li>Controle de usuários e acessos</li>
                            </ul>
                        <?php else: ?>
                            <ul>
                                <li>Até 30 mesas cadastradas</li>
                                <li>Até 1.000 pedidos por mês</li>
                                <li>Upgrade direto, sem trocar de sistema</li>
                            </ul>
                        <?php endif; ?>
                        <div class="actions">
                            <a class="button button-primary" href="<?= landingEscape($linkWhatsappPro) ?>" target="_blank" rel="noopener">Quero o PRO</a>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="section site-container" id="publico">
            <h2>Para bares, lanchonetes e restaurantes</h2>
            <div class="feature-grid audience">
                <article class="feature-card"><h3>Bares</h3><p>Mantenha comandas e consumos por mesa em ordem durante o atendimento.</p></article>
                <article class="feature-card"><h3>Lanchonetes</h3><p>Envie pedidos do balcão para a cozinha e acompanhe a retirada.</p></article>
                <article class="feature-card"><h3>Restaurantes</h3><p>Conecte salão, cozinha e caixa durante todo o serviço.</p></article>
            </div>
        </section>

        <section class="site-container">
            <div class="contact">
                <div>
                    <h2>Quer conhecer a Comandaria?</h2>
                    <p>Fale com a Bacuri Digital sobre sua operação.</p>
                </div>
                <a class="button button-light" href="<?= landingEscape($linkWhatsappDemo) ?>" target="_blank" rel="noopener">Conversar pelo WhatsApp</a>
            </div>
        </section>
    </main>

    <footer class="site-container site-footer">
        <span>Comandaria Express</span>
        <span>© <?= date('Y') ?> Bacuri Digital</span>
    </footer>

    <script>
        const navbar = document.getElementById('navbar');
        const mobileToggle = document.getElementById('mobileToggle');

        function atualizarMenu(aberto) {
            navbar.classList.toggle('open', aberto);
            mobileToggle.setAttribute('aria-expanded', String(aberto));
        }

        mobileToggle.addEventListener('click', () => {
            atualizarMenu(mobileToggle.getAttribute('aria-expanded') !== 'true');
        });

        document.querySelectorAll('.site-nav a').forEach(link => {
            link.addEventListener('click', () => atualizarMenu(false));
        });
    </script>
</body>
</html>
