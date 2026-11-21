<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

/*
|--------------------------------------------------------------------------
| Endpoint JSON: nunca deve devolver HTML.
|--------------------------------------------------------------------------
*/
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

/*
|--------------------------------------------------------------------------
| Garante que auth.php responda JSON se a sessão expirar.
|--------------------------------------------------------------------------
*/
$_SERVER['HTTP_ACCEPT'] = 'application/json';

try {

    require __DIR__ . '/auth.php';
    requireRole(['atendente', 'admin']);

    require __DIR__ . '/conexao.php';

    if(method_exists($conn, 'set_charset')){
        $conn->set_charset('utf8mb4');
    }

    /*
    |--------------------------------------------------------------------------
    | Mesa ocupada = pedido ativo com mesa > 0.
    |--------------------------------------------------------------------------
    */
    $statusPago = 'pago';
    $statusCancelado = 'cancelado';

    $stmt = $conn->prepare("
        SELECT
            p.id AS pedido_id,
            p.id AS id,
            p.id AS numero,
            p.mesa,

            p.status,
            p.subtotal,
            p.total_adicional,
            p.total,
            p.quantidade_itens,

            p.created_at,
            p.updated_at,

            x.total_pedidos,
            x.primeiro_pedido_id,
            x.ultimo_pedido_id

        FROM pedidos p

        INNER JOIN (
            SELECT
                mesa,
                COUNT(*) AS total_pedidos,
                MIN(id) AS primeiro_pedido_id,
                MAX(id) AS ultimo_pedido_id
            FROM pedidos
            WHERE mesa IS NOT NULL
            AND mesa > 0
            AND status NOT IN (?, ?)
            GROUP BY mesa
        ) x
            ON x.ultimo_pedido_id = p.id

        ORDER BY
            p.mesa ASC
    ");

    if(!$stmt){
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "ss",
        $statusPago,
        $statusCancelado
    );

    if(!$stmt->execute()){
        throw new Exception($stmt->error);
    }

    $res = $stmt->get_result();

    $mesas = [];

    while($row = $res->fetch_assoc()){

        $mesas[] = [
            'mesa' => (int)$row['mesa'],

            'pedido_id' => (int)$row['pedido_id'],
            'id' => (int)$row['id'],
            'numero' => (int)$row['numero'],

            'primeiro_pedido_id' => (int)$row['primeiro_pedido_id'],
            'ultimo_pedido_id' => (int)$row['ultimo_pedido_id'],
            'total_pedidos' => (int)$row['total_pedidos'],

            'status' => $row['status'],

            'subtotal' => (float)$row['subtotal'],
            'total_adicional' => (float)$row['total_adicional'],
            'total' => (float)$row['total'],
            'quantidade_itens' => (int)$row['quantidade_itens'],

            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    $stmt->close();

    echo json_encode(
        $mesas,
        JSON_UNESCAPED_UNICODE
    );

} catch(Throwable $e){

    error_log('[ERRO listar_mesas.php] ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'erro' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
