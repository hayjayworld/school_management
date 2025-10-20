<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$message = '';

// Get filter parameters
$student_id = $_GET['student_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$session_id = $_GET['session_id'] ?? '';
$term = $_GET['term'] ?? 'first';

// Get classes, sessions, and students for filters
$classes = $db->query("SELECT * FROM classes WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$sessions = $db->query("SELECT * FROM academic_sessions ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Build students query based on filters
$students_query = "SELECT s.id, s.student_id, u.full_name, c.name as class_name 
                   FROM students s 
                   JOIN users u ON s.user_id = u.id 
                   JOIN classes c ON s.class_id = c.id 
                   WHERE s.status = 'active'";
$params = [];

if ($class_id) {
    $students_query .= " AND s.class_id = :class_id";
    $params[':class_id'] = $class_id;
}
if ($session_id) {
    $students_query .= " AND s.session_id = :session_id";
    $params[':session_id'] = $session_id;
}

$students_query .= " ORDER BY u.full_name";
$students_stmt = $db->prepare($students_query);
foreach ($params as $key => $value) {
    $students_stmt->bindValue($key, $value);
}
$students_stmt->execute();
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle principal remark update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_remark') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        $student_id = sanitize($_POST['student_id']);
        $session_id = sanitize($_POST['session_id']);
        $term = sanitize($_POST['term']);
        $principal_comment = sanitize($_POST['principal_comment']);
        
        // Update all academic results for this student in the session and term
        $query = "UPDATE academic_results SET principal_comment = :comment 
                 WHERE student_id = :student_id AND session_id = :session_id AND term = :term";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':comment', $principal_comment);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':session_id', $session_id);
        $stmt->bindParam(':term', $term);
        
        if ($stmt->execute()) {
            $message = "Principal remark updated successfully!";
        } else {
            $message = "Error updating principal remark.";
        }
    }
}

// Get student results if student is selected
$student_results = [];
$student_details = [];
$overall_summary = [];

if ($student_id && $session_id) {
    // Get student details
    $student_details_query = "SELECT s.*, u.full_name, u.gender, c.name as class_name, ses.name as session_name
                             FROM students s 
                             JOIN users u ON s.user_id = u.id 
                             JOIN classes c ON s.class_id = c.id 
                             JOIN academic_sessions ses ON s.session_id = ses.id 
                             WHERE s.id = :student_id";
    $stmt = $db->prepare($student_details_query);
    $stmt->bindParam(':student_id', $student_id);
    $stmt->execute();
    $student_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get student results
    $results_query = "SELECT ar.*, sub.name as subject_name, sub.code as subject_code 
                     FROM academic_results ar 
                     JOIN subjects sub ON ar.subject_id = sub.id 
                     WHERE ar.student_id = :student_id AND ar.session_id = :session_id AND ar.term = :term 
                     ORDER BY sub.name";
    $stmt = $db->prepare($results_query);
    $stmt->bindParam(':student_id', $student_id);
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
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->bindParam(':term', $term);
    $stmt->execute();
    $attendance_summary = $stmt->fetch(PDO::FETCH_ASSOC);
}

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
    <title>Student Reports - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
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
        <nav class="nav flex-column scrollable-menu">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
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
            <a class="nav-link active" href="student_reports.php"><i class="fas fa-chart-bar me-2"></i>Student Reports</a>
            <a class="nav-link" href="student_promotion.php"><i class="fas fa-user-graduate me-2"></i>Student Promotion</a>
            <!-- <a class="nav-link" href="reports.php"><i class="fas fa-chart-pie me-2"></i>Reports</a> -->
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cogs me-2"></i>System Settings</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Student Reports & Results</h2>
            <div class="no-print">
                <?php if ($student_id && $session_id): ?>
                    <button class="btn btn-success" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report
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
                <h5 class="card-title mb-0"><i class="fas fa-filter me-2"></i>Filter Students</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-form">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Class</label>
                            <select class="form-select" name="class_id" onchange="this.form.submit()">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo $class['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Academic Session</label>
                            <select class="form-select" name="session_id" onchange="this.form.submit()">
                                <option value="">All Sessions</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?php echo $session['id']; ?>" <?php echo $session_id == $session['id'] ? 'selected' : ''; ?>>
                                        <?php echo $session['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Student</label>
                            <select class="form-select" name="student_id" onchange="this.form.submit()">
                                <option value="">Select Student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>>
                                        <?php echo $student['full_name'] . ' (' . $student['student_id'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
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

        <?php if ($student_id && $session_id && $student_details): ?>
            <!-- Student Report -->
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
                                <tr><td><strong>Name:</strong></td><td><?php echo $student_details['full_name']; ?></td></tr>
                                <tr><td><strong>Student ID:</strong></td><td><?php echo $student_details['student_id']; ?></td></tr>
                                <tr><td><strong>Class:</strong></td><td><?php echo $student_details['class_name']; ?></td></tr>
                                <tr><td><strong>Session:</strong></td><td><?php echo $student_details['session_name']; ?></td></tr>
                                <tr><td><strong>Term:</strong></td><td><?php echo ucfirst($term); ?> Term</td></tr>
                                <tr><td><strong>Gender:</strong></td><td><?php echo ucfirst($student_details['gender']); ?></td></tr>
                            </table>
                        </div>
                    </div>

                    <!-- Attendance Summary -->
                    <?php if ($attendance_summary): ?>
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
                            <?php if ($attendance_summary['total_days'] > 0): ?>
                                <div class="progress mt-3" style="height: 10px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo ($attendance_summary['present_days'] / $attendance_summary['total_days']) * 100; ?>%"></div>
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($attendance_summary['late_days'] / $attendance_summary['total_days']) * 100; ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    Attendance Rate: <?php echo round(($attendance_summary['present_days'] / $attendance_summary['total_days']) * 100, 1); ?>%
                                </small>
                            <?php endif; ?>
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
                                                    <small>Out of <?php echo count($students); ?> students</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Principal Remark Form -->
                                <div class="mt-4 no-print">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="update_remark">
                                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                        <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                                        <input type="hidden" name="term" value="<?php echo $term; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label"><strong>Principal's Remark</strong></label>
                                            <textarea class="form-control" name="principal_comment" rows="3" 
                                                      placeholder="Enter principal's remark for this student..."><?php echo $student_results[0]['principal_comment'] ?? ''; ?></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Save Remark</button>
                                    </form>
                                </div>

                            <?php else: ?>
                                <div class="alert alert-warning text-center">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No results found for this student in the selected term and session.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Print Option -->
            <div class="card no-print">
                <div class="card-body text-center">
                    <h5>Print Multiple Reports</h5>
                    <p class="text-muted">Print results for all students in this class and session</p>
                    <a href="print_results.php?class_id=<?php echo $class_id; ?>&session_id=<?php echo $session_id; ?>&term=<?php echo $term; ?>" 
                       class="btn btn-success" target="_blank">
                        <i class="fas fa-print me-2"></i>Print All Results for <?php echo $student_details['class_name']; ?>
                    </a>
                </div>
            </div>

        <?php elseif ($student_id && !$session_id): ?>
            <div class="alert alert-warning text-center">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Please select an academic session to view student reports.
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