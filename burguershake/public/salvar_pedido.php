<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

/*
|--------------------------------------------------------------------------
| Endpoint JSON
|--------------------------------------------------------------------------
| Não imprimir warnings/fatais em HTML na resposta JSON.
|--------------------------------------------------------------------------
*/
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/auth.php';
requireRole(['atendente', 'admin']);

require __DIR__ . '/conexao.php';

require __DIR__ . '/../helpers/response.php';
require __DIR__ . '/../helpers/request.php';
require __DIR__ . '/../helpers/logger.php';
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../core/pedido_core.php';

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

$transacaoAberta = false;

/*
|--------------------------------------------------------------------------
| Busca e trava os itens atuais do pedido
|--------------------------------------------------------------------------
| A trava FOR UPDATE evita que duas edições concorrentes destruam o estado
| de produção da cozinha.
|--------------------------------------------------------------------------
*/
function carregarItensPersistidosDoPedido(
    mysqli $conn,
    int $pedidoId
): array {

    $stmt = $conn->prepare("
        SELECT
            id,
            pedido_id,
            produto_id,
            quantidade,
            quantidade_pronta
        FROM pedido_itens
        WHERE pedido_id = ?
        ORDER BY id ASC
        FOR UPDATE
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

    $res = $stmt->get_result();

    $itens = [];

    while($row = $res->fetch_assoc()){
        $itemId = (int)$row['id'];

        $itens[$itemId] = [
            'id' => $itemId,
            'pedido_id' => (int)$row['pedido_id'],
            'produto_id' => (int)$row['produto_id'],
            'quantidade' => (int)$row['quantidade'],
            'quantidade_pronta' => (int)$row['quantidade_pronta']
        ];
    }

    $stmt->close();

    return $itens;
}


/*
|--------------------------------------------------------------------------
| Atualiza somente a quantidade de item já enviado
|--------------------------------------------------------------------------
| Importante:
| - preserva id da linha;
| - preserva quantidade_pronta;
| - preserva preço, adicional, observação e ponto já congelados;
| - permite apenas aumentar quantidade, nunca reduzir o já salvo.
|--------------------------------------------------------------------------
*/
function aumentarQuantidadeItemPersistido(
    mysqli $conn,
    int $pedidoId,
    int $itemId,
    int $quantidadeNova
): void {

    $stmt = $conn->prepare("
        UPDATE pedido_itens
        SET
            quantidade = ?,
            updated_at = NOW()
        WHERE id = ?
        AND pedido_id = ?
        LIMIT 1
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "iii",
        $quantidadeNova,
        $itemId,
        $pedidoId
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    if($stmt->affected_rows <= 0){
        $stmt->close();
        throw new Exception('Não foi possível atualizar a quantidade do item.');
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Sincroniza itens sem apagar o estado da cozinha
|--------------------------------------------------------------------------
| Comportamento:
|
| 1. Item com item_id existente:
|    - mantém a mesma linha do banco;
|    - mantém quantidade_pronta;
|    - permite apenas aumento de quantidade.
|
| 2. Item sem item_id:
|    - é item novo;
|    - entra com quantidade_pronta = 0 via salvarItensPedido().
|
| 3. Itens antigos que não vierem no payload:
|    - não são apagados;
|    - continuam preservados no pedido.
|--------------------------------------------------------------------------
*/
function sincronizarItensPedidoPreservandoProducao(
    mysqli $conn,
    int $pedidoId,
    array $itens
): array {

    $persistidos = carregarItensPersistidosDoPedido(
        $conn,
        $pedidoId
    );

    $itemIdsRecebidos = [];
    $novosItens = [];

    $itensAtualizados = 0;
    $itensInseridos = 0;
    $houveNovaProducao = false;

    foreach($itens as $item){

        if(!is_array($item)){
            throw new Exception('Item inválido no pedido.');
        }

        $itemId = (int)(
            $item['item_id'] ??
            $item['id_item'] ??
            0
        );

        $produtoId = (int)(
            $item['produto_id'] ??
            $item['id'] ??
            0
        );

        $quantidadeNova = (int)(
            $item['qtd'] ??
            $item['quantidade'] ??
            0
        );

        if($produtoId <= 0){
            throw new Exception('Produto inválido no pedido.');
        }

        if($quantidadeNova <= 0){
            throw new Exception('Quantidade inválida no pedido.');
        }

        /*
        |--------------------------------------------------------------------------
        | Item já existente no banco
        |--------------------------------------------------------------------------
        */
        if($itemId > 0){

            if(isset($itemIdsRecebidos[$itemId])){
                throw new Exception('Item repetido no envio do pedido.');
            }

            $itemIdsRecebidos[$itemId] = true;

            if(!isset($persistidos[$itemId])){
                throw new Exception('Item enviado não pertence a este pedido.');
            }

            $persistido = $persistidos[$itemId];

            if((int)$persistido['produto_id'] !== $produtoId){
                throw new Exception('Produto do item enviado não confere com o pedido.');
            }

            $quantidadeAtual = (int)$persistido['quantidade'];

            /*
             * A quantidade já persistida não pode diminuir.
             * O atendente pode acrescentar, mas não desfazer o que já foi enviado.
             */
            if($quantidadeNova < $quantidadeAtual){
                throw new Exception('Não é permitido reduzir itens já enviados.');
            }

            if($quantidadeNova > $quantidadeAtual){

                aumentarQuantidadeItemPersistido(
                    $conn,
                    $pedidoId,
                    $itemId,
                    $quantidadeNova
                );

                $itensAtualizados++;
                $houveNovaProducao = true;
            }

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Item novo
        |--------------------------------------------------------------------------
        | Mantém o mesmo contrato consumido por salvarItensPedido().
        |--------------------------------------------------------------------------
        */
        $novosItens[] = [
            'id' => $produtoId,
            'produto_id' => $produtoId,
            'qtd' => $quantidadeNova,
            'quantidade' => $quantidadeNova,
            'adicional' => max(0, min(5, (int)($item['adicional'] ?? 0))),
            'obs' => trim((string)($item['obs'] ?? $item['observacao'] ?? '')),
            'observacao' => trim((string)($item['observacao'] ?? $item['obs'] ?? '')),
            'ponto' => trim((string)($item['ponto'] ?? ''))
        ];
    }

    if(!empty($novosItens)){

        $itensInseridos = salvarItensPedido(
            $conn,
            $pedidoId,
            $novosItens
        );

        if($itensInseridos <= 0){
            throw new Exception('Nenhum item novo válido foi salvo.');
        }

        $houveNovaProducao = true;
    }

    /*
     * Se não existe item persistido e também não entrou item novo,
     * a requisição não produziu um pedido válido.
     */
    if(empty($persistidos) && $itensInseridos <= 0){
        throw new Exception('Nenhum item válido foi salvo.');
    }

    return [
        'itens_atualizados' => $itensAtualizados,
        'itens_inseridos' => $itensInseridos,
        'itens_processados' => $itensAtualizados + $itensInseridos,
        'houve_nova_producao' => $houveNovaProducao
    ];
}


try {

    $data = bodyJson();

    /*
    |--------------------------------------------------------------------------
    | CONTRATO
    |--------------------------------------------------------------------------
    | Criar pedido por mesa:
    |
    | {
    |   "mesa": 1,
    |   "cliente": null,
    |   "itens": [...]
    | }
    |
    | Criar pedido por cliente:
    |
    | {
    |   "mesa": null,
    |   "cliente": "João",
    |   "itens": [...]
    | }
    |
    | Atualizar pedido:
    |
    | {
    |   "pedido_id": 10,
    |   "mesa": 1,
    |   "cliente": null,
    |   "itens": [
    |      {
    |        "item_id": 99,
    |        "id": 5,
    |        "qtd": 3
    |      },
    |      {
    |        "item_id": null,
    |        "id": 8,
    |        "qtd": 1
    |      }
    |   ]
    | }
    |--------------------------------------------------------------------------
    */

    if(!is_array($data)){
        erro('JSON inválido', 400);
    }

    if(!isset($data['itens']) || !is_array($data['itens'])){
        erro('Itens inválidos', 400);
    }

    $pedidoId = !empty($data['pedido_id'])
        ? (int)$data['pedido_id']
        : null;

    $mesa = !empty($data['mesa'])
        ? (int)$data['mesa']
        : null;

    $cliente = !empty($data['cliente'])
        ? trim((string)$data['cliente'])
        : null;

    $itens = $data['itens'];

    $usuarioId = $_SESSION['user_id'] ?? null;

    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÕES
    |--------------------------------------------------------------------------
    */

    if(!$mesa && !$cliente){
        erro('Informe uma mesa ou cliente', 400);
    }

    if($mesa && $cliente){
        erro('Informe mesa ou cliente, não ambos', 400);
    }

    if(empty($itens)){
        erro('Adicione pelo menos um item', 400);
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDA PEDIDO EXISTENTE
    |--------------------------------------------------------------------------
    */
    if($pedidoId){

        $statusAtual = buscarStatusPedido(
            $conn,
            $pedidoId
        );

        if(!$statusAtual){
            erro('Pedido não encontrado', 404);
        }

        if($statusAtual === STATUS_PAGO){
            erro('Pedido já está pago e não pode ser editado', 409);
        }

        if($statusAtual === STATUS_CANCELADO){
            erro('Pedido cancelado não pode ser editado', 409);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSACTION
    |--------------------------------------------------------------------------
    */
    $conn->begin_transaction();
    $transacaoAberta = true;

    /*
    |--------------------------------------------------------------------------
    | BUSCA OU CRIA PEDIDO
    |--------------------------------------------------------------------------
    */
    $pedido = buscarOuCriarPedido(
        $conn,
        $pedidoId,
        $mesa,
        $cliente,
        $usuarioId
    );

    $pedidoId = (int)$pedido['pedido_id'];

    $publicToken = $pedido['public_token'] ?? null;

    /*
    |--------------------------------------------------------------------------
    | SINCRONIZA SEM APAGAR ITENS PRONTOS
    |--------------------------------------------------------------------------
    */
    $resultadoSincronizacao = sincronizarItensPedidoPreservandoProducao(
        $conn,
        $pedidoId,
        $itens
    );

    /*
    |--------------------------------------------------------------------------
    | RECALCULA TOTAIS
    |--------------------------------------------------------------------------
    */
    $totais = recalcularPedido(
        $conn,
        $pedidoId
    );

    /*
    |--------------------------------------------------------------------------
    | STATUS OPERACIONAL
    |--------------------------------------------------------------------------
    | Só volta o pedido para pendente quando houve:
    | - item novo;
    | - aumento de quantidade em item já existente.
    |--------------------------------------------------------------------------
    */
    if(!empty($resultadoSincronizacao['houve_nova_producao'])){
        atualizarStatusPedido(
            $conn,
            $pedidoId,
            STATUS_PENDENTE
        );
    }

    $publicToken = garantirPublicTokenPedido(
        $conn,
        $pedidoId,
        $publicToken
    );

    $conn->commit();
    $transacaoAberta = false;

    sucesso([
        'pedido_id' => $pedidoId,
        'id' => $pedidoId,
        'numero' => $pedidoId,

        'created' => (bool)($pedido['created'] ?? false),

        'status' => !empty($resultadoSincronizacao['houve_nova_producao'])
            ? STATUS_PENDENTE
            : (buscarStatusPedido($conn, $pedidoId) ?: STATUS_PENDENTE),

        'imprimir' => !empty($resultadoSincronizacao['houve_nova_producao']),

        'public_token' => $publicToken,

        'itens_salvos' => (int)$resultadoSincronizacao['itens_processados'],
        'itens_inseridos' => (int)$resultadoSincronizacao['itens_inseridos'],
        'itens_atualizados' => (int)$resultadoSincronizacao['itens_atualizados'],

        'totais' => $totais
    ]);

} catch(Throwable $e){

    if(isset($conn) && $transacaoAberta){
        try {
            $conn->rollback();
        } catch(Throwable $rollbackError) {
            logErro($rollbackError);
        }
    }

    logErro($e);

    erro(
        $e->getMessage(),
        500
    );
}

if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
