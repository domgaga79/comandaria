<?php

/*
|--------------------------------------------------------------------------
| MESARIA EXPRESS - CARDÁPIO/PEDIDO PÚBLICO DA MESA
|--------------------------------------------------------------------------
| Tela acessada pelo QR Code fixo da mesa.
|
| URL:
| /mesa.php?mesa=5&token=TOKEN_DA_MESA
|--------------------------------------------------------------------------
*/

ini_set('display_errors', 0);
ini_set('log_errors', 1);
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

$mesa = (int)($_GET['mesa'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));
$erro = null;

try {
    mesariaValidarMesaToken($conn, $mesa, $token, false);
} catch(Throwable $e){
    $erro = $e->getMessage();
}

if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}

?>
<?php
if(!function_exists('mesaria_is_https')){
    function mesaria_is_https() {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
    }
}

if(!function_exists('mesaria_base_url')){
    function mesaria_base_url() {
        $scheme = mesaria_is_https() ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $scriptName = explode('?', $_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '/', 2)[0];
        $dir = rtrim(dirname($scriptName), '/\\');
        return rtrim($scheme . '://' . $host . (($dir && $dir !== '.') ? $dir : ''), '/');
    }
}

if(!function_exists('mesaria_current_url')){
    function mesaria_current_url() {
        $query = (string)(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');
        $script = basename((string)(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''));
        return mesaria_base_url() . '/' . ltrim($query !== '' ? $script . '?' . $query : $script, '/');
    }
}

if(!function_exists('mesaria_asset_url')){
    function mesaria_asset_url($path) {
        return mesaria_base_url() . '/' . ltrim($path, '/') . '?v=20260513_limpo';
    }
}

if(!function_exists('mesaria_escape')){
    function mesaria_escape($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta name="description" content="Faça seu pedido pelo QR Code da mesa com o Mesaria Express.">
<meta name="theme-color" content="#183b2e">
<meta name="apple-mobile-web-app-title" content="Mesaria Express">
<meta name="application-name" content="Mesaria Express">

<link rel="icon" href="<?= mesaria_escape(mesaria_asset_url('images/mesaria_icon.png')) ?>" type="image/png">
<link rel="shortcut icon" href="<?= mesaria_escape(mesaria_asset_url('images/mesaria_icon.png')) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= mesaria_escape(mesaria_asset_url('images/mesaria_icon.png')) ?>">

<meta property="og:locale" content="pt_BR">
<meta property="og:title" content="Mesaria Express">
<meta property="og:description" content="Faça seu pedido pelo QR Code da mesa.">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= mesaria_escape(mesaria_current_url()) ?>">
<meta property="og:image" content="<?= mesaria_escape(mesaria_asset_url('images/mesaria_icon.png')) ?>">
<meta property="og:image:secure_url" content="<?= mesaria_escape(mesaria_asset_url('images/mesaria_icon.png')) ?>">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Mesaria Express">
<meta name="twitter:description" content="Faça seu pedido pelo QR Code da mesa.">
<meta name="twitter:image" content="<?= mesaria_escape(mesaria_asset_url('images/mesaria_icon.png')) ?>">

<link rel="stylesheet" href="assets/guest-menu.css?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mesaria Express - Pedido da Mesa</title>

</head>
<body>

<div class="menu-shell">
    <header class="menu-header">
        <img src="images/mesaria_icon.png" alt="" width="38" height="38">
        <span>Mesaria Express</span>
    </header>

    <div class="hero">
        <?php if(!$erro): ?><span class="mesa">Mesa <?= h($mesa) ?></span><?php endif; ?>
        <h1>Cardápio</h1>
        <?php if(!$erro): ?><p>Escolha seus itens e envie o pedido direto para a equipe.</p><?php endif; ?>
    </div>

    <?php if($erro): ?>
        <main class="alert alert-danger">
            <strong>QR Code inválido.</strong><br>
            <?= h($erro) ?>
        </main>
    <?php else: ?>
        <div class="menu-layout">
            <main class="menu-main">
                <div id="pedidoAtual" class="pedido-atual" style="display:none"></div>
                <div class="toolbar">
                    <div class="search">
                        <input id="busca" type="search" aria-label="Buscar no cardápio" placeholder="Buscar no cardápio" autocomplete="off">
                    </div>
                    <div id="categorias" class="categorias" role="group" aria-label="Filtrar por categoria"></div>
                </div>
                <div id="lista"></div>
            </main>

            <aside class="cart-panel" id="cartPanel" aria-labelledby="cartTitle">
                <div class="cart-inner">
                    <div class="cart-head">
                        <div>
                            <h2 class="cart-title" id="cartTitle">Seu pedido</h2>
                            <div class="small-muted" id="cartResumo" aria-live="polite">Nenhum item selecionado</div>
                        </div>
                        <div class="cart-totals">
                            <span class="cart-total" id="cartTotal">R$ 0,00</span>
                            <button type="button" class="cart-toggle" id="cartToggle" aria-label="Revisar pedido"
                                aria-expanded="false" aria-controls="cartContent" disabled>Revisar</button>
                        </div>
                    </div>

                    <div class="cart-content" id="cartContent">
                        <div id="cartItems" class="cart-items"></div>
                        <div class="cart-actions" id="cartActions" hidden>
                            <button type="button" class="btn btn-clear" id="btnLimpar">Limpar</button>
                            <button type="button" class="btn btn-submit" id="btnEnviar">Enviar pedido</button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php if(!$erro): ?>
<div id="toast" class="toast" role="status" aria-live="polite"></div>

<script>
const MESARIA = {
    mesa: <?= json_encode($mesa, JSON_UNESCAPED_UNICODE) ?>,
    token: <?= json_encode($token, JSON_UNESCAPED_UNICODE) ?>,
    endpoints: {
        produtos: 'listar_produtos.php?mesa=' + encodeURIComponent(<?= json_encode($mesa, JSON_UNESCAPED_UNICODE) ?>) + '&token=' + encodeURIComponent(<?= json_encode($token, JSON_UNESCAPED_UNICODE) ?>),
        salvar: 'public_salvar_pedido.php',
        atual: 'public_mesa_atual.php'
    }
};

const state = {
    produtos: [],
    categoria: '',
    busca: '',
    carrinho: {},
    enviando: false,
    pedidoAtual: null
};

const money = v => Number(v || 0).toLocaleString('pt-BR', {
    style:'currency',
    currency:'BRL'
});

const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;',
    '<':'&lt;',
    '>':'&gt;',
    '"':'&quot;',
    "'":'&#039;'
})[m]);

