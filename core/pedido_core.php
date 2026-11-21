<?php

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/tenant_core.php';

/*
|--------------------------------------------------------------------------
| PEDIDO CORE
|--------------------------------------------------------------------------
| Fonte de verdade do domínio de pedidos.
|
| Base atual:
|
| pedidos.id = número operacional da comanda
| pedidos.public_token = token público para QR Code
| não existe comanda_id
| não existe status_item em pedido_itens
|
| Campos usados:
|
| pedidos:
| id, mesa, cliente, status, created_at, updated_at, usuario_id,
| subtotal, total_adicional, total, quantidade_itens, public_token
|
| pedido_itens:
| id, pedido_id, produto_id, quantidade, preco_unitario, observacao,
| ponto, quantidade_pronta, adicional, valor_adicional, created_at, updated_at
|
| produtos:
| id, nome, categoria_id, preco, ativo, tempo, permite_obs,
| adicional_permitido, valor_adicional, deleted_at
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Gera token público para QR Code
|--------------------------------------------------------------------------
*/
function gerarPublicToken(): string {

    return bin2hex(
        random_bytes(32)
    );
}


/*
|--------------------------------------------------------------------------
| Garante token público em pedido existente
|--------------------------------------------------------------------------
*/
function garantirPublicTokenPedido(
    mysqli $conn,
    int $pedidoId,
    ?string $tokenAtual = null,
    ?int $empresaId = null
): string {

    $empresaId = tenantResolverEmpresaId($empresaId);
    $tokenAtual = trim((string)$tokenAtual);

    if($tokenAtual !== ''){
        return $tokenAtual;
    }

    $novoToken = gerarPublicToken();

    $stmt = $conn->prepare("
        UPDATE pedidos
        SET
            public_token = ?,
            updated_at = NOW()
        WHERE id = ?
        AND empresa_id = ?
        AND (
            public_token IS NULL
            OR public_token = ''
        )
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "sii",
        $novoToken,
        $pedidoId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $stmt->close();

    return $novoToken;
}


/*
|--------------------------------------------------------------------------
| Verifica existência de tabela no banco atual
|--------------------------------------------------------------------------
*/
function pedidoCoreTabelaExiste(
    mysqli $conn,
    string $tabela
): bool {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "s",
        $tabela
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: [];

    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}


/*
|--------------------------------------------------------------------------
| Trava a abertura de pedido ativo por mesa
|--------------------------------------------------------------------------
| Objetivo:
| - impedir que duas requisições simultâneas sem pedido_id criem
|   dois pedidos ativos para a mesma mesa;
| - cobrir principalmente o fluxo do atendente e chamadas concorrentes.
|
| Estratégia:
| 1. Quando a tabela mesas_qr existe e a mesa possui linha própria,
|    usa SELECT ... FOR UPDATE. A trava dura até COMMIT/ROLLBACK.
| 2. Se a mesa ainda não existir em mesas_qr, usa advisory lock do MySQL
|    como fallback. Esse lock fica preso à conexão e é liberado ao fechar
|    a conexão ao fim da requisição.
|--------------------------------------------------------------------------
*/
function travarAberturaPedidoAtivoMesa(
    mysqli $conn,
    int $mesa,
    ?int $empresaId = null
): void {

    if($mesa <= 0){
        return;
    }

    $empresaId = tenantResolverEmpresaId($empresaId);

    if(pedidoCoreTabelaExiste($conn, 'mesas_qr')){

        $stmtMesa = $conn->prepare("
            SELECT id
            FROM mesas_qr
            WHERE empresa_id = ?
            AND mesa = ?
            LIMIT 1
            FOR UPDATE
        ");

        if(!$stmtMesa){
            throw new Exception($conn->error);
        }

        $stmtMesa->bind_param(
            "ii",
            $empresaId,
            $mesa
        );

        if(!$stmtMesa->execute()){
            throw new Exception($stmtMesa->error);
        }

        $resMesa = $stmtMesa->get_result();
        $mesaQr = $resMesa->fetch_assoc();

        $stmtMesa->close();

        if($mesaQr){
            return;
        }
    }

    $nomeTrava = 'comandaria_pedido_ativo_empresa_' . $empresaId . '_mesa_' . $mesa;

    $stmtLock = $conn->prepare("
        SELECT GET_LOCK(?, 10) AS trava_obtida
    ");

    if(!$stmtLock){
        throw new Exception($conn->error);
    }

    $stmtLock->bind_param(
        "s",
        $nomeTrava
    );

    if(!$stmtLock->execute()){
        throw new Exception($stmtLock->error);
    }

    $resLock = $stmtLock->get_result();
    $lock = $resLock->fetch_assoc() ?: [];

    $stmtLock->close();

    if((int)($lock['trava_obtida'] ?? 0) !== 1){
        throw new Exception('Não foi possível reservar a mesa para criação do pedido. Tente novamente.');
    }
}


/*
|--------------------------------------------------------------------------
| Busca pedido ativo ou cria novo
|--------------------------------------------------------------------------
| Regras:
|
| 1. Se vier pedido_id, tenta carregar esse pedido desde que não esteja pago
|    nem cancelado.
|
| 2. Se não vier pedido_id, mas vier mesa, tenta reaproveitar pedido ativo
|    daquela mesa.
|
| 3. Se nada for encontrado, cria um novo pedido.
|--------------------------------------------------------------------------
*/
function buscarOuCriarPedido(
    mysqli $conn,
    ?int $pedidoId,
    ?int $mesa,
    ?string $cliente,
    ?int $usuarioId,
    ?int $empresaId = null
): array {

    $empresaId = tenantResolverEmpresaId($empresaId);
    $id = null;
    $numeroPedido = null;
    $publicToken = null;

    /*
    |--------------------------------------------------------------------------
    | Reserva a referência de mesa antes de procurar/criar conta ativa
    |--------------------------------------------------------------------------
    */
    if(!$pedidoId && $mesa){
        travarAberturaPedidoAtivoMesa(
            $conn,
            $mesa,
            $empresaId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Busca por pedido_id informado dentro da empresa
    |--------------------------------------------------------------------------
    */
    if($pedidoId){

        $stmt = $conn->prepare("
            SELECT
                id,
                numero_pedido,
                public_token
            FROM pedidos
            WHERE id = ?
            AND empresa_id = ?
            AND status NOT IN (?, ?)
            LIMIT 1
        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $statusPago = STATUS_PAGO;
        $statusCancelado = STATUS_CANCELADO;

        $stmt->bind_param(
            "iiss",
            $pedidoId,
            $empresaId,
            $statusPago,
            $statusCancelado
        );

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $res = $stmt->get_result();

        if($row = $res->fetch_assoc()){
            $id = (int)$row['id'];
            $numeroPedido = (int)$row['numero_pedido'];
            $publicToken = $row['public_token'] ?? null;
        }

        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Se não veio pedido_id, reaproveita pedido ativo da mesma mesa e empresa
    |--------------------------------------------------------------------------
    */
    if(!$id && $mesa){

        $stmt = $conn->prepare("
            SELECT
                id,
                numero_pedido,
                public_token
            FROM pedidos
            WHERE empresa_id = ?
            AND mesa = ?
            AND status NOT IN (?, ?)
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $statusPago = STATUS_PAGO;
        $statusCancelado = STATUS_CANCELADO;

        $stmt->bind_param(
            "iiss",
            $empresaId,
            $mesa,
            $statusPago,
            $statusCancelado
        );

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $res = $stmt->get_result();

        if($row = $res->fetch_assoc()){
            $id = (int)$row['id'];
            $numeroPedido = (int)$row['numero_pedido'];
            $publicToken = $row['public_token'] ?? null;
        }

        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Atualiza pedido existente
    |--------------------------------------------------------------------------
    */
    if($id){

        $publicToken = garantirPublicTokenPedido(
            $conn,
            $id,
            $publicToken,
            $empresaId
        );

        $status = STATUS_PENDENTE;

        $stmt = $conn->prepare("
            UPDATE pedidos
            SET
                mesa = COALESCE(?, mesa),
                cliente = COALESCE(?, cliente),
                status = ?,
                usuario_id = COALESCE(?, usuario_id),
                updated_at = NOW()
            WHERE id = ?
            AND empresa_id = ?
        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            "issiii",
            $mesa,
            $cliente,
            $status,
            $usuarioId,
            $id,
            $empresaId
        );

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $stmt->close();

        return [
            'pedido_id' => $id,
            'numero' => $numeroPedido,
            'numero_pedido' => $numeroPedido,
            'empresa_id' => $empresaId,
            'created' => false,
            'public_token' => $publicToken
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Cria pedido novo respeitando limite mensal e numeração por empresa
    |--------------------------------------------------------------------------
    */
    tenantGarantirLimitePedidos($conn, $empresaId);

    $status = STATUS_PENDENTE;
    $publicToken = gerarPublicToken();
    $numeroPedido = tenantGerarNumeroPedido($conn, $empresaId);

    $stmt = $conn->prepare("
        INSERT INTO pedidos (
            empresa_id,
            numero_pedido,
            mesa,
            cliente,
            status,
            created_at,
            updated_at,
            usuario_id,
            subtotal,
            total_adicional,
            total,
            quantidade_itens,
            public_token
        )
        VALUES (
            ?, ?, ?, ?, ?,
            NOW(),
            NOW(),
            ?,
            0,
            0,
            0,
            0,
            ?
        )
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "iiissis",
        $empresaId,
        $numeroPedido,
        $mesa,
        $cliente,
        $status,
        $usuarioId,
        $publicToken
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $id = (int)$conn->insert_id;

    $stmt->close();

    return [
        'pedido_id' => $id,
        'numero' => $numeroPedido,
        'numero_pedido' => $numeroPedido,
        'empresa_id' => $empresaId,
        'created' => true,
        'public_token' => $publicToken
    ];
}


/*
|--------------------------------------------------------------------------
| Limpa itens do pedido
|--------------------------------------------------------------------------
*/
function limparItensPedido(
    mysqli $conn,
    int $pedidoId
): void {

    $stmt = $conn->prepare("
        DELETE FROM pedido_itens
        WHERE pedido_id = ?
        AND empresa_id = ?
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "ii",
        $pedidoId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Salva itens do pedido
|--------------------------------------------------------------------------
| Retorna a quantidade real de linhas gravadas.
|
| Regra do adicional:
|
| Frontend envia:
| adicional = 1 marcado
| adicional = 0 desmarcado
|
| Backend decide se pode aplicar:
| produtos.adicional_permitido = 1
| produtos.valor_adicional > 0
|--------------------------------------------------------------------------
*/
function salvarItensPedido(
    mysqli $conn,
    int $pedidoId,
    array $itens,
    ?int $empresaId = null
): int {

    $empresaId = tenantResolverEmpresaId($empresaId);

    $ids = array_unique(
        array_filter(
            array_map(function($item){
                return (int)($item['id'] ?? 0);
            }, $itens)
        )
    );

    if(empty($ids)){
        return 0;
    }

    $placeholders = implode(
        ',',
        array_fill(0, count($ids), '?')
    );

    $types = 'i' . str_repeat(
        'i',
        count($ids)
    );

    $stmt = $conn->prepare("
        SELECT
            id,
            nome,
            preco,
            ativo,
            adicional_permitido,
            valor_adicional
        FROM produtos
        WHERE empresa_id = ?
        AND id IN ($placeholders)
        AND ativo = 1
        AND deleted_at IS NULL
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $paramsProdutos = array_merge([$empresaId], array_values($ids));

    $stmt->bind_param(
        $types,
        ...$paramsProdutos
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();

    $produtos = [];

    while($produto = $res->fetch_assoc()){
        $produtos[(int)$produto['id']] = $produto;
    }

    $stmt->close();

    if(empty($produtos)){
        return 0;
    }

    $stmt = $conn->prepare("
        INSERT INTO pedido_itens (
            empresa_id,
            pedido_id,
            produto_id,
            quantidade,
            preco_unitario,
            observacao,
            ponto,
            quantidade_pronta,
            adicional,
            valor_adicional,
            created_at,
            updated_at
        )
        VALUES (
            ?, ?, ?, ?, ?,
            ?, ?,
            0,
            ?, ?,
            NOW(),
            NOW()
        )
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $totalSalvo = 0;

    foreach($itens as $item){

        $produtoId = (int)($item['id'] ?? 0);

        $quantidade = (int)(
            $item['qtd'] ??
            $item['quantidade'] ??
            0
        );

        if(
            $produtoId <= 0 ||
            $quantidade <= 0 ||
            !isset($produtos[$produtoId])
        ){
            continue;
        }

        $produto = $produtos[$produtoId];
        $precoUnitario = (float)$produto['preco'];

        $observacao = trim(
            $item['obs'] ??
            $item['observacao'] ??
            ''
        );

        $ponto = trim(
            $item['ponto'] ??
            ''
        );

        $adicionalSolicitado = max(0, min(5, (int)($item['adicional'] ?? 0)));
        $adicionalPermitido = (int)$produto['adicional_permitido'] === 1;
        $valorAdicionalProduto = (float)$produto['valor_adicional'];

        $qtdAdicionais = (
            $adicionalPermitido &&
            $valorAdicionalProduto > 0
        ) ? $adicionalSolicitado : 0;

        $valorAdicional = $qtdAdicionais > 0
            ? $valorAdicionalProduto
            : 0;

        $stmt->bind_param(
            "iiiidssid",
            $empresaId,
            $pedidoId,
            $produtoId,
            $quantidade,
            $precoUnitario,
            $observacao,
            $ponto,
            $qtdAdicionais,
            $valorAdicional
        );

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        if($stmt->affected_rows > 0){
            $totalSalvo++;
        }
    }

    $stmt->close();

    return $totalSalvo;
}


/*
|--------------------------------------------------------------------------
| Recalcula totais do pedido
|--------------------------------------------------------------------------
*/
function recalcularPedido(
    mysqli $conn,
    int $pedidoId,
    ?int $empresaId = null
): array {

    $empresaId = tenantResolverEmpresaId($empresaId);

    $stmt = $conn->prepare("
        SELECT
            quantidade,
            preco_unitario,
            adicional,
            valor_adicional
        FROM pedido_itens
        WHERE pedido_id = ?
        AND empresa_id = ?
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "ii",
        $pedidoId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();

    $subtotal = 0;
    $totalAdicional = 0;
    $quantidadeItens = 0;

    while($item = $res->fetch_assoc()){

        $quantidade = (int)$item['quantidade'];
        $precoUnitario = (float)$item['preco_unitario'];

        $subtotal += $precoUnitario * $quantidade;

        $qtdAdicionais = max(0, (int)$item['adicional']);

        if($qtdAdicionais > 0){
            $totalAdicional += (float)$item['valor_adicional'] * $qtdAdicionais * $quantidade;
        }

        $quantidadeItens += $quantidade;
    }

    $stmt->close();

    $subtotal = round($subtotal, 2);
    $totalAdicional = round($totalAdicional, 2);
    $total = round($subtotal + $totalAdicional, 2);

    $temServico10 = pedidoTemEstruturaServico10($conn);
    $servico10Ativo = false;
    $taxaServico10 = 0.00;

    if($temServico10){
        $stmtServico = $conn->prepare("
            SELECT COALESCE(servico_10_ativo, 0) AS servico_10_ativo
            FROM pedidos
            WHERE id = ?
            AND empresa_id = ?
            LIMIT 1
        ");

        if(!$stmtServico){
            throw new Exception($conn->error);
        }

        $stmtServico->bind_param(
            "ii",
            $pedidoId,
            $empresaId
        );

        if(!$stmtServico->execute()){
            throw new Exception($stmtServico->error);
        }

        $rowServico = $stmtServico->get_result()->fetch_assoc();
        $stmtServico->close();

        $servico10Ativo = !empty($rowServico['servico_10_ativo']);
        $taxaServico10 = $servico10Ativo
            ? calcularTaxaServico10($total)
            : 0.00;

        $stmt = $conn->prepare("
            UPDATE pedidos
            SET
                subtotal = ?,
                total_adicional = ?,
                total = ?,
                servico_10_ativo = ?,
                taxa_servico_10 = ?,
                quantidade_itens = ?,
                updated_at = NOW()
            WHERE id = ?
            AND empresa_id = ?
        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $servico10AtivoInt = $servico10Ativo ? 1 : 0;

        $stmt->bind_param(
            "dddidiii",
            $subtotal,
            $totalAdicional,
            $total,
            $servico10AtivoInt,
            $taxaServico10,
            $quantidadeItens,
            $pedidoId,
            $empresaId
        );
    } else {
        $stmt = $conn->prepare("
            UPDATE pedidos
            SET
                subtotal = ?,
                total_adicional = ?,
                total = ?,
                quantidade_itens = ?,
                updated_at = NOW()
            WHERE id = ?
            AND empresa_id = ?
        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            "dddiii",
            $subtotal,
            $totalAdicional,
            $total,
            $quantidadeItens,
            $pedidoId,
            $empresaId
        );
    }

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $stmt->close();

    $totalComServico = round($total + $taxaServico10, 2);

    return [
        'subtotal' => $subtotal,
        'total_adicional' => $totalAdicional,
        'total' => $total,
        'servico_10_ativo' => $servico10Ativo,
        'taxa_servico_10' => round($taxaServico10, 2),
        'total_com_servico' => $totalComServico,
        'total_cobravel' => $totalComServico,
        'quantidade_itens' => $quantidadeItens
    ];
}


/*
|--------------------------------------------------------------------------
| Atualiza status do pedido
|--------------------------------------------------------------------------
*/
function atualizarStatusPedido(
    mysqli $conn,
    int $pedidoId,
    string $status,
    ?int $empresaId = null
): void {

    $empresaId = tenantResolverEmpresaId($empresaId);
    validarStatusPedido($status);

    $stmt = $conn->prepare("
        UPDATE pedidos
        SET
            status = ?,
            updated_at = NOW()
        WHERE id = ?
        AND empresa_id = ?
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "sii",
        $status,
        $pedidoId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $stmt->close();

    $stmtCheck = $conn->prepare("
        SELECT id
        FROM pedidos
        WHERE id = ?
        AND empresa_id = ?
        LIMIT 1
    ");

    if(!$stmtCheck){
        throw new Exception($conn->error);
    }

    $stmtCheck->bind_param(
        "ii",
        $pedidoId,
        $empresaId
    );

    if(!$stmtCheck->execute()){
        throw new Exception($stmtCheck->error);
    }

    $res = $stmtCheck->get_result();

    if($res->num_rows === 0){
        $stmtCheck->close();
        throw new Exception('Pedido não encontrado');
    }

    $stmtCheck->close();
}


/*
|--------------------------------------------------------------------------
| Verifica se pedidos.total_antecipado existe
|--------------------------------------------------------------------------
*/
function pedidoTemColunaTotalAntecipado(
    mysqli $conn
): bool {

    $check = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'total_antecipado'");

    $temColuna = $check instanceof mysqli_result
        && $check->num_rows > 0;

    if($check instanceof mysqli_result){
        $check->free();
    }

    return $temColuna;
}


/*
|--------------------------------------------------------------------------
| Verifica estrutura da cobrança opcional de 10%
|--------------------------------------------------------------------------
*/
function pedidoTemColunaServico10Ativo(
    mysqli $conn
): bool {

    $check = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'servico_10_ativo'");

    $temColuna = $check instanceof mysqli_result
        && $check->num_rows > 0;

    if($check instanceof mysqli_result){
        $check->free();
    }

    return $temColuna;
}

function pedidoTemColunaTaxaServico10(
    mysqli $conn
): bool {

    $check = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'taxa_servico_10'");

    $temColuna = $check instanceof mysqli_result
        && $check->num_rows > 0;

    if($check instanceof mysqli_result){
        $check->free();
    }

    return $temColuna;
}

function pedidoTemEstruturaServico10(
    mysqli $conn
): bool {

    return pedidoTemColunaServico10Ativo($conn)
        && pedidoTemColunaTaxaServico10($conn);
}

function calcularTaxaServico10(
    float $totalBase
): float {

    return round(max(0, $totalBase) * 0.10, 2);
}

/*
|--------------------------------------------------------------------------
| Ativa/desativa 10% da conta e recalcula a cobrança
|--------------------------------------------------------------------------
*/
function atualizarServico10Pedido(
    mysqli $conn,
    int $pedidoId,
    bool $ativo,
    ?int $empresaId = null
): array {

    $empresaId = tenantResolverEmpresaId($empresaId);

    if(!pedidoTemEstruturaServico10($conn)){
        throw new Exception('Estrutura de cobrança de 10% indisponível no banco atual');
    }

    $resumoAntes = buscarResumoFinanceiroPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    if(in_array($resumoAntes['status'], [STATUS_PAGO, STATUS_CANCELADO], true)){
        throw new Exception('Só é possível alterar os 10% em contas abertas');
    }

    $taxaServico10 = $ativo
        ? calcularTaxaServico10((float)$resumoAntes['total'])
        : 0.00;

    $totalCobravelProjetado = round((float)$resumoAntes['total'] + $taxaServico10, 2);

    if((float)$resumoAntes['total_antecipado'] - $totalCobravelProjetado > 0.009){
        throw new Exception('Não é possível desativar os 10% porque o valor antecipado já supera o novo total da conta');
    }

    $stmt = $conn->prepare("
        UPDATE pedidos
        SET
            servico_10_ativo = ?,
            taxa_servico_10 = ?,
            updated_at = NOW()
        WHERE id = ?
        AND empresa_id = ?
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $ativoInt = $ativo ? 1 : 0;

    $stmt->bind_param(
        "idii",
        $ativoInt,
        $taxaServico10,
        $pedidoId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $stmt->close();

    $totais = recalcularPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    $financeiro = buscarResumoFinanceiroPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    return [
        'pedido_id' => $pedidoId,
        'ativo' => !empty($financeiro['servico_10_ativo']),
        'totais' => $totais,
        'financeiro' => $financeiro
    ];
}

/*
|--------------------------------------------------------------------------
| Busca resumo financeiro da mesa/pedido
|--------------------------------------------------------------------------
*/
function buscarResumoFinanceiroPedido(
    mysqli $conn,
    int $pedidoId,
    ?int $empresaId = null
): array {

    $empresaId = tenantResolverEmpresaId($empresaId);

    $temAntecipado = pedidoTemColunaTotalAntecipado($conn);
    $temServico10 = pedidoTemEstruturaServico10($conn);

    $selectAntecipado = $temAntecipado
        ? "COALESCE(total_antecipado, 0) AS total_antecipado"
        : "0 AS total_antecipado";

    $selectServico10Ativo = $temServico10
        ? "COALESCE(servico_10_ativo, 0) AS servico_10_ativo"
        : "0 AS servico_10_ativo";

    $selectTaxaServico10 = $temServico10
        ? "COALESCE(taxa_servico_10, 0) AS taxa_servico_10"
        : "0 AS taxa_servico_10";

    $stmt = $conn->prepare("
        SELECT
            id,
            mesa,
            cliente,
            status,
            total,
            {$selectAntecipado},
            {$selectServico10Ativo},
            {$selectTaxaServico10}
        FROM pedidos
        WHERE id = ?
        AND empresa_id = ?
        LIMIT 1
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "ii",
        $pedidoId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();
    $row = $res->fetch_assoc();

    $stmt->close();

    if(!$row){
        throw new Exception('Pedido não encontrado');
    }

    $total = round((float)($row['total'] ?? 0), 2);
    $servico10Ativo = !empty($row['servico_10_ativo']);
    $taxaServico10 = $servico10Ativo
        ? round((float)($row['taxa_servico_10'] ?? calcularTaxaServico10($total)), 2)
        : 0.00;
    $totalComServico = round($total + $taxaServico10, 2);
    $totalAntecipado = round((float)($row['total_antecipado'] ?? 0), 2);
    $saldo = round(max(0, $totalComServico - $totalAntecipado), 2);

    return [
        'pedido_id' => (int)$row['id'],
        'mesa' => $row['mesa'] !== null ? (int)$row['mesa'] : null,
        'cliente' => $row['cliente'] ?? null,
        'status' => $row['status'] ?? null,
        'total' => $total,
        'servico_10_ativo' => $servico10Ativo,
        'taxa_servico_10' => $taxaServico10,
        'total_com_servico' => $totalComServico,
        'total_cobravel' => $totalComServico,
        'total_antecipado' => $totalAntecipado,
        'saldo' => $saldo,
        'tem_total_antecipado' => $temAntecipado,
        'tem_servico_10' => $temServico10
    ];
}


/*
|--------------------------------------------------------------------------
| Registra pagamento antecipado para mesa aberta
|--------------------------------------------------------------------------
*/
function registrarPagamentoAntecipado(
    mysqli $conn,
    int $pedidoId,
    float $valor,
    string $formaPagamento,
    ?int $usuarioId = null,
    ?int $empresaId = null
): array {

    $empresaId = tenantResolverEmpresaId($empresaId);

    $formasPermitidas = [
        PAGAMENTO_DINHEIRO,
        PAGAMENTO_PIX,
        PAGAMENTO_CARTAO
    ];

    if(!in_array($formaPagamento, $formasPermitidas, true)){
        throw new Exception('Forma de pagamento antecipado inválida');
    }

    $valor = round($valor, 2);

    if($valor <= 0){
        throw new Exception('Informe um valor antecipado maior que zero');
    }

    $resumo = buscarResumoFinanceiroPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    if(!$resumo['tem_total_antecipado']){
        throw new Exception('Estrutura de pagamento antecipado indisponível no banco atual');
    }

    if(empty($resumo['mesa'])){
        throw new Exception('Pagamento antecipado está disponível apenas para mesas');
    }

    if(in_array($resumo['status'], [STATUS_PAGO, STATUS_CANCELADO], true)){
        throw new Exception('Não é possível antecipar pagamento de conta já encerrada');
    }

    $saldoAtual = (float)$resumo['saldo'];

    if($saldoAtual <= 0){
        throw new Exception('Esta mesa já está totalmente coberta por pagamentos antecipados');
    }

    if($valor - $saldoAtual > 0.009){
        throw new Exception('O valor antecipado não pode ser maior que o saldo atual da mesa');
    }

    $stmt = $conn->prepare("
        INSERT INTO pagamentos_antecipados (
            empresa_id,
            pedido_id,
            mesa,
            valor,
            forma_pagamento,
            usuario_id,
            created_at
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, NOW()
        )
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $mesa = (int)$resumo['mesa'];

    $stmt->bind_param(
        "iiidsi",
        $empresaId,
        $pedidoId,
        $mesa,
        $valor,
        $formaPagamento,
        $usuarioId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $pagamentoId = (int)$conn->insert_id;

    $stmt->close();

    $stmtUpdate = $conn->prepare("
        UPDATE pedidos
        SET
            total_antecipado = ROUND(COALESCE(total_antecipado, 0) + ?, 2),
            updated_at = NOW()
        WHERE id = ?
        AND empresa_id = ?
    ");

    if(!$stmtUpdate){
        throw new Exception($conn->error);
    }

    $stmtUpdate->bind_param(
        "dii",
        $valor,
        $pedidoId,
        $empresaId
    );

    if(!$stmtUpdate->execute()){
        throw new Exception($stmtUpdate->error);
    }

    $stmtUpdate->close();

    $resumoAtualizado = buscarResumoFinanceiroPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    return [
        'pagamento_id' => $pagamentoId,
        'pedido_id' => $pedidoId,
        'mesa' => $mesa,
        'valor' => $valor,
        'forma_pagamento' => $formaPagamento,
        'total' => $resumoAtualizado['total'],
        'servico_10_ativo' => $resumoAtualizado['servico_10_ativo'],
        'taxa_servico_10' => $resumoAtualizado['taxa_servico_10'],
        'total_com_servico' => $resumoAtualizado['total_com_servico'],
        'total_cobravel' => $resumoAtualizado['total_cobravel'],
        'total_antecipado' => $resumoAtualizado['total_antecipado'],
        'saldo' => $resumoAtualizado['saldo']
    ];
}


/*
|--------------------------------------------------------------------------
| Exclui um item individual da conta pelo caixa
|--------------------------------------------------------------------------
| Regras:
|
| - Apenas pedidos em aberto podem ter itens removidos.
| - O item é removido de pedido_itens e os totais são recalculados.
| - O status de produção do pedido também é recalculado, pois a exclusão de
|   um item pendente pode deixar os itens restantes totalmente prontos.
| - Se houver pagamento antecipado, a remoção não pode fazer o total da conta
|   ficar menor que o valor já antecipado, evitando crédito financeiro órfão.
|--------------------------------------------------------------------------
*/
function excluirItemConta(
    mysqli $conn,
    int $itemId,
    ?int $empresaId = null
): array {

    $empresaId = tenantResolverEmpresaId($empresaId);

    if($itemId <= 0){
        throw new Exception('Item inválido');
    }

    $stmt = $conn->prepare("
        SELECT
            pi.id,
            pi.pedido_id,
            pi.produto_id,
            pi.quantidade,
            pi.preco_unitario,
            pi.adicional,
            pi.valor_adicional,
            pr.nome AS produto_nome,
            p.status AS pedido_status
        FROM pedido_itens pi

        INNER JOIN pedidos p
            ON p.id = pi.pedido_id

        LEFT JOIN produtos pr
            ON pr.id = pi.produto_id

        WHERE pi.id = ?
        AND pi.empresa_id = ?
        AND p.empresa_id = ?
        LIMIT 1
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "iii",
        $itemId,
        $empresaId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();
    $item = $res->fetch_assoc();

    $stmt->close();

    if(!$item){
        throw new Exception('Item não encontrado');
    }

    $pedidoId = (int)$item['pedido_id'];
    $statusPedido = (string)($item['pedido_status'] ?? '');

    if($statusPedido === STATUS_PAGO){
        throw new Exception('Pedido já está pago e não pode ter itens removidos');
    }

    if($statusPedido === STATUS_CANCELADO){
        throw new Exception('Pedido cancelado não pode ter itens removidos');
    }

    $quantidade = max(0, (int)($item['quantidade'] ?? 0));
    $precoUnitario = (float)($item['preco_unitario'] ?? 0);
    $qtdAdicionais = max(0, (int)($item['adicional'] ?? 0));
    $valorAdicional = $qtdAdicionais > 0
        ? (float)($item['valor_adicional'] ?? 0)
        : 0;

    $subtotalItem = round(
        ($precoUnitario + ($qtdAdicionais * $valorAdicional)) * $quantidade,
        2
    );

    $resumoAntes = buscarResumoFinanceiroPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    $novoTotalEstimado = round(
        max(0, (float)$resumoAntes['total'] - $subtotalItem),
        2
    );

    $novaTaxaServico10Estimada = !empty($resumoAntes['servico_10_ativo'])
        ? calcularTaxaServico10($novoTotalEstimado)
        : 0.00;

    $novoTotalCobravelEstimado = round(
        $novoTotalEstimado + $novaTaxaServico10Estimada,
        2
    );

    if((float)$resumoAntes['total_antecipado'] - $novoTotalCobravelEstimado > 0.009){
        throw new Exception(
            'A exclusão deixaria o total da conta menor que o valor já antecipado. Ajuste ou estorne o adiantamento antes de remover este item.'
        );
    }

    $stmtDelete = $conn->prepare("
        DELETE FROM pedido_itens
        WHERE id = ?
        AND pedido_id = ?
        AND empresa_id = ?
        LIMIT 1
    ");

    if(!$stmtDelete){
        throw new Exception($conn->error);
    }

    $stmtDelete->bind_param(
        "iii",
        $itemId,
        $pedidoId,
        $empresaId
    );

    if(!$stmtDelete->execute()){
        throw new Exception($stmtDelete->error);
    }

    if($stmtDelete->affected_rows <= 0){
        $stmtDelete->close();
        throw new Exception('Não foi possível excluir o item da conta');
    }

    $stmtDelete->close();

    $totais = recalcularPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    $statusProducao = recalcularStatusProducaoPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    $resumoDepois = buscarResumoFinanceiroPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    if((float)$resumoDepois['total_antecipado'] - (float)$resumoDepois['total_cobravel'] > 0.009){
        throw new Exception(
            'A exclusão gerou saldo financeiro inconsistente com o pagamento antecipado'
        );
    }

    return [
        'pedido_id' => $pedidoId,
        'status_pedido' => $statusProducao,
        'item_excluido' => [
            'id' => (int)$item['id'],
            'item_id' => (int)$item['id'],
            'produto_id' => (int)$item['produto_id'],
            'nome' => $item['produto_nome'] ?: 'Item',
            'quantidade' => $quantidade,
            'subtotal' => $subtotalItem
        ],
        'totais' => $totais,
        'financeiro' => [
            'total' => (float)$resumoDepois['total'],
            'servico_10_ativo' => !empty($resumoDepois['servico_10_ativo']),
            'taxa_servico_10' => (float)$resumoDepois['taxa_servico_10'],
            'total_com_servico' => (float)$resumoDepois['total_com_servico'],
            'total_cobravel' => (float)$resumoDepois['total_cobravel'],
            'total_antecipado' => (float)$resumoDepois['total_antecipado'],
            'saldo' => (float)$resumoDepois['saldo']
        ]
    ];
}


/*
|--------------------------------------------------------------------------
| Cancela pedido
|--------------------------------------------------------------------------
*/
function cancelarPedido(
    mysqli $conn,
    int $pedidoId,
    ?int $empresaId = null
): void {

    $empresaId = tenantResolverEmpresaId($empresaId);

    atualizarStatusPedido(
        $conn,
        $pedidoId,
        STATUS_CANCELADO,
        $empresaId
    );
}


/*
|--------------------------------------------------------------------------
| Marca pedido como pago
|--------------------------------------------------------------------------
*/
function marcarPago(
    mysqli $conn,
    int $pedidoId,
    string $formaPagamento,
    ?int $empresaId = null
): void {

    $empresaId = tenantResolverEmpresaId($empresaId);

    $formasPermitidas = [
        PAGAMENTO_DINHEIRO,
        PAGAMENTO_PIX,
        PAGAMENTO_CARTAO,
        PAGAMENTO_PRAZO,
        PAGAMENTO_ANTECIPADO
    ];

    if(!in_array($formaPagamento, $formasPermitidas, true)){
        throw new Exception('Forma de pagamento inválida');
    }

    $resumoFinanceiro = buscarResumoFinanceiroPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    $saldoFinal = (float)($resumoFinanceiro['saldo'] ?? 0);

    if($formaPagamento === PAGAMENTO_ANTECIPADO && $saldoFinal > 0.009){
        throw new Exception('Ainda existe saldo a pagar; escolha a forma do pagamento restante');
    }

    if($formaPagamento !== PAGAMENTO_ANTECIPADO && $saldoFinal <= 0.009){
        throw new Exception('A mesa já foi totalmente coberta por pagamentos antecipados; feche como antecipado');
    }

    atualizarStatusPedido(
        $conn,
        $pedidoId,
        STATUS_PAGO,
        $empresaId
    );

    $prazoStatus = $formaPagamento === PAGAMENTO_PRAZO
        ? PRAZO_STATUS_ABERTO
        : null;

    $stmtPagamento = $conn->prepare("
        UPDATE pedidos
        SET
            forma_pagamento = ?,
            prazo_status = ?,
            prazo_quitado_em = NULL,
            updated_at = NOW()
        WHERE id = ?
        AND empresa_id = ?
    ");

    if(!$stmtPagamento){
        throw new Exception($conn->error);
    }

    $stmtPagamento->bind_param(
        "ssii",
        $formaPagamento,
        $prazoStatus,
        $pedidoId,
        $empresaId
    );

    if(!$stmtPagamento->execute()){
        throw new Exception($stmtPagamento->error);
    }

    $stmtPagamento->close();
}


/*
|--------------------------------------------------------------------------
| Reabre pedido
|--------------------------------------------------------------------------
*/
function reabrirPedido(
    mysqli $conn,
    int $pedidoId,
    ?int $empresaId = null
): void {

    $empresaId = tenantResolverEmpresaId($empresaId);

    atualizarStatusPedido(
        $conn,
        $pedidoId,
        STATUS_PENDENTE,
        $empresaId
    );
}


/*
|--------------------------------------------------------------------------
| Produz uma unidade de um item
|--------------------------------------------------------------------------
*/
function produzirItem(
    mysqli $conn,
    int $itemId,
    ?int $empresaId = null
): array {

    $empresaId = tenantResolverEmpresaId($empresaId);

    $stmt = $conn->prepare("
        UPDATE pedido_itens
        SET
            quantidade_pronta = quantidade_pronta + 1,
            updated_at = NOW()
        WHERE id = ?
        AND empresa_id = ?
        AND quantidade_pronta < quantidade
        LIMIT 1
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "ii",
        $itemId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    if($stmt->affected_rows <= 0){

        $stmt->close();

        throw new Exception(
            'Item já está pronto ou não foi encontrado'
        );
    }

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | Busca item atualizado
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare("
        SELECT
            id,
            pedido_id,
            produto_id,
            quantidade,
            quantidade_pronta
        FROM pedido_itens
        WHERE id = ?
        AND empresa_id = ?
        LIMIT 1
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "ii",
        $itemId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();

    $item = $res->fetch_assoc();

    $stmt->close();

    if(!$item){
        throw new Exception('Item não encontrado após atualização');
    }

    $pedidoId = (int)$item['pedido_id'];

    $statusPedido = recalcularStatusProducaoPedido(
        $conn,
        $pedidoId,
        $empresaId
    );

    return [
        'id' => (int)$item['id'],
        'item_id' => (int)$item['id'],
        'pedido_id' => $pedidoId,
        'produto_id' => (int)$item['produto_id'],
        'quantidade' => (int)$item['quantidade'],
        'qtd' => (int)$item['quantidade'],
        'quantidade_pronta' => (int)$item['quantidade_pronta'],
        'qtd_pronta' => (int)$item['quantidade_pronta'],
        'status' => ((int)$item['quantidade_pronta'] >= (int)$item['quantidade'])
            ? STATUS_PRONTO
            : STATUS_PENDENTE,
        'status_pedido' => $statusPedido
    ];
}


/*
|--------------------------------------------------------------------------
| Recalcula status de produção do pedido
|--------------------------------------------------------------------------
*/
function recalcularStatusProducaoPedido(
    mysqli $conn,
    int $pedidoId,
    ?int $empresaId = null
): string {

    $empresaId = tenantResolverEmpresaId($empresaId);

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_itens,
            SUM(
                CASE
                    WHEN quantidade_pronta >= quantidade
                    THEN 1
                    ELSE 0
                END
            ) AS itens_prontos
        FROM pedido_itens
        WHERE pedido_id = ?
        AND empresa_id = ?
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "ii",
        $pedidoId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();

    $row = $res->fetch_assoc() ?: [];

    $stmt->close();

    $totalItens = (int)($row['total_itens'] ?? 0);

    $itensProntos = (int)($row['itens_prontos'] ?? 0);

    $status = (
        $totalItens > 0 &&
        $itensProntos >= $totalItens
    )
        ? STATUS_PRONTO
        : STATUS_PENDENTE;

    $statusPago = STATUS_PAGO;
    $statusCancelado = STATUS_CANCELADO;

    $stmt = $conn->prepare("
        UPDATE pedidos
        SET
            status = ?,
            updated_at = NOW()
        WHERE id = ?
        AND empresa_id = ?
        AND status NOT IN (?, ?)
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "siiss",
        $status,
        $pedidoId,
        $empresaId,
        $statusPago,
        $statusCancelado
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $stmt->close();

    return $status;
}


/*
|--------------------------------------------------------------------------
| Marca todos os itens do pedido como prontos
|--------------------------------------------------------------------------
| Usado quando a ação manual "pronto" for permitida.
|--------------------------------------------------------------------------
*/
function marcarTodosItensProntos(
    mysqli $conn,
    int $pedidoId,
    ?int $empresaId = null
): void {

    $empresaId = tenantResolverEmpresaId($empresaId);

    $stmt = $conn->prepare("
        UPDATE pedido_itens
        SET
            quantidade_pronta = quantidade,
            updated_at = NOW()
        WHERE pedido_id = ?
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "i",
        $pedidoId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $stmt->close();

    atualizarStatusPedido(
        $conn,
        $pedidoId,
        STATUS_PRONTO,
        $empresaId
    );
}


/*
|--------------------------------------------------------------------------
| Busca status atual do pedido
|--------------------------------------------------------------------------
*/
function buscarStatusPedido(
    mysqli $conn,
    int $pedidoId,
    ?int $empresaId = null
): ?string {

    $empresaId = tenantResolverEmpresaId($empresaId);

    $stmt = $conn->prepare("
        SELECT status
        FROM pedidos
        WHERE id = ?
        AND empresa_id = ?
        LIMIT 1
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "ii",
        $pedidoId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    return $row['status'] ?? null;
}


/*
|--------------------------------------------------------------------------
| Verifica se pedido existe
|--------------------------------------------------------------------------
*/
function pedidoExiste(
    mysqli $conn,
    int $pedidoId,
    ?int $empresaId = null
): bool {

    $empresaId = tenantResolverEmpresaId($empresaId);

    $stmt = $conn->prepare("
        SELECT id
        FROM pedidos
        WHERE id = ?
        AND empresa_id = ?
        LIMIT 1
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "ii",
        $pedidoId,
        $empresaId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();
    $existe = $res->num_rows > 0;
    $stmt->close();

    return $existe;
}


/*
|--------------------------------------------------------------------------
| Valida status permitido pelo banco
|--------------------------------------------------------------------------
*/
function validarStatusPedido(
    string $status
): void {

    $permitidos = [
        STATUS_PENDENTE,
        STATUS_PAGO,
        STATUS_PRONTO,
        STATUS_CANCELADO
    ];

    if(!in_array($status, $permitidos, true)){
        throw new Exception('Status inválido');
    }
}
