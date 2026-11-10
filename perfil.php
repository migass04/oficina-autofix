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

// 1. Obter dados do utilizador
$stmtUser = $pdo->prepare("SELECT * FROM utilizadores WHERE id = ?");
$stmtUser->execute([$utilizador_id]);
$user = $stmtUser->fetch();

if (!$user) {
    header("Location: logout.php");
    exit();
}

// 2. Processar Atualização de Dados e Palavra-passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil'])) {
    $nome           = trim($_POST['nome'] ?? '');
    $senha_atual    = $_POST['senha_atual'] ?? '';
    $nova_senha     = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if (!empty($nome)) {
        try {
            // Atualizar Nome na tabela utilizadores
            $stmtUp = $pdo->prepare("UPDATE utilizadores SET nome = ? WHERE id = ?");
            $stmtUp->execute([$nome, $utilizador_id]);
            $_SESSION['nome'] = $nome;

            // Se for cliente, atualizar também na tabela clientes
            if ($tipo_user === 'cliente') {
                $stmtCl = $pdo->prepare("UPDATE clientes SET nome = ? WHERE email = ?");
                $stmtCl->execute([$nome, $user['email']]);
            }

            $_SESSION['sucesso'] = "Dados pessoais atualizados com sucesso!";
        } catch (\PDOException $e) {
            $_SESSION['erro'] = "Erro ao atualizar dados: " . $e->getMessage();
        }
    }

    // Se o utilizador preencheu o campo de nova senha
    if (!empty($nova_senha)) {
        if (password_verify($senha_atual, $user['senha']) || $senha_atual === $user['senha']) { // Suporte a hash e texto limpo legado
            if ($nova_senha === $confirmar_senha) {
                $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmtPass = $pdo->prepare("UPDATE utilizadores SET senha = ? WHERE id = ?");
                $stmtPass->execute([$nova_senha_hash, $utilizador_id]);
                $_SESSION['sucesso'] = "Palavra-passe alterada com sucesso!";
            } else {
                $_SESSION['erro'] = "A nova palavra-passe e a confirmação não coincidem.";
            }
        } else {
            $_SESSION['erro'] = "A palavra-passe atual está incorreta.";
        }
    }

    header("Location: perfil.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - O Meu Perfil</title>
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
                <li class="nav-item me-3"><a class="nav-link" href="pecas.php">Inventário de Peças</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="agendamentos.php">Agendamentos</a></li>
                <li class="nav-item me-3"><a class="nav-link" href="historico.php">Histórico</a></li>
            </ul>
            <!-- Exemplo de como fica a zona do perfil na Navbar -->
            <div class="d-flex align-items-center">
            <!-- Botão Dark Mode -->
            <button type="button" class="btn btn-outline-light btn-sm me-2" onclick="alternarTema()" title="Alternar Modo Escuro/Claro">
            <span id="btnTemaIcone">🌙</span>
            </button>

            <a href="perfil.php" class="btn btn-outline-light btn-sm me-2">👤 O Meu Perfil</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Sair</a>
            </div>
    </div>
</nav>
<!-- Conteúdo Principal -->
<div class="container mb-5" style="max-width: 800px;">
    <?php if (isset($_SESSION['erro'])): ?>
        <div class="alert alert-danger py-2"><?= $_SESSION['erro']; unset($_SESSION['erro']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['sucesso'])): ?>
        <div class="alert alert-success py-2"><?= $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 bg-white">
        <div class="card-header bg-dark text-white fw-bold">
            👤 O Meu Perfil - AutoFix
        </div>
        <div class="card-body p-4">
            <form method="POST">
                <input type="hidden" name="atualizar_perfil" value="1">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nome Completo</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($user['nome']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Endereço de E-mail</label>
                        <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                        <small class="text-muted">O e-mail não pode ser alterado diretamente.</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Tipo de Perfil / Cargo</label>
                    <input type="text" class="form-control bg-light text-capitalize fw-bold" value="<?= htmlspecialchars($tipo_user) ?>" readonly>
                </div>

                <hr class="my-4">

                <h5 class="fw-bold mb-3">Alterar Palavra-passe</h5>
                <p class="text-muted small">Deixe estes campos em branco caso não pretenda alterar a sua palavra-passe.</p>

                <div class="mb-3">
                    <label class="form-label">Palavra-passe Atual</label>
                    <input type="password" name="senha_atual" class="form-control" placeholder="••••••••">
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nova Palavra-passe</label>
                        <input type="password" name="nova_senha" class="form-control" placeholder="••••••••">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Confirmar Nova Palavra-passe</label>
                        <input type="password" name="confirmar_senha" class="form-control" placeholder="••••••••">
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4">Guardar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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