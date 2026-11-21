<?php

function filaImpressao(
    mysqli $conn,
    string $tipo,
    string $entidade,
    int $entidadeId,
    array $payload
): bool {

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
    );

    $stmt = $conn->prepare("
        INSERT INTO fila_impressao
        (
            tipo,
            entidade,
            entidade_id,
            payload
        )
        VALUES
        (
            ?, ?, ?, ?
        )
    ");

    if(!$stmt){
        return false;
    }

    $stmt->bind_param(
        "ssis",
        $tipo,
        $entidade,
        $entidadeId,
        $json
    );

    $ok = $stmt->execute();

    $stmt->close();

    return $ok;
}