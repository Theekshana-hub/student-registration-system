<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbm_management');
define('DB_USER', 'root');
define('DB_PASS', '');          // Change this in production
define('DB_CHARSET', 'utf8mb4');

// App Settings
define('APP_NAME', 'DBM Student Management');
define('APP_URL', 'http://localhost/dbm-dashboard');
define('CURRENCY', 'LKR');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper Functions
function formatMoney($amount) {
    return number_format((float)$amount, 2) . ' ' . CURRENCY;
}

function formatDate($date) {
    if (empty($date) || $date == '0000-00-00') return '-';
    return date('Y-m-d', strtotime($date));
}

function statusBadge($status) {
    $colors = [
        'active'     => 'success',
        'completed'  => 'primary',
        'dropped'    => 'danger',
        'transferred'=> 'warning',
        'refunded'   => 'secondary',
        'pending'    => 'info',
        'paid'       => 'success',
        'overdue'    => 'danger',
        'partial'    => 'warning',
    ];
    $color = $colors[strtolower($status)] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}

function getStudentPaidTotal($pdo, $student_id) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = ? AND status = 'paid'");
    $stmt->execute([$student_id]);
    return $stmt->fetchColumn();
}

function getStudentPendingAmount($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT b.course_fee - COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = s.id AND status = 'paid'), 0)
        FROM students s
        LEFT JOIN batches b ON s.batch_id = b.id
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    return max(0, $stmt->fetchColumn());
}
