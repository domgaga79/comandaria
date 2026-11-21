<?php
require __DIR__ . '/auth.php';
requireRole(['atendente','admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require __DIR__ . '/conexao.php';

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

try {

    $sql = "
        SELECT
            id,
            id AS pedido_id,
            id AS numero,
            cliente,
            mesa,
            status,
            subtotal,
            total_adicional,
            total,
            quantidade_itens,
            created_at,
            updated_at
        FROM pedidos
        WHERE (
            mesa IS NULL
            OR mesa = 0
        )
        AND cliente IS NOT NULL
        AND TRIM(cliente) <> ''
        AND status NOT IN ('pago','cancelado')
        ORDER BY
            COALESCE(updated_at, created_at) DESC,
            id DESC
    ";

    $res = $conn->query($sql);

    if(!$res){
        throw new Exception($conn->error);
    }

    $clientes = [];

    while($row = $res->fetch_assoc()){

        $clientes[] = [
            'id' => (int)$row['id'],
            'pedido_id' => (int)$row['pedido_id'],
            'numero' => (int)$row['numero'],

            'cliente' => $row['cliente'],
            'mesa' => $row['mesa'] !== null ? (int)$row['mesa'] : null,
            'status' => $row['status'],

            'subtotal' => (float)$row['subtotal'],
            'total_adicional' => (float)$row['total_adicional'],
            'total' => (float)$row['total'],
            'quantidade_itens' => (int)$row['quantidade_itens'],

            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    echo json_encode(
        $clientes,
        JSON_UNESCAPED_UNICODE
    );

} catch(Throwable $e){

    error_log('[ERRO listar_clientes_abertos.php] ' . $e->getMessage());

    http_response_code(500);

    echo json_encode(
        [
            'ok' => false,
            'erro' => $e->getMessage()
        ],
        JSON_UNESCAPED_UNICODE
    );
}

if(isset($conn) && $conn instanceof mysqli){
    $conn->close();
}