function toast(msg, tipo = 'ok'){
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast show ' + (tipo === 'err' ? 'err' : 'ok');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 2300);
}

function produtoNomeLimpo(produto){
    return produto.nome_original || produto.nome || '';
}

function itemSubtotal(item){
    const produto = item.produto;
    const qtd = Number(item.qtd || 0);
    const preco = Number(produto.preco || 0);
    const qtdAdicionais = Number(item.adicional || 0);
    const valorAdicional = Number(produto.valor_adicional || 0);
    return qtd * (preco + (qtdAdicionais * valorAdicional));
}

function carrinhoTotal(){
    return Object.values(state.carrinho).reduce((s, item) => s + itemSubtotal(item), 0);
}

function carrinhoQtd(){
    return Object.values(state.carrinho).reduce((s, item) => s + Number(item.qtd || 0), 0);
}

function setLoading(flag){
    state.enviando = flag;
    document.getElementById('cartPanel').setAttribute('aria-busy', String(flag));
    document.getElementById('cartItems').inert = flag;
    document.getElementById('lista').inert = flag;
    document.getElementById('btnEnviar').disabled = flag;
    document.getElementById('btnLimpar').disabled = flag;
}

async function carregarProdutos(){
    const res = await fetch(MESARIA.endpoints.produtos, {cache:'no-store'});
    const data = await res.json();

    if(!res.ok || !Array.isArray(data)){
        throw new Error(data?.erro || 'Erro ao carregar produtos');
    }

    state.produtos = data;
    renderCategorias();
    renderProdutos();
}

async function carregarPedidoAtual(){
    const url = `${MESARIA.endpoints.atual}?mesa=${encodeURIComponent(MESARIA.mesa)}&token=${encodeURIComponent(MESARIA.token)}`;
    const res = await fetch(url, {cache:'no-store'});
    const data = await res.json();

    if(!res.ok || !data.ok){
        throw new Error(data?.erro || 'Erro ao carregar conta da mesa');
    }

    state.pedidoAtual = data.pedido || null;
    renderPedidoAtual();
}

function categoriasDisponiveis(){
    return [...new Set(state.produtos.map(p => p.cat || p.categoria || 'Sem categoria'))].sort();
}

function renderCategorias(){
    const cats = categoriasDisponiveis();
    const el = document.getElementById('categorias');

    el.innerHTML = `
        <button type="button" class="cat-btn ${state.categoria === '' ? 'active' : ''}" aria-pressed="${state.categoria === ''}" data-cat="">Todos</button>
        ${cats.map(cat => `
            <button type="button" class="cat-btn ${state.categoria === cat ? 'active' : ''}" aria-pressed="${state.categoria === cat}" data-cat="${escapeHtml(cat)}">
                ${escapeHtml(cat)}
            </button>
        `).join('')}
    `;
}

