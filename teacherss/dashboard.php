<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('teacher')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();
$teacher_id = $_SESSION['user_id'];

// Get teacher details
$teacher_query = "SELECT u.*, s.staff_id, s.qualification, s.specialization 
                 FROM users u 
                 JOIN staff s ON u.id = s.user_id 
                 WHERE u.id = :teacher_id";
$stmt = $db->prepare($teacher_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Get assigned classes and subjects
$subjects_query = "SELECT s.id, s.name, s.code, c.name as class_name, c.section 
                  FROM subjects s 
                  JOIN classes c ON s.class_id = c.id 
                  WHERE s.teacher_id = :teacher_id 
                  ORDER BY c.section, c.name";
$stmt = $db->prepare($subjects_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$assigned_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total students in assigned classes
$students_count = 0;
foreach ($assigned_subjects as $subject) {
    $count_query = "SELECT COUNT(*) FROM students WHERE class_id = 
                   (SELECT class_id FROM subjects WHERE id = :subject_id)";
    $stmt = $db->prepare($count_query);
    $stmt->bindParam(':subject_id', $subject['id']);
    $stmt->execute();
    $students_count += $stmt->fetchColumn();
}

// Get upcoming CBT exams
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

// Get recent results to grade
$ungraded_query = "SELECT COUNT(*) as ungraded_count 
                  FROM academic_results ar 
                  JOIN subjects s ON ar.subject_id = s.id 
                  WHERE s.teacher_id = :teacher_id 
                  AND ar.ca_score = 0 AND ar.exam_score = 0";
$stmt = $db->prepare($ungraded_query);
$stmt->bindParam(':teacher_id', $teacher_id);
$stmt->execute();
$ungraded_count = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Excel Schools</title>
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
            <p class="text-muted small">Teacher Panel</p>
        </div>
        <nav class="nav flex-column scrollable-menu">
            <a class="nav-link active" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="subjects.php"><i class="fas fa-book me-2"></i>My Subjects</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Attendance</a>
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
            <h2>Teacher Dashboard</h2>
            <div class="text-end">
                <h6 class="mb-0">Welcome, <?php echo $teacher['full_name']; ?></h6>
                <small class="text-muted">Staff ID: <?php echo $teacher['staff_id']; ?></small>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card text-white bg-primary stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo count($assigned_subjects); ?></h4>
                                <p class="mb-0">Assigned Subjects</p>
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
                                <h4><?php echo $students_count; ?></h4>
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
                <div class="card text-white bg-danger stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $ungraded_count; ?></h4>
                                <p class="mb-0">Results to Grade</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tasks fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Assigned Subjects -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">My Subjects</h5>
                        <a href="subjects.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Class</th>
                                        <th>Section</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_subjects as $subject): ?>
                                        <tr>
                                            <td><?php echo $subject['name']; ?></td>
                                            <td><?php echo $subject['class_name']; ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php echo $subject['section'] == 'primary' ? 'bg-primary' : ''; ?>
                                                    <?php echo $subject['section'] == 'junior' ? 'bg-success' : ''; ?>
                                                    <?php echo $subject['section'] == 'senior' ? 'bg-info' : ''; ?>">
                                                    <?php echo strtoupper($subject['section']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Exams -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Upcoming CBT Exams</h5>
                        <a href="cbt.php" class="btn btn-sm btn-primary">Manage Exams</a>
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
                                        <p class="mb-1"><?php echo $exam['subject_name']; ?> - <?php echo $exam['class_name']; ?></p>
                                        <small class="text-muted">Duration: <?php echo $exam['duration_minutes']; ?> mins</small>
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

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <a href="attendance.php" class="btn btn-outline-primary btn-lg w-100 py-3">
                                    <i class="fas fa-calendar-check fa-2x mb-2"></i><br>
                                    Take Attendance
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="results.php?action=add" class="btn btn-outline-success btn-lg w-100 py-3">
                                    <i class="fas fa-chart-line fa-2x mb-2"></i><br>
                                    Enter Results
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="cbt.php?action=create" class="btn btn-outline-info btn-lg w-100 py-3">
                                    <i class="fas fa-laptop fa-2x mb-2"></i><br>
                                    Create CBT
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="timetable.php" class="btn btn-outline-warning btn-lg w-100 py-3">
                                    <i class="fas fa-table fa-2x mb-2"></i><br>
                                    View Timetable
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