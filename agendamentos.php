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

// 1. Cliente ou Oficina cria o Agendamento (Sempre como 'Pendente')
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_agendamento'])) {
    $veiculo_id       = (int)($_POST['veiculo_id'] ?? 0);
    $data_agendamento = trim($_POST['data_agendamento'] ?? '');
    $servico          = trim($_POST['servico'] ?? '');

    if ($veiculo_id > 0 && !empty($data_agendamento) && !empty($servico)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO agendamentos (veiculo_id, data_agendamento, servico, estado) VALUES (?, ?, ?, 'Pendente')");
            $stmt->execute([$veiculo_id, $data_agendamento, $servico]);
            $_SESSION['sucesso'] = "Pedido de agendamento submetido! Aguarde a aprovação da oficina.";
        } catch (\PDOException $e) {
            $_SESSION['erro'] = "Erro ao submeter agendamento: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Por favor, preencha todos os campos obrigatórios.";
    }
    header("Location: agendamentos.php");
    exit();
}

// 2. Chefe ou Mecânico Aceita, Recusa ou Conclui o Agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_estado_agendamento']) && $e_chefe_ou_mecanico) {
    $agendamento_id = (int)($_POST['agendamento_id'] ?? 0);
    $novo_estado    = trim($_POST['estado'] ?? 'Pendente');

    if ($agendamento_id > 0) {
        try {
            if ($novo_estado === 'Concluído') {
                $horas_trabalho = (float)($_POST['horas_trabalho'] ?? 0);
                $custo_pecas    = (float)($_POST['custo_pecas'] ?? 0);

                if ($horas_trabalho <= 0) {
                    $_SESSION['erro'] = "É obrigatório indicar o número de horas de trabalho para concluir!";
                    header("Location: agendamentos.php");
                    exit();
                }

                $valor_total = ($horas_trabalho * 10.00) + $custo_pecas;

                $stmtGet = $pdo->prepare("SELECT veiculo_id, servico FROM agendamentos WHERE id = ?");
                $stmtGet->execute([$agendamento_id]);
                $agendamento = $stmtGet->fetch();

                if ($agendamento) {
                    $pdo->beginTransaction();

                    // Cria a reparação concluída no histórico
                    $stmtRep = $pdo->prepare("INSERT INTO reparacoes (veiculo_id, descricao, estado, valor, data_fim) VALUES (?, ?, 'Concluído', ?, ?)");
                    $stmtRep->execute([$agendamento['veiculo_id'], $agendamento['servico'], $valor_total, date('Y-m-d H:i:s')]);

                    // Atualiza o agendamento
                    $stmtUp = $pdo->prepare("UPDATE agendamentos SET estado = 'Concluído' WHERE id = ?");
                    $stmtUp->execute([$agendamento_id]);

                    $pdo->commit();
                    $_SESSION['sucesso'] = "Agendamento concluído e enviado para o Histórico! Total: " . number_format($valor_total, 2, ',', ' ') . " €";
                }
            } else {
                $stmt = $pdo->prepare("UPDATE agendamentos SET estado = ? WHERE id = ?");
                $stmt->execute([$novo_estado, $agendamento_id]);

                if ($novo_estado === 'Confirmado') {
                    $_SESSION['sucesso'] = "Agendamento aceite com sucesso!";
                } elseif ($novo_estado === 'Cancelado') {
                    $_SESSION['sucesso'] = "Agendamento recusado/cancelado.";
                } else {
                    $_SESSION['sucesso'] = "Estado do agendamento atualizado.";
                }
            }
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['erro'] = "Erro ao atualizar agendamento: " . $e->getMessage();
        }
    }
    header("Location: agendamentos.php");
    exit();
}

