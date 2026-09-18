<!-- Sidebar -->
<div class="sidebar" id="sidebar-wrapper">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <div class="brand-text">
            <h4>DBM Admin</h4>
            <span>Student Management</span>
        </div>
    </div>

    <!-- Navigation -->
    <div class="sidebar-nav">
        <!-- Dashboard -->
        <a href="index.php" class="nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        <!-- STUDENTS -->
        <div class="nav-section">Students</div>
        <a href="students.php" class="nav-item <?= ($activePage ?? '') === 'students' ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            <span>All Students</span>
        </a>
        <a href="add-student.php" class="nav-item <?= ($activePage ?? '') === 'add-student' ? 'active' : '' ?>">
            <i class="bi bi-person-plus"></i>
            <span>Add Student</span>
        </a>

        <!-- COURSES -->
        <div class="nav-section">Courses</div>
        <a href="courses.php" class="nav-item <?= ($activePage ?? '') === 'courses' ? 'active' : '' ?>">
            <i class="bi bi-journal-bookmark-fill"></i>
            <span>All Courses</span>
        </a>
        <a href="course-add.php" class="nav-item <?= ($activePage ?? '') === 'course-add' ? 'active' : '' ?>">
            <i class="bi bi-plus-circle"></i>
            <span>Add Course</span>
        </a>

        <!-- BATCHES -->
        <div class="nav-section">Batches</div>
        <a href="batches.php" class="nav-item <?= ($activePage ?? '') === 'batches' ? 'active' : '' ?>">
            <i class="bi bi-collection"></i>
            <span>All Batches</span>
        </a>
        <a href="add-batch.php" class="nav-item <?= ($activePage ?? '') === 'add-batch' ? 'active' : '' ?>">
            <i class="bi bi-plus-circle"></i>
            <span>Add Batch</span>
        </a>

        <!-- PAYMENTS -->
        <div class="nav-section">Payments</div>
        <a href="payments.php" class="nav-item <?= ($activePage ?? '') === 'payments' ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i>
            <span>All Payments</span>
        </a>
        
        <a href="paid-students.php" class="nav-item <?= ($activePage ?? '') === 'paid' ? 'active' : '' ?>">
            <i class="bi bi-check-circle"></i>
            <span>Fully Paid</span>
        </a>
        <a href="payment-history.php" class="nav-item <?= ($activePage ?? '') === 'history' ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i>
            <span>Payment History</span>
        </a>

        <!-- ACADEMICS -->
        <div class="nav-section">Academics</div>
        <a href="assignments.php" class="nav-item <?= ($activePage ?? '') === 'assignments' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i>
            <span>Assignments</span>
        </a>
        <a href="assignments_view.php" class="nav-item <?= ($activePage ?? '') === 'assignments-view' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i>
            <span>Assignments view</span>
        </a>
        <a href="exams.php" class="nav-item <?= ($activePage ?? '') === 'exams' ? 'active' : '' ?>">
            <i class="bi bi-pencil-square"></i>
            <span>Exams</span>
        </a>
        <a href="exams_view.php" class="nav-item <?= ($activePage ?? '') === 'exams-view' ? 'active' : '' ?>">
            <i class="bi bi-pencil-square"></i>
            <span>Exams view</span>
        </a>
        <a href="student_progress.php" class="nav-item <?= ($activePage ?? '') === 'progress-view' ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Student Progress</span>
        </a>

        <!-- CERTIFICATES -->
        <div class="nav-section">Certificates</div>
        <a href="certificates.php" class="nav-item <?= ($activePage ?? '') === 'certificates' ? 'active' : '' ?>">
            <i class="bi bi-award"></i>
            <span>Certificates</span>
        </a>

        <!-- REPORTS -->
        <div class="nav-section">Reports</div>
        <a href="student-report.php" class="nav-item <?= ($activePage ?? '') === 'student-report' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-person"></i>
            <span>Student Report</span>
        </a>
        <a href="income-report.php" class="nav-item <?= ($activePage ?? '') === 'income-report' ? 'active' : '' ?>">
            <i class="bi bi-graph-up"></i>
            <span>Income Report</span>
        </a>
        <a href="batch-report.php" class="nav-item <?= ($activePage ?? '') === 'batch-report' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart"></i>
            <span>Batch Report</span>
        </a>
    </div>
</div>

<!-- Page Content Wrapper -->
<div id="page-content-wrapper">
    <!-- Top Navbar -->
    <nav class="top-navbar">
        <div class="navbar-left">
            <button class="menu-toggle" id="menu-toggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="page-title d-none d-md-block">
                <?= $pageTitle ?? 'Dashboard' ?>
            </div>
        </div>

        <div class="navbar-right">
            <div class="current-time">
                <i class="bi bi-clock"></i>
                <span><?= date('Y-m-d H:i') ?></span>
            </div>

            <div class="dropdown">
                <a class="user-btn" href="#" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        <i class="bi bi-person"></i>
                    </div>
                    <span class="user-name">Admin</span>
                    <i class="bi bi-chevron-down ms-1" style="font-size: .7rem;"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end user-dropdown">
                    <li>
                        <a class="dropdown-item" href="#">
                            <i class="bi bi-person me-2"></i> Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <i class="bi bi-gear me-2"></i> Settings
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="#">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="main-content">