<?php

/*
|--------------------------------------------------------------------------
| Núcleo de abertura, fechamento e movimentos de caixa
|--------------------------------------------------------------------------
| Este módulo mantém uma sessão de caixa aberta por vez e registra os
| recebimentos que afetam o fechamento:
| - pagamento final de pedidos;
| - pagamentos antecipados de mesas;
| - vendas encerradas a prazo, apenas para conferência gerencial.
|--------------------------------------------------------------------------
*/

if(!function_exists('caixaTabelaExiste')){
    function caixaTabelaExiste(mysqli $conn, string $tabela): bool {
        $tabela = $conn->real_escape_string($tabela);
        $res = $conn->query("SHOW TABLES LIKE '{$tabela}'");
        $existe = $res instanceof mysqli_result && $res->num_rows > 0;

        if($res instanceof mysqli_result){
            $res->free();
        }

        return $existe;
    }
}

if(!function_exists('caixaEstruturaDisponivel')){
    function caixaEstruturaDisponivel(mysqli $conn): bool {
        return caixaTabelaExiste($conn, 'caixas')
            && caixaTabelaExiste($conn, 'caixa_movimentos');
    }
}

if(!function_exists('exigirEstruturaCaixa')){
    function exigirEstruturaCaixa(mysqli $conn): void {
        if(!caixaEstruturaDisponivel($conn)){
            throw new Exception('Estrutura de controle de caixa indisponível no banco atual');
        }
    }
}