function produtosFiltrados(){
    const busca = state.busca.trim().toLowerCase();

    return state.produtos.filter(p => {
        const cat = p.cat || p.categoria || 'Sem categoria';
        const nome = produtoNomeLimpo(p).toLowerCase();

        if(state.categoria && cat !== state.categoria){
            return false;
        }

        if(busca && !nome.includes(busca) && !cat.toLowerCase().includes(busca)){
            return false;
        }

        return true;
    });
}

function renderProdutos(){
    const lista = document.getElementById('lista');
    const produtos = produtosFiltrados();

    if(!produtos.length){
        lista.innerHTML = '<div class="alert">Nenhum produto encontrado.</div>';
        return;
    }

    const grupos = {};

    produtos.forEach(p => {
        const cat = p.cat || p.categoria || 'Sem categoria';
        if(!grupos[cat]) grupos[cat] = [];
        grupos[cat].push(p);
    });

    lista.innerHTML = Object.entries(grupos).map(([cat, itens]) => `
        <h2 class="section-title">${escapeHtml(cat)}</h2>
        <div class="grid">
            ${itens.map(produtoCard).join('')}
        </div>
    `).join('');
}

function produtoCard(p){
    const id = Number(p.id);
    const noCarrinho = !!state.carrinho[id];
    const nome = produtoNomeLimpo(p);
    const cat = p.cat || p.categoria || 'Sem categoria';
    const valorAdicional = Number(p.valor_adicional || 0);
    const adicionalPermitido = Number(p.adicional_permitido || 0) === 1 && valorAdicional > 0;
    const permiteObs = Number(p.permite_obs || 0) === 1;
    const permitePonto = Number(p.permite_ponto || 0) === 1;

    return `
        <article class="produto">
            <div class="produto-top">
                <div>
                    <div class="produto-nome">${escapeHtml(p.icone ? p.icone + ' ' + nome : nome)}</div>
                    <div class="produto-cat">${escapeHtml(cat)}</div>
                </div>
                <div class="preco">${money(p.preco)}</div>
            </div>

            <div class="tags">
                ${permiteObs ? '<span class="tag">observação</span>' : ''}
                ${permitePonto ? '<span class="tag">ponto</span>' : ''}
                ${adicionalPermitido ? `<span class="tag">adicional ${money(valorAdicional)}</span>` : ''}
            </div>

            <button type="button" class="btn btn-add ${noCarrinho ? 'added' : ''}" data-add="${id}">
                ${noCarrinho ? '✓ No carrinho' : '+ Adicionar'}
            </button>
        </article>
    `;
}

function adicionarProduto(id){
    const produto = state.produtos.find(p => Number(p.id) === Number(id));

    if(!produto){
        return;
    }

    if(!state.carrinho[id]){
        state.carrinho[id] = {
            id: Number(id),
            produto,
            qtd: 1,
            obs: '',
            ponto: '',
            adicional: 0
        };
    } else {
        state.carrinho[id].qtd = Math.min(20, Number(state.carrinho[id].qtd || 0) + 1);
    }

    navigator.vibrate?.(40);
    renderProdutos();
    renderCarrinho();
}

function alterarQtd(id, delta){
    const item = state.carrinho[id];
    if(!item) return;

    item.qtd = Number(item.qtd || 0) + delta;

    if(item.qtd <= 0){
        delete state.carrinho[id];
    } else {
        item.qtd = Math.min(20, item.qtd);
    }

    renderProdutos();
    renderCarrinho();
}

function alterarAdicional(id, delta){
    const item = state.carrinho[id];
    if(!item) return;

    item.adicional = Math.max(0, Math.min(5, Number(item.adicional || 0) + delta));
    renderCarrinho();
}

function renderCarrinho(){
    const itemsEl = document.getElementById('cartItems');
    const resumoEl = document.getElementById('cartResumo');
    const totalEl = document.getElementById('cartTotal');
    const toggle = document.getElementById('cartToggle');

    const itens = Object.values(state.carrinho);
    const qtd = carrinhoQtd();

    resumoEl.textContent = qtd > 0
        ? `${qtd} ${qtd === 1 ? 'item' : 'itens'}`
        : 'Nenhum item selecionado';

    totalEl.textContent = money(carrinhoTotal());

    toggle.disabled = qtd === 0;
    document.getElementById('cartActions').hidden = qtd === 0;

    if(!itens.length){
        mostrarCarrinho(false);
        itemsEl.innerHTML = '<div class="cart-empty">Toque em “Adicionar” nos produtos para montar seu pedido.</div>';
        return;
    }

    itemsEl.innerHTML = itens.map(item => cartItemHtml(item)).join('');
}

