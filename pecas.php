<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

// Suporte duplo de sessão
$utilizador_id = $_SESSION['utilizador_id'] ?? $_SESSION['user_id'] ?? null;
$tipo_user     = strtolower(trim($_SESSION['tipo'] ?? $_SESSION['user_tipo'] ?? ''));

if (!$utilizador_id) {
    header("Location: login.php");
    exit();
}

// Permissões: Mecânico pode editar/adicionar/apagar. Chefe apenas visualiza.
$e_mecanico = ($tipo_user === 'mecanico');
$e_chefe    = ($tipo_user === 'chefe');

// Apenas Chefe e Mecânico têm acesso a esta página
if (!$e_mecanico && !$e_chefe) {
    header("Location: index.php");
    exit();
}

// 1. Processar Adição de Nova Peça
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_peca']) && $e_mecanico) {
    $nome           = trim($_POST['nome'] ?? '');
    $referencia     = trim($_POST['referencia'] ?? '');
    $quantidade     = (int)($_POST['quantidade'] ?? 0);
    $preco_unitario = (float)($_POST['preco_unitario'] ?? 0.00);
    $stock_minimo   = (int)($_POST['stock_minimo'] ?? 5);

    if (!empty($nome)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO pecas (nome, referencia, quantidade, preco_unitario, stock_minimo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $referencia, $quantidade, $preco_unitario, $stock_minimo]);
            $_SESSION['sucesso'] = "Peça '{$nome}' adicionada ao inventário!";
        } catch (\PDOException $e) {
            $_SESSION['erro'] = "Erro ao adicionar peça: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "O nome da peça é obrigatório.";
    }
    header("Location: pecas.php");
    exit();
}

// 2. Processar Edição de Peça
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_peca']) && $e_mecanico) {
    $peca_id        = (int)($_POST['peca_id'] ?? 0);
    $nome           = trim($_POST['nome'] ?? '');
    $referencia     = trim($_POST['referencia'] ?? '');
    $quantidade     = (int)($_POST['quantidade'] ?? 0);
    $preco_unitario = (float)($_POST['preco_unitario'] ?? 0.00);
    $stock_minimo   = (int)($_POST['stock_minimo'] ?? 5);

    if ($peca_id > 0 && !empty($nome)) {
        try {
            $stmt = $pdo->prepare("UPDATE pecas SET nome = ?, referencia = ?, quantidade = ?, preco_unitario = ?, stock_minimo = ? WHERE id = ?");
            $stmt->execute([$nome, $referencia, $quantidade, $preco_unitario, $stock_minimo, $peca_id]);
            $_SESSION['sucesso'] = "Registo da peça atualizado com sucesso!";
        } catch (\PDOException $e) {
            $_SESSION['erro'] = "Erro ao atualizar peça: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Dados inválidos para alteração.";
    }
    header("Location: pecas.php");
    exit();
}

// 3. Processar Eliminação de Peça
if (isset($_GET['eliminar']) && $e_mecanico) {
    $peca_id = (int)$_GET['eliminar'];
    try {
        $stmt = $pdo->prepare("DELETE FROM pecas WHERE id = ?");
        $stmt->execute([$peca_id]);
        $_SESSION['sucesso'] = "Peça removida do inventário.";
    } catch (\PDOException $e) {
        $_SESSION['erro'] = "Erro ao eliminar peça: " . $e->getMessage();
    }
    header("Location: pecas.php");
    exit();
}

