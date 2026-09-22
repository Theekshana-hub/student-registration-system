<?php
$pageTitle = 'Add Batch';
$activePage = 'add-batch';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$success = $error = '';


$allCourses = $pdo->query("
    SELECT id, course_code, course_name, course_fee 
    FROM courses 
    WHERE status = 'active' 
    ORDER BY course_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO batches (
                course_id, batch_code, batch_name, start_date, end_date, 
                course_fee, installment_amount, total_installments, 
                duration_months, status, notes
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$_POST['course_id'],          
            $_POST['batch_code'],
            $_POST['batch_name'],
            $_POST['start_date'] ?: null,
            $_POST['end_date'] ?: null,
            $_POST['course_fee'],
            $_POST['installment_amount'],
            $_POST['total_installments'],
            $_POST['duration_months'],
            $_POST['status'],
            $_POST['notes'] ?: null,
        ]);
        $success = "Batch added successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="page-header">
    <h2><i class="bi bi-plus-circle"></i> Add New Batch</h2>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<form method="POST" class="card shadow-sm" id="batchForm">
    <div class="card-body">
        <div class="row g-3">

            
            <div class="col-md-4">
                <label class="form-label">Course *</label>
                <select name="course_id" id="courseSelect" class="form-select" required>
                    <option value="">— Select Course —</option>
                    <?php foreach ($allCourses as $c): ?>
                        <option value="<?= $c['id'] ?>" data-fee="<?= htmlspecialchars($c['course_fee']) ?>">
                            <?= htmlspecialchars($c['course_code'] . ' — ' . $c['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            

            <div class="col-md-4">
                <label class="form-label">Batch Code *</label>
                <input type="text" name="batch_code" class="form-control" required placeholder="e.g. DBM-B27">
            </div>
            <div class="col-md-4">
                <label class="form-label">Batch Name *</label>
                <input type="text" name="batch_name" class="form-control" required placeholder="e.g. DBM 27 (6 Month)">
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="upcoming">Upcoming</option>
                    <option value="active" selected>Active</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Duration (Months)</label>
                <input type="number" name="duration_months" class="form-control" value="6">
            </div>
            <div class="col-md-3">
                <label class="form-label">Course Fee *</label>
                <input type="number" name="course_fee" id="courseFee" class="form-control" required value="24000" step="0.01">
            </div>
            <div class="col-md-3">
                <label class="form-label">Total Installments *</label>
                <input type="number" name="total_installments" id="totalInstallments" class="form-control" required value="6" min="1">
            </div>
            <div class="col-md-3">
                <label class="form-label">Installment Amount *</label>
                <input type="number" name="installment_amount" id="installmentAmount" class="form-control" required value="4000" step="0.01">
                <div class="form-text" id="installmentHint"></div>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Batch</button>
        <a href="batches.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
(function () {
    const courseSelect       = document.getElementById('courseSelect');
    const courseFee          = document.getElementById('courseFee');
    const totalInstallments  = document.getElementById('totalInstallments');
    const installmentAmount  = document.getElementById('installmentAmount');
    const installmentHint    = document.getElementById('installmentHint');

    function calculateInstallment() {
        const fee   = parseFloat(courseFee.value);
        const count = parseInt(totalInstallments.value, 10);

        if (!isNaN(fee) && fee > 0 && !isNaN(count) && count > 0) {
            const perInstallment = fee / count;
            installmentAmount.value = perInstallment.toFixed(2);

           
            const total = perInstallment * count;
            if (Math.abs(total - fee) > 0.01) {
                installmentHint.textContent =
                    'Total after rounding: ' + total.toFixed(2) + ' (course fee: ' + fee.toFixed(2) + ')';
                installmentHint.classList.add('text-warning');
            } else {
                installmentHint.textContent = '';
                installmentHint.classList.remove('text-warning');
            }
        }
    }


    courseSelect.addEventListener('change', function () {
        const selectedOption = this.options[this.selectedIndex];
        const fee = selectedOption.getAttribute('data-fee');

        if (fee !== null && fee !== '') {
            courseFee.value = parseFloat(fee).toFixed(2);
        }
        calculateInstallment();
    });

    
    courseFee.addEventListener('input', calculateInstallment);

    
    totalInstallments.addEventListener('input', calculateInstallment);
})();
</script>

<?php require_once 'includes/footer.php'; ?>