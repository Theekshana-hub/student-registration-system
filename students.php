<?php
$pageTitle  = 'All Students';
$activePage = 'students';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Filters
$batchFilter  = $_GET['batch'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');

$sql = "
    SELECT s.*, b.batch_code, b.batch_name, b.course_fee, b.total_installments, b.installment_amount,
           COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.id AND status = 'paid'), 0) as paid_amount,
           (SELECT COUNT(DISTINCT installment_no) FROM payments WHERE student_id = s.id AND status = 'paid') as installments_paid,
           (SELECT MAX(installment_no) FROM payments WHERE student_id = s.id AND status = 'paid') as last_installment_no
    FROM students s
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE 1=1
";
$params = [];

if ($batchFilter !== '') {
    $sql .= " AND s.batch_id = ?";
    $params[] = $batchFilter;
}
if ($statusFilter !== '') {
    $sql .= " AND s.status = ?";
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= " AND (s.full_name LIKE ? OR s.register_no LIKE ? OR s.whatsapp_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY s.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$batches = $pdo->query("SELECT id, batch_code, batch_name FROM batches ORDER BY start_date DESC")->fetchAll();

/**
 * Get total installments based on student's payment_option
 */
function getPlanTotalInstallments($paymentOption, $batchTotal) {
    if ($paymentOption === 'one_time') return 1;
    if ($paymentOption === 'second')   return 2;
    if ($paymentOption === 'third')    return 3;
    // normal or empty → batch total
    $t = (int)$batchTotal;
    return $t > 0 ? $t : 8; // fallback
}

/**
 * Nice short label for payment option
 */
function getPlanBadge($paymentOption) {
    if ($paymentOption === 'one_time') return '<span class="badge bg-primary">1 Plan</span>';
    if ($paymentOption === 'second')   return '<span class="badge bg-info text-dark">2 Plan</span>';
    if ($paymentOption === 'third')    return '<span class="badge bg-warning text-dark">3 Plan</span>';
    if ($paymentOption === 'normal')   return '<span class="badge bg-secondary">Normal</span>';
    return '';
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-people"></i> All Students</h2>
        <p class="text-muted mb-0">Manage all registered students</p>
    </div>
    <a href="add-student.php" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> Add Student
    </a>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" id="filterForm" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search name, reg no, phone..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="batch" class="form-select" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Batches</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= ((string)$batchFilter === (string)$b['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['batch_code'] . ' - ' . $b['batch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="dropped" <?= $statusFilter === 'dropped' ? 'selected' : '' ?>>Dropped</option>
                    <option value="transferred" <?= $statusFilter === 'transferred' ? 'selected' : '' ?>>Transferred</option>
                    <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="students.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover datatable mb-0" data-page-length="25" data-order-column="0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Register No</th>
                        <th>Name</th>
                        <th>WhatsApp</th>
                        <th>Batch</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Installment Payments</th>
                        <th>Status</th>
                        <th>Access</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">No students found for the selected filters.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($students as $i => $s):
                            $balance = max(0, (float)($s['course_fee'] ?? 0) - (float)$s['paid_amount']);
                            $paidInstallments = (int)($s['installments_paid'] ?? 0);
                            $lastInstallment  = $s['last_installment_no'];

                            // ===== Total according to payment_option =====
                            $paymentOption     = $s['payment_option'] ?? '';
                            $totalInstallments = getPlanTotalInstallments($paymentOption, $s['total_installments'] ?? 0);
                            $installmentsLeft  = max(0, $totalInstallments - $paidInstallments);
                            $isFullyPaidByAmount = $balance <= 0 && (float)($s['course_fee'] ?? 0) > 0;
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($s['register_no']) ?></strong></td>
                            <td><?= htmlspecialchars($s['full_name']) ?></td>
                            <td>
                                <?php if ($s['whatsapp_no']): ?>
                                    <a href="https://wa.me/94<?= ltrim($s['whatsapp_no'], '0') ?>" target="_blank" class="text-success">
                                        <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($s['whatsapp_no']) ?>
                                    </a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark"><?= htmlspecialchars($s['batch_code'] ?? '-') ?></span></td>
                            <td class="text-success"><?= formatMoney($s['paid_amount']) ?></td>
                            <td class="<?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= formatMoney($balance) ?>
                            </td>
                            <td>
                                <?php if ($lastInstallment === null && $paidInstallments === 0): ?>
                                    <div class="small text-muted">No payments yet</div>
                                    <?= getPlanBadge($paymentOption) ?>
                                    <div class="small text-muted mt-1">0 / <?= $totalInstallments ?></div>
                                <?php else: ?>
                                    <div class="small">
                                        <strong><?= $paidInstallments ?></strong> / <strong><?= $totalInstallments ?></strong>
                                        <?= getPlanBadge($paymentOption) ?>
                                    </div>
                                    <?php if ($lastInstallment !== null): ?>
                                        <div class="small text-muted">Last: Inst #<?= (int)$lastInstallment ?></div>
                                    <?php endif; ?>
                                    <?php if ($isFullyPaidByAmount || $installmentsLeft === 0): ?>
                                        <span class="badge bg-success">Fully Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= $installmentsLeft ?> left</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= statusBadge($s['status']) ?></td>
                            <td>
                                <?php if ($s['access_given']): ?>
                                    <span class="badge bg-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="student-profile.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="bi bi-eye"></i>
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