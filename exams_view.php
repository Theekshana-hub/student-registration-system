<?php
$pageTitle = 'View Exams';
$activePage = 'exams-view';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/database.php';

/* =========================================================================
   COLUMN NAME MAP — keep identical to exams.php
   ========================================================================= */
$STUDENTS_NAME_COL   = 'full_name';
$STUDENTS_BATCH_FK   = 'batch_id';

$BATCHES_NAME_COL    = 'batch_name';
$BATCHES_COURSE_FK   = 'course_id';

$COURSES_NAME_COL    = 'course_name';

$EXAMS_TABLE         = 'exams';
$EXAMS_NUMBER_COL    = 'exam_no';
$EXAMS_DATE_COL      = 'exam_date';
$EXAMS_MARKS_COL     = 'marks';
$EXAMS_STATUS_COL    = 'result';   // your table uses `result` (free text: Pass/Fail/Grade)
$EXAMS_FEEDBACK_COL  = 'notes';    // your table uses `notes`

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
   FETCH STUDENTS + ALL their exam records (auto-detects however many
   exam numbers exist)
   ========================================================================= */
$students = [];
$exam_numbers = [];
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
        $eStmt = $pdo->prepare("SELECT * FROM `$EXAMS_TABLE` WHERE student_id IN ($placeholders)");
        $eStmt->execute($ids);

        $examData = [];
        foreach ($eStmt->fetchAll() as $row) {
            $examData[$row['student_id']][$row[$EXAMS_NUMBER_COL]] = $row;
            $exam_numbers[(int)$row[$EXAMS_NUMBER_COL]] = true;
        }
        ksort($exam_numbers);
        $exam_numbers = array_keys($exam_numbers);
        if (empty($exam_numbers)) $exam_numbers = [1];

        foreach ($studentRows as $s) {
            $row = ['id' => $s['id'], 'name' => $s['name'], 'exams' => []];
            foreach ($exam_numbers as $n) {
                $row['exams'][$n] = $examData[$s['id']][$n] ?? null;
            }
            $students[] = $row;
        }
    }
}

/* =========================================================================
   SUMMARY STATS
   ========================================================================= */
$totalCells = 0;
$recordedCells = 0; // an exam counts as "recorded" once marks have been entered
$marksSum = 0;
$marksCount = 0;

foreach ($students as $s) {
    foreach ($s['exams'] as $rec) {
        $totalCells++;
        if (!empty($rec) && $rec[$EXAMS_MARKS_COL] !== null) {
            $recordedCells++;
            $marksSum += (float)$rec[$EXAMS_MARKS_COL];
            $marksCount++;
        }
    }
}
$completionRate = $totalCells > 0 ? round(($recordedCells / $totalCells) * 100, 1) : 0;
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
        <h2><i class="bi bi-eye"></i> View Exams</h2>
        <p class="text-muted">Read-only report of exam results</p>
    </div>
    <div class="no-print">
        <a href="exams.php<?= $selected_batch ? '?course_id=' . $selected_course . '&batch_id=' . $selected_batch : '' ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil"></i> Edit Exams
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
                        <div class="text-muted small">Recorded Rate</div>
                        <div class="fs-4 fw-bold"><?= $completionRate ?>%</div>
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
                        <div class="text-muted small">Exams Tracked</div>
                        <div class="fs-4 fw-bold"><?= count($exam_numbers) ?></div>
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
                            <?php foreach ($exam_numbers as $n): ?>
                                <th colspan="4" class="text-center">Exam <?= $n ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($exam_numbers as $n): ?>
                                <th class="text-center">Date</th>
                                <th class="text-center">Marks</th>
                                <th class="text-center">Result</th>
                                <th>Notes</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $i => $s): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                            <?php foreach ($exam_numbers as $n): ?>
                                <?php $rec = $s['exams'][$n]; ?>
                                <td class="text-center">
                                    <?= (!empty($rec) && !empty($rec[$EXAMS_DATE_COL]) && $rec[$EXAMS_DATE_COL] !== '0000-00-00')
                                            ? htmlspecialchars($rec[$EXAMS_DATE_COL]) : '-' ?>
                                </td>
                                <td class="text-center">
                                    <?= (!empty($rec) && $rec[$EXAMS_MARKS_COL] !== null) ? htmlspecialchars($rec[$EXAMS_MARKS_COL]) : '-' ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($rec) && $rec[$EXAMS_STATUS_COL] !== null && $rec[$EXAMS_STATUS_COL] !== ''): ?>
                                        <span class="badge bg-info text-dark"><?= htmlspecialchars($rec[$EXAMS_STATUS_COL]) ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= (!empty($rec) && $rec[$EXAMS_FEEDBACK_COL] !== null && $rec[$EXAMS_FEEDBACK_COL] !== '')
                                            ? htmlspecialchars($rec[$EXAMS_FEEDBACK_COL]) : '-' ?>
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
    <div class="alert alert-info">Select a course and batch above to view exam records.</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>