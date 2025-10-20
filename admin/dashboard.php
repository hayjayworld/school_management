<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get comprehensive statistics
$stats = [];
$queries = [
    'students' => "SELECT COUNT(*) FROM students WHERE status = 'active'",
    'teachers' => "SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'",
    'admins' => "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'",
    'classes' => "SELECT COUNT(*) FROM classes WHERE status = 'active'",
    'subjects' => "SELECT COUNT(*) FROM subjects WHERE status = 'active'",
    'exams' => "SELECT COUNT(*) FROM cbt_exams WHERE status = 'active'",
    'pending_payments' => "SELECT COUNT(*) FROM fee_payments WHERE status = 'pending'",
    'library_books' => "SELECT COUNT(*) FROM library_books WHERE status = 'available'"
];

foreach ($queries as $key => $query) {
    $stmt = $db->query($query);
    $stats[$key] = $stmt->fetchColumn();
}

// Get recent activities
$activities_query = "SELECT al.action, al.table_name, al.record_id, u.full_name, al.created_at 
                    FROM audit_logs al 
                    LEFT JOIN users u ON al.user_id = u.id 
                    ORDER BY al.created_at DESC 
                    LIMIT 10";
$activities = $db->query($activities_query)->fetchAll(PDO::FETCH_ASSOC);

// Get class-wise student distribution
$class_distribution_query = "SELECT c.name, COUNT(s.id) as student_count 
                           FROM classes c 
                           LEFT JOIN students s ON c.id = s.class_id AND s.status = 'active' 
                           WHERE c.status = 'active' 
                           GROUP BY c.id, c.name 
                           ORDER BY c.section, c.name";
$class_distribution = $db->query($class_distribution_query)->fetchAll(PDO::FETCH_ASSOC);

// Get recent fee payments
$recent_payments_query = "SELECT fp.amount_paid, fp.payment_date, s.student_id, u.full_name, c.name as class_name 
                         FROM fee_payments fp 
                         JOIN students s ON fp.student_id = s.id 
                         JOIN users u ON s.user_id = u.id 
                         JOIN classes c ON s.class_id = c.id 
                         ORDER BY fp.created_at DESC 
                         LIMIT 5";
$recent_payments = $db->query($recent_payments_query)->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming events
$upcoming_events_query = "SELECT title, event_date, event_type 
                         FROM events 
                         WHERE event_date >= CURDATE() AND is_published = TRUE 
                         ORDER BY event_date 
                         LIMIT 5";
$upcoming_events = $db->query($upcoming_events_query)->fetchAll(PDO::FETCH_ASSOC);

