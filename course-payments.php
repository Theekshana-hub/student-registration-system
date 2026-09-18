<?php
$pageTitle = 'Course Payments';
$activePage = 'course-payments';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Selected course
$courseId = $_GET['course_id'] ?? '';

$courses = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_name")->fetchAll();

$students = [];
$selectedCourse = null;

if ($courseId !== '') {
    $stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $selectedCourse = $stmt->fetch();

    if ($selectedCourse) {
        $sql = "
            SELECT
                s.id, s.full_name, s.register_no, s.whatsapp_no, s.status,
                b.batch_code, b.batch_name, b.course_fee, b.total_installments, b.installment_amount,
                COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END), 0) AS paid_amount,
                COUNT(DISTINCT CASE WHEN p.status = 'paid' THEN p.installment_no END) AS installments_paid,
                MAX(CASE WHEN p.status = 'paid' THEN p.installment_no END) AS last_installment_no
            FROM students s
            INNER JOIN batches b ON s.batch_id = b.id
            LEFT JOIN payments p ON p.student_id = s.id
            WHERE b.course_id = ?
            GROUP BY s.id, s.full_name, s.register_no, s.whatsapp_no, s.status,
                     b.batch_code, b.batch_name, b.course_fee, b.total_installments, b.installment_amount
            ORDER BY s.id DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$courseId]);
        $students = $stmt->fetchAll();
    }
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-journal-bookmark-fill"></i> Course Payments</h2>
        <p class="text-muted mb-0">Select a course to see all students, payments and installments</p>
    </div>
</div>

<!-- Course selector -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" id="courseForm" class="row g-3">
            <div class="col-md-5">
                <select name="course_id" class="form-select" onchange="document.getElementById('courseForm').submit()">
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ((string)$courseId === (string)$c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <a href="course-payments.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if ($courseId !== '' && !$selectedCourse): ?>
    <div class="alert alert-warning">Course not found.</div>
<?php elseif ($selectedCourse): ?>

    <?php
        $totalStudents = count($students);
        $totalCollected = array_sum(array_column($students, 'paid_amount'));
        $totalExpected = array_sum(array_column($students, 'course_fee'));
        $totalBalance = max(0, $totalExpected - $totalCollected);
    ?>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total Students</div>
                    <h4 class="mb-0"><?= $totalStudents ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total Collected</div>
                    <h4 class="mb-0 text-success"><?= formatMoney($totalCollected) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total Balance</div>
                    <h4 class="mb-0 text-danger"><?= formatMoney($totalBalance) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover datatable mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Register No</th>
                            <th>Name</th>
                            <th>WhatsApp</th>
                            <th>Batch</th>
                            <th>Course Fee</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Installment No</th>
                            <th>Installments Left</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">No students found for this course.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($students as $i => $s):
                                $balance = max(0, ($s['course_fee'] ?? 0) - $s['paid_amount']);
                                $paidInstallments = (int)$s['installments_paid'];
                                $lastInstallment = $s['last_installment_no'];
                                $totalInstallments = (int)($s['total_installments'] ?? 0);
                                $installmentsLeft = $totalInstallments > 0 ? max(0, $totalInstallments - $paidInstallments) : null;
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
                                <td><?= formatMoney($s['course_fee'] ?? 0) ?></td>
                                <td class="text-success"><?= formatMoney($s['paid_amount']) ?></td>
                                <td class="<?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= formatMoney($balance) ?>
                                </td>
                                <td>
                                    <?php if ($lastInstallment !== null): ?>
                                        Installment <?= (int)$lastInstallment ?><?= $totalInstallments > 0 ? ' of ' . $totalInstallments : '' ?>
                                        <div class="text-muted small"><?= $paidInstallments ?> paid</div>
                                    <?php else: ?>
                                        <span class="text-muted">No payments yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($installmentsLeft === null): ?>
                                        <span class="text-muted">—</span>
                                    <?php elseif ($installmentsLeft === 0): ?>
                                        <span class="badge bg-success">Fully Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?= $installmentsLeft ?> left</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= statusBadge($s['status']) ?></td>
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

<?php else: ?>
    <div class="alert alert-info">Please select a course above to view students and payment status.</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>