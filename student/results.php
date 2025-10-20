<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';

// Get student details
$student_query = "SELECT s.id as student_id, s.student_id as admission_number, u.full_name, u.gender, 
                         c.name as class_name, c.id as class_id, ses.name as session_name, ses.id as session_id
                  FROM students s 
                  JOIN users u ON s.user_id = u.id 
                  JOIN classes c ON s.class_id = c.id 
                  JOIN academic_sessions ses ON s.session_id = ses.id 
                  WHERE s.user_id = :user_id AND s.status = 'active'";
$student_stmt = $db->prepare($student_query);
$student_stmt->bindParam(':user_id', $user_id);
$student_stmt->execute();
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $message = "Student record not found.";
}

// Get filter parameters
$session_id = $_GET['session_id'] ?? $student['session_id'];
$term = $_GET['term'] ?? 'first';

// Get available sessions
$sessions_query = "SELECT * FROM academic_sessions ORDER BY created_at DESC";
$sessions = $db->query($sessions_query)->fetchAll(PDO::FETCH_ASSOC);

// Get student results
$student_results = [];
$attendance_summary = [];
$overall_summary = [];

if ($student) {
    $results_query = "SELECT ar.*, sub.name as subject_name, sub.code as subject_code 
                     FROM academic_results ar 
                     JOIN subjects sub ON ar.subject_id = sub.id 
                     WHERE ar.student_id = :student_id AND ar.session_id = :session_id AND ar.term = :term 
                     ORDER BY sub.name";
    $stmt = $db->prepare($results_query);
    $stmt->bindParam(':student_id', $student['student_id']);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->bindParam(':term', $term);
    $stmt->execute();
    $student_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate overall summary
    if ($student_results) {
        $total_subjects = count($student_results);
        $total_score = 0;
        $total_max_score = 0;
        
        foreach ($student_results as $result) {
            $total_score += $result['total_score'];
            $total_max_score += 100; // Assuming max score per subject is 100
        }
        
        $overall_percentage = $total_max_score > 0 ? ($total_score / $total_max_score) * 100 : 0;
        
        $overall_summary = [
            'total_subjects' => $total_subjects,
            'total_score' => $total_score,
            'total_max_score' => $total_max_score,
            'percentage' => round($overall_percentage, 2),
            'average' => $total_subjects > 0 ? round($total_score / $total_subjects, 2) : 0
        ];
    }
    
    // Get attendance summary
    $attendance_query = "SELECT 
                        COUNT(*) as total_days,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
                        FROM attendance 
                        WHERE student_id = :student_id AND session_id = :session_id AND term = :term";
    $stmt = $db->prepare($attendance_query);
    $stmt->bindParam(':student_id', $student['student_id']);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->bindParam(':term', $term);
    $stmt->execute();
    $attendance_summary = $stmt->fetch(PDO::FETCH_ASSOC);
}

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
    <title>My Results - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .result-card { border-left: 4px solid #007bff; }
        .attendance-card { border-left: 4px solid #28a745; }
        .summary-card { border-left: 4px solid #ffc107; }
        @media print {
            .sidebar, .btn, .filter-form, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .card { border: 1px solid #000 !important; }
        }
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
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link active" href="results.php"><i class="fas fa-chart-line me-2"></i>My Results</a>
            <!-- <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a> -->
            <a class="nav-link" href="exams.php"><i class="fas fa-laptop me-2"></i>CBT Exams</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Class Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>School Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a>
            <!-- <a class="nav-link" href="library.php"><i class="fas fa-book me-2"></i>Library</a> -->
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Academic Results</h2>
            <div class="no-print">
                <?php if ($student_results): ?>
                    <button class="btn btn-success" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Results
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show no-print" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="card mb-4 no-print">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-filter me-2"></i>Filter Results</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-form">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Academic Session</label>
                            <select class="form-select" name="session_id" onchange="this.form.submit()">
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?php echo $session['id']; ?>" <?php echo $session_id == $session['id'] ? 'selected' : ''; ?>>
                                        <?php echo $session['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Term</label>
                            <select class="form-select" name="term" onchange="this.form.submit()">
                                <option value="first" <?php echo $term == 'first' ? 'selected' : ''; ?>>First Term</option>
                                <option value="second" <?php echo $term == 'second' ? 'selected' : ''; ?>>Second Term</option>
                                <option value="third" <?php echo $term == 'third' ? 'selected' : ''; ?>>Third Term</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($student): ?>
            <div class="row">
                <!-- Student Information -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Student Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px; font-size: 2rem;">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                            <table class="table table-sm">
                                <tr><td><strong>Name:</strong></td><td><?php echo $student['full_name']; ?></td></tr>
                                <tr><td><strong>Student ID:</strong></td><td><?php echo $student['admission_number']; ?></td></tr>
                                <tr><td><strong>Class:</strong></td><td><?php echo $student['class_name']; ?></td></tr>
                                <tr><td><strong>Session:</strong></td><td><?php echo $student['session_name']; ?></td></tr>
                                <tr><td><strong>Term:</strong></td><td><?php echo ucfirst($term); ?> Term</td></tr>
                                <tr><td><strong>Gender:</strong></td><td><?php echo ucfirst($student['gender']); ?></td></tr>
                            </table>
                        </div>
                    </div>

                    <!-- Attendance Summary -->
                    <?php if ($attendance_summary && $attendance_summary['total_days'] > 0): ?>
                    <div class="card mb-4 attendance-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Attendance Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <h4 class="text-success"><?php echo $attendance_summary['present_days']; ?></h4>
                                    <small>Present</small>
                                </div>
                                <div class="col-4">
                                    <h4 class="text-danger"><?php echo $attendance_summary['absent_days']; ?></h4>
                                    <small>Absent</small>
                                </div>
                                <div class="col-4">
                                    <h4 class="text-warning"><?php echo $attendance_summary['late_days']; ?></h4>
                                    <small>Late</small>
                                </div>
                            </div>
                            <div class="progress mt-3" style="height: 10px;">
                                <div class="progress-bar bg-success" style="width: <?php echo ($attendance_summary['present_days'] / $attendance_summary['total_days']) * 100; ?>%"></div>
                                <div class="progress-bar bg-warning" style="width: <?php echo ($attendance_summary['late_days'] / $attendance_summary['total_days']) * 100; ?>%"></div>
                            </div>
                            <small class="text-muted">
                                Attendance Rate: <?php echo round(($attendance_summary['present_days'] / $attendance_summary['total_days']) * 100, 1); ?>%
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Academic Results -->
                <div class="col-md-8">
                    <div class="card mb-4 result-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Academic Results - <?php echo ucfirst($term); ?> Term</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($student_results): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Subject</th>
                                                <th>CA Score</th>
                                                <th>Exam Score</th>
                                                <th>Total Score</th>
                                                <th>Grade</th>
                                                <th>Remark</th>
                                                <th>Position</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($student_results as $result): ?>
                                                <tr>
                                                    <td><?php echo $result['subject_name']; ?> (<?php echo $result['subject_code']; ?>)</td>
                                                    <td><?php echo $result['ca_score']; ?></td>
                                                    <td><?php echo $result['exam_score']; ?></td>
                                                    <td><strong><?php echo $result['total_score']; ?></strong></td>
                                                    <td><span class="badge bg-primary"><?php echo $result['grade']; ?></span></td>
                                                    <td><small><?php echo $result['remark']; ?></small></td>
                                                    <td><?php echo $result['position_in_subject']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Overall Summary -->
                                <?php if ($overall_summary): ?>
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="card summary-card">
                                            <div class="card-body">
                                                <h6 class="card-title">Overall Performance</h6>
                                                <div class="row text-center">
                                                    <div class="col-4">
                                                        <h5 class="text-primary"><?php echo $overall_summary['total_subjects']; ?></h5>
                                                        <small>Subjects</small>
                                                    </div>
                                                    <div class="col-4">
                                                        <h5 class="text-success"><?php echo $overall_summary['average']; ?></h5>
                                                        <small>Average</small>
                                                    </div>
                                                    <div class="col-4">
                                                        <h5 class="text-info"><?php echo $overall_summary['percentage']; ?>%</h5>
                                                        <small>Percentage</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title">Class Position</h6>
                                                <div class="text-center">
                                                    <h3 class="text-warning">
                                                        <?php 
                                                        $class_position = $student_results[0]['position_in_class'] ?? 'N/A';
                                                        echo $class_position;
                                                        if (is_numeric($class_position)) {
                                                            $suffix = ['th','st','nd','rd','th','th','th','th','th','th'];
                                                            $suffix_index = ($class_position % 100 >= 11 && $class_position % 100 <= 13) ? 0 : $class_position % 10;
                                                            echo $suffix[$suffix_index];
                                                        }
                                                        ?>
                                                    </h3>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Teacher and Principal Remarks -->
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h6 class="card-title mb-0">Class Teacher's Remark</h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text"><?php echo $student_results[0]['teacher_comment'] ?? 'No remark available.'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h6 class="card-title mb-0">Principal's Remark</h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text"><?php echo $student_results[0]['principal_comment'] ?? 'No remark available.'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="alert alert-warning text-center">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No results found for the selected term and session.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Student record not found. Please contact administration.
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
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>