function mostrarCarrinho(aberto){
    const toggle = document.getElementById('cartToggle');
    document.getElementById('cartPanel').classList.toggle('is-open', aberto);
    toggle.setAttribute('aria-expanded', String(aberto));
    toggle.setAttribute('aria-label', aberto ? 'Fechar resumo do pedido' : 'Revisar pedido');
    toggle.textContent = aberto ? 'Fechar' : 'Revisar';
}

function cartItemHtml(item){
    const p = item.produto;
    const id = item.id;
    const nome = produtoNomeLimpo(p);
    const valorAdicional = Number(p.valor_adicional || 0);
    const adicionalPermitido = Number(p.adicional_permitido || 0) === 1 && valorAdicional > 0;
    const permitePonto = Number(p.permite_ponto || 0) === 1;
    const permiteObs = Number(p.permite_obs || 0) === 1;

    return `
        <div class="cart-item" data-cart-id="${id}">
            <div class="cart-row">
                <div>
                    <div class="cart-name">${escapeHtml(nome)}</div>
                    <div class="small-muted">${money(p.preco)} un. ${item.adicional > 0 ? `+ ${item.adicional} adicional(is)` : ''}</div>
                </div>
                <div class="qty">
                    <button type="button" aria-label="Diminuir quantidade de ${escapeHtml(nome)}" data-qtd="${id}" data-delta="-1">−</button>
                    <strong>${Number(item.qtd || 0)}</strong>
                    <button type="button" aria-label="Aumentar quantidade de ${escapeHtml(nome)}" data-qtd="${id}" data-delta="1">+</button>
                </div>
            </div>

            <div class="cart-fields">
                ${permiteObs ? `
                    <textarea rows="2" data-obs="${id}" aria-label="Observação para ${escapeHtml(nome)}" placeholder="Observação para este item">${escapeHtml(item.obs || '')}</textarea>
                ` : ''}

                ${permitePonto ? `
                    <select data-ponto="${id}" aria-label="Ponto de ${escapeHtml(nome)}">
                        <option value="">Ponto da carne</option>
                        ${['Mal passado','Ao ponto','Bem passado'].map(op => `
                            <option value="${op}" ${item.ponto === op ? 'selected' : ''}>${op}</option>
                        `).join('')}
                    </select>
                ` : ''}

                ${adicionalPermitido ? `
                    <div class="add-control">
                        <span>Adicionais (${money(valorAdicional)} cada)</span>
                        <span class="qty">
                            <button type="button" aria-label="Diminuir adicionais de ${escapeHtml(nome)}" data-add-qtd="${id}" data-delta="-1">−</button>
                            <strong>${Number(item.adicional || 0)}</strong>
                            <button type="button" aria-label="Aumentar adicionais de ${escapeHtml(nome)}" data-add-qtd="${id}" data-delta="1">+</button>
                        </span>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
}

function renderPedidoAtual(){
    const el = document.getElementById('pedidoAtual');
    const p = state.pedidoAtual;

    if(!p){
        el.style.display = 'none';
        el.innerHTML = '';
        return;
    }

    const totalItens = Number(p.quantidade_itens || 0);
    const prontos = (p.itens || []).reduce((s, i) => s + Number(i.quantidade_pronta || 0), 0);
    const total = Number(p.total || 0);
    const servicoAtivo = Boolean(p.servico_10_ativo === true || Number(p.servico_10_ativo || 0) === 1);
    const taxa = servicoAtivo
        ? Number(p.taxa_servico_10 ?? (total * 0.10))
        : 0;
    const totalFinal = Number(p.total_com_servico ?? p.total_cobravel ?? (total + taxa));

    el.style.display = 'block';
    el.innerHTML = `
        <h2>Conta aberta da Mesa ${escapeHtml(p.mesa)}</h2>
        <div class="linha"><span>Pedido</span><strong>#${escapeHtml(p.pedido_id)}</strong></div>
        <div class="linha"><span>Status</span><strong>${escapeHtml(p.status_producao || p.status)}</strong></div>
        <div class="linha"><span>Itens prontos</span><strong>${prontos}/${totalItens}</strong></div>
        <div class="linha"><span>Subtotal</span><strong>${money(p.subtotal)}</strong></div>
        <div class="linha"><span>Adicionais</span><strong>${money(p.total_adicional)}</strong></div>
        <div class="linha total"><span>Total base</span><strong>${money(p.total)}</strong></div>
        <div class="linha"><span>10% serviço</span><strong>${servicoAtivo ? money(taxa) : 'Não cobrado'}</strong></div>
        <div class="linha total"><span>Total final</span><strong>${money(totalFinal)}</strong></div>
        <a class="link-comanda" href="${escapeHtml(p.comanda_url)}">Acompanhar comanda</a>
    `;
}

