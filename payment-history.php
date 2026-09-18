<?php
$pageTitle = 'Payment History';
$activePage = 'history';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT p.*, s.full_name, s.register_no, b.batch_code
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE p.payment_date BETWEEN ? AND ?
    ORDER BY p.payment_date DESC
");
$stmt->execute([$from, $to]);
$payments = $stmt->fetchAll();

$total = array_sum(array_column($payments, 'amount'));
?>

<div class="page-header">
    <h2><i class="bi bi-clock-history"></i> Payment History</h2>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="<?= $from ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="<?= $to ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-4 text-end">
                <h4 class="mb-0 text-success">Total: <?= formatMoney($total) ?></h4>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover datatable mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Reg No</th>
                    <th>Batch</th>
                    <th>Inst</th>
                    <th>Amount</th>
                    <th>Bank</th>
                    <th>Ref</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= formatDate($p['payment_date']) ?></td>
                    <td><?= htmlspecialchars($p['full_name']) ?></td>
                    <td><?= htmlspecialchars($p['register_no']) ?></td>
                    <td><?= htmlspecialchars($p['batch_code'] ?? '-') ?></td>
                    <td>#<?= $p['installment_no'] ?></td>
                    <td class="text-success fw-semibold"><?= formatMoney($p['amount']) ?></td>
                    <td><?= htmlspecialchars($p['bank'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['ref_no'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