if(!function_exists('normalizarCaixa')){
    function normalizarCaixa(array $row): array {
        return [
            'id' => (int)($row['id'] ?? 0),
            'status' => (string)($row['status'] ?? ''),
            'saldo_inicial' => round((float)($row['saldo_inicial'] ?? 0), 2),
            'aberto_em' => $row['aberto_em'] ?? null,
            'aberto_por_usuario_id' => isset($row['aberto_por_usuario_id']) ? (int)$row['aberto_por_usuario_id'] : null,
            'fechado_em' => $row['fechado_em'] ?? null,
            'fechado_por_usuario_id' => isset($row['fechado_por_usuario_id']) ? (int)$row['fechado_por_usuario_id'] : null,
            'saldo_final_informado' => isset($row['saldo_final_informado']) && $row['saldo_final_informado'] !== null
                ? round((float)$row['saldo_final_informado'], 2)
                : null,
            'saldo_esperado_dinheiro' => isset($row['saldo_esperado_dinheiro']) && $row['saldo_esperado_dinheiro'] !== null
                ? round((float)$row['saldo_esperado_dinheiro'], 2)
                : null,
            'diferenca_dinheiro' => isset($row['diferenca_dinheiro']) && $row['diferenca_dinheiro'] !== null
                ? round((float)$row['diferenca_dinheiro'], 2)
                : null,
            'observacao_abertura' => $row['observacao_abertura'] ?? null,
            'observacao_fechamento' => $row['observacao_fechamento'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}

if(!function_exists('buscarCaixaPorId')){
    function buscarCaixaPorId(mysqli $conn, int $caixaId): ?array {
        exigirEstruturaCaixa($conn);

        $stmt = $conn->prepare("\n            SELECT *\n            FROM caixas\n            WHERE id = ?\n            LIMIT 1\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('i', $caixaId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ? normalizarCaixa($row) : null;
    }
}

if(!function_exists('buscarCaixaAberto')){
    function buscarCaixaAberto(mysqli $conn): ?array {
        exigirEstruturaCaixa($conn);

        $statusAberto = CAIXA_STATUS_ABERTO;

        $stmt = $conn->prepare("\n            SELECT *\n            FROM caixas\n            WHERE status = ?\n            ORDER BY id DESC\n            LIMIT 1\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('s', $statusAberto);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return $row ? normalizarCaixa($row) : null;
    }
}

if(!function_exists('exigirCaixaAberto')){
    function exigirCaixaAberto(mysqli $conn): array {
        $caixa = buscarCaixaAberto($conn);

        if(!$caixa){
            throw new Exception('Abra o caixa antes de registrar pagamentos');
        }

        return $caixa;
    }
}

if(!function_exists('resumoCaixaVazio')){
    function resumoCaixaVazio(float $saldoInicial = 0): array {
        $saldoInicial = round($saldoInicial, 2);

        return [
            'saldo_inicial' => $saldoInicial,
            'dinheiro' => 0.00,
            'pix' => 0.00,
            'cartao' => 0.00,
            'prazo' => 0.00,
            'total_recebido' => 0.00,
            'total_antecipado_recebido' => 0.00,
            'total_pagamento_pedidos' => 0.00,
            'total_vendas_prazo' => 0.00,
            'saldo_esperado_dinheiro' => $saldoInicial,
            'quantidade_movimentos' => 0,
        ];
    }
}

if(!function_exists('obterResumoCaixa')){
    function obterResumoCaixa(mysqli $conn, int $caixaId): array {
        exigirEstruturaCaixa($conn);

        $caixa = buscarCaixaPorId($conn, $caixaId);

        if(!$caixa){
            throw new Exception('Caixa não encontrado');
        }

        $resumo = resumoCaixaVazio((float)$caixa['saldo_inicial']);

        $stmt = $conn->prepare("\n            SELECT\n                forma_pagamento,\n                tipo,\n                COUNT(*) AS quantidade,\n                COALESCE(SUM(valor), 0) AS total\n            FROM caixa_movimentos\n            WHERE caixa_id = ?\n            GROUP BY forma_pagamento, tipo\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('i', $caixaId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $res = $stmt->get_result();

        while($row = $res->fetch_assoc()){
            $forma = (string)($row['forma_pagamento'] ?? '');
            $tipo = (string)($row['tipo'] ?? '');
            $total = round((float)($row['total'] ?? 0), 2);
            $quantidade = (int)($row['quantidade'] ?? 0);

            if(array_key_exists($forma, $resumo)){
                $resumo[$forma] = round((float)$resumo[$forma] + $total, 2);
            }

            $resumo['quantidade_movimentos'] += $quantidade;

            if($tipo === CAIXA_MOVIMENTO_PAGAMENTO_ANTECIPADO){
                $resumo['total_antecipado_recebido'] = round(
                    (float)$resumo['total_antecipado_recebido'] + $total,
                    2
                );
            }

            if($tipo === CAIXA_MOVIMENTO_PAGAMENTO_PEDIDO){
                $resumo['total_pagamento_pedidos'] = round(
                    (float)$resumo['total_pagamento_pedidos'] + $total,
                    2
                );
            }

            if($tipo === CAIXA_MOVIMENTO_VENDA_PRAZO){
                $resumo['total_vendas_prazo'] = round(
                    (float)$resumo['total_vendas_prazo'] + $total,
                    2
                );
            }
        }

        $stmt->close();

        $resumo['total_recebido'] = round(
            (float)$resumo['dinheiro'] +
            (float)$resumo['pix'] +
            (float)$resumo['cartao'],
            2
        );

        $resumo['saldo_esperado_dinheiro'] = round(
            (float)$resumo['saldo_inicial'] + (float)$resumo['dinheiro'],
            2
        );

        return $resumo;
    }
}

if(!function_exists('abrirCaixaSessao')){
    function abrirCaixaSessao(
        mysqli $conn,
        float $saldoInicial,
        ?int $usuarioId = null,
        ?string $observacao = null
    ): array {
        exigirEstruturaCaixa($conn);

        $existente = buscarCaixaAberto($conn);

        if($existente){
            throw new Exception('Já existe um caixa aberto');
        }

        $saldoInicial = round(max(0, $saldoInicial), 2);
        $observacao = trim((string)$observacao);

        if($observacao === ''){
            $observacao = null;
        }

        $status = CAIXA_STATUS_ABERTO;

        $stmt = $conn->prepare("\n            INSERT INTO caixas (\n                status,\n                saldo_inicial,\n                aberto_em,\n                aberto_por_usuario_id,\n                observacao_abertura,\n                created_at,\n                updated_at\n            )\n            VALUES (?, ?, NOW(), ?, ?, NOW(), NOW())\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            'sdis',
            $status,
            $saldoInicial,
            $usuarioId,
            $observacao
        );

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $caixaId = (int)$conn->insert_id;
        $stmt->close();

        $caixa = buscarCaixaPorId($conn, $caixaId);

        if(!$caixa){
            throw new Exception('Não foi possível recuperar o caixa aberto');
        }

        return [
            'caixa' => $caixa,
            'resumo' => obterResumoCaixa($conn, $caixaId)
        ];
    }
}

if(!function_exists('fecharCaixaSessao')){
    function fecharCaixaSessao(
        mysqli $conn,
        float $saldoFinalInformado,
        ?int $usuarioId = null,
        ?string $observacao = null
    ): array {
        exigirEstruturaCaixa($conn);

        $caixa = exigirCaixaAberto($conn);
        $caixaId = (int)$caixa['id'];
        $resumo = obterResumoCaixa($conn, $caixaId);

        $saldoFinalInformado = round(max(0, $saldoFinalInformado), 2);
        $saldoEsperado = round((float)$resumo['saldo_esperado_dinheiro'], 2);
        $diferenca = round($saldoFinalInformado - $saldoEsperado, 2);

        $observacao = trim((string)$observacao);

        if($observacao === ''){
            $observacao = null;
        }

        $statusFechado = CAIXA_STATUS_FECHADO;
        $resumoJson = json_encode($resumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $conn->prepare("\n            UPDATE caixas\n            SET\n                status = ?,\n                fechado_em = NOW(),\n                fechado_por_usuario_id = ?,\n                saldo_final_informado = ?,\n                saldo_esperado_dinheiro = ?,\n                diferenca_dinheiro = ?,\n                observacao_fechamento = ?,\n                resumo_fechamento_json = ?,\n                updated_at = NOW()\n            WHERE id = ?\n            AND status = ?\n            LIMIT 1\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            'sidddssis',
            $statusFechado,
            $usuarioId,
            $saldoFinalInformado,
            $saldoEsperado,
            $diferenca,
            $observacao,
            $resumoJson,
            $caixaId,
            $caixa['status']
        );

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        if($stmt->affected_rows < 1){
            throw new Exception('O caixa não pôde ser fechado; atualize a tela e tente novamente');
        }

        $stmt->close();

        $caixaFechado = buscarCaixaPorId($conn, $caixaId);

        if(!$caixaFechado){
            throw new Exception('Não foi possível recuperar o fechamento do caixa');
        }

        return [
            'caixa' => $caixaFechado,
            'resumo' => $resumo,
            'saldo_final_informado' => $saldoFinalInformado,
            'saldo_esperado_dinheiro' => $saldoEsperado,
            'diferenca_dinheiro' => $diferenca
        ];
    }
}

if(!function_exists('registrarMovimentoCaixa')){
    function registrarMovimentoCaixa(
        mysqli $conn,
        int $caixaId,
        string $tipo,
        string $formaPagamento,
        float $valor,
        ?int $pedidoId = null,
        ?int $pagamentoAntecipadoId = null,
        ?int $usuarioId = null,
        ?string $descricao = null
    ): int {
        exigirEstruturaCaixa($conn);

        $tiposPermitidos = [
            CAIXA_MOVIMENTO_PAGAMENTO_PEDIDO,
            CAIXA_MOVIMENTO_PAGAMENTO_ANTECIPADO,
            CAIXA_MOVIMENTO_VENDA_PRAZO,
        ];

        $formasPermitidas = [
            PAGAMENTO_DINHEIRO,
            PAGAMENTO_PIX,
            PAGAMENTO_CARTAO,
            PAGAMENTO_PRAZO,
        ];

        if(!in_array($tipo, $tiposPermitidos, true)){
            throw new Exception('Tipo de movimento de caixa inválido');
        }

        if(!in_array($formaPagamento, $formasPermitidas, true)){
            throw new Exception('Forma do movimento de caixa inválida');
        }

        $valor = round($valor, 2);

        if($valor <= 0){
            throw new Exception('Valor do movimento de caixa deve ser maior que zero');
        }

        $descricao = trim((string)$descricao);

        if($descricao === ''){
            $descricao = null;
        }

        $stmt = $conn->prepare("\n            INSERT INTO caixa_movimentos (\n                caixa_id,\n                tipo,\n                forma_pagamento,\n                valor,\n                pedido_id,\n                pagamento_antecipado_id,\n                usuario_id,\n                descricao,\n                created_at\n            )\n            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            'issdiiis',
            $caixaId,
            $tipo,
            $formaPagamento,
            $valor,
            $pedidoId,
            $pagamentoAntecipadoId,
            $usuarioId,
            $descricao
        );

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $movimentoId = (int)$conn->insert_id;
        $stmt->close();

        return $movimentoId;
    }
}
