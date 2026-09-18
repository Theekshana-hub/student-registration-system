<?php
$pageTitle = 'Assignments';
$activePage = 'assignments';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'config/database.php';

/* =========================================================================
   COLUMN NAME MAP — edit this section only if your actual column names
   are different from what's guessed below. This is the ONLY section you
   should need to touch to match your database.
   ========================================================================= */
$STUDENTS_NAME_COL   = 'full_name';  // students table: student's full name
$STUDENTS_BATCH_FK   = 'batch_id';   // students table: FK to batches.id

$BATCHES_NAME_COL    = 'batch_name'; // batches table: batch's display name
$BATCHES_COURSE_FK   = 'course_id';  // batches table: FK to courses.id

$COURSES_NAME_COL    = 'course_name'; // courses table: course's display name

/* =========================================================================
   HANDLE SAVE (POST)
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assignments'])) {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $batch_id  = (int)($_POST['batch_id'] ?? 0);
    $rows = $_POST['a'] ?? []; // $rows[student_id][assignment_no] = ['status'=>..,'marks'=>..,'feedback'=>..]

    try {
        $pdo->beginTransaction();

        $check  = $pdo->prepare("SELECT id FROM assignments WHERE student_id = ? AND assignment_no = ?");
        $update = $pdo->prepare("UPDATE assignments
                                  SET status = ?, marks = ?, feedback = ?, submitted_date = ?
                                  WHERE student_id = ? AND assignment_no = ?");
        $insert = $pdo->prepare("INSERT INTO assignments
                                  (student_id, assignment_no, submitted_date, marks, status, feedback, created_at)
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())");

        foreach ($rows as $student_id => $assignments) {
            foreach ($assignments as $assignment_no => $data) {
                $status   = ($data['status'] ?? '') === 'submitted' ? 'submitted' : 'pending';
                $marksRaw = trim($data['marks'] ?? '');
                $marks    = ($marksRaw === '') ? null : (float)$marksRaw;
                $feedback = trim($data['feedback'] ?? '');
                $submitted_date = $status === 'submitted' ? date('Y-m-d') : null;

                $check->execute([(int)$student_id, (int)$assignment_no]);
                $existing = $check->fetch();

                if ($existing) {
                    $update->execute([$status, $marks, $feedback, $submitted_date, (int)$student_id, (int)$assignment_no]);
                } else {
                    $insert->execute([(int)$student_id, (int)$assignment_no, $submitted_date, $marks, $status, $feedback]);
                }
            }
        }

        $pdo->commit();
        $success = "Assignment records saved successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving records: " . $e->getMessage();
    }

    // Preserve selections after save (redisplay same course/batch/count)
    $_GET['course_id'] = $course_id;
    $_GET['batch_id']  = $batch_id;
}

/* =========================================================================
   READ FILTERS FROM GET
   ========================================================================= */
$selected_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selected_batch  = isset($_GET['batch_id'])  ? (int)$_GET['batch_id']  : 0;
$num_assignments = isset($_GET['num_assignments']) ? max(1, (int)$_GET['num_assignments']) : 3;

/* =========================================================================
   FETCH COURSES for the first dropdown
   ========================================================================= */
$courses = $pdo->query("SELECT id, `$COURSES_NAME_COL` AS course_name FROM courses ORDER BY `$COURSES_NAME_COL` ASC")->fetchAll();

/* =========================================================================
   FETCH BATCHES — only those belonging to the selected course
   ========================================================================= */
$batches = [];
if ($selected_course > 0) {
    $stmt = $pdo->prepare("SELECT id, `$BATCHES_NAME_COL` AS batch_name
                            FROM batches
                            WHERE `$BATCHES_COURSE_FK` = ?
                            ORDER BY `$BATCHES_NAME_COL` ASC");
    $stmt->execute([$selected_course]);
    $batches = $stmt->fetchAll();

    // If the previously selected batch doesn't belong to this course anymore, reset it
    if ($selected_batch > 0 && !in_array($selected_batch, array_column($batches, 'id'))) {
        $selected_batch = 0;
    }
}

/* =========================================================================
   FETCH STUDENTS + their assignment records for the selected batch.
   $num_assignments controls how many "Assignment N" columns are shown
   (Assignment 1, Assignment 2, Assignment 3, ...). Bump the number in the
   "How many assignments?" box on the page to add more columns — no code
   change needed, and it auto-detects the highest assignment_no already
   saved for this batch too.
   ========================================================================= */
$students = [];
if ($selected_batch > 0) {
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
        $maxFound = 0;
        foreach ($aStmt->fetchAll() as $row) {
            $assignmentData[$row['student_id']][$row['assignment_no']] = $row;
            if ((int)$row['assignment_no'] > $maxFound) $maxFound = (int)$row['assignment_no'];
        }
        // Auto-expand the column count if there's saved data beyond the current setting
        if ($maxFound > $num_assignments) {
            $num_assignments = $maxFound;
        }

        foreach ($studentRows as $s) {
            $row = ['id' => $s['id'], 'name' => $s['name'], 'assignments' => []];
            for ($n = 1; $n <= $num_assignments; $n++) {
                $row['assignments'][$n] = $assignmentData[$s['id']][$n] ?? null;
            }
            $students[] = $row;
        }
    }
}
?>
<div class="page-header">
    <h2><i class="bi bi-journal-text"></i> Assignments</h2>
    <p class="text-muted">Track student assignment submissions</p>
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
                <label class="form-label">How many assignments?</label>
                <input type="number" name="num_assignments" min="1" max="20"
                       value="<?= (int)$num_assignments ?>" class="form-control"
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
                                <?php for ($n = 1; $n <= $num_assignments; $n++): ?>
                                    <th colspan="3" class="text-center">Assignment <?= $n ?></th>
                                <?php endfor; ?>
                            </tr>
                            <tr>
                                <?php for ($n = 1; $n <= $num_assignments; $n++): ?>
                                    <th>Submitted</th><th>Marks</th><th>Feedback</th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>

                                <?php for ($n = 1; $n <= $num_assignments; $n++): ?>
                                    <?php $rec = $s['assignments'][$n]; ?>
                                    <td class="text-center">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               name="a[<?= $s['id'] ?>][<?= $n ?>][status]"
                                               value="submitted"
                                               <?= (!empty($rec) && $rec['status'] === 'submitted') ? 'checked' : '' ?>>
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0" max="100"
                                               class="form-control form-control-sm"
                                               name="a[<?= $s['id'] ?>][<?= $n ?>][marks]"
                                               value="<?= htmlspecialchars($rec['marks'] ?? '') ?>"
                                               placeholder="-">
                                    </td>
                                    <td>
                                        <input type="text"
                                               class="form-control form-control-sm"
                                               name="a[<?= $s['id'] ?>][<?= $n ?>][feedback]"
                                               value="<?= htmlspecialchars($rec['feedback'] ?? '') ?>"
                                               placeholder="optional">
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" name="save_assignments" class="btn btn-primary">
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