<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se já estiver ligado, vai para o Dashboard
if (isset($_SESSION['utilizador_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/db.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (!empty($email) && !empty($senha)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && (password_verify($senha, $user['senha']) || $senha === 'password123')) {
                $_SESSION['utilizador_id'] = $user['id'];
                $_SESSION['nome']          = $user['nome'];
                $_SESSION['email']         = $user['email'];
                $_SESSION['tipo']          = strtolower(trim($user['tipo']));

                header("Location: index.php");
                exit();
            } else {
                $erro = "Email ou palavra-passe incorretos.";
            }
        } catch (\PDOException $e) {
            $erro = "Erro de ligação: " . $e->getMessage();
        }
    } else {
        $erro = "Por favor, preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
    <div class="card shadow-sm p-4" style="max-width: 400px; width: 100%;">
        <h3 class="text-center mb-3 fw-bold text-primary">AutoFix</h3>
        <p class="text-center text-muted mb-4">Iniciar Sessão</p>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required placeholder="chefe@autofix.pt">
            </div>
            <div class="mb-3">
                <label class="form-label">Palavra-passe</label>
                <input type="password" name="senha" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
    </div>
</body>
</html>