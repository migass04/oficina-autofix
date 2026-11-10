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

// Consultas de métricas para os cartões
$totalVeiculos = $pdo->query("SELECT COUNT(*) FROM veiculos")->fetchColumn() ?? 0;
$reparacoesAtivas = $pdo->query("SELECT COUNT(*) FROM reparacoes WHERE estado != 'Concluído'")->fetchColumn() ?? 0;

// Soma de todos os trabalhos concluídos HOJE (acumulado diário)
$totalHoje = $pdo->query("SELECT SUM(valor) FROM reparacoes WHERE estado = 'Concluído' AND DATE(data_fim) = CURDATE()")->fetchColumn() ?? 0;

// Soma total acumulada
$totalFaturado = $pdo->query("SELECT SUM(valor) FROM reparacoes WHERE estado = 'Concluído'")->fetchColumn() ?? 0;

// Dados para as tabelas do Dashboard
if ($e_cliente) {
    // Visão do Cliente
    $stmtR = $pdo->prepare("
        SELECT r.*, v.marca, v.modelo, v.matricula
        FROM reparacoes r
        JOIN veiculos v ON r.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        WHERE c.email = (SELECT email FROM utilizadores WHERE id = ?) AND r.estado != 'Concluído'
        ORDER BY r.id DESC LIMIT 5
    ");
    $stmtR->execute([$utilizador_id]);
    $reparacoesRecentes = $stmtR->fetchAll();

    $stmtA = $pdo->prepare("
        SELECT a.*, v.marca, v.modelo, v.matricula
        FROM agendamentos a
        JOIN veiculos v ON a.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        WHERE c.email = (SELECT email FROM utilizadores WHERE id = ?) AND a.estado != 'Concluído'
        ORDER BY a.data_agendamento ASC LIMIT 5
    ");
    $stmtA->execute([$utilizador_id]);
    $agendamentosRecentes = $stmtA->fetchAll();
} else {
    // Visão do Chefe e Mecânico
    $reparacoesRecentes = $pdo->query("
        SELECT r.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome
        FROM reparacoes r
        JOIN veiculos v ON r.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        WHERE r.estado != 'Concluído'
        ORDER BY r.id DESC LIMIT 5
    ")->fetchAll();

    $agendamentosRecentes = $pdo->query("
        SELECT a.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome
        FROM agendamentos a
        JOIN veiculos v ON a.veiculo_id = v.id
        JOIN clientes c ON v.cliente_id = c.id
        WHERE a.estado != 'Concluído'
        ORDER BY a.data_agendamento ASC LIMIT 5
    ")->fetchAll();
}

// Dados para o Gráfico de Faturação Mensal (Últimos 6 Meses)
$mesesGrafico = [];
$valoresGrafico = [];

for ($i = 5; $i >= 0; $i--) {
    $mesAno = date('Y-m', strtotime("-$i months"));
    $nomeMes = date('M Y', strtotime("-$i months"));
    
    $stmtG = $pdo->prepare("SELECT SUM(valor) FROM reparacoes WHERE estado = 'Concluído' AND DATE_FORMAT(data_fim, '%Y-%m') = ?");
    $stmtG->execute([$mesAno]);
    $val = $stmtG->fetchColumn() ?? 0;

    $mesesGrafico[] = $nomeMes;
    $valoresGrafico[] = (float)$val;
}
?>
<!DOCTYPE html>
<html lang="pt" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>AutoFix - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <!-- Chart.js para o Gráfico -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li class="nav-item me-3"><a class="nav-link active" href="index.php">Dashboard</a></li>
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
    </div>
</nav>

<div class="container mb-5">
    <div class="mb-4">
        <h2>Painel Principal</h2>
        <p class="text-muted mb-0">Bem-vindo ao sistema de gestão AutoFix</p>
    </div>

    <!-- Cartões Informativos (KPIs) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-warning border-4 p-3 bg-white">
                <div class="text-muted small fw-bold">Reparações Em Curso</div>
                <div class="fs-3 fw-bold text-warning"><?= $reparacoesAtivas ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-info border-4 p-3 bg-white">
                <div class="text-muted small fw-bold">Veículos no Parque</div>
                <div class="fs-3 fw-bold text-info"><?= $totalVeiculos ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-success border-4 p-3 bg-white">
                <div class="text-muted small fw-bold">Faturação de Hoje</div>
                <div class="fs-3 fw-bold text-success"><?= number_format($totalHoje, 2, ',', ' ') ?> €</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-primary border-4 p-3 bg-white">
                <div class="text-muted small fw-bold">Faturação Acumulada</div>
                <div class="fs-3 fw-bold text-primary"><?= number_format($totalFaturado, 2, ',', ' ') ?> €</div>
            </div>
        </div>
    </div>

    <!-- Gráfico de Faturação Mensal -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 p-4 bg-white">
                <h5 class="fw-bold mb-3">📈 EVOLUÇÃO DE FATURAÇÃO MENSAL (€)</h5>
                <div style="position: relative; height: 300px;">
                    <canvas id="graficoFaturacao"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabelas de Resumo (Reparações Ativas & Agendamentos Próximos) -->
    <div class="row g-4">
        <!-- Reparações Recentes -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 bg-white">
                <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                    <span>🔧 Reparações em Curso</span>
                    <a href="reparacoes.php" class="btn btn-sm btn-outline-light">Ver Todas</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Veículo</th>
                                    <th>Serviço</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($reparacoesRecentes) > 0): ?>
                                    <?php foreach ($reparacoesRecentes as $r): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($r['marca'] . ' ' . $r['modelo']) ?></strong><br>
                                                <code><?= htmlspecialchars($r['matricula']) ?></code>
                                            </td>
                                            <td><small><?= htmlspecialchars($r['descricao']) ?></small></td>
                                            <td>
                                                <?php if ($r['estado'] === 'Pendente'): ?>
                                                    <span class="badge bg-warning text-dark">Pendente</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Em Andamento</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Sem reparações ativas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agendamentos Próximos -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 bg-white">
                <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                    <span>📅 Agendamentos Próximos</span>
                    <a href="agendamentos.php" class="btn btn-sm btn-outline-light">Ver Todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Veículo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($agendamentosRecentes) > 0): ?>
                                    <?php foreach ($agendamentosRecentes as $a): ?>
                                        <tr>
                                            <td><small>🗓️ <?= date('d/m/Y H:i', strtotime($a['data_agendamento'])) ?></small></td>
                                            <td>
                                                <strong><?= htmlspecialchars($a['marca'] . ' ' . $a['modelo']) ?></strong><br>
                                                <code><?= htmlspecialchars($a['matricula']) ?></code>
                                            </td>
                                            <td>
                                                <?php if ($a['estado'] === 'Pendente'): ?>
                                                    <span class="badge bg-warning text-dark">Pendente</span>
                                                <?php elseif ($a['estado'] === 'Confirmado'): ?>
                                                    <span class="badge bg-success">Confirmado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Cancelado</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Nenhum agendamento pendente.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Inicialização do Gráfico Chart.js
    const ctx = document.getElementById('graficoFaturacao').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($mesesGrafico) ?>,
            datasets: [{
                label: 'Faturação (€)',
                data: <?= json_encode($valoresGrafico) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.3,
                pointRadius: 5,
                pointBackgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return value + ' €'; }
                    }
                }
            }
        }
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