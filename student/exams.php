<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get student info
$student_id = $_SESSION['user_id'];
$query = "SELECT s.*, c.name as class_name FROM students s 
          JOIN classes c ON s.class_id = c.id 
          WHERE s.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get available exams
$query = "SELECT e.*, s.name as subject_name, t.full_name as teacher_name 
          FROM cbt_exams e 
          JOIN subjects s ON e.subject_id = s.id 
          JOIN users t ON e.created_by = t.id 
          WHERE e.class_id = :class_id 
          AND e.status = 'active' 
          AND NOW() BETWEEN e.start_time AND e.end_time
          AND e.id NOT IN (SELECT exam_id FROM cbt_results WHERE student_id = :student_id)";
$stmt = $db->prepare($query);
$stmt->bindParam(':class_id', $student['class_id']);
$stmt->bindParam(':student_id', $student['id']);
$stmt->execute();
$available_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get completed exams
$query = "SELECT e.*, s.name as subject_name, r.score, r.total_marks, r.percentage 
          FROM cbt_exams e 
          JOIN subjects s ON e.subject_id = s.id 
          JOIN cbt_results r ON e.id = r.exam_id 
          WHERE r.student_id = :student_id 
          ORDER BY r.submitted_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':student_id', $student['id']);
$stmt->execute();
$completed_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>CBT Exams - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
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
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-line me-2"></i>My Results</a>
            <!-- <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a> -->
            <a class="nav-link active" href="exams.php"><i class="fas fa-laptop me-2"></i>CBT Exams</a>
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
                    <h2>CBT Exams</h2>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="me-3">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                    </div>
                </div>

                <!-- Available Exams -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Available Exams</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($available_exams)): ?>
                            <p class="text-muted">No exams available at the moment.</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($available_exams as $exam): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo $exam['subject_name']; ?></h6>
                                                <p class="card-text small">
                                                    <strong>Title:</strong> <?php echo $exam['title']; ?><br>
                                                    <strong>Duration:</strong> <?php echo $exam['duration_minutes']; ?> minutes<br>
                                                    <strong>Questions:</strong> <?php echo $exam['total_questions']; ?><br>
                                                    <strong>Teacher:</strong> <?php echo $exam['teacher_name']; ?>
                                                </p>
                                            </div>
                                            <div class="card-footer">
                                                <a href="take_exam.php?exam_id=<?php echo $exam['id']; ?>" 
                                                   class="btn btn-primary btn-sm w-100">
                                                    Start Exam
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Completed Exams -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Completed Exams</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($completed_exams)): ?>
                            <p class="text-muted">No exams completed yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Exam Title</th>
                                            <th>Score</th>
                                            <th>Percentage</th>
                                            <th>Date Taken</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($completed_exams as $exam): ?>
                                            <tr>
                                                <td><?php echo $exam['subject_name']; ?></td>
                                                <td><?php echo $exam['title']; ?></td>
                                                <td><?php echo $exam['score']; ?>/<?php echo $exam['total_marks']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $exam['percentage'] >= 50 ? 'success' : 'danger'; ?>">
                                                        <?php echo $exam['percentage']; ?>%
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($exam['submitted_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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