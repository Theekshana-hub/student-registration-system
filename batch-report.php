<?php

$pageTitle = 'Batch Report';
$activePage = 'batch-report';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$batches = $pdo->query("
    SELECT b.*, 
           COUNT(s.id) as students,
           COALESCE(SUM(p.amount), 0) as collected,
           b.course_fee * COUNT(s.id) as expected
    FROM batches b
    LEFT JOIN students s ON s.batch_id = b.id
    LEFT JOIN payments p ON p.student_id = s.id AND p.status = 'paid'
    GROUP BY b.id
    ORDER BY b.start_date DESC
")->fetchAll();
?>

<div class="page-header">
    <h2><i class="bi bi-bar-chart"></i> Batch Report</h2>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Batch</th>
                    <th>Students</th>
                    <th>Expected</th>
                    <th>Collected</th>
                    <th>Collection %</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $b): 
                    $pct = $b['expected'] > 0 ? round(($b['collected'] / $b['expected']) * 100, 1) : 0;
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($b['batch_code']) ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($b['batch_name']) ?></small>
                    </td>
                    <td><?= $b['students'] ?></td>
                    <td><?= formatMoney($b['expected']) ?></td>
                    <td class="text-success"><?= formatMoney($b['collected']) ?></td>
                    <td>
                        <div class="progress" style="height:20px">
                            <div class="progress-bar <?= $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger') ?>" 
                                 style="width:<?= min(100, $pct) ?>%"><?= $pct ?>%</div>
                        </div>
                    </td>
                    <td><?= statusBadge($b['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
