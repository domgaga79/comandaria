<?php

/*
|--------------------------------------------------------------------------
| TENANT CORE - Comandaria/Mesaria Express Multitenant
|--------------------------------------------------------------------------
| Responsável por:
| - resolver a empresa ativa;
| - consultar plano/limites;
| - bloquear excesso de mesas e pedidos mensais;
| - gerar numeração sequencial de pedidos por empresa.
|--------------------------------------------------------------------------
*/

if(!function_exists('tenantEmpresaIdSessao')){
    function tenantEmpresaIdSessao(): ?int {
        $empresaId = isset($_SESSION['empresa_id'])
            ? (int)$_SESSION['empresa_id']
            : 0;

        return $empresaId > 0 ? $empresaId : null;
    }
}

if(!function_exists('tenantResolverEmpresaId')){
    function tenantResolverEmpresaId(?int $empresaId = null): int {
        $empresaId = $empresaId !== null ? (int)$empresaId : (tenantEmpresaIdSessao() ?? 0);

        if($empresaId <= 0){
            throw new Exception('Empresa não identificada no contexto da requisição. Faça login novamente.');
        }

        return $empresaId;
    }
}

if(!function_exists('tenantPlanoEmpresa')){
    function tenantPlanoEmpresa(mysqli $conn, int $empresaId): array {
        $empresaId = tenantResolverEmpresaId($empresaId);

        $stmt = $conn->prepare("\n            SELECT\n                e.id AS empresa_id,\n                e.nome AS empresa_nome,\n                e.slug AS empresa_slug,\n                e.status AS empresa_status,\n                e.status_assinatura,\n                e.plano_id,\n                p.nome AS plano_nome,\n                p.limite_mesas,\n                p.limite_pedidos_mes\n            FROM empresas e\n            INNER JOIN planos p\n                ON p.id = e.plano_id\n            WHERE e.id = ?\n            AND e.status = 'ativa'\n            LIMIT 1\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('i', $empresaId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if(!$row){
            throw new Exception('Empresa inativa ou não encontrada.');
        }

        if(($row['status_assinatura'] ?? 'ativa') !== 'ativa'){
            throw new Exception('Assinatura suspensa ou cancelada.');
        }

        return [
            'empresa_id' => (int)$row['empresa_id'],
            'empresa_nome' => (string)$row['empresa_nome'],
            'empresa_slug' => (string)$row['empresa_slug'],
            'empresa_status' => (string)$row['empresa_status'],
            'status_assinatura' => (string)$row['status_assinatura'],
            'plano_id' => (int)$row['plano_id'],
            'plano_nome' => (string)$row['plano_nome'],
            'limite_mesas' => (int)$row['limite_mesas'],
            'limite_pedidos_mes' => (int)$row['limite_pedidos_mes']
        ];
    }
}

if(!function_exists('tenantPlanoPermiteUsuarios')){
    function tenantPlanoPermiteUsuarios(array $plano): bool {
        return strtoupper(trim((string)($plano['plano_nome'] ?? ''))) === 'PRO'
            && ($plano['status_assinatura'] ?? '') === 'ativa';
    }
}

if(!function_exists('tenantTravarEmpresaLimites')){
    function tenantTravarEmpresaLimites(mysqli $conn, int $empresaId): void {
        $empresaId = tenantResolverEmpresaId($empresaId);

        $stmt = $conn->prepare("\n            SELECT id\n            FROM empresas\n            WHERE id = ?\n            LIMIT 1\n            FOR UPDATE\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('i', $empresaId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if(!$row){
            throw new Exception('Empresa não encontrada para validar limites.');
        }
    }
}

