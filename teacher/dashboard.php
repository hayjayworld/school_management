<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$teacher_id = $_SESSION['user_id'];

// Get teacher statistics
$stats = [];
$queries = [
    'subjects' => "SELECT COUNT(*) FROM subjects WHERE teacher_id = $teacher_id",
    'classes' => "SELECT COUNT(DISTINCT class_id) FROM subjects WHERE teacher_id = $teacher_id",
    'students' => "SELECT COUNT(DISTINCT s.id) FROM students s 
                   JOIN subjects sub ON s.class_id = sub.class_id 
                   WHERE sub.teacher_id = $teacher_id",
    'exams' => "SELECT COUNT(*) FROM cbt_exams WHERE created_by = $teacher_id"
];

foreach ($queries as $key => $query) {
    $stmt = $db->query($query);
    $stats[$key] = $stmt->fetchColumn();
}

// Get assigned subjects
$subjects_query = "SELECT s.*, c.name as class_name 
                  FROM subjects s 
                  JOIN classes c ON s.class_id = c.id 
                  WHERE s.teacher_id = :teacher_id 
                  ORDER BY c.name, s.name";
$stmt = $db->prepare($subjects_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$events_query = "SELECT title, event_date, event_type 
                FROM events 
                WHERE (target_audience = 'all' OR target_audience = 'teachers')
                AND is_published = 1 
                AND event_date >= CURDATE()
                ORDER BY event_date 
                LIMIT 5";
$upcoming_events = $db->query($events_query)->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Teacher Dashboard - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .stat-card { border: none; border-radius: 10px; transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); }
        .bg-subjects { background: linear-gradient(135deg, #667eea, #764ba2); }
        .bg-classes { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .bg-students { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .bg-exams { background: linear-gradient(135deg, #43e97b, #38f9d7); }
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
                <small class="text-muted">Teacher Panel</small>
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
            <p class="text-muted small">Teacher Panel</p>
        </div>
        <nav class="nav flex-column scrollable-menu">
            <a class="nav-link active" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="subjects.php"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-line me-2"></i>Results</a>
            <a class="nav-link" href="exams.php"><i class="fas fa-file-alt me-2"></i>CBT Exams</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Teacher Dashboard</h2>
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
                <div class="card stat-card text-white bg-exams">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $stats['exams']; ?></h4>
                                <p class="mb-0">Exams Created</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-file-alt fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Subjects -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">My Assigned Subjects</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($subjects as $subject): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo $subject['name']; ?></h6>
                                            <p class="card-text">
                                                <strong>Class:</strong> <?php echo $subject['class_name']; ?><br>
                                                <strong>Code:</strong> <?php echo $subject['code']; ?>
                                            </p>
                                            <div class="btn-group w-100">
                                                <a href="results.php?subject_id=<?php echo $subject['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">Enter Results</a>
                                                <a href="exams.php?subject_id=<?php echo $subject['id']; ?>" 
                                                   class="btn btn-sm btn-outline-info">Create Exam</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="results.php" class="btn btn-outline-primary">
                                <i class="fas fa-chart-bar me-2"></i>Enter Results
                            </a>
                            <a href="exams.php" class="btn btn-outline-success">
                                <i class="fas fa-file-alt me-2"></i>Create Exam
                            </a>
                            <a href="students.php" class="btn btn-outline-info">
                                <i class="fas fa-users me-2"></i>View Students
                            </a>
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