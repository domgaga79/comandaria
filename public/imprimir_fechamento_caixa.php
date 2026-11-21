<?php
/**
 * Impressão do fechamento de caixa - Comandaria Express Multitenant
 * Correção: localiza o último caixa fechado da empresa logada quando usado ?ultimo=1,
 * sem depender de um ID que pode não existir no tenant atual.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/auth.php';
requireRole(['caixa', 'admin']);
require __DIR__ . '/conexao.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Erro de conexão com o banco de dados.';
    exit;
}

$conn->set_charset('utf8mb4');

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function dinheiro($valor) {
    return number_format((float)$valor, 2, ',', '.');
}

function dataBr($valor) {
    if (!$valor || $valor === '0000-00-00 00:00:00') {
        return '-';
    }

    $ts = strtotime((string)$valor);
    if (!$ts) {
        return (string)$valor;
    }

    return date('d/m/Y H:i', $ts);
}

function normalizar($valor) {
    $valor = strtolower(trim((string)$valor));
    $valor = strtr($valor, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c'
    ]);
    return $valor;
}

function contem($texto, $agulha) {
    return strpos(normalizar($texto), normalizar($agulha)) !== false;
}

function tabelaExiste(mysqli $conn, $tabela) {
    $tabelaEsc = $conn->real_escape_string($tabela);
    $res = $conn->query("SHOW TABLES LIKE '{$tabelaEsc}'");
    return $res && $res->num_rows > 0;
}

function colunasTabela(mysqli $conn, $tabela) {
    static $cache = [];

    if (isset($cache[$tabela])) {
        return $cache[$tabela];
    }

    $cols = [];
    $tabelaSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $tabela);
    $res = $conn->query("SHOW COLUMNS FROM `{$tabelaSafe}`");

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[$row['Field']] = true;
        }
    }

    $cache[$tabela] = $cols;
    return $cols;
}

function temColuna($cols, $coluna) {
    return isset($cols[$coluna]);
}

function primeiraColuna($cols, $opcoes) {
    foreach ($opcoes as $opcao) {
        if (temColuna($cols, $opcao)) {
            return $opcao;
        }
    }
    return null;
}

function campo($linha, $opcoes, $padrao = null) {
    foreach ($opcoes as $opcao) {
        if (array_key_exists($opcao, $linha) && $linha[$opcao] !== null && $linha[$opcao] !== '') {
            return $linha[$opcao];
        }
    }
    return $padrao;
}

function sessionEmpresaId() {
    $opcoes = [
        'empresa_id', 'empresa_logada_id', 'tenant_id', 'current_empresa_id',
        'id_empresa', 'usuario_empresa_id'
    ];

    foreach ($opcoes as $opcao) {
        if (isset($_SESSION[$opcao]) && (int)$_SESSION[$opcao] > 0) {
            return (int)$_SESSION[$opcao];
        }
    }

    if (isset($_SESSION['empresa']['id']) && (int)$_SESSION['empresa']['id'] > 0) {
        return (int)$_SESSION['empresa']['id'];
    }

    return 0;
}

function sessionEmpresaNome() {
    $opcoes = ['empresa_nome', 'nome_empresa', 'tenant_nome'];
    foreach ($opcoes as $opcao) {
        if (!empty($_SESSION[$opcao])) {
            return (string)$_SESSION[$opcao];
        }
    }

    if (!empty($_SESSION['empresa']['nome'])) {
        return (string)$_SESSION['empresa']['nome'];
    }

    return 'Comandaria Express';
}

function buscarNomeEmpresa(mysqli $conn, $empresaId, $fallback) {
    if ($empresaId <= 0 || !tabelaExiste($conn, 'empresas')) {
        return $fallback;
    }

    $cols = colunasTabela($conn, 'empresas');
    $nomeCol = primeiraColuna($cols, ['nome', 'razao_social', 'fantasia', 'nome_fantasia']);
    if (!$nomeCol || !temColuna($cols, 'id')) {
        return $fallback;
    }

    $sql = "SELECT `{$nomeCol}` AS nome FROM `empresas` WHERE `id` = {$empresaId} LIMIT 1";
    $res = $conn->query($sql);
    if ($res && ($row = $res->fetch_assoc()) && !empty($row['nome'])) {
        return (string)$row['nome'];
    }

    return $fallback;
}

function encontrarTabelaCaixa(mysqli $conn) {
    $opcoes = ['caixas', 'caixa_sessoes', 'caixa_sessao', 'caixa'];
    foreach ($opcoes as $tabela) {
        if (tabelaExiste($conn, $tabela)) {
            return $tabela;
        }
    }
    return null;
}

function montarCondicaoFechado($cols) {
    $partes = [];

    foreach (['fechado_em', 'data_fechamento', 'dt_fechamento', 'encerrado_em', 'closed_at'] as $coluna) {
        if (temColuna($cols, $coluna)) {
            $partes[] = "`{$coluna}` IS NOT NULL";
        }
    }

    if (temColuna($cols, 'status')) {
        $partes[] = "LOWER(`status`) IN ('fechado','fechada','encerrado','encerrada','finalizado','finalizada','closed')";
    }

    if (temColuna($cols, 'aberto')) {
        $partes[] = "`aberto` = 0";
    }

    if (!$partes) {
        return '';
    }

    return '(' . implode(' OR ', $partes) . ')';
}

function buscarCaixaFechado(mysqli $conn, $tabela, $empresaId) {
    $cols = colunasTabela($conn, $tabela);
    $where = [];

    $idSolicitado = isset($_GET['caixa_id']) ? (int)$_GET['caixa_id'] : 0;
    $usarUltimo = isset($_GET['ultimo']) || $idSolicitado <= 0;

    if ($idSolicitado > 0 && temColuna($cols, 'id')) {
        $where[] = "`id` = {$idSolicitado}";
    }

    $empresaCol = primeiraColuna($cols, ['empresa_id', 'tenant_id', 'id_empresa']);
    if ($empresaCol && $empresaId > 0) {
        $where[] = "`{$empresaCol}` = {$empresaId}";
    }

    $condFechado = montarCondicaoFechado($cols);
    if ($condFechado !== '') {
        $where[] = $condFechado;
    }

    $ordemCol = primeiraColuna($cols, ['fechado_em', 'data_fechamento', 'dt_fechamento', 'encerrado_em', 'closed_at', 'updated_at', 'id']);
    $ordem = $ordemCol ? "`{$ordemCol}` DESC" : '1 DESC';

    $sql = "SELECT * FROM `{$tabela}`";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY {$ordem} LIMIT 1";

    $res = $conn->query($sql);
    if ($res && ($row = $res->fetch_assoc())) {
        return [$row, $cols, $sql];
    }

    // Fallback importante: se veio caixa_id inválido para o tenant, tenta último fechado da empresa.
    if (!$usarUltimo && $empresaCol && $empresaId > 0) {
        $where = ["`{$empresaCol}` = {$empresaId}"];
        if ($condFechado !== '') {
            $where[] = $condFechado;
        }

        $sql = "SELECT * FROM `{$tabela}` WHERE " . implode(' AND ', $where) . " ORDER BY {$ordem} LIMIT 1";
        $res = $conn->query($sql);
        if ($res && ($row = $res->fetch_assoc())) {
            return [$row, $cols, $sql];
        }
    }

    return [null, $cols, $sql];
}

function encontrarTabelaMovimentos(mysqli $conn) {
    $opcoes = ['caixa_movimentos', 'caixa_movimentacoes', 'caixa_lancamentos', 'caixa_movimento'];
    foreach ($opcoes as $tabela) {
        if (tabelaExiste($conn, $tabela)) {
            return $tabela;
        }
    }
    return null;
}

function buscarMovimentos(mysqli $conn, $caixaId, $empresaId) {
    $tabela = encontrarTabelaMovimentos($conn);
    if (!$tabela || $caixaId <= 0) {
        return [];
    }

    $cols = colunasTabela($conn, $tabela);
    $caixaCol = primeiraColuna($cols, ['caixa_id', 'sessao_caixa_id', 'caixa_sessao_id', 'id_caixa']);
    if (!$caixaCol) {
        return [];
    }

    $where = ["`{$caixaCol}` = {$caixaId}"];
    $empresaCol = primeiraColuna($cols, ['empresa_id', 'tenant_id', 'id_empresa']);
    if ($empresaCol && $empresaId > 0) {
        $where[] = "`{$empresaCol}` = {$empresaId}";
    }

    $ordemCol = primeiraColuna($cols, ['criado_em', 'created_at', 'data', 'id']);
    $ordem = $ordemCol ? "`{$ordemCol}` ASC" : '1 ASC';

    $sql = "SELECT * FROM `{$tabela}` WHERE " . implode(' AND ', $where) . " ORDER BY {$ordem}";
    $res = $conn->query($sql);

    $movimentos = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $movimentos[] = $row;
        }
    }

    return $movimentos;
}

function resumoMovimentos($movimentos) {
    $resumo = [
        'dinheiro' => 0.0,
        'pix' => 0.0,
        'cartao' => 0.0,
        'prazo' => 0.0,
        'entrada' => 0.0,
        'saida' => 0.0,
        'total' => 0.0,
        'qtd' => count($movimentos)
    ];

    foreach ($movimentos as $mov) {
        $valor = (float)campo($mov, ['valor', 'amount', 'total'], 0);
        $tipo = normalizar(campo($mov, ['tipo', 'natureza', 'operacao'], 'entrada'));
        $forma = normalizar(campo($mov, ['forma_pagamento', 'metodo_pagamento', 'pagamento', 'forma'], ''));
        $descricao = normalizar(campo($mov, ['descricao', 'observacao', 'origem'], ''));
        $texto = trim($forma . ' ' . $descricao . ' ' . $tipo);

        $ehSaida = contem($tipo, 'saida') || contem($tipo, 'retirada') || contem($tipo, 'sangria') || contem($tipo, 'cancel');

        if ($ehSaida) {
            $resumo['saida'] += abs($valor);
            $resumo['total'] -= abs($valor);
            continue;
        }

        $resumo['entrada'] += $valor;
        $resumo['total'] += $valor;

        if (contem($texto, 'dinheiro')) {
            $resumo['dinheiro'] += $valor;
        } elseif (contem($texto, 'pix')) {
            $resumo['pix'] += $valor;
        } elseif (contem($texto, 'cartao') || contem($texto, 'cartão') || contem($texto, 'credito') || contem($texto, 'debito')) {
            $resumo['cartao'] += $valor;
        } elseif (contem($texto, 'prazo')) {
            $resumo['prazo'] += $valor;
        }
    }

    return $resumo;
}

$tabelaCaixa = encontrarTabelaCaixa($conn);
$empresaId = sessionEmpresaId();
$empresaNome = buscarNomeEmpresa($conn, $empresaId, sessionEmpresaNome());
$usuarioNome = $_SESSION['user_nome'] ?? $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Usuário';

if (!$tabelaCaixa) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Fechamento não encontrado</title><p>Tabela de caixa não localizada.</p>';
    exit;
}

list($caixa, $caixaCols) = buscarCaixaFechado($conn, $tabelaCaixa, $empresaId);

if (!$caixa) {
    http_response_code(404);
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Fechamento não encontrado</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 520px; margin: 40px auto; padding: 20px; color: #1f2937; }
            .box { border: 1px solid #e5e7eb; border-radius: 14px; padding: 18px; background: #fff; }
            h1 { font-size: 20px; margin: 0 0 10px; }
            p { line-height: 1.5; }
            a, button { display: inline-block; margin-top: 12px; padding: 10px 14px; border-radius: 10px; background: #111827; color: #fff; text-decoration: none; border: 0; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Fechamento não encontrado</h1>
            <p>Não foi possível localizar um caixa fechado para impressão nesta empresa.</p>
            <p>Feche um caixa primeiro e tente imprimir novamente.</p>
            <a href="painel_caixa.php">Voltar ao caixa</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$caixaId = (int)campo($caixa, ['id', 'caixa_id'], 0);
$movimentos = buscarMovimentos($conn, $caixaId, $empresaId);
$resMov = resumoMovimentos($movimentos);

$saldoInicial = (float)campo($caixa, ['saldo_inicial', 'valor_abertura'], 0);
$dinheiro = (float)campo($caixa, ['total_dinheiro', 'dinheiro'], $resMov['dinheiro']);
$pix = (float)campo($caixa, ['total_pix', 'pix'], $resMov['pix']);
$cartao = (float)campo($caixa, ['total_cartao', 'cartao'], $resMov['cartao']);
$prazo = (float)campo($caixa, ['total_prazo', 'prazo'], $resMov['prazo']);
$totalRecebido = (float)campo($caixa, ['total_recebido', 'total_vendas', 'total_pagamentos'], $resMov['entrada']);
$saldoEsperado = (float)campo($caixa, ['saldo_esperado_dinheiro', 'saldo_esperado', 'valor_esperado'], $saldoInicial + $dinheiro);
$saldoFinalInformado = (float)campo($caixa, ['saldo_final_informado', 'saldo_final', 'valor_fechamento'], $saldoEsperado);
$diferenca = (float)campo($caixa, ['diferenca_dinheiro', 'diferenca', 'quebra'], $saldoFinalInformado - $saldoEsperado);

$abertoEm = campo($caixa, ['aberto_em', 'data_abertura', 'dt_abertura', 'created_at'], null);
$fechadoEm = campo($caixa, ['fechado_em', 'data_fechamento', 'dt_fechamento', 'encerrado_em', 'updated_at'], null);
$obsAbertura = campo($caixa, ['observacao_abertura', 'obs_abertura'], '');
$obsFechamento = campo($caixa, ['observacao_fechamento', 'obs_fechamento', 'observacao'], '');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fechamento de Caixa #<?= h($caixaId) ?></title>
    <style>
        @page { size: 58mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: #f3f4f6;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.25;
        }
        .toolbar {
            max-width: 360px;
            margin: 12px auto;
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .toolbar button,
        .toolbar a {
            border: 0;
            border-radius: 8px;
            padding: 9px 12px;
            background: #111827;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }
        .cupom {
            width: 58mm;
            min-height: 100vh;
            margin: 0 auto;
            padding: 8px 7px 12px;
            background: #fff;
            text-align: left;
        }
        .center { text-align: center; }
        .titulo {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0 0 2px;
        }
        .subtitulo { font-size: 11px; margin: 0; }
        .linha {
            border-top: 1px dashed #111;
            margin: 7px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 2px 0;
        }
        .row span:first-child { max-width: 60%; }
        .row strong { text-align: right; }
        .secao {
            font-weight: 800;
            text-transform: uppercase;
            font-size: 11px;
            margin: 7px 0 4px;
        }
        .obs {
            white-space: pre-wrap;
            word-break: break-word;
        }
        .movimento {
            margin: 4px 0;
            padding-bottom: 4px;
            border-bottom: 1px dotted #ccc;
        }
        .muted { color: #444; font-size: 10px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .cupom { margin: 0; width: 58mm; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
        <a href="painel_caixa.php">Voltar</a>
    </div>

    <main class="cupom">
        <div class="center">
            <div class="titulo"><?= h($empresaNome) ?></div>
            <p class="subtitulo">Fechamento de caixa</p>
            <p class="subtitulo">#<?= h($caixaId) ?></p>
        </div>

        <div class="linha"></div>

        <div class="row"><span>Abertura</span><strong><?= h(dataBr($abertoEm)) ?></strong></div>
        <div class="row"><span>Fechamento</span><strong><?= h(dataBr($fechadoEm)) ?></strong></div>
        <div class="row"><span>Impresso em</span><strong><?= h(date('d/m/Y H:i')) ?></strong></div>
        <div class="row"><span>Usuário</span><strong><?= h($usuarioNome) ?></strong></div>

        <div class="linha"></div>

        <div class="secao">Resumo financeiro</div>
        <div class="row"><span>Saldo inicial</span><strong>R$ <?= dinheiro($saldoInicial) ?></strong></div>
        <div class="row"><span>Dinheiro</span><strong>R$ <?= dinheiro($dinheiro) ?></strong></div>
        <div class="row"><span>Pix</span><strong>R$ <?= dinheiro($pix) ?></strong></div>
        <div class="row"><span>Cartão</span><strong>R$ <?= dinheiro($cartao) ?></strong></div>
        <div class="row"><span>A prazo</span><strong>R$ <?= dinheiro($prazo) ?></strong></div>
        <div class="row"><span>Total recebido</span><strong>R$ <?= dinheiro($totalRecebido) ?></strong></div>

        <div class="linha"></div>

        <div class="secao">Conferência do dinheiro</div>
        <div class="row"><span>Esperado</span><strong>R$ <?= dinheiro($saldoEsperado) ?></strong></div>
        <div class="row"><span>Informado</span><strong>R$ <?= dinheiro($saldoFinalInformado) ?></strong></div>
        <div class="row"><span>Diferença</span><strong>R$ <?= dinheiro($diferenca) ?></strong></div>

        <?php if ($obsAbertura !== '' || $obsFechamento !== ''): ?>
            <div class="linha"></div>
            <div class="secao">Observações</div>
            <?php if ($obsAbertura !== ''): ?>
                <div class="muted">Abertura</div>
                <div class="obs"><?= h($obsAbertura) ?></div>
            <?php endif; ?>
            <?php if ($obsFechamento !== ''): ?>
                <div class="muted">Fechamento</div>
                <div class="obs"><?= h($obsFechamento) ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($movimentos): ?>
            <div class="linha"></div>
            <div class="secao">Movimentos</div>
            <?php foreach ($movimentos as $mov): ?>
                <?php
                    $valorMov = (float)campo($mov, ['valor', 'amount', 'total'], 0);
                    $formaMov = campo($mov, ['forma_pagamento', 'metodo_pagamento', 'pagamento', 'forma'], '');
                    $tipoMov = campo($mov, ['tipo', 'natureza', 'operacao'], '');
                    $descMov = campo($mov, ['descricao', 'observacao', 'origem'], 'Movimento');
                    $dataMov = campo($mov, ['criado_em', 'created_at', 'data'], '');
                ?>
                <div class="movimento">
                    <div><strong><?= h($descMov) ?></strong></div>
                    <div class="row"><span><?= h(trim($tipoMov . ' ' . $formaMov)) ?></span><strong>R$ <?= dinheiro($valorMov) ?></strong></div>
                    <?php if ($dataMov): ?><div class="muted"><?= h(dataBr($dataMov)) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="linha"></div>
        <div class="center muted">Comandaria Express</div>
    </main>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                try { window.print(); } catch (e) {}
            }, 250);
        });
    </script>
</body>
</html>
