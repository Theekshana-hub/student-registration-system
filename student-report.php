<?php
$pageTitle = 'Student Report';
$activePage = 'student-report';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'dropped' THEN 1 ELSE 0 END) as dropped,
        SUM(CASE WHEN status = 'transferred' THEN 1 ELSE 0 END) as transferred
    FROM students
")->fetch();
?>

<div class="page-header">
    <h2><i class="bi bi-file-earmark-person"></i> Student Report</h2>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="card text-center shadow-sm"><div class="card-body"><h6>Total</h6><h3><?= $stats['total'] ?></h3></div></div></div>
    <div class="col-md-2"><div class="card text-center shadow-sm"><div class="card-body"><h6>Active</h6><h3 class="text-success"><?= $stats['active'] ?></h3></div></div></div>
    <div class="col-md-2"><div class="card text-center shadow-sm"><div class="card-body"><h6>Completed</h6><h3 class="text-primary"><?= $stats['completed'] ?></h3></div></div></div>
    <div class="col-md-2"><div class="card text-center shadow-sm"><div class="card-body"><h6>Dropped</h6><h3 class="text-danger"><?= $stats['dropped'] ?></h3></div></div></div>
    <div class="col-md-2"><div class="card text-center shadow-sm"><div class="card-body"><h6>Transferred</h6><h3 class="text-warning"><?= $stats['transferred'] ?></h3></div></div></div>
</div>

<?php require_once 'includes/footer.php'; ?>
