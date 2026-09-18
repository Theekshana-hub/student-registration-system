<?php
$pageTitle  = 'Batches';
$activePage = 'batches';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$batches = $pdo->query("
    SELECT b.*,
           c.course_code,
           c.course_name,
           COUNT(s.id) as student_count,
           COALESCE(SUM(p.amount), 0) as collected
    FROM batches b
    LEFT JOIN courses c ON c.id = b.course_id
    LEFT JOIN students s ON s.batch_id = b.id
    LEFT JOIN payments p ON p.student_id = s.id AND p.status = 'paid'
    GROUP BY b.id
    ORDER BY b.start_date DESC
")->fetchAll();
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-collection"></i> All Batches</h2>
        <p class="text-muted mb-0">Manage course batches</p>
    </div>
    <a href="add-batch.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Add Batch
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover datatable mb-0">
                <thead>
                    <tr>
                        <th>Batch Code</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Course Fee</th>
                        <th>Installment</th>
                        <th>Students</th>
                        <th>Collected</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">No batches found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($b['batch_code']) ?></strong></td>
                            <td><?= htmlspecialchars($b['batch_name']) ?></td>
                            <td>
                                <?php if (!empty($b['course_code'])): ?>
                                    <span class="badge bg-light text-dark">
                                        <?= htmlspecialchars($b['course_code']) ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars($b['course_name']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($b['start_date']) ?></td>
                            <td><?= formatDate($b['end_date']) ?></td>
                            <td><?= formatMoney($b['course_fee']) ?></td>
                            <td><?= formatMoney($b['installment_amount']) ?> × <?= $b['total_installments'] ?></td>
                            <td><span class="badge bg-primary"><?= $b['student_count'] ?></span></td>
                            <td class="text-success fw-semibold"><?= formatMoney($b['collected']) ?></td>
                            <td><?= statusBadge($b['status']) ?></td>
                            <td>
                                <a href="edit-batch.php?id=<?= $b['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>