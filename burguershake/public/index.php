<?php
require __DIR__ . '/auth.php';
requireRole(['atendente','admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}
require_once __DIR__ . '/inc/seo.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Comandaria Express</title>

<?= ce_og_tags('Comandaria Express', 'Sistema de comandas para restaurantes e bares') ?>
<link rel="icon" href="images/topo_comandaria_shake.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #111;
    color: #fff;
    font-family: Arial, sans-serif;
    padding-bottom: 120px;
}

.topo {
    text-align: center;
    padding: 10px 0;
}

.topo img {
    max-width: 430px;
    width: 100%;
}

.userbox {
    text-align: center;
    margin-bottom: 10px;
}

.userbox .inner {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(255,255,255,.06);
    font-size: 14px;
}

.section-title {
    font-size: 15px;
    font-weight: bold;
    margin: 12px 0 6px;
}

.grid-mesas {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 6px;
}

@media(max-width:600px) {
    .grid-mesas {
        grid-template-columns: repeat(4, 1fr);
    }
}

.btn-mesa {
    border: 0;
    border-radius: 10px;
    padding: 10px 4px;
    font-size: 18px;
    font-weight: bold;
    background: #e5e5e5;
    color: #222;
    position: relative;
}

.btn-mesa.ocupada {
    background: #b71c1c;
    color: #fff;
}

.btn-mesa.ativa {
    background: #198754;
    color: #fff;
    outline: 3px solid #fff;
}

.btn-mesa small {
    display: block;
    font-size: 10px;
    font-weight: normal;
    opacity: .85;
}

.cliente-row {
    display: flex;
    gap: 6px;
}

.cliente-row input {
    flex: 1;
}

.clientes-abertos-wrap {
    margin: 8px 0 10px;
}

.clientes-abertos {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 4px;
}

.btn-cliente-aberto {
    border: 0;
    border-radius: 10px;
    padding: 7px 8px;
    background: #343a40;
    color: #fff;
    text-align: left;
    font-size: 12px;
    min-height: 46px;
    max-width: 132px;
}

.btn-cliente-aberto.ativa {
    background: #198754;
    color: #fff;
    outline: 3px solid #fff;
}

.btn-cliente-aberto strong {
    display: block;
    font-size: 13px;
    line-height: 1.1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.btn-cliente-aberto small {
    display: block;
    opacity: .85;
    font-size: 10px;
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.clientes-vazio {
    color: #aaa;
    font-size: 13px;
    padding: 6px 0;
    grid-column: 1 / -1;
}

.clientes-erro {
    color: #ffc107;
    font-size: 13px;
    padding: 6px 0;
    grid-column: 1 / -1;
}

.status-sessao {
    background: #1f1f1f;
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 12px;
    padding: 10px;
    margin: 10px 0;
    font-size: 14px;
}

.busca {
    position: sticky;
    top: 0;
    z-index: 5;
    background: #111;
    padding: 8px 0;
}

.categorias {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 6px;
    margin-bottom: 8px;
}

.categorias button {
    white-space: nowrap;
    border: 0;
    border-radius: 999px;
    padding: 7px 12px;
    font-size: 13px;
    background: #333;
    color: #fff;
}

.categorias button.ativa {
    background: #ffc107;
    color: #111;
    font-weight: bold;
}

.produtos {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
}

@media(min-width:768px) {
    .produtos {
        grid-template-columns: repeat(4, 1fr);
    }
}

.produto {
    background: #fff;
    color: #111;
    border-radius: 12px;
    padding: 10px;
    min-height: 125px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,.25);
}

.produto .cat {
    font-size: 11px;
    text-transform: uppercase;
    color: #777;
    font-weight: bold;
}

.produto .nome {
    font-weight: bold;
    font-size: 14px;
    margin: 4px 0;
}

.produto .preco {
    font-size: 16px;
    font-weight: bold;
}

.produto .adicional-info {
    font-size: 11px;
    color: #0d6efd;
}

.produto .acoes {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 8px;
}

.produto .acoes button {
    flex: 1;
    border: 0;
    border-radius: 10px;
    padding: 6px;
    font-weight: 900;
}

.produto .qtd {
    min-width: 28px;
    text-align: center;
    font-weight: bold;
}

.btn-minus {
    background: linear-gradient(180deg, #ff4d5f, #dc3545);
    color: #fff;
    font-size: 20px;
    line-height: 1;
    min-height: 36px;
    box-shadow: 0 2px 6px rgba(220,53,69,.35);
}

.btn-plus {
    background: linear-gradient(180deg, #22c55e, #198754);
    color: #fff;
    font-size: 20px;
    line-height: 1;
    min-height: 36px;
    box-shadow: 0 2px 6px rgba(25,135,84,.35);
}

.btn-minus:active,
.btn-plus:active {
    transform: scale(.96);
}

.cart-select-label {
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 3px;
    color: #555;
}

.cart-select-help {
    font-size: 11px;
    color: #777;
    margin-top: 3px;
}

.carrinho-fixo {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 20;
    background: #000;
    border-top: 1px solid rgba(255,255,255,.15);
    padding: 10px;
}

.carrinho-fixo .linha {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
}

.carrinho-fixo .total {
    font-size: clamp(16px, 4vw, 20px);
    font-weight: bold;
    white-space: nowrap;
}

.btn-salvar {
    display: none;
    border: 0;
    border-radius: 10px;
    padding: 10px 14px;
    background: #ffc107;
    color: #111;
    font-weight: bold;
}

.btn-carrinho {
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 10px;
    padding: 10px 12px;
    background: #222;
    color: #fff;
}

.btn-carrinho.carrinho-com-itens {
    background: #ffc107;
    border-color: #ffc107;
    color: #111;
    box-shadow: 0 0 12px rgba(255,193,7,.45);
}

.modal-content {
    color: #111;
}

.modal-title small {
    font-size: 12px;
    font-weight: 500;
}

.cart-item {
    border-bottom: 1px solid #eee;
    padding: 10px 0;
}

.cart-item:last-child {
    border-bottom: 0;
}

.cart-title {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-weight: bold;
}

.cart-controls {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 6px 0;
}

.cart-controls button {
    border: 0;
    border-radius: 9px;
    padding: 6px 12px;
    font-weight: 900;
    min-width: 38px;
    min-height: 34px;
    font-size: 20px;
    line-height: 1;
}

.cart-btn-minus {
    background: linear-gradient(180deg, #ff4d5f, #dc3545) !important;
    color: #fff !important;
    box-shadow: 0 2px 6px rgba(220,53,69,.35);
}

.cart-btn-plus {
    background: linear-gradient(180deg, #22c55e, #198754) !important;
    color: #fff !important;
    box-shadow: 0 2px 6px rgba(25,135,84,.35);
}

.cart-btn-minus:active,
.cart-btn-plus:active {
    transform: scale(.96);
}

.cart-controls button:disabled,
.produto .acoes button:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.badge-enviado {
    display: inline-block;
    margin-top: 5px;
    border-radius: 999px;
    background: #e9ecef;
    color: #555;
    font-size: 11px;
    padding: 3px 8px;
}

.toast-app {
    position: fixed;
    right: 15px;
    bottom: 100px;
    background: #111;
    color: #fff;
    padding: 12px 15px;
    border-radius: 10px;
    opacity: 0;
    transform: translateY(15px);
    transition: .25s;
    z-index: 9999;
    box-shadow: 0 3px 12px rgba(0,0,0,.3);
}

.toast-app.show {
    opacity: 1;
    transform: translateY(0);
}

.toast-app.ok {
    background: #198754;
}

.toast-app.erro {
    background: #dc3545;
}

.toast-app.warn {
    background: #ffc107;
    color: #111;
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

<div class="container-fluid">

    <div class="topo">
        <img src="images/topo_comandaria_shake.png" alt="Comandaria Express">
    </div>

    <div class="userbox">
        <div class="inner">
            <span>
                Bem-vindo,
                <strong><?= htmlspecialchars($_SESSION['user_nome'] ?? '') ?></strong>
            </span>

            <a href="logout.php" class="text-warning text-decoration-none">
                sair
            </a>
        </div>
    </div>
    
    <div id="statusSessao" class="status-sessao">
        Selecione uma mesa ou informe um cliente.
    </div>
    
    
    <div class="section-title">Cliente avulso</div>

    <div class="cliente-row">
        <input
            id="cliente"
            class="form-control"
            placeholder="Nome do cliente"
            autocomplete="off"
        >

        <button id="btnCliente" class="btn btn-success">
            Abrir
        </button>
    </div>

    <div class="section-title">Clientes abertos</div>

    <div class="clientes-abertos-wrap">
        <div id="clientesAbertos" class="clientes-abertos">
            <div class="clientes-vazio">Carregando clientes abertos...</div>
        </div>
    </div>

    <div class="section-title">Mesas</div>

    <div id="mesas" class="grid-mesas"></div>

    <div class="busca">
        <input
            id="busca"
            class="form-control mb-2"
            placeholder="Buscar produto..."
            autocomplete="off"
        >

        <div id="categorias" class="categorias"></div>
    </div>

    <div id="produtos" class="produtos"></div>

</div>

<div class="carrinho-fixo">
    <div class="linha">
        <button
            class="btn-carrinho"
            data-bs-toggle="modal"
            data-bs-target="#modalCarrinho"
        >
            Carrinho: <span id="qtdCarrinho">0</span>
        </button>

        <div class="total">
            R$ <span id="total">0,00</span>
        </div>

        <button id="btnSalvar" class="btn-salvar">
            Enviar
        </button>
    </div>
</div>

<div class="modal fade" id="modalCarrinho" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <span id="tituloModalPedido">Itens do pedido</span>
                    <small id="subtituloModalPedido" class="d-block text-muted mt-1"></small>
                </h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="cartBody"></div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">
                    Fechar
                </button>

                <button id="btnImprimirCarrinho" class="btn btn-outline-dark">
                    🖨️ Imprimir pedido
                </button>

                <button id="btnSalvarModal" class="btn btn-warning">
                    Enviar pedido
                </button>
            </div>

        </div>
    </div>
</div>

<div id="toast" class="toast-app"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
const state = {
    produtos: [],
    categorias: [],
    categoria: 'Todos',

    pedido_id: null,
    mesa: null,
    cliente: null,
    tipo: null,

    carrinho: {}
};

const atendenteLogado = <?= json_encode($_SESSION['user_nome'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const utils = {
    money(v){
        return Number(v || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    escape(s){
        return String(s ?? '').replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[m]);
    },

    produto(id){
        return state.produtos.find(p => Number(p.id) === Number(id));
    },

    adicionalAtivo(produto){
        return Number(produto?.adicional_permitido || 0) === 1 &&
               Number(produto?.valor_adicional || produto?.adicional || 0) > 0;
    },

    valorAdicional(produto){
        if(!this.adicionalAtivo(produto)){
            return 0;
        }

        return Number(produto.valor_adicional || produto.adicional || 0);
    },

    permitePonto(produto){
        return Number(produto?.permite_ponto || 0) === 1;
    },

    itemTotal(produto, item){
        const preco = Number(produto?.preco || 0);

        const qtdAdicionais = this.adicionalAtivo(produto)
            ? Math.max(0, Math.min(5, Number(item?.adicional || 0)))
            : 0;

        const adicional = qtdAdicionais * this.valorAdicional(produto);

        return Number(item?.qtd || 0) * (preco + adicional);
    },

    vibrar(ms = 60){
        navigator.vibrate?.(ms);
    }
};

function toast(msg, tipo = 'ok'){

    const el = document.getElementById('toast');

    el.className = 'toast-app show ' + tipo;
    el.textContent = msg;

    clearTimeout(el._timer);

    el._timer = setTimeout(() => {
        el.className = 'toast-app';
    }, 1800);
}

function isErroConexao(err){

    const msg = String(err?.message || err || '').toLowerCase();

    return (
        msg.includes('failed to fetch') ||
        msg.includes('networkerror') ||
        msg.includes('load failed') ||
        msg.includes('erro de conexão') ||
        msg.includes('erro de conexao') ||
        msg.includes('erro ao conectar ao banco') ||
        msg.includes('erro ao conectar') ||
        msg.includes('no such file or directory') ||
        msg.includes('unexpected token')
    );
}

function toastErroSeNaoForConexao(err, fallback){

    console.error(err);

    if(isErroConexao(err)){
        return;
    }

    toast(err?.message || fallback, 'erro');
}

const api = {
    async produtos(){

        const res = await fetch('listar_produtos.php', {
            cache: 'no-store'
        });

        const data = await res.json();

        if(!Array.isArray(data)){
            throw new Error(data?.erro || 'Produtos inválidos');
        }

        return data;
    },

    async mesas(){

        const res = await fetch('listar_mesas.php', {
            cache: 'no-store'
        });

        const data = await res.json();

        if(!Array.isArray(data)){
            throw new Error(data?.erro || 'Mesas inválidas');
        }

        return data;
    },

    async clientesAbertos(){

        const res = await fetch('listar_clientes_abertos.php', {
            cache: 'no-store'
        });

        const text = await res.text();

        let data;

        try {
            data = JSON.parse(text);
        } catch(e) {
            throw new Error('listar_clientes_abertos.php retornou resposta inválida: ' + text.substring(0, 120));
        }

        if(!res.ok){
            throw new Error(data?.erro || 'Erro ao listar clientes abertos');
        }

        if(!Array.isArray(data)){
            throw new Error(data?.erro || 'Clientes abertos inválidos');
        }

        return data;
    },

    async buscarPedido(pedidoId){

        const res = await fetch(
            'buscar_pedido.php?pedido_id=' + encodeURIComponent(pedidoId),
            {
                cache: 'no-store'
            }
        );

        const text = await res.text();

        let data;

        try {
            data = JSON.parse(text);
        } catch(e) {
            throw new Error(
                'buscar_pedido.php retornou resposta inválida: ' +
                text.substring(0, 180)
            );
        }

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao buscar pedido');
        }

        return data.pedido;
    },

    async salvar(payload){

        const res = await fetch('salvar_pedido.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao salvar pedido');
        }

        return data;
    }
};



function gerarCartKey(produtoId){
    return 'novo_' + produtoId + '_' + Date.now() + '_' + Math.floor(Math.random() * 100000);
}

function itemProdutoId(item){
    return Number(item?.produto_id ?? item?.id ?? 0);
}

function somarQtdProduto(produtoId){
    return Object.values(state.carrinho)
        .filter(item => itemProdutoId(item) === Number(produtoId))
        .reduce((total, item) => total + Number(item.qtd || 0), 0);
}

function itemPadraoAberto(produtoId){

    return Object.entries(state.carrinho).find(([key, item]) => {

        const locked = item.locked === true || Number(item.locked || 0) === 1;

        return !locked &&
            itemProdutoId(item) === Number(produtoId) &&
            Number(item.adicional || 0) === 0 &&
            String(item.obs || '') === '' &&
            String(item.ponto || '') === '';
    });
}

function ultimoItemReduzivel(produtoId){

    const entradas = Object.entries(state.carrinho)
        .filter(([key, item]) =>
            itemProdutoId(item) === Number(produtoId) &&
            podeReduzirItem(item)
        );

    if(!entradas.length){
        return null;
    }

    /*
     * Prioriza itens novos/sem trava. Se não houver, pega o último acréscimo
     * acima da quantidade original.
     */
    const novo = entradas
        .reverse()
        .find(([key, item]) => !(item.locked === true || Number(item.locked || 0) === 1));

    return novo || entradas[0];
}

function normalizarCarrinhoCarregado(){

    const novoCarrinho = {};

    Object.entries(state.carrinho || {}).forEach(([key, item]) => {

        const produtoId = Number(
            item.produto_id ??
            item.id ??
            key
        );

        const itemId = Number(
            item.item_id ??
            item.id_item ??
            0
        );

        const cartKey = item.cart_key ||
            (
                itemId > 0
                    ? 'item_' + itemId
                    : gerarCartKey(produtoId)
            );

        novoCarrinho[cartKey] = {
            ...item,
            cart_key: cartKey,
            item_id: itemId > 0 ? itemId : null,
            id: produtoId,
            produto_id: produtoId,
            qtd: Number(item.qtd || item.quantidade || 0),
            adicional: Math.max(0, Math.min(5, Number(item.adicional || 0))),
            obs: item.obs ?? item.observacao ?? '',
            observacao: item.observacao ?? item.obs ?? '',
            ponto: item.ponto ?? '',
            locked: true,
            qtd_original: Number(item.qtd_original || item.quantidade_original || item.qtd || item.quantidade || 0)
        };
    });

    state.carrinho = novoCarrinho;
}

function podeReduzirItem(item){

    if(!item){
        return false;
    }

    const locked = item.locked === true || Number(item.locked || 0) === 1;

    if(!locked){
        return true;
    }

    const qtdAtual = Number(item.qtd || 0);

    const qtdOriginal = Number(item.qtd_original || item.quantidade_original || item.qtd || 0);

    /*
     * Permite reduzir apenas acréscimos ainda não enviados.
     * Exemplo:
     * original = 2
     * garçom aumentou para 3
     * pode voltar para 2, mas não para 1.
     */
    return qtdAtual > qtdOriginal;
}

function limparInputCliente(){

    const clienteInput = document.getElementById('cliente');

    if(clienteInput){
        clienteInput.value = '';
    }
}

function setSessao({
    pedido_id = null,
    mesa = null,
    cliente = null,
    tipo = null
}){

    state.pedido_id = pedido_id;
    state.mesa = mesa;
    state.cliente = cliente;
    state.tipo = tipo;

    limparInputCliente();

    renderStatusSessao();
    renderMesas();
    carregarClientesAbertos();
}

function limparSessao(){

    state.pedido_id = null;
    state.mesa = null;
    state.cliente = null;
    state.tipo = null;
    state.carrinho = {};

    limparInputCliente();

    renderAll();
}

function renderStatusSessao(){

    const el = document.getElementById('statusSessao');

    if(!state.tipo){

        el.innerHTML = 'Selecione uma mesa ou informe um cliente.';
        return;
    }

    const ref = state.tipo === 'mesa'
        ? `Mesa ${state.mesa}`
        : `Cliente ${utils.escape(state.cliente)}`;

    el.innerHTML = `
        <div class="d-flex justify-content-between align-items-center gap-2">
            <div>
                <strong>${ref}</strong>
                ${
                    state.pedido_id
                    ? `<div class="small text-secondary">Pedido #${state.pedido_id}</div>`
                    : '<div class="small text-secondary">Novo pedido</div>'
                }
            </div>

            <button class="btn btn-sm btn-outline-light" onclick="limparSessao()">
                limpar
            </button>
        </div>
    `;
}

let clientesAbertosCache = [];

async function carregarClientesAbertos(){

    const box = document.getElementById('clientesAbertos');

    if(!box){
        console.warn('Elemento #clientesAbertos não encontrado no HTML.');
        return;
    }

    try {

        const clientes = await api.clientesAbertos();

        clientesAbertosCache = clientes;

        if(!clientes.length){
            box.innerHTML = '<div class="clientes-vazio">Nenhum cliente aberto.</div>';
            return;
        }

        box.innerHTML = clientes.map(c => {

            const pedidoId = Number(c.pedido_id || c.id || 0);

            const ativo = state.tipo === 'cliente' &&
                Number(state.pedido_id || 0) === pedidoId;

            return `
                <button
                    type="button"
                    class="btn-cliente-aberto ${ativo ? 'ativa' : ''}"
                    data-cliente-pedido-id="${pedidoId}"
                >
                    <strong>${utils.escape(c.cliente || 'Cliente')}</strong>
                    <small>#${pedidoId} • R$ ${utils.money(c.total || 0)}</small>
                </button>
            `;
        }).join('');

    } catch(err){

        console.error(err);

        box.innerHTML = `
            <div class="clientes-erro">
                Erro ao carregar clientes abertos.
                <br>
                <small>${utils.escape(err.message || '')}</small>
            </div>
        `;
    }
}

document.addEventListener('click', async e => {

    const btn = e.target.closest('[data-cliente-pedido-id]');

    if(!btn){
        return;
    }

    const pedidoId = Number(btn.dataset.clientePedidoId || 0);

    if(pedidoId <= 0){
        return;
    }

    utils.vibrar();

    try {

        const pedido = await api.buscarPedido(pedidoId);

        state.carrinho = pedido.carrinho || {};

        normalizarCarrinhoCarregado();

        setSessao({
            pedido_id: pedido.pedido_id,
            mesa: null,
            cliente: pedido.cliente,
            tipo: 'cliente'
        });

        renderAll();

        await carregarClientesAbertos();

        toast('Cliente carregado', 'ok');

    } catch(err){

        toastErroSeNaoForConexao(err, 'Erro ao abrir cliente');
    }
});

let mesasOcupadas = [];

async function carregarMesas(){

    try {

        mesasOcupadas = await api.mesas();

        renderMesas();

    } catch(err){

        toastErroSeNaoForConexao(err, 'Erro ao carregar mesas');
    }
}

function renderMesas(){

    const box = document.getElementById('mesas');

    const ocupadasMap = new Map(
        mesasOcupadas.map(m => [
            Number(m.mesa),
            m
        ])
    );

    let html = '';

    for(let i = 1; i <= 30; i++){

        const ocupada = ocupadasMap.get(i);

        const ativa = state.tipo === 'mesa' && Number(state.mesa) === i;

        const totalMesa = Number(
            ocupada?.total ??
            ocupada?.valor_total ??
            ocupada?.total_pedido ??
            ocupada?.total_geral ??
            0
        );

        html += `
            <button
                class="btn-mesa ${ocupada ? 'ocupada' : ''} ${ativa ? 'ativa' : ''}"
                data-mesa="${i}"
                data-pedido-id="${ocupada?.pedido_id || ''}"
            >
                ${i}
                ${
                    ocupada
                    ? `<small>#${ocupada.pedido_id} • R$ ${utils.money(totalMesa)}</small>`
                    : ''
                }
            </button>
        `;
    }

    box.innerHTML = html;
}

document.getElementById('mesas').addEventListener('click', async e => {

    const btn = e.target.closest('.btn-mesa');

    if(!btn) return;

    const mesa = Number(btn.dataset.mesa);
    const pedidoId = Number(btn.dataset.pedidoId || 0);

    utils.vibrar();

    if(pedidoId > 0){

        try {

            const pedido = await api.buscarPedido(pedidoId);

            state.carrinho = pedido.carrinho || {};

            normalizarCarrinhoCarregado();

            setSessao({
                pedido_id: pedido.pedido_id,
                mesa: pedido.mesa,
                cliente: null,
                tipo: 'mesa'
            });

            renderAll();

            toast('Mesa carregada', 'ok');

        } catch(err){

            toastErroSeNaoForConexao(err, 'Erro ao abrir mesa');
        }

        return;
    }

    state.carrinho = {};

    setSessao({
        pedido_id: null,
        mesa: mesa,
        cliente: null,
        tipo: 'mesa'
    });

    renderAll();
});

document.getElementById('btnCliente').addEventListener('click', () => {

    const nome = document.getElementById('cliente').value.trim();

    if(!nome){
        toast('Informe o nome do cliente', 'warn');
        return;
    }

    state.carrinho = {};

    setSessao({
        pedido_id: null,
        mesa: null,
        cliente: nome,
        tipo: 'cliente'
    });

    renderAll();

    toast('Cliente aberto', 'ok');
});

async function carregarProdutos(){

    try {

        state.produtos = await api.produtos();

        const cats = Array.from(
            new Set(
                state.produtos.map(p => p.cat || p.categoria || 'Sem categoria')
            )
        );

        state.categorias = ['Todos', ...cats];

        renderAll();

    } catch(err){

        toastErroSeNaoForConexao(err, 'Erro ao carregar produtos');
    }
}

function renderCategorias(){

    const el = document.getElementById('categorias');

    el.innerHTML = state.categorias.map(cat => `
        <button
            class="${cat === state.categoria ? 'ativa' : ''}"
            data-cat="${utils.escape(cat)}"
        >
            ${utils.escape(cat)}
        </button>
    `).join('');
}

document.getElementById('categorias').addEventListener('click', e => {

    const btn = e.target.closest('button');

    if(!btn) return;

    state.categoria = btn.dataset.cat;

    renderAll();
});

document.getElementById('busca').addEventListener('input', () => {
    renderProdutos();
});

function renderProdutos(){

    const busca = document.getElementById('busca').value.trim().toLowerCase();

    const el = document.getElementById('produtos');

    const lista = state.produtos.filter(p => {

        const cat = p.cat || p.categoria || 'Sem categoria';

        if(state.categoria !== 'Todos' && cat !== state.categoria){
            return false;
        }

        const nome = String(p.nome || '').toLowerCase();

        return !busca || nome.includes(busca);
    });

    if(!lista.length){

        el.innerHTML = '<p class="text-center w-100">Nenhum produto encontrado.</p>';
        return;
    }

    el.innerHTML = lista.map(p => {

        const qtd = somarQtdProduto(p.id);

        const reduzivel = ultimoItemReduzivel(p.id);

        const podeReduzir = !!reduzivel;

        const adicionalAtivo = utils.adicionalAtivo(p);

        const valorAdicional = utils.valorAdicional(p);

        return `
            <div class="produto">
                <div>
                    <div class="cat">
                        ${utils.escape(p.cat || p.categoria || '')}
                    </div>

                    <div class="nome">
                        ${utils.escape(p.nome)}
                    </div>

                    <div class="preco">
                        R$ ${utils.money(p.preco)}
                    </div>

                    ${
                        adicionalAtivo
                        ? `<div class="adicional-info">Adicional: + R$ ${utils.money(valorAdicional)}</div>`
                        : ''
                    }
                </div>

                <div class="acoes">
                    <button
                        class="btn-minus"
                        data-minus="${p.id}"
                        ${!podeReduzir ? 'disabled' : ''}
                    >
                        −
                    </button>

                    <span class="qtd">${qtd}</span>

                    <button class="btn-plus" data-plus="${p.id}">
                        +
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

document.getElementById('produtos').addEventListener('click', e => {

    const plus = e.target.closest('[data-plus]');
    const minus = e.target.closest('[data-minus]');

    if(plus){

        alterarProduto(Number(plus.dataset.plus), 1);
        return;
    }

    if(minus){

        alterarProduto(Number(minus.dataset.minus), -1);
    }
});

function alterarProduto(produtoId, delta){

    if(!state.tipo){
        toast('Selecione uma mesa ou cliente primeiro', 'warn');
        utils.vibrar(100);
        return;
    }

    const produto = utils.produto(produtoId);

    if(!produto){
        return;
    }

    if(delta > 0){

        /*
         * O botão + do card incrementa uma linha padrão aberta:
         * mesmo produto, sem adicional, sem obs e sem ponto.
         * Se a linha existente foi personalizada, cria uma nova linha.
         */
        let entrada = itemPadraoAberto(produtoId);

        if(!entrada){

            const key = gerarCartKey(produtoId);

            state.carrinho[key] = {
                cart_key: key,
                item_id: null,
                id: produtoId,
                produto_id: produtoId,
                qtd: 0,
                qtd_original: 0,
                locked: false,
                adicional: 0,
                obs: '',
                observacao: '',
                ponto: ''
            };

            entrada = [key, state.carrinho[key]];
        }

        entrada[1].qtd += delta;

        utils.vibrar();

        renderAll();

        return;
    }

    if(delta < 0){

        const entrada = ultimoItemReduzivel(produtoId);

        if(!entrada){
            toast('Não é possível reduzir item já enviado', 'warn');
            utils.vibrar(120);
            return;
        }

        const [key, item] = entrada;

        item.qtd += delta;

        if(item.qtd <= 0){
            delete state.carrinho[key];
        }

        utils.vibrar();

        renderAll();
    }
}


function renderTituloModalPedido(){

    const titulo = document.getElementById('tituloModalPedido');
    const subtitulo = document.getElementById('subtituloModalPedido');

    if(!titulo || !subtitulo){
        return;
    }

    titulo.textContent = 'Itens do pedido';

    if(!state.tipo){
        subtitulo.textContent = 'Nenhuma mesa ou cliente selecionado';
        return;
    }

    const ref = state.tipo === 'mesa'
        ? `Mesa ${state.mesa}`
        : `Cliente ${state.cliente || '-'}`;

    const pedido = state.pedido_id
        ? `#${state.pedido_id}`
        : 'Novo pedido';

    subtitulo.textContent = `${ref} • ${pedido}`;
}

function renderCarrinho(){

    const body = document.getElementById('cartBody');

    const itens = Object.entries(state.carrinho);

    if(!itens.length){

        body.innerHTML = '<p class="text-muted">Carrinho vazio.</p>';
        return;
    }

    body.innerHTML = itens.map(([key, item]) => {

        const produto = utils.produto(itemProdutoId(item));

        if(!produto){
            return '';
        }

        const adicionalAtivo = utils.adicionalAtivo(produto);

        const total = utils.itemTotal(produto, item);

        const podeReduzir = podeReduzirItem(item);

        const locked = item.locked === true || Number(item.locked || 0) === 1;

        const qtdOriginal = Number(item.qtd_original || item.quantidade_original || 0);

        return `
            <div class="cart-item" data-cart-key="${utils.escape(key)}">
                <div class="cart-title">
                    <span>${utils.escape(produto.nome)}</span>
                    <span>R$ ${utils.money(total)}</span>
                </div>

                ${locked ? `<div class="badge-enviado">Já enviado: ${qtdOriginal} un. não pode reduzir</div>` : ''}

                <div class="cart-controls">
                    <button
                        class="cart-btn-minus"
                        data-cart-minus="${utils.escape(key)}"
                        ${!podeReduzir ? 'disabled' : ''}
                    >
                        −
                    </button>

                    <strong>${item.qtd}</strong>

                    <button class="cart-btn-plus" data-cart-plus="${utils.escape(key)}">
                        +
                    </button>
                </div>

                ${
                    adicionalAtivo
                    ? `
                        <div class="my-2">
                            <div class="cart-select-label">
                                Adicionais
                            </div>

                            <select
                                class="form-select form-select-sm"
                                data-cart-adicional="${utils.escape(key)}"
                                ${locked ? 'disabled' : ''}
                            >
                                ${[0,1,2,3,4,5].map(n => `
                                    <option
                                        value="${n}"
                                        ${Number(item.adicional || 0) === n ? 'selected' : ''}
                                    >
                                        ${
                                            n === 0
                                            ? 'Sem adicional'
                                            : `${n} ${n > 1 ? 'adicionais' : 'adicional'} (+ R$ ${utils.money(utils.valorAdicional(produto) * n)} por un.)`
                                        }
                                    </option>
                                `).join('')}
                            </select>

                            <div class="cart-select-help">
                                Valor unitário do adicional: R$ ${utils.money(utils.valorAdicional(produto))}
                            </div>
                        </div>
                    `
                    : ''
                }

                ${
                    Number(produto.permite_obs || 0) === 1
                    ? `
                        <input
                            class="form-control form-control-sm mb-2"
                            placeholder="Observação"
                            data-cart-obs="${utils.escape(key)}"
                            value="${utils.escape(item.obs || '')}"
                            ${locked ? 'readonly' : ''}
                        >
                    `
                    : ''
                }

                ${
                    utils.permitePonto(produto)
                    ? `
                        <div class="mt-2">
                            <div class="cart-select-label">
                                Ponto / preparo
                            </div>

                            <select
                                class="form-select form-select-sm"
                                data-cart-ponto="${utils.escape(key)}"
                                ${locked ? 'disabled' : ''}
                            >
                                ${[
                                    '',
                                    'Normal',
                                    'Mal passado',
                                    'Ao ponto',
                                    'Bem passado',
                                    'Pouco assado',
                                    'Bem assado',
                                    'Torrado'
                                ].map(opcao => `
                                    <option
                                        value="${utils.escape(opcao)}"
                                        ${(item.ponto || '') === opcao ? 'selected' : ''}
                                    >
                                        ${opcao === '' ? 'Selecione o ponto/preparo' : utils.escape(opcao)}
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                    `
                    : ''
                }
            </div>
        `;
    }).join('');
}

document.getElementById('cartBody').addEventListener('click', e => {

    const plus = e.target.closest('[data-cart-plus]');
    const minus = e.target.closest('[data-cart-minus]');

    if(plus){

        alterarLinhaCarrinho(plus.dataset.cartPlus, 1);
        return;
    }

    if(minus){
        alterarLinhaCarrinho(minus.dataset.cartMinus, -1);
    }
});

function alterarLinhaCarrinho(key, delta){

    const item = state.carrinho[key];

    if(!item){
        return;
    }

    if(delta < 0 && !podeReduzirItem(item)){
        toast('Não é possível reduzir item já enviado', 'warn');
        utils.vibrar(120);
        return;
    }

    item.qtd += delta;

    if(item.qtd <= 0){
        delete state.carrinho[key];
    }

    utils.vibrar();

    renderAll();
}


document.getElementById('cartBody').addEventListener('change', e => {

    const adicional = e.target.closest('[data-cart-adicional]');
    const ponto = e.target.closest('[data-cart-ponto]');

    if(adicional){

        const key = adicional.dataset.cartAdicional;

        if(state.carrinho[key] && !(state.carrinho[key].locked === true || Number(state.carrinho[key].locked || 0) === 1)){
            state.carrinho[key].adicional = Math.max(0, Math.min(5, Number(adicional.value || 0)));
        }

        renderAll();
        return;
    }

    if(ponto){

        const key = ponto.dataset.cartPonto;

        if(state.carrinho[key] && !(state.carrinho[key].locked === true || Number(state.carrinho[key].locked || 0) === 1)){
            state.carrinho[key].ponto = ponto.value;
        }

        renderResumo();
    }
});


document.getElementById('cartBody').addEventListener('input', e => {

    const obs = e.target.closest('[data-cart-obs]');

    if(obs){

        const key = obs.dataset.cartObs;

        if(state.carrinho[key] && !(state.carrinho[key].locked === true || Number(state.carrinho[key].locked || 0) === 1)){
            state.carrinho[key].obs = obs.value;
            state.carrinho[key].observacao = obs.value;
        }
    }

    renderResumo();
});


function renderResumo(){

    const itens = Object.values(state.carrinho);

    let qtd = 0;
    let total = 0;

    itens.forEach(item => {

        const produto = utils.produto(itemProdutoId(item));

        qtd += Number(item.qtd || 0);

        total += utils.itemTotal(produto, item);
    });

    const qtdCarrinhoEl = document.getElementById('qtdCarrinho');
    const totalEl = document.getElementById('total');

    if(qtdCarrinhoEl){
        qtdCarrinhoEl.textContent = qtd;
        qtdCarrinhoEl.closest('.btn-carrinho').classList.toggle('carrinho-com-itens', qtd > 0);
    }

    if(totalEl){
        totalEl.textContent = utils.money(total);
    }
}

function renderAll(){

    renderStatusSessao();
    renderTituloModalPedido();
    renderMesas();
    renderCategorias();
    renderProdutos();
    renderCarrinho();
    renderResumo();
}

function fecharModalCarrinho(){

    const modalEl = document.getElementById('modalCarrinho');

    if(!modalEl || !modalEl.classList.contains('show')){
        return;
    }

    if(typeof bootstrap === 'undefined' || !bootstrap.Modal){
        return;
    }

    const modal = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);

    modal.hide();
}

function imprimirPedidoCarrinho(){
    
    const NOME_ESTABELECIMENTO = 'BURGUERSHAKE';

    if(!state.tipo){
        toast('Selecione uma mesa ou cliente', 'warn');
        return;
    }

    const itens = Object.values(state.carrinho)
        .filter(item => Number(item.qtd || 0) > 0);

    if(!itens.length){
        toast('Adicione pelo menos um item', 'warn');
        return;
    }

    const janela = window.open('', 'imprimirPedidoCarrinho', 'width=430,height=760,scrollbars=yes');

    if(!janela){
        toast('Permita pop-ups para imprimir o pedido', 'warn');
        return;
    }

    const referencia = state.tipo === 'mesa'
        ? `Mesa ${state.mesa}`
        : `Cliente ${state.cliente || '-'}`;

    const pedido = state.pedido_id
        ? `#${state.pedido_id}`
        : 'Novo pedido';

    const dataHora = new Date().toLocaleString('pt-BR');

    let totalGeral = 0;

    const itensHtml = itens.map(item => {

        const produto = utils.produto(itemProdutoId(item));

        const nome = produto?.nome || `Produto #${itemProdutoId(item)}`;
        const quantidade = Number(item.qtd || 0);
        const precoBase = Number(produto?.preco || 0);
        const quantidadeAdicionais = utils.adicionalAtivo(produto)
            ? Math.max(0, Math.min(5, Number(item.adicional || 0)))
            : 0;
        const valorAdicional = utils.valorAdicional(produto);
        const totalItem = utils.itemTotal(produto, item);
        const locked = item.locked === true || Number(item.locked || 0) === 1;

        totalGeral += totalItem;

        const detalhes = [];

        if(quantidadeAdicionais > 0){
            detalhes.push(
                `${quantidadeAdicionais} ${quantidadeAdicionais > 1 ? 'adicionais' : 'adicional'} por unidade: R$ ${utils.money(valorAdicional * quantidadeAdicionais)}`
            );
        }

        if(String(item.ponto || '').trim() !== ''){
            detalhes.push(`Ponto/preparo: ${item.ponto}`);
        }

        if(String(item.obs || '').trim() !== ''){
            detalhes.push(`Obs.: ${item.obs}`);
        }

        if(locked){
            detalhes.push('Item já enviado no pedido');
        }

        const detalhesHtml = detalhes.length
            ? detalhes.map(texto => `<div class="detalhe">${utils.escape(texto)}</div>`).join('')
            : '';

        return `
            <section class="item">
                <div class="linha-item">
                    <strong>${quantidade}x ${utils.escape(nome)}</strong>
                    <span>R$ ${utils.money(totalItem)}</span>
                </div>
                <div class="detalhe">Unitário base: R$ ${utils.money(precoBase)}</div>
                ${detalhesHtml}
            </section>
        `;
    }).join('');

    const atendenteHtml = String(atendenteLogado || '').trim() !== ''
        ? `<div><strong>Atendente:</strong> ${utils.escape(atendenteLogado)}</div>`
        : '';

    janela.document.open();
    janela.document.write(`<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Imprimir pedido do carrinho</title>
<style>
    @page {
        size: 58mm auto;
        margin: 3mm;
    }

    * {
        box-sizing: border-box;
    }

    body {
        width: 52mm;
        margin: 0 auto;
        color: #000;
        background: #fff;
        font-family: Arial, sans-serif;
        font-size: 11px;
        line-height: 1.35;
    }

    .centro {
        text-align: center;
    }

    h1 {
        font-size: 15px;
        margin: 0 0 4px;
        text-transform: uppercase;
    }

    .subtitulo {
        font-size: 11px;
        font-weight: bold;
        margin-bottom: 7px;
    }

    .meta {
        border-top: 1px dashed #000;
        border-bottom: 1px dashed #000;
        padding: 6px 0;
        margin: 7px 0;
    }

    .item {
        border-bottom: 1px dashed #000;
        padding: 6px 0;
    }

    .linha-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 6px;
        font-size: 11px;
    }

    .linha-item strong {
        flex: 1;
    }

    .linha-item span {
        white-space: nowrap;
        font-weight: bold;
    }

    .detalhe {
        margin-top: 2px;
        font-size: 10px;
    }

    .total {
        display: flex;
        justify-content: space-between;
        gap: 6px;
        margin-top: 8px;
        padding-top: 6px;
        border-top: 1px solid #000;
        font-size: 13px;
        font-weight: bold;
    }

    .rodape {
        margin-top: 10px;
        padding-top: 6px;
        border-top: 1px dashed #000;
        text-align: center;
        font-size: 10px;
    }
    
    .cabecalho-cupom {
        margin-bottom: 6px;
    }
    
    .nome-estabelecimento {
        font-size: 15px;
        font-weight: 900;
        text-transform: uppercase;
        margin-bottom: 4px;
        letter-spacing: .3px;
    }
    
    .titulo-cupom {
        font-size: 13px;
        font-weight: 900;
        text-transform: uppercase;
    }
    
    .subtitulo-cupom {
        font-size: 10px;
        font-weight: 700;
        line-height: 1.25;
        margin-top: 2px;
    }
</style>
</head>
<body>
    <div class="centro cabecalho-cupom">
        <div class="nome-estabelecimento">
            ${utils.escape(NOME_ESTABELECIMENTO)}
        </div>
    
        <div class="titulo-cupom">
            PEDIDO DO CARRINHO
        </div>
    
        <div class="subtitulo-cupom">
            Prévia para impressão pelo atendente
        </div>
    </div>

    <div class="meta">
        <div><strong>Referência:</strong> ${utils.escape(referencia)}</div>
        <div><strong>Pedido:</strong> ${utils.escape(pedido)}</div>
        ${atendenteHtml}
        <div><strong>Emissão:</strong> ${utils.escape(dataHora)}</div>
    </div>

    ${itensHtml}

    <div class="total">
        <span>Total</span>
        <span>R$ ${utils.money(totalGeral)}</span>
    </div>

    <div class="rodape">
        Impressão gerada a partir do carrinho atual.
    </div>
</body>
</html>`);
    janela.document.close();

    janela.focus();

    setTimeout(() => {
        janela.print();
    }, 350);
}

async function salvarPedido(){

    if(!state.tipo){
        toast('Selecione uma mesa ou cliente', 'warn');
        return;
    }

    const itens = Object.values(state.carrinho)
        .filter(i => Number(i.qtd) > 0)
        .map(i => ({
            cart_key: i.cart_key || '',
            item_id: i.item_id || null,
            id: Number(itemProdutoId(i)),
            produto_id: Number(itemProdutoId(i)),
            qtd: Number(i.qtd),
            adicional: Math.max(0, Math.min(5, Number(i.adicional || 0))),
            obs: i.obs || '',
            observacao: i.obs || '',
            ponto: utils.permitePonto(utils.produto(itemProdutoId(i))) ? (i.ponto || '') : ''
        }))

    if(!itens.length){
        toast('Adicione pelo menos um item', 'warn');
        return;
    }

    const payload = {
        pedido_id: state.pedido_id,
        mesa: state.tipo === 'mesa' ? state.mesa : null,
        cliente: state.tipo === 'cliente' ? state.cliente : null,
        itens: itens
    };

    const btn = document.getElementById('btnSalvar');
    const btnModal = document.getElementById('btnSalvarModal');

    btn.disabled = true;
    btnModal.disabled = true;

    try {

        const data = await api.salvar(payload);

        state.pedido_id = Number(data.pedido_id);

        toast('Pedido enviado com sucesso', 'ok');

        fecharModalCarrinho();

        utils.vibrar(120);
        
        
        
        /*
         * Depois de enviar o pedido, limpa a seleção atual para evitar
         * reaproveitar sem querer a mesma mesa/cliente e zera o carrinho
         * exibido na tela.
         */
        limparSessao();
        
        await carregarMesas();
        await carregarClientesAbertos();
        
        renderAll();




    } catch(err){

        toastErroSeNaoForConexao(err, 'Erro ao salvar pedido');

    } finally {

        btn.disabled = false;
        btnModal.disabled = false;
    }
}

document.getElementById('btnSalvar').addEventListener('click', salvarPedido);
document.getElementById('btnSalvarModal').addEventListener('click', salvarPedido);
document.getElementById('btnImprimirCarrinho').addEventListener('click', imprimirPedidoCarrinho);

async function init(){

    await carregarProdutos();
    await carregarMesas();
    await carregarClientesAbertos();

    renderAll();

    setInterval(() => {
        carregarMesas();
        carregarClientesAbertos();
    }, 8000);
}

init();
</script>

</body>
</html>