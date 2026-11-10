<?php
session_start();
require_once 'config/db.php';

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $tipo = trim($_POST['tipo']);

    $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        $erro = 'Este email já está registado!';
    } else {
        $senha_hash = password_hash($senha, PASSWORD_BCRYPT);

        try {
            $stmt = $pdo->prepare("INSERT INTO utilizadores (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $email, $senha_hash, $tipo]);

            if ($tipo === 'cliente') {
                $nif = trim($_POST['nif'] ?? '');
                $telefone = trim($_POST['telefone'] ?? '');
                
                $stmtCliente = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, nif) VALUES (?, ?, ?, ?)");
                $stmtCliente->execute([$nome, $email, $telefone, $nif]);
            }

            $mensagem = 'Conta criada com sucesso! Já podes fazer login.';
        } catch (PDOException $e) {
            $erro = 'Erro ao criar conta: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Registar Conta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark d-flex align-items-center justify-content-center" style="min-height: 100vh; padding: 20px 0;">
<div class="card p-4 shadow" style="width: 420px;">
    <div class="text-center mb-3">
        <img src="img/autofix.jpg" alt="AutoFix Logo" class="img-fluid rounded" style="max-width: 150px;">
    </div>
    
    <?php if ($mensagem): ?>
        <div class="alert alert-success p-2 text-center"><?= $mensagem ?><br><a href="login.php" class="btn btn-sm btn-primary mt-2">Ir para Login</a></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert alert-danger p-2 text-center"><?= $erro ?></div>
    <?php endif; ?>

    <form method="POST" id="formRegisto">
        <div class="mb-3">
            <label class="form-label">Nome Completo</label>
            <input type="text" name="nome" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Palavra-passe</label>
            <input type="password" name="senha" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Tipo de Utilizador</label>
            <select name="tipo" id="tipoUser" class="form-select" onchange="toggleClienteFields()" required>
                <option value="cliente">Cliente</option>
                <option value="mecanico">Mecânico</option>
                <option value="chefe">Chefe da Empresa</option>
            </select>
        </div>

        <div id="camposCliente">
            <div class="mb-3">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">NIF</label>
                <input type="text" name="nif" class="form-control">
            </div>
        </div>

        <button type="submit" class="btn btn-success w-100">Criar Conta</button>
    </form>
    
    <div class="mt-3 text-center">
        <a href="login.php" class="text-decoration-none small">Já tens conta? Fazer Login</a>
    </div>
</div>

<script>
function toggleClienteFields() {
    const tipo = document.getElementById('tipoUser').value;
    const campos = document.getElementById('camposCliente');
    campos.style.display = tipo === 'cliente' ? 'block' : 'none';
}
toggleClienteFields();
</script>
<!-- Fim do conteúdo PHP / HTML -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script JavaScript para fechar os alertas automaticamente -->
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
            }, 4000); // Desaparece após 4 segundos
        });
    });
</script>
</body>
</html>