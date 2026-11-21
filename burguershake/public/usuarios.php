<?php
require 'auth.php';
requireRole(['admin']);

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Bahia');

require 'conexao.php';

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
| AÇÕES GET
|--------------------------------------------------------------------------
*/

try {

    $acao = $_GET['acao'] ?? '';
    $id = (int)($_GET['id'] ?? 0);

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
            ");

            if(!$stmt){
                throw new Exception($conn->error);
            }

            $stmt->bind_param("i", $id);

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
            ");

            if(!$stmt){
                throw new Exception($conn->error);
            }

            $stmt->bind_param("i", $id);

            if(!$stmt->execute()){
                throw new Exception($stmt->error);
            }

            $stmt->close();

            redirectUsuarios('Usuário bloqueado com sucesso');
        }
    }

} catch(Throwable $e){

    redirectUsuarios($e->getMessage(), 'erro');
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
                ");

                if(!$stmt){
                    throw new Exception($conn->error);
                }

                $stmt->bind_param(
                    "ssssssi",
                    $nome,
                    $email,
                    $hash,
                    $tipo,
                    $status,
                    $senhaPura,
                    $id
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
                ");

                if(!$stmt){
                    throw new Exception($conn->error);
                }

                $stmt->bind_param(
                    "ssssi",
                    $nome,
                    $email,
                    $tipo,
                    $status,
                    $id
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
                nome,
                email,
                senha,
                tipo,
                status,
                senha_pura
            )
            VALUES (
                ?, ?, ?, ?, ?, ?
            )
        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            "ssssss",
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

    redirectUsuarios($e->getMessage(), 'erro');
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
    "1 = 1"
];

$params = [];
$types = '';

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
        status,
        senha_pura
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

<style>
body {
    background: #111;
    color: #fff;
    font-family: Arial, sans-serif;
    min-height: 100vh;
}

.header {
    text-align: center;
    padding: 18px 10px 8px;
}

.header img {
    max-width: 430px;
    width: 100%;
}

.userbox {
    text-align: center;
    margin-bottom: 12px;
}

.userbox .inner {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 999px;
    background: rgba(255,255,255,.06);
    font-size: 14px;
}

.container-main {
    max-width: 1050px;
    margin: 0 auto;
    padding: 10px;
}

.card-box {
    background: #fff;
    color: #111;
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 14px;
    box-shadow: 0 4px 16px rgba(0,0,0,.3);
}

.badge-pill {
    border-radius: 999px;
    padding: 5px 9px;
    font-size: 11px;
}

.table {
    vertical-align: middle;
}

.mobile-card {
    display: none;
}

@media(max-width: 768px) {
    .table-responsive {
        display: none;
    }

    .mobile-card {
        display: block;
    }

    .usuario-card {
        background: #fff;
        color: #111;
        border-radius: 14px;
        padding: 13px;
        margin-bottom: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,.25);
    }

    .usuario-card .nome {
        font-weight: 800;
        font-size: 16px;
    }

    .usuario-card .meta {
        color: #555;
        font-size: 13px;
        margin-top: 3px;
    }

    .usuario-card .acoes {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 10px;
    }
}

.btn-yellow {
    background: #ffc107;
    color: #111;
    border: none;
    font-weight: 800;
}

.btn-yellow:hover {
    background: #ffca2c;
    color: #111;
}

.logout {
    color: #ffc107;
    text-decoration: none;
}

.logout:hover {
    color: #ffda6a;
}
img {
  animation: pulsar 5s infinite;
}

@keyframes pulsar {
  0% {
    transform: scale(1);
  }
  80% {
    transform: scale(1);
  }
  90% {
    transform: scale(0.95);
  }
  100% {
    transform: scale(1);
  }
}
</style>
</head>

<body>

<div class="header">
    <img src="images/topo_comandaria_shake.png" alt="Comandaria Express">
</div>

<div class="userbox">
    <div class="inner">
        <span>
            Bem-vindo,
            <strong><?= h($nomeUsuario) ?></strong>
        </span>

        <a href="dashboard.php" class="logout">
            dashboard
        </a>

        <a href="logout.php" class="logout">
            sair
        </a>
    </div>
</div>

<div class="container-main">

    <?php if($msg !== ''): ?>
        <div class="alert alert-<?= $alerta === 'erro' ? 'danger' : 'success' ?>">
            <?= h($msg) ?>
        </div>
    <?php endif; ?>

    <div class="card-box">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
            <div>
                <h3 class="mb-0">👥 Usuários</h3>

                <div class="text-muted small">
                    Gerencie acessos por perfil: admin, atendente, cozinha e caixa.
                </div>
            </div>

            <button
                class="btn btn-yellow"
                data-bs-toggle="modal"
                data-bs-target="#modalUsuario"
                onclick="novoUsuario()"
            >
                + Novo usuário
            </button>
        </div>

        <form class="row g-2" method="GET">
            <div class="col-md-5">
                <input
                    type="text"
                    name="busca"
                    class="form-control"
                    placeholder="Buscar por nome ou e-mail..."
                    value="<?= h($busca) ?>"
                >
            </div>

            <div class="col-md-3">
                <select name="tipo" class="form-select">
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

            <div class="col-md-2">
                <select name="status" class="form-select">
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

            <div class="col-md-2">
                <button class="btn btn-dark w-100">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <div class="card-box">

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
                                    onclick='editarUsuario(<?= json_encode($u, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                >
                                    Editar
                                </button>

                                <?php if($u['status'] === 'ativo'): ?>
                                    <a
                                        href="usuarios.php?acao=bloquear&id=<?= (int)$u['id'] ?>"
                                        class="btn btn-sm btn-outline-warning"
                                        onclick="return confirm('Bloquear este usuário?')"
                                    >
                                        Bloquear
                                    </a>
                                <?php else: ?>
                                    <a
                                        href="usuarios.php?acao=ativar&id=<?= (int)$u['id'] ?>"
                                        class="btn btn-sm btn-outline-success"
                                    >
                                        Ativar
                                    </a>
                                <?php endif; ?>
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
                            onclick='editarUsuario(<?= json_encode($u, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                        >
                            Editar
                        </button>

                        <?php if($u['status'] === 'ativo'): ?>
                            <a
                                href="usuarios.php?acao=bloquear&id=<?= (int)$u['id'] ?>"
                                class="btn btn-sm btn-outline-warning"
                                onclick="return confirm('Bloquear este usuário?')"
                            >
                                Bloquear
                            </a>
                        <?php else: ?>
                            <a
                                href="usuarios.php?acao=ativar&id=<?= (int)$u['id'] ?>"
                                class="btn btn-sm btn-outline-success"
                            >
                                Ativar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

</div>

<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-dark">

            <form method="POST" autocomplete="off">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">
                        Novo usuário
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="id" id="usuario_id">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">
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
                            <label class="form-label">
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
                            <label class="form-label">
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
                            <label class="form-label">
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
                            <label class="form-label">
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

                    <button class="btn btn-yellow">
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