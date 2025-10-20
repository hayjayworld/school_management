<?php
require_once '../includes/config.php';

// Check if student is logged into CBT portal
if (!isset($_SESSION['cbt_logged_in']) || !$_SESSION['cbt_logged_in']) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$student_id = $_SESSION['student_id'];
$class_id = $_SESSION['cbt_class_id'];

// Get available exams for the student's class
$current_time = date('Y-m-d H:i:s');
$exams_query = "SELECT ce.*, s.name as subject_name, s.code as subject_code,
                       (SELECT COUNT(*) FROM cbt_results WHERE exam_id = ce.id AND student_id = :student_id) as attempted
                FROM cbt_exams ce 
                JOIN subjects s ON ce.subject_id = s.id 
                WHERE ce.class_id = :class_id 
                AND ce.status = 'active'
                AND ce.start_time <= :current_time 
                AND ce.end_time >= :current_time
                ORDER BY ce.start_time";
$stmt = $db->prepare($exams_query);
$stmt->bindParam(':class_id', $class_id);
$stmt->bindParam(':student_id', $student_id);
$stmt->bindParam(':current_time', $current_time);
$stmt->execute();
$available_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get completed exams
$completed_query = "SELECT ce.*, s.name as subject_name, cr.score, cr.total_marks, cr.percentage, cr.grade
                    FROM cbt_results cr 
                    JOIN cbt_exams ce ON cr.exam_id = ce.id 
                    JOIN subjects s ON ce.subject_id = s.id 
                    WHERE cr.student_id = :student_id 
                    ORDER BY cr.submitted_at DESC 
                    LIMIT 5";
$stmt = $db->prepare($completed_query);
$stmt->bindParam(':student_id', $student_id);
$stmt->execute();
$completed_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Portal - Excel Schools</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }
        .navbar-cbt {
            background: #2c3e50;
        }
        .exam-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .exam-card:hover {
            transform: translateY(-5px);
        }
        .available-exam {
            border-left: 4px solid #28a745;
        }
        .attempted-exam {
            border-left: 4px solid #ffc107;
        }
        .completed-exam {
            border-left: 4px solid #17a2b8;
        }
        .countdown-timer {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-cbt">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap me-2"></i>
                Excel Schools - CBT Portal
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo $_SESSION['cbt_full_name']; ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Student Info -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="card-title">Student Information</h5>
                                <p class="mb-1"><strong>Name:</strong> <?php echo $_SESSION['cbt_full_name']; ?></p>
                                <p class="mb-1"><strong>Class:</strong> <?php echo $_SESSION['cbt_class_name']; ?></p>
                                <p class="mb-0"><strong>Student ID:</strong> <?php echo $_SESSION['cbt_student_id']; ?></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Server Time: <span id="serverTime"><?php echo date('Y-m-d H:i:s'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Exams -->
        <div class="row mb-4">
            <div class="col-12">
                <h3 class="mb-3">Available Exams</h3>
                <?php if (count($available_exams) > 0): ?>
                    <div class="row">
                        <?php foreach ($available_exams as $exam): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card exam-card <?php echo $exam['attempted'] ? 'attempted-exam' : 'available-exam'; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title"><?php echo $exam['title']; ?></h5>
                                            <span class="badge bg-<?php echo $exam['attempted'] ? 'warning' : 'success'; ?>">
                                                <?php echo $exam['attempted'] ? 'Attempted' : 'Available'; ?>
                                            </span>
                                        </div>
                                        
                                        <p class="card-text">
                                            <strong>Subject:</strong> <?php echo $exam['subject_name']; ?> (<?php echo $exam['subject_code']; ?>)
                                        </p>
                                        
                                        <p class="card-text">
                                            <strong>Duration:</strong> <?php echo $exam['duration_minutes']; ?> minutes
                                        </p>
                                        
                                        <p class="card-text">
                                            <strong>Questions:</strong> <?php echo $exam['total_questions']; ?> questions
                                        </p>
                                        
                                        <p class="card-text">
                                            <strong>Total Marks:</strong> <?php echo $exam['total_marks']; ?> marks
                                        </p>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <strong>Time Window:</strong><br>
                                                Start: <?php echo date('M j, Y g:i A', strtotime($exam['start_time'])); ?><br>
                                                End: <?php echo date('M j, Y g:i A', strtotime($exam['end_time'])); ?>
                                            </small>
                                        </div>

                                        <?php if ($exam['attempted']): ?>
                                            <button class="btn btn-warning w-100" disabled>
                                                <i class="fas fa-check-circle me-2"></i>Already Attempted
                                            </button>
                                        <?php else: ?>
                                            <a href="exam.php?exam_id=<?php echo $exam['id']; ?>" 
                                               class="btn btn-success w-100"
                                               onclick="return confirm('Are you ready to start the exam? Once started, the timer will begin.')">
                                                <i class="fas fa-play-circle me-2"></i>Start Exam
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No exams are currently available for you. Please check back later.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Completed Exams -->
        <?php if (count($completed_exams) > 0): ?>
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-3">Recent Exam Results</h3>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Exam Title</th>
                                            <th>Subject</th>
                                            <th>Score</th>
                                            <th>Percentage</th>
                                            <th>Grade</th>
                                            <th>Date Taken</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($completed_exams as $exam): ?>
                                            <tr>
                                                <td><?php echo $exam['title']; ?></td>
                                                <td><?php echo $exam['subject_name']; ?></td>
                                                <td><?php echo $exam['score']; ?>/<?php echo $exam['total_marks']; ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?php echo $exam['percentage'] >= 70 ? 'bg-success' : ''; ?>
                                                        <?php echo $exam['percentage'] >= 50 && $exam['percentage'] < 70 ? 'bg-warning' : ''; ?>
                                                        <?php echo $exam['percentage'] < 50 ? 'bg-danger' : ''; ?>">
                                                        <?php echo $exam['percentage']; ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge 
                                                        <?php echo $exam['grade'] == 'A' ? 'bg-success' : ''; ?>
                                                        <?php echo $exam['grade'] == 'B' ? 'bg-primary' : ''; ?>
                                                        <?php echo $exam['grade'] == 'C' ? 'bg-info' : ''; ?>
                                                        <?php echo $exam['grade'] == 'D' ? 'bg-warning' : ''; ?>
                                                        <?php echo $exam['grade'] == 'F' ? 'bg-danger' : ''; ?>">
                                                        <?php echo $exam['grade']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($exam['submitted_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Update server time every second
        function updateServerTime() {
            const now = new Date();
            const serverTimeElem = document.getElementById('serverTime');
            serverTimeElem.textContent = now.toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }
        
        setInterval(updateServerTime, 1000);
        updateServerTime();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>