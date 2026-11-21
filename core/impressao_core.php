<?php
require_once __DIR__ . '/tenant_core.php';

function filaImpressao(
    mysqli $conn,
    string $tipo,
    string $entidade,
    int $entidadeId,
    array $payload,
    ?int $empresaId = null
): bool {

    $empresaId = tenantResolverEmpresaId($empresaId);

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
    );

    $stmt = $conn->prepare("
        INSERT INTO fila_impressao
        (
            empresa_id,
            tipo,
            entidade,
            entidade_id,
            payload
        )
        VALUES
        (
            ?, ?, ?, ?, ?
        )
    ");

    if(!$stmt){
        return false;
    }

    $stmt->bind_param(
        "issis",
        $empresaId,
        $tipo,
        $entidade,
        $entidadeId,
        $json
    );

    $ok = $stmt->execute();

    $stmt->close();

    return $ok;
}