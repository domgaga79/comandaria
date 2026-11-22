<?php

/*
|--------------------------------------------------------------------------
| COMANDA PÚBLICA
|--------------------------------------------------------------------------
| Tela pública acessada pelo QR Code.
|
| Contrato seguro:
|
| /comanda.php?pedido_id=10&token=HASH
|
| Esta tela não consulta mais buscar_pedido.php.
| Agora usa:
|
| /public_buscar_pedido.php?pedido_id=10&token=HASH
|--------------------------------------------------------------------------
*/

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

$pedidoId = (int)(
    $_GET['pedido_id'] ??
    $_GET['id'] ??
    0
);

$token = trim((string)($_GET['token'] ?? ''));

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if($pedidoId <= 0){
    echo "Pedido inválido";
    exit;
}

if($token === ''){
    echo "Token inválido";
    exit;
}

require_once __DIR__ . '/inc/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Comanda #<?= h($pedidoId) ?></title>

<?= ce_og_tags('Comanda #' . $pedidoId . ' - Comandaria Express', 'Acompanhe sua comanda em tempo real') ?>
<link rel="icon" href="images/comandaria_logo.png" type="image/png">

<link rel="stylesheet" href="assets/comanda-publica.css">
</head>

<body>

<header class="comanda-topo">
    <div class="comanda-marca">
        <img src="images/comandaria_logo.png" alt="" width="40" height="40">
        Comandaria Express
    </div>
</header>

<main class="container">
    <div id="erro" role="alert"></div>

    <div id="app" aria-busy="true">
        <p class="loading" role="status">Carregando comanda…</p>
    </div>

    <footer class="rodape">
        <p id="ultimaAtualizacao" class="atualizado">Atualização automática a cada 5 segundos</p>
        <button type="button" class="btn" id="btnAtualizar" onclick="carregarPedido(true)">
            Atualizar agora
        </button>
    </footer>
</main>

<script>
const pedidoId = <?= (int)$pedidoId ?>;
const publicToken = <?= json_encode($token, JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE) ?>;

