<?php
$pageTitle = 'Exams';
$activePage = 'exams';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/database.php';

/* =========================================================================
   TABLE + COLUMN NAME MAP — edit this section only if your actual table
   or column names are different from what's guessed below. This is the
   ONLY section you should need to touch to match your database.

   Go to phpMyAdmin -> dbm_management -> look at the table list on the
   left, and put the EXACT table names below (case matters on some setups).
   ========================================================================= */
$STUDENTS_TABLE       = 'students';    // <-- change if your table is named differently (e.g. 'student', 'tbl_students')
$STUDENTS_NAME_COL    = 'full_name';
$STUDENTS_BATCH_FK    = 'batch_id';

$BATCHES_TABLE        = 'batches';     // <-- check this too
$BATCHES_NAME_COL     = 'batch_name';
$BATCHES_COURSE_FK    = 'course_id';

$COURSES_TABLE        = 'courses';     // <-- and this
$COURSES_NAME_COL     = 'course_name';

// `exams` table — guessed to mirror `assignments`: id, student_id, exam_no,
// exam_date, marks, status, feedback, created_at. Check your actual table
// (phpMyAdmin -> exams -> Structure) and fix the names below if different.
$EXAMS_TABLE         = 'exams';
$EXAMS_NUMBER_COL    = 'exam_no';     // e.g. 1 = "Exam 1", 2 = "Exam 2"...
$EXAMS_DATE_COL      = 'exam_date';
$EXAMS_MARKS_COL     = 'marks';
$EXAMS_STATUS_COL    = 'result';      // your table uses `result`
$EXAMS_FEEDBACK_COL  = 'notes';       // your table uses `notes`

/* =========================================================================
   HANDLE SAVE (POST)
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_exams'])) {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $batch_id  = (int)($_POST['batch_id'] ?? 0);
    $rows = $_POST['e'] ?? []; // $rows[student_id][exam_no] = ['status'=>..,'marks'=>..,'feedback'=>..,'date'=>..]

    try {
        $pdo->beginTransaction();

        $check  = $pdo->prepare("SELECT id FROM `$EXAMS_TABLE` WHERE student_id = ? AND `$EXAMS_NUMBER_COL` = ?");
        $update = $pdo->prepare("UPDATE `$EXAMS_TABLE`
                                  SET `$EXAMS_STATUS_COL` = ?, `$EXAMS_MARKS_COL` = ?,
                                      `$EXAMS_FEEDBACK_COL` = ?, `$EXAMS_DATE_COL` = ?
                                  WHERE student_id = ? AND `$EXAMS_NUMBER_COL` = ?");
        $insert = $pdo->prepare("INSERT INTO `$EXAMS_TABLE`
                                  (student_id, `$EXAMS_NUMBER_COL`, `$EXAMS_DATE_COL`, `$EXAMS_MARKS_COL`, `$EXAMS_STATUS_COL`, `$EXAMS_FEEDBACK_COL`, created_at)
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())");

        foreach ($rows as $student_id => $exams) {
            foreach ($exams as $exam_no => $data) {
                $result   = trim($data['result'] ?? '');   // e.g. Pass / Fail / A / B ... free text
                $marksRaw = trim($data['marks'] ?? '');
                $marks    = ($marksRaw === '') ? null : (float)$marksRaw;
                $notes    = trim($data['notes'] ?? '');
                $dateRaw  = trim($data['date'] ?? '');
                $exam_date = $dateRaw !== '' ? $dateRaw : null;

                // Skip completely empty rows (nothing entered for this student/exam)
                if ($result === '' && $marks === null && $notes === '' && $exam_date === null) {
                    continue;
                }

                $check->execute([(int)$student_id, (int)$exam_no]);
                $existing = $check->fetch();

                if ($existing) {
                    $update->execute([$result, $marks, $notes, $exam_date, (int)$student_id, (int)$exam_no]);
                } else {
                    $insert->execute([(int)$student_id, (int)$exam_no, $exam_date, $marks, $result, $notes]);
                }
            }
        }

        $pdo->commit();
        $success = "Exam records saved successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving records: " . $e->getMessage();
    }

    $_GET['course_id'] = $course_id;
    $_GET['batch_id']  = $batch_id;
}

/* =========================================================================
   READ FILTERS FROM GET
   ========================================================================= */
$selected_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selected_batch  = isset($_GET['batch_id'])  ? (int)$_GET['batch_id']  : 0;
$num_exams       = isset($_GET['num_exams']) ? max(1, (int)$_GET['num_exams']) : 2;

/* =========================================================================
   FETCH COURSES
   ========================================================================= */
$courses = $pdo->query("SELECT id, `$COURSES_NAME_COL` AS course_name FROM `$COURSES_TABLE` ORDER BY `$COURSES_NAME_COL` ASC")->fetchAll();

