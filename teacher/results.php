<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$teacher_id = $_SESSION['user_id'];

$message = '';
$class_id = $_GET['class_id'] ?? '';
$subject_id = $_GET['subject_id'] ?? '';
$action = $_GET['action'] ?? '';

// Get teacher's assigned classes and subjects
$classes_query = "SELECT DISTINCT c.id, c.name 
                 FROM classes c 
                 JOIN subjects s ON c.id = s.class_id 
                 WHERE s.teacher_id = :teacher_id 
                 ORDER BY c.name";
$stmt = $db->prepare($classes_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$assigned_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_session_query = "SELECT * FROM academic_sessions WHERE status = 'active' LIMIT 1";
$current_session = $db->query($current_session_query)->fetch(PDO::FETCH_ASSOC);



// Get subjects for selected class
$subjects = [];
if ($class_id) {
    $subjects_query = "SELECT id, name FROM subjects WHERE class_id = :class_id AND teacher_id = :teacher_id";
    $stmt = $db->prepare($subjects_query);
    $stmt->bindParam(':class_id', $class_id);
    $stmt->bindParam(':teacher_id', $teacher_id);
    $stmt->execute();
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle results submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        $class_id = sanitize($_POST['class_id']);
        $subject_id = sanitize($_POST['subject_id']);
        $session_id = $current_session['id']; // Get from current session
        $current_term = $current_session['current_term']; // Get from current term
        
        $success_count = 0;
        
        foreach ($_POST['ca_scores'] as $student_id => $ca_score) {
            $exam_score = sanitize($_POST['exam_scores'][$student_id]);
            $remark = sanitize($_POST['remark'][$student_id]);
            $total_score = $ca_score + $exam_score;
            
            // Calculate grade
            if ($total_score >= 80) $grade = 'A1';
            elseif ($total_score >= 70) $grade = 'B2';
            elseif ($total_score >= 65) $grade = 'B3';
            elseif ($total_score >= 60) $grade = 'C4';
            elseif ($total_score >= 55) $grade = 'C5';
            elseif ($total_score >= 50) $grade = 'C6';
            elseif ($total_score >= 45) $grade = 'D7';
            elseif ($total_score >= 40) $grade = 'E8';
            else $grade = 'F9';
            
            // Check if result already exists
            $check_query = "SELECT id FROM academic_results 
                           WHERE student_id = :student_id AND subject_id = :subject_id 
                           AND session_id = :session_id AND term = :term";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':student_id', $student_id);
            $check_stmt->bindParam(':subject_id', $subject_id);
            $check_stmt->bindParam(':session_id', $session_id);
            $check_stmt->bindParam(':term', $current_term);
            $check_stmt->execute();
            
            if ($check_stmt->fetch()) {
                // Update existing result
                $update_query = "UPDATE academic_results SET ca_score = :ca_score, exam_score = :exam_score,
                               total_score = :total_score, grade = :grade, remark = :remark, created_by = :created_by
                               WHERE student_id = :student_id AND subject_id = :subject_id 
                               AND session_id = :session_id AND term = :term";
                $stmt = $db->prepare($update_query);
            } else {
                // Insert new result
                $update_query = "INSERT INTO academic_results 
                               (student_id, subject_id, session_id, term, ca_score, exam_score, total_score, grade, remark, created_by) 
                               VALUES (:student_id, :subject_id, :session_id, :term, :ca_score, :exam_score, :total_score, :grade, :remark, :created_by)";
                $stmt = $db->prepare($update_query);
            }
            
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':subject_id', $subject_id);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->bindParam(':term', $current_term);
            $stmt->bindParam(':ca_score', $ca_score);
            $stmt->bindParam(':exam_score', $exam_score);
            $stmt->bindParam(':total_score', $total_score);
            $stmt->bindParam(':grade', $grade);
            $stmt->bindParam(':remark', $remark);
            $stmt->bindParam(':created_by', $teacher_id);
            
            if ($stmt->execute()) {
                $success_count++;
            }
        }
        
        $message = "Results saved successfully for $success_count students!";
    }
}

