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

// 1. Processar Criação de Reparação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_reparacao']) && $e_chefe_ou_mecanico) {
    $veiculo_id = (int)($_POST['veiculo_id'] ?? 0);
    $descricao  = trim($_POST['descricao'] ?? '');
    $estado     = trim($_POST['estado'] ?? 'Pendente');

    if ($veiculo_id > 0 && !empty($descricao)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO reparacoes (veiculo_id, descricao, estado, valor) VALUES (?, ?, ?, 0.00)");
            $stmt->execute([$veiculo_id, $descricao, $estado]);
            $_SESSION['sucesso'] = "Reparação registada com sucesso!";
        } catch (\PDOException $e) {
            $_SESSION['erro'] = "Erro ao registar reparação: " . $e->getMessage();
        }
    } else {
        $_SESSION['erro'] = "Por favor, selecione um veículo e descreva o serviço.";
    }
    header("Location: reparacoes.php");
    exit();
}

// 2. Processar Conclusão / Atualização com Baixa de Stock de Peça
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_reparacao']) && $e_chefe_ou_mecanico) {
    $reparacao_id = (int)($_POST['reparacao_id'] ?? 0);
    $novo_estado  = trim($_POST['estado'] ?? 'Pendente');

    if ($reparacao_id > 0) {
        try {
            if ($novo_estado === 'Concluído') {
                $horas_trabalho = (float)($_POST['horas_trabalho'] ?? 0);
                $custo_pecas    = (float)($_POST['custo_pecas'] ?? 0);
                $peca_id        = (int)($_POST['peca_id'] ?? 0);
                $qtd_peca       = (int)($_POST['qtd_peca'] ?? 1);

                if ($horas_trabalho <= 0) {
                    $_SESSION['erro'] = "Para concluir a reparação, é obrigatório indicar as horas de trabalho!";
                    header("Location: reparacoes.php");
                    exit();
                }

                $pdo->beginTransaction();

                // Se selecionou uma peça do inventário, verificar stock e dar baixa
                if ($peca_id > 0) {
                    $stmtStock = $pdo->prepare("SELECT nome, quantidade FROM pecas WHERE id = ?");
                    $stmtStock->execute([$peca_id]);
                    $peca = $stmtStock->fetch();

                    if ($peca) {
                        if ($peca['quantidade'] < $qtd_peca) {
                            $pdo->rollBack();
                            $_SESSION['erro'] = "Stock insuficiente para a peça '{$peca['nome']}'. Disponível: {$peca['quantidade']}.";
                            header("Location: reparacoes.php");
                            exit();
                        }

                        // Baixa no stock do inventário
                        $stmtBaixa = $pdo->prepare("UPDATE pecas SET quantidade = quantidade - ? WHERE id = ?");
                        $stmtBaixa->execute([$qtd_peca, $peca_id]);

                        // Registo na tabela intermédia
                        $stmtRP = $pdo->prepare("INSERT INTO reparacao_pecas (reparacao_id, peca_id, quantidade) VALUES (?, ?, ?)");
                        $stmtRP->execute([$reparacao_id, $peca_id, $qtd_peca]);
                    }
                }

                // Cálculo final (10€/h + Custo das peças inserido)
                $valor_mao_obra = $horas_trabalho * 10.00;
                $valor_total    = $valor_mao_obra + $custo_pecas;
                $data_fim       = date('Y-m-d H:i:s');

                $stmt = $pdo->prepare("UPDATE reparacoes SET estado = ?, valor = ?, data_fim = ? WHERE id = ?");
                $stmt->execute([$novo_estado, $valor_total, $data_fim, $reparacao_id]);

                $pdo->commit();
                $_SESSION['sucesso'] = "Reparação concluída e enviada para o Histórico! Stock atualizado com sucesso.";
            } else {
                $stmt = $pdo->prepare("UPDATE reparacoes SET estado = ? WHERE id = ?");
                $stmt->execute([$novo_estado, $reparacao_id]);
                $_SESSION['sucesso'] = "Estado da reparação atualizado para '{$novo_estado}'.";
            }
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['erro'] = "Erro ao atualizar reparação: " . $e->getMessage();
        }
    }
    header("Location: reparacoes.php");
    exit();
}