$settings_qry = "SELECT setting_key, setting_value FROM system_settings WHERE is_public = 1";
$settings_rst = $db->query($settings_qry);
$school_settings = [];
while ($row = $settings_rst->fetch(PDO::FETCH_ASSOC)) {
    $school_settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .stat-card { border: none; border-radius: 10px; transition: transform 0.3s ease; color: white; }
        .stat-card:hover { transform: translateY(-5px); }
        .bg-students { background: linear-gradient(135deg, #667eea, #764ba2); }
        .bg-teachers { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .bg-classes { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .bg-subjects { background: linear-gradient(135deg, #43e97b, #38f9d7); }
        .bg-exams { background: linear-gradient(135deg, #ff9a9e, #fecfef); }
        .bg-payments { background: linear-gradient(135deg, #a1c4fd, #c2e9fb); }
        .bg-library { background: linear-gradient(135deg, #d4fc79, #96e6a1); }
        .bg-admins { background: linear-gradient(135deg, #fd746c, #ff9068); }
        .activity-item { border-left: 3px solid #3498db; padding-left: 15px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <div>
                <h5 class="mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>
                    <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?>
                </h5>
                <small class="text-muted">Admin Panel</small>
            </div>
            <button class="menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>
    <div class="sidebar">
        <div class="p-4 text-center">
            <h4><i class="fas fa-graduation-cap"></i> <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></h4>
            <p class="text-muted small">Admin Panel</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link active" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="students.php"><i class="fas fa-users me-2"></i>Students</a>
            <a class="nav-link" href="staff.php"><i class="fas fa-chalkboard-teacher me-2"></i>Staff</a>
            <a class="nav-link" href="classes.php"><i class="fas fa-door-open me-2"></i>Classes</a>
            <a class="nav-link" href="subjects.php"><i class="fas fa-book me-2"></i>Subjects</a>
            <a class="nav-link" href="sessions.php"><i class="fas fa-calendar-alt me-2"></i>Sessions</a>
            <a class="nav-link" href="exams.php"><i class="fas fa-file-alt me-2"></i>Exams</a>
            <a class="nav-link" href="fees.php"><i class="fas fa-money-bill me-2"></i>Fees</a>
            <a class="nav-link" href="library.php"><i class="fas fa-book-open me-2"></i>Library</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="student_reports.php"><i class="fas fa-chart-bar me-2"></i>Student Reports</a>
            <a class="nav-link" href="student_promotion.php"><i class="fas fa-user-graduate me-2"></i>Student Promotion</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cogs me-2"></i>System Settings</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Admin Dashboard</h2>
            <div class="d-flex align-items-center">
                <span class="me-3">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="../update_profile.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card text-white bg-students">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['students']; ?></h4>
                                <p class="mb-0">Students</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card text-white bg-teachers">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['teachers']; ?></h4>
                                <p class="mb-0">Teachers</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chalkboard-teacher fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card text-white bg-admins">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['admins']; ?></h4>
                                <p class="mb-0">Administrators</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-shield fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card text-white bg-classes">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['classes']; ?></h4>
                                <p class="mb-0">Classes</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-door-open fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card text-white bg-subjects">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['subjects']; ?></h4>
                                <p class="mb-0">Subjects</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card text-white bg-exams">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['exams']; ?></h4>
                                <p class="mb-0">Active Exams</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-file-alt fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card text-white bg-payments">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['pending_payments']; ?></h4>
                                <p class="mb-0">Pending Payments</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-money-bill fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card text-white bg-library">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['library_books']; ?></h4>
                                <p class="mb-0">Library Books</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book-open fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Quick Actions & Class Distribution -->
            <div class="col-md-8">
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="students.php?action=add" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i>Add Student
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="staff.php?action=add" class="btn btn-outline-success w-100">
                                    <i class="fas fa-plus me-2"></i>Add Staff
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="exams.php?action=create" class="btn btn-outline-info w-100">
                                    <i class="fas fa-file-alt me-2"></i>Create Exam
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="events.php?action=create" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-calendar-plus me-2"></i>Add Event
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Class Distribution -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Class-wise Student Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Students</th>
                                        <th>Progress</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_students = $stats['students'];
                                    foreach ($class_distribution as $class): 
                                        $percentage = $total_students > 0 ? round(($class['student_count'] / $total_students) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo $class['name']; ?></td>
                                            <td><?php echo $class['student_count']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $percentage; ?>%" 
                                                         aria-valuenow="<?php echo $percentage; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </td>
                                            <td><?php echo $percentage; ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activities & Sidebar -->
            <div class="col-md-4">
                <!-- Recent Activities -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($activities as $activity): ?>
                                <div class="list-group-item px-0 activity-item">
                                    <small class="text-muted">
                                        <i class="fas fa-circle me-1" style="font-size: 6px;"></i>
                                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                    </small>
                                    <p class="mb-1 small"><?php echo $activity['action']; ?></p>
                                    <?php if ($activity['full_name']): ?>
                                        <small class="text-muted">By: <?php echo $activity['full_name']; ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Payments -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Fee Payments</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_payments as $payment): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo $payment['full_name']; ?></h6>
                                        <small class="text-success">₦<?php echo number_format($payment['amount_paid'], 2); ?></small>
                                    </div>
                                    <p class="mb-1 small"><?php echo $payment['class_name']; ?> | <?php echo $payment['student_id']; ?></p>
                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Upcoming Events -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Upcoming Events</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo $event['title']; ?></h6>
                                        <span class="badge bg-<?php 
                                            echo $event['event_type'] == 'academic' ? 'primary' : 
                                                ($event['event_type'] == 'sports' ? 'success' : 
                                                ($event['event_type'] == 'cultural' ? 'warning' : 'info')); 
                                        ?>">
                                            <?php echo ucfirst($event['event_type']); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
         // Mobile Menu Toggle Functionality
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileOverlay = document.getElementById('mobileOverlay');
        const body = document.body;

        function toggleMobileMenu() {
            body.classList.toggle('sidebar-mobile-open');
            mobileOverlay.style.display = body.classList.contains('sidebar-mobile-open') ? 'block' : 'none';
        }

        mobileMenuToggle.addEventListener('click', toggleMobileMenu);
        mobileOverlay.addEventListener('click', toggleMobileMenu);

        // Close menu when clicking on a link (on mobile)
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleMobileMenu();
                }
            });
        });

        // Close menu when window is resized to desktop size
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                body.classList.remove('sidebar-mobile-open');
                mobileOverlay.style.display = 'none';
            }
        });
    </script>
</body>
</html>