<?php
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// ========== DASHBOARD STATS ==========
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$activeBatches = $pdo->query("SELECT COUNT(*) FROM batches WHERE status = 'active'")->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'")->fetchColumn();
$pendingPayments = $pdo->query("
    SELECT COUNT(DISTINCT s.id)
    FROM students s
    JOIN batches b ON s.batch_id = b.id
    WHERE s.status = 'active'
    AND (b.course_fee - COALESCE((SELECT SUM(amount) FROM payments p WHERE p.student_id = s.id AND p.status = 'paid'), 0)) > 0
")->fetchColumn();
$fullyPaid = $pdo->query("
    SELECT COUNT(DISTINCT s.id)
    FROM students s
    JOIN batches b ON s.batch_id = b.id
    WHERE (b.course_fee - COALESCE((SELECT SUM(amount) FROM payments p WHERE p.student_id = s.id AND p.status = 'paid'), 0)) <= 0
")->fetchColumn();
$newThisMonth = $pdo->query("
    SELECT COUNT(*) FROM students
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
")->fetchColumn();
$certPending = $pdo->query("
    SELECT COUNT(*) FROM certificates
    WHERE design_status != 'completed' OR printing_status != 'printed'
")->fetchColumn();
$recentPayments = $pdo->query("
    SELECT p.*, s.full_name, s.register_no, b.batch_code
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN batches b ON s.batch_id = b.id
    ORDER BY p.payment_date DESC, p.id DESC
    LIMIT 10
")->fetchAll();
$batchStats = $pdo->query("
    SELECT b.batch_code, b.batch_name, COUNT(s.id) as student_count,
           COALESCE(SUM(p.amount), 0) as collected
    FROM batches b
    LEFT JOIN students s ON s.batch_id = b.id
    LEFT JOIN payments p ON p.student_id = s.id AND p.status = 'paid'
    GROUP BY b.id
    ORDER BY b.start_date DESC
    LIMIT 8
")->fetchAll();
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

<style>
/* =========================================================
   DBM DASHBOARD — Premium Full Redesign 2025
   ========================================================= */
:root {
    --bg: #f1f5f9;
    --surface: #ffffff;
    --ink: #0f172a;
    --ink-soft: #475569;
    --ink-muted: #94a3b8;
    --coral: #e11d48;
    --coral-soft: #fff1f2;
    --coral-mid: #fecdd3;
    --teal: #0d9488;
    --teal-soft: #f0fdfa;
    --teal-mid: #99f6e4;
    --amber: #d97706;
    --amber-soft: #fffbeb;
    --amber-mid: #fde68a;
    --indigo: #4f46e5;
    --indigo-soft: #eef2ff;
    --indigo-mid: #c7d2fe;
    --sky: #0284c7;
    --sky-soft: #f0f9ff;
    --sky-mid: #bae6fd;
    --violet: #7c3aed;
    --violet-soft: #f5f3ff;
    --violet-mid: #ddd6fe;
    --radius: 20px;
    --radius-sm: 14px;
    --shadow: 0 1px 3px rgba(15,23,42,.04), 0 6px 20px rgba(15,23,42,.06);
    --shadow-lg: 0 12px 40px rgba(15,23,42,.10);
    --shadow-hover: 0 16px 48px rgba(15,23,42,.12);
}

*, *::before, *::after { box-sizing: border-box; }

body {
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--ink);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* ---------- Page Header ---------- */
.dash-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1.25rem;
    margin-bottom: 2rem;
    animation: riseIn .5s cubic-bezier(.22,1,.36,1) both;
}

.dash-header h1 {
    font-size: 1.85rem;
    font-weight: 800;
    letter-spacing: -.035em;
    margin: 0;
    display: flex;
    align-items: center;
    gap: .75rem;
    color: var(--ink);
}

.dash-header h1 .icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #e11d48 0%, #fb7185 100%);
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 1.35rem;
    box-shadow: 0 8px 20px rgba(225,29,72,.35);
}

.dash-header p {
    margin: .4rem 0 0;
    color: var(--ink-soft);
    font-size: .95rem;
    font-weight: 500;
}

.live-badge {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: var(--teal-soft);
    color: var(--teal);
    font-size: .78rem;
    font-weight: 650;
    padding: .45rem 1rem;
    border-radius: 999px;
    border: 1px solid var(--teal-mid);
    box-shadow: 0 2px 8px rgba(13,148,136,.08);
}

.live-badge .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--teal);
    animation: blink 1.8s ease-in-out infinite;
}

/* ---------- Stat Grid ---------- */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.15rem;
    margin-bottom: 1.35rem;
}

.stat-card {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 1.4rem 1.5rem;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
    transition: all .35s cubic-bezier(.34,1.4,.64,1);
    animation: riseIn .55s cubic-bezier(.22,1,.36,1) both;
    border: 1px solid rgba(15,23,42,.03);
}

.stat-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-hover);
}

