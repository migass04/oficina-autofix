<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID de reparação inválido.");
}

$sql = "SELECT r.*, v.marca, v.modelo, v.matricula, c.nome AS cliente_nome, c.email AS cliente_email, c.nif AS cliente_nif 
        FROM reparacoes r 
        JOIN veiculos v ON r.veiculo_id = v.id 
        JOIN clientes c ON v.cliente_id = c.id 
        WHERE r.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$reparacao = $stmt->fetch();

if (!$reparacao) {
    die("Reparação não encontrada.");
}

$subtotal = $reparacao['valor_estimado'];
$iva = $subtotal * 0.23;
$total = $subtotal + $iva;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Fatura #<?= str_pad($reparacao['id'], 5, '0', STR_PAD_LEFT) ?> - AutoFix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: Arial, sans-serif; }
        .invoice-card { max-width: 800px; margin: 30px auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .invoice-card { box-shadow: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
<div class="invoice-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <img src="img/autofix.jpg" alt="AutoFix Logo" style="max-width: 180px;" class="rounded">
            <h5 class="mt-2 mb-0">AutoFix Oficina</h5>
            <p class="text-muted small">Serviços de Manutenção Automóvel</p>
        </div>
        <div class="text-end">
            <h4>FATURA</h4>
            <p class="mb-0"><strong>Nº:</strong> #FT-<?= str_pad($reparacao['id'], 5, '0', STR_PAD_LEFT) ?></p>
            <p class="text-muted small">Data: <?= date('d/m/Y') ?></p>
        </div>
    </div>
    <hr>

    <div class="row my-4">
        <div class="col-6">
            <h6><strong>Dados do Cliente:</strong></h6>
            <p class="mb-1"><?= htmlspecialchars($reparacao['cliente_nome']) ?></p>
            <p class="mb-1">NIF: <?= htmlspecialchars($reparacao['cliente_nif']) ?></p>
            <p class="mb-0">Email: <?= htmlspecialchars($reparacao['cliente_email']) ?></p>
        </div>
        <div class="col-6 text-end">
            <h6><strong>Dados do Veículo:</strong></h6>
            <p class="mb-1"><?= htmlspecialchars($reparacao['marca'] . ' ' . $reparacao['modelo']) ?></p>
            <p class="mb-0">Matrícula: <strong><?= htmlspecialchars($reparacao['matricula']) ?></strong></p>
        </div>
    </div>

    <table class="table table-bordered mt-4">
        <thead class="table-light">
            <tr>
                <th>Descrição do Serviço / Reparação</th>
                <th class="text-end" style="width: 180px;">Valor Base</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($reparacao['descricao_problema']) ?></td>
                <td class="text-end"><?= number_format($subtotal, 2, ',', ' ') ?> €</td>
            </tr>
        </tbody>
    </table>

    <div class="row justify-content-end mt-4">
        <div class="col-5">
            <table class="table table-borderless">
                <tr>
                    <td class="text-end">Subtotal:</td>
                    <td class="text-end"><strong><?= number_format($subtotal, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr>
                    <td class="text-end">IVA (23%):</td>
                    <td class="text-end"><strong><?= number_format($iva, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <tr class="table-dark">
                    <td class="text-end"><strong>Total:</strong></td>
                    <td class="text-end"><strong><?= number_format($total, 2, ',', ' ') ?> €</strong></td>
                </tr>
            </table>
        </div>
    </div>

    <hr class="mt-5">
    <div class="text-center text-muted small">
        <p>Obrigado pela sua preferência! Documento emitido por computador.</p>
    </div>

    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-primary me-2">Imprimir / Guardar PDF</button>
        <button onclick="window.close()" class="btn btn-secondary">Fechar</button>
    </div>
</div>
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