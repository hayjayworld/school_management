<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$student_id = $_SESSION['user_id'];

// Get student info with class details
$student_query = "SELECT s.*, u.full_name, u.gender, c.name as class_name, c.section 
                 FROM students s 
                 JOIN users u ON s.user_id = u.id 
                 JOIN classes c ON s.class_id = c.id 
                 WHERE s.user_id = :user_id";
$stmt = $db->prepare($student_query);
$stmt->bindParam(':user_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get upcoming exams
$exams_query = "SELECT e.*, s.name as subject_name 
               FROM cbt_exams e 
               JOIN subjects s ON e.subject_id = s.id 
               WHERE e.class_id = :class_id 
               AND e.status = 'active' 
               AND e.start_time > NOW() 
               ORDER BY e.start_time 
               LIMIT 5";
$stmt = $db->prepare($exams_query);
$stmt->bindParam(':class_id', $student['class_id']);
$stmt->execute();
$upcoming_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent results
$results_query = "SELECT r.*, s.name as subject_name, e.title as exam_title 
                 FROM cbt_results r 
                 JOIN cbt_exams e ON r.exam_id = e.id 
                 JOIN subjects s ON e.subject_id = s.id 
                 WHERE r.student_id = :student_id 
                 ORDER BY r.submitted_at DESC 
                 LIMIT 5";
$stmt = $db->prepare($results_query);
$stmt->bindParam(':student_id', $student['id']);
$stmt->execute();
$recent_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent announcements/events
$events_query = "SELECT title, event_date, event_type 
                FROM events 
                WHERE (target_audience = 'all' OR target_audience = 'students')
                AND is_published = 1 
                AND event_date >= CURDATE()
                ORDER BY event_date 
                LIMIT 5";
$events = $db->query($events_query)->fetchAll(PDO::FETCH_ASSOC);

$settings_qry = "SELECT setting_key, setting_value FROM system_settings";
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
    <title>Student Dashboard - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .welcome-card { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .stat-card { border: none; border-radius: 10px; transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); }
        .bg-exams { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .bg-results { background: linear-gradient(135deg, #43e97b, #38f9d7); }
        .bg-attendance { background: linear-gradient(135deg, #fa709a, #fee140); }
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
                <small class="text-muted">Student Portal</small>
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
            <p class="text-muted small">Student Portal</p>
        </div>
        <nav class="nav flex-column scrollable-menu">
            <a class="nav-link active" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-line me-2"></i>My Results</a>
            <!-- <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a> -->
            <a class="nav-link" href="exams.php"><i class="fas fa-laptop me-2"></i>CBT Exams</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Class Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>School Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a>
            <!-- <a class="nav-link" href="library.php"><i class="fas fa-book me-2"></i>Library</a> -->
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>


            <!-- Main content -->
            <main class="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2>Student Dashboard</h2>
                    <div class="btn-toolbar mb-2 mb-md-0">
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

                <!-- Welcome Card -->
                <div class="card welcome-card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h4>Hello, <?php echo $student['full_name']; ?>! 👋</h4>
                                <p class="mb-0">
                                    <strong>Class:</strong> <?php echo $student['class_name']; ?> | 
                                    <strong>Student ID:</strong> <?php echo $student['student_id']; ?> |
                                    <strong>Section:</strong> <?php echo ucfirst($student['section']); ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="btn-group">
                                    <a href="exams.php" class="btn btn-light">Take Exam</a>
                                    <a href="results.php" class="btn btn-outline-light">View Results</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stat-card text-white bg-exams">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo count($upcoming_exams); ?></h4>
                                        <p class="mb-0">Upcoming Exams</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-file-alt fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stat-card text-white bg-results">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo count($recent_results); ?></h4>
                                        <p class="mb-0">Recent Results</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-bar fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stat-card text-white bg-attendance">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4>85%</h4>
                                        <p class="mb-0">Attendance</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-check fa-2x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Upcoming Exams -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Upcoming Exams</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcoming_exams)): ?>
                                    <p class="text-muted">No upcoming exams scheduled.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($upcoming_exams as $exam): ?>
                                            <div class="list-group-item px-0">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo $exam['subject_name']; ?></h6>
                                                    <small><?php echo date('M j', strtotime($exam['start_time'])); ?></small>
                                                </div>
                                                <p class="mb-1 small"><?php echo $exam['title']; ?></p>
                                                <small class="text-muted">
                                                    Duration: <?php echo $exam['duration_minutes']; ?> mins | 
                                                    Questions: <?php echo $exam['total_questions']; ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <a href="exams.php" class="btn btn-outline-primary btn-sm">View All Exams</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Results -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Results</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_results)): ?>
                                    <p class="text-muted">No results available yet.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_results as $result): ?>
                                            <div class="list-group-item px-0">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo $result['subject_name']; ?></h6>
                                                    <span class="badge bg-<?php echo $result['percentage'] >= 50 ? 'success' : 'danger'; ?>">
                                                        <?php echo $result['percentage']; ?>%
                                                    </span>
                                                </div>
                                                <p class="mb-1 small"><?php echo $result['exam_title']; ?></p>
                                                <small class="text-muted">
                                                    Score: <?php echo $result['score']; ?>/<?php echo $result['total_marks']; ?> | 
                                                    <?php echo date('M j, g:i A', strtotime($result['submitted_at'])); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <a href="results.php" class="btn btn-outline-primary btn-sm">View All Results</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- School Events -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Upcoming Events</h5>
                                <a href="events.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if ($events): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($events as $event): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo $event['title']; ?></h6>
                                                    <small><?php echo date('M j', strtotime($event['event_date'])); ?></small>
                                                </div>
                                                <small class="text-muted">
                                                    <span class="badge 
                                                        <?php echo $event['event_type'] == 'academic' ? 'bg-primary' : ''; ?>
                                                        <?php echo $event['event_type'] == 'sports' ? 'bg-success' : ''; ?>
                                                        <?php echo $event['event_type'] == 'cultural' ? 'bg-danger' : ''; ?>
                                                        <?php echo $event['event_type'] == 'holiday' ? 'bg-warning' : ''; ?>">
                                                        <?php echo ucfirst($event['event_type']); ?>
                                                    </span>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">No upcoming events</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="exams.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-file-alt me-2"></i>Take Exam
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="results.php" class="btn btn-outline-success w-100">
                                            <i class="fas fa-chart-bar me-2"></i>View Results
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="timetable.php" class="btn btn-outline-info w-100">
                                            <i class="fas fa-calendar me-2"></i>Time Table
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="profile.php" class="btn btn-outline-warning w-100">
                                            <i class="fas fa-user-edit me-2"></i>Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

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