.stat-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, transparent 60%, rgba(255,255,255,.4));
    pointer-events: none;
}

.stat-card::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3.5px;
    border-radius: 3.5px 3.5px 0 0;
    opacity: 0;
    transition: opacity .3s ease;
}

.stat-card:hover::after { opacity: 1; }

.stat-card.theme-coral::after { background: linear-gradient(90deg, var(--coral), #fb7185); }
.stat-card.theme-teal::after  { background: linear-gradient(90deg, var(--teal), #2dd4bf); }
.stat-card.theme-sky::after   { background: linear-gradient(90deg, var(--sky), #38bdf8); }
.stat-card.theme-amber::after { background: linear-gradient(90deg, var(--amber), #fbbf24); }
.stat-card.theme-indigo::after{ background: linear-gradient(90deg, var(--indigo), #818cf8); }
.stat-card.theme-violet::after{ background: linear-gradient(90deg, var(--violet), #a78bfa); }

.stat-card .top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.stat-card .ico {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    font-size: 1.45rem;
    transition: transform .4s cubic-bezier(.34,1.4,.64,1);
}

.stat-card:hover .ico {
    transform: scale(1.12) rotate(-6deg);
}

.theme-coral .ico { background: var(--coral-soft); color: var(--coral); }
.theme-teal .ico  { background: var(--teal-soft);  color: var(--teal); }
.theme-sky .ico   { background: var(--sky-soft);   color: var(--sky); }
.theme-amber .ico { background: var(--amber-soft); color: var(--amber); }
.theme-indigo .ico{ background: var(--indigo-soft);color: var(--indigo); }
.theme-violet .ico{ background: var(--violet-soft);color: var(--violet); }

.stat-card .label {
    font-size: .72rem;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--ink-muted);
    margin-bottom: .25rem;
}

.stat-card .value {
    font-size: 1.85rem;
    font-weight: 800;
    letter-spacing: -.03em;
    line-height: 1.15;
    color: var(--ink);
}

/* Staggered entrance */
.stat-grid:nth-of-type(1) .stat-card:nth-child(1) { animation-delay: .05s; }
.stat-grid:nth-of-type(1) .stat-card:nth-child(2) { animation-delay: .10s; }
.stat-grid:nth-of-type(1) .stat-card:nth-child(3) { animation-delay: .15s; }
.stat-grid:nth-of-type(1) .stat-card:nth-child(4) { animation-delay: .20s; }
.stat-grid:nth-of-type(2) .stat-card:nth-child(1) { animation-delay: .25s; }
.stat-grid:nth-of-type(2) .stat-card:nth-child(2) { animation-delay: .30s; }
.stat-grid:nth-of-type(2) .stat-card:nth-child(3) { animation-delay: .35s; }
.stat-grid:nth-of-type(2) .stat-card:nth-child(4) { animation-delay: .40s; }

.theme-amber .ico,
.theme-coral .ico.pulse {
    animation: softGlow 2.6s ease-in-out infinite;
}

/* ---------- Content Panels ---------- */
.panel-row {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 1.25rem;
}

.panel {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    animation: riseIn .65s cubic-bezier(.22,1,.36,1) both;
    border: 1px solid rgba(15,23,42,.04);
    transition: box-shadow .35s ease, transform .35s ease;
}

.panel:hover {
    box-shadow: var(--shadow-lg);
}

.panel-row .panel:first-child { animation-delay: .45s; }
.panel-row .panel:last-child  { animation-delay: .52s; }

.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.15rem 1.4rem;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(to bottom, #ffffff, #fafbfc);
}

.panel-head h2 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: .55rem;
    color: var(--ink);
}

.panel-head h2 i {
    font-size: 1.1rem;
    color: var(--coral);
}

.btn-view {
    font-size: .78rem;
    font-weight: 650;
    color: var(--coral);
    background: var(--coral-soft);
    border: 1px solid var(--coral-mid);
    border-radius: 10px;
    padding: .38rem .9rem;
    text-decoration: none;
    transition: all .25s ease;
}

.btn-view:hover {
    background: var(--coral);
    color: #fff;
    border-color: var(--coral);
    box-shadow: 0 6px 16px rgba(225,29,72,.28);
    transform: translateY(-1px);
}

/* ---------- Tables ---------- */
.panel table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.panel thead th {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--ink-muted);
    background: #f8fafc;
    padding: .8rem 1.25rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}

.panel tbody td {
    padding: .95rem 1.25rem;
    font-size: .875rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    color: var(--ink);
}

.panel tbody tr:last-child td { border-bottom: none; }

.panel tbody tr {
    transition: background .2s ease;
}

.panel tbody tr:hover {
    background: #f8fafc;
}

.student-cell strong {
    font-weight: 650;
    display: block;
    font-size: .875rem;
    color: var(--ink);
}

.student-cell small {
    color: var(--ink-muted);
    font-size: .75rem;
    font-weight: 500;
}

.batch-cell strong {
    font-weight: 700;
    font-size: .875rem;
    display: block;
    color: var(--ink);
}

.batch-cell small {
    color: var(--ink-muted);
    font-size: .75rem;
}

.badge-inst {
    font-family: 'JetBrains Mono', monospace;
    font-size: .72rem;
    font-weight: 500;
    background: #f1f5f9;
    color: var(--ink-soft);
    padding: .25rem .55rem;
    border-radius: 7px;
    border: 1px solid #e2e8f0;
}

.badge-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 .5rem;
    border-radius: 9px;
    font-size: .8rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--coral), #fb7185);
    color: #fff;
    box-shadow: 0 3px 10px rgba(225,29,72,.25);
}

.amount {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 550;
    font-size: .88rem;
    color: var(--teal);
}

.empty-state {
    text-align: center;
    padding: 3rem 1.5rem;
    color: var(--ink-muted);
    font-size: .95rem;
    font-weight: 500;
}

.empty-state i {
    display: block;
    font-size: 2.2rem;
    margin-bottom: .65rem;
    opacity: .35;
}

/* ---------- Animations ---------- */
@keyframes riseIn {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: .25; }
}

@keyframes softGlow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(217,119,6,.35); }
    50% { box-shadow: 0 0 0 10px rgba(217,119,6,0); }
}

/* =========================================================
   RESPONSIVE
   ========================================================= */
@media (max-width: 1199.98px) {
    .stat-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .panel-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 767.98px) {
    .dash-header h1 {
        font-size: 1.45rem;
    }
    .dash-header h1 .icon-wrap {
        width: 40px;
        height: 40px;
        font-size: 1.15rem;
    }
    .stat-grid {
        grid-template-columns: 1fr 1fr;
        gap: .9rem;
    }
    .stat-card {
        padding: 1.15rem 1.2rem;
    }
    .stat-card .ico {
        width: 46px;
        height: 46px;
        font-size: 1.25rem;
        border-radius: 14px;
    }
    .stat-card .value {
        font-size: 1.5rem;
    }
    .stat-card .label {
        font-size: .68rem;
    }
    .panel-head {
        padding: 1rem 1.15rem;
    }
    .panel thead th,
    .panel tbody td {
        padding: .75rem 1rem;
        font-size: .82rem;
    }
}

@media (max-width: 479.98px) {
    .stat-grid {
        grid-template-columns: 1fr;
    }
    .dash-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<!-- ===================== HEADER ===================== -->
<div class="dash-header">
    <div>
        <h1>
            <span class="icon-wrap"><i class="bi bi-speedometer2"></i></span>
            Dashboard
        </h1>
        <p>DBM Course Student Management Overview</p>
    </div>
    <div class="live-badge">
        <span class="dot"></span>
        Live Overview
    </div>
</div>

<!-- ===================== STATS ROW 1 ===================== -->
<div class="stat-grid">
    <div class="stat-card theme-coral">
        <div class="top">
            <div class="ico"><i class="bi bi-people-fill"></i></div>
        </div>
        <div class="label">Total Students</div>
        <div class="value"><?= number_format($totalStudents) ?></div>
    </div>

    <div class="stat-card theme-teal">
        <div class="top">
            <div class="ico"><i class="bi bi-collection-fill"></i></div>
        </div>
        <div class="label">Active Batches</div>
        <div class="value"><?= number_format($activeBatches) ?></div>
    </div>

    <div class="stat-card theme-sky">
        <div class="top">
            <div class="ico"><i class="bi bi-cash-stack"></i></div>
        </div>
        <div class="label">Total Revenue</div>
        <div class="value"><?= number_format($totalRevenue / 1000, 1) ?>K</div>
    </div>

    <div class="stat-card theme-amber">
        <div class="top">
            <div class="ico"><i class="bi bi-exclamation-triangle-fill"></i></div>
        </div>
        <div class="label">Pending Payments</div>
        <div class="value"><?= number_format($pendingPayments) ?></div>
    </div>
</div>

<!-- ===================== STATS ROW 2 ===================== -->
<div class="stat-grid">
    <div class="stat-card theme-teal">
        <div class="top">
            <div class="ico"><i class="bi bi-check-circle-fill"></i></div>
        </div>
        <div class="label">Fully Paid Students</div>
        <div class="value"><?= number_format($fullyPaid) ?></div>
    </div>

    <div class="stat-card theme-coral">
        <div class="top">
            <div class="ico pulse"><i class="bi bi-person-plus-fill"></i></div>
        </div>
        <div class="label">New This Month</div>
        <div class="value"><?= number_format($newThisMonth) ?></div>
    </div>

    <div class="stat-card theme-violet">
        <div class="top">
            <div class="ico"><i class="bi bi-award-fill"></i></div>
        </div>
        <div class="label">Certificates Pending</div>
        <div class="value"><?= number_format($certPending) ?></div>
    </div>

    <div class="stat-card theme-indigo">
        <div class="top">
            <div class="ico"><i class="bi bi-calendar-event"></i></div>
        </div>
        <div class="label">Upcoming Exams</div>
        <div class="value">—</div>
    </div>
</div>

<!-- ===================== PANELS ===================== -->
<div class="panel-row">

    <!-- Recent Payments -->
    <div class="panel">
        <div class="panel-head">
            <h2><i class="bi bi-clock-history"></i> Recent Payments</h2>
            <a href="payments.php" class="btn-view">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Batch</th>
                        <th>Inst.</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentPayments)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    No payments yet
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentPayments as $p): ?>
                        <tr>
                            <td class="student-cell">
                                <strong><?= htmlspecialchars($p['full_name']) ?></strong>
                                <small><?= htmlspecialchars($p['register_no']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($p['batch_code'] ?? '—') ?></td>
                            <td><span class="badge-inst">#<?= $p['installment_no'] ?></span></td>
                            <td class="amount"><?= formatMoney($p['amount']) ?></td>
                            <td><?= formatDate($p['payment_date']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Batch Overview -->
    <div class="panel">
        <div class="panel-head">
            <h2><i class="bi bi-collection"></i> Batch Overview</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Students</th>
                        <th>Collected</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batchStats)): ?>
                        <tr>
                            <td colspan="3">
                                <div class="empty-state">
                                    <i class="bi bi-folder2-open"></i>
                                    No batches found
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($batchStats as $b): ?>
                        <tr>
                            <td class="batch-cell">
                                <strong><?= htmlspecialchars($b['batch_code']) ?></strong>
                                <small><?= htmlspecialchars($b['batch_name']) ?></small>
                            </td>
                            <td><span class="badge-count"><?= $b['student_count'] ?></span></td>
                            <td class="amount"><?= formatMoney($b['collected']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>