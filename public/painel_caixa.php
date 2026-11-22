<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require 'auth.php';
requireRole(['caixa','admin']);

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

<title>Comandaria Express - Caixa</title>

<?= ce_og_tags('Painel do Caixa - Comandaria Express', 'Painel do caixa para fechamento e acompanhamento de comandas') ?>
<link rel="icon" href="images/topo_comandaria_caixa.png" type="image/png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/financeiro.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/staff.css?v=2">
</head>

<body class="cd-staff finance-page finance-caixa">
<?php $staffPage = 'painel_caixa.php'; require __DIR__ . '/includes/staff_nav.php'; ?>

<main id="conteudo" class="finance-workspace">
<header class="finance-heading">
    <h1>Caixa</h1>
    <p>Confira as comandas, registre pagamentos e gerencie o caixa.</p>
</header>

<section id="caixaSessaoCard" class="caixa-sessao-card">
    <div class="caixa-sessao-topo">
        <h2 class="finance-section-title">Resumo do caixa</h2>
        <span id="caixaStatusBadge" class="caixa-status-badge fechado">● Caixa carregando</span>
    </div>

    <div id="caixaResumoGrid" class="caixa-resumo-grid"></div>

    <div class="caixa-acoes">
        <button type="button" class="btn btn-sm btn-success" data-caixa-action="abrir">Abrir caixa</button>
        <button type="button" class="btn btn-sm btn-danger" data-caixa-action="fechar">Fechar caixa</button>
        <button type="button" class="btn btn-sm btn-outline-dark" data-caixa-action="atualizar">Atualizar resumo</button>
        <button
            type="button"
            class="btn btn-sm btn-outline-dark"
            data-caixa-action="imprimir-fechamento"
            disabled
        >
            Imprimir fechamento
        </button>
    </div>

    <div id="caixaInfoOperacional" class="caixa-info-operacional">Carregando situação do caixa...</div>
</section>

<div class="caixa-workspace">
    <section class="card-box caixa-fila" aria-labelledby="caixaFilaTitulo">
        <div class="caixa-fila-topo">
            <h2 id="caixaFilaTitulo" class="finance-section-title">Contas em aberto</h2>
            <span id="caixaFilaContagem" class="caixa-fila-contagem">0</span>
        </div>
        <ul id="caixaContas" class="caixa-fila-lista"></ul>
    </section>
    <section id="pedidos" class="caixa-detalhes" aria-label="Detalhes da conta"></section>
</div>
</main>

<div id="pagamentoModal" class="pagamento-modal" aria-hidden="true">
    <div class="pagamento-backdrop" data-payment-close="1"></div>

    <div class="pagamento-card" role="dialog" aria-modal="true" aria-labelledby="pagamentoTitulo">
        <h2 id="pagamentoTitulo">Forma de pagamento</h2>
        <p>Escolha como a conta será registrada antes de confirmar o fechamento.</p>

        <div class="pagamento-resumo">
            <strong id="pagamentoAlvo">Conta</strong>
            <div id="pagamentoResumo">Selecione uma opção.</div>
        </div>

        <div class="pagamento-opcoes">
            <button type="button" class="pagamento-opcao" data-forma-pagamento="dinheiro">Dinheiro</button>
            <button type="button" class="pagamento-opcao" data-forma-pagamento="pix">Pix</button>
            <button type="button" class="pagamento-opcao" data-forma-pagamento="cartao">Cartão</button>
            <button type="button" class="pagamento-opcao" data-forma-pagamento="prazo">A prazo</button>
        </div>

        <button type="button" class="pagamento-cancelar" data-payment-close="1">
            Voltar sem fechar
        </button>
    </div>
</div>

<div id="antecipadoModal" class="pagamento-modal" aria-hidden="true">
    <div class="pagamento-backdrop" data-advance-close="1"></div>

    <div class="pagamento-card" role="dialog" aria-modal="true" aria-labelledby="antecipadoTitulo">
        <h2 id="antecipadoTitulo">Pagamento antecipado da mesa</h2>
        <p>Registre um valor já recebido para abater automaticamente na conta final.</p>

        <div class="pagamento-resumo">
            <strong id="antecipadoAlvo">Mesa</strong>
            <div id="antecipadoResumo">Informe o valor recebido.</div>
        </div>

        <div class="antecipado-form">
            <div>
                <label for="antecipadoValor">Valor antecipado</label>
                <input id="antecipadoValor" type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="0,00">
            </div>

            <div>
                <label for="antecipadoForma">Forma recebida</label>
                <select id="antecipadoForma">
                    <option value="dinheiro">Dinheiro</option>
                    <option value="pix">Pix</option>
                    <option value="cartao">Cartão</option>
                </select>
            </div>

            <button type="button" class="antecipado-confirmar" data-advance-confirm="1">
                Registrar pagamento antecipado
            </button>
        </div>

        <button type="button" class="pagamento-cancelar" data-advance-close="1">
            Voltar sem registrar
        </button>
    </div>
</div>

<div id="caixaAbrirModal" class="pagamento-modal" aria-hidden="true">
    <div class="pagamento-backdrop" data-caixa-open-close="1"></div>

    <div class="pagamento-card" role="dialog" aria-modal="true" aria-labelledby="caixaAbrirTitulo">
        <h2 id="caixaAbrirTitulo">Abrir caixa</h2>
        <p>Informe o saldo inicial disponível em dinheiro para iniciar a sessão.</p>

        <div class="caixa-form">
            <div>
                <label for="caixaSaldoInicial">Saldo inicial em dinheiro</label>
                <input id="caixaSaldoInicial" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0,00">
            </div>

            <div>
                <label for="caixaObsAbertura">Observação opcional</label>
                <textarea id="caixaObsAbertura" placeholder="Ex.: troco inicial conferido"></textarea>
            </div>

            <button type="button" class="caixa-confirmar" data-caixa-open-confirm="1">
                Confirmar abertura
            </button>
        </div>

        <button type="button" class="pagamento-cancelar" data-caixa-open-close="1">
            Voltar sem abrir
        </button>
    </div>
</div>

<div id="caixaFecharModal" class="pagamento-modal" aria-hidden="true">
    <div class="pagamento-backdrop" data-caixa-close-close="1"></div>

    <div class="pagamento-card" role="dialog" aria-modal="true" aria-labelledby="caixaFecharTitulo">
        <h2 id="caixaFecharTitulo">Fechar caixa</h2>
        <p>Confira o resumo e informe quanto há fisicamente em dinheiro.</p>

        <div id="caixaFechamentoResumo" class="caixa-fechamento-resumo"></div>

        <div class="caixa-form">
            <div>
                <label for="caixaSaldoFinal">Dinheiro contado no caixa</label>
                <input id="caixaSaldoFinal" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0,00">
            </div>

            <div>
                <label for="caixaObsFechamento">Observação opcional</label>
                <textarea id="caixaObsFechamento" placeholder="Ex.: diferença conferida com responsável"></textarea>
            </div>

            <button type="button" class="caixa-confirmar fechar" data-caixa-close-confirm="1">
                Confirmar fechamento
            </button>
        </div>

        <button type="button" class="pagamento-cancelar" data-caixa-close-close="1">
            Voltar sem fechar
        </button>
    </div>
