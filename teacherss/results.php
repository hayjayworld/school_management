<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$teacher_id = $_SESSION['user_id'];

// Get teacher's assigned classes and subjects
$query = "SELECT s.id, s.name, c.name as class_name 
          FROM subjects s 
          JOIN classes c ON s.class_id = c.id 
          WHERE s.teacher_id = :teacher_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle result submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_results'])) {
    $csrf_token = sanitize($_POST['csrf_token']);
    $subject_id = sanitize($_POST['subject_id']);
    $session_id = sanitize($_POST['session_id']);
    $term = sanitize($_POST['term']);
    
    if (validateCSRFToken($csrf_token)) {
        $success_count = 0;
        
        foreach ($_POST['scores'] as $student_id => $scores) {
            $ca_score = floatval($scores['ca']);
            $exam_score = floatval($scores['exam']);
            $total_score = $ca_score + $exam_score;
            
            // Calculate grade
            if ($total_score >= 80) {
                $grade = 'A';
                $remark = 'Excellent';
            } elseif ($total_score >= 70) {
                $grade = 'B';
                $remark = 'Very Good';
            } elseif ($total_score >= 60) {
                $grade = 'C';
                $remark = 'Good';
            } elseif ($total_score >= 50) {
                $grade = 'D';
                $remark = 'Fair';
            } else {
                $grade = 'F';
                $remark = 'Fail';
            }
            
            // Check if result exists
            $check_query = "SELECT id FROM academic_results 
                           WHERE student_id = :student_id 
                           AND subject_id = :subject_id 
                           AND session_id = :session_id 
                           AND term = :term";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':student_id', $student_id);
            $check_stmt->bindParam(':subject_id', $subject_id);
            $check_stmt->bindParam(':session_id', $session_id);
            $check_stmt->bindParam(':term', $term);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // Update existing result
                $update_query = "UPDATE academic_results 
                               SET ca_score = :ca_score, exam_score = :exam_score, 
                                   total_score = :total_score, grade = :grade, remark = :remark,
                                   updated_at = CURRENT_TIMESTAMP 
                               WHERE student_id = :student_id 
                               AND subject_id = :subject_id 
                               AND session_id = :session_id 
                               AND term = :term";
                $update_stmt = $db->prepare($update_query);
            } else {
                // Insert new result
                $update_query = "INSERT INTO academic_results 
                               (student_id, subject_id, session_id, term, ca_score, exam_score, 
                                total_score, grade, remark, created_by) 
                               VALUES (:student_id, :subject_id, :session_id, :term, :ca_score, 
                                      :exam_score, :total_score, :grade, :remark, :teacher_id)";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':teacher_id', $teacher_id);
            }
            
            $update_stmt->bindParam(':student_id', $student_id);
            $update_stmt->bindParam(':subject_id', $subject_id);
            $update_stmt->bindParam(':session_id', $session_id);
            $update_stmt->bindParam(':term', $term);
            $update_stmt->bindParam(':ca_score', $ca_score);
            $update_stmt->bindParam(':exam_score', $exam_score);
            $update_stmt->bindParam(':total_score', $total_score);
            $update_stmt->bindParam(':grade', $grade);
            $update_stmt->bindParam(':remark', $remark);
            
            if ($update_stmt->execute()) {
                $success_count++;
            }
        }
        
        $message = "Successfully updated $success_count student results.";
        $message_type = "success";
    } else {
        $message = "Security token invalid. Please try again.";
        $message_type = "error";
    }
}

