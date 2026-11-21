<?php
require_once __DIR__ . '/tenant_core.php';

function auditoria(
    mysqli $conn,
    string $acao,
    string $entidade,
    ?int $entidadeId = null,
    $antes = null,
    $depois = null
){

    if(session_status() === PHP_SESSION_NONE){
        session_start();
    }

    $usuarioId = $_SESSION['user_id'] ?? null;
    $empresaId = tenantResolverEmpresaId($_SESSION['empresa_id'] ?? null);

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $antesJson = $antes
        ? json_encode($antes, JSON_UNESCAPED_UNICODE)
        : null;

    $depoisJson = $depois
        ? json_encode($depois, JSON_UNESCAPED_UNICODE)
        : null;

    $stmt = $conn->prepare("
        INSERT INTO auditoria
        (
            empresa_id,
            usuario_id,
            acao,
            entidade,
            entidade_id,
            dados_antes,
            dados_depois,
            ip,
            user_agent
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    if(!$stmt){
        return false;
    }

    $stmt->bind_param(
        "iississss",
        $empresaId,
        $usuarioId,
        $acao,
        $entidade,
        $entidadeId,
        $antesJson,
        $depoisJson,
        $ip,
        $ua
    );

    $ok = $stmt->execute();

    $stmt->close();

    return $ok;
}