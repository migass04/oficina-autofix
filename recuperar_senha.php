<?php
session_start();
require_once 'config/db.php';

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmtUpdate = $pdo->prepare("UPDATE utilizadores SET reset_token = ?, reset_token_expira = ? WHERE id = ?");
        $stmtUpdate->execute([$token, $expira, $user['id']]);

        $link = "redefinir_senha.php?token=" . $token;
        $mensagem = "Pedido gerado! Clica aqui para alterar a tua palavra-passe: <a href='$link' class='alert-link'>Redefinir Palavra-passe</a>";
    } else {
        $erro = 'Email não encontrado no sistema.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Recuperar Palavra-passe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center" style="min-height: 100vh;">
<div class="card p-4 shadow" style="width: 400px;">
    <div class="text-center mb-3">
        <img src="img/logo.jpg" alt="AutoFix Logo" class="img-fluid rounded" style="max-width: 100px;">
    </div>
    <h5 class="text-center text-muted mb-4">Recuperar Palavra-passe</h5>

    <?php if ($mensagem): ?>
        <div class="alert alert-success p-2 text-center"><?= $mensagem ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert alert-danger p-2 text-center"><?= $erro ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Email da Conta</label>
            <input type="email" name="email" class="form-control" required placeholder="exemplo@email.com">
        </div>
        <button type="submit" class="btn btn-primary w-100 mb-3">Gerar Pedido de Recuperação</button>
    </form>

    <div class="text-center">
        <a href="login.php" class="text-decoration-none small">← Voltar ao Login</a>
    </div>
</div>
</body>
</html>