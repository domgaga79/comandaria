<?php

require_once dirname(__DIR__, 2) . '/config/comercial.php';

$landingAtRoot = $landingAtRoot ?? false;
$whatsappNumero = '5571993143374';
$linkWhatsapp = 'https://wa.me/' . $whatsappNumero . '?text=';
$linkWhatsappFree = $linkWhatsapp . rawurlencode('Olá, Bacuri Digital! Quero começar no plano FREE da Comandaria Express.');
$mensagemPro = 'Olá, Bacuri Digital! Quero saber mais sobre o plano PRO da Comandaria Express por ' . COMANDARIA_PRECO_PRO . '/mês.';
$linkWhatsappPro = $linkWhatsapp . rawurlencode($mensagemPro);
$linkWhatsappDemo = $linkWhatsapp . rawurlencode('Olá, Bacuri Digital! Quero conhecer melhor a Comandaria Express.');
$linkLogin = $landingAtRoot ? 'login.php' : '../login.php';
$caminhoPublico = $landingAtRoot ? '' : '../';

$recursos = [
    ['receipt', 'Atendimento e comandas', 'Lance itens com observações e adicionais e envie a comanda para a cozinha.'],
    ['grid', 'Gestão de mesas', 'Veja as mesas disponíveis e em atendimento e acompanhe as contas abertas.'],
    ['fire', 'Painel da cozinha', 'Organize a fila e atualize o preparo de cada pedido em tempo real.'],
    ['wallet2', 'Caixa e fechamento', 'Acompanhe os pagamentos e feche a conta com os valores conferidos.'],
    ['qr-code', 'Pedidos por QR Code', 'Ofereça um cardápio por mesa para o cliente pedir e acompanhar.'],
    ['box-seam', 'Produtos e adicionais', 'Edite o cardápio, categorias, preços e opções extras.'],
    ['bar-chart', 'Relatórios operacionais', 'Consulte movimentações, pedidos e resultados do período.'],
    ['buildings', 'Gestão por estabelecimento', 'Separe as operações de cada estabelecimento em sua própria conta.'],
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
    <meta name="theme-color" content="#212529">
    <meta name="description" content="Comandaria Express: pedidos, mesas, cozinha, caixa e cardápio por QR Code para bares e restaurantes.">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Comandaria Express">
    <meta property="og:description" content="Pedidos, mesas, cozinha e caixa em um só sistema.">
    <meta property="og:image" content="https://comandaria.bacuridigital.com/images/comandaria_logo_og_1200x630.png">
    <title>Comandaria Express | Pedidos, Mesas, Cozinha e Caixa</title>
    <link rel="icon" href="<?= landingEscape($caminhoPublico) ?>images/comandaria-marca.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= landingEscape($caminhoPublico) ?>assets/guest-landing.css?v=11">
</head>
<body>
    <header class="site-header">
        <div class="site-container navbar" id="navbar">
            <a class="wordmark" href="#inicio">
                <img src="<?= landingEscape($caminhoPublico) ?>images/comandaria-marca.png" alt="" width="40" height="40">
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
        <section class="hero-shell" aria-labelledby="hero-title">
            <div class="site-container hero">
                <div class="hero-copy">
                    <h1 id="hero-title">Do pedido ao caixa, <em data-typewriter>em um só lugar.</em></h1>
                    <p>Organize o atendimento, acompanhe o preparo e feche as contas em um sistema feito para bares, lanchonetes e restaurantes.</p>
                    <div class="actions">
                        <?php if($landingAtRoot): ?>
                            <a class="button button-primary" href="<?= landingEscape($linkLogin) ?>">Começar grátis</a>
                        <?php else: ?>
                            <a class="button button-primary" href="<?= landingEscape($linkWhatsappFree) ?>" target="_blank" rel="noopener">Começar grátis</a>
                        <?php endif; ?>
                        <a class="button button-outline" href="#diferenciais">Veja como funciona <span aria-hidden="true">↓</span></a>
                    </div>
                </div>
                <figure class="hero-image">
                    <img src="<?= landingEscape($caminhoPublico) ?>images/comandaria-previa-atendimento.webp" width="1320" height="820" fetchpriority="high" alt="Tela da Comandaria com a grade de mesas e o cardápio do atendimento">
                    <figcaption>Atendimento na Comandaria</figcaption>
                </figure>
            </div>
        </section>

        <section class="section section-features" id="funcionalidades">
            <div class="site-container">
                <h2>Recursos para cada etapa do serviço</h2>
                <div class="feature-grid">
                    <?php foreach($recursos as [$icone, $titulo, $descricao]): ?>
                        <article class="feature-card" data-reveal>
                            <i class="feature-icon bi bi-<?= landingEscape($icone) ?>" aria-hidden="true"></i>
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
                    <li data-reveal><i class="feature-icon bi bi-receipt" aria-hidden="true"></i><strong>Receba o pedido</strong><span>A equipe lança a comanda ou o cliente pede pelo QR Code da mesa.</span></li>
                    <li data-reveal><i class="feature-icon bi bi-fire" aria-hidden="true"></i><strong>Acompanhe o preparo</strong><span>A cozinha recebe os itens e informa quando estão prontos.</span></li>
                    <li data-reveal><i class="feature-icon bi bi-credit-card" aria-hidden="true"></i><strong>Feche a conta</strong><span>O caixa revisa os valores e registra os pagamentos.</span></li>
                </ol>
            </div>
        </section>

        <section class="section section-tinted" id="planos">
            <div class="site-container">
                <h2>Planos</h2>
                <div class="plans">
                    <article class="plan" data-reveal>
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
                    <article class="plan plan-pro" data-reveal>
                        <h3>PRO</h3>
                        <p class="price"><strong><?= landingEscape(COMANDARIA_PRECO_PRO) ?></strong> /mês</p>
                        <ul>
                            <li>Até 30 mesas cadastradas</li>
                            <li>Até 1.000 pedidos por mês</li>
                            <li>Cadastro da equipe e controle de acessos</li>
                        </ul>
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
                <article class="feature-card" data-reveal><i class="feature-icon bi bi-cup-straw" aria-hidden="true"></i><h3>Bares</h3><p>Mantenha comandas e consumos por mesa em ordem durante o atendimento.</p></article>
                <article class="feature-card" data-reveal><i class="feature-icon bi bi-shop" aria-hidden="true"></i><h3>Lanchonetes</h3><p>Envie pedidos do balcão para a cozinha e acompanhe a retirada.</p></article>
                <article class="feature-card" data-reveal><i class="feature-icon bi bi-shop-window" aria-hidden="true"></i><h3>Restaurantes</h3><p>Conecte salão, cozinha e caixa durante todo o serviço.</p></article>
            </div>
        </section>

        <section class="site-container">
            <div class="contact" data-reveal>
                <div>
                    <h2>Quer conhecer a Comandaria?</h2>
                    <p>Fale com a Bacuri Digital sobre sua operação.</p>
                </div>
                <a class="button button-light" href="<?= landingEscape($linkWhatsappDemo) ?>" target="_blank" rel="noopener">Conversar pelo WhatsApp</a>
            </div>
        </section>
    </main>

    <footer class="site-container site-footer">
        <a class="wordmark" href="#inicio"><img src="<?= landingEscape($caminhoPublico) ?>images/comandaria-marca.png" width="28" height="28" alt=""> Comandaria Express</a>
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
        const destaque = document.querySelector('[data-typewriter]');
        const frase = destaque.textContent;
        const letras = Array.from(frase);

        function animarTitulo() {
            const textoDigitado = document.createElement('span');
            textoDigitado.className = 'typewriter-text';
            textoDigitado.setAttribute('aria-hidden', 'true');

            const textoOriginal = document.createElement('span');
            textoOriginal.className = 'typewriter-original';
            textoOriginal.textContent = frase;
            destaque.replaceChildren(textoOriginal, textoDigitado);
            destaque.classList.add('is-typing');

            let posicao = 0;
            function digitar() {
                posicao += 1;
                textoDigitado.textContent = letras.slice(0, posicao).join('');
                if (posicao < letras.length) {
                    setTimeout(digitar, 105);
                } else {
                    destaque.textContent = frase;
                    destaque.classList.replace('is-typing', 'is-typed');
                }
            }
            setTimeout(digitar, 420);
        }

        function animarBlocos() {
            if (!('IntersectionObserver' in window)) return;

            const observer = new IntersectionObserver(entries => {
                let ordem = 0;
                for (const entry of entries) {
                    if (!entry.isIntersecting) continue;
                    entry.target.style.setProperty('--reveal-delay', Math.min(ordem++, 3) * 150 + 'ms');
                    entry.target.classList.add('reveal-in');
                    observer.unobserve(entry.target);
                }
            }, { threshold: 0.15 });

            document.querySelectorAll('[data-reveal]:not(.reveal-in)').forEach(bloco => observer.observe(bloco));
        }

        document.querySelector('.hero').classList.add('hero-intro');
        animarTitulo();
        animarBlocos();
    </script>
</body>
</html>