const state = {
    carregando: false,
    ultimoJson: '',
    timer: null
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
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[m]);
    },

    qtd(item){
        return Number(item.quantidade ?? item.qtd ?? 0);
    },

    qtdPronta(item){
        return Number(item.quantidade_pronta ?? item.qtd_pronta ?? 0);
    },

    itemPronto(item){
        return this.qtdPronta(item) >= this.qtd(item);
    },

    statusLabel(status){
        return {
            pendente: 'Pendente',
            pronto: 'Pronto',
            pago: 'Pago',
            cancelado: 'Cancelado'
        }[status] || status;
    },

    statusBadge(status){
        if(status === 'pago'){
            return 'badge-pago';
        }

        if(status === 'cancelado'){
            return 'badge-cancelado';
        }

        if(status === 'pronto'){
            return 'badge-pronto';
        }

        return 'badge-pendente';
    },

    tituloPedido(pedido){
        if(pedido.tipo === 'mesa'){
            return `Mesa ${pedido.mesa}`;
        }

        return `Cliente ${pedido.cliente || '-'}`;
    },

    atendentePedido(pedido){
        return pedido.atendente_nome || pedido.atendente || 'Não informado';
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

    data(data){
        if(!data){
            return '--/--/----';
        }

        const d = new Date(data);

        if(isNaN(d.getTime())){
            return '--/--/----';
        }

        return d.toLocaleDateString('pt-BR');
    },

    agora(){
        const d = new Date();

        return d.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
};

async function carregarPedido(force = false){

    if(state.carregando){
        return;
    }

    state.carregando = true;

    const btn = document.getElementById('btnAtualizar');

    if(btn){
        btn.disabled = true;
    }

    try {

        const url = `public_buscar_pedido.php?pedido_id=${encodeURIComponent(pedidoId)}&token=${encodeURIComponent(publicToken)}`;

        const res = await fetch(url, {
            cache: 'no-store'
        });

        const data = await res.json();

        if(!res.ok || !data.ok){
            throw new Error(data?.erro || 'Erro ao carregar comanda');
        }

        const json = JSON.stringify(data.pedido);

        document.getElementById('erro').textContent = '';

        if(!force && json === state.ultimoJson){
            atualizarHora();
            return;
        }

        state.ultimoJson = json;

        renderPedido(data.pedido);

        atualizarHora();

    } catch(err){

        if(!state.ultimoJson){
            document.getElementById('app').replaceChildren();
        }

        document.getElementById('erro').innerHTML = `
            <div class="erro">
                ${utils.escape(err.message || 'Erro ao carregar comanda')}
            </div>
        `;

    } finally {

        state.carregando = false;
        document.getElementById('app').setAttribute('aria-busy', 'false');

        if(btn){
            btn.disabled = false;
        }
    }
}

function atualizarHora(){

    const el = document.getElementById('ultimaAtualizacao');

    if(el){
        el.textContent = `Última atualização: ${utils.agora()}`;
    }
}

function renderPedido(pedido){

    const itens = pedido.itens || [];

    let totalQtd = 0;
    let totalPronta = 0;

    itens.forEach(item => {
        totalQtd += utils.qtd(item);

        totalPronta += Math.min(
            utils.qtdPronta(item),
            utils.qtd(item)
        );
    });

    const percentual = totalQtd > 0
        ? Math.round((totalPronta / totalQtd) * 100)
        : 0;

    let statusProducao = 'pendente';

    if(totalQtd > 0 && percentual >= 100){
        statusProducao = 'pronto';
    }

    if(pedido.status === 'pago'){
        statusProducao = 'pago';
    }

    if(pedido.status === 'cancelado'){
        statusProducao = 'cancelado';
    }

    const itensHtml = itens.length
        ? itens.map(item => renderItem(item)).join('')
        : '<p class="small">Nenhum item encontrado.</p>';

    const app = document.getElementById('app');

    app.innerHTML = `
        <section class="card comanda-capa" aria-labelledby="comanda-titulo">
            <div class="card-header">
                <div>
                    <div class="subtitulo">${utils.escape(utils.tituloPedido(pedido))}</div>
                    <h1 class="titulo" id="comanda-titulo">Comanda #${utils.escape(pedido.pedido_id)}</h1>
                </div>
                <div class="badges">
                    <span class="badge ${utils.statusBadge(pedido.status)}">
                        ${utils.escape(utils.statusLabel(pedido.status))}
                    </span>

                    <span class="badge ${utils.statusBadge(statusProducao)}">
                        Cozinha: ${utils.escape(utils.statusLabel(statusProducao))}
                    </span>
                </div>

            </div>

            <div class="subtitulo comanda-meta">
                <span>${utils.data(pedido.created_at)} às ${utils.hora(pedido.created_at)}</span>
                <span>Atendimento: ${utils.escape(utils.atendentePedido(pedido))}</span>
            </div>

            <div class="progresso-box">
                <div class="progresso-label">
                    <span>Progresso do preparo</span>
                    <strong>${totalPronta} de ${totalQtd} prontos</strong>
                </div>
                <div class="progresso-barra" role="progressbar" aria-label="Progresso do preparo"
                    aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percentual}">
                    <div class="progresso-fill" style="width:${percentual}%"></div>
                </div>
            </div>
        </section>

        <div class="comanda-detalhes">
            <section class="card card-body" aria-labelledby="itens-titulo">
                <h2 id="itens-titulo">Itens do pedido</h2>
                ${itensHtml}
            </section>

            <section class="card card-body comanda-total" aria-labelledby="total-titulo">
                <h2 id="total-titulo">Resumo da conta</h2>
                <div class="linha-total">
                    <span>Subtotal</span>
                    <strong>R$ ${utils.money(pedido.subtotal)}</strong>
                </div>

                <div class="linha-total">
                    <span>Adicionais</span>
                    <strong>R$ ${utils.money(pedido.total_adicional)}</strong>
                </div>

                <div class="linha-total">
                    <span>Total dos itens</span>
                    <span>R$ ${utils.money(pedido.total)}</span>
                </div>

                <div class="linha-total">
                    <span>Taxa de serviço</span>
                    <strong>${utils.servico10Ativo(pedido) ? `R$ ${utils.money(utils.taxaServico(pedido))}` : 'Não cobrado'}</strong>
                </div>

                <div class="linha-total total-final">
                    <span>Total final</span>
                    <strong>R$ ${utils.money(utils.totalComServico(pedido))}</strong>
                </div>
            </section>
        </div>
    `;
}

function renderItem(item){

    const qtd = utils.qtd(item);
    const pronta = utils.qtdPronta(item);
    const pronto = utils.itemPronto(item);

    const adicionalAtivo = Number(item.adicional || 0) === 1;

    const detalhes = [];

    if(adicionalAtivo){
        detalhes.push(
            `Adicional + R$ ${utils.money(item.valor_adicional)}`
        );
    }

    const obs = item.obs ?? item.observacao ?? '';

    if(obs){
        detalhes.push(`Obs: ${obs}`);
    }

    if(item.ponto){
        detalhes.push(`Ponto: ${item.ponto}`);
    }

    const detalhesHtml = detalhes.length
        ? `<div class="item-meta">${utils.escape(detalhes.join(' • '))}</div>`
        : '';

    return `
        <div class="item ${pronto ? 'item-pronto' : ''}">
            <div class="item-top">
                <div>
                    <div class="item-nome">
                        ${qtd}x ${utils.escape(item.nome)}
                    </div>

                    <div class="item-meta">
                        ${utils.escape([item.categoria, pronto ? 'Pronto' : `Preparo ${pronta}/${qtd}`].filter(Boolean).join(' • '))}
                    </div>

                    ${detalhesHtml}
                </div>

                <div class="item-total">
                    R$ ${utils.money(item.subtotal)}
                </div>
            </div>
        </div>
    `;
}

carregarPedido(true);

state.timer = setInterval(() => {
    carregarPedido(false);
}, 5000);
</script>

</body>
</html>