// Obter Lista de Peças
$pecas = $pdo->query("SELECT * FROM pecas ORDER BY nome ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Inventário de Peças</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- Navbar de Topo -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center fw-bold me-4" href="index.php">
            <img src="img/autofix.jpg" alt="AutoFix" width="35" height="35" class="d-inline-block align-text-top rounded me-2">
            AutoFix
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarAutoFix">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarAutoFix">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item me-3"><a class="nav-link" href="index.php">Dashboard</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="clientes.php">Clientes & Veículos</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="reparacoes.php">Reparações</a></li>
                <li class="nav-item me-3"><a class="nav-link active" href="pecas.php">Inventário de Peças</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="agendamentos.php">Agendamentos</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="historico.php">Histórico</a></li>
            </ul>
            <div class="d-flex align-items-center">
                <a href="perfil.php" class="btn btn-outline-light btn-sm me-2">👤 O Meu Perfil</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Sair</a>
            </div>
        </div>
    </div>
</nav>

<!-- Conteúdo Principal -->
<div class="container mb-5">
    <?php if (isset($_SESSION['erro'])): ?>
        <div class="alert alert-danger py-2"><?= $_SESSION['erro']; unset($_SESSION['erro']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['sucesso'])): ?>
        <div class="alert alert-success py-2"><?= $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2>Inventário de Peças</h2>
            <p class="text-muted small mb-0">
                <?= $e_mecanico ? '🛠️ Acesso Total (Gestão de Stock)' : '👁️ Modo de Visualização (Consulta)' ?>
            </p>
        </div>
        
        <?php if ($e_mecanico): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaPeca">
                + Nova Peça
            </button>
        <?php endif; ?>
    </div>

    <!-- Campo de Pesquisa em Tempo Real -->
    <div class="row mb-3">
        <div class="col-md-4">
            <input type="text" id="inputPesquisa" class="form-control" placeholder="🔍 Pesquisar peça por nome ou referência...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped bg-white shadow-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Nome da Peça</th>
                    <th>Referência</th>
                    <th>Qtd. Stock</th>
                    <th>Preço Unitário (€)</th>
                    <th>Stock Mínimo</th>
                    <?php if ($e_mecanico): ?><th>Ações</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="tabelaPecas">
                <?php if (count($pecas) > 0): ?>
                    <?php $posicao = 1; ?>
                    <?php foreach ($pecas as $p): ?>
                        <?php $stockBaixo = ($p['quantidade'] <= $p['stock_minimo']); ?>
                        <tr>
                            <td><strong><?= $posicao++ ?>º</strong></td>
                            <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                            <td><code><?= htmlspecialchars($p['referencia'] ?: 'N/A') ?></code></td>
                            <td>
                                <span class="badge <?= $stockBaixo ? 'bg-danger' : 'bg-success' ?> fs-6">
                                    <?= $p['quantidade'] ?> <?= $stockBaixo ? '⚠️ (Baixo)' : '' ?>
                                </span>
                            </td>
                            <td><?= number_format($p['preco_unitario'], 2, ',', ' ') ?> €</td>
                            <td><?= $p['stock_minimo'] ?></td>
                            
                            <?php if ($e_mecanico): ?>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#modalEditarPeca<?= $p['id'] ?>">
                                        Editar
                                    </button>
                                    <a href="pecas.php?eliminar=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem a certeza que deseja remover esta peça do inventário?');">
                                        Eliminar
                                    </a>

                                    <!-- Modal Editar Peça -->
                                    <div class="modal fade" id="modalEditarPeca<?= $p['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" class="modal-content text-start">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Editar Peça</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="editar_peca" value="1">
                                                    <input type="hidden" name="peca_id" value="<?= $p['id'] ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label">Nome da Peça</label>
                                                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($p['nome']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Referência</label>
                                                        <input type="text" name="referencia" class="form-control" value="<?= htmlspecialchars($p['referencia']) ?>">
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Quantidade</label>
                                                            <input type="number" name="quantidade" class="form-control" value="<?= $p['quantidade'] ?>" required min="0">
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Preço (€)</label>
                                                            <input type="number" step="0.01" name="preco_unitario" class="form-control" value="<?= $p['preco_unitario'] ?>" required min="0">
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Stock Mín.</label>
                                                            <input type="number" name="stock_minimo" class="form-control" value="<?= $p['stock_minimo'] ?>" required min="0">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary">Guardar Alterações</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $e_mecanico ? '7' : '6' ?>" class="text-center text-muted py-4">Nenhuma peça registada no inventário.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Adicionar Peça -->
<?php if ($e_mecanico): ?>
<div class="modal fade" id="modalNovaPeca" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Adicionar Peça ao Inventário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="adicionar_peca" value="1">

                <div class="mb-3">
                    <label class="form-label">Nome da Peça</label>
                    <input type="text" name="nome" class="form-control" required placeholder="Ex: Calços de Travão Dianteiros">
                </div>
                <div class="mb-3">
                    <label class="form-label">Referência / Código</label>
                    <input type="text" name="referencia" class="form-control" placeholder="Ex: REF-98745">
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Quantidade</label>
                        <input type="number" name="quantidade" class="form-control" required value="1" min="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Preço Unit. (€)</label>
                        <input type="number" step="0.01" name="preco_unitario" class="form-control" required placeholder="0.00" min="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Stock Mínimo</label>
                        <input type="number" name="stock_minimo" class="form-control" required value="5" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Registar Peça</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('inputPesquisa').addEventListener('keyup', function() {
        const termo = this.value.toLowerCase();
        const linhas = document.querySelectorAll('#tabelaPecas tr');

        linhas.forEach(linha => {
            const texto = linha.textContent.toLowerCase();
            if (texto.includes(termo)) {
                linha.style.display = '';
            } else {
                linha.style.display = 'none';
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const alertas = document.querySelectorAll('.alert');
        alertas.forEach(function(alerta) {
            setTimeout(function() {
                alerta.style.transition = "opacity 0.5s ease";
                alerta.style.opacity = "0";
                setTimeout(function() { alerta.remove(); }, 500);
            }, 4000);
        });
    });
    // Aplica o tema guardado assim que a página carrega
(function() {
    const temaGuardado = localStorage.getItem('tema_autofix') || 'light';
    document.documentElement.setAttribute('data-bs-theme', temaGuardado);
})();

// Função para alternar o tema
function alternarTema() {
    const temaAtual = document.documentElement.getAttribute('data-bs-theme');
    const novoTema = (temaAtual === 'dark') ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-bs-theme', novoTema);
    localStorage.setItem('tema_autofix', novoTema);
    
    // Atualiza o ícone do botão se existir
    const btnIcon = document.getElementById('btnTemaIcone');
    if (btnIcon) {
        btnIcon.textContent = (novoTema === 'dark') ? '☀️' : '🌙';
    }
}
</script>
</body>
</html>