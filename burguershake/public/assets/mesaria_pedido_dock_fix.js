/* =========================================================
   MESARIA EXPRESS - DOCK "SEU PEDIDO"
   Detecta o painel inferior e ajusta o respiro da página
   Arquivo: assets/mesaria_pedido_dock_fix.js
========================================================= */
(function(){
    'use strict';

    const DOCK_ID = 'mesariaPedidoDock';
    const DOCK_VAZIO_H = 85;
    const DOCK_GAP_H = 15;
    let timer = null;

    function normalizarTexto(valor){
        return String(valor || '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function area(el){
        const r = el.getBoundingClientRect();
        return Math.max(1, Math.round(r.width * r.height));
    }

    function pareceContainerMuitoGrande(el){
        const tag = String(el.tagName || '').toLowerCase();
        if(['html', 'body', 'main'].includes(tag)){
            return true;
        }

        const r = el.getBoundingClientRect();
        return r.height > window.innerHeight * 0.82 && r.width > window.innerWidth * 0.82;
    }

    function localizarDock(){
        const direto = document.getElementById('cartPanel') ||
            document.getElementById(DOCK_ID) ||
            document.querySelector('[data-mesaria-pedido-dock="1"]');

        if(direto){
            return direto;
        }

        const seletores = 'aside, section, footer, article, div';
        const candidatos = Array.from(document.querySelectorAll(seletores));
        const ranqueados = [];

        candidatos.forEach(el => {
            if(pareceContainerMuitoGrande(el)){
                return;
            }

            const txt = normalizarTexto(el.textContent);

            if(!txt.includes('seu pedido')){
                return;
            }

            let score = 100;

            if(txt.includes('enviar pedido')) score += 45;
            if(txt.includes('limpar')) score += 20;
            if(txt.includes('nenhum item selecionado')) score += 20;
            if(txt.includes('toque em')) score += 10;
            if(txt.includes('r$')) score += 10;

            const r = el.getBoundingClientRect();
            if(r.top > window.innerHeight * 0.35) score += 15;
            if(getComputedStyle(el).position === 'fixed') score += 25;

            ranqueados.push({ el, score, area: area(el) });
        });

        ranqueados.sort((a, b) => {
            if(b.score !== a.score){
                return b.score - a.score;
            }
            return a.area - b.area;
        });

        return ranqueados[0]?.el || null;
    }

    function temItensNoPedido(dock){
        const txt = normalizarTexto(dock.textContent);

        if(
            txt.includes('nenhum item selecionado') ||
            txt.includes('carrinho vazio') ||
            txt.includes('toque em')
        ){
            return false;
        }

        if(dock.querySelector('[data-cart-id], [data-item-id], [data-produto-id], .cart-item, .pedido-item, .item-pedido, .linha-produto-pedido')){
            return true;
        }

        const qtdEl = document.getElementById('qtdCarrinho') ||
            document.querySelector('[data-qtd-carrinho], .qtd-carrinho, .contador-carrinho');

        if(qtdEl){
            const qtd = Number(String(qtdEl.textContent || '0').replace(/\D/g, '') || 0);
            return qtd > 0;
        }

        const totalMatch = txt.match(/r\$\s*([0-9]+(?:[\.,][0-9]{2})?)/i);
        if(totalMatch){
            const total = Number(totalMatch[1].replace('.', '').replace(',', '.'));
            return total > 0;
        }

        return false;
    }

    function setVarDockAltura(px){
        const valor = Math.max(DOCK_VAZIO_H, Math.ceil(Number(px) || DOCK_VAZIO_H));
        document.documentElement.style.setProperty('--mesaria-pedido-dock-h', valor + 'px', 'important');
        document.documentElement.style.setProperty('--mesaria-pedido-dock-gap', DOCK_GAP_H + 'px', 'important');
    }

    function aplicarDockVazio(dock){
        dock.style.setProperty('height', DOCK_VAZIO_H + 'px', 'important');
        dock.style.setProperty('max-height', DOCK_VAZIO_H + 'px', 'important');
        dock.style.setProperty('overflow', 'hidden', 'important');
        setVarDockAltura(DOCK_VAZIO_H);
    }

    function aplicarDockComItens(dock){
        dock.style.removeProperty('height');
        dock.style.removeProperty('max-height');
        dock.style.removeProperty('overflow');
        dock.style.setProperty('overflow-y', 'auto', 'important');

        requestAnimationFrame(() => {
            const altura = Math.max(DOCK_VAZIO_H, Math.ceil(dock.getBoundingClientRect().height || DOCK_VAZIO_H));
            setVarDockAltura(altura);
        });
    }

    function atualizarDock(){
        const dock = localizarDock();

        if(!dock){
            return;
        }

        dock.setAttribute('data-mesaria-pedido-dock', '1');
        dock.classList.add('mesaria-pedido-dock-auto');
        document.body.classList.add('mesaria-pedido-dock-ativo');

        const comItens = temItensNoPedido(dock);

        dock.classList.toggle('mesaria-pedido-vazio', !comItens);
        dock.classList.toggle('mesaria-pedido-com-itens', comItens);

        requestAnimationFrame(() => {
            if(comItens){
                aplicarDockComItens(dock);
            } else {
                aplicarDockVazio(dock);
            }
        });
    }

    function agendarAtualizacao(){
        clearTimeout(timer);
        timer = setTimeout(atualizarDock, 80);
    }

    function iniciar(){
        atualizarDock();

        const observer = new MutationObserver(agendarAtualizacao);
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }

    window.addEventListener('load', agendarAtualizacao);
    window.addEventListener('resize', agendarAtualizacao);
    window.addEventListener('orientationchange', agendarAtualizacao);
})();
