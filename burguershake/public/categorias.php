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

function redirectCategorias(string $msg = '', string $alerta = 'ok'): void {

    $url = 'categorias.php';

    if($msg !== ''){
        $url .= '?msg=' . urlencode($msg) . '&alerta=' . urlencode($alerta);
    }

    header("Location: {$url}");
    exit;
}

/*
|--------------------------------------------------------------------------
| AÇÕES GET
|--------------------------------------------------------------------------
*/

try {

    $acao = $_GET['acao'] ?? '';
    $id = (int)($_GET['id'] ?? 0);

    if($acao && $id > 0){

        if($acao === 'ativar'){

            $stmt = $conn->prepare("
                UPDATE categorias
                SET ativo = 1
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

            redirectCategorias('Categoria ativada com sucesso');
        }

        if($acao === 'desativar'){

            $stmt = $conn->prepare("
                UPDATE categorias
                SET ativo = 0
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

            redirectCategorias('Categoria desativada com sucesso');
        }

        if($acao === 'excluir'){

            /*
             * Só permite excluir categoria sem produtos vinculados.
             */
            $stmtCheck = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM produtos
                WHERE categoria_id = ?
            ");

            if(!$stmtCheck){
                throw new Exception($conn->error);
            }

            $stmtCheck->bind_param("i", $id);

            if(!$stmtCheck->execute()){
                throw new Exception($stmtCheck->error);
            }

            $resCheck = $stmtCheck->get_result();
            $rowCheck = $resCheck->fetch_assoc();

            $stmtCheck->close();

            if((int)$rowCheck['total'] > 0){
                redirectCategorias(
                    'Não é possível excluir: existem produtos vinculados a esta categoria',
                    'erro'
                );
            }

            $stmt = $conn->prepare("
                DELETE FROM categorias
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

            redirectCategorias('Categoria excluída com sucesso');
        }
    }

} catch(Throwable $e){

    redirectCategorias($e->getMessage(), 'erro');
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

        $ativo = isset($_POST['ativo'])
            ? 1
            : 0;

        if($nome === ''){
            redirectCategorias('Informe o nome da categoria', 'erro');
        }

        /*
        |--------------------------------------------------------------------------
        | Verifica duplicidade
        |--------------------------------------------------------------------------
        */

        $stmtDuplicado = $conn->prepare("
            SELECT id
            FROM categorias
            WHERE nome = ?
            AND id != ?
            LIMIT 1
        ");

        if(!$stmtDuplicado){
            throw new Exception($conn->error);
        }

        $stmtDuplicado->bind_param(
            "si",
            $nome,
            $id
        );

        if(!$stmtDuplicado->execute()){
            throw new Exception($stmtDuplicado->error);
        }

        $resDuplicado = $stmtDuplicado->get_result();

        if($resDuplicado->num_rows > 0){

            $stmtDuplicado->close();

            redirectCategorias(
                'Já existe uma categoria com este nome',
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

            $stmt = $conn->prepare("
                UPDATE categorias
                SET
                    nome = ?,
                    ativo = ?
                WHERE id = ?
            ");

            if(!$stmt){
                throw new Exception($conn->error);
            }

            $stmt->bind_param(
                "sii",
                $nome,
                $ativo,
                $id
            );

            if(!$stmt->execute()){
                throw new Exception($stmt->error);
            }

            $stmt->close();

            redirectCategorias('Categoria atualizada com sucesso');
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            INSERT INTO categorias (
                nome,
                ativo
            )
            VALUES (
                ?, ?
            )
        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            "si",
            $nome,
            $ativo
        );

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $stmt->close();

        redirectCategorias('Categoria cadastrada com sucesso');
    }

} catch(Throwable $e){

    redirectCategorias($e->getMessage(), 'erro');
}

/*
|--------------------------------------------------------------------------
| LISTAGEM
|--------------------------------------------------------------------------
*/

$busca = trim($_GET['busca'] ?? '');
$filtroStatus = trim($_GET['status'] ?? '');

$where = [
    "1 = 1"
];

$params = [];
$types = '';

if($busca !== ''){
    $where[] = "c.nome LIKE ?";
    $params[] = '%' . $busca . '%';
    $types .= 's';
}

if($filtroStatus === 'ativo'){
    $where[] = "c.ativo = 1";
}

if($filtroStatus === 'inativo'){
    $where[] = "c.ativo = 0";
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT
        c.id,
        c.nome,
        c.ativo,
        COUNT(p.id) AS total_produtos
    FROM categorias c

    LEFT JOIN produtos p
        ON p.categoria_id = c.id
        AND p.deleted_at IS NULL

    WHERE {$whereSql}

    GROUP BY
        c.id,
        c.nome,
        c.ativo

    ORDER BY
        c.nome ASC
";

$stmt = $conn->prepare($sql);

if(!$stmt){
    die("Erro prepare categorias: " . h($conn->error));
}

if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$res = $stmt->get_result();

$categorias = [];

while($row = $res->fetch_assoc()){
    $categorias[] = $row;
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

<title>Categorias - Comandaria Express</title>

<?= ce_og_tags('Categorias - Comandaria Express', 'Cadastro e gestão de categorias do Comandaria Express') ?>
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
    max-width: 950px;
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

    .categoria-card {
        background: #fff;
        color: #111;
        border-radius: 14px;
        padding: 13px;
        margin-bottom: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,.25);
    }

    .categoria-card .nome {
        font-weight: 800;
        font-size: 16px;
    }

    .categoria-card .meta {
        color: #555;
        font-size: 13px;
        margin-top: 3px;
    }

    .categoria-card .acoes {
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

        <a href="produtos.php" class="logout">
            produtos
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
                <h3 class="mb-0">🏷️ Categorias</h3>

                <div class="text-muted small">
                    Gerencie as categorias usadas no cadastro de produtos.
                </div>
            </div>

            <button
                class="btn btn-yellow"
                data-bs-toggle="modal"
                data-bs-target="#modalCategoria"
                onclick="novaCategoria()"
            >
                + Nova categoria
            </button>
        </div>

        <form class="row g-2" method="GET">
            <div class="col-md-6">
                <input
                    type="text"
                    name="busca"
                    class="form-control"
                    placeholder="Buscar categoria..."
                    value="<?= h($busca) ?>"
                >
            </div>

            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Todas</option>

                    <option value="ativo" <?= $filtroStatus === 'ativo' ? 'selected' : '' ?>>
                        Ativas
                    </option>

                    <option value="inativo" <?= $filtroStatus === 'inativo' ? 'selected' : '' ?>>
                        Inativas
                    </option>
                </select>
            </div>

            <div class="col-md-3">
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
                        <th>Categoria</th>
                        <th>Produtos vinculados</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if(empty($categorias)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                Nenhuma categoria encontrada.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach($categorias as $cat): ?>
                        <tr>
                            <td>
                                <strong><?= h($cat['nome']) ?></strong>

                                <div class="small text-muted">
                                    ID #<?= (int)$cat['id'] ?>
                                </div>
                            </td>

                            <td>
                                <?= (int)$cat['total_produtos'] ?>
                            </td>

                            <td>
                                <?php if((int)$cat['ativo'] === 1): ?>
                                    <span class="badge bg-success badge-pill">
                                        ativa
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger badge-pill">
                                        inativa
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <button
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalCategoria"
                                    onclick='editarCategoria(<?= json_encode($cat, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                >
                                    Editar
                                </button>

                                <?php if((int)$cat['ativo'] === 1): ?>
                                    <a
                                        href="categorias.php?acao=desativar&id=<?= (int)$cat['id'] ?>"
                                        class="btn btn-sm btn-outline-warning"
                                        onclick="return confirm('Desativar esta categoria?')"
                                    >
                                        Desativar
                                    </a>
                                <?php else: ?>
                                    <a
                                        href="categorias.php?acao=ativar&id=<?= (int)$cat['id'] ?>"
                                        class="btn btn-sm btn-outline-success"
                                    >
                                        Ativar
                                    </a>
                                <?php endif; ?>

                                <?php if((int)$cat['total_produtos'] === 0): ?>
                                    <a
                                        href="categorias.php?acao=excluir&id=<?= (int)$cat['id'] ?>"
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Excluir esta categoria?')"
                                    >
                                        Excluir
                                    </a>
                                <?php else: ?>
                                    <button
                                        class="btn btn-sm btn-outline-secondary"
                                        disabled
                                        title="Categoria possui produtos vinculados"
                                    >
                                        Excluir
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-card">
            <?php if(empty($categorias)): ?>
                <div class="text-center text-muted py-4">
                    Nenhuma categoria encontrada.
                </div>
            <?php endif; ?>

            <?php foreach($categorias as $cat): ?>
                <div class="categoria-card">
                    <div class="nome">
                        <?= h($cat['nome']) ?>
                    </div>

                    <div class="meta">
                        ID #<?= (int)$cat['id'] ?>
                        • Produtos: <?= (int)$cat['total_produtos'] ?>
                        • Status: <?= (int)$cat['ativo'] === 1 ? 'ativa' : 'inativa' ?>
                    </div>

                    <div class="acoes">
                        <button
                            class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#modalCategoria"
                            onclick='editarCategoria(<?= json_encode($cat, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                        >
                            Editar
                        </button>

                        <?php if((int)$cat['ativo'] === 1): ?>
                            <a
                                href="categorias.php?acao=desativar&id=<?= (int)$cat['id'] ?>"
                                class="btn btn-sm btn-outline-warning"
                                onclick="return confirm('Desativar esta categoria?')"
                            >
                                Desativar
                            </a>
                        <?php else: ?>
                            <a
                                href="categorias.php?acao=ativar&id=<?= (int)$cat['id'] ?>"
                                class="btn btn-sm btn-outline-success"
                            >
                                Ativar
                            </a>
                        <?php endif; ?>

                        <?php if((int)$cat['total_produtos'] === 0): ?>
                            <a
                                href="categorias.php?acao=excluir&id=<?= (int)$cat['id'] ?>"
                                class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Excluir esta categoria?')"
                            >
                                Excluir
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

</div>

<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content text-dark">

            <form method="POST" autocomplete="off">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">
                        Nova categoria
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="id" id="categoria_id">

                    <div class="mb-3">
                        <label class="form-label">
                            Nome da categoria
                        </label>

                        <input
                            type="text"
                            name="nome"
                            id="nome"
                            class="form-control"
                            required
                        >
                    </div>

                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="ativo"
                            id="ativo"
                            checked
                        >

                        <label class="form-check-label" for="ativo">
                            Categoria ativa
                        </label>
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
                        Salvar categoria
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
function novaCategoria(){

    document.getElementById('modalTitulo').textContent = 'Nova categoria';

    document.getElementById('categoria_id').value = '';
    document.getElementById('nome').value = '';
    document.getElementById('ativo').checked = true;
}

function editarCategoria(categoria){

    document.getElementById('modalTitulo').textContent = 'Editar categoria';

    document.getElementById('categoria_id').value = categoria.id || '';
    document.getElementById('nome').value = categoria.nome || '';
    document.getElementById('ativo').checked = Number(categoria.ativo || 0) === 1;
}
</script>

</body>
</html>

<?php
$conn->close();
?>