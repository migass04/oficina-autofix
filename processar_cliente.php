<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

// Apenas o Chefe tem acesso a processar novos clientes
$tipo_user = strtolower(trim($_SESSION['tipo'] ?? $_SESSION['user_tipo'] ?? ''));
if ($tipo_user !== 'chefe') {
    header("Location: clientes.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $telefone = trim($_POST['telefone'] ?? '');
    $nif      = trim($_POST['nif'] ?? '');

    if (!empty($nome) && !empty($email)) {
        try {
            $pdo->beginTransaction();

            // 1. Inserir na tabela de clientes
            $stmtC = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, nif) VALUES (?, ?, ?, ?)");
            $stmtC->execute([$nome, $email, $telefone, $nif]);

            // 2. Definir Palavra-passe Padrão Inicial
            $passePadrao = 'AutoFix123';
            $hashPasse   = password_hash($passePadrao, PASSWORD_DEFAULT);

            // 3. Criar conta na tabela utilizadores (utilizando a coluna 'senha')
            $stmtU = $pdo->prepare("INSERT INTO utilizadores (nome, email, senha, tipo) VALUES (?, ?, ?, 'cliente')");
            $stmtU->execute([$nome, $email, $hashPasse]);

            $pdo->commit();
            $_SESSION['sucesso'] = "Perfil de cliente criado com sucesso! Palavra-passe inicial: <strong>{$passePadrao}</strong>";
        } catch (\PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $_SESSION['erro'] = "O email '{$email}' já se encontra registado no sistema.";
            } else {
                $_SESSION['erro'] = "Erro ao criar conta de cliente: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['erro'] = "Por favor, preencha pelo menos o Nome e o Email.";
    }
}

header("Location: clientes.php");
exit();