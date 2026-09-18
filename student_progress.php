<?php
$pageTitle = 'Student Progress';
$activePage = 'progress-view';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/database.php';

/* =========================================================================
   COLUMN NAME MAP — keep identical to assignments.php / exams.php
   ========================================================================= */
$STUDENTS_NAME_COL   = 'full_name';
$STUDENTS_BATCH_FK   = 'batch_id';

$BATCHES_NAME_COL    = 'batch_name';
$BATCHES_COURSE_FK   = 'course_id';

$COURSES_NAME_COL    = 'course_name';

// assignments table
$ASSIGN_TABLE          = 'assignments';
$ASSIGN_NUMBER_COL     = 'assignment_no';
$ASSIGN_MARKS_COL      = 'marks';
$ASSIGN_STATUS_COL     = 'status';     // 'submitted' / 'pending'
$ASSIGN_FEEDBACK_COL   = 'feedback';

// exams table
$EXAMS_TABLE          = 'exams';
$EXAMS_NUMBER_COL     = 'exam_no';
$EXAMS_DATE_COL       = 'exam_date';
$EXAMS_MARKS_COL      = 'marks';
$EXAMS_RESULT_COL     = 'result';      // free text: Pass / Fail / Grade
$EXAMS_NOTES_COL      = 'notes';

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
   FETCH STUDENTS + BOTH assignments and exams, auto-detecting how many
   of each exist for this batch
   ========================================================================= */
$students = [];
$assignment_numbers = [];
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

        // Assignments
        $aStmt = $pdo->prepare("SELECT * FROM `$ASSIGN_TABLE` WHERE student_id IN ($placeholders)");
        $aStmt->execute($ids);
        $assignData = [];
        foreach ($aStmt->fetchAll() as $row) {
            $assignData[$row['student_id']][$row[$ASSIGN_NUMBER_COL]] = $row;
            $assignment_numbers[(int)$row[$ASSIGN_NUMBER_COL]] = true;
        }
        ksort($assignment_numbers);
        $assignment_numbers = array_keys($assignment_numbers);
        if (empty($assignment_numbers)) $assignment_numbers = [1];

        // Exams
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
            $row = ['id' => $s['id'], 'name' => $s['name'], 'assignments' => [], 'exams' => []];
            foreach ($assignment_numbers as $n) {
                $row['assignments'][$n] = $assignData[$s['id']][$n] ?? null;
            }
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
$assignTotal = 0; $assignSubmitted = 0; $assignMarksSum = 0; $assignMarksCount = 0;
$examTotal = 0; $examRecorded = 0; $examMarksSum = 0; $examMarksCount = 0;

foreach ($students as $s) {
    foreach ($s['assignments'] as $rec) {
        $assignTotal++;
        if (!empty($rec) && $rec[$ASSIGN_STATUS_COL] === 'submitted') $assignSubmitted++;
        if (!empty($rec) && $rec[$ASSIGN_MARKS_COL] !== null) {
            $assignMarksSum += (float)$rec[$ASSIGN_MARKS_COL];
            $assignMarksCount++;
        }
    }
    foreach ($s['exams'] as $rec) {
        $examTotal++;
        if (!empty($rec) && $rec[$EXAMS_MARKS_COL] !== null) {
            $examRecorded++;
            $examMarksSum += (float)$rec[$EXAMS_MARKS_COL];
            $examMarksCount++;
        }
    }
}
$assignRate = $assignTotal > 0 ? round(($assignSubmitted / $assignTotal) * 100, 1) : 0;
$assignAvg  = $assignMarksCount > 0 ? round($assignMarksSum / $assignMarksCount, 1) : null;
$examRate   = $examTotal > 0 ? round(($examRecorded / $examTotal) * 100, 1) : 0;
$examAvg    = $examMarksCount > 0 ? round($examMarksSum / $examMarksCount, 1) : null;
?>
<style>
    @media print {
        .no-print { display: none !important; }
        .page-header { margin-bottom: 10px; }
    }
    .section-divider {
        background: #f1f3f5;
        font-weight: 600;
        text-align: center;
    }
</style>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap">
    <div>
        <h2><i class="bi bi-graph-up-arrow"></i> Student Progress</h2>
        <p class="text-muted">Assignments and exam results together, per student</p>
    </div>
    <div class="no-print">
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
                        <div class="text-muted small">Assignment Submission</div>
                        <div class="fs-4 fw-bold"><?= $assignRate ?>%</div>
                        <div class="text-muted small">Avg marks: <?= $assignAvg !== null ? $assignAvg : '-' ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Exam Recorded</div>
                        <div class="fs-4 fw-bold"><?= $examRate ?>%</div>
                        <div class="text-muted small">Avg marks: <?= $examAvg !== null ? $examAvg : '-' ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Tracked</div>
                        <div class="fs-6 fw-bold">
                            <?= count($assignment_numbers) ?> assignment(s)<br>
                            <?= count($exam_numbers) ?> exam(s)
                        </div>
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
                            <th rowspan="3" class="align-middle">#</th>
                            <th rowspan="3" class="align-middle">Student</th>
                            <th colspan="<?= count($assignment_numbers) * 3 ?>" class="section-divider">Assignments</th>
                            <th colspan="<?= count($exam_numbers) * 4 ?>" class="section-divider">Exams</th>
                        </tr>
                        <tr>
                            <?php foreach ($assignment_numbers as $n): ?>
                                <th colspan="3" class="text-center">Assignment <?= $n ?></th>
                            <?php endforeach; ?>
                            <?php foreach ($exam_numbers as $n): ?>
                                <th colspan="4" class="text-center">Exam <?= $n ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($assignment_numbers as $n): ?>
                                <th class="text-center">Status</th>
                                <th class="text-center">Marks</th>
                                <th>Feedback</th>
                            <?php endforeach; ?>
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

                            <?php foreach ($assignment_numbers as $n): ?>
                                <?php $rec = $s['assignments'][$n]; ?>
                                <td class="text-center">
                                    <?php if (!empty($rec) && $rec[$ASSIGN_STATUS_COL] === 'submitted'): ?>
                                        <span class="badge bg-success">Submitted</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?= (!empty($rec) && $rec[$ASSIGN_MARKS_COL] !== null) ? htmlspecialchars($rec[$ASSIGN_MARKS_COL]) : '-' ?>
                                </td>
                                <td>
                                    <?= (!empty($rec) && $rec[$ASSIGN_FEEDBACK_COL] !== null && $rec[$ASSIGN_FEEDBACK_COL] !== '')
                                            ? htmlspecialchars($rec[$ASSIGN_FEEDBACK_COL]) : '-' ?>
                                </td>
                            <?php endforeach; ?>

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
                                    <?php if (!empty($rec) && $rec[$EXAMS_RESULT_COL] !== null && $rec[$EXAMS_RESULT_COL] !== ''): ?>
                                        <span class="badge bg-info text-dark"><?= htmlspecialchars($rec[$EXAMS_RESULT_COL]) ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= (!empty($rec) && $rec[$EXAMS_NOTES_COL] !== null && $rec[$EXAMS_NOTES_COL] !== '')
                                            ? htmlspecialchars($rec[$EXAMS_NOTES_COL]) : '-' ?>
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
    <div class="alert alert-info">Select a course and batch above to view student progress.</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>