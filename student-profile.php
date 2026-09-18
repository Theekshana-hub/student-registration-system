<?php
ob_start();

$pageTitle  = 'Student Profile';
$activePage = 'students';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: students.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, b.batch_code, b.batch_name, b.course_fee, b.installment_amount, b.total_installments
    FROM students s
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    echo '<div class="alert alert-danger">Student not found</div>';
    require_once 'includes/footer.php';
    exit;
}

// Student's preferred payment option
$studentPaymentOption = $student['payment_option'] ?? '';

// ========== ADD PAYMENT ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {

    $paidCheckStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = ? AND status = 'paid'");
    $paidCheckStmt->execute([$id]);
    $currentPaid = (float)$paidCheckStmt->fetchColumn();
    $courseFee   = (float)($student['course_fee'] ?? 0);

    if ($courseFee > 0 && $currentPaid >= $courseFee) {
        header("Location: student-profile.php?id=$id&msg=already_paid");
        exit;
    }

    $paymentOption = $_POST['payment_option'] ?? '';
    $installmentNo = null;

    if ($paymentOption === 'one_time') {
        $installmentNo = 1;
    } elseif ($paymentOption === 'second') {
        $installmentNo = 2;
    } elseif ($paymentOption === 'third') {
        $installmentNo = 3;
    } elseif ($paymentOption === 'normal') {
        $installmentNo = !empty($_POST['installment_no']) ? (int)$_POST['installment_no'] : null;
    }

    if ($installmentNo === null) {
        header("Location: student-profile.php?id=$id&msg=invalid_option");
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO payments
        (student_id, installment_no, payment_option, amount, bank, ref_no, payment_date, payment_month, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?)
    ");
    $stmt->execute([
        $id,
        $installmentNo,
        $paymentOption,
        $_POST['amount'],
        $_POST['bank'] ?: null,
        $_POST['ref_no'] ?: null,
        $_POST['payment_date'] ?: null,
        $_POST['payment_month'] ?: null,
        $_POST['notes'] ?: null,
    ]);

    header("Location: student-profile.php?id=$id&msg=payment_added");
    exit;
}

// ========== DATA FOR PAGE ==========
$paymentsStmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY installment_no ASC, id ASC");
$paymentsStmt->execute([$id]);
$payments = $paymentsStmt->fetchAll();

$regFeesStmt = $pdo->prepare("SELECT * FROM registration_fees WHERE student_id = ?");
$regFeesStmt->execute([$id]);
$regFees = $regFeesStmt->fetchAll();

$paidPayments = array_filter($payments, fn($p) => ($p['status'] ?? '') === 'paid');
$paidTotal    = array_sum(array_column($paidPayments, 'amount'));
$courseFee    = (float)($student['course_fee'] ?? 0);
$balance      = max(0, $courseFee - $paidTotal);

// =====================================================
// TOTAL INSTALLMENTS according to Payment Option
// one_time → 1 | second → 2 | third → 3 | normal → batch total
// =====================================================
if ($studentPaymentOption === 'one_time') {
    $totalInstallments = 1;
} elseif ($studentPaymentOption === 'second') {
    $totalInstallments = 2;
} elseif ($studentPaymentOption === 'third') {
    $totalInstallments = 3;
} else {
    // normal OR not set → use batch total_installments
    $totalInstallments = (int)($student['total_installments'] ?? 0);
    if ($totalInstallments < 1) {
        $totalInstallments = 8; // fallback
    }
}

// Unique paid installment numbers
$paidInstallmentNos = [];
foreach ($paidPayments as $p) {
    $no = (int)$p['installment_no'];
    if ($no > 0 && !in_array($no, $paidInstallmentNos)) {
        $paidInstallmentNos[] = $no;
    }
}
sort($paidInstallmentNos);

$paidInstallmentsCount = count($paidInstallmentNos);
$installmentsLeft      = max(0, $totalInstallments - $paidInstallmentsCount);
$isFullyPaid           = $courseFee > 0 && $balance <= 0;