</div>

<div id="toast" role="status" aria-live="polite"></div>

<script>
const state = {
    pedidos: [],
    pedidosCarregados: false,
    contaSelecionada: null,
    ultimoHash: '',
    running: false,
    intervalo: 4000,
    tentativasErro: 0,
    cards: new Map(),
    caixa: {
        carregado: false,
        aberto: false,
        caixa: null,
        resumo: null,
        atualizado_em: 0
    }
};

const utils = {
    money(v){
        return Number(v || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    escape(s){
        return String(s ?? '').replace(/[&<>"']/g, m => ({
            '&':'&amp;',
            '<':'&lt;',
            '>':'&gt;',
            '"':'&quot;',
            "'":'&#039;'
        })[m]);
    },

    hora(data){
        if(!data){
            return '--:--';
        }

        const d = new Date(data);

        if(isNaN(d.getTime())){
            return '--:--';
        }

        return d.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
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

    pedidoPronto(pedido){
        if(pedido.status === 'pronto'){
            return true;
        }

        if(pedido.status_producao === 'pronto'){
            return true;
        }

        const itens = pedido.itens || [];

        return itens.length > 0 && itens.every(i => this.itemPronto(i));
    },

    tituloPedido(pedido){
        if(pedido.tipo === 'mesa'){
            return `Mesa ${pedido.referencia ?? pedido.identificador ?? pedido.mesa}`;
        }

        return `Cliente ${pedido.referencia ?? pedido.identificador ?? pedido.cliente}`;
    },

    atendentePedido(pedido){
        return pedido.atendente_nome || pedido.atendente || 'Não informado';
    },


    dataHora(d){
        if(!d){
            return '-';
        }

        const data = new Date(String(d).replace(' ', 'T'));

        if(Number.isNaN(data.getTime())){
            return d;
        }

        return data.toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    origemPedido(pedido){
        return pedido.origem === 'cliente_qr'
            ? 'Cliente via QR Code'
            : 'Atendimento';
    },

    servico10Ativo(pedido){
        return Boolean(
            pedido.servico_10_ativo === true ||
            Number(pedido.servico_10_ativo || 0) === 1
        );
    },

    taxaServico(pedido){
        if(!this.servico10Ativo(pedido)){
            return 0;
        }

        const taxaInformada = Number(pedido.taxa_servico_10);

        if(Number.isFinite(taxaInformada) && taxaInformada >= 0){
            return taxaInformada;
        }

        return Number(pedido.total || 0) * 0.10;
    },

    totalComServico(pedido){
        const totalInformado = Number(pedido.total_com_servico ?? pedido.total_cobravel);

        if(Number.isFinite(totalInformado)){
            return Math.max(0, totalInformado);
        }

        return Number(pedido.total || 0) + this.taxaServico(pedido);
    },

    totalAntecipado(pedido){
        return Math.max(0, Number(pedido.total_antecipado || 0));
    },

    saldoConta(pedido){
        const saldoInformado = Number(pedido.saldo);

        if(Number.isFinite(saldoInformado)){
            return Math.max(0, saldoInformado);
        }

        return Math.max(0, this.totalComServico(pedido) - this.totalAntecipado(pedido));
    },

    subtotalItem(item){
        const qtd = this.qtd(item);

        const preco = Number(
            item.preco_unitario ??
            item.preco ??
            0
        );

        const qtdAdicionais = Math.max(0, Number(item.adicional || 0));

        const adicional = qtdAdicionais > 0
            ? qtdAdicionais * Number(item.valor_adicional || 0)
            : 0;

        return qtd * (preco + adicional);
    },

    vibrar(ms = 80){
        navigator.vibrate?.(ms);
    }
};

function exibirModalCaixa(modal, controleInicial){
    modal._focoAnterior = document.activeElement;
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    controleInicial?.focus();
}

function ocultarModalCaixa(modal){
    if(!modal){
        return;
    }

    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');

    const focoAnterior = modal._focoAnterior;

    if(focoAnterior?.isConnected && !focoAnterior.disabled){
        focoAnterior.focus();
    } else {
        document.querySelector('[data-conta][aria-pressed="true"]')?.focus();
    }

    modal._focoAnterior = null;
}

const caixaManager = {
    aberto(){
        return Boolean(state.caixa?.aberto);
    },

    resumo(){
        return state.caixa?.resumo || {
            saldo_inicial: 0,
            dinheiro: 0,
            pix: 0,
            cartao: 0,
            prazo: 0,
            total_recebido: 0,
            total_antecipado_recebido: 0,
            total_pagamento_pedidos: 0,
            total_vendas_prazo: 0,
            saldo_esperado_dinheiro: 0,
            quantidade_movimentos: 0
        };
    },

    async carregar(silencioso = true){
        try {
            const data = await api.statusCaixa();

            state.caixa = {
                carregado: true,
                aberto: Boolean(data.aberto),
                caixa: data.caixa || null,
                resumo: data.resumo || null,
                atualizado_em: Date.now()
            };

            render.caixa();

        } catch(err){
            console.error(err);

            state.caixa = {
                carregado: true,
                aberto: false,
                caixa: null,
                resumo: null,
                atualizado_em: Date.now(),
                erro: err.message || 'Erro ao carregar caixa'
            };

            render.caixa();

            if(!silencioso){
                toast(err.message || 'Erro ao carregar situação do caixa', 'erro');
            }
        }
    },

    abrirModal(){
        if(this.aberto()){
            toast('Já existe um caixa aberto', 'warn');
            return;
        }

        const modal = document.getElementById('caixaAbrirModal');
        const saldo = document.getElementById('caixaSaldoInicial');
        const obs = document.getElementById('caixaObsAbertura');

        if(!modal || !saldo || !obs){
            return;
        }

        saldo.value = '0.00';
        obs.value = '';
        exibirModalCaixa(modal, saldo);
    },

    fecharModal(){
        if(!this.aberto()){
            toast('Não há caixa aberto para fechar', 'warn');
            return;
        }

        const modal = document.getElementById('caixaFecharModal');
        const resumoEl = document.getElementById('caixaFechamentoResumo');
        const saldoFinal = document.getElementById('caixaSaldoFinal');
        const obs = document.getElementById('caixaObsFechamento');

        if(!modal || !resumoEl || !saldoFinal || !obs){
            return;
        }

        const resumo = this.resumo();
        const caixa = state.caixa.caixa || {};

        resumoEl.innerHTML = `
            <strong>Resumo do caixa #${Number(caixa.id || 0)}</strong>
            Abertura: ${utils.escape(utils.dataHora(caixa.aberto_em))}<br>
            Saldo inicial: R$ ${utils.money(resumo.saldo_inicial)}<br>
            Dinheiro recebido: R$ ${utils.money(resumo.dinheiro)}<br>
            Pix: R$ ${utils.money(resumo.pix)} • Cartão: R$ ${utils.money(resumo.cartao)}<br>
            Vendas a prazo: R$ ${utils.money(resumo.prazo)}<br>
            Esperado em dinheiro: <strong>R$ ${utils.money(resumo.saldo_esperado_dinheiro)}</strong>
        `;

        saldoFinal.value = Number(resumo.saldo_esperado_dinheiro || 0).toFixed(2);
        obs.value = '';
        exibirModalCaixa(modal, saldoFinal);
    },

    fecharAbrirModal(){
        const modal = document.getElementById('caixaAbrirModal');
        ocultarModalCaixa(modal);
    },

    fecharFecharModal(){
        const modal = document.getElementById('caixaFecharModal');
        ocultarModalCaixa(modal);
    },

    async confirmarAbertura(){
        const saldoInicial = Number(document.getElementById('caixaSaldoInicial')?.value || 0);
        const observacao = document.getElementById('caixaObsAbertura')?.value || '';

        if(saldoInicial < 0){
            toast('O saldo inicial não pode ser negativo', 'warn');
            return;
        }

        try {
            const data = await api.abrirCaixa(saldoInicial, observacao);

            state.caixa = {
                carregado: true,
                aberto: true,
                caixa: data.caixa || null,
                resumo: data.resumo || null,
                atualizado_em: Date.now()
            };

            this.fecharAbrirModal();
            render.caixa();
            toast('Caixa aberto com sucesso', 'ok');

        } catch(err){
            console.error(err);
            toast(err.message || 'Erro ao abrir caixa', 'erro');
        }
    },

    async confirmarFechamento(){
        const saldoFinal = Number(document.getElementById('caixaSaldoFinal')?.value || 0);
        const observacao = document.getElementById('caixaObsFechamento')?.value || '';

        if(saldoFinal < 0){
            toast('O dinheiro contado não pode ser negativo', 'warn');
            return;
        }

        if(!confirm('Confirmar fechamento do caixa?\n\nApós fechar, novos recebimentos exigirão nova abertura.')){
            return;
        }

        try {
            const data = await api.fecharCaixa(saldoFinal, observacao);
            const diferenca = Number(data.diferenca_dinheiro || 0);

            state.caixa = {
                carregado: true,
                aberto: false,
                caixa: data.caixa || null,
                resumo: data.resumo || null,
                atualizado_em: Date.now()
            };

            this.fecharFecharModal();
            render.caixa();

            const textoDiferenca = diferenca === 0
                ? 'sem diferença em dinheiro'
                : `diferença de R$ ${utils.money(diferenca)}`;

            toast(`Caixa fechado: ${textoDiferenca}`, diferenca === 0 ? 'ok' : 'warn');

        } catch(err){
            console.error(err);
            toast(err.message || 'Erro ao fechar caixa', 'erro');
        }
    }
};

const pagamento = {
    labels: {
        dinheiro: 'Dinheiro',
        pix: 'Pix',
        cartao: 'Cartão',
        prazo: 'A prazo',
        antecipado: 'Antecipado'
    },

    resolver: null,

    label(forma){
        return this.labels[forma] || 'Não informado';
    },

    abrir(pedido){
        const modal = document.getElementById('pagamentoModal');
        const alvo = document.getElementById('pagamentoAlvo');
        const resumo = document.getElementById('pagamentoResumo');

        if(!modal || !alvo || !resumo){
            return Promise.resolve(null);
        }

        alvo.textContent = utils.tituloPedido(pedido);
        const textoServico = utils.servico10Ativo(pedido)
            ? `10% serviço: R$ ${utils.money(utils.taxaServico(pedido))}`
            : '10% serviço: não cobrado';

        resumo.textContent = `Total base: R$ ${utils.money(pedido.total)} • ${textoServico} • Total final: R$ ${utils.money(utils.totalComServico(pedido))} • Antecipado: R$ ${utils.money(utils.totalAntecipado(pedido))} • Saldo: R$ ${utils.money(utils.saldoConta(pedido))}`;

        exibirModalCaixa(modal, modal.querySelector('[data-forma-pagamento]'));

        return new Promise(resolve => {
            this.resolver = resolve;
        });
    },

    fechar(forma = null){
        const modal = document.getElementById('pagamentoModal');

        ocultarModalCaixa(modal);

        if(typeof this.resolver === 'function'){
            this.resolver(forma);
        }

        this.resolver = null;
    }
};

const antecipado = {
    resolver: null,

    abrir(pedido){
        const modal = document.getElementById('antecipadoModal');
        const alvo = document.getElementById('antecipadoAlvo');
        const resumo = document.getElementById('antecipadoResumo');
        const valor = document.getElementById('antecipadoValor');
        const forma = document.getElementById('antecipadoForma');

        if(!modal || !alvo || !resumo || !valor || !forma){
            return Promise.resolve(null);
        }

        const saldo = utils.saldoConta(pedido);

        alvo.textContent = utils.tituloPedido(pedido);
        resumo.textContent = `Total: R$ ${utils.money(pedido.total)} • Já antecipado: R$ ${utils.money(utils.totalAntecipado(pedido))} • Limite agora: R$ ${utils.money(saldo)}`;

        valor.value = '';
        valor.max = saldo.toFixed(2);
        forma.value = 'dinheiro';

        exibirModalCaixa(modal, valor);

        return new Promise(resolve => {
            this.resolver = resolve;
        });
    },

    fechar(dados = null){
        const modal = document.getElementById('antecipadoModal');

        ocultarModalCaixa(modal);

        if(typeof this.resolver === 'function'){
            this.resolver(dados);
        }

        this.resolver = null;
    },

    confirmar(){
        const valor = Number(document.getElementById('antecipadoValor')?.value || 0);
        const forma = document.getElementById('antecipadoForma')?.value || '';

        if(!valor || valor <= 0){
            toast('Informe um valor antecipado maior que zero', 'warn');
            return;
        }

        if(!['dinheiro', 'pix', 'cartao'].includes(forma)){
            toast('Forma de pagamento antecipado inválida', 'warn');
            return;
        }

        this.fechar({ valor, forma_pagamento: forma });
    }
};

function toast(msg, tipo = 'ok'){

    const el = document.getElementById('toast');

    if(!el){
        return;
    }

    el.className = '';

    el.classList.add(
        'show',
        ['ok','erro','warn'].includes(tipo) ? tipo : 'ok'
    );

    el.textContent = msg;

    clearTimeout(el._timer);

    el._timer = setTimeout(() => {
        el.classList.remove('show');
    }, 1600);
}

function confirmarConta(acao, pedido, pedidoId, formaPagamento = null){

    const alvo = utils.tituloPedido(pedido);
    const total = utils.money(pedido.total);
    const antecipadoValor = utils.money(utils.totalAntecipado(pedido));
    const saldo = utils.money(utils.saldoConta(pedido));
    const taxaServico = utils.money(utils.taxaServico(pedido));
    const totalComServico = utils.money(utils.totalComServico(pedido));
    const statusServico = utils.servico10Ativo(pedido)
        ? `R$ ${taxaServico}`
        : 'Não cobrado';
    const atendente = utils.atendentePedido(pedido);

    const titulo = acao === 'cancelar'
        ? 'Cancelar a conta?'
        : 'Fechar a conta?';

    const linhaPagamento = acao === 'cancelar'
        ? ''
        : `Forma de pagamento: ${pagamento.label(formaPagamento)}\n`;

    return confirm(
        titulo + "\n\n" +
        alvo + "\n" +
        `Atendente: ${atendente}\n` +
        `Pedido #${pedidoId}\n` +
        linhaPagamento +
        `Total: R$ ${total}\n` +
        `Antecipado: R$ ${antecipadoValor}\n` +
        `Saldo da conta: R$ ${saldo}\n` +
        `10% serviço: ${statusServico}\n` +
        `Total final: R$ ${totalComServico}`
    );
}

function confirmarExclusaoItem(pedido, pedidoId, item){

    const alvo = utils.tituloPedido(pedido);
    const quantidade = utils.qtd(item);
    const nome = item.nome || 'Item';
    const subtotal = utils.money(utils.subtotalItem(item));

    return confirm(
        'Excluir este item da conta?\n\n' +
        alvo + '\n' +
        `Pedido #${pedidoId}\n` +
        `${quantidade}x ${nome}\n` +
        `Valor retirado: R$ ${subtotal}\n\n` +
        'A conta será recalculada sem cancelar os demais itens.'
    );
}

const api = {
    async listar(){

        let url = 'listar_pedidos.php';

        if(state.ultimoHash){
            url += '?last=' + encodeURIComponent(state.ultimoHash);
        }

        const res = await fetch(url, {
            cache: 'no-store'
        });

        const data = await res.json();

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao listar conta');
        }

        return data;
    },

    async atualizarStatus(pedidoId, acao, formaPagamento = null, ativoServico10 = null){

        const payload = {
            pedido_id: pedidoId,
            acao: acao
        };

        if(acao === 'pago' && formaPagamento){
            payload.forma_pagamento = formaPagamento;
        }

        if(acao === 'alternar_servico_10'){
            payload.ativo = Boolean(ativoServico10);
        }

        const res = await fetch('atualizar_status_pedido.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao atualizar conta');
        }

        return data;
    },

    async registrarAntecipado(pedidoId, valor, formaPagamento){
        const res = await fetch('registrar_pagamento_antecipado.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                pedido_id: pedidoId,
                valor: valor,
                forma_pagamento: formaPagamento
            })
        });

        const data = await res.json();

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao registrar pagamento antecipado');
        }

        return data;
    },

    async statusCaixa(){
        const res = await fetch('caixa_status.php', {
            cache: 'no-store'
        });

        const data = await res.json();

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao carregar caixa');
        }

        return data;
    },

    async abrirCaixa(saldoInicial, observacao){
        const res = await fetch('abrir_caixa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                saldo_inicial: saldoInicial,
                observacao: observacao
            })
        });

        const data = await res.json();

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao abrir caixa');
        }

        return data;
    },

    async fecharCaixa(saldoFinalInformado, observacao){
        const res = await fetch('fechar_caixa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                saldo_final_informado: saldoFinalInformado,
                observacao: observacao
            })
        });

        const data = await res.json();

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao fechar caixa');
        }

        return data;
    },

    async excluirItemConta(itemId){
        const res = await fetch('excluir_item_conta.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                item_id: itemId
            })
        });

        const data = await res.json();

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao excluir item da conta');
        }

        return data;
    }
};

