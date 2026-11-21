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
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
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
<meta name="theme-color" content="#0b0b0b">
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

<link rel="stylesheet" href="<?= mesaria_escape(mesaria_asset_url('assets/mesaria_pedido_dock_fix.css')) ?>">
<script defer src="<?= mesaria_escape(mesaria_asset_url('assets/mesaria_pedido_dock_fix.js')) ?>"></script>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mesaria Express - Pedido da Mesa</title>
<meta name="theme-color" content="#111111">
<style>
*{
    box-sizing:border-box;
}
:root{
    --bg:#111;
    --panel:#1b1b1b;
    --panel2:#242424;
    --card:#fff;
    --text:#fff;
    --muted:#bdbdbd;
    --dark:#111;
    --line:#e8e8e8;
    --accent:#ffc107;
    --accent2:#198754;
    --danger:#dc3545;
}
body{
    margin:0;
    background:var(--bg);
    color:var(--text);
    font-family:Arial, Helvetica, sans-serif;
    padding-bottom:130px;
}
.container{
    max-width:980px;
    margin:0 auto;
    padding:12px;
}
.hero{
    background:linear-gradient(135deg,#191919,#2b2110);
    border:1px solid rgba(255,255,255,.08);
    border-radius:22px;
    padding:18px;
    margin-bottom:14px;
    box-shadow:0 8px 26px rgba(0,0,0,.28);
}
.logo{
    text-align:center;
}
.logo img{
    width:100%;
    max-width:220px;
}
.brand{
    text-align:center;
}
.brand h1{
    margin:6px 0 2px;
    font-size:28px;
    letter-spacing:.2px;
}
.brand .mesa{
    display:inline-block;
    background:var(--accent);
    color:#111;
    border-radius:999px;
    padding:7px 14px;
    font-weight:900;
    margin-top:8px;
}
.brand p{
    color:var(--muted);
    margin:8px 0 0;
    font-size:14px;
}
.alert{
    border-radius:14px;
    padding:14px;
    background:#fff3cd;
    color:#111;
    margin:16px 0;
}
.alert-danger{
    background:#f8d7da;
}
.toolbar{
    position:sticky;
    top:0;
    z-index:20;
    background:rgba(17,17,17,.96);
    backdrop-filter:blur(8px);
    padding:8px 0 10px;
    margin-bottom:10px;
}
.search{
    display:flex;
    gap:8px;
}
.search input{
    width:100%;
    border:0;
    border-radius:12px;
    padding:13px 14px;
    font-size:16px;
    outline:none;
}
.categorias{
    display:flex;
    gap:8px;
    overflow:auto;
    padding:10px 0 2px;
}
.cat-btn{
    white-space:nowrap;
    border:1px solid rgba(255,255,255,.14);
    background:var(--panel2);
    color:#fff;
    border-radius:999px;
    padding:9px 12px;
    font-weight:700;
}
.cat-btn.active{
    background:var(--accent);
    color:#111;
    border-color:var(--accent);
}
.section-title{
    font-size:18px;
    font-weight:900;
    margin:18px 0 8px;
}
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(250px,1fr));
    gap:10px;
}
.produto{
    background:var(--card);
    color:#111;
    border-radius:18px;
    padding:13px;
    box-shadow:0 4px 14px rgba(0,0,0,.22);
    display:flex;
    flex-direction:column;
    gap:8px;
}
.produto-top{
    display:flex;
    justify-content:space-between;
    gap:10px;
}
.produto-nome{
    font-weight:900;
    font-size:16px;
    line-height:1.22;
}
.produto-cat{
    color:#666;
    font-size:12px;
    margin-top:3px;
}
.preco{
    font-weight:900;
    white-space:nowrap;
}
.tags{
    display:flex;
    gap:5px;
    flex-wrap:wrap;
}
.tag{
    background:#f1f1f1;
    color:#333;
    border-radius:999px;
    padding:4px 7px;
    font-size:11px;
    font-weight:700;
}
.btn{
    border:0;
    border-radius:12px;
    padding:11px 12px;
    font-weight:900;
    cursor:pointer;
}
.btn-add{
    background:#111;
    color:#fff;
    width:100%;
}
.btn-add.added{
    background:var(--accent2);
}
.pedido-atual{
    background:var(--panel);
    border:1px solid rgba(255,255,255,.08);
    border-radius:18px;
    padding:13px;
    margin-bottom:14px;
}
.pedido-atual h2{
    margin:0 0 8px;
    font-size:18px;
}
.pedido-atual .linha{
    display:flex;
    justify-content:space-between;
    gap:8px;
    color:#ddd;
    font-size:14px;
    padding:4px 0;
}
.pedido-atual .total{
    font-size:18px;
    font-weight:900;
    color:#fff;
    border-top:1px dashed rgba(255,255,255,.18);
    margin-top:5px;
    padding-top:8px;
}
.link-comanda{
    display:inline-block;
    margin-top:10px;
    background:var(--accent);
    color:#111;
    text-decoration:none;
    font-weight:900;
    border-radius:12px;
    padding:10px 12px;
}
.cart-panel{
    position:fixed;
    left:0;
    right:0;
    bottom:0;
    z-index:60;
    background:#fff;
    color:#111;
    box-shadow:0 -8px 24px rgba(0,0,0,.38);
    border-radius:22px 22px 0 0;
    max-height:78vh;
    overflow:auto;
}
.cart-panel.carrinho-com-itens{
    border-top:4px solid #ffc107;
}
.cart-panel.carrinho-com-itens .cart-total{
    background:#ffc107;
    color:#111;
    border-radius:8px;
    padding:4px 8px;
}
.cart-inner{
    max-width:980px;
    margin:0 auto;
    padding:12px;
}
.cart-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}
.cart-title{
    font-size:18px;
    font-weight:900;
}
.cart-total{
    font-weight:900;
    font-size:18px;
}
.cart-empty{
    color:#555;
    font-size:14px;
    padding:8px 0;
}
.cart-items{
    display:grid;
    gap:8px;
    margin-top:10px;
}
.cart-item{
    border:1px solid #eee;
    border-radius:14px;
    padding:10px;
    background:#fafafa;
}
.cart-row{
    display:flex;
    justify-content:space-between;
    gap:10px;
    align-items:flex-start;
}
.cart-name{
    font-weight:900;
}
.qty{
    display:flex;
    align-items:center;
    gap:6px;
    white-space:nowrap;
}
.qty button{
    width:30px;
    height:30px;
    border:0;
    border-radius:9px;
    background:#111;
    color:#fff;
    font-weight:900;
}
.cart-fields{
    display:grid;
    grid-template-columns:1fr;
    gap:7px;
    margin-top:9px;
}
.cart-fields textarea,
.cart-fields select{
    width:100%;
    border:1px solid #ddd;
    border-radius:10px;
    padding:9px;
    font-size:14px;
    font-family:Arial,sans-serif;
}
.add-control{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    background:#fff;
    border:1px solid #ddd;
    border-radius:10px;
    padding:8px;
    font-size:13px;
}
.add-control .qty button{
    width:28px;
    height:28px;
}
.cart-actions{
    display:grid;
    grid-template-columns:1fr 1.2fr;
    gap:8px;
    margin-top:10px;
}
.btn-clear{
    background:#eee;
    color:#111;
}
.btn-submit{
    background:var(--accent2);
    color:#fff;
}
.btn-submit:disabled{
    opacity:.65;
    cursor:not-allowed;
}
.toast{
    position:fixed;
    left:50%;
    bottom:116px;
    transform:translateX(-50%) translateY(20px);
    background:#111;
    color:#fff;
    border-radius:999px;
    padding:11px 15px;
    z-index:90;
    opacity:0;
    pointer-events:none;
    transition:.22s;
    box-shadow:0 4px 18px rgba(0,0,0,.35);
    max-width:92%;
    text-align:center;
}
.toast.show{
    opacity:1;
    transform:translateX(-50%) translateY(0);
}
.toast.ok{
    background:#198754;
}
.toast.err{
    background:#dc3545;
}
.loading{
    opacity:.6;
    pointer-events:none;
}
.small-muted{
    color:#666;
    font-size:12px;
}
@media(max-width:560px){
    .grid{
        grid-template-columns:1fr;
    }
    .brand h1{
        font-size:24px;
    }
    .cart-actions{
        grid-template-columns:1fr;
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

<div class="container">
    <div class="hero">
        <div class="logo">
            <img src="images/mesaria_icon.png" alt="Mesaria Express" onerror="this.style.display='none'">
        </div>
        <div class="brand">
            <!-- <h1>Mesaria Express</h1> -->
            <div class="mesa">Mesa <?= h($mesa) ?></div>
            <p>Escolha seus itens, confira o carrinho e envie o pedido direto para a equipe.</p>
        </div>
    </div>

    <?php if($erro): ?>
        <div class="alert alert-danger">
            <strong>QR Code inválido.</strong><br>
            <?= h($erro) ?>
        </div>
    <?php else: ?>
        <div id="pedidoAtual" class="pedido-atual" style="display:none"></div>

        <div class="toolbar">
            <div class="search">
                <input id="busca" type="search" placeholder="Buscar produto..." autocomplete="off">
            </div>
            <div id="categorias" class="categorias"></div>
        </div>

        <div id="lista"></div>
    <?php endif; ?>
</div>

<?php if(!$erro): ?>
<div class="cart-panel" id="cartPanel">
    <div class="cart-inner">
        <div class="cart-head">
            <div>
                <div class="cart-title">🛒 Seu pedido</div>
                <div class="small-muted" id="cartResumo">Nenhum item selecionado</div>
            </div>
            <div class="cart-total" id="cartTotal">R$ 0,00</div>
        </div>

        <div id="cartItems" class="cart-items"></div>

        <div class="cart-actions">
            <button type="button" class="btn btn-clear" id="btnLimpar">Limpar</button>
            <button type="button" class="btn btn-submit" id="btnEnviar">Enviar pedido</button>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

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
    document.body.classList.toggle('loading', flag);
    document.getElementById('btnEnviar').disabled = flag;
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
        <button type="button" class="cat-btn ${state.categoria === '' ? 'active' : ''}" data-cat="">Todos</button>
        ${cats.map(cat => `
            <button type="button" class="cat-btn ${state.categoria === cat ? 'active' : ''}" data-cat="${escapeHtml(cat)}">
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
        <div class="section-title">${escapeHtml(cat)}</div>
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

    const itens = Object.values(state.carrinho);
    const qtd = carrinhoQtd();
    document.getElementById('cartPanel').classList.toggle('carrinho-com-itens', qtd > 0);

    resumoEl.textContent = qtd > 0
        ? `${qtd} item(ns) selecionado(s)`
        : 'Nenhum item selecionado';

    totalEl.textContent = money(carrinhoTotal());

    if(!itens.length){
        itemsEl.innerHTML = '<div class="cart-empty">Toque em “Adicionar” nos produtos para montar seu pedido.</div>';
        return;
    }

    itemsEl.innerHTML = itens.map(item => cartItemHtml(item)).join('');
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
                    <button type="button" data-qtd="${id}" data-delta="-1">−</button>
                    <strong>${Number(item.qtd || 0)}</strong>
                    <button type="button" data-qtd="${id}" data-delta="1">+</button>
                </div>
            </div>

            <div class="cart-fields">
                ${permiteObs ? `
                    <textarea rows="2" data-obs="${id}" placeholder="Observação para este item">${escapeHtml(item.obs || '')}</textarea>
                ` : ''}

                ${permitePonto ? `
                    <select data-ponto="${id}">
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
                            <button type="button" data-add-qtd="${id}" data-delta="-1">−</button>
                            <strong>${Number(item.adicional || 0)}</strong>
                            <button type="button" data-add-qtd="${id}" data-delta="1">+</button>
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
document.getElementById('categorias').addEventListener('click', e => {
    const btn = e.target.closest('[data-cat]');
    if(!btn) return;
    state.categoria = btn.dataset.cat || '';
    renderCategorias();
    renderProdutos();
});

document.getElementById('busca').addEventListener('input', e => {
    state.busca = e.target.value || '';
    renderProdutos();
});

document.getElementById('lista').addEventListener('click', e => {
    const btn = e.target.closest('[data-add]');
    if(!btn) return;
    adicionarProduto(Number(btn.dataset.add));
});

document.getElementById('cartItems').addEventListener('click', e => {
    const qtd = e.target.closest('[data-qtd]');
    if(qtd){
        alterarQtd(Number(qtd.dataset.qtd), Number(qtd.dataset.delta));
        return;
    }

    const add = e.target.closest('[data-add-qtd]');
    if(add){
        alterarAdicional(Number(add.dataset.addQtd), Number(add.dataset.delta));
    }
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