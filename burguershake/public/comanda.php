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
<link rel="icon" href="images/topo_comandaria_shake.png" type="image/png">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #111;
    color: #fff;
    font-family: Arial, sans-serif;
    padding: 12px;
}

.container {
    max-width: 520px;
    margin: 0 auto;
}

.logo {
    text-align: center;
    margin-bottom: 12px;
}

.logo img {
    max-width: 360px;
    width: 100%;
}

.card {
    background: #fff;
    color: #111;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 18px rgba(0,0,0,.35);
    margin-bottom: 12px;
}

.card-header {
    padding: 14px;
    background: #f1f1f1;
    border-bottom: 1px solid #ddd;
}

.card-body {
    padding: 14px;
}

.titulo {
    font-size: 21px;
    font-weight: 800;
    margin: 0;
}

.subtitulo {
    font-size: 13px;
    color: #555;
    margin-top: 4px;
}

.badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}

.badge {
    display: inline-block;
    border-radius: 999px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 700;
}

.badge-pendente {
    background: #ffc107;
    color: #111;
}

.badge-pronto {
    background: #198754;
    color: #fff;
}

.badge-pago {
    background: #0d6efd;
    color: #fff;
}

.badge-cancelado {
    background: #dc3545;
    color: #fff;
}

.badge-info {
    background: #212529;
    color: #fff;
}

.progresso-box {
    margin-top: 12px;
}

.progresso-label {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 5px;
}

.progresso-barra {
    width: 100%;
    height: 12px;
    background: #ddd;
    border-radius: 999px;
    overflow: hidden;
}

.progresso-fill {
    height: 100%;
    width: 0%;
    background: #198754;
    transition: width .3s ease;
}

.item {
    border-bottom: 1px solid #eee;
    padding: 11px 0;
}

.item:last-child {
    border-bottom: none;
}

.item-top {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.item-nome {
    font-weight: 700;
}

.item-total {
    font-weight: 800;
    white-space: nowrap;
}

.item-meta {
    margin-top: 4px;
    font-size: 12px;
    color: #555;
}

.item-pronto {
    opacity: .6;
}

.item-pronto .item-nome {
    text-decoration: line-through;
}

.linha-total {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
    font-size: 14px;
}

.total-final {
    font-size: 21px;
    font-weight: 900;
    border-top: 1px dashed #aaa;
    padding-top: 8px;
    margin-top: 8px;
}

.loading {
    text-align: center;
    padding: 25px;
    color: #ccc;
}

.erro {
    background: #dc3545;
    color: #fff;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 12px;
}

.footer {
    text-align: center;
    color: #aaa;
    font-size: 12px;
    margin-top: 16px;
}

.btn {
    border: none;
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    font-weight: 800;
    background: #ffc107;
    color: #111;
    margin-top: 10px;
}

.btn:disabled {
    opacity: .6;
}

.small {
    font-size: 12px;
    color: #666;
}

.atualizado {
    color: #aaa;
    font-size: 12px;
    text-align: center;
    margin-top: 8px;
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

    <div class="logo">
        <img src="images/topo_comandaria_shake.png" alt="Comandaria Express">
    </div>

    <div id="erro"></div>

    <div id="app">
        <div class="loading">
            Carregando comanda...
        </div>
    </div>

    <button class="btn" id="btnAtualizar" onclick="carregarPedido(true)">
        Atualizar agora
    </button>

    <div id="ultimaAtualizacao" class="atualizado"></div>

    <div class="footer">
        Comandaria Express<br>
        Atualização automática a cada 5 segundos
    </div>

</div>

<script>
const pedidoId = <?= (int)$pedidoId ?>;
const publicToken = <?= json_encode($token, JSON_UNESCAPED_UNICODE) ?>;

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

    if(state.carregando && !force){
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

        if(!force && json === state.ultimoJson){
            atualizarHora();
            return;
        }

        state.ultimoJson = json;

        renderPedido(data.pedido);

        document.getElementById('erro').innerHTML = '';

        atualizarHora();

    } catch(err){

        document.getElementById('erro').innerHTML = `
            <div class="erro">
                ${utils.escape(err.message || 'Erro ao carregar comanda')}
            </div>
        `;

    } finally {

        state.carregando = false;

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
        <div class="card">
            <div class="card-header">
                <h1 class="titulo">
                    Comanda #${pedido.pedido_id}
                </h1>

                <div class="subtitulo">
                    ${utils.escape(utils.tituloPedido(pedido))}
                    • ${utils.data(pedido.created_at)}
                    • ${utils.hora(pedido.created_at)}
                </div>

                <div class="subtitulo">
                    Atendente: ${utils.escape(utils.atendentePedido(pedido))}
                </div>

                <div class="badges">
                    <span class="badge ${utils.statusBadge(pedido.status)}">
                        ${utils.escape(pedido.status)}
                    </span>

                    <span class="badge ${utils.statusBadge(statusProducao)}">
                        Cozinha: ${utils.escape(statusProducao)}
                    </span>

                    <span class="badge badge-info">
                        ${totalPronta}/${totalQtd} itens prontos
                    </span>
                </div>

                <div class="progresso-box">
                    <div class="progresso-label">
                        <span>Progresso do preparo</span>
                        <strong>${percentual}%</strong>
                    </div>

                    <div class="progresso-barra">
                        <div class="progresso-fill" style="width:${percentual}%"></div>
                    </div>
                </div>
            </div>

            <div class="card-body">
                ${itensHtml}
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="linha-total">
                    <span>Subtotal</span>
                    <strong>R$ ${utils.money(pedido.subtotal)}</strong>
                </div>

                <div class="linha-total">
                    <span>Adicionais</span>
                    <strong>R$ ${utils.money(pedido.total_adicional)}</strong>
                </div>

                <div class="linha-total total-final">
                    <span>Total</span>
                    <span>R$ ${utils.money(pedido.total)}</span>
                </div>

                <div class="linha-total">
                    <span>10% serviço</span>
                    <strong>${utils.servico10Ativo(pedido) ? `R$ ${utils.money(utils.taxaServico(pedido))}` : 'Não cobrado'}</strong>
                </div>

                <div class="linha-total">
                    <span>Total final</span>
                    <strong>R$ ${utils.money(utils.totalComServico(pedido))}</strong>
                </div>
            </div>
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
                        ${utils.escape(item.categoria || '')}
                        • preparo ${pronta}/${qtd}
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
