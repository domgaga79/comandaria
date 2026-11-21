<?php
require 'auth.php';
requireRole(['cozinha','admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

date_default_timezone_set('America/Bahia');

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$pedidoId = (int)($_GET['pedido_id'] ?? $_GET['id'] ?? 0);

if($pedidoId <= 0){
    http_response_code(400);
    echo 'Pedido inválido';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Impressão automática da cozinha #<?= h($pedidoId) ?></title>
<style>
    html, body {
        margin: 0;
        padding: 0;
        background: #fff;
        color: #000;
    }

    body {
        min-height: 1px;
    }

    #autoPrintStatus {
        box-sizing: border-box;
        width: 58mm;
        padding: 8px;
        font-family: Arial, sans-serif;
        font-size: 12px;
        line-height: 1.35;
    }

    #autoPrintStatus strong {
        display: block;
        margin-bottom: 4px;
    }

    #conteudoImpressao {
        display: none;
    }

    body.pronto #autoPrintStatus {
        display: none;
    }

    body.pronto #conteudoImpressao {
        display: block;
    }

    @media print {
        #autoPrintStatus {
            display: none !important;
        }

        #conteudoImpressao {
            display: block !important;
        }
    }
</style>
</head>
<body>
<div id="autoPrintStatus">
    <strong>Preparando impressão da cozinha…</strong>
    Pedido #<?= h($pedidoId) ?>
</div>

<div id="conteudoImpressao"></div>

<script>
(() => {
    const pedidoId = <?= json_encode($pedidoId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const statusEl = document.getElementById('autoPrintStatus');
    const conteudoEl = document.getElementById('conteudoImpressao');

    function atualizarStatus(texto){
        if(statusEl){
            statusEl.innerHTML = '<strong>' + texto + '</strong>Pedido #' + pedidoId;
        }
    }

    function dormir(ms){
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function aguardarImagens(root){
        const imagens = Array.from(root.querySelectorAll('img'));

        if(!imagens.length){
            return Promise.resolve();
        }

        return Promise.all(
            imagens.map(img => {
                if(img.complete && img.naturalWidth > 0){
                    return Promise.resolve();
                }

                if(typeof img.decode === 'function'){
                    return img.decode().catch(() => new Promise(resolve => {
                        img.addEventListener('load', resolve, { once:true });
                        img.addEventListener('error', resolve, { once:true });
                    }));
                }

                return new Promise(resolve => {
                    img.addEventListener('load', resolve, { once:true });
                    img.addEventListener('error', resolve, { once:true });
                });
            })
        );
    }

    function copiarEstilos(docOrigem){
        const estilos = docOrigem.querySelectorAll('style');

        estilos.forEach(styleOrigem => {
            const style = document.createElement('style');
            style.textContent = styleOrigem.textContent || '';
            document.head.appendChild(style);
        });
    }

    function limparConteudoOrigem(docOrigem){
        docOrigem.querySelectorAll('script').forEach(el => el.remove());
        docOrigem.querySelectorAll('#btnImprimir').forEach(el => el.remove());
        return docOrigem.body ? docOrigem.body.innerHTML : '';
    }

    async function carregarEImprimir(){
        try {
            atualizarStatus('Carregando cupom da cozinha…');

            const resposta = await fetch(
                'imprimir_cozinha.php?pedido_id=' + encodeURIComponent(pedidoId),
                {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }
            );

            const html = await resposta.text();

            if(!resposta.ok){
                throw new Error('Falha ao carregar o cupom de impressão.');
            }

            const parser = new DOMParser();
            const docOrigem = parser.parseFromString(html, 'text/html');

            const textoVisivel = (docOrigem.body?.textContent || '').trim();
            if(!textoVisivel){
                throw new Error('O cupom retornou sem conteúdo.');
            }

            copiarEstilos(docOrigem);
            conteudoEl.innerHTML = limparConteudoOrigem(docOrigem);

            document.body.classList.add('pronto');

            atualizarStatus('Finalizando preparação da impressão…');

            await aguardarImagens(conteudoEl);

            if(document.fonts && document.fonts.ready){
                await document.fonts.ready.catch(() => null);
            }

            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            await dormir(900);

            window.focus();
            window.print();
        } catch (erro) {
            console.error(erro);
            atualizarStatus('Não foi possível preparar a impressão automática.');
            if(statusEl){
                const detalhe = document.createElement('div');
                detalhe.style.marginTop = '6px';
                detalhe.textContent = erro?.message || 'Erro inesperado.';
                statusEl.appendChild(detalhe);
            }
        }
    }

    window.addEventListener('load', carregarEImprimir, { once:true });

    window.addEventListener('afterprint', () => {
        setTimeout(() => {
            try {
                window.close();
            } catch (_) {}
        }, 250);
    });
})();
</script>
</body>
</html>
