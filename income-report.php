<?php

$activePage = 'income-report';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$from = $_GET['from'] ?? date('Y-01-01');
$to = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total, COUNT(*) as count
    FROM payments
    WHERE status = 'paid' AND payment_date BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month
");
$stmt->execute([$from, $to]);
$monthly = $stmt->fetchAll();

$grandTotal = array_sum(array_column($monthly, 'total'));
?>

<div class="page-header">
    <h2><i class="bi bi-graph-up"></i> Income Report</h2>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= $from ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= $to ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100">Generate</button>
            </div>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <h6 class="text-muted">Total Income</h6>
                <h2 class="text-success fw-bold"><?= formatMoney($grandTotal) ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Payments Count</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly as $m): ?>
                <tr>
                    <td><?= $m['month'] ?></td>
                    <td><?= $m['count'] ?></td>
                    <td class="text-success fw-semibold"><?= formatMoney($m['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
