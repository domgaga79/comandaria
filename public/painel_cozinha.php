<?php
require __DIR__ . '/auth.php';
requireRole(['cozinha','admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require __DIR__ . '/conexao.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Cozinha | Comandaria Express</title>

<meta property="og:title" content="Comandaria Express" />
<meta property="og:description" content="Comandaria Express" />
<meta property="og:type" content="website" />
<meta property="og:image" content="images/comandaria_logo_og.png" />

<link rel="icon" href="images/comandaria_logo.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/cozinha.css?v=3">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/staff.css?v=2">
</head>

<body class="cd-staff cd-kitchen">
<?php $staffPage = 'painel_cozinha.php'; require __DIR__ . '/includes/staff_nav.php'; ?>
<header class="cozinha-topo">
    <a class="cozinha-marca" href="<?= authTipo() === 'admin' ? 'dashboard.php' : 'painel_cozinha.php' ?>">
        <img src="images/comandaria_logo.png" alt="" width="36" height="36">
        Comandaria Express
    </a>
    <div class="cozinha-userbox">
        <span><?= htmlspecialchars($_SESSION['user_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        <a href="logout.php">Sair</a>
    </div>
</header>

<main class="cozinha-main">
    <div class="cozinha-titulo">
        <h1>Cozinha</h1>
        <p id="conexao-cozinha" role="status" data-conexao="aguardando">Conectando…</p>
    </div>
    <div class="cozinha-controles">
        <label class="cozinha-filtro">
            <span>Filtrar pedido</span>
            <select id="filtro" class="form-select"><option value="">Todos os pedidos</option></select>
        </label>
        <div class="auto-print-box">
            <div class="form-check form-switch">
                <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    id="autoPrintItensNovos"
                    aria-describedby="descricao-auto-print"
                >
                <label class="form-check-label" for="autoPrintItensNovos">Impressão automática</label>
            </div>
            <small id="descricao-auto-print">Imprime itens novos e aumentos de quantidade.</small>
        </div>
    </div>

    <div id="pedidos" class="cozinha-quadro">
        <section class="cozinha-coluna" aria-labelledby="novos-titulo">
            <div class="cozinha-coluna-topo">
                <h2 id="novos-titulo">Novos</h2>
                <span id="total-novos" class="cozinha-contagem">0</span>
            </div>
            <div id="fila-novos" class="cozinha-fila"></div>
            <p class="cozinha-vazio">Aguardando novos pedidos.</p>
        </section>
        <section class="cozinha-coluna" aria-labelledby="preparo-titulo">
            <div class="cozinha-coluna-topo">
                <h2 id="preparo-titulo">Em preparo</h2>
                <span id="total-preparo" class="cozinha-contagem">0</span>
            </div>
            <div id="fila-preparo" class="cozinha-fila"></div>
            <p class="cozinha-vazio">Nenhum pedido iniciado.</p>
        </section>
        <section class="cozinha-coluna" aria-labelledby="prontos-titulo">
            <div class="cozinha-coluna-topo">
                <h2 id="prontos-titulo">Prontos</h2>
                <span id="total-prontos" class="cozinha-contagem">0</span>
            </div>
            <div id="fila-prontos" class="cozinha-fila"></div>
            <p class="cozinha-vazio">Nenhum pedido pronto.</p>
        </section>
    </div>
</main>

<div id="toast" role="status"></div>

<script>
/* =========================
   STATE
========================= */
const AUTO_PRINT_STORAGE_KEY = 'comandaria_cozinha_auto_print_itens_novos';

const state = {
    pedidos: [],
    filtro: null,
    ultimoHash: '',
    lockUntil: 0,
    alertados: new Set(),
    iniciado: false,

    // Agora a autoimpressão usa um iframe invisível, sem janela auxiliar.
    // A preferência pode permanecer ativa ao recarregar o painel.
    autoPrintAtivo: localStorage.getItem(AUTO_PRINT_STORAGE_KEY) === '1',
    autoPrintSnapshot: new Map(),
    autoPrintInicializado: false,
    autoPrintFila: [],
    autoPrintEmExecucao: false,
    autoPrintFrame: null
};

const dom = {
    cards: new Map(),
    filas: Object.fromEntries(
        ['novos', 'preparo', 'prontos'].map(etapa => [etapa, document.getElementById('fila-' + etapa)])
    )
};

let intervalo = 3000;
let tentativasErro = 0;

/* =========================
   UTILS
========================= */
const utils = {
    escape(s){
        return String(s ?? '').replace(/[&<>"']/g, m => ({
            '&':'&amp;',
            '<':'&lt;',
            '>':'&gt;',
            '"':'&quot;',
            "'":'&#039;'
        })[m]);
    },

    vibrar(ms = 60){
        navigator.vibrate?.(ms);
    },

    minutos(data){
        const ts = new Date(data).getTime();

        if(!ts || isNaN(ts)){
            return 0;
        }

        return Math.max(
            0,
            Math.floor((Date.now() - ts) / 60000)
        );
    },

    tempoClass(min){
        if(min >= 20) return 'urgente';
        if(min >= 10) return 'medio';

        return 'normal';
    },

    pedidoId(pedido){
        return Number(
            pedido.pedido_id ??
            pedido.id ??
            0
        );
    },

    itemId(item){
        return Number(
            item.item_id ??
            item.id ??
            0
        );
    },

    qtd(item){
        return Number(
            item.quantidade ??
            item.qtd ??
            0
        );
    },

    qtdPronta(item){
        return Number(
            item.quantidade_pronta ??
            item.qtd_pronta ??
            0
        );
    },

    itemPronto(item){
        return this.qtdPronta(item) >= this.qtd(item);
    },

    etapaPedido(pedido){
        const itens = pedido.itens || [];

        if(itens.every(item => this.itemPronto(item))){
            return 'prontos';
        }

        return itens.some(item => this.qtdPronta(item) > 0) ? 'preparo' : 'novos';
    },

    tituloPedido(pedido){
        return pedido.tipo === 'mesa'
            ? `Mesa ${pedido.identificador ?? pedido.referencia ?? pedido.mesa}`
            : `Cliente ${pedido.identificador ?? pedido.referencia ?? pedido.cliente}`;
    },

    dinheiro(v){
        return Number(v || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
};

/* =========================
   TOAST
========================= */
function toast(msg, tipo = 'ok'){

    const el = document.getElementById('toast');

    if(!el) return;

    const tipos = ['ok','erro','warn'];

    el.className = '';
    el.classList.add(
        'show',
        tipos.includes(tipo) ? tipo : 'ok'
    );

    el.textContent = msg;

    clearTimeout(el._timer);

    el._timer = setTimeout(() => {
        el.classList.remove('show');
    }, 1800);
}

/* =========================
   LOCK
========================= */
function isLocked(){
    return Date.now() < state.lockUntil;
}

function lock(ms = 500){
    state.lockUntil = Date.now() + ms;

    document.body.classList.add('locked');

    setTimeout(() => {
        document.body.classList.remove('locked');
    }, ms);
}

/* =========================
   AUTO IMPRESSÃO DE ITENS NOVOS
========================= */
function aplicarEstadoToggleAutoPrint(){
    const toggle = document.getElementById('autoPrintItensNovos');

    if(toggle){
        toggle.checked = !!state.autoPrintAtivo;
    }
}

function salvarPreferenciaAutoPrint(ativo){
    state.autoPrintAtivo = !!ativo;
    localStorage.setItem(
        AUTO_PRINT_STORAGE_KEY,
        state.autoPrintAtivo ? '1' : '0'
    );
}

function garantirIframeAutoPrint(){

    let frame = state.autoPrintFrame;

    if(frame && frame.isConnected){
        return frame;
    }

    frame = document.getElementById('cozinhaAutoPrintFrame');

    if(!frame){
        frame = document.createElement('iframe');
        frame.id = 'cozinhaAutoPrintFrame';
        frame.title = 'Impressão automática da cozinha';
        frame.setAttribute('aria-hidden', 'true');
        frame.tabIndex = -1;

        Object.assign(frame.style, {
            position: 'fixed',
            right: '-20px',
            bottom: '-20px',
            width: '1px',
            height: '1px',
            border: '0',
            opacity: '0',
            pointerEvents: 'none'
        });

        document.body.appendChild(frame);
    }

    state.autoPrintFrame = frame;

    return frame;
}

function gerarSnapshotItens(pedidos){

    const snapshot = new Map();

    (pedidos || []).forEach(pedido => {

        const pedidoId = utils.pedidoId(pedido);

        if(!pedidoId) return;

        (pedido.itens || []).forEach(item => {

            const itemId = utils.itemId(item);

            if(!itemId) return;

            snapshot.set(String(itemId), {
                itemId,
                pedidoId,
                quantidade: utils.qtd(item)
            });
        });
    });

    return snapshot;
}

function detectarItensNovosParaImpressao(pedidos){

    const novoSnapshot = gerarSnapshotItens(pedidos);

    /*
    |----------------------------------------------------------------------
    | Primeira carga:
    | apenas memoriza o estado atual da cozinha, sem imprimir pedidos antigos.
    |----------------------------------------------------------------------
    */
    if(!state.autoPrintInicializado){
        state.autoPrintSnapshot = novoSnapshot;
        state.autoPrintInicializado = true;
        return;
    }

    /*
    |----------------------------------------------------------------------
    | Com impressão desativada:
    | mantém o snapshot atualizado para não "represarmos" impressões antigas
    | quando o usuário reativar a opção.
    |----------------------------------------------------------------------
    */
    if(!state.autoPrintAtivo){
        state.autoPrintSnapshot = novoSnapshot;
        return;
    }

    const itensNovosParaImprimir = [];

    for(const [chave, atual] of novoSnapshot){

        const anterior = state.autoPrintSnapshot.get(chave);

        const quantidadeAtual = Number(atual.quantidade || 0);
        const quantidadeAnterior = Number(anterior?.quantidade || 0);

        const itemFoiCriado = !anterior;
        const quantidadeAumentou = anterior
            ? quantidadeAtual > quantidadeAnterior
            : false;

        /*
        |------------------------------------------------------------------
        | O gatilho é SOMENTE:
        | - item novo que surgiu na cozinha;
        | - acréscimo de quantidade em um item que já existia.
        |
        | Alteração de quantidade_pronta/status/finalização NÃO entra aqui.
        |------------------------------------------------------------------
        */
        if(itemFoiCriado || quantidadeAumentou){

            const quantidadeNova = itemFoiCriado
                ? quantidadeAtual
                : quantidadeAtual - quantidadeAnterior;

            if(Number(atual.itemId || 0) > 0 && quantidadeNova > 0){
                itensNovosParaImprimir.push({
                    itemId: Number(atual.itemId),
                    pedidoId: Number(atual.pedidoId || 0),
                    quantidadeNova: Number(quantidadeNova)
                });
            }
        }
    }

    state.autoPrintSnapshot = novoSnapshot;

    itensNovosParaImprimir.forEach(item => {
        enfileirarImpressaoItemNovo(item);
    });
}

function enfileirarImpressaoItemNovo(item){

    const itemId = Number(item?.itemId || 0);
    const pedidoId = Number(item?.pedidoId || 0);
    const quantidadeNova = Number(item?.quantidadeNova || 0);

    if(!itemId || quantidadeNova <= 0){
        return;
    }

    state.autoPrintFila.push({
        itemId,
        pedidoId,
        quantidadeNova
    });

    processarFilaImpressao();
}

function processarFilaImpressao(){

    if(state.autoPrintEmExecucao){
        return;
    }

    const itemNovo = state.autoPrintFila.shift();

    if(!itemNovo){
        return;
    }

    const frame = garantirIframeAutoPrint();

    if(!frame){
        toast('Não foi possível preparar a impressão automática.', 'erro');
        return;
    }

    state.autoPrintEmExecucao = true;

    const destino = 'imprimir_cozinha_item.php?item_id=' +
        encodeURIComponent(itemNovo.itemId) +
        '&qtd_impressao=' + encodeURIComponent(itemNovo.quantidadeNova) +
        '&_auto=1&_ts=' + Date.now();

    let finalizado = false;
    let timeoutSeguranca = null;

    const liberarFila = () => {

        if(finalizado){
            return;
        }

        finalizado = true;

        if(timeoutSeguranca){
            clearTimeout(timeoutSeguranca);
        }

        frame.onload = null;
        frame.onerror = null;

        state.autoPrintEmExecucao = false;
        processarFilaImpressao();
    };

    frame.onload = () => {
        /*
        |------------------------------------------------------------------
        | O cupom carrega no iframe e o próprio imprimir_cozinha_item.php
        | dispara window.print(). Este pequeno intervalo apenas evita que a
        | próxima impressão da fila atropelhe a anterior.
        |------------------------------------------------------------------
        */
        setTimeout(liberarFila, 1800);
    };

    frame.onerror = () => {
        console.error('Falha ao carregar o cupom de autoimpressão da cozinha.');
        liberarFila();
    };

    timeoutSeguranca = setTimeout(liberarFila, 5000);

    frame.src = destino;

    toast('Item novo recebido: impressão automática enviada.', 'warn');
}

/* =========================
   API
========================= */
const api = {
    async listar(){

        let url = 'listar_pedidos.php';

        if(state.ultimoHash){
            url += '?last=' + encodeURIComponent(state.ultimoHash);
        }

        const res = await fetch(url, {
            cache:'no-store'
        });

        if(!res.ok){
            throw new Error('Erro ao listar pedidos');
        }

        return res.json();
    },

    async produzirItem(itemId){

        const res = await fetch('atualizar_status_pedido.php', {
            method:'POST',
            headers:{
                'Content-Type':'application/json'
            },
            body: JSON.stringify({
                acao:'produzir_item',
                item_id:itemId
            })
        });

        if(!res.ok){
            throw new Error('Erro ao produzir item');
        }

        return res.json();
    }
};

/* =========================
   TEMPOS
========================= */
function atualizarTempos(){

    document.querySelectorAll('.tempo').forEach(el => {

        const card = el.closest('.card');

        if(!card || !el.dataset.ts || card.dataset.etapa === 'prontos') return;

        const min = utils.minutos(
            Number(el.dataset.ts)
        );

        if(Number(el.dataset.min) === min) return;

        el.dataset.min = min;
        el.textContent = `${min} min`;

        const classe = utils.tempoClass(min);

        card.classList.remove(
            'urgente',
            'medio',
            'normal'
        );

        card.classList.add(classe);
    });
}

setInterval(atualizarTempos, 5000);

/* =========================
   RENDER
========================= */
const render = {
    update(){
        this.select();
        this.pedidos();
    },

    select(){

        const select = document.getElementById('filtro');

        if(!select) return;

        const pedidosVisiveis = state.pedidos.filter(p => (p.itens || []).length > 0);

        const options = pedidosVisiveis.map(p => `
            <option value="${utils.pedidoId(p)}">
                ${utils.escape(utils.tituloPedido(p))} · #${utils.pedidoId(p)}
            </option>
        `).join('');

        const html = '<option value="">Todos os pedidos</option>' + options;

        if(select.innerHTML !== html){
            select.innerHTML = html;
        }

        if(state.filtro && !pedidosVisiveis.some(p => utils.pedidoId(p) === state.filtro)){
            state.filtro = null;
        }

        select.value = state.filtro ?? '';
    },

    pedidos(){

        const lista = state.pedidos
            .filter(p => (!state.filtro || utils.pedidoId(p) === state.filtro) && (p.itens || []).length)
            .sort((a,b) => {
                const da = new Date(a.created_at ?? a.data_criacao ?? 0);
                const db = new Date(b.created_at ?? b.data_criacao ?? 0);

                return da - db;
            });

        const contagens = {novos: 0, preparo: 0, prontos: 0};
        const idsAtuais = new Set();

        lista.forEach(pedido => {

            const pedidoId = utils.pedidoId(pedido);

            if(!pedidoId) return;

            const id = String(pedidoId);

            idsAtuais.add(id);

            let card = dom.cards.get(id);

            const hash = JSON.stringify({
                status: pedido.status,
                itens: (pedido.itens || []).map(i => [
                    utils.itemId(i),
                    utils.qtd(i),
                    utils.qtdPronta(i),
                    i.status,
                    i.obs ?? i.observacao,
                    i.ponto,
                    i.adicional,
                    i.valor_adicional
                ])
            });

            if(!card){

                card = this.criarCard(pedido);
                card._hash = hash;

                dom.cards.set(id, card);

                card.classList.add('novo');

                setTimeout(() => {
                    card.classList.remove('novo');
                }, 800);
            } else if(card._hash !== hash){
                card._hash = hash;
                this.atualizarCard(card, pedido);
            }

            const etapa = utils.etapaPedido(pedido);
            card.dataset.etapa = etapa;

            const fila = dom.filas[etapa];
            const posicao = contagens[etapa]++;
            if(fila.children[posicao] !== card){
                fila.insertBefore(card, fila.children[posicao] || null);
            }
        });

        for(const [id, card] of dom.cards){

            if(idsAtuais.has(id)) continue;

            card.remove();
            dom.cards.delete(id);
        }

        for(const [etapa, total] of Object.entries(contagens)){
            document.getElementById('total-' + etapa).textContent = total;
        }
    },

    criarCard(pedido){

        const pedidoId = utils.pedidoId(pedido);

        const data = pedido.created_at ?? pedido.data_criacao;

        const min = utils.minutos(data);

        const card = document.createElement('article');

        card.className = `card ${utils.tempoClass(min)}`;

        card.dataset.id = pedidoId;

        const titulo = utils.tituloPedido(pedido);

        const ts = new Date(data).getTime();

        card.innerHTML = `
            <div class="cozinha-card-cabecalho">
                <div>
                    <h3>${utils.escape(titulo)}</h3>
                    <span class="cozinha-card-numero">Pedido #${pedidoId}</span>
                </div>

                <span
                    class="tempo"
                    data-ts="${ts}"
                    data-min="${min}"
                >
                    ${min} min
                </span>
            </div>

            <div class="itens"></div>

            <div class="cozinha-card-acoes">
                <button class="cozinha-botao" type="button" data-action="imprimir">
                    Imprimir pedido
                </button>
                <button class="cozinha-botao cozinha-botao-principal" type="button" data-action="finalizar">
                    Finalizar pedido
                </button>
            </div>
        `;

        const itens = card.querySelector('.itens');

        this.syncItens(
            itens,
            pedido.itens || []
        );

        return card;
    },

    atualizarCard(card, pedido){

        const itens = card.querySelector('.itens');

        this.syncItens(
            itens,
            pedido.itens || []
        );
    },

    syncItens(container, itens){

        if(!container._items){
            container._items = new Map();
        }

        const mapa = container._items;

        const atuais = new Set();

        itens.forEach(item => {

            const itemId = utils.itemId(item);

            if(!itemId) return;

            const id = String(itemId);

            atuais.add(id);

            let el = mapa.get(id);

            if(!el){

                el = this.criarItem(item);

                mapa.set(id, el);

                container.appendChild(el);

                return;
            }

            this.atualizarItem(el, item);
        });

        for(const [id, el] of mapa){

            if(atuais.has(id)) continue;

            el.remove();

            mapa.delete(id);
        }
    },

    criarItem(item){

        const el = document.createElement('div');

        el.className = 'item';

        this.atualizarItem(el, item);

        return el;
    },

    atualizarItem(el, item){

        const quantidade = utils.qtd(item);
        const pronta = utils.qtdPronta(item);

        const finalizado = pronta >= quantidade;

        const hash = JSON.stringify([
            quantidade,
            pronta,
            finalizado,
            item.nome,
            item.imagem_url,
            item.obs ?? item.observacao,
            item.ponto,
            item.categoria,
            item.adicional,
            item.valor_adicional
        ]);

        if(el._hash === hash){
            return;
        }

        el._hash = hash;

        el.classList.toggle(
            'item-pronto',
            finalizado
        );

        const obs = item.obs ?? item.observacao ?? '';

        const qtdAdicionais = Math.max(0, Number(item.adicional || 0));

        const valorAdicional = Number(item.valor_adicional || 0);

        const adicional = qtdAdicionais > 0
            ? `<small><b>Adicional:</b> ${qtdAdicionais}x ${valorAdicional > 0 ? 'R$ ' + utils.dinheiro(valorAdicional) + ' cada' : ''}</small>`
            : '';

        const imagem = typeof item.imagem_url === 'string' && /^https:\/\//i.test(item.imagem_url)
            ? `<img class="item-foto" src="${utils.escape(item.imagem_url)}" alt="" width="40" height="40"
                   loading="lazy" referrerpolicy="no-referrer">`
            : '';

        el.innerHTML = `
            ${imagem}
            <div class="item-info">
                <span class="categoria">
                    ${utils.escape(item.categoria ?? '')}
                </span>

                <strong>${quantidade}× ${utils.escape(item.nome)}</strong>
                <span class="progresso">${finalizado ? 'Pronto' : `${pronta}/${quantidade} prontos`}</span>

                ${
                    obs
                        ? `<small class="cozinha-obs"><b>Obs:</b> ${utils.escape(obs)}</small>`
                        : ''
                }

                ${
                    item.ponto
                        ? `<small><b>Ponto:</b> ${utils.escape(item.ponto)}</small>`
                        : ''
                }

                ${adicional}
            </div>

            <div class="item-actions">
                <button
                    type="button"
                    class="btn-imprimir-item"
                    data-action="imprimir-item"
                    data-item-id="${utils.itemId(item)}"
                    title="Imprimir somente este item"
                    aria-label="Imprimir ${utils.escape(item.nome)}"
                >
                    <i class="bi bi-printer" aria-hidden="true" style="font-size: 1.25rem; line-height: 1"></i>
                </button>

                <label class="cozinha-item-pronto" ${finalizado ? 'hidden' : ''}>
                    <input
                        type="checkbox"
                        data-item-id="${utils.itemId(item)}"
                        ${finalizado ? 'checked disabled' : ''}
                        aria-label="Marcar mais uma unidade de ${utils.escape(item.nome)} como pronta"
                    >
                    <span aria-hidden="true">+1</span>
                </label>
            </div>
        `;
        el.querySelector('.item-foto')?.addEventListener('error', event => event.currentTarget.remove(), { once: true });
    }
};

/* =========================
   EVENTS
========================= */
document.getElementById('autoPrintItensNovos')
?.addEventListener('change', e => {

    const ativar = !!e.target.checked;

    if(ativar){
        garantirIframeAutoPrint();
        salvarPreferenciaAutoPrint(true);
        aplicarEstadoToggleAutoPrint();
        toast('Impressão automática ativada.', 'ok');
        return;
    }

    salvarPreferenciaAutoPrint(false);
    aplicarEstadoToggleAutoPrint();
    state.autoPrintFila = [];
    state.autoPrintEmExecucao = false;
    toast('Impressão automática desativada.', 'warn');
});

document.getElementById('filtro')
.addEventListener('change', e => {

    state.filtro = e.target.value
        ? Number(e.target.value)
        : null;

    render.pedidos();
});

document.getElementById('pedidos')
.addEventListener('click', async e => {

    const btnImprimirItem = e.target.closest('[data-action="imprimir-item"]');

    if(btnImprimirItem){

        const itemId = Number(btnImprimirItem.dataset.itemId || 0);

        if(!itemId) return;

        utils.vibrar();

        window.open(
            'imprimir_cozinha_item.php?item_id=' + itemId,
            '_blank'
        );

        return;
    }

    const btnImprimir = e.target.closest('[data-action="imprimir"]');

    if(btnImprimir){

        const card = btnImprimir.closest('.card');

        if(!card) return;

        const pedidoId = Number(card.dataset.id);

        if(!pedidoId) return;

        utils.vibrar();

        window.open(
            'imprimir_cozinha.php?pedido_id=' + pedidoId,
            '_blank'
        );

        return;
    }

    const btnFinalizar = e.target.closest('[data-action="finalizar"]');

    if(btnFinalizar){

        const card = btnFinalizar.closest('.card');

        if(!card || card.dataset.locked === '1') return;

        const pedidoId = Number(card.dataset.id);

        const pedido = state.pedidos.find(
            p => utils.pedidoId(p) === pedidoId
        );

        if(!pedido) return;

        card.dataset.locked = '1';
        card.classList.add('loading');

        try {

            for(const item of pedido.itens || []){

                let restante = utils.qtd(item) - utils.qtdPronta(item);

                while(restante > 0){

                    const resposta = await api.produzirItem(utils.itemId(item));

                    if(!resposta.ok || !resposta.item){
                        throw new Error('Resposta inválida');
                    }

                    item.quantidade_pronta = Number(resposta.item.quantidade_pronta ?? 0);
                    item.status = resposta.item.status;
                    restante--;
                }
            }

            toast('Pedido finalizado', 'ok');

            pedido.status = 'pronto';
            render.update();

            utils.vibrar(100);

        } catch(err){

            console.error(err);

            render.update();
            toast('Erro ao finalizar', 'erro');
        } finally {
            card.dataset.locked = '';
            card.classList.remove('loading');
            state.ultimoHash = '';
        }

        return;
    }
});

document.getElementById('pedidos')
.addEventListener('change', async e => {

    if(e.target.type !== 'checkbox') return;

    if(isLocked()){
        e.target.checked = false;
        return;
    }

    lock(700);

    const input = e.target;

    const itemId = Number(input.dataset.itemId);

    if(!itemId){
        input.checked = false;
        return;
    }

    const card = input.closest('.card');

    if(!card){
        input.checked = false;
        return;
    }

    const pedidoId = Number(card.dataset.id);

    const pedido = state.pedidos.find(
        p => utils.pedidoId(p) === pedidoId
    );

    const item = pedido?.itens?.find(
        i => utils.itemId(i) === itemId
    );

    if(!pedido || !item){
        input.checked = false;
        return;
    }

    input.disabled = true;

    try {

        const data = await api.produzirItem(itemId);

        if(!data.ok || !data.item){
            throw new Error('Resposta inválida');
        }

        item.quantidade_pronta = Number(
            data.item.quantidade_pronta ?? 0
        );

        item.qtd_pronta = item.quantidade_pronta;

        item.status = data.item.status;

        toast(`+1 ${item.nome}`, 'ok');

        const finalizado = utils.itemPronto(item);

        input.checked = finalizado;
        input.disabled = finalizado;

        const aindaPendente = (pedido.itens || [])
            .some(i => !utils.itemPronto(i));

        if(!aindaPendente){
            pedido.status = 'pronto';
            utils.vibrar(100);
        }

        render.update();

        state.ultimoHash = '';

    } catch(err){

        console.error(err);

        input.disabled = false;
        input.checked = false;

        toast('Erro ao marcar item', 'erro');
    }
});

/* =========================
   LOOP
========================= */
function atualizarConexao(estado, mensagem){
    const el = document.getElementById('conexao-cozinha');
    if(el.dataset.conexao !== estado){
        el.dataset.conexao = estado;
        el.textContent = mensagem;
    }
}

async function loop(){

    if(isLocked()){

        setTimeout(loop, intervalo);

        return;
    }

    try {

        const data = await api.listar();

        if(!data || data.ok !== true){
            throw new Error('Resposta inválida');
        }

        atualizarConexao('online', 'Ao vivo');
        intervalo = 3000;
        tentativasErro = 0;

        if(data.ultima){
            state.ultimoHash = data.ultima;
        }

        if(data.changed === false){

            setTimeout(loop, intervalo);

            return;
        }

        const novos = Array.isArray(data.pedidos)
            ? data.pedidos
            : [];

        /*
        |----------------------------------------------------------------------
        | A autoimpressão roda antes da renderização e observa somente:
        | - item que ainda não existia no snapshot anterior;
        | - acréscimo da quantidade do item.
        |
        | A impressão automática chama o cupom do item, não o pedido inteiro.
        | Finalização/status pronto não gera gatilho.
        |----------------------------------------------------------------------
        */
        detectarItensNovosParaImpressao(novos);

        const antigosMap = new Map(
            state.pedidos.map(p => [
                utils.pedidoId(p),
                p
            ])
        );

        const finais = novos
            .filter(p =>
                p.status !== 'pago' &&
                p.status !== 'cancelado'
            )
            .map(novo => {

                const pedidoId = utils.pedidoId(novo);

                const antigo = antigosMap.get(pedidoId);

                if(
                    state.iniciado &&
                    !antigo &&
                    !state.alertados.has(pedidoId)
                ){
                    state.alertados.add(pedidoId);
                    toast('Novo pedido!', 'warn');
                    utils.vibrar(120);
                }

                return {
                    ...(antigo || {}),
                    ...novo,
                    itens: novo.itens || []
                };
            });

        state.pedidos = finais;
        state.iniciado = true;

        render.update();

    } catch(err){

        console.error('Erro loop:', err);
        atualizarConexao('offline', 'Reconectando…');

        tentativasErro++;

        intervalo = Math.min(
            1000 * Math.pow(2, tentativasErro),
            60000
        );

        if(
            !window._erroToastAt ||
            Date.now() - window._erroToastAt > 5000
        ){
            window._erroToastAt = Date.now();
            toast('Erro de conexão', 'erro');
        }
    }

    setTimeout(loop, intervalo);
}

/* =========================
   INIT
========================= */
aplicarEstadoToggleAutoPrint();
render.update();
loop();
</script>

<?php require __DIR__ . '/includes/onboarding_tour.php'; ?>

</body>
</html>