// Get students and their results for selected class and subject
$students = [];
if ($class_id && $subject_id) {
    $students_query = "SELECT s.id, s.student_id, u.full_name, 
                      ar.ca_score, ar.exam_score, ar.total_score, ar.grade, ar.remark
                      FROM students s 
                      JOIN users u ON s.user_id = u.id 
                      LEFT JOIN academic_results ar ON s.id = ar.student_id 
                      AND ar.subject_id = :subject_id 
                      AND ar.session_id = :session_id 
                      AND ar.term = :current_term
                      WHERE s.class_id = :class_id AND s.status = 'active' 
                      ORDER BY u.full_name";
    $stmt = $db->prepare($students_query);
    $stmt->bindParam(':session_id', $current_session['id']);
    $stmt->bindParam(':current_term', $current_session['current_term']);
    $stmt->bindParam(':class_id', $class_id);
    $stmt->bindParam(':subject_id', $subject_id);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Results Management - <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .grade-A { background-color: #d4edda !important; }
        .grade-B { background-color: #fff3cd !important; }
        .grade-C { background-color: #ffeaa7 !important; }
        .grade-D { background-color: #f8d7da !important; }
        .grade-F { background-color: #f5c6cb !important; }
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
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="subjects.php"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a class="nav-link active" href="results.php"><i class="fas fa-chart-line me-2"></i>Results</a>
            <a class="nav-link" href="exams.php"><i class="fas fa-laptop me-2"></i>CBT Exams</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Results Management</h2>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Select Class and Subject</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Class</label>
                        <select class="form-select" name="class_id" required onchange="this.form.submit()">
                            <option value="">Select Class</option>
                            <?php foreach ($assigned_classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo $class['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Subject</label>
                        <select class="form-select" name="subject_id" required onchange="this.form.submit()">
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo $subject['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($class_id && $subject_id && count($students) > 0): ?>
            <!-- Results Form -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Enter Results</h5>
                    <div>
                        <span class="me-5">Current Session : <span><?= $current_session['name']?></span></span>
                        <span>Current Term : <span class="badge bg-primary"><?= $current_session['current_term']?> term</span></span>
                    </div>
                    <div>
                        <span class="badge bg-primary me-2">CA: 0-30</span>
                        <span class="badge bg-success">Exam: 0-70</span>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                    
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>CA Score (30)</th>
                                        <th>Exam Score (70)</th>
                                        <th>Total Score</th>
                                        <th>Grade</th>
                                        <th>Remark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <?php
                                        $ca_score = $student['ca_score'] ?? 0;
                                        $exam_score = $student['exam_score'] ?? 0;
                                        $total_score = $student['total_score'] ?? 0;
                                        $grade = $student['grade'] ?? '';
                                        $remark = $student['remark'] ?? '';
                                        $grade_class = $grade ? 'grade-' . $grade : '';
                                        ?>
                                        <tr class="<?php echo $grade_class; ?>">
                                            <td><?php echo $student['student_id']; ?></td>
                                            <td><?php echo $student['full_name']; ?></td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm" 
                                                       name="ca_scores[<?php echo $student['id']; ?>]" 
                                                       value="<?php echo $ca_score; ?>" min="0" max="30" step="0.5"
                                                       onchange="calculateTotal(this, <?php echo $student['id']; ?>)">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm" 
                                                       name="exam_scores[<?php echo $student['id']; ?>]" 
                                                       value="<?php echo $exam_score; ?>" min="0" max="70" step="0.5"
                                                       onchange="calculateTotal(this, <?php echo $student['id']; ?>)">
                                            </td>
                                            <td>
                                                <span id="total_<?php echo $student['id']; ?>"><?php echo $total_score; ?></span>
                                            </td>
                                            <td>
                                                <span id="grade_<?php echo $student['id']; ?>" class="badge 
                                                    <?php echo $grade == 'A1' ? 'bg-success' : ''; ?>
                                                    <?php echo $grade == 'B2' ? 'bg-primary' : ''; ?>
                                                    <?php echo $grade == 'B3' ? 'bg-primary' : ''; ?>
                                                    <?php echo $grade == 'C4' ? 'bg-info' : ''; ?>
                                                    <?php echo $grade == 'C5' ? 'bg-info' : ''; ?>
                                                    <?php echo $grade == 'C6' ? 'bg-info' : ''; ?>
                                                    <?php echo $grade == 'D7' ? 'bg-warning' : ''; ?>
                                                    <?php echo $grade == 'E8' ? 'bg-warning' : ''; ?>
                                                    <?php echo $grade == 'F9' ? 'bg-danger' : ''; ?>">
                                                    <?php echo $grade; ?>
                                                </span>
                                            </td>

                                            <td>
                                                <input type="text" class="form-control form-control-sm" 
                                                name="remark[<?php echo $student['id']; ?>]"
                                                value="<?php echo $remark; ?>"
                                                id="remark_<?php echo $student['id']; ?>" readonly>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Results
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php elseif ($class_id && $subject_id): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                No students found in this class or the class is empty.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Please select a class and subject to enter results.
            </div>
        <?php endif; ?>
    </div>

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
        function calculateTotal(input, studentId) {
            const row = input.closest('tr');
            const caInput = row.querySelector('input[name="ca_scores[' + studentId + ']"]');
            const examInput = row.querySelector('input[name="exam_scores[' + studentId + ']"]');
            const totalSpan = document.getElementById('total_' + studentId);
            const gradeSpan = document.getElementById('grade_' + studentId);
            const remark = document.getElementById('remark_' + studentId);
            
            const caScore = parseFloat(caInput.value) || 0;
            const examScore = parseFloat(examInput.value) || 0;
            const totalScore = caScore + examScore;
            
            totalSpan.textContent = totalScore.toFixed(1);
            
            // Calculate grade
            let grade = 'F9';
            let gradeClass = 'bg-danger';
            let remarktxt = 'Fail';
            if (totalScore >= 80) {
                grade = 'A1';
                gradeClass = 'bg-success';
                remarktxt = 'Excellent';
            } else if (totalScore >= 70) {
                grade = 'B2';
                gradeClass = 'bg-primary';
                remarktxt = 'Very Good';
            } else if (totalScore >= 65) {
                grade = 'B3';
                gradeClass = 'bg-primary';
                remarktxt = 'Good';
            } else if (totalScore >= 60) {
                grade = 'C4';
                gradeClass = 'bg-info';
                remarktxt = 'Credit';
            } else if (totalScore >= 55) {
                grade = 'C5';
                gradeClass = 'bg-info';
                remarktxt = 'Credit';
            } else if (totalScore >= 50) {
                grade = 'C6';
                gradeClass = 'bg-info';
                remarktxt = 'Credit';
            }  else if (totalScore >= 45) {
                grade = 'D7';
                gradeClass = 'bg-warning';
                remarktxt = 'Pass';
            }else if (totalScore >= 40) {
                grade = 'E8';
                gradeClass = 'bg-warning';
                remarktxt = 'Pass';
            }
            
            gradeSpan.textContent = grade;
            gradeSpan.className = 'badge ' + gradeClass;
            remark.value = remarktxt;
            // Update row background color
            row.className = row.className.replace(/grade-[A-F]/, '');
            row.classList.add('grade-' + grade);
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>