function statusCard(pedido){

    if(pedido.status === 'pago'){
        return 'pago';
    }

    if(pedido.status === 'cancelado'){
        return 'cancelado';
    }

    if(utils.pedidoPronto(pedido)){
        return 'pronto';
    }

    return 'pendente';
}

function badgeStatus(pedido){

    const pronto = utils.pedidoPronto(pedido);

    if(pedido.status === 'pago'){
        return '<span class="badge bg-primary badge-status">pago</span>';
    }

    if(pedido.status === 'cancelado'){
        return '<span class="badge bg-danger badge-status">cancelado</span>';
    }

    if(pronto){
        return '<span class="badge bg-success badge-status">pronto</span>';
    }

    return '<span class="badge bg-warning text-dark badge-status">pendente</span>';
}

const render = {
    update(){
        this.caixa();
        this.contas();
        this.pedidos();
    },

    caixa(){
        const badge = document.getElementById('caixaStatusBadge');
        const grid = document.getElementById('caixaResumoGrid');
        const info = document.getElementById('caixaInfoOperacional');
        const botaoAbrir = document.querySelector('[data-caixa-action="abrir"]');
        const botaoFechar = document.querySelector('[data-caixa-action="fechar"]');
        const botaoImprimir = document.querySelector('[data-caixa-action="imprimir-fechamento"]');

        if(!badge || !grid || !info){
            return;
        }

        const carregado = Boolean(state.caixa?.carregado);
        const aberto = Boolean(state.caixa?.aberto);
        const caixa = state.caixa?.caixa || null;
        const resumo = caixaManager.resumo();

        if(!carregado){
            badge.className = 'caixa-status-badge fechado';
            badge.textContent = '● Caixa carregando';
            grid.innerHTML = '';
            info.textContent = 'Carregando situação do caixa...';

            if(botaoImprimir){
                botaoImprimir.disabled = true;
                botaoImprimir.title = 'Carregue o caixa para verificar o último fechamento';
            }

            return;
        }

        badge.className = `caixa-status-badge ${aberto ? 'aberto' : 'fechado'}`;
        badge.textContent = aberto ? '● Caixa aberto' : '● Caixa fechado';

        grid.innerHTML = `
            <div class="caixa-resumo-item">
                <span>Saldo inicial</span>
                <strong>R$ ${utils.money(resumo.saldo_inicial)}</strong>
            </div>
            <div class="caixa-resumo-item">
                <span>Dinheiro</span>
                <strong>R$ ${utils.money(resumo.dinheiro)}</strong>
            </div>
            <div class="caixa-resumo-item">
                <span>Pix / Cartão</span>
                <strong>R$ ${utils.money(Number(resumo.pix || 0) + Number(resumo.cartao || 0))}</strong>
            </div>
            <div class="caixa-resumo-item">
                <span>Esperado em dinheiro</span>
                <strong>R$ ${utils.money(resumo.saldo_esperado_dinheiro)}</strong>
            </div>
        `;

        if(aberto && caixa){
            info.innerHTML = `
                Caixa #${Number(caixa.id || 0)} aberto em ${utils.escape(utils.dataHora(caixa.aberto_em))}.
                Recebido antecipado: <strong>R$ ${utils.money(resumo.total_antecipado_recebido)}</strong>.
                Vendas a prazo registradas: <strong>R$ ${utils.money(resumo.total_vendas_prazo)}</strong>.
            `;
        } else if(state.caixa?.erro){
            info.textContent = state.caixa.erro;
        } else {
            info.textContent = 'Abra o caixa para registrar pagamentos finais e antecipados.';
        }

        if(botaoAbrir){
            botaoAbrir.disabled = aberto;
        }

        if(botaoFechar){
            botaoFechar.disabled = !aberto;
        }

        if(botaoImprimir){
            botaoImprimir.disabled = aberto;
            botaoImprimir.title = aberto
                ? 'Feche o caixa para imprimir o fechamento'
                : 'Imprimir último fechamento de caixa';
        }
    },

    contas(){
        const fila = document.getElementById('caixaContas');
        const contas = state.pedidos
            .filter(pedido => !['pago', 'cancelado'].includes(pedido.status) && utils.pedidoId(pedido))
            .sort((a, b) =>
                Number(utils.pedidoPronto(b)) - Number(utils.pedidoPronto(a)) ||
                new Date(b.created_at ?? 0).getTime() - new Date(a.created_at ?? 0).getTime()
            );

        if(!contas.some(pedido => utils.pedidoId(pedido) === state.contaSelecionada)){
            state.contaSelecionada = contas.length ? utils.pedidoId(contas[0]) : null;
        }

        const contagem = document.getElementById('caixaFilaContagem');
        contagem.textContent = state.pedidosCarregados ? String(contas.length) : '–';

        const html = contas.map(pedido => {
            const id = utils.pedidoId(pedido);
            const selecionado = id === state.contaSelecionada;

            return `
                <li>
                    <button class="caixa-fila-conta" type="button" data-conta="${id}"
                        aria-controls="pedidos" aria-pressed="${selecionado}">
                        <span class="caixa-fila-origem">
                            <strong>${utils.escape(utils.tituloPedido(pedido))}</strong>
                            <small>#${id} · ${utils.hora(pedido.created_at)}</small>
                        </span>
                        <span class="caixa-fila-valores">
                            <strong>R$ ${utils.money(utils.saldoConta(pedido))}</strong>
                            ${badgeStatus(pedido)}
                        </span>
                    </button>
                </li>
            `;
        }).join('');

        if(fila._html !== html){
            const foco = document.activeElement?.dataset.conta;
            fila.innerHTML = html;
            fila._html = html;

            if(foco){
                fila.querySelector(`[data-conta="${Number(foco)}"]`)?.focus({ preventScroll: true });
            }
        }
    },

    pedidos(){
        const container = document.getElementById('pedidos');
        const lista = state.pedidos.filter(pedido =>
            utils.pedidoId(pedido) === state.contaSelecionada &&
            !['pago', 'cancelado'].includes(pedido.status)
        );

        if(!lista.length){
            const titulo = state.pedidosCarregados ? 'Nenhuma conta em aberto' : 'Carregando contas';
            const descricao = state.pedidosCarregados
                ? 'As novas comandas aparecem aqui assim que forem enviadas.'
                : 'Aguardando a consulta ao caixa.';

            if(container.querySelector('.caixa-vazio strong')?.textContent !== titulo){
                container.innerHTML = `
                    <div class="alerta-vazio caixa-vazio" role="status">
                        <strong>${titulo}</strong>
                        <p>${descricao}</p>
                    </div>
                `;
            }

            state.cards.clear();

            return;
        }

        const vazio = container.querySelector('.caixa-vazio');

        if(vazio){
            vazio.remove();
        }

        const idsAtuais = new Set();

        lista.forEach(pedido => {
            const pedidoId = utils.pedidoId(pedido);

            if(!pedidoId){
                return;
            }

            idsAtuais.add(String(pedidoId));

            const hash = JSON.stringify({
                status: pedido.status,
                status_producao: pedido.status_producao,
                subtotal: pedido.subtotal,
                total_adicional: pedido.total_adicional,
                total: pedido.total,
                servico_10_ativo: pedido.servico_10_ativo,
                taxa_servico_10: pedido.taxa_servico_10,
                total_com_servico: pedido.total_com_servico,
                total_cobravel: pedido.total_cobravel,
                total_antecipado: pedido.total_antecipado,
                saldo: pedido.saldo,
                titulo: utils.tituloPedido(pedido),
                origem: pedido.origem,
                created_at: pedido.created_at,
                atendente: utils.atendentePedido(pedido),
                itens: (pedido.itens || []).map(i => [
                    utils.itemId(i),
                    utils.qtd(i),
                    utils.qtdPronta(i),
                    i.nome,
                    i.observacao ?? i.obs,
                    i.ponto,
                    i.adicional,
                    i.valor_adicional,
                    i.subtotal
                ])
            });

            let card = state.cards.get(String(pedidoId));

            if(!card){
                card = this.criarCard(pedido);
                card._hash = hash;

                state.cards.set(String(pedidoId), card);
                container.appendChild(card);

                return;
            }

            if(card._hash !== hash){
                const novo = this.criarCard(pedido);
                novo._hash = hash;

                card.replaceWith(novo);

                state.cards.set(String(pedidoId), novo);
            }
        });

        for(const [id, card] of state.cards){
            if(idsAtuais.has(id)){
                continue;
            }

            card.remove();
            state.cards.delete(id);
        }
    },

    criarCard(pedido){
        const pedidoId = utils.pedidoId(pedido);

        const card = document.createElement('article');

        const classeStatus = statusCard(pedido);

        card.className = `card-caixa ${classeStatus}`;
        card.dataset.id = pedidoId;

        const itensHtml = (pedido.itens || []).map(item => {
            const itemId = utils.itemId(item);
            const qtd = utils.qtd(item);
            const subtotal = utils.subtotalItem(item);
            const prontoItem = utils.itemPronto(item);

            const detalhes = [];

            const qtdAdicionais = Math.max(0, Number(item.adicional || 0));

            if(qtdAdicionais > 0){
                detalhes.push(
                    `${qtdAdicionais} adicional(is) + R$ ${utils.money(item.valor_adicional)} cada`
                );
            }

            const obs = item.obs ?? item.observacao ?? '';

            if(obs){
                detalhes.push(`Obs: ${obs}`);
            }

            if(item.ponto){
                detalhes.push(`Ponto: ${item.ponto}`);
            }

            if(prontoItem){
                detalhes.push('Pronto');
            } else {
                detalhes.push(`Preparo ${utils.qtdPronta(item)}/${utils.qtd(item)}`);
            }

            return `
                <div class="item ${prontoItem ? 'item-pronto' : ''}">
                    <div class="item-main">
                        <div>
                            <div class="item-nome">
                                ${qtd}x ${utils.escape(item.nome)}
                            </div>

                            <div class="item-detalhe">
                                ${utils.escape(item.categoria || '')}
                            </div>
                        </div>

                        <div class="item-total">
                            R$ ${utils.money(subtotal)}
                        </div>
                    </div>

                    ${
                        detalhes.length
                        ? `
                            <div class="item-detalhe">
                                ${utils.escape(detalhes.join(' • '))}
                            </div>
                        `
                        : ''
                    }

                    ${itemId > 0 ? `
                        <div class="item-acoes">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                data-action="remover-item"
                                data-item-id="${itemId}"
                                title="Excluir este item da conta sem cancelar a conta inteira"
                            >
                                Excluir item
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');

        const pronto = utils.pedidoPronto(pedido);

        card.innerHTML = `
            <div class="card-header-caixa">
                <div class="header-top">
                    <div>
                        <h2 class="titulo">
                            ${utils.escape(utils.tituloPedido(pedido))}
                        </h2>

                        <div class="numero">
                            Pedido #${pedidoId}
                        </div>

                        <div class="numero">
                            Atendente: ${utils.escape(utils.atendentePedido(pedido))}
                        </div>

                        <div class="numero">
                            Origem: ${utils.escape(utils.origemPedido(pedido))}
                        </div>
                    </div>

                    <div class="text-end">
                        ${badgeStatus(pedido)}

                        <div class="hora">
                            ${utils.hora(pedido.created_at)}
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body-caixa">
                <div class="caixa-itens">
                    <h3>Itens da comanda</h3>
                    ${
                        itensHtml ||
                        '<p class="text-muted mb-0">Sem itens.</p>'
                    }
                </div>

                <div class="caixa-totais">
                    <div class="total-box">
                        <h3>Resumo da conta</h3>
                        <div class="total-linha">
                            <span>Subtotal</span>
                            <strong>R$ ${utils.money(pedido.subtotal)}</strong>
                        </div>

                        <div class="total-linha">
                            <span>Adicionais</span>
                            <strong>R$ ${utils.money(pedido.total_adicional)}</strong>
                        </div>

                        <div class="total-linha">
                            <span>Total dos itens</span>
                            <strong>R$ ${utils.money(pedido.total)}</strong>
                        </div>

                        <div class="total-linha">
                            <span>10% serviço</span>
                            <strong>${utils.servico10Ativo(pedido) ? `R$ ${utils.money(utils.taxaServico(pedido))}` : 'Não cobrado'}</strong>
                        </div>

                        <div class="total-linha">
                            <span>Total da conta</span>
                            <strong>R$ ${utils.money(utils.totalComServico(pedido))}</strong>
                        </div>

                        <div class="total-linha total-credito">
                            <span>Antecipado</span>
                            <strong>R$ ${utils.money(utils.totalAntecipado(pedido))}</strong>
                        </div>

                        <div class="total-final">
                            <span>Saldo a cobrar</span>
                            <strong>R$ ${utils.money(utils.saldoConta(pedido))}</strong>
                        </div>
                    </div>

                    <button
                        class="btn btn-success w-100 mt-3 btn-fechar-conta-principal"
                        data-action="pagar"
                        ${pronto ? '' : 'disabled'}
                        title="${pronto ? 'Fechar conta' : 'Aguardando cozinha finalizar'}"
                    >
                        Fechar conta
                    </button>

                    ${pronto ? '' : '<p class="caixa-pagamento-pendente">Disponível quando a cozinha finalizar o pedido.</p>'}

                    <button
                        class="btn btn-sm ${utils.servico10Ativo(pedido) ? 'btn-outline-danger' : 'btn-outline-success'} w-100 mt-2"
                        data-action="alternar-servico-10"
                    >
                        ${utils.servico10Ativo(pedido) ? 'Remover taxa de serviço' : 'Adicionar taxa de serviço'}
                    </button>

                    <button
                        class="btn btn-sm btn-outline-dark w-100 mt-2"
                        data-action="imprimir"
                    >
                        Imprimir comanda
                    </button>

                    ${pedido.tipo === 'mesa' ? `
                        <button
                            class="btn btn-sm btn-outline-success w-100 mt-2"
                            data-action="antecipar"
                            ${utils.saldoConta(pedido) > 0 ? '' : 'disabled'}
                            title="${utils.saldoConta(pedido) > 0 ? 'Registrar pagamento antecipado' : 'Mesa já coberta por antecipações'}"
                        >
                            Pagamento antecipado
                        </button>
                    ` : ''}

                    <button
                        class="btn btn-sm btn-outline-danger w-100 mt-2"
                        data-action="cancelar"
                    >
                        Cancelar conta
                    </button>
                </div>
            </div>
        `;

        return card;
    }
};

document.getElementById('caixaSessaoCard')?.addEventListener('click', async e => {
    const action = e.target.closest('[data-caixa-action]')?.dataset.caixaAction;

    if(!action){
        return;
    }

    if(action === 'abrir'){
        caixaManager.abrirModal();
        return;
    }

    if(action === 'fechar'){
        await caixaManager.carregar(false);
        caixaManager.fecharModal();
        return;
    }

    if(action === 'atualizar'){
        await caixaManager.carregar(false);
        toast('Resumo do caixa atualizado', 'ok');
        return;
    }

    if(action === 'imprimir-fechamento'){
        utils.vibrar();

        window.open(
            'imprimir_fechamento_caixa.php?ultimo=1',
            '_blank'
        );

        return;
    }
});

document.getElementById('caixaAbrirModal')?.addEventListener('click', e => {
    if(e.target.closest('[data-caixa-open-confirm="1"]')){
        caixaManager.confirmarAbertura();
        return;
    }

    if(e.target.closest('[data-caixa-open-close="1"]')){
        caixaManager.fecharAbrirModal();
    }
});

document.getElementById('caixaFecharModal')?.addEventListener('click', e => {
    if(e.target.closest('[data-caixa-close-confirm="1"]')){
        caixaManager.confirmarFechamento();
        return;
    }

    if(e.target.closest('[data-caixa-close-close="1"]')){
        caixaManager.fecharFecharModal();
    }
});

document.getElementById('pagamentoModal')?.addEventListener('click', e => {
    const forma = e.target.closest('[data-forma-pagamento]')?.dataset.formaPagamento;

    if(forma){
        pagamento.fechar(forma);
        return;
    }

    if(e.target.closest('[data-payment-close="1"]')){
        pagamento.fechar(null);
    }
});

document.getElementById('antecipadoModal')?.addEventListener('click', e => {
    if(e.target.closest('[data-advance-confirm="1"]')){
        antecipado.confirmar();
        return;
    }

    if(e.target.closest('[data-advance-close="1"]')){
        antecipado.fechar(null);
    }
});

document.addEventListener('keydown', e => {
    if(e.key === 'Tab'){
        const modal = document.querySelector('.pagamento-modal.show');
        const controls = modal?.querySelectorAll('button:not([disabled]), input, textarea, select');

        if(controls?.length){
            const first = controls[0];
            const last = controls[controls.length - 1];

            if(!modal.contains(document.activeElement) || (e.shiftKey && document.activeElement === first) || (!e.shiftKey && document.activeElement === last)){
                e.preventDefault();
                (e.shiftKey ? last : first).focus();
            }
        }
    }

    if(e.key === 'Escape'){
        const modal = document.getElementById('pagamentoModal');

        if(modal?.classList.contains('show')){
            pagamento.fechar(null);
        }

        const modalAntecipado = document.getElementById('antecipadoModal');

        if(modalAntecipado?.classList.contains('show')){
            antecipado.fechar(null);
        }

        const modalAbrirCaixa = document.getElementById('caixaAbrirModal');

        if(modalAbrirCaixa?.classList.contains('show')){
            caixaManager.fecharAbrirModal();
        }

        const modalFecharCaixa = document.getElementById('caixaFecharModal');

        if(modalFecharCaixa?.classList.contains('show')){
            caixaManager.fecharFecharModal();
        }
    }
});

document.getElementById('caixaContas').addEventListener('click', e => {
    const conta = e.target.closest('[data-conta]');

    if(!conta){
        return;
    }

    state.contaSelecionada = Number(conta.dataset.conta);
    render.contas();
    render.pedidos();
});

document.getElementById('pedidos').addEventListener('click', async e => {
    const btn = e.target.closest('button');

    if(!btn){
        return;
    }

    const card = btn.closest('.card-caixa');

    if(!card){
        return;
    }

    const pedidoId = Number(card.dataset.id);

    if(!pedidoId){
        return;
    }

    const pedido = state.pedidos.find(
        p => utils.pedidoId(p) === pedidoId
    );

    if(!pedido){
        toast('Pedido não encontrado', 'erro');
        return;
    }

    const action = btn.dataset.action;

    if(action === 'remover-item'){
        const itemId = Number(btn.dataset.itemId || 0);

        if(!itemId){
            toast('Item inválido', 'erro');
            return;
        }

        const item = (pedido.itens || []).find(
            i => utils.itemId(i) === itemId
        );

        if(!item){
            toast('Item não encontrado na conta', 'erro');
            return;
        }

        if(!confirmarExclusaoItem(pedido, pedidoId, item)){
            return;
        }

        if(card.dataset.lock === '1'){
            return;
        }

        card.dataset.lock = '1';
        card.classList.add('loading');

        try {
            const resp = await api.excluirItemConta(itemId);
            const totais = resp.totais || {};
            const financeiro = resp.financeiro || {};

            pedido.itens = (pedido.itens || []).filter(
                i => utils.itemId(i) !== itemId
            );

            pedido.subtotal = Number(totais.subtotal ?? pedido.subtotal ?? 0);
            pedido.total_adicional = Number(totais.total_adicional ?? pedido.total_adicional ?? 0);
            pedido.total = Number(totais.total ?? pedido.total ?? 0);
            pedido.servico_10_ativo = Boolean(financeiro.servico_10_ativo ?? totais.servico_10_ativo ?? pedido.servico_10_ativo ?? false);
            pedido.taxa_servico_10 = Number(financeiro.taxa_servico_10 ?? totais.taxa_servico_10 ?? pedido.taxa_servico_10 ?? 0);
            pedido.total_com_servico = Number(financeiro.total_com_servico ?? totais.total_com_servico ?? pedido.total_com_servico ?? (pedido.total + pedido.taxa_servico_10));
            pedido.total_cobravel = Number(financeiro.total_cobravel ?? totais.total_cobravel ?? pedido.total_cobravel ?? pedido.total_com_servico);
            pedido.quantidade_itens = Number(totais.quantidade_itens ?? pedido.quantidade_itens ?? 0);

            pedido.total_antecipado = Number(
                financeiro.total_antecipado ?? pedido.total_antecipado ?? 0
            );

            pedido.saldo = Number(
                financeiro.saldo ?? Math.max(0, utils.totalComServico(pedido) - pedido.total_antecipado)
            );

            pedido.status = resp.status_pedido || pedido.status;
            pedido.status_producao = resp.status_pedido || pedido.status_producao;

            state.ultimoHash = '';
            render.update();

            toast('Item excluído da conta', 'warn');

        } catch(err){
            console.error(err);
            toast(err.message || 'Erro ao excluir item da conta', 'erro');

        } finally {
            card.dataset.lock = '0';
            card.classList.remove('loading');
        }

        return;
    }

    if(action === 'alternar-servico-10'){
        const ativar = !utils.servico10Ativo(pedido);
        const verbo = ativar ? 'ativar' : 'desativar';

        if(!confirm(`Deseja ${verbo} os 10% de serviço?\n\n${utils.tituloPedido(pedido)}\nPedido #${pedidoId}`)){
            return;
        }

        if(card.dataset.lock === '1'){
            return;
        }

        card.dataset.lock = '1';
        card.classList.add('loading');

        try {
            const resp = await api.atualizarStatus(
                pedidoId,
                'alternar_servico_10',
                null,
                ativar
            );

            const totais = resp.totais || {};
            const financeiro = resp.financeiro || {};

            pedido.servico_10_ativo = Boolean(financeiro.servico_10_ativo ?? totais.servico_10_ativo ?? ativar);
            pedido.taxa_servico_10 = Number(financeiro.taxa_servico_10 ?? totais.taxa_servico_10 ?? 0);
            pedido.total_com_servico = Number(financeiro.total_com_servico ?? totais.total_com_servico ?? utils.totalComServico(pedido));
            pedido.total_cobravel = Number(financeiro.total_cobravel ?? totais.total_cobravel ?? pedido.total_com_servico);
            pedido.total_antecipado = Number(financeiro.total_antecipado ?? pedido.total_antecipado ?? 0);
            pedido.saldo = Number(financeiro.saldo ?? utils.saldoConta(pedido));

            state.ultimoHash = '';
            render.update();

            toast(ativar ? '10% ativado na conta' : '10% desativado da conta', 'ok');

        } catch(err){
            console.error(err);
            toast(err.message || 'Erro ao alterar os 10%', 'erro');

        } finally {
            card.dataset.lock = '0';
            card.classList.remove('loading');
        }

        return;
    }

    if(action === 'imprimir'){
        utils.vibrar();

        window.open(
            'imprimir_comanda.php?pedido_id=' + pedidoId,
            '_blank'
        );

        return;
    }

    if(action === 'antecipar'){
        if(!caixaManager.aberto()){
            toast('Abra o caixa antes de registrar pagamentos antecipados', 'warn');
            return;
        }

        if(pedido.tipo !== 'mesa'){
            toast('Pagamento antecipado está disponível apenas para mesas', 'warn');
            return;
        }

        if(utils.saldoConta(pedido) <= 0){
            toast('Esta mesa já está totalmente coberta por antecipações', 'warn');
            return;
        }

        const dadosAntecipado = await antecipado.abrir(pedido);

        if(!dadosAntecipado){
            return;
        }

        const valorTxt = utils.money(dadosAntecipado.valor);
        const formaTxt = pagamento.label(dadosAntecipado.forma_pagamento);

        if(!confirm(`Registrar pagamento antecipado?\n\n${utils.tituloPedido(pedido)}\nValor: R$ ${valorTxt}\nForma: ${formaTxt}`)){
            return;
        }

        if(card.dataset.lock === '1'){
            return;
        }

        card.dataset.lock = '1';
        card.classList.add('loading');

        try {
            const resp = await api.registrarAntecipado(
                pedidoId,
                dadosAntecipado.valor,
                dadosAntecipado.forma_pagamento
            );

            pedido.servico_10_ativo = Boolean(resp.servico_10_ativo ?? pedido.servico_10_ativo ?? false);
            pedido.taxa_servico_10 = Number(resp.taxa_servico_10 ?? pedido.taxa_servico_10 ?? 0);
            pedido.total_com_servico = Number(resp.total_com_servico ?? pedido.total_com_servico ?? utils.totalComServico(pedido));
            pedido.total_cobravel = Number(resp.total_cobravel ?? pedido.total_cobravel ?? pedido.total_com_servico);
            pedido.total_antecipado = Number(resp.total_antecipado ?? pedido.total_antecipado ?? 0);
            pedido.saldo = Number(resp.saldo ?? utils.saldoConta(pedido));

            state.ultimoHash = '';
            render.update();

            await caixaManager.carregar(true);

            toast(`Antecipado R$ ${valorTxt}`, 'ok');

        } catch(err){
            console.error(err);
            toast(err.message || 'Erro ao registrar pagamento antecipado', 'erro');

        } finally {
            card.dataset.lock = '0';
            card.classList.remove('loading');
        }

        return;
    }

    if(action === 'cancelar'){
        if(!confirmarConta('cancelar', pedido, pedidoId)){
            return;
        }

        if(card.dataset.lock === '1'){
            return;
        }

        card.dataset.lock = '1';
        card.classList.add('loading');

        try {
            await api.atualizarStatus(
                pedidoId,
                'cancelado'
            );

            toast('Pedido cancelado', 'warn');

            card.classList.add('sumindo');

            setTimeout(() => {
                state.pedidos = state.pedidos.filter(
                    p => utils.pedidoId(p) !== pedidoId
                );

                state.ultimoHash = '';

                render.update();
            }, 250);

        } catch(err){
            console.error(err);
            toast(err.message || 'Erro ao cancelar conta', 'erro');

        } finally {
            card.dataset.lock = '0';
            card.classList.remove('loading');
        }

        return;
    }

    if(action === 'pagar'){
        if(!caixaManager.aberto()){
            toast('Abra o caixa antes de fechar contas', 'warn');
            return;
        }

        if(!utils.pedidoPronto(pedido)){
            toast('Pedido ainda não está pronto', 'warn');
            return;
        }

        let formaPagamento = null;

        if(utils.saldoConta(pedido) <= 0.009){
            formaPagamento = 'antecipado';
        } else {
            formaPagamento = await pagamento.abrir(pedido);
        }

        if(!formaPagamento){
            return;
        }

        if(!confirmarConta('fechar', pedido, pedidoId, formaPagamento)){
            return;
        }

        if(card.dataset.lock === '1'){
            return;
        }

        card.dataset.lock = '1';
        card.classList.add('loading');

        try {
            await api.atualizarStatus(
                pedidoId,
                'pago',
                formaPagamento
            );

            await caixaManager.carregar(true);

            toast(`Conta fechada em ${pagamento.label(formaPagamento)}`, 'ok');

            card.classList.add('sumindo');

            setTimeout(() => {
                state.pedidos = state.pedidos.filter(
                    p => utils.pedidoId(p) !== pedidoId
                );

                state.ultimoHash = '';

                render.update();
            }, 250);

        } catch(err){
            console.error(err);
            toast(err.message || 'Erro ao fechar conta', 'erro');

        } finally {
            card.dataset.lock = '0';
            card.classList.remove('loading');
        }
    }
});

async function loop(){

    if(state.running){
        setTimeout(loop, state.intervalo);
        return;
    }

    state.running = true;

    try {
        const data = await api.listar();

        if(!state.caixa.atualizado_em || (Date.now() - state.caixa.atualizado_em) > 15000){
            await caixaManager.carregar(true);
        }

        if(data.ultima){
            state.ultimoHash = data.ultima;
        }

        if(data.changed !== false || !state.pedidosCarregados){
            state.pedidosCarregados = true;
            state.pedidos = (data.pedidos || [])
                .filter(p => !['pago','cancelado'].includes(p.status));

            render.update();
        }

        state.intervalo = 4000;
        state.tentativasErro = 0;

    } catch(err){
        console.error(err);

        state.tentativasErro++;

        state.intervalo = Math.min(
            1000 * Math.pow(2, state.tentativasErro),
            60000
        );

        // Erro operacional de conexão fica apenas no console.
        // Não exibe toast para não poluir o uso do caixa em quedas rápidas.

    } finally {
        state.running = false;

        setTimeout(loop, state.intervalo);
    }
}

render.update();
caixaManager.carregar(true);
loop();
</script>

</body>
</html>