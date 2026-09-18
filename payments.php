<?php
$pageTitle = 'All Payments';
$activePage = 'payments';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Status filter
$statusFilter = $_GET['status'] ?? '';

// ============================================
// PENDING → show students with outstanding balance
// ============================================
if ($statusFilter === 'pending') {

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

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2><i class="bi bi-exclamation-triangle"></i> Pending Payments</h2>
            <p class="text-muted mb-0">Students with outstanding balances</p>
        </div>

        <!-- Status Dropdown Filter -->
        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 fw-semibold">Status:</label>
            <select name="status" class="form-select form-select-sm" style="width: 160px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="pending" selected>Pending</option>
                <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            </select>
        </form>
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

<?php
// ============================================
// Other statuses (paid / failed / refunded / all) → normal payment list
// ============================================
} else {

    $sql = "
        SELECT p.*, s.full_name, s.register_no, b.batch_code
        FROM payments p
        JOIN students s ON p.student_id = s.id
        LEFT JOIN batches b ON s.batch_id = b.id
    ";

    $params = [];
    if ($statusFilter !== '') {
        $sql .= " WHERE p.status = ?";
        $params[] = $statusFilter;
    }

    $sql .= " ORDER BY p.payment_date DESC, p.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    ?>

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2><i class="bi bi-cash-stack"></i> All Payments</h2>
            <p class="text-muted mb-0">Complete payment history</p>
        </div>

        <!-- Status Dropdown Filter -->
        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 fw-semibold">Status:</label>
            <select name="status" class="form-select form-select-sm" style="width: 160px;" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            </select>
        </form>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover datatable mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Reg No</th>
                            <th>Batch</th>
                            <th>Inst #</th>
                            <th>Amount</th>
                            <th>Bank</th>
                            <th>Ref No</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= formatDate($p['payment_date']) ?></td>
                            <td>
                                <a href="student-profile.php?id=<?= $p['student_id'] ?>">
                                    <?= htmlspecialchars($p['full_name']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($p['register_no']) ?></td>
                            <td><?= htmlspecialchars($p['batch_code'] ?? '-') ?></td>
                            <td><span class="badge bg-light text-dark">#<?= $p['installment_no'] ?></span></td>
                            <td class="fw-semibold"><?= formatMoney($p['amount']) ?></td>
                            <td><?= htmlspecialchars($p['bank'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['ref_no'] ?? '-') ?></td>
                            <td><?= statusBadge($p['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php } // end if/else ?>

<?php require_once 'includes/footer.php'; ?>