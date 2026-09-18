<?php
$pageTitle  = 'Edit Batch';
$activePage = 'batches';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: batches.php');
    exit;
}

// Batch load
$stmt = $pdo->prepare("SELECT * FROM batches WHERE id = ?");
$stmt->execute([$id]);
$batch = $stmt->fetch();

if (!$batch) {
    header('Location: batches.php');
    exit;
}

// Active courses
$allCourses = $pdo->query("
    SELECT id, course_code, course_name 
    FROM courses 
    WHERE status = 'active' 
    ORDER BY course_name
")->fetchAll();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            UPDATE batches SET
                course_id           = ?,
                batch_code          = ?,
                batch_name          = ?,
                start_date          = ?,
                end_date            = ?,
                course_fee          = ?,
                installment_amount  = ?,
                total_installments  = ?,
                duration_months     = ?,
                status              = ?,
                notes               = ?
            WHERE id = ?
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
            $id
        ]);

        $success = "Batch updated successfully!";

        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM batches WHERE id = ?");
        $stmt->execute([$id]);
        $batch = $stmt->fetch();

    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="page-header">
    <h2><i class="bi bi-pencil-square"></i> Edit Batch</h2>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST" class="card shadow-sm">
    <div class="card-body">
        <div class="row g-3">

            <!-- Course Select -->
            <div class="col-md-4">
                <label class="form-label">Course *</label>
                <select name="course_id" class="form-select" required>
                    <option value="">— Select Course —</option>
                    <?php foreach ($allCourses as $c): ?>
                        <option value="<?= $c['id'] ?>"
                            <?= ((int)$batch['course_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['course_code'] . ' — ' . $c['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Batch Code *</label>
                <input type="text" name="batch_code" class="form-control" required
                       value="<?= htmlspecialchars($batch['batch_code']) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Batch Name *</label>
                <input type="text" name="batch_name" class="form-control" required
                       value="<?= htmlspecialchars($batch['batch_name']) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="upcoming"  <?= $batch['status'] === 'upcoming'  ? 'selected' : '' ?>>Upcoming</option>
                    <option value="active"    <?= $batch['status'] === 'active'    ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $batch['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control"
                       value="<?= htmlspecialchars($batch['start_date'] ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control"
                       value="<?= htmlspecialchars($batch['end_date'] ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Duration (Months)</label>
                <input type="number" name="duration_months" class="form-control"
                       value="<?= htmlspecialchars($batch['duration_months'] ?? 6) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Course Fee *</label>
                <input type="number" name="course_fee" class="form-control" required step="0.01"
                       value="<?= htmlspecialchars($batch['course_fee']) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Installment Amount *</label>
                <input type="number" name="installment_amount" class="form-control" required step="0.01"
                       value="<?= htmlspecialchars($batch['installment_amount']) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Total Installments *</label>
                <input type="number" name="total_installments" class="form-control" required
                       value="<?= htmlspecialchars($batch['total_installments']) ?>">
            </div>

            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($batch['notes'] ?? '') ?></textarea>
            </div>

        </div>
    </div>
    <div class="card-footer bg-white">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Update Batch</button>
        <a href="batches.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>