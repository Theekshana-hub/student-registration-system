<?php
$pageTitle = 'View Assignments';
$activePage = 'assignments-view';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/database.php';

/* =========================================================================
   COLUMN NAME MAP — keep this identical to assignments.php so both pages
   agree on your actual database structure.
   ========================================================================= */
$STUDENTS_NAME_COL   = 'full_name';
$STUDENTS_BATCH_FK   = 'batch_id';

$BATCHES_NAME_COL    = 'batch_name';
$BATCHES_COURSE_FK   = 'course_id';

$COURSES_NAME_COL    = 'course_name';

/* =========================================================================
   READ FILTERS
   ========================================================================= */
$selected_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selected_batch  = isset($_GET['batch_id'])  ? (int)$_GET['batch_id']  : 0;

/* =========================================================================
   COURSES + BATCHES for filter dropdowns
   ========================================================================= */
$courses = $pdo->query("SELECT id, `$COURSES_NAME_COL` AS course_name FROM courses ORDER BY `$COURSES_NAME_COL` ASC")->fetchAll();

$batches = [];
if ($selected_course > 0) {
    $stmt = $pdo->prepare("SELECT id, `$BATCHES_NAME_COL` AS batch_name
                            FROM batches
                            WHERE `$BATCHES_COURSE_FK` = ?
                            ORDER BY `$BATCHES_NAME_COL` ASC");
    $stmt->execute([$selected_course]);
    $batches = $stmt->fetchAll();

    if ($selected_batch > 0 && !in_array($selected_batch, array_column($batches, 'id'))) {
        $selected_batch = 0;
    }
}

/* =========================================================================
   FETCH STUDENTS + ALL their assignment records (view auto-detects however
   many assignment numbers exist — no manual count needed here)
   ========================================================================= */
$students = [];
$assignment_numbers = [];
$batch_name_display = '';

if ($selected_batch > 0) {
    foreach ($batches as $b) {
        if ($b['id'] == $selected_batch) { $batch_name_display = $b['batch_name']; break; }
    }

    $stmt = $pdo->prepare("SELECT id, `$STUDENTS_NAME_COL` AS name
                            FROM students
                            WHERE `$STUDENTS_BATCH_FK` = ?
                            ORDER BY `$STUDENTS_NAME_COL` ASC");
    $stmt->execute([$selected_batch]);
    $studentRows = $stmt->fetchAll();

    if ($studentRows) {
        $ids = array_column($studentRows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $aStmt = $pdo->prepare("SELECT * FROM assignments WHERE student_id IN ($placeholders)");
        $aStmt->execute($ids);

        $assignmentData = [];
        foreach ($aStmt->fetchAll() as $row) {
            $assignmentData[$row['student_id']][$row['assignment_no']] = $row;
            $assignment_numbers[(int)$row['assignment_no']] = true;
        }
        ksort($assignment_numbers);
        $assignment_numbers = array_keys($assignment_numbers);
        if (empty($assignment_numbers)) $assignment_numbers = [1]; // nothing saved yet, show at least Assignment 1

        foreach ($studentRows as $s) {
            $row = ['id' => $s['id'], 'name' => $s['name'], 'assignments' => []];
            foreach ($assignment_numbers as $n) {
                $row['assignments'][$n] = $assignmentData[$s['id']][$n] ?? null;
            }
            $students[] = $row;
        }
    }
}

/* =========================================================================
   SUMMARY STATS
   ========================================================================= */
$totalCells = 0;
$submittedCells = 0;
$marksSum = 0;
$marksCount = 0;

foreach ($students as $s) {
    foreach ($s['assignments'] as $rec) {
        $totalCells++;
        if (!empty($rec) && $rec['status'] === 'submitted') {
            $submittedCells++;
        }
        if (!empty($rec) && $rec['marks'] !== null) {
            $marksSum += (float)$rec['marks'];
            $marksCount++;
        }
    }
}
$submissionRate = $totalCells > 0 ? round(($submittedCells / $totalCells) * 100, 1) : 0;
$avgMarks = $marksCount > 0 ? round($marksSum / $marksCount, 1) : null;
?>
<style>
    @media print {
        .no-print { display: none !important; }
        .page-header { margin-bottom: 10px; }
    }
</style>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap">
    <div>
        <h2><i class="bi bi-eye"></i> View Assignments</h2>
        <p class="text-muted">Read-only report of assignment submissions</p>
    </div>
    <div class="no-print">
        <a href="assignments.php<?= $selected_batch ? '?course_id=' . $selected_course . '&batch_id=' . $selected_batch : '' ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil"></i> Edit Assignments
        </a>
        <?php if ($selected_batch): ?>
            <button onclick="window.print()" class="btn btn-outline-secondary">
                <i class="bi bi-printer"></i> Print
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Course</label>
                <select name="course_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">-- Select a course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selected_course == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Batch</label>
                <select name="batch_id" class="form-select" onchange="this.form.submit()" <?= $selected_course == 0 ? 'disabled' : '' ?>>
                    <option value="0">-- Select a batch --</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $selected_batch == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['batch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_batch > 0): ?>

    <?php if (empty($students)): ?>
        <div class="alert alert-warning">No students found in this batch.</div>
    <?php else: ?>

        <div class="row mb-3 no-print">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Students</div>
                        <div class="fs-4 fw-bold"><?= count($students) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Submission Rate</div>
                        <div class="fs-4 fw-bold"><?= $submissionRate ?>%</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Average Marks</div>
                        <div class="fs-4 fw-bold"><?= $avgMarks !== null ? $avgMarks : '-' ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Assignments Tracked</div>
                        <div class="fs-4 fw-bold"><?= count($assignment_numbers) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <strong><?= htmlspecialchars($batch_name_display) ?></strong>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-bordered align-middle table-striped">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2" class="align-middle">#</th>
                            <th rowspan="2" class="align-middle">Student</th>
                            <?php foreach ($assignment_numbers as $n): ?>
                                <th colspan="3" class="text-center">Assignment <?= $n ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($assignment_numbers as $n): ?>
                                <th class="text-center">Status</th>
                                <th class="text-center">Marks</th>
                                <th>Feedback</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $i => $s): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                            <?php foreach ($assignment_numbers as $n): ?>
                                <?php $rec = $s['assignments'][$n]; ?>
                                <td class="text-center">
                                    <?php if (!empty($rec) && $rec['status'] === 'submitted'): ?>
                                        <span class="badge bg-success">Submitted</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?= (!empty($rec) && $rec['marks'] !== null) ? htmlspecialchars($rec['marks']) : '-' ?>
                                </td>
                                <td>
                                    <?= (!empty($rec) && $rec['feedback'] !== null && $rec['feedback'] !== '')
                                            ? htmlspecialchars($rec['feedback']) : '-' ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

<?php elseif ($selected_course > 0): ?>
    <div class="alert alert-info">Now select a batch above.</div>
<?php else: ?>
    <div class="alert alert-info">Select a course and batch above to view assignment records.</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>