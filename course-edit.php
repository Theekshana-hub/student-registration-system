<?php
$pageTitle  = 'Edit Course';
$activePage = 'courses';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: courses.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$id]);
$course = $stmt->fetch();
if (!$course) { header('Location: courses.php'); exit; }

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code     = trim($_POST['course_code'] ?? '');
    $name     = trim($_POST['course_name'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $fee      = (float)($_POST['course_fee'] ?? 0);
    $status   = $_POST['status'] ?? 'active';

    if ($code === '')  $errors[] = 'Course code is required.';
    if ($name === '')  $errors[] = 'Course name is required.';
    if ($fee < 0)      $errors[] = 'Fee cannot be negative.';

    if ($code !== '') {
        $chk = $pdo->prepare("SELECT id FROM courses WHERE course_code = ? AND id != ?");
        $chk->execute([$code, $id]);
        if ($chk->fetch()) $errors[] = 'Course code already exists.';
    }

    if (empty($errors)) {
        $upd = $pdo->prepare("
            UPDATE courses
            SET course_code=?, course_name=?, description=?, duration=?, course_fee=?, status=?
            WHERE id=?
        ");
        $upd->execute([$code, $name, $desc ?: null, $duration ?: null, $fee, $status, $id]);
        $success = true;
        // refresh data
        $stmt->execute([$id]);
        $course = $stmt->fetch();
    }
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --coral:#e11d48; --teal:#0d9488; --ink:#0f172a; --muted:#64748b;
    --bg:#f0f2f5; --surface:#fff; --radius:16px;
    --shadow:0 1px 3px rgba(15,23,42,.04), 0 4px 16px rgba(15,23,42,.06);
}
body { font-family:'Plus Jakarta Sans',system-ui,sans-serif; background:var(--bg); color:var(--ink); }
.page-head { margin-bottom:1.5rem; }
.page-head h1 { font-size:1.5rem; font-weight:800; margin:0; display:flex; align-items:center; gap:.5rem; }
.page-head h1 i { color:var(--coral); }
.form-card {
    background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow);
    padding:1.75rem; max-width:640px; border:1px solid rgba(15,23,42,.04);
}
.form-label { font-weight:600; font-size:.85rem; margin-bottom:.35rem; }
.form-control, .form-select {
    border-radius:10px; border:1px solid #e2e8f0; padding:.6rem .9rem; font-size:.9rem;
}
.form-control:focus, .form-select:focus {
    border-color:var(--coral); box-shadow:0 0 0 3px rgba(225,29,72,.15);
}
.btn-save {
    background:var(--coral); color:#fff; border:none; border-radius:10px;
    padding:.6rem 1.4rem; font-weight:600; font-size:.9rem;
}
.btn-save:hover { background:#be123c; color:#fff; }
.btn-cancel {
    background:#f1f5f9; color:var(--muted); border:none; border-radius:10px;
    padding:.6rem 1.2rem; font-weight:600; font-size:.9rem; text-decoration:none;
}
.btn-cancel:hover { background:#e2e8f0; color:var(--ink); }
.alert { border-radius:12px; font-size:.9rem; }
</style>

<div class="page-head">
    <h1><i class="bi bi-pencil-square"></i> Edit Course</h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">Course updated successfully! <a href="courses.php">Back to list</a></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Course Code *</label>
                <input type="text" name="course_code" class="form-control"
                       value="<?= htmlspecialchars($course['course_code']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Course Name *</label>
                <input type="text" name="course_name" class="form-control"
                       value="<?= htmlspecialchars($course['course_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Duration</label>
                <input type="text" name="duration" class="form-control"
                       value="<?= htmlspecialchars($course['duration'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Course Fee (LKR)</label>
                <input type="number" name="course_fee" class="form-control" step="0.01" min="0"
                       value="<?= htmlspecialchars($course['course_fee']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($course['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active"   <?= $course['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $course['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2 mt-3">
                <button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Update Course</button>
                <a href="courses.php" class="btn-cancel">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>