// Unpaid numbers (for Normal Pay dropdown + for fixed plans)
$unpaidInstallments = [];
for ($i = 1; $i <= $totalInstallments; $i++) {
    if (!in_array($i, $paidInstallmentNos)) {
        $unpaidInstallments[] = $i;
    }
}

// Nice label for student's preferred payment option
function getStudentPaymentOptionLabel($option) {
    if ($option === 'one_time') return '<span class="badge bg-primary">One Time Pay</span>';
    if ($option === 'second')   return '<span class="badge bg-info text-dark">2nd Pay</span>';
    if ($option === 'third')    return '<span class="badge bg-warning text-dark">3rd Pay</span>';
    if ($option === 'normal')   return '<span class="badge bg-secondary">Normal Pay</span>';
    return '<span class="text-muted">Not set</span>';
}

// Helper for payment rows
function getPaymentLabel($row) {
    $option = $row['payment_option'] ?? null;
    $no     = (int)$row['installment_no'];

    if ($option === 'one_time') {
        return '<span class="badge bg-primary">One Time Pay</span>';
    }
    if ($option === 'second') {
        return '<span class="badge bg-info text-dark">2nd Pay</span>';
    }
    if ($option === 'third') {
        return '<span class="badge bg-warning text-dark">3rd Pay</span>';
    }
    if ($option === 'normal') {
        return '<span class="badge bg-secondary">Normal – Inst #' . $no . '</span>';
    }

    // Fallback
    if ($no === 1) return '<span class="badge bg-primary">One Time (Inst #1)</span>';
    if ($no === 2) return '<span class="badge bg-info text-dark">2nd Pay (Inst #2)</span>';
    if ($no === 3) return '<span class="badge bg-warning text-dark">3rd Pay (Inst #3)</span>';
    return '<span class="badge bg-secondary">Inst #' . $no . '</span>';
}
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="bi bi-person"></i> <?= htmlspecialchars($student['full_name']) ?></h2>
        <p class="text-muted mb-0">
            <?= htmlspecialchars($student['register_no']) ?> · <?= htmlspecialchars($student['batch_code'] ?? 'No Batch') ?>
            <?php if ($studentPaymentOption): ?>
                · Preferred: <?= getStudentPaymentOptionLabel($studentPaymentOption) ?>
            <?php endif; ?>
        </p>
    </div>
    <a href="students.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'payment_added'): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle"></i> Payment added successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'already_paid'): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle"></i> This student is already fully paid. No further payments can be added.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'invalid_option'): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-x-circle"></i> Please select a valid Payment Option / Installment No.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($isFullyPaid): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-check-circle-fill fs-4"></i>
        <div>
            <strong>Payment Successful — Fully Paid</strong>
            <div class="small">This student has completed all payments for the course fee.</div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <h6 class="text-muted">Course Fee</h6>
                <h4 class="fw-bold"><?= formatMoney($student['course_fee'] ?? 0) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <h6 class="text-muted">Paid</h6>
                <h4 class="fw-bold text-success"><?= formatMoney($paidTotal) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <h6 class="text-muted">Balance</h6>
                <h4 class="fw-bold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= formatMoney($balance) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <h6 class="text-muted">Status</h6>
                <h4><?= statusBadge($student['status']) ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0">Student Info</h5></div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr><th>WhatsApp</th><td>
                        <?php if ($student['whatsapp_no']): ?>
                            <a href="https://wa.me/94<?= ltrim($student['whatsapp_no'], '0') ?>" target="_blank" class="text-success">
                                <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($student['whatsapp_no']) ?>
                            </a>
                        <?php else: ?>-<?php endif; ?>
                    </td></tr>
                    <tr><th>Email</th><td><?= htmlspecialchars($student['email'] ?? '-') ?></td></tr>
                    <tr><th>Address</th><td><?= htmlspecialchars($student['address'] ?? '-') ?></td></tr>
                    <tr><th>Birthday</th><td><?= formatDate($student['birthday']) ?></td></tr>
                    <tr><th>Gender</th><td><?= htmlspecialchars($student['gender'] ?? '-') ?></td></tr>
                    <tr><th>NIC</th><td><?= htmlspecialchars($student['nic'] ?? '-') ?></td></tr>
                    <tr><th>Payment Option</th><td><?= getStudentPaymentOptionLabel($studentPaymentOption) ?></td></tr>
                    <tr><th>Username</th><td><?= htmlspecialchars($student['username'] ?? '-') ?></td></tr>
                    <tr><th>Password</th><td><?= htmlspecialchars($student['password_plain'] ?? '-') ?></td></tr>
                    <tr><th>Access</th><td><?= $student['access_given'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td></tr>
                    <tr><th>Sent</th><td><?= $student['credentials_sent'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td></tr>
                    <tr><th>Agent</th><td><?= htmlspecialchars($student['call_center_agent'] ?? '-') ?></td></tr>
                    <tr><th>Remark</th><td><?= nl2br(htmlspecialchars($student['remark'] ?? '-')) ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Installment Payments</h5>
                    <div class="small text-muted mt-1">
                        <strong><?= $paidInstallmentsCount ?></strong> / <strong><?= $totalInstallments ?></strong> installments paid
                        <?php if (!$isFullyPaid && $installmentsLeft > 0): ?>
                            <span class="badge bg-warning text-dark ms-1"><?= $installmentsLeft ?> left</span>
                        <?php elseif ($isFullyPaid || $installmentsLeft === 0): ?>
                            <span class="badge bg-success ms-1">All paid</span>
                        <?php endif; ?>

                        <?php if ($studentPaymentOption === 'one_time'): ?>
                            <span class="badge bg-primary ms-1">Plan: 1 installment (Full)</span>
                        <?php elseif ($studentPaymentOption === 'second'): ?>
                            <span class="badge bg-info text-dark ms-1">Plan: 2 installments</span>
                        <?php elseif ($studentPaymentOption === 'third'): ?>
                            <span class="badge bg-warning text-dark ms-1">Plan: 3 installments</span>
                        <?php elseif ($studentPaymentOption === 'normal'): ?>
                            <span class="badge bg-secondary ms-1">Plan: Normal (<?= $totalInstallments ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($isFullyPaid): ?>
                    <button class="btn btn-sm btn-success" disabled>
                        <i class="bi bi-check-circle"></i> Fully Paid
                    </button>
                <?php else: ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        <i class="bi bi-plus"></i> Add Payment
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Payment Option</th>
                            <th>Amount</th>
                            <th>Bank</th>
                            <th>Ref No</th>
                            <th>Date</th>
                            <th>Month</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">No payments recorded</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= getPaymentLabel($p) ?></td>
                                <td class="fw-semibold text-success"><?= formatMoney($p['amount']) ?></td>
                                <td><?= htmlspecialchars($p['bank'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($p['ref_no'] ?? '-') ?></td>
                                <td><?= formatDate($p['payment_date']) ?></td>
                                <td><?= htmlspecialchars($p['payment_month'] ?? '-') ?></td>
                                <td><?= statusBadge($p['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($regFees): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0">Registration Fees</h5></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Amount</th><th>Bank</th><th>Ref</th><th>Date</th><th>Marked By</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regFees as $r): ?>
                        <tr>
                            <td><?= formatMoney($r['amount']) ?></td>
                            <td><?= htmlspecialchars($r['bank'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['ref_no'] ?? '-') ?></td>
                            <td><?= formatDate($r['payment_date']) ?></td>
                            <td><?= htmlspecialchars($r['slip_marked_by'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== ADD PAYMENT MODAL ==================== -->
<?php if (!$isFullyPaid): ?>
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="add_payment" value="1">

                <div class="mb-3">
                    <label class="form-label">Payment Option *</label>
                    <select name="payment_option" id="payment_option" class="form-select" required>
                        <option value="">— Select Payment Type —</option>
                        <option value="one_time" <?= $studentPaymentOption === 'one_time' ? 'selected' : '' ?>>
                            One Time Pay (1 installment – Full)
                        </option>
                        <option value="second" <?= $studentPaymentOption === 'second' ? 'selected' : '' ?>>
                            2nd Pay (2 installments)
                        </option>
                        <option value="third" <?= $studentPaymentOption === 'third' ? 'selected' : '' ?>>
                            3rd Pay (3 installments)
                        </option>
                        <option value="normal" <?= $studentPaymentOption === 'normal' ? 'selected' : '' ?>>
                            Normal Pay (Batch installments)
                        </option>
                    </select>
                    <?php if ($studentPaymentOption): ?>
                        <div class="form-text text-success">
                            Student preferred: <strong><?= htmlspecialchars($studentPaymentOption) ?></strong>
                            → Total installments for this plan: <strong><?= $totalInstallments ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Installment No – only for Normal Pay -->
                <div class="mb-3" id="installment_no_wrapper" style="<?= $studentPaymentOption === 'normal' ? '' : 'display:none;' ?>">
                    <label class="form-label">Installment No *</label>
                    <select name="installment_no" id="installment_no" class="form-select">
                        <option value="">— Select Unpaid Installment —</option>
                        <?php foreach ($unpaidInstallments as $num): ?>
                            <option value="<?= $num ?>">Installment #<?= $num ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($unpaidInstallments)): ?>
                        <div class="form-text text-danger">All installments are already paid.</div>
                    <?php else: ?>
                        <div class="form-text">Unpaid: <?= implode(', ', $unpaidInstallments) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Amount *</label>
                    <input type="number" name="amount" id="amount" class="form-control" required step="0.01">
                </div>
                <div class="mb-3">
                    <label class="form-label">Bank</label>
                    <input type="text" name="bank" class="form-control" placeholder="BOC / Sampath / HNB / Peoples">
                </div>
                <div class="mb-3">
                    <label class="form-label">Ref No</label>
                    <input type="text" name="ref_no" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Month Note</label>
                    <input type="text" name="payment_month" class="form-control" placeholder="e.g. November 06.12.2023">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
const courseFee            = <?= (float)$courseFee ?>;
const balance              = <?= (float)$balance ?>;
const installmentAmount    = <?= (float)($student['installment_amount'] ?? 0) ?>;
const unpaidInstallments   = <?= json_encode(array_values($unpaidInstallments)) ?>;
const studentPaymentOption = <?= json_encode($studentPaymentOption) ?>;
const planTotalInstallments = <?= (int)$totalInstallments ?>;

const paymentOption       = document.getElementById('payment_option');
const installmentWrapper  = document.getElementById('installment_no_wrapper');
const installmentNoSelect = document.getElementById('installment_no');
const amountInput         = document.getElementById('amount');

function updatePaymentFields() {
    const option = paymentOption.value;

    // Installment No only for Normal
    if (option === 'normal') {
        installmentWrapper.style.display = 'block';
        installmentNoSelect.required = true;
        if (unpaidInstallments.length > 0 && !installmentNoSelect.value) {
            installmentNoSelect.value = unpaidInstallments[0];
        }
    } else {
        installmentWrapper.style.display = 'none';
        installmentNoSelect.required = false;
        installmentNoSelect.value = '';
    }

    // Amount
    if (option === 'one_time') {
        amountInput.value = balance > 0 ? balance.toFixed(2) : '';
    }
    else if (option === 'second') {
        amountInput.value = courseFee > 0 ? (courseFee / 2).toFixed(2) : '';
    }
    else if (option === 'third') {
        amountInput.value = courseFee > 0 ? (courseFee / 3).toFixed(2) : '';
    }
    else if (option === 'normal') {
        let amt = installmentAmount > 0 ? installmentAmount : 0;
        if (balance > 0 && amt > balance) amt = balance;
        amountInput.value = amt > 0 ? amt.toFixed(2) : (balance > 0 ? balance.toFixed(2) : '');
    }
    else {
        amountInput.value = '';
    }
}

paymentOption.addEventListener('change', updatePaymentFields);

document.getElementById('addPaymentModal').addEventListener('show.bs.modal', function () {
    if (studentPaymentOption) {
        paymentOption.value = studentPaymentOption;
    } else {
        paymentOption.value = '';
    }
    updatePaymentFields();
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>