<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !hasRole('student')) {
    redirect('../auth/login.php');
}

$database = new Database();
$db = $database->getConnection();

$student_id = $_SESSION['user_id'];

// Get student info
$student_query = "SELECT s.* FROM students s WHERE s.user_id = :user_id";
$stmt = $db->prepare($student_query);
$stmt->bindParam(':user_id', $student_id);
$stmt->execute();
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get CBT results
$cbt_results_query = "SELECT r.*, e.title as exam_title, s.name as subject_name 
                     FROM cbt_results r 
                     JOIN cbt_exams e ON r.exam_id = e.id 
                     JOIN subjects s ON e.subject_id = s.id 
                     WHERE r.student_id = :student_id 
                     ORDER BY r.submitted_at DESC";
$stmt = $db->prepare($cbt_results_query);
$stmt->bindParam(':student_id', $student['id']);
$stmt->execute();
$cbt_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get academic results
$academic_results_query = "SELECT r.*, s.name as subject_name, ses.name as session_name 
                          FROM academic_results r 
                          JOIN subjects s ON r.subject_id = s.id 
                          JOIN academic_sessions ses ON r.session_id = ses.id 
                          WHERE r.student_id = :student_id 
                          ORDER BY ses.name, r.term, s.name";
$stmt = $db->prepare($academic_results_query);
$stmt->bindParam(':student_id', $student['id']);
$stmt->execute();
$academic_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Results - Excel Schools</title>
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

            <!-- Main content -->
            <main class="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2>My Results</h2>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="me-3">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                    </div>
                </div>

                <!-- CBT Results -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-desktop me-2"></i>CBT Exam Results
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cbt_results)): ?>
                            <p class="text-muted">No CBT exam results available yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Exam Title</th>
                                            <th>Subject</th>
                                            <th>Score</th>
                                            <th>Percentage</th>
                                            <th>Date Taken</th>
                                            <th>Time Taken</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cbt_results as $result): ?>
                                            <tr>
                                                <td><?php echo $result['exam_title']; ?></td>
                                                <td><?php echo $result['subject_name']; ?></td>
                                                <td><?php echo $result['score']; ?>/<?php echo $result['total_marks']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $result['percentage'] >= 70 ? 'success' : ($result['percentage'] >= 50 ? 'warning' : 'danger'); ?>">
                                                        <?php echo $result['percentage']; ?>%
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($result['submitted_at'])); ?></td>
                                                <td><?php echo $result['time_taken']; ?> mins</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Academic Results -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-graduation-cap me-2"></i>Academic Results
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($academic_results)): ?>
                            <p class="text-muted">No academic results available yet.</p>
                        <?php else: ?>
                            <?php
                            // Group results by session and term
                            $grouped_results = [];
                            foreach ($academic_results as $result) {
                                $key = $result['session_name'] . '|' . $result['term'];
                                if (!isset($grouped_results[$key])) {
                                    $grouped_results[$key] = [
                                        'session' => $result['session_name'],
                                        'term' => $result['term'],
                                        'results' => []
                                    ];
                                }
                                $grouped_results[$key]['results'][] = $result;
                            }
                            ?>

                            <?php foreach ($grouped_results as $group): ?>
                                <div class="mb-4">
                                    <h6 class="text-primary">
                                        <?php echo $group['session']; ?> - <?php echo ucfirst($group['term']); ?> Term
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>CA Score</th>
                                                    <th>Exam Score</th>
                                                    <th>Total</th>
                                                    <th>Grade</th>
                                                    <th>Remark</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $total_score = 0;
                                                $subject_count = 0;
                                                ?>
                                                <?php foreach ($group['results'] as $result): ?>
                                                    <?php 
                                                    $total_score += $result['total_score'];
                                                    $subject_count++;
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $result['subject_name']; ?></td>
                                                        <td><?php echo $result['ca_score']; ?></td>
                                                        <td><?php echo $result['exam_score']; ?></td>
                                                        <td><?php echo $result['total_score']; ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $result['grade'] == 'A' ? 'success' : 
                                                                    ($result['grade'] == 'B' ? 'info' : 
                                                                    ($result['grade'] == 'C' ? 'warning' : 
                                                                    ($result['grade'] == 'D' ? 'primary' : 'danger'))); 
                                                            ?>">
                                                                <?php echo $result['grade']; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $result['remark']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if ($subject_count > 0): ?>
                                                    <tr class="table-info">
                                                        <td colspan="3" class="text-end"><strong>Average:</strong></td>
                                                        <td colspan="3">
                                                            <strong><?php echo number_format($total_score / $subject_count, 2); ?>%</strong>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <hr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Result Summary -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Performance Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-primary"><?php echo count($cbt_results); ?></h4>
                                    <p class="mb-0">Exams Taken</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-success">
                                        <?php
                                        $passed = array_filter($cbt_results, function($result) {
                                            return $result['percentage'] >= 50;
                                        });
                                        echo count($passed);
                                        ?>
                                    </h4>
                                    <p class="mb-0">Exams Passed</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-info"><?php echo count($academic_results); ?></h4>
                                    <p class="mb-0">Subjects Graded</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-warning">85%</h4>
                                    <p class="mb-0">Overall Average</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>