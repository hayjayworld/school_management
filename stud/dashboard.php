<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$student_id = $_SESSION['user_id'];

// Get student details
$student_query = "SELECT s.*, u.full_name, u.gender, c.name as class_name, c.section 
                 FROM students s 
                 JOIN users u ON s.user_id = u.id 
                 JOIN classes c ON s.class_id = c.id 
                 WHERE s.user_id = :user_id";
$stmt = $db->prepare($student_query);
$stmt->bindParam(':user_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current academic results summary
$results_query = "SELECT ar.subject_id, s.name as subject_name, 
                 AVG(ar.total_score) as average_score,
                 MAX(ar.total_score) as highest_score
                 FROM academic_results ar
                 JOIN subjects s ON ar.subject_id = s.id
                 WHERE ar.student_id = :student_id 
                 AND ar.session_id = :session_id
                 AND ar.term = :current_term
                 GROUP BY ar.subject_id, s.name";
$stmt = $db->prepare($results_query);
$stmt->bindParam(':student_id', $student['id']);
$stmt->bindParam(':session_id', $student['session_id']);
$stmt->bindParam(':current_term', $student['current_term']);
$stmt->execute();
$results_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming CBT exams
$exams_query = "SELECT ce.*, s.name as subject_name, c.name as class_name 
               FROM cbt_exams ce 
               JOIN subjects s ON ce.subject_id = s.id 
               JOIN classes c ON ce.class_id = c.id 
               WHERE ce.class_id = :class_id 
               AND ce.start_time > NOW() 
               AND ce.status = 'active'
               ORDER BY ce.start_time 
               LIMIT 5";
$stmt = $db->prepare($exams_query);
$stmt->bindParam(':class_id', $student['class_id']);
$stmt->execute();
$upcoming_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance summary
$attendance_query = "SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
                    FROM attendance 
                    WHERE student_id = :student_id 
                    AND session_id = :session_id
                    AND term = :current_term";
$stmt = $db->prepare($attendance_query);
$stmt->bindParam(':student_id', $student['id']);
$stmt->bindParam(':session_id', $student['session_id']);
$stmt->bindParam(':current_term', $student['current_term']);
$stmt->execute();
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent announcements/events
$events_query = "SELECT title, event_date, event_type 
                FROM events 
                WHERE (target_audience = 'all' OR target_audience = 'students')
                AND is_published = 1 
                AND event_date >= CURDATE()
                ORDER BY event_date 
                LIMIT 5";
$events = $db->query($events_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Excel Schools</title>
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
        .stat-card { border-radius: 10px; margin-bottom: 20px; }
        .scrollable-menu { max-height: calc(100vh - 120px); overflow-y: auto; }
        .progress { height: 10px; }
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-4 text-center">
            <h4><i class="fas fa-graduation-cap"></i> Excel Schools</h4>
            <p class="text-muted small">Student Portal</p>
        </div>
        <nav class="nav flex-column scrollable-menu">
            <a class="nav-link active" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a>
            <a class="nav-link" href="results.php"><i class="fas fa-chart-line me-2"></i>My Results</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
            <a class="nav-link" href="cbt.php"><i class="fas fa-laptop me-2"></i>CBT Exams</a>
            <a class="nav-link" href="timetable.php"><i class="fas fa-table me-2"></i>Class Timetable</a>
            <a class="nav-link" href="events.php"><i class="fas fa-calendar me-2"></i>School Events</a>
            <a class="nav-link" href="library.php"><i class="fas fa-book me-2"></i>Library</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Student Dashboard</h2>
            <div class="text-end">
                <h6 class="mb-0">Welcome, <?php echo $student['full_name']; ?></h6>
                <small class="text-muted">
                    <?php echo $student['class_name']; ?> • 
                    Student ID: <?php echo $student['student_id']; ?>
                </small>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-primary stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo count($results_summary); ?></h4>
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
                <div class="card text-white bg-success stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $attendance['present_days'] ?? 0; ?></h4>
                                <p class="mb-0">Days Present</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-check fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-warning stat-card">
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
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-info stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $attendance['total_days'] > 0 ? 
                                    round(($attendance['present_days'] / $attendance['total_days']) * 100) : 0; ?>%</h4>
                                <p class="mb-0">Attendance Rate</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chart-line fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Academic Performance -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Academic Performance</h5>
                        <a href="results.php" class="btn btn-sm btn-primary">View All Results</a>
                    </div>
                    <div class="card-body">
                        <?php if ($results_summary): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Average</th>
                                            <th>Highest</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results_summary as $result): ?>
                                            <tr>
                                                <td><?php echo $result['subject_name']; ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?php echo $result['average_score'] >= 70 ? 'bg-success' : ''; ?>
                                                        <?php echo $result['average_score'] >= 50 && $result['average_score'] < 70 ? 'bg-warning' : ''; ?>
                                                        <?php echo $result['average_score'] < 50 ? 'bg-danger' : ''; ?>">
                                                        <?php echo number_format($result['average_score'], 1); ?>%
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($result['highest_score'], 1); ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No results available for current term</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Exams -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Upcoming CBT Exams</h5>
                        <a href="cbt.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if ($upcoming_exams): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($upcoming_exams as $exam): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $exam['title']; ?></h6>
                                            <small><?php echo date('M j', strtotime($exam['start_time'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo $exam['subject_name']; ?></p>
                                        <small class="text-muted">
                                            Duration: <?php echo $exam['duration_minutes']; ?> mins
                                            <?php if (strtotime($exam['start_time']) <= time() && strtotime($exam['end_time']) >= time()): ?>
                                                <span class="badge bg-success ms-2">Available Now</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No upcoming exams</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Attendance Summary -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Attendance Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($attendance['total_days'] > 0): ?>
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="text-success">
                                        <h4><?php echo $attendance['present_days']; ?></h4>
                                        <small>Present</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-danger">
                                        <h4><?php echo $attendance['absent_days']; ?></h4>
                                        <small>Absent</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-warning">
                                        <h4><?php echo $attendance['late_days']; ?></h4>
                                        <small>Late</small>
                                    </div>
                                </div>
                            </div>
                            <div class="progress mb-2">
                                <div class="progress-bar bg-success" 
                                     style="width: <?php echo ($attendance['present_days'] / $attendance['total_days']) * 100; ?>%">
                                </div>
                            </div>
                            <small class="text-muted">
                                Attendance Rate: <?php echo round(($attendance['present_days'] / $attendance['total_days']) * 100); ?>%
                            </small>
                        <?php else: ?>
                            <p class="text-muted text-center">No attendance records found</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- School Events -->
            <div class="col-lg-6 mb-4">
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
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Access</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <a href="cbt.php" class="btn btn-outline-primary btn-lg w-100 py-3">
                                    <i class="fas fa-laptop fa-2x mb-2"></i><br>
                                    Take CBT Exam
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="results.php" class="btn btn-outline-success btn-lg w-100 py-3">
                                    <i class="fas fa-chart-line fa-2x mb-2"></i><br>
                                    View Results
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="timetable.php" class="btn btn-outline-info btn-lg w-100 py-3">
                                    <i class="fas fa-table fa-2x mb-2"></i><br>
                                    Class Timetable
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="library.php" class="btn btn-outline-warning btn-lg w-100 py-3">
                                    <i class="fas fa-book fa-2x mb-2"></i><br>
                                    Library
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>