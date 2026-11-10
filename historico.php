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

$e_chefe    = ($tipo_user === 'chefe');
$e_mecanico = ($tipo_user === 'mecanico');
$e_cliente  = ($tipo_user === 'cliente');
$e_chefe_ou_mecanico = ($e_chefe || $e_mecanico);

// Processar Alteração do Estado de Pagamento (Apenas Chefe e Mecânico)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_pagamento']) && $e_chefe_ou_mecanico) {
    $reparacao_id  = (int)($_POST['reparacao_id'] ?? 0);
    $novo_estado_pago = (int)($_POST['novo_estado_pago'] ?? 0);

    if ($reparacao_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE reparacoes SET pago = ? WHERE id = ?");
            $stmt->execute([$novo_estado_pago, $reparacao_id]);
            
            $msgStatus = ($novo_estado_pago === 1) ? "marcado como PAGO!" : "marcado como PENDENTE DE PAGAMENTO!";
            $_SESSION['sucesso'] = "Serviço #{$reparacao_id} {$msgStatus}";
        } catch (\PDOException $e) {
            $_SESSION['erro'] = "Erro ao atualizar pagamento: " . $e->getMessage();
        }
    }
    header("Location: historico.php");
    exit();
}

// Obter Histórico de Serviços Concluídos
if ($e_cliente) {
    // Clientes veem apenas o histórico dos seus próprios veículos
    $stmtH = $pdo->prepare("
        SELECT r.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome, c.email AS cliente_email, c.nif AS cliente_nif
        FROM reparacoes r
        JOIN veiculos v ON r.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        WHERE c.email = (SELECT email FROM utilizadores WHERE id = ?) AND r.estado = 'Concluído'
        ORDER BY r.data_fim DESC, r.id DESC
    ");
    $stmtH->execute([$utilizador_id]);
    $historico = $stmtH->fetchAll();
} else {
    // Chefe e Mecânico veem todo o histórico de serviços concluídos
    $historico = $pdo->query("
        SELECT r.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome, c.email AS cliente_email, c.nif AS cliente_nif
        FROM reparacoes r
        JOIN veiculos v ON r.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        WHERE r.estado = 'Concluído'
        ORDER BY r.data_fim DESC, r.id DESC
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Histórico de Serviços</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        @media print {
            body * { visibility: hidden; }
            .modal.show .modal-content * { visibility: visible; }
            .modal.show .modal-content { position: absolute; left: 0; top: 0; width: 100%; }
            .btn-close, .modal-footer { display: none !important; }
        }
    </style>
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
                <li class="nav-item me-3"><a class="nav-link active" href="historico.php">Histórico</a></li>
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
            <h2>Histórico de Serviços Concluídos</h2>
            <p class="text-muted small mb-0">Registo de reparações finalizadas, controlo de pagamento e faturas</p>
        </div>
    </div>

    <!-- Campo de Pesquisa em Tempo Real -->
    <div class="row mb-3">
        <div class="col-md-4">
            <input type="text" id="inputPesquisaHistorico" class="form-control" placeholder="🔍 Pesquisar por cliente, matrícula ou serviço...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped bg-white shadow-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Data de Conclusão</th>
                    <th>Cliente</th>
                    <th>Veículo</th>
                    <th>Descrição do Serviço</th>
                    <th>Valor Total (€)</th>
                    <th>Estado de Pagamento</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaHistoricoBody">
                <?php if (count($historico) > 0): ?>
                    <?php $numOrdem = 1; ?>
                    <?php foreach ($historico as $h): ?>
                        <?php
                        $totalComIVA = (float)($h['valor'] ?? 0);
                        $baseImponivel = $totalComIVA / 1.23;
                        $valorIVA = $totalComIVA - $baseImponivel;
                        $estaPago = ((int)($h['pago'] ?? 0) === 1);
                        ?>
                        <tr>
                            <td><strong><?= $numOrdem++ ?>º</strong></td>
                            <td>
                                🗓️ <?= $h['data_fim'] ? date('d/m/Y H:i', strtotime($h['data_fim'])) : 'N/A' ?>
                            </td>
                            <td><strong><?= htmlspecialchars($h['cliente_nome']) ?></strong></td>
                            <td>
                                🚘 <strong><?= htmlspecialchars($h['marca'] . ' ' . $h['modelo']) ?></strong><br>
                                <code><?= htmlspecialchars($h['matricula']) ?></code>
                            </td>
                            <td><?= nl2br(htmlspecialchars($h['descricao'])) ?></td>
                            <td class="text-success fw-bold"><?= number_format($totalComIVA, 2, ',', ' ') ?> €</td>
                            <td>
                                <?php if ($estaPago): ?>
                                    <span class="badge bg-success fs-6">🟢 Pago</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark fs-6">🔴 Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php if ($e_chefe_ou_mecanico): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="alterar_pagamento" value="1">
                                            <input type="hidden" name="reparacao_id" value="<?= $h['id'] ?>">
                                            <input type="hidden" name="novo_estado_pago" value="<?= $estaPago ? 0 : 1 ?>">
                                            <button type="submit" class="btn btn-sm <?= $estaPago ? 'btn-outline-warning' : 'btn-outline-success' ?> me-1" title="Alternar Estado de Pagamento">
                                                <?= $estaPago ? 'Marcar Pendente' : 'Marcar Pago' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalFatura<?= $h['id'] ?>">
                                        🧾 Fatura
                                    </button>
                                </div>

                                <!-- Modal Fatura / Recibo -->
                                <div class="modal fade" id="modalFatura<?= $h['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content text-start">
                                            <div class="modal-header bg-dark text-white">
                                                <h5 class="modal-title fw-bold">🧾 Fatura / Comprovativo #FT-<?= str_pad($h['id'], 5, '0', STR_PAD_LEFT) ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row mb-4">
                                                    <div class="col-6">
                                                        <h4 class="fw-bold text-primary mb-1">AutoFix Oficina</h4>
                                                        <p class="small text-muted mb-0">Manutenção & Reparação Automóvel</p>
                                                        <p class="small text-muted mb-0">NIF: 500 123 456</p>
                                                        <p class="small text-muted">Portugal</p>
                                                    </div>
                                                    <div class="col-6 text-end">
                                                        <h5 class="fw-bold">FATURA SIMPLIFICADA</h5>
                                                        <p class="mb-1"><strong>Nº:</strong> FT-<?= str_pad($h['id'], 5, '0', STR_PAD_LEFT) ?></p>
                                                        <p class="mb-1"><strong>Data:</strong> <?= $h['data_fim'] ? date('d/m/Y', strtotime($h['data_fim'])) : date('d/m/Y') ?></p>
                                                        <p class="mb-0">
                                                            <strong>Estado:</strong> 
                                                            <?php if ($estaPago): ?>
                                                                <span class="badge bg-success">PAGO</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark">PENDENTE DE PAGAMENTO</span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <hr>

                                                <div class="row mb-4">
                                                    <div class="col-6">
                                                        <h6 class="fw-bold text-secondary">Dados do Cliente</h6>
                                                        <p class="mb-1"><strong>Nome:</strong> <?= htmlspecialchars($h['cliente_nome']) ?></p>
                                                        <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($h['cliente_email'] ?? 'N/A') ?></p>
                                                        <p class="mb-0"><strong>NIF:</strong> <?= htmlspecialchars($h['cliente_nif'] ?: 'Consumidor Final') ?></p>
                                                    </div>
                                                    <div class="col-6 text-end">
                                                        <h6 class="fw-bold text-secondary">Dados do Veículo</h6>
                                                        <p class="mb-1"><strong>Marca/Modelo:</strong> <?= htmlspecialchars($h['marca'] . ' ' . $h['modelo']) ?></p>
                                                        <p class="mb-0"><strong>Matrícula:</strong> <code><?= htmlspecialchars($h['matricula']) ?></code></p>
                                                    </div>
                                                </div>

                                                <table class="table table-bordered mb-4">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Descrição do Serviço / Peças</th>
                                                            <th class="text-end" style="width: 150px;">Valor Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td><?= nl2br(htmlspecialchars($h['descricao'])) ?></td>
                                                            <td class="text-end"><?= number_format($totalComIVA, 2, ',', ' ') ?> €</td>
                                                        </tr>
                                                    </tbody>
                                                </table>

                                                <div class="row">
                                                    <div class="col-6 text-muted small">
                                                        <p class="mb-1">Processado por programa certificado AutoFix.</p>
                                                        <p class="mb-0">Obrigado pela sua preferência!</p>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="bg-light p-3 rounded border text-end">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span>Base Imponível:</span>
                                                                <span><?= number_format($baseImponivel, 2, ',', ' ') ?> €</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span>IVA (23%):</span>
                                                                <span><?= number_format($valorIVA, 2, ',', ' ') ?> €</span>
                                                            </div>
                                                            <hr class="my-2">
                                                            <div class="d-flex justify-content-between fw-bold fs-5 text-success">
                                                                <span>Total:</span>
                                                                <span><?= number_format($totalComIVA, 2, ',', ' ') ?> €</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                                <button type="button" class="btn btn-success" onclick="window.print();">🖨️ Imprimir / Guardar PDF</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Nenhum serviço concluído no histórico.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Pesquisa Rápida em tempo real
    const inputPesquisa = document.getElementById('inputPesquisaHistorico');
    if (inputPesquisa) {
        inputPesquisa.addEventListener('keyup', function() {
            const termo = this.value.toLowerCase();
            const linhas = document.querySelectorAll('#tabelaHistoricoBody tr');

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

    // Auto-dismiss dos alertas
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