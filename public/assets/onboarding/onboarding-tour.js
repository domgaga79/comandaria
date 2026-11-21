(() => {
    'use strict';

    const cfg = window.COMANDARIA_ONBOARDING || {};
    const driverFactory = window.driver?.js?.driver;

    if(!driverFactory){
        console.warn('[Onboarding] Driver.js não carregado.');
        return;
    }

    const page = String(cfg.page || '').trim();
    const role = String(cfg.role || '').trim();
    const statusUrl = String(cfg.statusUrl || 'onboarding_status.php');
    const completeUrl = String(cfg.completeUrl || 'onboarding_concluir.php');

    function existe(selector){
        if(!selector){
            return true;
        }

        try {
            return !!document.querySelector(selector);
        } catch(e){
            return false;
        }
    }

    function passo(element, title, description, side = 'bottom', align = 'start', extras = {}){
        if(element && !existe(element)){
            return null;
        }

        return {
            element: element || undefined,
            popover: {
                title,
                description,
                side,
                align
            },
            ...extras
        };
    }

    function modalCarrinhoInstancia(){
        const modalEl = document.getElementById('modalCarrinho');

        if(!modalEl || !window.bootstrap?.Modal){
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(modalEl, {
            backdrop: false,
            keyboard: false
        });
    }

    function abrirModalCarrinhoTour(){
        const modal = modalCarrinhoInstancia();

        if(!modal){
            return;
        }

        modal.show();
    }

    function fecharModalCarrinhoTour(){
        const modalEl = document.getElementById('modalCarrinho');

        if(!modalEl || !window.bootstrap?.Modal){
            return;
        }

        const modal = window.bootstrap.Modal.getInstance(modalEl);

        if(modal){
            modal.hide();
        }
    }

    function passosDashboard(){
        return [
            passo(
                '.userbox',
                'Bem-vindo ao painel',
                'Aqui você confere o usuário logado e pode sair do sistema com segurança.',
                'bottom',
                'center'
            ),
            passo(
                '.grid',
                'Módulos principais',
                'Os cards levam às áreas operacionais e administrativas da Comandaria.',
                'top',
                'center'
            ),
            passo(
                '.card-menu[href*="index.php"]',
                'Atendimento',
                'Use esta área para abrir mesas, clientes avulsos e enviar pedidos.',
                'right',
                'start'
            ),
            passo(
                '.card-menu[href*="painel_cozinha.php"]',
                'Cozinha',
                'Acompanhe pedidos em preparo e marque os itens como prontos.',
                'right',
                'start'
            ),
            passo(
                '.card-menu[href*="painel_caixa.php"]',
                'Caixa',
                'Confira comandas, imprima contas, registre pagamentos e feche pedidos.',
                'left',
                'start'
            ),
            passo(
                '.card-menu[href*="produtos.php"]',
                'Produtos',
                'Cadastre produtos, preços, observações e adicionais.',
                'right',
                'start'
            ),
            passo(
                '.card-menu[href*="relatorios.php"]',
                'Relatórios',
                'Analise vendas, produtos mais vendidos, histórico e desempenho.',
                'left',
                'start'
            )
        ].filter(Boolean);
    }

    function passosAtendimento(){
        return [
            passo(
                '.userbox',
                'Atendimento conectado',
                'Esta é a área usada por atendentes e administradores para lançar pedidos.',
                'bottom',
                'center'
            ),
            passo(
                '.cliente-row',
                'Cliente avulso',
                'Digite o nome e abra um atendimento sem mesa quando necessário.',
                'bottom',
                'start'
            ),
            passo(
                '.clientes-abertos-wrap, #clientesAbertos',
                'Clientes abertos',
                'Pedidos de clientes avulsos em andamento aparecem aqui para retomada rápida.',
                'bottom',
                'start'
            ),
            passo(
                '#mesas',
                'Mesas',
                'Selecione uma mesa livre ou ocupada para criar ou continuar a comanda.',
                'bottom',
                'center'
            ),
            passo(
                '#statusSessao',
                'Atendimento selecionado',
                'Aqui o sistema mostra qual mesa ou cliente está ativo no momento.',
                'top',
                'center'
            ),
            passo(
                '#busca',
                'Buscar produto',
                'Filtre rapidamente o cardápio por nome do item.',
                'bottom',
                'start'
            ),
            passo(
                '#categorias',
                'Categorias',
                'Use os filtros para navegar pelo cardápio com mais agilidade.',
                'bottom',
                'start'
            ),
            passo(
                '#produtos',
                'Adicionar itens',
                'Nos cards de produto, escolha quantidades, adicionais e observações quando disponíveis.',
                'top',
                'center'
            ),
            passo(
                '.carrinho-fixo',
                'Carrinho do pedido',
                'Acompanhe o total, abra o carrinho e revise tudo antes de enviar.',
                'top',
                'center',
                {
                    onDeselected: () => {
                        abrirModalCarrinhoTour();
                    }
                }
            ),
            passo(
                '#btnSalvarModal',
                'Enviar pedido',
                'Este é o botão final do pedido. Depois de conferir os itens no Carrinho, clique aqui para enviar à operação.',
                'top',
                'end',
                {
                    onHighlightStarted: () => {
                        abrirModalCarrinhoTour();
                    },
                    onDeselected: () => {
                        fecharModalCarrinhoTour();
                    }
                }
            )
        ].filter(Boolean);
    }

    function passosCozinha(){
        return [
            passo(
                '.userbox',
                'Painel da cozinha',
                'Este painel recebe os pedidos e organiza a produção em tempo real.',
                'bottom',
                'center'
            ),
            passo(
                '#filtro',
                'Filtrar visualização',
                'Use o filtro para priorizar pedidos e ajustar a leitura da fila.',
                'bottom',
                'start'
            ),
            passo(
                '#pedidos',
                'Fila de pedidos',
                'Os cards mostram mesa ou cliente, tempo, itens e progresso da produção.',
                'top',
                'center'
            ),
            passo(
                '[data-action="imprimir-item"]',
                'Imprimir item',
                'Quando disponível, este botão imprime somente o item novo ou selecionado.',
                'left',
                'center'
            ),
            passo(
                '[data-action="imprimir"]',
                'Imprimir pedido',
                'Use para imprimir a comanda de produção do pedido inteiro.',
                'left',
                'center'
            ),
            passo(
                '[data-action="finalizar"]',
                'Finalizar pedido',
                'Ao concluir todos os itens, finalize o pedido para liberar o fluxo do caixa.',
                'left',
                'center'
            )
        ].filter(Boolean);
    }

    function passosCaixa(){
        return [
            passo(
                '.userbox',
                'Painel do caixa',
                'Esta área concentra conferência, impressão, pagamento e fechamento de contas.',
                'bottom',
                'center'
            ),
            passo(
                '#pedidos, .pedidos-grid, .grid-pedidos, .cards-pedidos',
                'Contas em andamento',
                'As comandas abertas aparecem nesta região para conferência e ação.',
                'top',
                'center'
            ),
            passo(
                '[data-action*="imprimir"], button[onclick*="imprimir"], .btn-imprimir',
                'Imprimir conta',
                'Gere a impressão da comanda para conferência do cliente.',
                'left',
                'center'
            ),
            passo(
                '[data-action*="fechar"], [data-action*="pago"], button[onclick*="fechar"], .btn-fechar-conta',
                'Fechar conta',
                'Finalize o atendimento registrando o pagamento e encerrando a comanda.',
                'left',
                'center'
            )
        ].filter(Boolean);
    }

    function passosDaPagina(){
        switch(page){
            case 'dashboard.php':
                return passosDashboard();
            case 'index.php':
                return passosAtendimento();
            case 'painel_cozinha.php':
                return passosCozinha();
            case 'painel_caixa.php':
                return passosCaixa();
            default:
                return [];
        }
    }

    async function marcarConcluido(tourKey){
        if(!tourKey){
            return;
        }

        try {
            await fetch(completeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    tour_key: tourKey
                })
            });
        } catch(err){
            console.warn('[Onboarding] Não foi possível registrar conclusão.', err);
        }
    }

    function criarTour(steps, tourKey, persistirConclusao){
        return driverFactory({
            animate: true,
            showProgress: true,
            allowClose: true,
            overlayOpacity: 0.72,
            stagePadding: 8,
            stageRadius: 14,
            nextBtnText: 'Próximo',
            prevBtnText: 'Voltar',
            doneBtnText: 'Concluir',
            closeBtnText: 'Pular',
            steps,
            onDestroyed: () => {
                if(page === 'index.php'){
                    fecharModalCarrinhoTour();
                }

                if(persistirConclusao){
                    marcarConcluido(tourKey);
                }
            }
        });
    }

    async function iniciarAutomaticamente(){
        const steps = passosDaPagina();

        if(!steps.length){
            return;
        }

        try {
            const url = statusUrl + '?page=' + encodeURIComponent(page);

            const res = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            });

            const data = await res.json();

            if(!res.ok || !data?.ok || !data?.mostrar || !data?.tour_key){
                return;
            }

            setTimeout(() => {
                criarTour(steps, data.tour_key, true).drive();
            }, 650);

        } catch(err){
            console.warn('[Onboarding] Não foi possível consultar status.', err);
        }
    }

    function repetirGuia(){
        const steps = passosDaPagina();

        if(!steps.length){
            return;
        }

        const tourKeyManual = 'manual_' + page.replace('.php', '') + '_' + (role || 'geral');

        criarTour(steps, tourKeyManual, false).drive();
    }

    window.repetirGuiaComandaria = repetirGuia;

    document.addEventListener('click', event => {
        const botao = event.target.closest('[data-repetir-onboarding]');

        if(!botao){
            return;
        }

        repetirGuia();
    });

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', iniciarAutomaticamente);
    } else {
        iniciarAutomaticamente();
    }
})();
