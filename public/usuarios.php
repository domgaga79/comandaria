<?php
require 'auth.php';
requireRole(['admin']);

require __DIR__ . '/conexao.php';
require __DIR__ . '/../core/tenant_core.php';

$empresaId = tenantResolverEmpresaId(authEmpresaId());

try {
    $acessoPro = tenantPlanoPermiteUsuarios(tenantPlanoEmpresa($conn, $empresaId));
} catch(Throwable $e){
    error_log('[USUARIOS] ' . $e->getMessage());
    $acessoPro = false;
}

if(!$acessoPro){
    authHtmlDenied('O gerenciamento de usuários está disponível para empresas com plano Pro ativo.');
}

$_SESSION['csrf_usuarios'] ??= bin2hex(random_bytes(32));
$csrfUsuarios = $_SESSION['csrf_usuarios'];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $tokenEnviado = $_POST['csrf'] ?? '';
    if(!is_string($tokenEnviado) || !hash_equals($csrfUsuarios, $tokenEnviado)){
        authHtmlDenied('Sessão inválida. Reabra a página de usuários.');
    }
}

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectUsuarios(string $msg = '', string $alerta = 'ok'): void {

    $url = 'usuarios.php';

    if($msg !== ''){
        $url .= '?msg=' . urlencode($msg) . '&alerta=' . urlencode($alerta);
    }

    header("Location: {$url}");
    exit;
}

function renderAcaoStatus(array $usuario, string $csrf): void {
    $ativo = $usuario['status'] === 'ativo';
    $acao = $ativo ? 'bloquear' : 'ativar';
    ?>
    <form method="post" class="d-inline" <?php if($ativo): ?>onsubmit="return confirm('Bloquear este usuário?')"<?php endif; ?>>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="acao" value="<?= h($acao) ?>">
        <input type="hidden" name="id" value="<?= (int)$usuario['id'] ?>">
        <button type="submit" class="btn btn-sm btn-outline-<?= $ativo ? 'warning' : 'success' ?>">
            <?= $ativo ? 'Bloquear' : 'Ativar' ?>
        </button>
    </form>
    <?php
}

$tiposPermitidos = [
    'admin',
    'atendente',
    'cozinha',
    'caixa'
];

$statusPermitidos = [
    'ativo',
    'inativo'
];

/*
|--------------------------------------------------------------------------
| ATIVAÇÃO E BLOQUEIO
|--------------------------------------------------------------------------
*/

