<?php
$pageTitle = 'Pending Payments';
$activePage = 'pending';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$students = $pdo->query("
    SELECT s.*, b.batch_code, b.course_fee, b.installment_amount,
           COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.id AND status = 'paid'), 0) as paid_amount,
           (SELECT COUNT(*) FROM payments WHERE student_id = s.id AND status = 'paid') as paid_count
    FROM students s
    JOIN batches b ON s.batch_id = b.id
    WHERE s.status = 'active'
    AND (b.course_fee - COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.id AND status = 'paid'), 0)) > 0
    ORDER BY (b.course_fee - COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.id AND status = 'paid'), 0)) DESC
")->fetchAll();
?>

<div class="page-header">
    <h2><i class="bi bi-exclamation-triangle"></i> Pending Payments</h2>
    <p class="text-muted mb-0">Students with outstanding balances</p>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover datatable mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Reg No</th>
                        <th>WhatsApp</th>
                        <th>Batch</th>
                        <th>Course Fee</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Installments</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): 
                        $balance = $s['course_fee'] - $s['paid_amount'];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($s['register_no']) ?></td>
                        <td>
                            <?php if ($s['whatsapp_no']): ?>
                                <a href="https://wa.me/94<?= ltrim($s['whatsapp_no'], '0') ?>" target="_blank" class="text-success">
                                    <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($s['whatsapp_no']) ?>
                                </a>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($s['batch_code']) ?></td>
                        <td><?= formatMoney($s['course_fee']) ?></td>
                        <td class="text-success"><?= formatMoney($s['paid_amount']) ?></td>
                        <td class="text-danger fw-bold"><?= formatMoney($balance) ?></td>
                        <td><?= $s['paid_count'] ?> paid</td>
                        <td>
                            <a href="student-profile.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
