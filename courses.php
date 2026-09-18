<?php
$pageTitle  = 'All Courses';
$activePage = 'courses';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$courses = $pdo->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM batches b WHERE b.course_id = c.id) AS batch_count,
           (SELECT COUNT(*) FROM students s
            JOIN batches b ON s.batch_id = b.id
            WHERE b.course_id = c.id) AS student_count
    FROM courses c
    ORDER BY c.created_at DESC
")->fetchAll();
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --coral: #e11d48; --teal: #0d9488; --ink: #0f172a; --muted: #64748b;
    --bg: #f0f2f5; --surface: #fff; --radius: 16px;
    --shadow: 0 1px 3px rgba(15,23,42,.04), 0 4px 16px rgba(15,23,42,.06);
}
body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: var(--bg); color: var(--ink); }
.page-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; }
.page-head h1 { font-size:1.5rem; font-weight:800; margin:0; display:flex; align-items:center; gap:.5rem; }
.page-head h1 i { color: var(--coral); }
.btn-add {
    background: var(--coral); color:#fff; border:none; border-radius:10px;
    padding:.55rem 1.1rem; font-weight:600; font-size:.875rem;
    display:inline-flex; align-items:center; gap:.4rem; text-decoration:none;
    transition: all .2s;
}
.btn-add:hover { background:#be123c; color:#fff; box-shadow:0 4px 14px rgba(225,29,72,.3); }
.card-box {
    background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow);
    overflow:hidden; border:1px solid rgba(15,23,42,.04);
}
table { width:100%; border-collapse:collapse; margin:0; }
thead th {
    font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
    color:var(--muted); background:#f8fafc; padding:.75rem 1.1rem;
    border-bottom:1px solid #e2e8f0; text-align:left; white-space:nowrap;
}
tbody td { padding:.9rem 1.1rem; font-size:.875rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
tbody tr:last-child td { border-bottom:none; }
tbody tr:hover { background:#f8fafc; }
.badge-status {
    font-size:.72rem; font-weight:600; padding:.25rem .6rem; border-radius:999px;
}
.badge-active { background:#f0fdfa; color:var(--teal); }
.badge-inactive { background:#fef2f2; color:#dc2626; }
.badge-count {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:26px; height:26px; padding:0 .45rem; border-radius:8px;
    font-size:.78rem; font-weight:700; background:var(--coral); color:#fff;
}
.actions a {
    width:34px; height:34px; border-radius:8px; display:inline-grid; place-items:center;
    font-size:.95rem; text-decoration:none; transition:all .2s; margin-right:.25rem;
}
.actions .edit { background:#eef2ff; color:#4f46e5; }
.actions .edit:hover { background:#4f46e5; color:#fff; }
.actions .del { background:#fff1f2; color:var(--coral); }
.actions .del:hover { background:var(--coral); color:#fff; }
.empty { text-align:center; padding:3rem 1rem; color:var(--muted); }
.empty i { font-size:2rem; opacity:.4; display:block; margin-bottom:.5rem; }
.fee { font-weight:600; color:var(--teal); }
@media (max-width:767.98px) {
    thead th, tbody td { padding:.7rem .8rem; font-size:.8rem; }
}
</style>

<div class="page-head">
    <h1><i class="bi bi-journal-bookmark-fill"></i> All Courses</h1>
    <a href="course-add.php" class="btn-add"><i class="bi bi-plus-lg"></i> Add Course</a>
</div>

<div class="card-box">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Course Name</th>
                    <th>Duration</th>
                    <th>Fee</th>
                    <th>Batches</th>
                    <th>Students</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty">
                                <i class="bi bi-journal-x"></i>
                                No courses yet. <a href="course-add.php">Add your first course</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($courses as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                        <td><?= htmlspecialchars($c['course_name']) ?></td>
                        <td><?= htmlspecialchars($c['duration'] ?? '—') ?></td>
                        <td class="fee"><?= number_format($c['course_fee'], 2) ?> LKR</td>
                        <td><span class="badge-count"><?= (int)$c['batch_count'] ?></span></td>
                        <td><span class="badge-count"><?= (int)$c['student_count'] ?></span></td>
                        <td>
                            <span class="badge-status <?= $c['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                <?= ucfirst($c['status']) ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="course-edit.php?id=<?= $c['id'] ?>" class="edit" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a href="course-delete.php?id=<?= $c['id'] ?>" class="del" title="Delete"
                               onclick="return confirm('Delete this course? Batches will be unlinked.')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>