async function enviarPedido(){
    if(state.enviando) return;

    const itens = Object.values(state.carrinho).map(item => ({
        id: item.id,
        qtd: Number(item.qtd || 0),
        obs: item.obs || '',
        observacao: item.obs || '',
        ponto: item.ponto || '',
        adicional: Number(item.adicional || 0)
    })).filter(item => item.qtd > 0);

    if(!itens.length){
        toast('Adicione pelo menos um item.', 'err');
        return;
    }

    setLoading(true);

    try {
        const res = await fetch(MESARIA.endpoints.salvar, {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                mesa: MESARIA.mesa,
                token: MESARIA.token,
                itens
            })
        });

        const data = await res.json();

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao enviar pedido');
        }

        state.carrinho = {};
        renderProdutos();
        renderCarrinho();

        toast('Pedido enviado com sucesso!');

        await carregarPedidoAtual();

        if(data.comanda_url){
            setTimeout(() => {
                const link = document.querySelector('.link-comanda');
                link?.scrollIntoView({behavior:'smooth', block:'center'});
            }, 250);
        }

    } catch(err){
        console.error(err);
        toast(err.message || 'Erro ao enviar pedido', 'err');

    } finally {
        setLoading(false);
    }
}

/* Eventos */
document.getElementById('cartToggle').addEventListener('click', event => {
    mostrarCarrinho(event.currentTarget.getAttribute('aria-expanded') !== 'true');
});

document.getElementById('categorias').addEventListener('click', e => {
    const btn = e.target.closest('[data-cat]');
    if(!btn) return;
    state.categoria = btn.dataset.cat || '';
    e.currentTarget.querySelectorAll('[data-cat]').forEach(categoria => {
        const ativa = categoria === btn;
        categoria.classList.toggle('active', ativa);
        categoria.setAttribute('aria-pressed', String(ativa));
    });
    renderProdutos();
});

document.getElementById('busca').addEventListener('input', e => {
    state.busca = e.target.value || '';
    renderProdutos();
});

document.getElementById('lista').addEventListener('click', e => {
    const btn = e.target.closest('[data-add]');
    if(!btn) return;
    const id = Number(btn.dataset.add);
    adicionarProduto(id);
    e.currentTarget.querySelector(`[data-add="${id}"]`)?.focus({preventScroll:true});
});

document.getElementById('cartItems').addEventListener('click', e => {
    const btn = e.target.closest('[data-qtd], [data-add-qtd]');
    if(!btn) return;

    const delta = Number(btn.dataset.delta);
    const id = Number(btn.dataset.qtd ?? btn.dataset.addQtd);
    const quantidade = btn.hasAttribute('data-qtd');
    if(quantidade){
        alterarQtd(id, delta);
    } else {
        alterarAdicional(id, delta);
    }

    const atributo = quantidade ? 'data-qtd' : 'data-add-qtd';
    e.currentTarget.querySelector(`[${atributo}="${id}"][data-delta="${delta}"]`)?.focus({preventScroll:true});
});

document.getElementById('cartItems').addEventListener('input', e => {
    const obs = e.target.closest('[data-obs]');
    if(obs && state.carrinho[obs.dataset.obs]){
        state.carrinho[obs.dataset.obs].obs = obs.value;
    }
});

document.getElementById('cartItems').addEventListener('change', e => {
    const ponto = e.target.closest('[data-ponto]');
    if(ponto && state.carrinho[ponto.dataset.ponto]){
        state.carrinho[ponto.dataset.ponto].ponto = ponto.value;
    }
});

document.getElementById('btnLimpar').addEventListener('click', () => {
    state.carrinho = {};
    renderProdutos();
    renderCarrinho();
});

document.getElementById('btnEnviar').addEventListener('click', enviarPedido);

(async function init(){
    try {
        renderCarrinho();
        await carregarProdutos();
        await carregarPedidoAtual();
        setInterval(() => carregarPedidoAtual().catch(console.warn), 12000);
    } catch(err){
        console.error(err);
        toast(err.message || 'Erro ao iniciar cardápio', 'err');
    }
})();
</script>
<?php endif; ?>

</body>
</html>