<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$teacher_id = $_SESSION['user_id'];

// Get teacher's assigned subjects
$subjects_query = "SELECT s.*, c.name as class_name, c.section as class_section,
                  (SELECT COUNT(*) FROM students WHERE class_id = c.id AND status = 'active') as student_count
                  FROM subjects s 
                  JOIN classes c ON s.class_id = c.id 
                  WHERE s.teacher_id = :teacher_id 
                  AND s.status = 'active'
                  ORDER BY c.section, c.name, s.name";
$stmt = $db->prepare($subjects_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming exams for these subjects
$exams_query = "SELECT ce.*, s.name as subject_name, c.name as class_name 
               FROM cbt_exams ce 
               JOIN subjects s ON ce.subject_id = s.id 
               JOIN classes c ON ce.class_id = c.id 
               WHERE s.teacher_id = :teacher_id 
               AND ce.start_time > NOW() 
               ORDER BY ce.start_time 
               LIMIT 5";
$stmt = $db->prepare($exams_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$upcoming_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>My Subjects - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
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
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link active" href="subjects.php"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-line me-2"></i>Results</a>
            <a class="nav-link" href="exams.php"><i class="fas fa-laptop me-2"></i>CBT Exams</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Subjects</h2>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo count($subjects); ?></h4>
                                <p class="mb-0">Total Subjects</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo count(array_unique(array_column($subjects, 'class_name'))); ?></h4>
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
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo array_sum(array_column($subjects, 'student_count')); ?></h4>
                                <p class="mb-0">Total Students</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-warning">
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
        </div>

        <!-- Subjects Grid -->
        <div class="row">
            <?php foreach ($subjects as $subject): ?>
                <div class="col-md-6 mb-4">
                    <div class="card subject-card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $subject['name']; ?></h5>
                            <p class="card-text">
                                <strong>Code:</strong> <?php echo $subject['code']; ?>
                            </p>
                            <p class="card-text">
                                <strong>Class:</strong> 
                                <span class="badge bg-<?php echo $subject['class_section'] == 'primary' ? 'info' : ($subject['class_section'] == 'junior' ? 'warning' : 'success'); ?>">
                                    <?php echo $subject['class_name']; ?> (<?php echo ucfirst($subject['class_section']); ?>)
                                </span>
                            </p>
                            <p class="card-text">
                                <strong>Students:</strong> <?php echo $subject['student_count']; ?> students
                            </p>
                            <?php if ($subject['description']): ?>
                                <p class="card-text">
                                    <strong>Description:</strong> 
                                    <?php echo $subject['description']; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="btn-group w-100 mt-3">
                                <a href="attendance.php?class_id=<?php echo $subject['class_id']; ?>&subject_id=<?php echo $subject['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar-check me-1"></i>Attendance
                                </a>
                                <a href="results.php?class_id=<?php echo $subject['class_id']; ?>&subject_id=<?php echo $subject['id']; ?>" 
                                   class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-chart-line me-1"></i>Results
                                </a>
                                <a href="exams.php?subject_id=<?php echo $subject['id']; ?>" 
                                   class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-laptop me-1"></i>CBT
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Upcoming Exams -->
        <?php if ($upcoming_exams): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Upcoming CBT Exams</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Exam Title</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Start Time</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_exams as $exam): ?>
                                    <tr>
                                        <td><?php echo $exam['title']; ?></td>
                                        <td><?php echo $exam['subject_name']; ?></td>
                                        <td><?php echo $exam['class_name']; ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($exam['start_time'])); ?></td>
                                        <td><?php echo $exam['duration_minutes']; ?> minutes</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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