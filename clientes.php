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

$e_chefe = ($tipo_user === 'chefe');
$e_chefe_ou_mecanico = ($tipo_user === 'chefe' || $tipo_user === 'mecanico');

// 1. Criar Perfil de Cliente + Utilizador (Exclusivo do Chefe)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_cliente']) && $e_chefe) {
    $nome     = trim($_POST['nome'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $nif      = trim($_POST['nif'] ?? '');
    $pass     = $_POST['password'] ?? '';

    if (!empty($nome) && !empty($email) && !empty($pass)) {
        try {
            // Verificar se o e-mail já existe
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM utilizadores WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetchColumn() > 0) {
                $_SESSION['erro'] = "O e-mail '{$email}' já está registado!";
            } else {
                $pdo->beginTransaction();

                // Criar Registo na Tabela Clientes
                $stmtC = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, nif) VALUES (?, ?, ?, ?)");
                $stmtC->execute([$nome, $email, $telefone, $nif]);

                // Criar Registo na Tabela Utilizadores (Login)
                $passHash = password_hash($pass, PASSWORD_DEFAULT);
                $stmtU = $pdo->prepare("INSERT INTO utilizadores (nome, email, password, tipo) VALUES (?, ?, ?, 'cliente')");
                $stmtU->execute([$nome, $email, $passHash]);

                $pdo->commit();
                $_SESSION['sucesso'] = "Cliente '{$nome}' criado com sucesso!";
            }
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['erro'] = "Erro ao criar cliente: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Preencha o Nome, E-mail e Password obrigatórios.";
    }
    header("Location: clientes.php");
    exit();
}

// 2. Eliminar Cliente Completo (Exclusivo do Chefe)
if (isset($_GET['eliminar']) && $e_chefe) {
    $cliente_id = (int)$_GET['eliminar'];
    
    $stmtEmail = $pdo->prepare("SELECT email FROM clientes WHERE id = ?");
    $stmtEmail->execute([$cliente_id]);
    $emailCliente = $stmtEmail->fetchColumn();

    if ($emailCliente) {
        $stmtDelUser = $pdo->prepare("DELETE FROM utilizadores WHERE email = ?");
        $stmtDelUser->execute([$emailCliente]);
    }

    $stmtDel = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
    $stmtDel->execute([$cliente_id]);

    $_SESSION['sucesso'] = "Perfil de cliente eliminado com sucesso.";
    header("Location: clientes.php");
    exit();
}

// 3. Eliminar Veículo Específico (Chefe e Mecânico)
if (isset($_GET['eliminar_veiculo']) && $e_chefe_ou_mecanico) {
    $veiculo_id = (int)$_GET['eliminar_veiculo'];

    try {
        $stmtDelV = $pdo->prepare("DELETE FROM veiculos WHERE id = ?");
        $stmtDelV->execute([$veiculo_id]);

        $_SESSION['sucesso'] = "Veículo removido com sucesso!";
    } catch (\PDOException $e) {
        $_SESSION['erro'] = "Erro ao remover veículo: " . $e->getMessage();
    }
    header("Location: clientes.php");
    exit();
}

// 4. Adicionar Veículo (Chefe e Mecânico)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_veiculo']) && $e_chefe_ou_mecanico) {
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $marca      = trim($_POST['marca'] ?? '');
    $modelo     = trim($_POST['modelo'] ?? '');
    $matricula  = strtoupper(trim($_POST['matricula'] ?? ''));

    if ($cliente_id > 0 && !empty($marca) && !empty($modelo) && !empty($matricula)) {
        
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM veiculos WHERE UPPER(REPLACE(matricula, ' ', '')) = UPPER(REPLACE(?, ' ', ''))");
        $stmtCheck->execute([$matricula]);
        $jaExiste = $stmtCheck->fetchColumn();

        if ($jaExiste > 0) {
            $_SESSION['erro'] = "A matrícula '{$matricula}' já se encontra registada no sistema!";
        } else {
            try {
                $stmtV = $pdo->prepare("INSERT INTO veiculos (cliente_id, marca, modelo, matricula) VALUES (?, ?, ?, ?)");
                $stmtV->execute([$cliente_id, $marca, $modelo, $matricula]);
                $_SESSION['sucesso'] = "Veículo '{$marca} {$modelo}' ({$matricula}) adicionado com sucesso!";
            } catch (\PDOException $e) {
                $_SESSION['erro'] = "Erro MySQL: " . $e->getMessage();
            }
        }

    } else {
        $_SESSION['erro'] = "Por favor, preencha todos os campos obrigatórios.";
    }
    header("Location: clientes.php");
    exit();
}

