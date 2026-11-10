<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db/db.php';

// 1. Processar Pedido de Alteração de Palavra-passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_password'])) {
    $email        = strtolower(trim($_POST['email_alterar'] ?? ''));
    $passeAntiga  = $_POST['passe_antiga'] ?? '';
    $novaPasse    = $_POST['nova_passe'] ?? '';
    $confirmaPass = $_POST['confirma_passe'] ?? '';

    if (!empty($email) && !empty($passeAntiga) && !empty($novaPasse)) {
        if ($novaPasse !== $confirmaPass) {
            $_SESSION['erro'] = "A nova palavra-passe e a confirmação não coincidem.";
        } else {
            // Verificar se o utilizador existe
            $stmtU = $pdo->prepare("SELECT * FROM utilizadores WHERE email = ?");
            $stmtU->execute([$email]);
            $user = $stmtU->fetch();

            if ($user) {
                // Suporte flexível para a coluna 'password' ou 'senha'
                $hashAtual = $user['password'] ?? $user['senha'] ?? '';

                if (password_verify($passeAntiga, $hashAtual)) {
                    $novoHash = password_hash($novaPasse, PASSWORD_DEFAULT);

                    try {
                        // Atualiza no campo correto dependendo da estrutura da tabela
                        if (array_key_exists('password', $user)) {
                            $stmtUp = $pdo->prepare("UPDATE utilizadores SET password = ? WHERE email = ?");
                        } else {
                            $stmtUp = $pdo->prepare("UPDATE utilizadores SET senha = ? WHERE email = ?");
                        }
                        $stmtUp->execute([$novoHash, $email]);

                        $_SESSION['sucesso'] = "Palavra-passe alterada com sucesso! Já pode iniciar sessão.";
                    } catch (\PDOException $e) {
                        $_SESSION['erro'] = "Erro ao atualizar palavra-passe: " . $e->getMessage();
                    }
                } else {
                    $_SESSION['erro'] = "A palavra-passe atual está incorreta.";
                }
            } else {
                $_SESSION['erro'] = "Nenhuma conta encontrada com o e-mail fornecido.";
            }
        }
    } else {
        $_SESSION['erro'] = "Por favor, preencha todos os campos do formulário de alteração.";
    }
    header("Location: login.php");
    exit();
}

// 2. Processar Autenticação (Login)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iniciar_sessao'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (!empty($email) && !empty($pass)) {
        $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $hashBD = $user['password'] ?? $user['senha'] ?? '';

        if ($user && password_verify($pass, $hashBD)) {
            $_SESSION['utilizador_id'] = $user['id'];
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['nome']          = $user['nome'];
            $_SESSION['tipo']          = $user['tipo'];
            $_SESSION['user_tipo']     = $user['tipo'];

            header("Location: index.php");
            exit();
        } else {
            $_SESSION['erro'] = "Credenciais inválidas. Verifique o e-mail e a palavra-passe.";
        }
    } else {
        $_SESSION['erro'] = "Preencha o e-mail e a palavra-passe.";
    }
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 text-center">
                    <img src="img/autofix.jpg" alt="AutoFix Logo" class="img-fluid rounded mb-3" style="max-width: 180px;">
                    <h4 class="fw-bold mb-3">Acesso ao Sistema</h4>

                    <?php if (isset($_SESSION['erro'])): ?>
                        <div class="alert alert-danger py-2 small"><?= $_SESSION['erro']; unset($_SESSION['erro']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sucesso'])): ?>
                        <div class="alert alert-success py-2 small"><?= $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" class="text-start">
                        <input type="hidden" name="iniciar_sessao" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">E-mail</label>
                            <input type="email" name="email" class="form-control" required placeholder="seu@email.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Palavra-passe</label>
                            <input type="password" name="password" class="form-control" required placeholder="••••••••">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3">Entrar</button>
                    </form>

                    <div class="text-center border-top pt-2">
                        <button type="button" class="btn btn-link btn-sm text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalAlterarPasse">
                            Esqueceu-se ou quer alterar a palavra-passe?
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Alterar Palavra-passe -->
<div class="modal fade" id="modalAlterarPasse" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="login.php" class="modal-content text-start">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Alterar Palavra-passe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="alterar_password" value="1">

                <div class="mb-3">
                    <label class="form-label">E-mail da Conta</label>
                    <input type="email" name="email_alterar" class="form-control" required placeholder="cliente@email.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Palavra-passe Atual (ou Temporária)</label>
                    <input type="password" name="passe_antiga" class="form-control" required placeholder="Ex: AutoFix123">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nova Palavra-passe</label>
                    <input type="password" name="nova_passe" class="form-control" required placeholder="Nova palavra-passe">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar Nova Palavra-passe</label>
                    <input type="password" name="confirma_passe" class="form-control" required placeholder="Repita a nova palavra-passe">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Guardar Nova Palavra-passe</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script para os alertas desaparecerem automaticamente após 4 segundos -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const alertas = document.querySelectorAll('.alert');
        
        alertas.forEach(function(alerta) {
            setTimeout(function() {
                alerta.style.transition = "opacity 0.5s ease";
                alerta.style.opacity = "0";
                
                setTimeout(function() {
                    alerta.remove();
                }, 500);
            }, 4000);
        });
    });
</script>
</body>
</html>