// 3. Obter Agendamentos da BD
if ($e_cliente) {
    $stmtA = $pdo->prepare("
        SELECT a.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome
        FROM agendamentos a
        JOIN veiculos v ON a.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        WHERE c.email = (SELECT email FROM utilizadores WHERE id = ?) AND a.estado != 'Concluído'
        ORDER BY a.data_agendamento ASC
    ");
    $stmtA->execute([$utilizador_id]);
    $agendamentos = $stmtA->fetchAll();

    $stmtV = $pdo->prepare("
        SELECT v.id, v.marca, v.modelo, v.matricula 
        FROM veiculos v 
        JOIN clientes c ON v.cliente_id = c.id 
        WHERE c.email = (SELECT email FROM utilizadores WHERE id = ?)
    ");
    $stmtV->execute([$utilizador_id]);
    $veiculosDisponiveis = $stmtV->fetchAll();
} else {
    $agendamentos = $pdo->query("
        SELECT a.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome
        FROM agendamentos a
        JOIN veiculos v ON a.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        WHERE a.estado != 'Concluído'
        ORDER BY a.data_agendamento ASC
    ")->fetchAll();

    $veiculosDisponiveis = $pdo->query("
        SELECT v.id, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome
        FROM veiculos v
        JOIN clientes c ON v.cliente_id = c.id
        ORDER BY c.nome ASC
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Agendamentos</title>
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
                <li class="nav-item me-3"><a class="nav-link active" href="agendamentos.php">Agendamentos</a></li>
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

<div class="container mb-5">
    <?php if (isset($_SESSION['erro'])): ?>
        <div class="alert alert-danger py-2"><?= $_SESSION['erro']; unset($_SESSION['erro']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['sucesso'])): ?>
        <div class="alert alert-success py-2"><?= $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2>Agendamentos de Serviços</h2>
            <p class="text-muted small mb-0">Marcação e aprovação de revisões e reparações</p>
        </div>
        
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoAgendamento">
            + Pedir Agendamento
        </button>
    </div>

    <?php if ($e_chefe_ou_mecanico): ?>
        <div class="row mb-3">
            <div class="col-md-4">
                <input type="text" id="inputPesquisaAgendamentos" class="form-control" placeholder="🔍 Pesquisar por cliente, veículo ou serviço...">
            </div>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-striped bg-white shadow-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Data & Hora</th>
                    <th>Cliente</th>
                    <th>Veículo</th>
                    <th>Serviço Solicitado</th>
                    <th>Estado</th>
                    <?php if ($e_chefe_ou_mecanico): ?><th>Ações de Aprovação</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="tabelaAgendamentosBody">
                <?php if (count($agendamentos) > 0): ?>
                    <?php $numOrdem = 1; ?>
                    <?php foreach ($agendamentos as $a): ?>
                        <tr>
                            <td><strong><?= $numOrdem++ ?>º</strong></td>
                            <td><strong><?= date('d/m/Y H:i', strtotime($a['data_agendamento'])) ?></strong></td>
                            <td><?= htmlspecialchars($a['cliente_nome']) ?></td>
                            <td>
                                🚘 <strong><?= htmlspecialchars($a['marca'] . ' ' . $a['modelo']) ?></strong><br>
                                <code><?= htmlspecialchars($a['matricula']) ?></code>
                            </td>
                            <td><?= nl2br(htmlspecialchars($a['servico'])) ?></td>
                            <td>
                                <?php if ($a['estado'] === 'Pendente'): ?>
                                    <span class="badge bg-warning text-dark fs-6">⏳ Pendente de Aprovação</span>
                                <?php elseif ($a['estado'] === 'Confirmado'): ?>
                                    <span class="badge bg-success fs-6">✅ Aceite / Confirmado</span>
                                <?php elseif ($a['estado'] === 'Cancelado'): ?>
                                    <span class="badge bg-danger fs-6">❌ Recusado</span>
                                <?php endif; ?>
                            </td>
                            
                            <?php if ($e_chefe_ou_mecanico): ?>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <!-- Botão Aceitar Rápido -->
                                        <?php if ($a['estado'] !== 'Confirmado'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="atualizar_estado_agendamento" value="1">
                                                <input type="hidden" name="agendamento_id" value="<?= $a['id'] ?>">
                                                <input type="hidden" name="estado" value="Confirmado">
                                                <button type="submit" class="btn btn-sm btn-success me-1" title="Aceitar Agendamento">
                                                    ✓ Aceitar
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Botão Recusar Rápido -->
                                        <?php if ($a['estado'] !== 'Cancelado'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="atualizar_estado_agendamento" value="1">
                                                <input type="hidden" name="agendamento_id" value="<?= $a['id'] ?>">
                                                <input type="hidden" name="estado" value="Cancelado">
                                                <button type="submit" class="btn btn-sm btn-outline-danger me-1" onclick="return confirm('Recusar este agendamento?');" title="Recusar Agendamento">
                                                    ✕ Recusar
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Concluir e Enviar para Histórico -->
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalGerirAgendamento<?= $a['id'] ?>">
                                            🏁 Concluir
                                        </button>
                                    </div>

                                    <!-- Modal Concluir Agendamento -->
                                    <div class="modal fade" id="modalGerirAgendamento<?= $a['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" class="modal-content text-start">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Concluir Agendamento</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="atualizar_estado_agendamento" value="1">
                                                    <input type="hidden" name="agendamento_id" value="<?= $a['id'] ?>">
                                                    <input type="hidden" name="estado" value="Concluído">

                                                    <div class="p-3 bg-light rounded border mb-3">
                                                        <h6 class="fw-bold text-success mb-2">💰 Fecho de Serviço (10 €/Hora)</h6>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Horas de Trabalho Gastas</label>
                                                            <input type="number" step="0.5" min="0.5" name="horas_trabalho" class="form-control" placeholder="Ex: 1.5" required>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Custo Total de Peças (€)</label>
                                                            <input type="number" step="0.01" min="0" name="custo_pecas" class="form-control" value="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-success">Finalizar & Enviar p/ Histórico</button>
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
                        <td colspan="<?= $e_chefe_ou_mecanico ? '7' : '6' ?>" class="text-center text-muted py-4">Nenhum agendamento registado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Novo Agendamento -->
<div class="modal fade" id="modalNovoAgendamento" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Marcar Agendamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="criar_agendamento" value="1">

                <div class="mb-3">
                    <label class="form-label fw-bold">Selecione o Veículo</label>
                    <select name="veiculo_id" class="form-select" required>
                        <option value="">-- Escolher Veículo --</option>
                        <?php foreach ($veiculosDisponiveis as $v): ?>
                            <option value="<?= $v['id'] ?>">
                                <?= $e_cliente ? '' : htmlspecialchars($v['cliente_nome']) . ' - ' ?>
                                <?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?> (<?= htmlspecialchars($v['matricula']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Data e Hora Pretendidas</label>
                    <input type="datetime-local" name="data_agendamento" class="form-control" required min="<?= date('Y-m-d\TH:i') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Serviço Pretendido / Observações</label>
                    <textarea name="servico" class="form-control" rows="3" required placeholder="Ex: Mudança de óleo e revisão de travões."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Submeter Pedido</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const inputPesquisa = document.getElementById('inputPesquisaAgendamentos');
    if (inputPesquisa) {
        inputPesquisa.addEventListener('keyup', function() {
            const termo = this.value.toLowerCase();
            const linhas = document.querySelectorAll('#tabelaAgendamentosBody tr');

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