/* =========================================================================
   FETCH BATCHES — only those belonging to the selected course
   ========================================================================= */
$batches = [];
if ($selected_course > 0) {
    $stmt = $pdo->prepare("SELECT id, `$BATCHES_NAME_COL` AS batch_name
                            FROM `$BATCHES_TABLE`
                            WHERE `$BATCHES_COURSE_FK` = ?
                            ORDER BY `$BATCHES_NAME_COL` ASC");
    $stmt->execute([$selected_course]);
    $batches = $stmt->fetchAll();

    if ($selected_batch > 0 && !in_array($selected_batch, array_column($batches, 'id'))) {
        $selected_batch = 0;
    }
}

/* =========================================================================
   FETCH STUDENTS + their exam records for the selected batch
   ========================================================================= */
$students = [];
if ($selected_batch > 0) {
    $stmt = $pdo->prepare("SELECT id, `$STUDENTS_NAME_COL` AS name
                            FROM `$STUDENTS_TABLE`
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
        $maxFound = 0;
        foreach ($eStmt->fetchAll() as $row) {
            $examData[$row['student_id']][$row[$EXAMS_NUMBER_COL]] = $row;
            if ((int)$row[$EXAMS_NUMBER_COL] > $maxFound) $maxFound = (int)$row[$EXAMS_NUMBER_COL];
        }
        if ($maxFound > $num_exams) {
            $num_exams = $maxFound;
        }

        foreach ($studentRows as $s) {
            $row = ['id' => $s['id'], 'name' => $s['name'], 'exams' => []];
            for ($n = 1; $n <= $num_exams; $n++) {
                $row['exams'][$n] = $examData[$s['id']][$n] ?? null;
            }
            $students[] = $row;
        }
    }
}
?>
<div class="page-header">
    <h2><i class="bi bi-pencil-square"></i> Exams</h2>
    <p class="text-muted">Link exam marks and results to each student</p>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">1. Select Course</label>
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
                <label class="form-label">2. Select Batch</label>
                <select name="batch_id" class="form-select" onchange="this.form.submit()" <?= $selected_course == 0 ? 'disabled' : '' ?>>
                    <option value="0">-- Select a batch --</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $selected_batch == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['batch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">How many exams?</label>
                <input type="number" name="num_exams" min="1" max="20"
                       value="<?= (int)$num_exams ?>" class="form-control"
                       onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<?php if ($selected_course > 0 && $selected_batch > 0): ?>
    <?php if (empty($students)): ?>
        <div class="alert alert-warning">No students found in this batch.</div>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="course_id" value="<?= $selected_course ?>">
            <input type="hidden" name="batch_id" value="<?= $selected_batch ?>">
            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" class="align-middle">Student</th>
                                <?php for ($n = 1; $n <= $num_exams; $n++): ?>
                                    <th colspan="4" class="text-center">Exam <?= $n ?></th>
                                <?php endfor; ?>
                            </tr>
                            <tr>
                                <?php for ($n = 1; $n <= $num_exams; $n++): ?>
                                    <th>Date</th><th>Marks</th><th>Result</th><th>Notes</th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>

                                <?php for ($n = 1; $n <= $num_exams; $n++): ?>
                                    <?php $rec = $s['exams'][$n]; ?>
                                    <td>
                                        <input type="date"
                                               class="form-control form-control-sm"
                                               name="e[<?= $s['id'] ?>][<?= $n ?>][date]"
                                               value="<?= htmlspecialchars(($rec[$EXAMS_DATE_COL] ?? '') && $rec[$EXAMS_DATE_COL] !== '0000-00-00' ? $rec[$EXAMS_DATE_COL] : '') ?>">
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0" max="100"
                                               class="form-control form-control-sm"
                                               name="e[<?= $s['id'] ?>][<?= $n ?>][marks]"
                                               value="<?= htmlspecialchars($rec[$EXAMS_MARKS_COL] ?? '') ?>"
                                               placeholder="-">
                                    </td>
                                    <td>
                                        <input type="text"
                                               class="form-control form-control-sm"
                                               name="e[<?= $s['id'] ?>][<?= $n ?>][result]"
                                               value="<?= htmlspecialchars($rec[$EXAMS_STATUS_COL] ?? '') ?>"
                                               placeholder="Pass / Fail / Grade">
                                    </td>
                                    <td>
                                        <input type="text"
                                               class="form-control form-control-sm"
                                               name="e[<?= $s['id'] ?>][<?= $n ?>][notes]"
                                               value="<?= htmlspecialchars($rec[$EXAMS_FEEDBACK_COL] ?? '') ?>"
                                               placeholder="optional">
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" name="save_exams" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save All
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
<?php elseif ($selected_course > 0): ?>
    <div class="alert alert-info">Now select a batch above.</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>