try {

    $acao = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['acao'] ?? '') : '';
    $id = (int)($_POST['id'] ?? 0);

    if($acao && $id > 0){

        if(
            $id === (int)($_SESSION['user_id'] ?? 0) &&
            $acao === 'bloquear'
        ){
            redirectUsuarios(
                'Você não pode bloquear seu próprio usuário',
                'erro'
            );
        }

        if($acao === 'ativar'){

            $stmt = $conn->prepare("
                UPDATE usuarios
                SET status = 'ativo'
                WHERE id = ?
                AND empresa_id = ?
            ");

            if(!$stmt){
                throw new Exception($conn->error);
            }

            $stmt->bind_param("ii", $id, $empresaId);

            if(!$stmt->execute()){
                throw new Exception($stmt->error);
            }

            $stmt->close();

            redirectUsuarios('Usuário ativado com sucesso');
        }

        if($acao === 'bloquear'){

            $stmt = $conn->prepare("
                UPDATE usuarios
                SET status = 'inativo'
                WHERE id = ?
                AND empresa_id = ?
            ");

            if(!$stmt){
                throw new Exception($conn->error);
            }

            $stmt->bind_param("ii", $id, $empresaId);

            if(!$stmt->execute()){
                throw new Exception($stmt->error);
            }

            $stmt->close();

            redirectUsuarios('Usuário bloqueado com sucesso');
        }
    }

} catch(Throwable $e){

    error_log('[USUARIOS] ' . $e->getMessage());
    redirectUsuarios('Não foi possível salvar a alteração do usuário.', 'erro');
}

/*
|--------------------------------------------------------------------------
| INSERT / UPDATE
|--------------------------------------------------------------------------
*/

try {

    if($_SERVER['REQUEST_METHOD'] === 'POST'){

        $id = (int)($_POST['id'] ?? 0);

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = (string)($_POST['senha'] ?? '');

        $tipo = trim($_POST['tipo'] ?? '');
        $status = trim($_POST['status'] ?? 'ativo');

        if($nome === ''){
            redirectUsuarios('Informe o nome do usuário', 'erro');
        }

        if($email === ''){
            redirectUsuarios('Informe o e-mail do usuário', 'erro');
        }

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            redirectUsuarios('E-mail inválido', 'erro');
        }

        if(!in_array($tipo, $tiposPermitidos, true)){
            redirectUsuarios('Tipo de usuário inválido', 'erro');
        }

        if(!in_array($status, $statusPermitidos, true)){
            redirectUsuarios('Status inválido', 'erro');
        }

        /*
        |--------------------------------------------------------------------------
        | Verifica e-mail duplicado
        |--------------------------------------------------------------------------
        */

        $stmtDuplicado = $conn->prepare("
            SELECT id
            FROM usuarios
            WHERE email = ?
            AND id != ?
            LIMIT 1
        ");

        if(!$stmtDuplicado){
            throw new Exception($conn->error);
        }

        $stmtDuplicado->bind_param(
            "si",
            $email,
            $id
        );

        if(!$stmtDuplicado->execute()){
            throw new Exception($stmtDuplicado->error);
        }

        $resDuplicado = $stmtDuplicado->get_result();

        if($resDuplicado->num_rows > 0){

            $stmtDuplicado->close();

            redirectUsuarios(
                'Já existe um usuário com este e-mail',
                'erro'
            );
        }

        $stmtDuplicado->close();

        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if($id > 0){

            if(
                $id === (int)($_SESSION['user_id'] ?? 0) &&
                $status !== 'ativo'
            ){
                redirectUsuarios(
                    'Você não pode inativar seu próprio usuário',
                    'erro'
                );
            }

            if($senha !== ''){

                if(strlen($senha) < 4){
                    redirectUsuarios(
                        'A senha deve ter pelo menos 4 caracteres',
                        'erro'
                    );
                }

                $hash = password_hash(
                    $senha,
                    PASSWORD_DEFAULT
                );

                /*
                 * Por segurança, senha_pura não armazena senha real.
                 */
                $senhaPura = '';

                $stmt = $conn->prepare("
                    UPDATE usuarios
                    SET
                        nome = ?,
                        email = ?,
                        senha = ?,
                        tipo = ?,
                        status = ?,
                        senha_pura = ?
                    WHERE id = ?
                    AND empresa_id = ?
                ");

                if(!$stmt){
                    throw new Exception($conn->error);
                }

                $stmt->bind_param(
                    "ssssssii",
                    $nome,
                    $email,
                    $hash,
                    $tipo,
                    $status,
                    $senhaPura,
                    $id,
                    $empresaId
                );

            } else {

                $stmt = $conn->prepare("
                    UPDATE usuarios
                    SET
                        nome = ?,
                        email = ?,
                        tipo = ?,
                        status = ?
                    WHERE id = ?
                    AND empresa_id = ?
                ");

                if(!$stmt){
                    throw new Exception($conn->error);
                }

                $stmt->bind_param(
                    "ssssii",
                    $nome,
                    $email,
                    $tipo,
                    $status,
                    $id,
                    $empresaId
                );
            }

            if(!$stmt->execute()){
                throw new Exception($stmt->error);
            }

            $stmt->close();

            redirectUsuarios('Usuário atualizado com sucesso');
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT
        |--------------------------------------------------------------------------
        */

        if($senha === ''){
            redirectUsuarios(
                'Informe uma senha para o novo usuário',
                'erro'
            );
        }

        if(strlen($senha) < 4){
            redirectUsuarios(
                'A senha deve ter pelo menos 4 caracteres',
                'erro'
            );
        }

        $hash = password_hash(
            $senha,
            PASSWORD_DEFAULT
        );

        /*
         * Por segurança, senha_pura fica vazia.
         */
        $senhaPura = '';

        $stmt = $conn->prepare("
            INSERT INTO usuarios (
                empresa_id,
                nome,
                email,
                senha,
                tipo,
                status,
                senha_pura
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?
            )
        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            "issssss",
            $empresaId,
            $nome,
            $email,
            $hash,
            $tipo,
            $status,
            $senhaPura
        );

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $stmt->close();

        redirectUsuarios('Usuário cadastrado com sucesso');
    }

} catch(Throwable $e){

    error_log('[USUARIOS] ' . $e->getMessage());
    redirectUsuarios('Não foi possível salvar a alteração do usuário.', 'erro');
}

/*
|--------------------------------------------------------------------------
| LISTAGEM
|--------------------------------------------------------------------------
*/

$busca = trim($_GET['busca'] ?? '');
$filtroTipo = trim($_GET['tipo'] ?? '');
$filtroStatus = trim($_GET['status'] ?? '');

$where = [
    "empresa_id = ?"
];

$params = [$empresaId];
$types = 'i';

if($busca !== ''){
    $where[] = "(nome LIKE ? OR email LIKE ?)";
    $params[] = '%' . $busca . '%';
    $params[] = '%' . $busca . '%';
    $types .= 'ss';
}

if($filtroTipo !== '' && in_array($filtroTipo, $tiposPermitidos, true)){
    $where[] = "tipo = ?";
    $params[] = $filtroTipo;
    $types .= 's';
}

if($filtroStatus !== '' && in_array($filtroStatus, $statusPermitidos, true)){
    $where[] = "status = ?";
    $params[] = $filtroStatus;
    $types .= 's';
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT
        id,
        nome,
        email,
        tipo,
        status
    FROM usuarios
    WHERE {$whereSql}
    ORDER BY
        FIELD(tipo, 'admin', 'caixa', 'atendente', 'cozinha'),
        nome ASC
";

$stmt = $conn->prepare($sql);

if(!$stmt){
    die("Erro prepare usuários: " . h($conn->error));
}

if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$res = $stmt->get_result();

$usuarios = [];

while($row = $res->fetch_assoc()){
    $usuarios[] = $row;
}

$stmt->close();

$msg = trim($_GET['msg'] ?? '');
$alerta = trim($_GET['alerta'] ?? 'ok');

$nomeUsuario = $_SESSION['user_nome'] ?? 'Admin';

require_once __DIR__ . '/inc/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Usuários - Comandaria Express</title>

<?= ce_og_tags('Usuários - Comandaria Express', 'Cadastro e gestão de usuários do Comandaria Express') ?>
<link rel="icon" href="images/topo_comandaria_shake.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/management.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/staff.css?v=2">
</head>

<body class="cd-staff">
<?php $staffPage = 'usuarios.php'; require __DIR__ . '/includes/staff_nav.php'; ?>

<main class="cd-management cd-list">
    <header class="cd-page-head">
        <div>
            <h1>Usuários</h1>
            <p>Gerencie o acesso da sua equipe à Comandaria.</p>
        </div>
                <button
            class="btn cd-primary"
            data-bs-toggle="modal"
            data-bs-target="#modalUsuario"
            onclick="novoUsuario()"
        >
            + Novo usuário
        </button>

    </header>


    <?php if($msg !== ''): ?>
        <div role="status" class="alert alert-<?= $alerta === 'erro' ? 'danger' : 'success' ?>">
            <?= h($msg) ?>
        </div>
    <?php endif; ?>

    <div class="card-box">
        <form class="cd-filters" method="GET" aria-label="Filtros da lista">
            <div>
                <input
                    type="search"
                    name="busca"
                    aria-label="Buscar na lista"
                    class="form-control"
                    placeholder="Buscar por nome ou e-mail..."
                    value="<?= h($busca) ?>"
                >
            </div>

            <div>
                <select name="tipo" class="form-select" aria-label="Filtrar por perfil">
                    <option value="">Todos os tipos</option>

                    <?php foreach($tiposPermitidos as $tipo): ?>
                        <option
                            value="<?= h($tipo) ?>"
                            <?= $filtroTipo === $tipo ? 'selected' : '' ?>
                        >
                            <?= h($tipo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select name="status" class="form-select" aria-label="Filtrar por situação">
                    <option value="">Todos</option>

                    <?php foreach($statusPermitidos as $status): ?>
                        <option
                            value="<?= h($status) ?>"
                            <?= $filtroStatus === $status ? 'selected' : '' ?>
                        >
                            <?= h($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button class="btn cd-outline w-100">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <section class="card-box cd-records" aria-label="Usuários cadastrados">
        <div class="cd-list-head"><strong>Resultados</strong><span><?= count($usuarios) ?></span></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>E-mail</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if(empty($usuarios)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Nenhum usuário encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach($usuarios as $u): ?>
                        <tr>
                            <td>
                                <strong><?= h($u['nome']) ?></strong>

                                <div class="small text-muted">
                                    ID #<?= (int)$u['id'] ?>
                                </div>
                            </td>

                            <td>
                                <?= h($u['email']) ?>
                            </td>

                            <td>
                                <span class="badge bg-dark badge-pill">
                                    <?= h($u['tipo']) ?>
                                </span>
                            </td>

                            <td>
                                <?php if($u['status'] === 'ativo'): ?>
                                    <span class="badge bg-success badge-pill">
                                        ativo
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger badge-pill">
                                        inativo
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <button
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalUsuario"
                                    onclick='editarUsuario(<?= json_encode($u, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'
                                >
                                    Editar
                                </button>

                                <?php renderAcaoStatus($u, $csrfUsuarios); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-card">
            <?php if(empty($usuarios)): ?>
                <div class="text-center text-muted py-4">
                    Nenhum usuário encontrado.
                </div>
            <?php endif; ?>

            <?php foreach($usuarios as $u): ?>
                <div class="usuario-card">
                    <div class="nome">
                        <?= h($u['nome']) ?>
                    </div>

                    <div class="meta">
                        <?= h($u['email']) ?>
                    </div>

                    <div class="meta">
                        Tipo: <?= h($u['tipo']) ?>
                        • Status: <?= h($u['status']) ?>
                    </div>

                    <div class="acoes">
                        <button
                            class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#modalUsuario"
                            onclick='editarUsuario(<?= json_encode($u, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'
                        >
                            Editar
                        </button>

                        <?php renderAcaoStatus($u, $csrfUsuarios); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </section>

</main>

<div class="modal fade cd-editor" id="modalUsuario" tabindex="-1" aria-labelledby="modalTitulo">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-dark">

            <form method="POST" autocomplete="off">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">
                        Novo usuário
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="id" id="usuario_id">
                    <input type="hidden" name="csrf" value="<?= h($csrfUsuarios) ?>">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label" for="nome">
                                Nome
                            </label>

                            <input
                                type="text"
                                name="nome"
                                id="nome"
                                class="form-control"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="email">
                                E-mail
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="form-control"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="tipo">
                                Tipo
                            </label>

                            <select
                                name="tipo"
                                id="tipo"
                                class="form-select"
                                required
                            >
                                <option value="">Selecione</option>

                                <?php foreach($tiposPermitidos as $tipo): ?>
                                    <option value="<?= h($tipo) ?>">
                                        <?= h($tipo) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="status">
                                Status
                            </label>

                            <select
                                name="status"
                                id="status"
                                class="form-select"
                                required
                            >
                                <?php foreach($statusPermitidos as $status): ?>
                                    <option value="<?= h($status) ?>">
                                        <?= h($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="senha">
                                Senha
                            </label>

                            <input
                                type="password"
                                name="senha"
                                id="senha"
                                class="form-control"
                                placeholder="Nova senha"
                            >

                            <div class="form-text" id="senhaHelp">
                                Para editar sem trocar a senha, deixe em branco.
                            </div>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Fechar
                    </button>

                    <button class="btn cd-primary">
                        Salvar usuário
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
function novoUsuario(){

    document.getElementById('modalTitulo').textContent = 'Novo usuário';

    document.getElementById('usuario_id').value = '';
    document.getElementById('nome').value = '';
    document.getElementById('email').value = '';
    document.getElementById('tipo').value = '';
    document.getElementById('status').value = 'ativo';

    document.getElementById('senha').value = '';
    document.getElementById('senha').required = true;

    document.getElementById('senhaHelp').textContent =
        'Senha obrigatória para novo usuário.';
}

function editarUsuario(usuario){

    document.getElementById('modalTitulo').textContent = 'Editar usuário';

    document.getElementById('usuario_id').value = usuario.id || '';
    document.getElementById('nome').value = usuario.nome || '';
    document.getElementById('email').value = usuario.email || '';
    document.getElementById('tipo').value = usuario.tipo || '';
    document.getElementById('status').value = usuario.status || 'ativo';

    document.getElementById('senha').value = '';
    document.getElementById('senha').required = false;

    document.getElementById('senhaHelp').textContent =
        'Para editar sem trocar a senha, deixe em branco.';
}
</script>

</body>
</html>

<?php
$conn->close();
?>