// Get current session
$session_query = "SELECT * FROM academic_sessions WHERE status = 'active' LIMIT 1";
$current_session = $db->query($session_query)->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Management - Excel Schools</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { 
            background: #2c3e50; 
            color: white; 
            height: 100vh; 
            position: fixed; 
            width: 250px; 
            overflow-y: auto;
        }
        .sidebar .nav-link { 
            color: white; 
            padding: 15px 20px; 
            border-bottom: 1px solid #34495e; 
        }
        .sidebar .nav-link:hover { background: #34495e; }
        .sidebar .nav-link.active { background: #3498db; }
        .main-content { margin-left: 250px; padding: 20px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-4 text-center">
            <h4><i class="fas fa-graduation-cap"></i> Excel Schools</h4>
            <p class="text-muted small">Teacher Panel</p>
        </div>
        <nav class="nav flex-column scrollable-menu">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="subjects.php"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a class="nav-link active" href="results.php"><i class="fas fa-chart-line me-2"></i>Results</a>
            <a class="nav-link" href="exams.php"><i class="fas fa-file-alt me-2"></i>CBT Exams</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2>Result Management</h2>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="me-3">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Result Entry Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Enter Student Results</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="resultForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Select Subject</label>
                                    <select class="form-select" name="subject_id" id="subjectSelect" required>
                                        <option value="">Choose subject...</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>">
                                                <?php echo $subject['name'] . ' - ' . $subject['class_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Academic Session</label>
                                    <select class="form-select" name="session_id" required>
                                        <option value="<?php echo $current_session['id']; ?>">
                                            <?php echo $current_session['name']; ?>
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Term</label>
                                    <select class="form-select" name="term" required>
                                        <option value="first">First Term</option>
                                        <option value="second">Second Term</option>
                                        <option value="third">Third Term</option>
                                    </select>
                                </div>
                            </div>

                            <div id="studentsSection" style="display: none;">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Student ID</th>
                                                <th>Student Name</th>
                                                <th>CA Score (30%)</th>
                                                <th>Exam Score (70%)</th>
                                                <th>Total</th>
                                                <th>Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody id="studentsTable">
                                            <!-- Students will be loaded here via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                                
                                <button type="submit" name="submit_results" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Save Results
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('subjectSelect').addEventListener('change', function() {
            const subjectId = this.value;
            if (subjectId) {
                // Load students for selected subject
                fetch('get_students.php?subject_id=' + subjectId)
                    .then(response => response.json())
                    .then(data => {
                        const tableBody = document.getElementById('studentsTable');
                        tableBody.innerHTML = '';
                        
                        data.forEach(student => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${student.student_id}</td>
                                <td>${student.full_name}</td>
                                <td>
                                    <input type="number" name="scores[${student.id}][ca]" 
                                           class="form-control form-control-sm ca-score" 
                                           min="0" max="30" step="0.5" value="${student.ca_score || 0}">
                                </td>
                                <td>
                                    <input type="number" name="scores[${student.id}][exam]" 
                                           class="form-control form-control-sm exam-score" 
                                           min="0" max="70" step="0.5" value="${student.exam_score || 0}">
                                </td>
                                <td>
                                    <span class="total-score">0</span>
                                </td>
                                <td>
                                    <span class="grade">-</span>
                                </td>
                            `;
                            tableBody.appendChild(row);
                        });
                        
                        document.getElementById('studentsSection').style.display = 'block';
                        
                        // Add event listeners for score calculation
                        document.querySelectorAll('.ca-score, .exam-score').forEach(input => {
                            input.addEventListener('input', calculateScores);
                        });
                        calculateScores();
                    });
            } else {
                document.getElementById('studentsSection').style.display = 'none';
            }
        });

        function calculateScores() {
            document.querySelectorAll('tr').forEach(row => {
                const caInput = row.querySelector('.ca-score');
                const examInput = row.querySelector('.exam-score');
                const totalSpan = row.querySelector('.total-score');
                const gradeSpan = row.querySelector('.grade');
                
                if (caInput && examInput) {
                    const ca = parseFloat(caInput.value) || 0;
                    const exam = parseFloat(examInput.value) || 0;
                    const total = ca + exam;
                    
                    totalSpan.textContent = total.toFixed(1);
                    
                    // Calculate grade
                    let grade = '-';
                    if (total >= 80) grade = 'A';
                    else if (total >= 70) grade = 'B';
                    else if (total >= 60) grade = 'C';
                    else if (total >= 50) grade = 'D';
                    else if (total > 0) grade = 'F';
                    
                    gradeSpan.textContent = grade;
                    gradeSpan.className = 'grade badge bg-' + 
                        (grade === 'A' ? 'success' : 
                         grade === 'B' ? 'info' : 
                         grade === 'C' ? 'warning' : 
                         grade === 'D' ? 'primary' : 'danger');
                }
            });
        }
    </script>
</body>
</html>