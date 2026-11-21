<?php

/*
|--------------------------------------------------------------------------
| MESARIA EXPRESS - MESA QR CORE
|--------------------------------------------------------------------------
| Funções de apoio para QR Code fixo por mesa.
|
| Tabela esperada:
| mesas_qr: id, mesa, token, ativo, created_at, updated_at
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/constants.php';

if(!function_exists('mesariaGerarToken')){
    function mesariaGerarToken(): string {
        return bin2hex(random_bytes(32));
    }
}

if(!function_exists('mesariaTabelaExiste')){
    function mesariaTabelaExiste(mysqli $conn, string $tabela): bool {

        $stmt = $conn->prepare("\n            SELECT COUNT(*) AS total\n            FROM information_schema.TABLES\n            WHERE TABLE_SCHEMA = DATABASE()\n            AND TABLE_NAME = ?\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('s', $tabela);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: [];
        $stmt->close();

        return (int)($row['total'] ?? 0) > 0;
    }
}

if(!function_exists('mesariaColunaExiste')){
    function mesariaColunaExiste(mysqli $conn, string $tabela, string $coluna): bool {

        $stmt = $conn->prepare("\n            SELECT COUNT(*) AS total\n            FROM information_schema.COLUMNS\n            WHERE TABLE_SCHEMA = DATABASE()\n            AND TABLE_NAME = ?\n            AND COLUMN_NAME = ?\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('ss', $tabela, $coluna);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $res = $stmt->get_result();
        $row = $res->fetch_assoc() ?: [];
        $stmt->close();

        return (int)($row['total'] ?? 0) > 0;
    }
}

if(!function_exists('mesariaGarantirTabelaQr')){
    function mesariaGarantirTabelaQr(mysqli $conn): void {
        if(!mesariaTabelaExiste($conn, 'mesas_qr')){
            throw new Exception('Tabela mesas_qr não encontrada no banco atual.');
        }
    }
}

if(!function_exists('mesariaValidarMesaToken')){
    function mesariaValidarMesaToken(
        mysqli $conn,
        int $mesa,
        string $token,
        bool $forUpdate = false
    ): array {

        mesariaGarantirTabelaQr($conn);

        $token = trim($token);

        if($mesa <= 0){
            throw new Exception('Mesa inválida.');
        }

        if($token === ''){
            throw new Exception('Token da mesa inválido.');
        }

        $sql = "\n            SELECT\n                id,\n                mesa,\n                token,\n                ativo,\n                created_at,\n                updated_at\n            FROM mesas_qr\n            WHERE mesa = ?\n            AND token = ?\n            AND ativo = 1\n            LIMIT 1\n        ";

        if($forUpdate){
            $sql .= " FOR UPDATE";
        }

        $stmt = $conn->prepare($sql);

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('is', $mesa, $token);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if(!$row){
            throw new Exception('QR Code inválido, inativo ou não encontrado.');
        }

        return [
            'id' => (int)$row['id'],
            'mesa' => (int)$row['mesa'],
            'token' => (string)$row['token'],
            'ativo' => (int)$row['ativo'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
}

if(!function_exists('mesariaBuscarQrPorMesa')){
    function mesariaBuscarQrPorMesa(mysqli $conn, int $mesa): ?array {

        mesariaGarantirTabelaQr($conn);

        $stmt = $conn->prepare("\n            SELECT id, mesa, token, ativo, created_at, updated_at\n            FROM mesas_qr\n            WHERE mesa = ?\n            LIMIT 1\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('i', $mesa);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if(!$row){
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'mesa' => (int)$row['mesa'],
            'token' => (string)$row['token'],
            'ativo' => (int)$row['ativo'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
}

if(!function_exists('mesariaCriarQrMesaSeNaoExiste')){
    function mesariaCriarQrMesaSeNaoExiste(mysqli $conn, int $mesa): array {

        if($mesa <= 0){
            throw new Exception('Mesa inválida.');
        }

        $existente = mesariaBuscarQrPorMesa($conn, $mesa);

        if($existente){
            return $existente;
        }

        $token = mesariaGerarToken();

        $stmt = $conn->prepare("\n            INSERT INTO mesas_qr (mesa, token, ativo, created_at, updated_at)\n            VALUES (?, ?, 1, NOW(), NOW())\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('is', $mesa, $token);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $id = (int)$conn->insert_id;
        $stmt->close();

        return [
            'id' => $id,
            'mesa' => $mesa,
            'token' => $token,
            'ativo' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
}

if(!function_exists('mesariaListarMesasQr')){
    function mesariaListarMesasQr(mysqli $conn): array {

        mesariaGarantirTabelaQr($conn);

        $res = $conn->query("\n            SELECT id, mesa, token, ativo, created_at, updated_at\n            FROM mesas_qr\n            ORDER BY mesa ASC\n        ");

        if(!$res){
            throw new Exception($conn->error);
        }

        $mesas = [];

        while($row = $res->fetch_assoc()){
            $mesas[] = [
                'id' => (int)$row['id'],
                'mesa' => (int)$row['mesa'],
                'token' => (string)$row['token'],
                'ativo' => (int)$row['ativo'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }

        return $mesas;
    }
}

if(!function_exists('mesariaBuscarPedidoAbertoMesa')){
    function mesariaBuscarPedidoAbertoMesa(mysqli $conn, int $mesa): ?array {

        $statusPago = STATUS_PAGO;
        $statusCancelado = STATUS_CANCELADO;

        $stmt = $conn->prepare("\n            SELECT\n                id,\n                mesa,\n                cliente,\n                status,\n                public_token,\n                subtotal,\n                total_adicional,\n                total,\n                quantidade_itens,\n                created_at,\n                updated_at\n            FROM pedidos\n            WHERE mesa = ?\n            AND status NOT IN (?, ?)\n            ORDER BY id DESC\n            LIMIT 1\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('iss', $mesa, $statusPago, $statusCancelado);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if(!$row){
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'pedido_id' => (int)$row['id'],
            'mesa' => $row['mesa'] !== null ? (int)$row['mesa'] : null,
            'cliente' => $row['cliente'],
            'status' => $row['status'],
            'public_token' => $row['public_token'],
            'subtotal' => (float)$row['subtotal'],
            'total_adicional' => (float)$row['total_adicional'],
            'total' => (float)$row['total'],
            'quantidade_itens' => (int)$row['quantidade_itens'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
}

if(!function_exists('mesariaMarcarOrigemPedido')){
    function mesariaMarcarOrigemPedido(mysqli $conn, int $pedidoId, string $origem): void {

        if($pedidoId <= 0){
            return;
        }

        if(!in_array($origem, ['atendimento', 'cliente_qr'], true)){
            return;
        }

        if(!mesariaColunaExiste($conn, 'pedidos', 'origem')){
            return;
        }

        $stmt = $conn->prepare("\n            UPDATE pedidos\n            SET origem = ?, updated_at = NOW()\n            WHERE id = ?\n            LIMIT 1\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('si', $origem, $pedidoId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $stmt->close();
    }
}

if(!function_exists('mesariaUrlBasePublica')){
    function mesariaUrlBasePublica(): string {

        $https = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );

        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $dir = rtrim($dir, '/');

        if($dir === '' || $dir === '.'){
            $dir = '';
        }

        return $scheme . '://' . $host . $dir;
    }
}
