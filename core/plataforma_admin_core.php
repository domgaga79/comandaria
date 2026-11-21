<?php

/*
|--------------------------------------------------------------------------
| PLATAFORMA ADMIN CORE - Bacuri Digital
|--------------------------------------------------------------------------
| Centraliza a validação do tenant administrador da plataforma.
| O painel global de empresas/planos somente deve ser acessado por:
| - usuário autenticado como admin;
| - empresa marcada como administradora da plataforma;
| - slug administrativo comandaria-de-bacuri-digital.
|--------------------------------------------------------------------------
*/

if(!function_exists('plataformaAdminSlug')){
    function plataformaAdminSlug(): string {
        return 'comandaria-de-bacuri-digital';
    }
}

if(!function_exists('plataformaAdminTelefone')){
    function plataformaAdminTelefone(): string {
        return '71993143374';
    }
}

if(!function_exists('plataformaNormalizarTelefone')){
    function plataformaNormalizarTelefone(?string $telefone): string {
        return preg_replace('/\D+/', '', (string)$telefone) ?: '';
    }
}

if(!function_exists('plataformaEhAdminPlataforma')){
    function plataformaEhAdminPlataforma(mysqli $conn): bool {
        if(!function_exists('authTipo') || !function_exists('authEmpresaId')){
            return false;
        }

        if((string)authTipo() !== 'admin'){
            return false;
        }

        $empresaId = (int)(authEmpresaId() ?? 0);

        if($empresaId <= 0){
            return false;
        }

        $slugAdmin = plataformaAdminSlug();

        $stmt = $conn->prepare("\n            SELECT id\n            FROM empresas\n            WHERE id = ?\n            AND slug = ?\n            AND is_admin_plataforma = 1\n            AND status = 'ativa'\n            AND status_assinatura = 'ativa'\n            LIMIT 1\n        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param('is', $empresaId, $slugAdmin);

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (bool)$row;
    }
}

if(!function_exists('plataformaRequireAdmin')){
    function plataformaRequireAdmin(mysqli $conn): void {
        try {
            if(plataformaEhAdminPlataforma($conn)){
                return;
            }
        } catch(Throwable $e){
            error_log('[PLATAFORMA ADMIN] ' . $e->getMessage());
        }

        $mensagem = 'Acesso restrito ao painel administrativo da Bacuri Digital.';

        if(function_exists('isJsonRequest') && isJsonRequest() && function_exists('authJsonError')){
            authJsonError($mensagem, 403);
        }

        if(function_exists('authHtmlDenied')){
            authHtmlDenied($mensagem);
        }

        http_response_code(403);
        exit($mensagem);
    }
}
