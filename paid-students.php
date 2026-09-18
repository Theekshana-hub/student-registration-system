<?php
$pageTitle = 'Fully Paid Students';
$activePage = 'paid';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$students = $pdo->query("
    SELECT s.*, b.batch_code, b.course_fee,
           COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.id AND status = 'paid'), 0) as paid_amount
    FROM students s
    JOIN batches b ON s.batch_id = b.id
    WHERE (b.course_fee - COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.id AND status = 'paid'), 0)) <= 0
    ORDER BY s.full_name
")->fetchAll();
?>

<div class="page-header">
    <h2><i class="bi bi-check-circle"></i> Fully Paid Students</h2>
    <p class="text-muted mb-0">Students who have completed all payments</p>
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
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['full_name']) ?></strong></td>
                        <td><?= htmlspecialchars($s['register_no']) ?></td>
                        <td><?= htmlspecialchars($s['whatsapp_no'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($s['batch_code']) ?></td>
                        <td><?= formatMoney($s['course_fee']) ?></td>
                        <td class="text-success fw-semibold"><?= formatMoney($s['paid_amount']) ?></td>
                        <td><?= statusBadge($s['status']) ?></td>
                        <td>
                            <a href="student-profile.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
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