if(!function_exists('tenantContarMesas')){
    function tenantContarMesas(mysqli $conn, int $empresaId): int {
        $empresaId = tenantResolverEmpresaId($empresaId);

        $stmt = $conn->prepare("\n            SELECT COUNT(*) AS total\n            FROM mesas_qr\n            WHERE empresa_id = ?\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('i', $empresaId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        return (int)($row['total'] ?? 0);
    }
}

if(!function_exists('tenantContarPedidosMesAtual')){
    function tenantContarPedidosMesAtual(mysqli $conn, int $empresaId): int {
        $empresaId = tenantResolverEmpresaId($empresaId);

        $stmt = $conn->prepare("\n            SELECT COUNT(*) AS total\n            FROM pedidos\n            WHERE empresa_id = ?\n            AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')\n            AND created_at < DATE_ADD(DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00'), INTERVAL 1 MONTH)\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('i', $empresaId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        return (int)($row['total'] ?? 0);
    }
}

if(!function_exists('tenantGarantirLimiteMesas')){
    function tenantGarantirLimiteMesas(mysqli $conn, int $empresaId, int $novasMesas = 1): array {
        $empresaId = tenantResolverEmpresaId($empresaId);
        $novasMesas = max(0, $novasMesas);

        tenantTravarEmpresaLimites($conn, $empresaId);

        $plano = tenantPlanoEmpresa($conn, $empresaId);
        $usadas = tenantContarMesas($conn, $empresaId);
        $limite = (int)$plano['limite_mesas'];

        if(($usadas + $novasMesas) > $limite){
            throw new Exception(
                'Limite de mesas atingido no plano ' . $plano['plano_nome'] .
                ': ' . $usadas . '/' . $limite . '. Faça upgrade para continuar.'
            );
        }

        return [
            'plano' => $plano,
            'usadas' => $usadas,
            'limite' => $limite,
            'disponiveis' => max(0, $limite - $usadas)
        ];
    }
}

if(!function_exists('tenantGarantirLimitePedidos')){
    function tenantGarantirLimitePedidos(mysqli $conn, int $empresaId): array {
        $empresaId = tenantResolverEmpresaId($empresaId);

        tenantTravarEmpresaLimites($conn, $empresaId);

        $plano = tenantPlanoEmpresa($conn, $empresaId);
        $usados = tenantContarPedidosMesAtual($conn, $empresaId);
        $limite = (int)$plano['limite_pedidos_mes'];

        if($usados >= $limite){
            throw new Exception(
                'Limite mensal de pedidos atingido no plano ' . $plano['plano_nome'] .
                ': ' . $usados . '/' . $limite . '. Faça upgrade para continuar.'
            );
        }

        return [
            'plano' => $plano,
            'usados' => $usados,
            'limite' => $limite,
            'disponiveis' => max(0, $limite - $usados)
        ];
    }
}

if(!function_exists('tenantGerarNumeroPedido')){
    function tenantGerarNumeroPedido(mysqli $conn, int $empresaId): int {
        $empresaId = tenantResolverEmpresaId($empresaId);

        $stmt = $conn->prepare("\n            SELECT ultimo_numero_pedido\n            FROM empresas\n            WHERE id = ?\n            LIMIT 1\n            FOR UPDATE\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('i', $empresaId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if(!$row){
            throw new Exception('Empresa não encontrada para gerar número de pedido.');
        }

        $novoNumero = ((int)($row['ultimo_numero_pedido'] ?? 0)) + 1;

        $stmt = $conn->prepare("\n            UPDATE empresas\n            SET ultimo_numero_pedido = ?\n            WHERE id = ?\n            LIMIT 1\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('ii', $novoNumero, $empresaId);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $stmt->close();

        return $novoNumero;
    }
}

if(!function_exists('tenantResumoUsoPlano')){
    function tenantResumoUsoPlano(mysqli $conn, int $empresaId): array {
        $empresaId = tenantResolverEmpresaId($empresaId);
        $plano = tenantPlanoEmpresa($conn, $empresaId);
        $mesas = tenantContarMesas($conn, $empresaId);
        $pedidos = tenantContarPedidosMesAtual($conn, $empresaId);

        $limiteMesas = max(1, (int)$plano['limite_mesas']);
        $limitePedidos = max(1, (int)$plano['limite_pedidos_mes']);

        return [
            'plano' => $plano,
            'mesas_usadas' => $mesas,
            'mesas_limite' => $limiteMesas,
            'mesas_percentual' => min(100, round(($mesas / $limiteMesas) * 100, 1)),
            'pedidos_usados' => $pedidos,
            'pedidos_limite' => $limitePedidos,
            'pedidos_percentual' => min(100, round(($pedidos / $limitePedidos) * 100, 1))
        ];
    }
}
