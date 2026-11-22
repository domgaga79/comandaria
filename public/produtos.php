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
require __DIR__ . '/../core/tenant_core.php';

$empresaId = tenantResolverEmpresaId(authEmpresaId());

if(method_exists($conn, 'set_charset')){
    $conn->set_charset('utf8mb4');
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dinheiro($value): string {
    return number_format((float)$value, 2, ',', '.');
}

function normalizarDecimal($valor): float {

    $valor = trim((string)$valor);

    if($valor === ''){
        return 0;
    }

    if(strpos($valor, ',') !== false){
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return (float)$valor;
}

function redirectProdutos(string $msg = '', string $tipo = 'ok'): void {

    $url = 'produtos.php';

    if($msg !== ''){
        $url .= '?msg=' . urlencode($msg) . '&tipo=' . urlencode($tipo);
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
                UPDATE produtos
                SET ativo = 1
                WHERE id = ?
                AND empresa_id = ?
                AND deleted_at IS NULL
            ");

            if(!$stmt){
                throw new Exception($conn->error);
            }

            $stmt->bind_param("ii", $id, $empresaId);

            if(!$stmt->execute()){
                throw new Exception($stmt->error);
            }

            $stmt->close();

            redirectProdutos('Produto ativado com sucesso');
        }

        if($acao === 'desativar'){

            $stmt = $conn->prepare("
                UPDATE produtos
                SET ativo = 0
                WHERE id = ?
                AND empresa_id = ?
                AND deleted_at IS NULL
            ");

            if(!$stmt){
                throw new Exception($conn->error);
            }

            $stmt->bind_param("ii", $id, $empresaId);

            if(!$stmt->execute()){
                throw new Exception($stmt->error);
            }

            $stmt->close();

            redirectProdutos('Produto desativado com sucesso');
        }

        if($acao === 'excluir'){

            $stmt = $conn->prepare("
                UPDATE produtos
                SET
                    ativo = 0,
                    deleted_at = NOW()
                WHERE id = ?
                AND empresa_id = ?
                AND deleted_at IS NULL
            ");

            if(!$stmt){
                throw new Exception($conn->error);
            }

            $stmt->bind_param("ii", $id, $empresaId);

            if(!$stmt->execute()){
                throw new Exception($stmt->error);
            }

            $stmt->close();

            redirectProdutos('Produto excluído com sucesso');
        }
    }

} catch(Throwable $e){

    redirectProdutos($e->getMessage(), 'erro');
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

        $imagemUrl = trim((string)($_POST['imagem_url'] ?? ''));
        if ($imagemUrl !== '' && (
            strlen($imagemUrl) > 2048 ||
            !filter_var($imagemUrl, FILTER_VALIDATE_URL) ||
            strtolower((string)parse_url($imagemUrl, PHP_URL_SCHEME)) !== 'https'
        )) {
            redirectProdutos('Informe um link HTTPS válido para a foto.', 'erro');
        }
        $imagemUrl = $imagemUrl !== '' ? $imagemUrl : null;


        $categoriaId = !empty($_POST['categoria_id'])
            ? (int)$_POST['categoria_id']
            : null;

        $preco = normalizarDecimal($_POST['preco'] ?? 0);

        $tempo = (int)($_POST['tempo'] ?? 10);

        $permiteObs = isset($_POST['permite_obs'])
            ? 1
            : 0;

        $adicionalPermitido = isset($_POST['adicional_permitido'])
            ? 1
            : 0;

        $valorAdicional = $adicionalPermitido === 1
            ? normalizarDecimal($_POST['valor_adicional'] ?? 0)
            : 0;

        $ativo = isset($_POST['ativo'])
            ? 1
            : 0;

        if($nome === ''){
            redirectProdutos('Informe o nome do produto', 'erro');
        }

        if($preco < 0){
            redirectProdutos('Preço inválido', 'erro');
        }

        if($tempo <= 0){
            $tempo = 10;
        }

        if($adicionalPermitido === 1 && $valorAdicional <= 0){
            redirectProdutos('Informe o valor do adicional', 'erro');
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        if($id > 0){

            $stmt = $conn->prepare("
                UPDATE produtos
                SET
                    nome = ?,
                    categoria_id = ?,
                    preco = ?,
                    ativo = ?,
                    tempo = ?,
                    permite_obs = ?,
                    adicional_permitido = ?,
                    valor_adicional = ?,
                    imagem_url = ?
                WHERE id = ?
                AND empresa_id = ?
                AND deleted_at IS NULL
            ");

            if(!$stmt){
                throw new Exception($conn->error);
            }

            $stmt->bind_param(
                "sidiiiidsii",
                $nome,
                $categoriaId,
                $preco,
                $ativo,
                $tempo,
                $permiteObs,
                $adicionalPermitido,
                $valorAdicional,
                $imagemUrl,
                $id,
                $empresaId
            );

            if(!$stmt->execute()){
                throw new Exception($stmt->error);
            }

            $stmt->close();

            redirectProdutos('Produto atualizado com sucesso');
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            INSERT INTO produtos (
                empresa_id,
                nome,
                categoria_id,
                preco,
                ativo,
                tempo,
                permite_obs,
                adicional_permitido,
                valor_adicional,
                imagem_url,
                created_at,
                deleted_at
            )
            VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                NOW(),
                NULL
            )
        ");

        if(!$stmt){
            throw new Exception($conn->error);
        }

        $stmt->bind_param(
            "isidiiiids",
            $empresaId,
            $nome,
            $categoriaId,
            $preco,
            $ativo,
            $tempo,
            $permiteObs,
            $adicionalPermitido,
            $valorAdicional,
            $imagemUrl
        );

        if(!$stmt->execute()){
            throw new Exception($stmt->error);
        }

        $stmt->close();

        redirectProdutos('Produto cadastrado com sucesso');
    }

} catch(Throwable $e){

    redirectProdutos($e->getMessage(), 'erro');
}

/*
|--------------------------------------------------------------------------
| CATEGORIAS
|--------------------------------------------------------------------------
*/

$categorias = [];

$stmtCategorias = $conn->prepare("
    SELECT
        id,
        nome,
        ativo
    FROM categorias
    WHERE empresa_id = ?
    AND ativo = 1
    ORDER BY nome ASC
");

if(!$stmtCategorias){
    die("Erro prepare categorias: " . h($conn->error));
}

$stmtCategorias->bind_param('i', $empresaId);
$stmtCategorias->execute();

$resCategorias = $stmtCategorias->get_result();

while($cat = $resCategorias->fetch_assoc()){
    $categorias[] = $cat;
}

$stmtCategorias->close();

/*
|--------------------------------------------------------------------------
| PRODUTOS
|--------------------------------------------------------------------------
*/

$busca = trim($_GET['busca'] ?? '');
$filtroCategoria = (int)($_GET['categoria_id'] ?? 0);
$filtroStatus = $_GET['status'] ?? '';

$where = [
    "p.empresa_id = ?",
    "p.deleted_at IS NULL"
];

$params = [$empresaId];
$types = 'i';

if($busca !== ''){
    $where[] = "p.nome LIKE ?";
    $params[] = '%' . $busca . '%';
    $types .= 's';
}

if($filtroCategoria > 0){
    $where[] = "p.categoria_id = ?";
    $params[] = $filtroCategoria;
    $types .= 'i';
}

if($filtroStatus === 'ativo'){
    $where[] = "p.ativo = 1";
}

if($filtroStatus === 'inativo'){
    $where[] = "p.ativo = 0";
}

$whereSql = implode(' AND ', $where);

$sqlProdutos = "
    SELECT
        p.id,
        p.nome,
        p.categoria_id,
        p.preco,
        p.ativo,
        p.created_at,
        p.tempo,
        p.permite_obs,
        p.adicional_permitido,
        p.valor_adicional,
        p.imagem_url,

        c.nome AS categoria_nome

    FROM produtos p

    LEFT JOIN categorias c
        ON c.id = p.categoria_id
        AND c.empresa_id = p.empresa_id

    WHERE {$whereSql}

    ORDER BY
        c.nome ASC,
        p.nome ASC
";

$stmtProdutos = $conn->prepare($sqlProdutos);

if(!$stmtProdutos){
    die("Erro prepare produtos: " . h($conn->error));
}

if(!empty($params)){
    $stmtProdutos->bind_param($types, ...$params);
}

$stmtProdutos->execute();

$resProdutos = $stmtProdutos->get_result();

$produtos = [];

while($produto = $resProdutos->fetch_assoc()){
    $produtos[] = $produto;
}

$stmtProdutos->close();

$msg = trim($_GET['msg'] ?? '');
$tipoMsg = trim($_GET['tipo'] ?? 'ok');

$nomeUsuario = $_SESSION['user_nome'] ?? 'Admin';

require_once __DIR__ . '/inc/seo.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Produtos - Comandaria Express</title>

<?= ce_og_tags('Produtos - Comandaria Express', 'Cadastro e gestão de produtos do Comandaria Express') ?>
<link rel="icon" href="images/comandaria_logo.png" type="image/png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="assets/management.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/staff.css?v=2">
</head>

<body class="cd-staff">
<?php $staffPage = 'produtos.php'; require __DIR__ . '/includes/staff_nav.php'; ?>

<main class="cd-management cd-list">
    <header class="cd-page-head">
        <div>
            <h1>Produtos</h1>
            <p>Gerencie o cardápio, os preços e os adicionais.</p>
        </div>
        <button
            class="btn cd-primary"
            data-bs-toggle="modal"
            data-bs-target="#modalProduto"
            onclick="novoProduto()"
        >
            + Novo produto
        </button>
    </header>

    <nav class="cd-catalog" aria-label="Cardápio">
        <a href="produtos.php" aria-current="page">Produtos</a>
        <a href="categorias.php">Categorias</a>
    </nav>

    <?php if($msg !== ''): ?>
        <div role="status" class="alert alert-<?= $tipoMsg === 'erro' ? 'danger' : 'success' ?>">
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
                    placeholder="Buscar produto..."
                    value="<?= h($busca) ?>"
                >
            </div>

            <div>
                <select name="categoria_id" class="form-select" aria-label="Filtrar por categoria">
                    <option value="0">Todas as categorias</option>

                    <?php foreach($categorias as $cat): ?>
                        <option
                            value="<?= (int)$cat['id'] ?>"
                            <?= $filtroCategoria === (int)$cat['id'] ? 'selected' : '' ?>
                        >
                            <?= h($cat['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select name="status" class="form-select" aria-label="Filtrar por situação">
                    <option value="">Todos</option>

                    <option value="ativo" <?= $filtroStatus === 'ativo' ? 'selected' : '' ?>>
                        Ativos
                    </option>

                    <option value="inativo" <?= $filtroStatus === 'inativo' ? 'selected' : '' ?>>
                        Inativos
                    </option>
                </select>
            </div>

            <div>
                <button class="btn cd-outline w-100">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <section class="card-box cd-records" aria-label="Produtos cadastrados">
        <div class="cd-list-head"><strong>Resultados</strong><span><?= count($produtos) ?></span></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Preço</th>
                        <th>Tempo</th>
                        <th>Obs</th>
                        <th>Adicional</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if(empty($produtos)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Nenhum produto encontrado.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach($produtos as $p): ?>
                        <tr>
                            <td>
                                <strong><?= h($p['nome']) ?></strong>

                                <div class="small text-muted">
                                    ID #<?= (int)$p['id'] ?>
                                </div>
                            </td>

                            <td>
                                <?= h($p['categoria_nome'] ?? 'Sem categoria') ?>
                            </td>

                            <td>
                                R$ <?= dinheiro($p['preco']) ?>
                            </td>

                            <td>
                                <?= (int)$p['tempo'] ?> min
                            </td>

                            <td>
                                <?php if((int)$p['permite_obs'] === 1): ?>
                                    <span class="badge bg-info text-dark badge-pill">sim</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary badge-pill">não</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if((int)$p['adicional_permitido'] === 1): ?>
                                    <span class="badge bg-primary badge-pill">
                                        + R$ <?= dinheiro($p['valor_adicional']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary badge-pill">não</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if((int)$p['ativo'] === 1): ?>
                                    <span class="badge bg-success badge-pill">ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger badge-pill">inativo</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <button
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalProduto"
                                    onclick='editarProduto(<?= json_encode($p, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'
                                >
                                    Editar
                                </button>

                                <?php if((int)$p['ativo'] === 1): ?>
                                    <a
                                        class="btn btn-sm btn-outline-warning"
                                        href="produtos.php?acao=desativar&id=<?= (int)$p['id'] ?>"
                                        onclick="return confirm('Desativar este produto?')"
                                    >
                                        Desativar
                                    </a>
                                <?php else: ?>
                                    <a
                                        class="btn btn-sm btn-outline-success"
                                        href="produtos.php?acao=ativar&id=<?= (int)$p['id'] ?>"
                                    >
                                        Ativar
                                    </a>
                                <?php endif; ?>

                                <a
                                    class="btn btn-sm btn-outline-danger"
                                    href="produtos.php?acao=excluir&id=<?= (int)$p['id'] ?>"
                                    onclick="return confirm('Excluir este produto?')"
                                >
                                    Excluir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mobile-card">
            <?php if(empty($produtos)): ?>
                <div class="text-center text-muted py-4">
                    Nenhum produto encontrado.
                </div>
            <?php endif; ?>

            <?php foreach($produtos as $p): ?>
                <div class="produto-card">
                    <div class="nome">
                        <?= h($p['nome']) ?>
                    </div>

                    <div class="meta">
                        <?= h($p['categoria_nome'] ?? 'Sem categoria') ?>
                        • R$ <?= dinheiro($p['preco']) ?>
                        • <?= (int)$p['tempo'] ?> min
                    </div>

                    <div class="meta">
                        Obs: <?= (int)$p['permite_obs'] === 1 ? 'sim' : 'não' ?>
                        • Adicional:
                        <?php if((int)$p['adicional_permitido'] === 1): ?>
                            + R$ <?= dinheiro($p['valor_adicional']) ?>
                        <?php else: ?>
                            não
                        <?php endif; ?>
                    </div>

                    <div class="meta">
                        Status:
                        <?= (int)$p['ativo'] === 1 ? 'ativo' : 'inativo' ?>
                    </div>

                    <div class="acoes">
                        <button
                            class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#modalProduto"
                            onclick='editarProduto(<?= json_encode($p, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'
                        >
                            Editar
                        </button>

                        <?php if((int)$p['ativo'] === 1): ?>
                            <a
                                class="btn btn-sm btn-outline-warning"
                                href="produtos.php?acao=desativar&id=<?= (int)$p['id'] ?>"
                                onclick="return confirm('Desativar este produto?')"
                            >
                                Desativar
                            </a>
                        <?php else: ?>
                            <a
                                class="btn btn-sm btn-outline-success"
                                href="produtos.php?acao=ativar&id=<?= (int)$p['id'] ?>"
                            >
                                Ativar
                            </a>
                        <?php endif; ?>

                        <a
                            class="btn btn-sm btn-outline-danger"
                            href="produtos.php?acao=excluir&id=<?= (int)$p['id'] ?>"
                            onclick="return confirm('Excluir este produto?')"
                        >
                            Excluir
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </section>

</main>

<div class="modal fade cd-editor" id="modalProduto" tabindex="-1" aria-labelledby="modalTitulo">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content text-dark">

            <form method="POST" autocomplete="off">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo">
                        Novo produto
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="id" id="produto_id">

                    <div class="row g-3">

                        <div class="col-md-7">
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

                        <div class="col-12">
                            <label class="form-label" for="imagem_url">Link da foto</label>
                            <input type="url" name="imagem_url" id="imagem_url" class="form-control"
                                maxlength="2048" placeholder="https://exemplo.com/foto.jpg"
                                aria-describedby="imagem_ajuda">
                            <small id="imagem_ajuda" class="text-muted">Use o endereço direto da imagem (HTTPS).</small>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label" for="categoria_id">
                                Categoria
                            </label>

                            <select
                                name="categoria_id"
                                id="categoria_id"
                                class="form-select"
                            >
                                <option value="">Sem categoria</option>

                                <?php foreach($categorias as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>">
                                        <?= h($cat['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="preco">
                                Preço
                            </label>

                            <input
                                type="text"
                                name="preco"
                                id="preco"
                                class="form-control"
                                placeholder="0,00"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="tempo">
                                Tempo preparo
                            </label>

                            <input
                                type="number"
                                name="tempo"
                                id="tempo"
                                class="form-control"
                                min="1"
                                value="10"
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Status
                            </label>

                            <div class="form-check mt-2">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="ativo"
                                    id="ativo"
                                    checked
                                >

                                <label class="form-check-label" for="ativo">
                                    Produto ativo
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check mt-2">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="permite_obs"
                                    id="permite_obs"
                                >

                                <label class="form-check-label" for="permite_obs">
                                    Permitir observação no pedido
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check mt-2">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="adicional_permitido"
                                    id="adicional_permitido"
                                    onchange="toggleAdicional()"
                                >

                                <label class="form-check-label" for="adicional_permitido">
                                    Permitir adicional monetário
                                </label>
                            </div>
                        </div>

                        <div class="col-md-4" id="boxValorAdicional" style="display:none;">
                            <label class="form-label" for="valor_adicional">
                                Valor adicional
                            </label>

                            <input
                                type="text"
                                name="valor_adicional"
                                id="valor_adicional"
                                class="form-control"
                                placeholder="0,00"
                            >
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
                        Salvar produto
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
function moedaBR(valor){

    return Number(valor || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function novoProduto(){

    document.getElementById('modalTitulo').textContent = 'Novo produto';

    document.getElementById('produto_id').value = '';
    document.getElementById('nome').value = '';
    document.getElementById('imagem_url').value = '';
    document.getElementById('categoria_id').value = '';
    document.getElementById('preco').value = '';
    document.getElementById('tempo').value = 10;
    document.getElementById('ativo').checked = true;
    document.getElementById('permite_obs').checked = false;
    document.getElementById('adicional_permitido').checked = false;
    document.getElementById('valor_adicional').value = '';

    toggleAdicional();
}

function editarProduto(produto){

    document.getElementById('modalTitulo').textContent = 'Editar produto';

    document.getElementById('produto_id').value = produto.id || '';
    document.getElementById('nome').value = produto.nome || '';
    document.getElementById('imagem_url').value = produto.imagem_url || '';
    document.getElementById('categoria_id').value = produto.categoria_id || '';
    document.getElementById('preco').value = moedaBR(produto.preco || 0);
    document.getElementById('tempo').value = produto.tempo || 10;
    document.getElementById('ativo').checked = Number(produto.ativo || 0) === 1;
    document.getElementById('permite_obs').checked = Number(produto.permite_obs || 0) === 1;
    document.getElementById('adicional_permitido').checked = Number(produto.adicional_permitido || 0) === 1;
    document.getElementById('valor_adicional').value = moedaBR(produto.valor_adicional || 0);

    toggleAdicional();
}

function toggleAdicional(){

    const checked = document.getElementById('adicional_permitido').checked;

    document.getElementById('boxValorAdicional').style.display = checked
        ? 'block'
        : 'none';

    if(!checked){
        document.getElementById('valor_adicional').value = '';
    }
}
</script>

</body>
</html>

<?php
$conn->close();
?>