// Obter Lista de Clientes
$clientes = $pdo->query("SELECT * FROM clientes ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Clientes & Veículos</title>
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
                <li class="nav-item me-3"><a class="nav-link active" href="clientes.php">Clientes & Veículos</a></li>
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
        <h2>Gestão de Clientes e Parque de Veículos</h2>
        
        <?php if ($e_chefe): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoCliente">
                + Criar Perfil de Cliente
            </button>
        <?php endif; ?>
    </div>

    <!-- Pesquisa Rápida (Exclusiva do Chefe e Mecânico) -->
    <?php if ($e_chefe_ou_mecanico): ?>
        <div class="row mb-3">
            <div class="col-md-4">
                <input type="text" id="inputPesquisaClientes" class="form-control" placeholder="🔍 Pesquisar por cliente, NIF, e-mail ou veículo...">
            </div>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-striped bg-white shadow-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Nome do Cliente</th>
                    <th>Contacto</th>
                    <th>NIF</th>
                    <th>Veículos Registados</th>
                    <?php if ($e_chefe_ou_mecanico): ?><th>Ações</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="tabelaClientesBody">
                <?php if (count($clientes) > 0): ?>
                    <?php $numOrdem = 1; ?>
                    <?php foreach ($clientes as $c): ?>
                        <?php
                        $stmtV = $pdo->prepare("SELECT * FROM veiculos WHERE cliente_id = ? ORDER BY id DESC");
                        $stmtV->execute([$c['id']]);
                        $veiculos = $stmtV->fetchAll();
                        ?>
                        <tr>
                            <td><strong><?= $numOrdem++ ?>º</strong></td>
                            <td>
                                <strong><?= htmlspecialchars($c['nome']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($c['email']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($c['telefone'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($c['nif'] ?: 'N/A') ?></td>
                            <td>
                                <?php if (count($veiculos) > 0): ?>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($veiculos as $v): ?>
                                            <li class="mb-1 d-flex justify-content-between align-items-center">
                                                <span>🚘 <strong><?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?></strong> (<code><?= htmlspecialchars($v['matricula']) ?></code>)</span>
                                                <?php if ($e_chefe_ou_mecanico): ?>
                                                    <a href="clientes.php?eliminar_veiculo=<?= $v['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-1 ms-2" onclick="return confirm('Remover o veículo <?= htmlspecialchars($v['matricula']) ?>?');" title="Remover Veículo">✕</a>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="text-muted small">Sem veículos associados.</span>
                                <?php endif; ?>
                            </td>
                            
                            <?php if ($e_chefe_ou_mecanico): ?>
                                <td>
                                    <button class="btn btn-sm btn-outline-success me-1" data-bs-toggle="modal" data-bs-target="#modalAdicionarVeiculo<?= $c['id'] ?>">
                                        + Veículo
                                    </button>
                                    
                                    <?php if ($e_chefe): ?>
                                        <a href="clientes.php?eliminar=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ATENÇÃO: Apagar este cliente removerá também a sua conta de utilizador. Continuar?');">
                                            Eliminar
                                        </a>
                                    <?php endif; ?>

                                    <!-- Modal Adicionar Veículo -->
                                    <div class="modal fade text-start" id="modalAdicionarVeiculo<?= $c['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Adicionar Veículo a <?= htmlspecialchars($c['nome']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="adicionar_veiculo" value="1">
                                                    <input type="hidden" name="cliente_id" value="<?= $c['id'] ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label">Marca</label>
                                                        <input type="text" name="marca" class="form-control" required placeholder="Ex: Volkswagen">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Modelo</label>
                                                        <input type="text" name="modelo" class="form-control" required placeholder="Ex: Golf 1.9 TDI">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Matrícula</label>
                                                        <input type="text" name="matricula" class="form-control" required placeholder="Ex: 45-AB-89">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-success">Guardar Veículo</button>
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
                        <td colspan="<?= $e_chefe_ou_mecanico ? '6' : '5' ?>" class="text-center text-muted py-4">Nenhum cliente registado no sistema.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Novo Cliente (Exclusivo do Chefe) -->
<?php if ($e_chefe): ?>
<div class="modal fade" id="modalNovoCliente" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content text-start">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Criar Novo Perfil de Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="criar_cliente" value="1">

                <div class="mb-3">
                    <label class="form-label fw-bold">Nome Completo</label>
                    <input type="text" name="nome" class="form-control" required placeholder="Ex: Carlos Silva">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Endereço de E-mail</label>
                    <input type="email" name="email" class="form-control" required placeholder="Ex: carlos@email.com">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Password para Acesso</label>
                    <input type="password" name="password" class="form-control" required placeholder="Mínimo 6 caracteres">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Telefone</label>
                        <input type="text" name="telefone" class="form-control" placeholder="Ex: 912345678">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">NIF</label>
                        <input type="text" name="nif" class="form-control" placeholder="Ex: 123456789">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registar Cliente</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const inputPesquisa = document.getElementById('inputPesquisaClientes');
    if (inputPesquisa) {
        inputPesquisa.addEventListener('keyup', function() {
            const termo = this.value.toLowerCase();
            const linhas = document.querySelectorAll('#tabelaClientesBody tr');

            linhas.forEach(linha => {
                const texto = linha.textContent.toLowerCase();
                if (texto.includes(termo)) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            });
        });
    }

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