// 3. Obter Reparações Ativas e Peças Disponíveis
if ($e_cliente) {
    $stmtR = $pdo->prepare("
        SELECT r.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome
        FROM reparacoes r
        JOIN veiculos v ON r.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        WHERE c.email = (SELECT email FROM utilizadores WHERE id = ?) AND r.estado != 'Concluído'
        ORDER BY r.id DESC
    ");
    $stmtR->execute([$utilizador_id]);
    $reparacoes = $stmtR->fetchAll();
} else {
    $reparacoes = $pdo->query("
        SELECT r.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome
        FROM reparacoes r
        JOIN veiculos v ON r.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        WHERE r.estado != 'Concluído'
        ORDER BY r.id DESC
    ")->fetchAll();

    $veiculosDisponiveis = $pdo->query("
        SELECT v.id, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome
        FROM veiculos v 
        JOIN clientes c ON v.cliente_id = c.id 
        ORDER BY c.nome ASC
    ")->fetchAll();

    $pecasDisponiveis = $pdo->query("SELECT * FROM pecas WHERE quantidade > 0 ORDER BY nome ASC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Reparações Ativas</title>
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
                <li class="nav-item me-3"><a class="nav-link active" href="reparacoes.php">Reparações</a></li>
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

<div class="container mb-5">
    <?php if (isset($_SESSION['erro'])): ?>
        <div class="alert alert-danger py-2"><?= $_SESSION['erro']; unset($_SESSION['erro']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['sucesso'])): ?>
        <div class="alert alert-success py-2"><?= $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2>Reparações em Curso</h2>
            <p class="text-muted small mb-0">Gestão de serviços na oficina</p>
        </div>
        
        <?php if ($e_chefe_ou_mecanico): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaReparacao">
                + Nova Reparação
            </button>
        <?php endif; ?>
    </div>

    <?php if ($e_chefe_ou_mecanico): ?>
        <div class="row mb-3">
            <div class="col-md-4">
                <input type="text" id="inputPesquisaReparacoes" class="form-control" placeholder="🔍 Pesquisar por cliente, matrícula ou serviço...">
            </div>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-striped bg-white shadow-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Veículo</th>
                    <th>Descrição do Serviço</th>
                    <th>Estado</th>
                    <?php if ($e_chefe_ou_mecanico): ?><th>Ações</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="tabelaReparacoesBody">
                <?php if (count($reparacoes) > 0): ?>
                    <?php $numOrdem = 1; ?>
                    <?php foreach ($reparacoes as $r): ?>
                        <tr>
                            <td><strong><?= $numOrdem++ ?>º</strong></td>
                            <td><strong><?= htmlspecialchars($r['cliente_nome']) ?></strong></td>
                            <td>
                                🚘 <strong><?= htmlspecialchars($r['marca'] . ' ' . $r['modelo']) ?></strong><br>
                                <code><?= htmlspecialchars($r['matricula']) ?></code>
                            </td>
                            <td><?= nl2br(htmlspecialchars($r['descricao'])) ?></td>
                            <td>
                                <?php if ($r['estado'] === 'Pendente'): ?>
                                    <span class="badge bg-warning text-dark fs-6">⏳ Pendente</span>
                                <?php elseif ($r['estado'] === 'Em Andamento'): ?>
                                    <span class="badge bg-primary fs-6">🔧 Em Andamento</span>
                                <?php endif; ?>
                            </td>
                            
                            <?php if ($e_chefe_ou_mecanico): ?>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarReparacao<?= $r['id'] ?>">
                                        Atualizar / Concluir
                                    </button>

                                    <!-- Modal Atualizar / Concluir Reparação -->
                                    <div class="modal fade" id="modalEditarReparacao<?= $r['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" class="modal-content text-start">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Atualizar Serviço</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="atualizar_reparacao" value="1">
                                                    <input type="hidden" name="reparacao_id" value="<?= $r['id'] ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Estado do Serviço</label>
                                                        <select name="estado" class="form-select select-estado-modal" data-id="<?= $r['id'] ?>" required>
                                                            <option value="Pendente" <?= $r['estado'] === 'Pendente' ? 'selected' : '' ?>>⏳ Pendente</option>
                                                            <option value="Em Andamento" <?= $r['estado'] === 'Em Andamento' ? 'selected' : '' ?>>🔧 Em Andamento</option>
                                                            <option value="Concluído">✅ Concluído (Enviar para Histórico)</option>
                                                        </select>
                                                    </div>

                                                    <!-- Campos ativados ao escolher Concluído -->
                                                    <div id="camposConclusao<?= $r['id'] ?>" class="p-3 bg-light rounded border mb-3 d-none">
                                                        <h6 class="fw-bold text-success mb-2">💰 Fecho de Serviço (10 €/Hora)</h6>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Horas de Trabalho Gastas</label>
                                                            <input type="number" step="0.5" min="0.5" name="horas_trabalho" class="form-control" placeholder="Ex: 2.5">
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Selecione a Peça Utilizada (Opcional)</label>
                                                            <select name="peca_id" class="form-select form-select-sm">
                                                                <option value="0">-- Nenhuma Peça do Inventário --</option>
                                                                <?php foreach ($pecasDisponiveis as $p): ?>
                                                                    <option value="<?= $p['id'] ?>">
                                                                        <?= htmlspecialchars($p['nome']) ?> (Stock atual: <?= $p['quantidade'] ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="row">
                                                            <div class="col-md-6 mb-2">
                                                                <label class="form-label fw-bold small">Qtd. de Peças</label>
                                                                <input type="number" min="1" name="qtd_peca" class="form-control form-control-sm" value="1">
                                                            </div>
                                                            <div class="col-md-6 mb-2">
                                                                <label class="form-label fw-bold small">Custo Total de Peças (€)</label>
                                                                <input type="number" step="0.01" min="0" name="custo_pecas" class="form-control form-control-sm" value="0.00">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-success">Guardar Alterações</button>
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
                        <td colspan="<?= $e_chefe_ou_mecanico ? '6' : '5' ?>" class="text-center text-muted py-4">Sem reparações ativas no momento.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Nova Reparação -->
<?php if ($e_chefe_ou_mecanico): ?>
<div class="modal fade" id="modalNovaReparacao" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Registar Nova Reparação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="criar_reparacao" value="1">

                <div class="mb-3">
                    <label class="form-label fw-bold">Selecione o Veículo</label>
                    <select name="veiculo_id" class="form-select" required>
                        <option value="">-- Escolher Veículo --</option>
                        <?php foreach ($veiculosDisponiveis as $v): ?>
                            <option value="<?= $v['id'] ?>">
                                <?= htmlspecialchars($v['cliente_nome']) ?> - <?= htmlspecialchars($v['marca'] . ' ' . $v['modelo']) ?> (<?= htmlspecialchars($v['matricula']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Descrição do Serviço</label>
                    <textarea name="descricao" class="form-control" rows="3" required placeholder="Ex: Mudança de calços e discos dianteiros."></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Estado Inicial</label>
                    <select name="estado" class="form-select" required>
                        <option value="Pendente">⏳ Pendente</option>
                        <option value="Em Andamento">🔧 Em Andamento</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success">Criar Reparação</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.select-estado-modal').forEach(select => {
        select.addEventListener('change', function() {
            const repId = this.getAttribute('data-id');
            const divConclusao = document.getElementById('camposConclusao' + repId);
            const inputHoras = divConclusao.querySelector('input[name="horas_trabalho"]');
            
            if (this.value === 'Concluído') {
                divConclusao.classList.remove('d-none');
                inputHoras.setAttribute('required', 'required');
            } else {
                divConclusao.classList.add('d-none');
                inputHoras.removeAttribute('required');
            }
        });
    });

    const inputPesquisa = document.getElementById('inputPesquisaReparacoes');
    if (inputPesquisa) {
        inputPesquisa.addEventListener('keyup', function() {
            const termo = this.value.toLowerCase();
            const linhas = document.querySelectorAll('#tabelaReparacoesBody tr');

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