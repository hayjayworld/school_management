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
$date = $_GET['date'] ?? date('Y-m-d');

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

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = sanitize($_POST['csrf_token']);
    
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security token invalid.";
    } else {
        $class_id = sanitize($_POST['class_id']);
        $subject_id = sanitize($_POST['subject_id']);
        $attendance_date = sanitize($_POST['attendance_date']);
        $session_id = 1; // Get from current session
        $current_term = 'first'; // Get from current term
        
        // Get students for the class
        $students_query = "SELECT id FROM students WHERE class_id = :class_id AND status = 'active'";
        $stmt = $db->prepare($students_query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $success_count = 0;
        
        foreach ($students as $student) {
            $status = sanitize($_POST['attendance_' . $student['id']]) ?? 'absent';
            
            // Check if attendance already exists for this date
            $check_query = "SELECT id FROM attendance WHERE student_id = :student_id AND date = :date";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':student_id', $student['id']);
            $check_stmt->bindParam(':date', $attendance_date);
            $check_stmt->execute();
            
            if ($check_stmt->fetch()) {
                // Update existing attendance
                $update_query = "UPDATE attendance SET status = :status, recorded_by = :recorded_by 
                               WHERE student_id = :student_id AND date = :date";
                $stmt = $db->prepare($update_query);
            } else {
                // Insert new attendance
                $update_query = "INSERT INTO attendance (student_id, class_id, session_id, date, term, status, recorded_by) 
                               VALUES (:student_id, :class_id, :session_id, :date, :term, :status, :recorded_by)";
                $stmt = $db->prepare($update_query);
                $stmt->bindParam(':class_id', $class_id);
                $stmt->bindParam(':session_id', $session_id);
                $stmt->bindParam(':term', $current_term);
            }
            
            $stmt->bindParam(':student_id', $student['id']);
            $stmt->bindParam(':date', $attendance_date);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':recorded_by', $teacher_id);
            
            if ($stmt->execute()) {
                $success_count++;
            }
        }
        
        $message = "Attendance recorded successfully for $success_count students!";
    }
}

// Get students for selected class
$students = [];
if ($class_id) {
    $students_query = "SELECT s.id, s.student_id, u.full_name 
                      FROM students s 
                      JOIN users u ON s.user_id = u.id 
                      WHERE s.class_id = :class_id AND s.status = 'active' 
                      ORDER BY u.full_name";
    $stmt = $db->prepare($students_query);
    $stmt->bindParam(':class_id', $class_id);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get today's attendance for these students
    $attendance_query = "SELECT student_id, status FROM attendance WHERE date = :date";
    $stmt = $db->prepare($attendance_query);
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    $today_attendance = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Excel Schools</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { background: #2c3e50; color: white; min-height: 100vh; position: fixed; width: 250px; }
        .sidebar .nav-link { color: white; padding: 15px 20px; border-bottom: 1px solid #34495e; }
        .sidebar .nav-link:hover { background: #34495e; }
        .sidebar .nav-link.active { background: #3498db; }
        .main-content { margin-left: 250px; padding: 20px; }
        .attendance-badge { cursor: pointer; }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; min-height: auto; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-4 text-center">
            <h4><i class="fas fa-graduation-cap"></i> Excel Schools</h4>
            <p class="text-muted small">Teacher Panel</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="subjects.php"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a class="nav-link active" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-line me-2"></i>Results</a>
            <a class="nav-link" href="cbt.php"><i class="fas fa-laptop me-2"></i>CBT Exams</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>Events</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Student Attendance</h2>
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
                <h5 class="card-title mb-0">Select Class and Date</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
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
                    <div class="col-md-4">
                        <label class="form-label">Subject</label>
                        <select class="form-select" name="subject_id">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo $subject['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="date" value="<?php echo $date; ?>" onchange="this.form.submit()">
                    </div>
                </form>
            </div>
        </div>

        <?php if ($class_id && count($students) > 0): ?>
            <!-- Attendance Form -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Mark Attendance for <?php echo date('F j, Y', strtotime($date)); ?></h5>
                    <span class="badge bg-primary"><?php echo count($students); ?> Students</span>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                    <input type="hidden" name="attendance_date" value="<?php echo $date; ?>">
                    
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Attendance Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo $student['student_id']; ?></td>
                                            <td><?php echo $student['full_name']; ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <input type="radio" class="btn-check" name="attendance_<?php echo $student['id']; ?>" 
                                                           value="present" id="present_<?php echo $student['id']; ?>"
                                                           <?php echo ($today_attendance[$student['id']] ?? '') == 'present' ? 'checked' : ''; ?>>
                                                    <label class="btn btn-outline-success attendance-badge" for="present_<?php echo $student['id']; ?>">
                                                        <i class="fas fa-check me-1"></i>Present
                                                    </label>
                                                    
                                                    <input type="radio" class="btn-check" name="attendance_<?php echo $student['id']; ?>" 
                                                           value="absent" id="absent_<?php echo $student['id']; ?>"
                                                           <?php echo ($today_attendance[$student['id']] ?? '') == 'absent' ? 'checked' : ''; ?>>
                                                    <label class="btn btn-outline-danger attendance-badge" for="absent_<?php echo $student['id']; ?>">
                                                        <i class="fas fa-times me-1"></i>Absent
                                                    </label>
                                                    
                                                    <input type="radio" class="btn-check" name="attendance_<?php echo $student['id']; ?>" 
                                                           value="late" id="late_<?php echo $student['id']; ?>"
                                                           <?php echo ($today_attendance[$student['id']] ?? '') == 'late' ? 'checked' : ''; ?>>
                                                    <label class="btn btn-outline-warning attendance-badge" for="late_<?php echo $student['id']; ?>">
                                                        <i class="fas fa-clock me-1"></i>Late
                                                    </label>
                                                    
                                                    <input type="radio" class="btn-check" name="attendance_<?php echo $student['id']; ?>" 
                                                           value="excused" id="excused_<?php echo $student['id']; ?>"
                                                           <?php echo ($today_attendance[$student['id']] ?? '') == 'excused' ? 'checked' : ''; ?>>
                                                    <label class="btn btn-outline-info attendance-badge" for="excused_<?php echo $student['id']; ?>">
                                                        <i class="fas fa-user-clock me-1"></i>Excused
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Attendance
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php elseif ($class_id): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                No students found in this class or the class is empty.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Please select a class to view and mark attendance.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>